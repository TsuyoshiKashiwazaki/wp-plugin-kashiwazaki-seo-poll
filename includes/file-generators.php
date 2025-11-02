<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function _kashiwazaki_poll_yaml_quote_string($value) {
    if (!is_string($value)) {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        return (string)$value;
    }

    $needs_quoting = false;
    if (preg_match('/(^[\s\-\?:]|\s$|[:{},\[\]&*#?|!\-><=%@`])/', $value) ||
        is_numeric($value) || in_array(strtolower($value), ['true', 'false', 'null', 'yes', 'no', 'on', 'off']) ||
        strpos($value, "\n") !== false || strpos($value, "\r") !== false
    ) {
        $needs_quoting = true;
    }

    if ($needs_quoting) {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace('"', '\\"', $value);
        $value = str_replace(["\n", "\r"], '\\n', $value);
        return '"' . $value . '"';
    }
    
    return $value;
}

function kashiwazaki_poll_generate_all_data_files( $poll_id, $counts ) {
    $poll_id = intval( $poll_id );
    if ( ! $poll_id ) {
        error_log( "[Poll Data Gen] Invalid poll ID provided: " . $poll_id );
        return false;
    }

    $poll_post = get_post( $poll_id );
    if ( ! $poll_post || $poll_post->post_type !== 'poll' ) {
        error_log( "[Poll Data Gen - ID:{$poll_id}] Poll post not found or invalid post type." );
        return false;
    }

    $poll_title = $poll_post->post_title;
    $poll_title_sanitized = preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $poll_title);


    $options = get_post_meta( $poll_id, '_kashiwazaki_poll_options', true );
    if ( ! is_array( $options ) ) { $options = []; }
    $options_sanitized = array_map(function($opt) {
        return preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $opt);
    }, $options);

    $current_option_count = count($options_sanitized);
    if ( ! is_array( $counts ) ) {
        $counts = array_fill( 0, $current_option_count, 0 );
    } else if ( count( $counts ) < $current_option_count ) {
        $counts = array_pad( $counts, $current_option_count, 0 );
    } else if ( count( $counts ) > $current_option_count && $current_option_count > 0 ) {
         $counts = array_slice( $counts, 0, $current_option_count );
    } elseif ( $current_option_count === 0) {
         $counts = [];
    }

    $total_votes = empty($counts) ? 0 : array_sum( $counts );
    $last_updated_time = current_time( 'timestamp' );
    $last_updated_formatted = date_i18n( 'c', $last_updated_time );

    $poll_license = get_post_meta( $poll_id, '_kashiwazaki_poll_license', true );
    if ( empty( $poll_license ) ) {
        $poll_license = 'https://creativecommons.org/licenses/by/4.0/';
    }
    $poll_license_sanitized = filter_var($poll_license, FILTER_SANITIZE_URL);
    if (empty($poll_license_sanitized)) {
         $poll_license_sanitized = 'License info unavailable';
    }

    $site_name = get_bloginfo('name');
    $site_url = home_url();
    $site_name_sanitized = !empty($site_name) ? preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $site_name) : 'Site Operator';
    $copyright_info = sprintf("Copyright (c) %s %s. All Rights Reserved.", date('Y'), $site_name_sanitized);
    $copyright_site_url = sprintf("%s (%s)", $copyright_info, esc_url($site_url));


    $poll_data_for_export = [
        'poll_id' => $poll_id,
        'title' => $poll_title_sanitized,
        'last_updated' => $last_updated_formatted,
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

    kashiwazaki_poll_generate_csv( $poll_id, $poll_title_sanitized, $total_votes, $options_data_list, $copyright_info, $poll_license_sanitized );
    kashiwazaki_poll_generate_xml( $poll_id, $poll_title_sanitized, $last_updated_formatted, $total_votes, $options_data_list, $copyright_info, $poll_license_sanitized );
    kashiwazaki_poll_generate_yaml( $poll_id, $poll_data_for_export );
    kashiwazaki_poll_generate_json( $poll_id, $poll_data_for_export );
    kashiwazaki_poll_generate_svg_pie( $poll_id, $poll_title_sanitized, $total_votes, $options_data_list, $copyright_site_url, $poll_license_sanitized );

    kashiwazaki_poll_generate_sitemap_poll();

    return true;
}

