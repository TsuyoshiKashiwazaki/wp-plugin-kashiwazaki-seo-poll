<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** データファイルの形式。 */
function kashiwazaki_poll_data_file_types() {
    return array( 'csv', 'xml', 'yaml', 'json', 'svg' );
}

/**
 * 公開してよいデータセットか（公開状態で、パスワード保護されていない poll）。
 * データファイル・サイトマップ・公開ページ・投票・集計取得の判定はすべてこれに揃える。
 *
 * @param int|WP_Post $post
 * @return bool
 */
function kashiwazaki_poll_is_public_poll( $post ) {
    $post = get_post( $post );
    return $post
        && 'poll' === $post->post_type
        && 'publish' === $post->post_status
        && '' === (string) $post->post_password;
}

/**
 * データファイル・サイトマップ・XML に入れられない文字だけを取り除く（XML 1.0 で有効な文字は残す。絵文字など U+10000 以降も含む）。
 */
function kashiwazaki_poll_strip_invalid_xml_chars( $value ) {
    $clean = preg_replace( '/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]+/u', '', (string) $value );
    return null === $clean ? '' : $clean;
}

/**
 * データセット単位の排他（投票・投票数の編集・ファイルの生成と削除が同時に走って値やファイルを壊さないように）。
 * データベースの名前付きロック（GET_LOCK）を使う。ロックの仕組みが使えない環境では、処理を止めずに続ける。
 *
 * @return bool 取れた（または仕組みが無く続行してよい）とき true、他の処理が持ったまま時間切れのとき false
 */
function kashiwazaki_poll_acquire_lock( $poll_id, $timeout = 10 ) {
    return 'busy' !== kashiwazaki_poll_acquire_lock_state( $poll_id, $timeout );
}

/**
 * 排他を取り、結果を 3 通りで返す。
 *
 * @return string 'locked'（この接続で取れた）/ 'busy'（他の処理が持ったまま時間切れ）/ 'unavailable'（GET_LOCK が NULL を返し、仕組みが使えない）
 */
function kashiwazaki_poll_acquire_lock_state( $poll_id, $timeout = 10 ) {
    global $wpdb;
    $name   = kashiwazaki_poll_lock_name( $poll_id );
    $result = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $name, (int) $timeout ) );
    if ( null === $result ) {
        error_log( "[Poll Lock - ID:{$poll_id}] GET_LOCK unavailable; continuing without lock." );
        return 'unavailable';
    }
    return '1' === (string) $result ? 'locked' : 'busy';
}

function kashiwazaki_poll_release_lock( $poll_id ) {
    global $wpdb;
    $wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', kashiwazaki_poll_lock_name( $poll_id ) ) );
}

function kashiwazaki_poll_lock_name( $poll_id ) {
    global $wpdb;
    return 'kspoll_' . substr( md5( DB_NAME . '|' . $wpdb->prefix ), 0, 12 ) . '_' . (int) $poll_id;
}

/**
 * 一時ファイルに全部書けたことを確かめてから置き換える（途中で失敗しても前の正しいファイルを壊さない）。
 *
 * @return bool 書き込みと置き換えが成功したとき true
 */
function kashiwazaki_poll_write_file_atomic( $file_path, $content ) {
    if ( ! is_string( $content ) ) {
        return false;
    }
    $dir_path = dirname( $file_path );
    if ( ! file_exists( $dir_path ) && ! wp_mkdir_p( $dir_path ) ) {
        error_log( '[Poll Data Gen] Failed to create directory: ' . $dir_path );
        return false;
    }
    $tmp_path = $file_path . '.tmp-' . wp_generate_password( 8, false, false );
    $written  = @file_put_contents( $tmp_path, $content, LOCK_EX );
    if ( false === $written || strlen( $content ) !== $written ) {
        error_log( '[Poll Data Gen] Failed to write: ' . $file_path );
        if ( file_exists( $tmp_path ) ) {
            @unlink( $tmp_path );
        }
        return false;
    }
    if ( ! @rename( $tmp_path, $file_path ) ) {
        error_log( '[Poll Data Gen] Failed to replace: ' . $file_path );
        @unlink( $tmp_path );
        return false;
    }
    return true;
}

/**
 * YAML のスカラー値。文字列は常に二重引用符で囲み、必要な文字をエスケープする（引用符・空文字・改行・制御文字を含む値も元の文字列のまま読み戻せるように）。
 */
function _kashiwazaki_poll_yaml_quote_string( $value ) {
    if ( is_bool( $value ) ) {
        return $value ? 'true' : 'false';
    }
    if ( is_null( $value ) ) {
        return 'null';
    }
    if ( is_int( $value ) || is_float( $value ) ) {
        return (string) $value;
    }
    $value = (string) $value;
    $escaped = strtr( $value, array(
        '\\' => '\\\\',
        '"'  => '\\"',
        "\n" => '\\n',
        "\r" => '\\r',
        "\t" => '\\t',
    ) );
    $escaped = preg_replace_callback( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', function ( $m ) {
        return sprintf( '\\x%02X', ord( $m[0] ) );
    }, $escaped );
    return '"' . $escaped . '"';
}

