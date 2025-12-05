<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function kashiwazaki_poll_is_already_voted( $poll_id, $ip, $cookie_key ) {
    $reset_ts = (int) get_option( 'kashiwazaki_poll_reset_timestamp', 0 );
    $voted_ips = get_post_meta( $poll_id, '_kashiwazaki_poll_voted_ips', true );
    if ( ! is_array( $voted_ips ) ) {
        $voted_ips = array();
    }
    if ( isset( $voted_ips[ $ip ] ) ) {
        $vote_time = (int) $voted_ips[ $ip ];
        if ( $vote_time >= $reset_ts ) {
            return true;
        }
    }
    return false;
}

function kashiwazaki_poll_get_dataset_file_path( $poll_id, $file_type ) {
    $allowed_types = ['csv', 'xml', 'yaml', 'json', 'svg'];
    if ( ! in_array( $file_type, $allowed_types ) ) {
        return false;
    }
    return KASHIWAZAKI_POLL_DIR . "datasets/{$file_type}/{$poll_id}.{$file_type}";
}

function kashiwazaki_poll_get_dataset_file_url( $poll_id, $file_type ) {
    $allowed_types = ['csv', 'xml', 'yaml', 'json', 'svg'];
    if ( ! in_array( $file_type, $allowed_types ) ) {
        return false;
    }
    return KASHIWAZAKI_POLL_URL . "datasets/{$file_type}/{$poll_id}.{$file_type}";
}

function kashiwazaki_poll_get_dataset_index_url() {
    return home_url( '/datasets/' );
}

function kashiwazaki_poll_get_single_dataset_page_url( $poll_id, $file_type ) {
    if ( $file_type === 'html' ) {
        return home_url( "/datasets/detail-{$poll_id}/" );
    }
    $allowed_types = ['csv', 'xml', 'yaml', 'json', 'svg'];
    if ( ! in_array( $file_type, $allowed_types ) ) {
        return false;
    }
    return home_url( "/datasets/{$file_type}/detail-{$poll_id}/" );
}

/**
 * カラーテーマ設定を取得
 *
 * @return array カラーテーマ名と設定値の配列
 */
function kashiwazaki_poll_get_color_theme() {
    $settings = get_option( 'kashiwazaki_poll_settings', array( 'dataset_color_theme' => 'minimal' ) );
    $color_theme_name = isset($settings['dataset_color_theme']) ? $settings['dataset_color_theme'] : 'minimal';

    // カラーテーマ定義
    $themes = array(
        'minimal' => array(
            'body_bg' => '#ffffff',
            'body_color' => '#333333',
            'header_bg' => '#f8f9fa',
            'header_color' => '#333333',
            'accent_color' => '#6c757d',
            'button_primary' => '#6c757d',
            'button_secondary' => '#e9ecef',
            'border_color' => '#ddd',
            'tag_bg' => '#95a5a6',
            'link_color' => '#6c757d'
        ),
        'blue' => array(
            'body_bg' => '#ffffff',
            'body_color' => '#333333',
            'header_bg' => '#0073aa',
            'header_color' => '#ffffff',
            'accent_color' => '#0073aa',
            'button_primary' => '#0073aa',
            'button_secondary' => '#e9ecef',
            'border_color' => '#ddd',
            'tag_bg' => '#0073aa',
            'link_color' => '#0073aa'
        ),
        'green' => array(
            'body_bg' => '#ffffff',
            'body_color' => '#333333',
            'header_bg' => '#28a745',
            'header_color' => '#ffffff',
            'accent_color' => '#28a745',
            'button_primary' => '#28a745',
            'button_secondary' => '#e9ecef',
            'border_color' => '#ddd',
            'tag_bg' => '#28a745',
            'link_color' => '#28a745'
        ),
        'orange' => array(
            'body_bg' => '#ffffff',
            'body_color' => '#333333',
            'header_bg' => '#fd7e14',
            'header_color' => '#ffffff',
            'accent_color' => '#fd7e14',
            'button_primary' => '#fd7e14',
            'button_secondary' => '#e9ecef',
            'border_color' => '#ddd',
            'tag_bg' => '#fd7e14',
            'link_color' => '#fd7e14'
        ),
        'purple' => array(
            'body_bg' => '#ffffff',
            'body_color' => '#333333',
            'header_bg' => '#6f42c1',
            'header_color' => '#ffffff',
            'accent_color' => '#6f42c1',
            'button_primary' => '#6f42c1',
            'button_secondary' => '#e9ecef',
            'border_color' => '#ddd',
            'tag_bg' => '#6f42c1',
            'link_color' => '#6f42c1'
        ),
        'dark' => array(
            'body_bg' => '#2c3e50',
            'body_color' => '#ecf0f1',
            'header_bg' => '#34495e',
            'header_color' => '#ecf0f1',
            'accent_color' => '#3498db',
            'button_primary' => '#3498db',
            'button_secondary' => '#34495e',
            'border_color' => '#555',
            'tag_bg' => '#7f8c8d',
            'link_color' => '#3498db'
        )
    );

    $theme_colors = isset($themes[$color_theme_name]) ? $themes[$color_theme_name] : $themes['minimal'];

    return array(
        'name' => $color_theme_name,
        'colors' => $theme_colors
    );
}