function kashiwazaki_poll_generate_csv( $poll_id, $poll_title, $total_votes, $options_data, $copyright, $license ) {
    $dir_path = KASHIWAZAKI_POLL_DIR . 'datasets/csv/';
    $file_path = $dir_path . $poll_id . '.csv';
    if ( ! file_exists( $dir_path ) && ! wp_mkdir_p( $dir_path ) ) { error_log("[Poll Data Gen CSV - ID:{$poll_id}] Failed to create directory: " . $dir_path); return; }
    if ( ! is_writable( $dir_path ) ) { error_log("[Poll Data Gen CSV - ID:{$poll_id}] Directory not writable: " . $dir_path); return; }

    $fp = fopen( $file_path, 'w' );
    if ( !$fp ) { error_log("[Poll Data Gen CSV - ID:{$poll_id}] Failed to open file: " . $file_path); return; }

    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, [$poll_title]);
    fputcsv( $fp, ['option_text', 'vote_count', 'percentage'] );
    if (!empty($options_data)) {
        foreach ( $options_data as $option ) {
            fputcsv( $fp, [ $option['text'], $option['count'], $option['percentage'] ] );
        }
    }
    fwrite($fp, "\n");
    fputcsv($fp, ["Total Votes:", $total_votes]);

    fwrite($fp, "\n");
    $copyright_clean = str_replace(["\r", "\n"], ' ', $copyright);
    $license_clean = str_replace(["\r", "\n"], ' ', $license);
    fwrite($fp, "# " . $copyright_clean . "\n");
    fwrite($fp, "# License: " . $license_clean . "\n");

    fclose( $fp );
}

function kashiwazaki_poll_generate_xml( $poll_id, $poll_title, $last_updated, $total_votes, $options_data, $copyright, $license ) {
    $dir_path = KASHIWAZAKI_POLL_DIR . 'datasets/xml/';
    $file_path = $dir_path . $poll_id . '.xml';
    if ( ! file_exists( $dir_path ) && ! wp_mkdir_p( $dir_path ) ) { error_log("[Poll Data Gen XML - ID:{$poll_id}] Failed to create directory: " . $dir_path); return; }
    if ( ! is_writable( $dir_path ) ) { error_log("[Poll Data Gen XML - ID:{$poll_id}] Directory not writable: " . $dir_path); return; }

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

    $lastUpdatedElement = $dom->createElement('last_updated', $last_updated);
    $metadataElement->appendChild($lastUpdatedElement);

    $copyrightElement = $dom->createElement('copyright');
    $copyrightElement->appendChild($dom->createTextNode($copyright));
    $metadataElement->appendChild($copyrightElement);

    $licenseElement = $dom->createElement('license');
    $licenseElement->appendChild($dom->createTextNode($license));
    $metadataElement->appendChild($licenseElement);

    $totalVotesElement = $dom->createElement('total_votes', $total_votes);
    $root->appendChild($totalVotesElement);

    $optionsContainer = $dom->createElement('options');
    $root->appendChild($optionsContainer);

    if (!empty($options_data)) {
        foreach ( $options_data as $option ) {
            $optionElement = $dom->createElement('option');
            $textElement = $dom->createElement('text');
            $textElement->appendChild($dom->createTextNode($option['text']));
            $optionElement->appendChild($textElement);
            $countElement = $dom->createElement('count', $option['count']);
            $optionElement->appendChild($countElement);
            $percentageElement = $dom->createElement('percentage', $option['percentage']);
            $optionElement->appendChild($percentageElement);
            $optionsContainer->appendChild($optionElement);
        }
    }

    $saveResult = $dom->save($file_path);
    if ( $saveResult === false ) {
         error_log("[Poll Data Gen XML - ID:{$poll_id}] Failed to save XML file: " . $file_path . ". libxml errors: " . print_r(libxml_get_errors(), true));
         libxml_clear_errors();
    }
}