/**
 * 公開中のデータセットのデータファイル5種を作る（作り直す）。
 *
 * @param int   $poll_id
 * @param mixed $counts       使わない（互換のため残す。生成時は排他の中で最新の票数を読む）
 * @param bool  $skip_sitemap 一括処理の呼び出し元が最後に一度だけサイトマップを作るとき true
 * @return bool 5 形式すべてを書けたとき true
 */
function kashiwazaki_poll_generate_all_data_files( $poll_id, $counts = null, $skip_sitemap = false ) {
    $poll_id = intval( $poll_id );
    if ( ! $poll_id ) {
        error_log( "[Poll Data Gen] Invalid poll ID provided: " . $poll_id );
        return false;
    }

    if ( ! kashiwazaki_poll_acquire_lock( $poll_id ) ) {
        error_log( "[Poll Data Gen - ID:{$poll_id}] Could not acquire lock." );
        return false;
    }

    // 排他の中で最新の状態を読み直す。公開していない（非公開・パスワード保護・ゴミ箱など）ときは作らず、残っているファイルを消す。
    clean_post_cache( $poll_id );
    $poll_post = get_post( $poll_id );
    if ( ! kashiwazaki_poll_is_public_poll( $poll_post ) ) {
        kashiwazaki_poll_delete_data_files_unlocked( $poll_id );
        kashiwazaki_poll_release_lock( $poll_id );
        if ( ! $skip_sitemap ) {
            kashiwazaki_poll_generate_sitemap_poll();
        }
        return false;
    }

    $results = kashiwazaki_poll_write_data_files_unlocked( $poll_post );
    // 書いている間に非公開・パスワード保護に変わっていたら、書いたファイルを消す（排他を持ったまま確かめる）。
    clean_post_cache( $poll_id );
    if ( ! kashiwazaki_poll_is_public_poll( $poll_id ) ) {
        kashiwazaki_poll_delete_data_files_unlocked( $poll_id );
        kashiwazaki_poll_release_lock( $poll_id );
        if ( ! $skip_sitemap ) {
            kashiwazaki_poll_generate_sitemap_poll();
        }
        return false;
    }
    // 公開中のファイルを書けたなら、以前の削除失敗の記録は不要。
    if ( ! in_array( false, $results, true ) ) {
        kashiwazaki_poll_record_delete_result( $poll_id, true );
    }
    kashiwazaki_poll_release_lock( $poll_id );

    // 一括再生成など多数の poll をまとめて処理する呼び出し元は $skip_sitemap=true を渡し、
    // ループ内での毎回のサイトマップ再生成（O(N^2) 化）を避けてループ完了後に一度だけ呼ぶ。
    if ( ! $skip_sitemap ) {
        kashiwazaki_poll_generate_sitemap_poll();
    }

    return ! in_array( false, $results, true );
}

/**
 * 排他を持った状態で、データファイル5種の中身を作って書く。
 *
 * @return array 形式 => 書けたか
 */
