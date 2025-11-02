<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function kashiwazaki_poll_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'tk_poll' );
    $poll_id = intval( $atts['id'] );
    if ( ! $poll_id ) return '';
    $poll_post = get_post( $poll_id );
    if ( ! $poll_post || $poll_post->post_type !== 'poll' || $poll_post->post_status !== 'publish' ) return '<p>データが見つかりません。</p>';
    $question = $poll_post->post_title;
    $question_esc = esc_html($question);
    $heading_level = get_post_meta( $poll_id, '_kashiwazaki_poll_heading_level', true );
    if ( ! in_array( $heading_level, array( 'h1','h2','h3','h4','h5','h6' ) ) ) $heading_level = 'h3';
    $options  = get_post_meta( $poll_id, '_kashiwazaki_poll_options', true );
    if ( ! is_array( $options ) || empty( $options ) ) return '<p>選択肢がありません。</p>';
    $poll_type = get_post_meta( $poll_id, '_kashiwazaki_poll_type', true );
    if ( ! in_array( $poll_type, array( 'single','multiple' ) ) ) $poll_type = 'multiple';
    $poll_description = get_post_meta( $poll_id, '_kashiwazaki_poll_description', true );
    $datePublished    = get_the_date( 'c', $poll_post );
    $counts = get_post_meta( $poll_id, '_kashiwazaki_poll_counts', true );
    $total_votes = ( is_array( $counts ) && ! empty( $counts ) ) ? array_sum( $counts ) : 0;
    $has_data = $total_votes > 0;
    $ip         = $_SERVER['REMOTE_ADDR'];
    $cookie_key = 'kashiwazaki_poll_voted_' . $poll_id;
    $already_voted = kashiwazaki_poll_is_already_voted( $poll_id, $ip, $cookie_key );
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
        'pollId'       => $poll_id, 'alreadyVoted' => $already_voted, 'hasData' => $has_data,
        'siteName'     => $site_name, 'pollQuestion' => $question, 'ajaxUrl' => $ajax_url,
        'nonce'        => wp_create_nonce( 'kashiwazaki_poll_vote_' . $poll_id ),
        'datasetTheme' => $current_theme,
    );
    $data_json = json_encode( $poll_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    if ($data_json !== false) { echo sprintf("<script id='kashiwazaki-poll-data-%d'>kashiwazakiPollAllData[%d] = %s;</script>\n", $poll_id, $poll_id, $data_json); }

    echo '<div class="kashiwazaki-poll-block" data-poll-id="' . $poll_id . '">';
    echo '<div class="kashiwazaki-poll-title">' . $question_esc . '</div>';
    echo '<div id="kashiwazaki-poll-view-result-area-'. $poll_id .'" class="kashiwazaki-poll-view-result-trigger-area" style="'. ($has_data && !$already_voted ? '' : 'display: none;') .'">';
    echo '<button type="button" class="kashiwazaki-poll-view-result" data-pollid="' . $poll_id . '">集計データを拡大</button>';
    echo '</div>';
    echo '<div id="kashiwazaki-poll-result-' . $poll_id . '" class="kashiwazaki-poll-result-container"></div>';
    echo '<div id="kashiwazaki-poll-preview-' . $poll_id . '" class="kashiwazaki-poll-preview-container" style="'. ($has_data && !$already_voted ? '' : 'display: none;') .'"></div>';
    echo '<form class="kashiwazaki-poll-form" data-pollid="' . $poll_id . '">';
    echo wp_nonce_field( 'kashiwazaki_poll_vote_' . $poll_id, '_wpnonce', true, false );
    echo '<input type="hidden" name="poll_id" value="' . $poll_id . '">';
    echo '<input type="hidden" name="poll_type" value="' . $poll_type . '">';
    echo '<button type="button" id="kashiwazaki-poll-submit-top-' . $poll_id . '" class="kashiwazaki-poll-submit">投票する</button>';
    foreach ( $options as $i => $opt ) {
        $opt_esc = esc_html( $opt );
        $input_type = ($poll_type === 'multiple') ? 'checkbox' : 'radio';
        echo '<div><label><input type="'.$input_type.'" name="poll_options[]" value="' . $i . '"> ' . $opt_esc . '</label></div>';
    }
    echo '<button type="button" id="kashiwazaki-poll-submit-bottom-' . $poll_id . '" class="kashiwazaki-poll-submit">投票する</button>';
    echo '</form>';

    if ( $already_voted ) {
        echo '<p class="voted-msg">既に投票しています</p>';
        echo '<form class="kashiwazaki-poll-form kashiwazaki-poll-form-disabled" data-pollid="' . $poll_id . '" style="display: none;">';
        foreach ( $options as $i => $opt ) {
            $opt_esc = esc_html( $opt );
            $input_type = ($poll_type === 'multiple') ? 'checkbox' : 'radio';
            echo '<div><label><input type="'.$input_type.'" name="poll_options[]" value="' . $i . '" disabled> ' . $opt_esc . '</label></div>';
        }
        echo '</form>';
    }

    // 個別データセットページへのリンクを追加（フィルターで制御可能）
    $show_detail_link = apply_filters('kashiwazaki_poll_show_detail_link', true, $poll_id);
    if ($show_detail_link) {
        echo '<div class="poll-detail-link" style="margin-top: 20px; text-align: center;">';
        echo '<a href="' . esc_url(get_permalink($poll_id)) . '" class="poll-detail-button" style="display: inline-block; padding: 8px 16px; border: 1px solid; border-radius: 3px; text-decoration: none; font-size: 0.9em; transition: opacity 0.2s;">このデータセットの詳細ページを見る</a>';
        echo '</div>';
    }

    echo '</div>';
    $poll_desc_length = strlen( strip_tags( $poll_description ) );
    if ( $poll_desc_length >= 150 ) {
         $current_counts = get_post_meta( $poll_id, '_kashiwazaki_poll_counts', true );
         $counts_for_ld = $current_counts; if ( ! is_array( $counts_for_ld ) ) { $counts_for_ld = array_fill( 0, count( $options ), 0 ); } elseif ( count( $counts_for_ld ) < count( $options ) ) { $counts_for_ld = array_pad( $counts_for_ld, count( $options ), 0 ); } elseif ( count( $counts_for_ld ) > count( $options ) ) { $counts_for_ld = array_slice( $counts_for_ld, 0, count( $options ) ); }
         $variableMeasured = []; foreach ( $options as $i => $opt ) { $value = isset( $counts_for_ld[$i] ) ? intval($counts_for_ld[$i]) : 0; $variableMeasured[] = ["@type"=>"PropertyValue", "name"=>$opt, "value"=>$value]; }
         $poll_license = get_post_meta( $poll_id, '_kashiwazaki_poll_license', true ); if ( empty( $poll_license ) ) { $poll_license = 'https://creativecommons.org/licenses/by/4.0/'; }
         global $post; $post_url = ''; $keywords = [];
         if ( is_object($post) && isset($post->ID) ) { $post_url = get_permalink( $post->ID ); $shortcode_post_tags = get_the_tags( $post->ID ); if ( ! is_wp_error( $shortcode_post_tags ) && is_array( $shortcode_post_tags ) ) { foreach ( $shortcode_post_tags as $tag ) { $keywords[] = $tag->name; } } }
         else { $post_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"; }
         $site_organization_name = get_bloginfo('name'); $site_organization_url = home_url();
         $site_admin_email = get_bloginfo('admin_email');

         // Creator情報を設定から取得
         $plugin_settings = get_option( 'kashiwazaki_poll_settings', array(
             'creator_type' => 'organization_only',
             'creator_person_name' => '',
             'creator_person_url' => '',
             'creator_organization_name' => get_bloginfo('name'),
             'creator_organization_url' => home_url(),
             'creator_organization_email' => get_bloginfo('admin_email')
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
             $org_email = !empty($plugin_settings['creator_organization_email']) ? $plugin_settings['creator_organization_email'] : $site_admin_email;

             $creator_info[] = [
                 "@type" => "Organization",
                 "name" => $org_name,
                 "url" => $org_url,
                 "contactPoint" => [
                     "@type" => "ContactPoint",
                     "contactType" => "customer service",
                     "email" => $org_email
                 ]
             ];
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

         $distribution = []; $datasets_base_path = KASHIWAZAKI_POLL_DIR . 'datasets/'; $datasets_base_url = KASHIWAZAKI_POLL_URL . 'datasets/';
         $file_types_sd = [ 'csv' => ['path' => 'csv/', 'ext' => '.csv', 'format' => 'text/csv'], 'xml' => ['path' => 'xml/', 'ext' => '.xml', 'format' => 'application/xml'], 'yaml' => ['path' => 'yaml/', 'ext' => '.yaml', 'format' => 'application/x-yaml'], 'json' => ['path' => 'json/', 'ext' => '.json', 'format' => 'application/json'], 'svg' => ['path' => 'svg/', 'ext' => '.svg', 'format' => 'image/svg+xml'], ];
         foreach ($file_types_sd as $key => $type) { $file_path = $datasets_base_path . $type['path'] . $poll_id . $type['ext']; if (file_exists($file_path)) { $distribution[] = ['@type' => 'DataDownload', 'contentUrl' => $datasets_base_url . $type['path'] . $poll_id . $type['ext'], 'encodingFormat' => $type['format']]; } }

         $publisher_info = ["@type"=>"Organization", "name"=>$site_organization_name, "url"=>$site_organization_url, "email"=>$site_admin_email];
         $dataset = [ "@context" => "https://schema.org/", "@type" => "Dataset", "name" => $question, "description" => strip_tags($poll_description), "url" => $post_url, "creator" => $creator_info, "publisher" => $publisher_info, "datePublished" => $datePublished, "license" => $poll_license, "variableMeasured" => $variableMeasured ];
         if ( $provider_info !== null ) {
             $dataset["provider"] = $provider_info;
         }
         if ( ! empty( $keywords ) ) { $dataset["keywords"] = $keywords; }
         if ( ! empty( $distribution ) ) { $dataset["distribution"] = $distribution; }
         if (!empty($dataset['url'])) { echo '<script type="application/ld+json">' . json_encode($dataset, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>'; }
    }

    return ob_get_clean();
}
add_shortcode( 'tk_poll', 'kashiwazaki_poll_shortcode' );