function kashiwazaki_poll_generate_yaml( $poll_id, $poll_data ) {
    $dir_path = KASHIWAZAKI_POLL_DIR . 'datasets/yaml/';
    $file_path = $dir_path . $poll_id . '.yaml';
    if ( ! file_exists( $dir_path ) && ! wp_mkdir_p( $dir_path ) ) { error_log("[Poll Data Gen YAML - ID:{$poll_id}] Failed to create directory: " . $dir_path); return; }
    if ( ! is_writable( $dir_path ) ) { error_log("[Poll Data Gen YAML - ID:{$poll_id}] Directory not writable: " . $dir_path); return; }

    $yaml_content = '';
    $exclude_keys = ['options'];

    foreach ($poll_data as $key => $value) {
        if (in_array($key, $exclude_keys)) continue;
        $quoted_value = _kashiwazaki_poll_yaml_quote_string($value);
        $yaml_content .= $key . ": " . $quoted_value . "\n";
    }

    if (isset($poll_data['options']) && is_array($poll_data['options']) && !empty($poll_data['options'])) {
        $yaml_content .= "options:\n";
        foreach ($poll_data['options'] as $option) {
            $quoted_text = _kashiwazaki_poll_yaml_quote_string($option['text']);
            $yaml_content .= "  - text: " . $quoted_text . "\n";
            $yaml_content .= "    count: " . $option['count'] . "\n";
            $yaml_content .= "    percentage: " . $option['percentage'] . "\n";
        }
    } else {
        $yaml_content .= "options: []\n";
    }

    if (file_put_contents($file_path, $yaml_content) === false) {
        error_log("[Poll Data Gen YAML - ID:{$poll_id}] Failed to write YAML file: " . $file_path);
    }
}

function kashiwazaki_poll_generate_json( $poll_id, $poll_data ) {
    $dir_path = KASHIWAZAKI_POLL_DIR . 'datasets/json/';
    $file_path = $dir_path . $poll_id . '.json';
    if ( ! file_exists( $dir_path ) && ! wp_mkdir_p( $dir_path ) ) { error_log("[Poll Data Gen JSON - ID:{$poll_id}] Failed to create directory: " . $dir_path); return; }
    if ( ! is_writable( $dir_path ) ) { error_log("[Poll Data Gen JSON - ID:{$poll_id}] Directory not writable: " . $dir_path); return; }

    if (!isset($poll_data['copyright']) || !isset($poll_data['license'])) {
        error_log("[Poll Data Gen JSON - ID:{$poll_id}] WARNING: Missing expected copyright/license key in data for JSON.");
    }

    $json_content = json_encode($poll_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json_content !== false) {
        if (file_put_contents($file_path, $json_content) === false) {
            error_log("[Poll Data Gen JSON - ID:{$poll_id}] Failed to write JSON file: " . $file_path);
        }
    } else {
         error_log("[Poll Data Gen JSON - ID:{$poll_id}] Failed to encode data to JSON. Error: " . json_last_error_msg());
    }
}