function kashiwazaki_poll_write_data_files_unlocked( $poll_post ) {
    $poll_id = (int) $poll_post->ID;
    wp_cache_delete( $poll_id, 'post_meta' );

    $poll_title_sanitized = kashiwazaki_poll_strip_invalid_xml_chars( kashiwazaki_poll_decode_stored_text( $poll_post->post_title ) );

    // ファイルの中身になる値はデータベースから直接読み、読み取りのエラーを確かめる（エラーを「データが無い」と
    // 取り違えて、0 票・選択肢なしのファイルで正しいファイルを置き換えないように）。読めなければ書かずに失敗を返す。
    $meta = kashiwazaki_poll_read_meta_strict( $poll_id, array( '_kashiwazaki_poll_options', '_kashiwazaki_poll_counts', '_kashiwazaki_poll_voted_ips', '_kashiwazaki_poll_license' ) );
    if ( null === $meta ) {
        error_log( "[Poll Data Gen - ID:{$poll_id}] Could not read poll data; files were not written." );
        return array_fill_keys( kashiwazaki_poll_data_file_types(), false );
    }
    $options = is_array( $meta['_kashiwazaki_poll_options'] ) ? array_map( 'kashiwazaki_poll_decode_stored_text', $meta['_kashiwazaki_poll_options'] ) : array();
    $options_sanitized = array_map( 'kashiwazaki_poll_strip_invalid_xml_chars', $options );

    $counts = kashiwazaki_poll_normalize_counts( $meta['_kashiwazaki_poll_counts'], count( $options_sanitized ) );

    $total_votes = empty($counts) ? 0 : array_sum( $counts );
    $last_updated_formatted = wp_date( 'c', time() );

    // 調査期間（最初と最後の投票時刻）
    $voted_ips = $meta['_kashiwazaki_poll_voted_ips'];
    $survey_period = ( is_array( $voted_ips ) && ! empty( $voted_ips ) ) ? array( 'start' => min( $voted_ips ), 'end' => max( $voted_ips ) ) : null;
    $survey_period_start = $survey_period ? wp_date( 'c', $survey_period['start'] ) : null;
    $survey_period_end = $survey_period ? wp_date( 'c', $survey_period['end'] ) : null;

    $poll_license = $meta['_kashiwazaki_poll_license'];
    if ( empty( $poll_license ) ) {
        $poll_license = 'https://creativecommons.org/licenses/by/4.0/';
    }
    $poll_license_sanitized = filter_var($poll_license, FILTER_SANITIZE_URL);
    if (empty($poll_license_sanitized)) {
         $poll_license_sanitized = 'License info unavailable';
    }

    $site_name = get_bloginfo('name');
    $site_url = home_url();
    $site_name_sanitized = !empty($site_name) ? kashiwazaki_poll_strip_invalid_xml_chars( $site_name ) : 'Site Operator';
    $copyright_info = sprintf("Copyright (c) %s %s. All Rights Reserved.", wp_date('Y'), $site_name_sanitized);
    $copyright_site_url = sprintf("%s (%s)", $copyright_info, esc_url($site_url));

    $poll_data_for_export = [
        'poll_id' => $poll_id,
        'title' => $poll_title_sanitized,
        'last_updated' => $last_updated_formatted,
        'survey_period_start' => $survey_period_start,
        'survey_period_end' => $survey_period_end,
        'copyright' => $copyright_info,
        'license' => $poll_license_sanitized,
        'total_votes' => $total_votes,
        'options' => []
    ];
    $options_data_list = [];
    foreach ($options_sanitized as $index => $option_text) {
        $vote_count = isset($counts[$index]) ? intval($counts[$index]) : 0;
        $percentage = ($total_votes > 0) ? round(($vote_count / $total_votes) * 100, 2) : 0;
        $option_item = [ 'text' => $option_text, 'count' => $vote_count, 'percentage' => $percentage ];
        $poll_data_for_export['options'][] = $option_item;
        $options_data_list[] = $option_item;
    }

    $contents = array(
        'csv'  => kashiwazaki_poll_build_csv( $poll_title_sanitized, $total_votes, $options_data_list, $copyright_info, $poll_license_sanitized, $survey_period_start, $survey_period_end ),
        'xml'  => kashiwazaki_poll_build_xml( $poll_id, $poll_title_sanitized, $last_updated_formatted, $total_votes, $options_data_list, $copyright_info, $poll_license_sanitized, $survey_period_start, $survey_period_end ),
        'yaml' => kashiwazaki_poll_build_yaml( $poll_data_for_export ),
        'json' => kashiwazaki_poll_build_json( $poll_data_for_export ),
        'svg'  => kashiwazaki_poll_build_svg_pie( $poll_id, $poll_title_sanitized, $total_votes, $options_data_list, $copyright_site_url, $poll_license_sanitized ),
    );

    $results = array();
    foreach ( $contents as $type => $content ) {
        $path = kashiwazaki_poll_get_dataset_file_path( $poll_id, $type );
        $results[ $type ] = ( false !== $content && $path ) ? kashiwazaki_poll_write_file_atomic( $path, $content ) : false;
        if ( ! $results[ $type ] ) {
            error_log( "[Poll Data Gen - ID:{$poll_id}] {$type} file was not written." );
        }
    }
    return $results;
}

/**
 * CSVインジェクション対策: =, +, -, @, タブ, CR で始まるセルは表計算ソフトで数式として
 * 解釈され得るため、先頭にシングルクオートを付けて無害化する（OWASP 推奨）。
 *
 * @param string $value セル値
 * @return string 無害化済みセル値
 */
function kashiwazaki_poll_csv_safe_cell( $value ) {
    $value = (string) $value;
    if ( $value !== '' && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
        return "'" . $value;
    }
    return $value;
}

/** @return string|false */
function kashiwazaki_poll_build_csv( $poll_title, $total_votes, $options_data, $copyright, $license, $survey_period_start = null, $survey_period_end = null ) {
    $fp = fopen( 'php://temp', 'w+' );
    if ( ! $fp ) {
        return false;
    }
    $ok = ( false !== fwrite( $fp, "\xEF\xBB\xBF" ) );
    $ok = $ok && false !== fputcsv( $fp, [ kashiwazaki_poll_csv_safe_cell( $poll_title ) ] );
    $ok = $ok && false !== fputcsv( $fp, [ 'option_text', 'vote_count', 'percentage' ] );
    foreach ( $options_data as $option ) {
        $ok = $ok && false !== fputcsv( $fp, [ kashiwazaki_poll_csv_safe_cell( $option['text'] ), $option['count'], $option['percentage'] ] );
    }
    $ok = $ok && false !== fwrite( $fp, "\n" );
    $ok = $ok && false !== fputcsv( $fp, [ 'Total Votes:', $total_votes ] );
    if ( $survey_period_start && $survey_period_end ) {
        $ok = $ok && false !== fputcsv( $fp, [ 'Survey Period:', $survey_period_start . ' - ' . $survey_period_end ] );
    }
    $ok = $ok && false !== fwrite( $fp, "\n" );
    $ok = $ok && false !== fwrite( $fp, '# ' . str_replace( [ "\r", "\n" ], ' ', $copyright ) . "\n" );
    $ok = $ok && false !== fwrite( $fp, '# License: ' . str_replace( [ "\r", "\n" ], ' ', $license ) . "\n" );
    if ( ! $ok ) {
        fclose( $fp );
        return false;
    }
    rewind( $fp );
    $content = stream_get_contents( $fp );
    fclose( $fp );
    return $content;
}

