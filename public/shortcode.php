<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function kashiwazaki_poll_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'tk_poll' );
    $poll_id = intval( $atts['id'] );
    if ( ! $poll_id ) return '';
    $poll_post = get_post( $poll_id );
    // 公開していない・パスワード保護されたデータセットは表示しない。
    if ( ! kashiwazaki_poll_is_public_poll( $poll_post ) ) return '<p>データが見つかりません。</p>';
    // 4バイト文字の文字参照（データベースが utf8mb4 でないとき WordPress が変換したもの）を元の文字に戻す。
    $question = kashiwazaki_poll_decode_stored_text( $poll_post->post_title );
    $question_esc = esc_html($question);
    $heading_level = get_post_meta( $poll_id, '_kashiwazaki_poll_heading_level', true );
    if ( ! in_array( $heading_level, array( 'h1','h2','h3','h4','h5','h6' ) ) ) $heading_level = 'h3';
    $options  = get_post_meta( $poll_id, '_kashiwazaki_poll_options', true );
    if ( ! is_array( $options ) || empty( $options ) ) return '<p>選択肢がありません。</p>';
    // 表示用（4バイト文字の文字参照を元の文字に戻したもの）。投票の照合の署名は保存されたままの $options で作る。
    $display_options = array_map( 'kashiwazaki_poll_decode_stored_text', $options );
    $poll_type = get_post_meta( $poll_id, '_kashiwazaki_poll_type', true );
    if ( ! in_array( $poll_type, array( 'single','multiple' ) ) ) $poll_type = 'multiple';
    $poll_description = kashiwazaki_poll_decode_stored_text( get_post_meta( $poll_id, '_kashiwazaki_poll_description', true ) );
    $datePublished    = get_the_date( 'c', $poll_post );
    $counts = get_post_meta( $poll_id, '_kashiwazaki_poll_counts', true );
    $total_votes = ( is_array( $counts ) && ! empty( $counts ) ) ? array_sum( $counts ) : 0;
    $has_data = $total_votes > 0;
    $ip         = kashiwazaki_poll_get_client_ip();
    $cookie_key = 'kashiwazaki_poll_voted2_' . $poll_id; // v2: UTC基準（util.php の説明参照）
    $already_voted = kashiwazaki_poll_is_already_voted( $poll_id, $ip, $cookie_key );
    // 受付終了（ロック中）なら投票フォームを出さず、結果を表示する。
    $locked    = kashiwazaki_poll_is_locked( $poll_id );
    $show_form = ! $already_voted && ! $locked;
    $site_name        = get_bloginfo('name');
    $ajax_url         = admin_url('admin-ajax.php');

    wp_enqueue_script('chart-js');
    wp_enqueue_script('chartjs-plugin-datalabels');
    wp_enqueue_script('kashiwazaki-poll-frontend-js');

    ob_start();

    if ( ! wp_script_is( 'kashiwazaki-poll-data-init-inline', 'enqueued' ) ) {
        echo "<script id='kashiwazaki-poll-data-init-inline'>var kashiwazakiPollAllData = window.kashiwazakiPollAllData || {};</script>\n";
        wp_register_script( 'kashiwazaki-poll-data-init-inline', '', [], false, true );
        wp_enqueue_script( 'kashiwazaki-poll-data-init-inline' );
    }
    // データセットカラーテーマ情報を取得
    $settings = get_option( 'kashiwazaki_poll_settings', array( 'dataset_color_theme' => 'minimal' ) );
    $color_theme = $settings['dataset_color_theme'];
    $themes = array(
        'minimal' => array('button_primary' => '#6c757d', 'accent_color' => '#6c757d'),
        'blue' => array('button_primary' => '#0073aa', 'accent_color' => '#0073aa'),
        'green' => array('button_primary' => '#28a745', 'accent_color' => '#28a745'),
        'orange' => array('button_primary' => '#fd7e14', 'accent_color' => '#fd7e14'),
        'purple' => array('button_primary' => '#6f42c1', 'accent_color' => '#6f42c1'),
        'dark' => array('button_primary' => '#3498db', 'accent_color' => '#3498db')
    );
    $current_theme = isset($themes[$color_theme]) ? $themes[$color_theme] : $themes['minimal'];

    $poll_data = array(
        'pollId'       => $poll_id, 'alreadyVoted' => ! $show_form, 'locked' => $locked, 'hasData' => $has_data,
        'siteName'     => $site_name, 'pollQuestion' => $question, 'ajaxUrl' => $ajax_url,
        'nonce'        => wp_create_nonce( 'kashiwazaki_poll_vote_' . $poll_id ),
        'datasetTheme' => $current_theme,
        // データセットURLは window.location.origin ではなく home_url 基準にする
        // （サブディレクトリ設置・リバースプロキシ・別 home_url でもリンクが正しくなる）。
        'datasetsBaseUrl' => home_url( '/datasets/' ),
    );
    // JSON_HEX_TAG | JSON_HEX_AMP: `<` `>` `&` を \u00XX 化し、保存値中の `</script>` 等で
    // <script> 要素を突破される（XSS）のを防ぐ。
    $data_json = json_encode( $poll_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP );
    if ($data_json !== false) { echo sprintf("<script id='kashiwazaki-poll-data-%d'>kashiwazakiPollAllData[%d] = %s;</script>\n", $poll_id, $poll_id, $data_json); }

    // 個別データセットページへのリンクを出すか（フィルターで制御可能。データセットの詳細ページ自身では出さない）。
    $show_detail_link = apply_filters('kashiwazaki_poll_show_detail_link', true, $poll_id);
    $detail_link_html = '';
    if ($show_detail_link) {
        $detail_link_html = '<div class="poll-detail-link" style="margin-top: 20px; text-align: center;">'
            . '<a href="' . esc_url(get_permalink($poll_id)) . '" class="poll-detail-button" style="display: inline-block; padding: 8px 16px; border: 1px solid; border-radius: 3px; text-decoration: none; font-size: 0.9em; transition: opacity 0.2s;">このデータセットの詳細ページを見る</a>'
            . '</div>';
    }

    if ( $locked ) {
        // 受付終了（ロック中）は、集計グラフと詳細ページへのリンクだけを出す
        // （見出し・投票フォーム・締切の文言・ダウンロード類は出さない）。グラフは JS が描く。
        echo '<div class="kashiwazaki-poll-block kashiwazaki-poll-locked" data-poll-id="' . $poll_id . '">';
        echo '<div id="kashiwazaki-poll-result-' . $poll_id . '" class="kashiwazaki-poll-result-container"></div>';
        echo $detail_link_html;
        echo '</div>';
    } else {
    echo '<div class="kashiwazaki-poll-block" data-poll-id="' . $poll_id . '">';
    // 管理画面で設定した見出しレベル($heading_level)で質問文を出力（SEO/アクセシビリティ）。
    echo '<' . $heading_level . ' class="kashiwazaki-poll-title">' . $question_esc . '</' . $heading_level . '>';
    echo '<div id="kashiwazaki-poll-view-result-area-'. $poll_id .'" class="kashiwazaki-poll-view-result-trigger-area" style="'. ( $show_form ? '' : 'display: none;' ) .'">';
    echo '<button type="button" class="kashiwazaki-poll-view-result" data-pollid="' . $poll_id . '">集計データを拡大</button>';
    echo '</div>';
    echo '<div id="kashiwazaki-poll-result-' . $poll_id . '" class="kashiwazaki-poll-result-container"></div>';
    echo '<div id="kashiwazaki-poll-preview-' . $poll_id . '" class="kashiwazaki-poll-preview-container" style="'. ( $show_form ? '' : 'display: none;' ) .'"></div>';

    if ( $show_form ) {
        echo '<form class="kashiwazaki-poll-form" data-pollid="' . $poll_id . '">';
        echo wp_nonce_field( 'kashiwazaki_poll_vote_' . $poll_id, '_wpnonce', true, false );
        // 表示した選択肢の並びの署名（投票時に今の選択肢と照らし、変わっていたら受け付けない）。
        echo '<input type="hidden" name="options_sig" value="' . esc_attr( kashiwazaki_poll_options_signature( $options ) ) . '">';
        echo '<input type="hidden" name="poll_id" value="' . $poll_id . '">';
        echo '<input type="hidden" name="poll_type" value="' . $poll_type . '">';
        // 選択肢を先に提示してから投票ボタンを置く（スクリーンリーダー/キーボードの
        // 論理順序。送信操作が選択肢より先に現れる問題を解消）。
        foreach ( $display_options as $i => $opt ) {
            $opt_esc = esc_html( $opt );
            $input_type = ($poll_type === 'multiple') ? 'checkbox' : 'radio';
            echo '<div><label><input type="'.$input_type.'" name="poll_options[]" value="' . $i . '"> ' . $opt_esc . '</label></div>';
        }
        echo '<button type="button" id="kashiwazaki-poll-submit-bottom-' . $poll_id . '" class="kashiwazaki-poll-submit">投票する</button>';
        echo '</form>';
    } else {
        echo '<p class="voted-msg">既に投票しています</p>';
        echo '<form class="kashiwazaki-poll-form kashiwazaki-poll-form-disabled" data-pollid="' . $poll_id . '" style="display: none;">';
        foreach ( $display_options as $i => $opt ) {
            $opt_esc = esc_html( $opt );
            $input_type = ($poll_type === 'multiple') ? 'checkbox' : 'radio';
            echo '<div><label><input type="'.$input_type.'" name="poll_options[]" value="' . $i . '" disabled> ' . $opt_esc . '</label></div>';
        }
        echo '</form>';
    }

    // 個別データセットページへのリンク
    echo $detail_link_html;

    echo '</div>';
    } // end if ( $locked ) else
    // Dataset 構造化データは説明文の長さに関わらず常に出力する（短い説明のpollでも
    // SEO/AEO向けに構造化データを欠落させない）。
    // poll 個別ページ(/datasets/detail-{id}/)では single-poll.php が wp_head で同一の
    // Dataset JSON-LD を出力するため、ショートコード側の出力は抑止して二重出力を防ぐ。
    if ( $poll_id && ! is_singular( 'poll' ) ) {
         $current_counts = get_post_meta( $poll_id, '_kashiwazaki_poll_counts', true );
         $counts_for_ld = $current_counts; if ( ! is_array( $counts_for_ld ) ) { $counts_for_ld = array_fill( 0, count( $options ), 0 ); } elseif ( count( $counts_for_ld ) < count( $options ) ) { $counts_for_ld = array_pad( $counts_for_ld, count( $options ), 0 ); } elseif ( count( $counts_for_ld ) > count( $options ) ) { $counts_for_ld = array_slice( $counts_for_ld, 0, count( $options ) ); }
         $variableMeasured = []; foreach ( $display_options as $i => $opt ) { $value = isset( $counts_for_ld[$i] ) ? intval($counts_for_ld[$i]) : 0; $variableMeasured[] = ["@type"=>"PropertyValue", "name"=>$opt, "value"=>$value]; }
         $poll_license = get_post_meta( $poll_id, '_kashiwazaki_poll_license', true ); if ( empty( $poll_license ) ) { $poll_license = 'https://creativecommons.org/licenses/by/4.0/'; }
         global $post; $post_url = ''; $keywords = [];
         if ( is_object($post) && isset($post->ID) ) { $post_url = get_permalink( $post->ID ); $shortcode_post_tags = get_the_tags( $post->ID ); if ( ! is_wp_error( $shortcode_post_tags ) && is_array( $shortcode_post_tags ) ) { foreach ( $shortcode_post_tags as $tag ) { $keywords[] = $tag->name; } } }
         else {
             // global $post が無い文脈では、クライアント制御の HTTP_HOST を信用せず、
             // 信頼できる home_url() のホストに現在のパスのみを連結する（host header 注入対策）。
             $req_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '/';
             $post_url = home_url( $req_path ? $req_path : '/' );
         }
         $site_organization_name = get_bloginfo('name'); $site_organization_url = home_url();
         $site_admin_email = get_bloginfo('admin_email');

         // Creator情報を設定から取得
         $plugin_settings = get_option( 'kashiwazaki_poll_settings', array(
             'creator_type' => 'organization_only',
             'creator_person_name' => '',
             'creator_person_url' => '',
             'creator_organization_name' => get_bloginfo('name'),
             'creator_organization_url' => home_url(),
             'creator_organization_email' => ''
         ) );

         $creator_info = [];

         if ( $plugin_settings['creator_type'] === 'person_only' || $plugin_settings['creator_type'] === 'both' ) {
             $person_name = !empty($plugin_settings['creator_person_name']) ? $plugin_settings['creator_person_name'] : 'Unknown';
             $person_url = !empty($plugin_settings['creator_person_url']) ? $plugin_settings['creator_person_url'] : $site_organization_url;

             $creator_info[] = [
                 "@type" => "Person",
                 "name" => $person_name,
                 "url" => $person_url
             ];
         }

         if ( $plugin_settings['creator_type'] === 'organization_only' || $plugin_settings['creator_type'] === 'both' ) {
             $org_name = !empty($plugin_settings['creator_organization_name']) ? $plugin_settings['creator_organization_name'] : $site_organization_name;
             $org_url = !empty($plugin_settings['creator_organization_url']) ? $plugin_settings['creator_organization_url'] : $site_organization_url;
             $creator_info[] = kashiwazaki_poll_ld_organization_creator( $org_name, $org_url );
         }

         $provider_info = null;
         if ( isset($plugin_settings['structured_data_provider']) && $plugin_settings['structured_data_provider'] == 1 ) {
             $plugin_author_name = '柏崎剛';
             $plugin_organization_name = 'SEO対策研究室';
             $plugin_author_url = 'https://www.tsuyoshikashiwazaki.jp/';
             $provider_info = [
                 "@type" => "Person",
                 "name" => $plugin_author_name,
                 "url" => $plugin_author_url,
                 "affiliation" => [
                     "@type" => "Organization",
                     "name" => $plugin_organization_name
                 ]
             ];
         }

         $distribution = []; $datasets_base_path = kashiwazaki_poll_datasets_base_dir(); $datasets_base_url = kashiwazaki_poll_datasets_base_url();
         $file_types_sd = [ 'csv' => ['path' => 'csv/', 'ext' => '.csv', 'format' => 'text/csv'], 'xml' => ['path' => 'xml/', 'ext' => '.xml', 'format' => 'application/xml'], 'yaml' => ['path' => 'yaml/', 'ext' => '.yaml', 'format' => 'application/x-yaml'], 'json' => ['path' => 'json/', 'ext' => '.json', 'format' => 'application/json'], 'svg' => ['path' => 'svg/', 'ext' => '.svg', 'format' => 'image/svg+xml'], ];
         foreach ($file_types_sd as $key => $type) { $file_path = $datasets_base_path . $type['path'] . $poll_id . $type['ext']; if (file_exists($file_path)) { $distribution[] = ['@type' => 'DataDownload', 'contentUrl' => $datasets_base_url . $type['path'] . $poll_id . $type['ext'], 'encodingFormat' => $type['format']]; } }

         $publisher_info = kashiwazaki_poll_ld_publisher();
         $dataset = [ "@context" => "https://schema.org/", "@type" => "Dataset", "name" => $question, "description" => strip_tags($poll_description), "url" => $post_url, "creator" => $creator_info, "publisher" => $publisher_info, "datePublished" => $datePublished, "license" => $poll_license, "variableMeasured" => $variableMeasured ];
         if ( $provider_info !== null ) {
             $dataset["provider"] = $provider_info;
         }
         // キーワードは詳細ページと同じく編集画面の「キーワード」を優先し、未入力のときだけ掲載記事のタグを使う。
         $poll_keywords = kashiwazaki_poll_decode_stored_text( get_post_meta( $poll_id, 'dataset_keywords', true ) );
         if ( ! empty( $poll_keywords ) ) {
             $keywords = array_values( array_filter( array_map( 'trim', explode( ',', $poll_keywords ) ), 'strlen' ) );
         }
         if ( ! empty( $keywords ) ) { $dataset["keywords"] = array_values( array_unique( $keywords ) ); }
         // 地理的範囲も詳細ページと同じ値を出す。
         $dataset["spatialCoverage"] = ( is_array( $plugin_settings ) && ! empty( $plugin_settings['dataset_spatial_coverage'] ) ) ? $plugin_settings['dataset_spatial_coverage'] : '日本';
         if ( ! empty( $distribution ) ) { $dataset["distribution"] = $distribution; }
         if (!empty($dataset['url'])) { echo '<script type="application/ld+json">' . json_encode($dataset, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>'; }
    }

    return ob_get_clean();
}
add_shortcode( 'tk_poll', 'kashiwazaki_poll_shortcode' );