function kashiwazaki_poll_generate_svg_pie( $poll_id, $poll_title, $total_votes, $options_data, $copyright_site_url, $license ) {
    $dir_path = KASHIWAZAKI_POLL_DIR . 'datasets/svg/';
    $file_path = $dir_path . $poll_id . '.svg';
    if ( ! file_exists( $dir_path ) && ! wp_mkdir_p( $dir_path ) ) { error_log("[Poll Data Gen SVG - ID:{$poll_id}] Failed to create directory: " . $dir_path); return; }
    if ( ! is_writable( $dir_path ) ) { error_log("[Poll Data Gen SVG - ID:{$poll_id}] Directory not writable: " . $dir_path); return; }

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
        if (!empty($options_data)) {
            foreach ($options_data as $index => $option) {
                $count = $option['count']; if ($count <= 0) continue; $percentage = $option['percentage']; $angle = ($count / $total_votes) * 360;
                $end_angle = $start_angle + $angle; $start_rad = deg2rad($start_angle); $end_rad = deg2rad($end_angle);
                $startX = $cx + $radius * cos($start_rad); $startY = $cy + $radius * sin($start_rad); $endX = $cx + $radius * cos($end_rad); $endY = $cy + $radius * sin($end_rad);
                $large_arc_flag = ($angle > 180) ? 1 : 0; $path_data = implode(' ', ['M', $cx, $cy, 'L', $startX, $startY, 'A', $radius, $radius, 0, $large_arc_flag, 1, $endX, $endY, 'Z']);
                $color_index = $index % count($colors); $color = $colors[$color_index];
                $svg_content .= '<path d="' . $path_data . '" fill="' . $color . '" stroke="#fff" stroke-width="1" />' . "\n";
                $legend_items[] = ['text' => $option['text'], 'color' => $color, 'percentage' => $percentage]; $start_angle = $end_angle;
            }
        }
    }
    $legend_x = 50; $legend_y = $legend_y_start; $legend_rect_size = 12; $legend_spacing = 18;
    foreach($legend_items as $item) {
         $svg_content .= '<rect x="' . $legend_x . '" y="' . ($legend_y - $legend_rect_size/2 -2) . '" width="' . $legend_rect_size . '" height="' . $legend_rect_size . '" fill="' . $item['color'] . '" />' . "\n";
         $svg_content .= '<text x="' . ($legend_x + $legend_rect_size + 8) . '" y="' . $legend_y . '" font-family="sans-serif" font-size="12" fill="#333" dominant-baseline="middle">' . htmlspecialchars($item['text'], ENT_QUOTES, 'UTF-8') . ' (' . $item['percentage'] . '%)</text>' . "\n";
         $legend_y += $legend_spacing;
    }
    $svg_content .= '</svg>';

    if ( file_put_contents($file_path, $svg_content) === false ) {
        error_log("[Poll Data Gen SVG - ID:{$poll_id}] Failed to write SVG file: " . $file_path);
    }
}

function kashiwazaki_poll_generate_sitemap_poll() {
    // sitemap-poll-datasets.xmlのみを生成
    $sitemap_file_path = ABSPATH . 'sitemap-poll-datasets.xml';

    if ( ! is_writable( ABSPATH ) ) {
        error_log("[Sitemap Gen] WordPress root directory not writable for sitemap: " . ABSPATH);
        return;
    }

    // Delete existing sitemap file to ensure fresh generation
    if ( file_exists( $sitemap_file_path ) ) {
        @unlink( $sitemap_file_path );
    }

    // 古いsitemap-poll.xmlが存在する場合は削除
    $old_sitemap_path = ABSPATH . 'sitemap-poll.xml';
    if ( file_exists( $old_sitemap_path ) ) {
        @unlink( $old_sitemap_path );
    }

    ob_start();
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<!-- Generated by Kashiwazaki SEO Poll - Datasets Only -->' . "\n";
    ?>
    <urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd http://www.google.com/schemas/sitemap-image/1.1 http://www.google.com/schemas/sitemap-image/1.1/sitemap-image.xsd">
    <?php
    $polls = get_posts(array(
        'post_type'      => 'poll',
        'post_status'    => 'publish',
        'numberposts'    => -1,
        'orderby'        => 'modified',
        'order'          => 'DESC',
    ));

    $file_types = ['csv', 'xml', 'yaml', 'json', 'svg'];

    foreach ( $polls as $poll ) {
        $poll_id = $poll->ID;
        $last_modified_gmt = get_post_modified_time( 'c', true, $poll_id );

        foreach ($file_types as $type) {
            $dataset_page_url = kashiwazaki_poll_get_single_dataset_page_url($poll_id, $type);
            $dataset_file_path = kashiwazaki_poll_get_dataset_file_path($poll_id, $type);
            $dataset_last_mod = (file_exists($dataset_file_path)) ? date_i18n('c', filemtime($dataset_file_path)) : $last_modified_gmt;

            if ($dataset_page_url) {
                echo '  <url>' . "\n";
                echo '    <loc>' . esc_url($dataset_page_url) . '</loc>' . "\n";
                echo '    <lastmod>' . $dataset_last_mod . '</lastmod>' . "\n";
                echo '    <changefreq>daily</changefreq>' . "\n";
                echo '    <priority>0.7</priority>' . "\n";
                echo '  </url>' . "\n";
            }
        }
    }
    ?>
    </urlset>
    <?php
    $sitemap_content = ob_get_clean();
    @file_put_contents($sitemap_file_path, $sitemap_content);
}