/** @return string|false */
function kashiwazaki_poll_build_xml( $poll_id, $poll_title, $last_updated, $total_votes, $options_data, $copyright, $license, $survey_period_start = null, $survey_period_end = null ) {
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;

    $root = $dom->createElement('poll_results');
    $root->setAttribute('poll_id', $poll_id);
    $dom->appendChild($root);

    $metadataElement = $dom->createElement('metadata');
    $root->appendChild($metadataElement);

    $titleElement = $dom->createElement('title');
    $titleElement->appendChild($dom->createTextNode($poll_title));
    $metadataElement->appendChild($titleElement);

    $metadataElement->appendChild( $dom->createElement( 'last_updated', $last_updated ) );

    // 調査期間を追加
    if ( $survey_period_start && $survey_period_end ) {
        $metadataElement->appendChild( $dom->createElement( 'survey_period_start', $survey_period_start ) );
        $metadataElement->appendChild( $dom->createElement( 'survey_period_end', $survey_period_end ) );
    }

    $copyrightElement = $dom->createElement('copyright');
    $copyrightElement->appendChild($dom->createTextNode($copyright));
    $metadataElement->appendChild($copyrightElement);

    $licenseElement = $dom->createElement('license');
    $licenseElement->appendChild($dom->createTextNode($license));
    $metadataElement->appendChild($licenseElement);

    $root->appendChild( $dom->createElement( 'total_votes', $total_votes ) );

    $optionsContainer = $dom->createElement('options');
    $root->appendChild($optionsContainer);

    foreach ( $options_data as $option ) {
        $optionElement = $dom->createElement('option');
        $textElement = $dom->createElement('text');
        $textElement->appendChild($dom->createTextNode($option['text']));
        $optionElement->appendChild($textElement);
        $optionElement->appendChild( $dom->createElement( 'count', $option['count'] ) );
        $optionElement->appendChild( $dom->createElement( 'percentage', $option['percentage'] ) );
        $optionsContainer->appendChild($optionElement);
    }

    $xml = $dom->saveXML();
    if ( false === $xml ) {
        error_log( "[Poll Data Gen XML - ID:{$poll_id}] Failed to build XML. libxml errors: " . print_r( libxml_get_errors(), true ) );
        libxml_clear_errors();
    }
    return $xml;
}

/** @return string */
function kashiwazaki_poll_build_yaml( $poll_data ) {
    $yaml_content = '';
    foreach ( $poll_data as $key => $value ) {
        if ( 'options' === $key ) {
            continue;
        }
        $yaml_content .= $key . ': ' . _kashiwazaki_poll_yaml_quote_string( $value ) . "\n";
    }

    if ( ! empty( $poll_data['options'] ) && is_array( $poll_data['options'] ) ) {
        $yaml_content .= "options:\n";
        foreach ( $poll_data['options'] as $option ) {
            $yaml_content .= '  - text: ' . _kashiwazaki_poll_yaml_quote_string( (string) $option['text'] ) . "\n";
            $yaml_content .= '    count: ' . (int) $option['count'] . "\n";
            $yaml_content .= '    percentage: ' . _kashiwazaki_poll_yaml_quote_string( $option['percentage'] ) . "\n";
        }
    } else {
        $yaml_content .= "options: []\n";
    }
    return $yaml_content;
}

/** @return string|false */
function kashiwazaki_poll_build_json( $poll_data ) {
    $json_content = wp_json_encode( $poll_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    if ( false === $json_content ) {
        error_log( '[Poll Data Gen JSON - ID:' . ( isset( $poll_data['poll_id'] ) ? $poll_data['poll_id'] : '?' ) . '] Failed to encode data to JSON. Error: ' . json_last_error_msg() );
    }
    return $json_content;
}

/** @return string */
function kashiwazaki_poll_build_svg_pie( $poll_id, $poll_title, $total_votes, $options_data, $copyright_site_url, $license ) {
    $svg_width = 800; $svg_height = 600; $cx = $svg_width / 2; $cy = ($svg_height / 2) - 50;
    $radius = min($svg_width, $svg_height) * 0.30; $legend_y_start = $cy + $radius + 40;
    $colors = ['#3498db', '#e74c3c', '#2ecc71', '#f1c40f', '#9b59b6', '#34495e', '#1abc9c', '#f39c12', '#d35400', '#c0392b'];
    $svg_content = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>' . "\n";
    $svg_content .= '<svg width="' . $svg_width . '" height="' . $svg_height . '" viewBox="0 0 ' . $svg_width . ' ' . $svg_height . '" xmlns="http://www.w3.org/2000/svg" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:cc="http://creativecommons.org/ns#">' . "\n";

    $svg_content .= '  <metadata>'."\n";
    $svg_content .= '    <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'."\n";
    $svg_content .= '      <cc:Work rdf:about="">'."\n";
    $svg_content .= '        <dc:format>image/svg+xml</dc:format>'."\n";
    $svg_content .= '        <dc:type rdf:resource="http://purl.org/dc/dcmitype/StillImage" />'."\n";
    if (!empty($poll_title)) { $svg_content .= '        <dc:title>' . htmlspecialchars($poll_title, ENT_XML1, 'UTF-8') . '</dc:title>'."\n"; }
    $svg_content .= '        <dc:description>Pie chart representing poll results for poll ID ' . intval($poll_id) . '.</dc:description>'."\n";
    if (!empty($copyright_site_url)) { $svg_content .= '        <dc:rights>' . htmlspecialchars($copyright_site_url, ENT_XML1, 'UTF-8') . '</dc:rights>'."\n"; }
    if (!empty($license)) { $svg_content .= '        <cc:license rdf:resource="' . htmlspecialchars($license, ENT_XML1, 'UTF-8') . '" />'."\n"; }
    $svg_content .= '      </cc:Work>'."\n"; $svg_content .= '    </rdf:RDF>'."\n"; $svg_content .= '  </metadata>'."\n";

    $svg_content .= '<rect width="100%" height="100%" fill="#f9f9f9" />' . "\n";
    $svg_content .= '<text x="' . $cx . '" y="30" font-family="sans-serif" font-size="20" fill="#333" text-anchor="middle" dominant-baseline="middle">' . htmlspecialchars($poll_title, ENT_QUOTES, 'UTF-8') . '</text>' . "\n";
    $start_angle = -90; $legend_items = [];
    if ($total_votes <= 0) {
        $svg_content .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $radius . '" fill="#eee" stroke="#ccc" stroke-width="1"/>' . "\n";
        $svg_content .= '<text x="' . $cx . '" y="' . $cy . '" font-family="sans-serif" font-size="16" fill="#666" text-anchor="middle" dominant-baseline="middle">No votes yet</text>' . "\n";
    } else {
        foreach ($options_data as $index => $option) {
            $count = $option['count']; if ($count <= 0) continue; $percentage = $option['percentage'];
            $color = $colors[ $index % count( $colors ) ];
            if ( $count >= $total_votes ) {
                // 1 つの選択肢が全票のときは扇形（円弧）では描けないため円で描く。
                $svg_content .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $radius . '" fill="' . $color . '" stroke="#fff" stroke-width="1" />' . "\n";
            } else {
                $angle = ($count / $total_votes) * 360;
                $end_angle = $start_angle + $angle; $start_rad = deg2rad($start_angle); $end_rad = deg2rad($end_angle);
                $startX = $cx + $radius * cos($start_rad); $startY = $cy + $radius * sin($start_rad); $endX = $cx + $radius * cos($end_rad); $endY = $cy + $radius * sin($end_rad);
                $large_arc_flag = ($angle > 180) ? 1 : 0; $path_data = implode(' ', ['M', $cx, $cy, 'L', $startX, $startY, 'A', $radius, $radius, 0, $large_arc_flag, 1, $endX, $endY, 'Z']);
                $svg_content .= '<path d="' . $path_data . '" fill="' . $color . '" stroke="#fff" stroke-width="1" />' . "\n";
                $start_angle = $end_angle;
            }
            $legend_items[] = ['text' => $option['text'], 'color' => $color, 'percentage' => $percentage];
        }
    }
    $legend_x = 50; $legend_y = $legend_y_start; $legend_rect_size = 12; $legend_spacing = 18;
    foreach($legend_items as $item) {
         $svg_content .= '<rect x="' . $legend_x . '" y="' . ($legend_y - $legend_rect_size/2 -2) . '" width="' . $legend_rect_size . '" height="' . $legend_rect_size . '" fill="' . $item['color'] . '" />' . "\n";
         $svg_content .= '<text x="' . ($legend_x + $legend_rect_size + 8) . '" y="' . $legend_y . '" font-family="sans-serif" font-size="12" fill="#333" dominant-baseline="middle">' . htmlspecialchars($item['text'], ENT_QUOTES, 'UTF-8') . ' (' . $item['percentage'] . '%)</text>' . "\n";
         $legend_y += $legend_spacing;
    }
    $svg_content .= '</svg>';
    return $svg_content;
}

/**
 * データセット専用サイトマップ（sitemap-poll-datasets.xml）を作る。公開してよいデータセットの詳細ページと、ファイルがある形式別ページを載せる。
 *
 * @return bool 書けたとき true
 */
function kashiwazaki_poll_generate_sitemap_poll() {
    $sitemap_file_path = ABSPATH . 'sitemap-poll-datasets.xml';

    if ( ! is_writable( ABSPATH ) ) {
        error_log("[Sitemap Gen] WordPress root directory not writable for sitemap: " . ABSPATH);
        return false;
    }

    // 古いsitemap-poll.xmlが存在する場合は削除
    $old_sitemap_path = ABSPATH . 'sitemap-poll.xml';
    if ( file_exists( $old_sitemap_path ) ) {
        @unlink( $old_sitemap_path );
    }

    clearstatcache();
    $lines   = array();
    $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $lines[] = '<!-- Generated by Kashiwazaki SEO Poll - Datasets Only -->';
    $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

    $polls = get_posts( array(
        'post_type'   => 'poll',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby'     => 'modified',
        'order'       => 'DESC',
    ) );

    foreach ( $polls as $poll ) {
        if ( ! kashiwazaki_poll_is_public_poll( $poll ) ) {
            continue;
        }
        $poll_id = $poll->ID;

        // 形式別ページの正規 URL である詳細ページも載せる。
        $detail_url = get_permalink( $poll_id );
        if ( $detail_url ) {
            $detail_last = kashiwazaki_poll_get_last_updated_time( $poll_id );
            if ( ! $detail_last ) {
                $detail_last = (int) get_post_modified_time( 'U', true, $poll );
            }
            $lines[] = '  <url>';
            $lines[] = '    <loc>' . esc_url( $detail_url ) . '</loc>';
            $lines[] = '    <lastmod>' . wp_date( 'c', $detail_last ) . '</lastmod>';
            $lines[] = '    <changefreq>daily</changefreq>';
            $lines[] = '    <priority>0.8</priority>';
            $lines[] = '  </url>';
        }

        foreach ( kashiwazaki_poll_data_file_types() as $type ) {
            $dataset_page_url  = kashiwazaki_poll_get_single_dataset_page_url( $poll_id, $type );
            $dataset_file_path = kashiwazaki_poll_get_dataset_file_path( $poll_id, $type );
            // データファイルが存在しない形式は形式別ページが404になるため、サイトマップに含めない。
            if ( ! $dataset_page_url || ! $dataset_file_path || ! file_exists( $dataset_file_path ) ) {
                continue;
            }
            $lines[] = '  <url>';
            $lines[] = '    <loc>' . esc_url( $dataset_page_url ) . '</loc>';
            $lines[] = '    <lastmod>' . wp_date( 'c', filemtime( $dataset_file_path ) ) . '</lastmod>';
            $lines[] = '    <changefreq>daily</changefreq>';
            $lines[] = '    <priority>0.7</priority>';
            $lines[] = '  </url>';
        }
    }
    $lines[] = '</urlset>';

    if ( ! kashiwazaki_poll_write_file_atomic( $sitemap_file_path, implode( "\n", $lines ) . "\n" ) ) {
        error_log( "[Sitemap Gen] Failed to write sitemap: " . $sitemap_file_path );
        return false;
    }
    return true;
}

/**
 * 保存が終わった後（投稿・タクソノミー・メタがすべて保存された後）に、データファイルとサイトマップを公開状態に合わせる。
 * 公開してよいデータセットなら最新の内容で作り直し（タイトル・ライセンス・選択肢・投票数の変更、予約投稿の公開、クイック編集も含む）、
 * そうでなければ（下書き・非公開・パスワード保護・ゴミ箱）データファイルを消してサイトマップから外す。
 */
add_action( 'wp_after_insert_post', 'kashiwazaki_poll_sync_files_after_save', 10, 4 );
function kashiwazaki_poll_sync_files_after_save( $post_id, $post, $update, $post_before ) {
    if ( ! $post || 'poll' !== $post->post_type || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
        return;
    }
    if ( 'auto-draft' === $post->post_status ) {
        return;
    }
    if ( kashiwazaki_poll_is_public_poll( $post ) ) {
        kashiwazaki_poll_generate_all_data_files( $post_id );
        return;
    }
    $was_public = $post_before && kashiwazaki_poll_is_public_poll( $post_before );
    if ( $was_public || kashiwazaki_poll_has_data_files( $post_id ) ) {
        kashiwazaki_poll_delete_data_files( $post_id );
        kashiwazaki_poll_generate_sitemap_poll();
    }
}

/**
 * 投稿の更新が保存された直後（メタの保存や wp_after_insert_post より前）に、公開から外れたことを見つけてデータファイルを消す。
 * パスワードの設定は公開のまま（publish → publish）なので状態の変化として届かず、後の処理が途中で止まると
 * wp_after_insert_post まで届かないため、ここで先に消しておく（消せなければ削除失敗として記録・通知される）。
 */
add_action( 'post_updated', 'kashiwazaki_poll_sync_files_on_update', 10, 3 );
function kashiwazaki_poll_sync_files_on_update( $post_id, $post_after, $post_before ) {
    if ( ! $post_after || 'poll' !== $post_after->post_type || wp_is_post_revision( $post_id ) ) {
        return;
    }
    if ( $post_before && kashiwazaki_poll_is_public_poll( $post_before ) && ! kashiwazaki_poll_is_public_poll( $post_after ) ) {
        kashiwazaki_poll_delete_data_files( $post_id );
        kashiwazaki_poll_generate_sitemap_poll();
    }
}

/**
 * 公開から外れたとき（ゴミ箱への移動など、保存処理を通らない変更も含む）にデータファイルを消す。
 */
add_action( 'transition_post_status', 'kashiwazaki_poll_sync_files_on_status_change', 10, 3 );
function kashiwazaki_poll_sync_files_on_status_change( $new_status, $old_status, $post ) {
    if ( ! $post || 'poll' !== $post->post_type || $new_status === $old_status ) {
        return;
    }
    if ( 'publish' === $old_status && 'publish' !== $new_status ) {
        kashiwazaki_poll_delete_data_files( $post->ID );
        kashiwazaki_poll_generate_sitemap_poll();
    }
}

/**
 * データセットを完全に削除したとき（ゴミ箱を通らない削除を含む）に、データファイルを消してサイトマップから外す。
 */
add_action( 'deleted_post', 'kashiwazaki_poll_cleanup_after_delete', 10, 2 );
function kashiwazaki_poll_cleanup_after_delete( $post_id, $post = null ) {
    if ( ! $post || 'poll' !== $post->post_type ) {
        return;
    }
    kashiwazaki_poll_delete_data_files( $post_id );
    kashiwazaki_poll_generate_sitemap_poll();
    // 削除したデータセットの「確認待ち」の通知は、編集画面が無く消せないので一緒に消す。
    delete_option( 'kashiwazaki_poll_vote_repair_' . (int) $post_id );
}

function kashiwazaki_poll_has_data_files( $poll_id ) {
    foreach ( kashiwazaki_poll_data_file_types() as $type ) {
        $path = kashiwazaki_poll_get_dataset_file_path( $poll_id, $type );
        if ( $path && file_exists( $path ) ) {
            return true;
        }
    }
    return false;
}

/**
 * データセットのデータファイル5種を消す（排他を取って、生成と同時に走らないようにする）。
 *
 * @return bool すべて消せた（または元から無い）とき true
 */
function kashiwazaki_poll_delete_data_files( $poll_id ) {
    if ( ! kashiwazaki_poll_acquire_lock( $poll_id ) ) {
        // 生成中などで排他を取れないときは、今ファイルが無くても（生成がこの後書き出すかもしれないので）
        // 消せなかったものとして記録する（管理画面で知らせ、一括生成で消し直す）。
        kashiwazaki_poll_record_delete_result( $poll_id, false );
        error_log( "[Poll Data Delete - ID:{$poll_id}] Could not acquire lock; deletion deferred." );
        return false;
    }
    $ok = kashiwazaki_poll_delete_data_files_unlocked( $poll_id );
    kashiwazaki_poll_release_lock( $poll_id );
    return $ok;
}

function kashiwazaki_poll_delete_data_files_unlocked( $poll_id ) {
    $ok = true;
    clearstatcache();
    foreach ( kashiwazaki_poll_data_file_types() as $type ) {
        $path = kashiwazaki_poll_get_dataset_file_path( $poll_id, $type );
        if ( $path && file_exists( $path ) ) {
            @unlink( $path );
            clearstatcache( true, $path );
            if ( file_exists( $path ) ) {
                $ok = false;
                error_log( "[Poll Data Delete - ID:{$poll_id}] Failed to delete: " . $path );
            }
        }
    }
    kashiwazaki_poll_record_delete_result( $poll_id, $ok );
    return $ok;
}

/**
 * 削除に失敗したデータセットを記録し、管理画面で知らせる（「データファイルを一括生成する」で再試行できる）。
 */
function kashiwazaki_poll_record_delete_result( $poll_id, $ok ) {
    // データセットごとに別の記録にする（別のデータセットの同時の記録で上書きし合わないように）。
    $option = 'kashiwazaki_poll_delete_failed_' . (int) $poll_id;
    if ( $ok ) {
        delete_option( $option );
    } else {
        update_option( $option, time(), false );
    }
}

/**
 * 削除失敗が記録されているデータセットの ID。
 *
 * @return int[]
 */
function kashiwazaki_poll_get_delete_failures() {
    global $wpdb;
    $names = $wpdb->get_col( $wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like( 'kashiwazaki_poll_delete_failed_' ) . '%'
    ) );
    $ids = array();
    foreach ( (array) $names as $name ) {
        $id = (int) substr( $name, strlen( 'kashiwazaki_poll_delete_failed_' ) );
        if ( $id > 0 ) {
            $ids[] = $id;
        }
    }
    sort( $ids );
    return $ids;
}

/**
 * データファイルの作り直しを少し後に予約する（混み合っていてその場で作り直せなかったとき）。
 */
function kashiwazaki_poll_schedule_regeneration( $poll_id ) {
    $args = array( (int) $poll_id );
    if ( ! wp_next_scheduled( 'kashiwazaki_poll_regenerate_poll', $args ) ) {
        wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'kashiwazaki_poll_regenerate_poll', $args );
    }
}

add_action( 'kashiwazaki_poll_regenerate_poll', 'kashiwazaki_poll_run_scheduled_regeneration' );
function kashiwazaki_poll_run_scheduled_regeneration( $poll_id ) {
    if ( ! kashiwazaki_poll_generate_all_data_files( (int) $poll_id ) && kashiwazaki_poll_is_public_poll( (int) $poll_id ) ) {
        // まだ混み合っているときは、もう一度だけではなく作り直せるまで予約し直す（予約は同じ ID で 1 件だけ）。
        kashiwazaki_poll_schedule_regeneration( (int) $poll_id );
    }
}

add_action( 'admin_notices', 'kashiwazaki_poll_vote_repair_notice' );
function kashiwazaki_poll_vote_repair_notice() {
    global $wpdb;
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like( 'kashiwazaki_poll_vote_repair_' ) . '%'
    ) );
    // 印は保存の間だけ一時的に作られるため、作られてから 60 秒たったもの（保存が途中で止まったもの）だけを知らせる。
    $names = array();
    foreach ( (array) $rows as $row ) {
        if ( (int) $row->option_value <= time() - 60 ) {
            $names[] = $row->option_name;
        }
    }
    if ( empty( $names ) ) {
        return;
    }
    $ids = array_map( function ( $name ) {
        return (int) substr( $name, strlen( 'kashiwazaki_poll_vote_repair_' ) );
    }, $names );
    echo '<div class="notice notice-error"><p>' . esc_html( sprintf(
        'Kashiwazaki SEO Poll: データセット（ID: %s）で、投票数などの保存が途中で止まったか、データベースのエラーで元に戻せませんでした。編集画面で選択肢と投票数を確認し、必要なら直して「更新」を押してください（更新するとこの通知は消えます）。',
        implode( ', ', $ids )
    ) ) . '</p></div>';
}

add_action( 'admin_notices', 'kashiwazaki_poll_delete_failure_notice' );
function kashiwazaki_poll_delete_failure_notice() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $failed = kashiwazaki_poll_get_delete_failures();
    if ( empty( $failed ) ) {
        return;
    }
    echo '<div class="notice notice-error"><p>' . esc_html( sprintf(
        'Kashiwazaki SEO Poll: 公開をやめたデータセット（ID: %s）のデータファイルを削除できませんでした。データファイルの保存用フォルダの書き込み権限を確認し、基本設定の「データファイルを一括生成する」を押してください。',
        implode( ', ', array_map( 'intval', $failed ) )
    ) ) . '</p></div>';
}

/**
 * 公開してよいデータセットに属さないデータファイル（旧版で非公開・削除・パスワード保護にした後も残ったもの、書きかけの一時ファイル）を消す。
 *
 * データセットごとに生成・削除と同じ排他を取り、その中で最新の公開状態を確かめてから消す
 * （片付けの途中で再公開されたデータセットの正しいファイルを消さないように）。
 *
 * @return int 消し切れなかったデータセットの数（失敗は管理画面に通知され、次回の一括生成で消し直す）
 */
function kashiwazaki_poll_purge_orphan_files() {
    $base = kashiwazaki_poll_datasets_base_dir();
    $ids  = array();
    foreach ( kashiwazaki_poll_data_file_types() as $type ) {
        $dir = $base . $type . '/';
        if ( ! is_dir( $dir ) ) {
            continue;
        }
        foreach ( (array) glob( $dir . '*' ) as $path ) {
            if ( ! is_file( $path ) ) {
                continue;
            }
            $name = basename( $path );
            if ( preg_match( '/^(\d+)\.' . preg_quote( $type, '/' ) . '$/', $name, $m ) ) {
                $ids[ (int) $m[1] ] = true;
            } elseif ( false !== strpos( $name, '.tmp-' ) && filemtime( $path ) < time() - HOUR_IN_SECONDS ) {
                @unlink( $path ); // 書き込み途中で残った古い一時ファイル（新しいものは書き込み中かもしれないので残す）。
            }
        }
    }

    $failed = 0;
    foreach ( array_keys( $ids ) as $poll_id ) {
        if ( ! kashiwazaki_poll_acquire_lock( $poll_id ) ) {
            if ( ! kashiwazaki_poll_is_public_poll( $poll_id ) ) {
                kashiwazaki_poll_record_delete_result( $poll_id, false );
                $failed++;
            }
            continue;
        }
        clean_post_cache( $poll_id );
        if ( ! kashiwazaki_poll_is_public_poll( $poll_id ) && ! kashiwazaki_poll_delete_data_files_unlocked( $poll_id ) ) {
            $failed++;
        }
        kashiwazaki_poll_release_lock( $poll_id );
    }

    // 記録された削除失敗のうち、ファイルがもう無いもの・公開に戻ったものは記録から外す（生成・削除と同じ排他の中で確かめる）。
    foreach ( kashiwazaki_poll_get_delete_failures() as $fid ) {
        if ( isset( $ids[ $fid ] ) || ! kashiwazaki_poll_acquire_lock( $fid ) ) {
            continue; // 上で処理済み、または今は確かめられない（記録を残す）。
        }
        clean_post_cache( $fid );
        if ( kashiwazaki_poll_is_public_poll( $fid ) || ! kashiwazaki_poll_has_data_files( $fid ) ) {
            kashiwazaki_poll_record_delete_result( $fid, true );
        }
        kashiwazaki_poll_release_lock( $fid );
    }
    return $failed;
}

/**
 * 1.0.7 への更新後に一度だけ、残っている不要なデータファイルを消す（管理者が管理画面を開いたとき）。
 */
add_action( 'admin_init', 'kashiwazaki_poll_purge_orphans_once' );
function kashiwazaki_poll_purge_orphans_once() {
    if ( '1.0.7' === get_option( 'kashiwazaki_poll_orphans_purged', '' ) || ! current_user_can( 'manage_options' ) ) {
        return;
    }
    // 消せなかったものは削除失敗として記録され、管理画面の通知に出る（一括生成で消し直せる）ので、走査は一度だけにする。
    kashiwazaki_poll_purge_orphan_files();
    update_option( 'kashiwazaki_poll_orphans_purged', '1.0.7', false );
    kashiwazaki_poll_generate_sitemap_poll();
}
