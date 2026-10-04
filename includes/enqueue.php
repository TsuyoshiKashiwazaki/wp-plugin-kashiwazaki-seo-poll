<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_enqueue_scripts', function() {

    $chart_js_version = '4.4.8';
    wp_register_script(
        'chart-js',
        'https://cdn.jsdelivr.net/npm/chart.js@' . $chart_js_version . '/dist/chart.umd.min.js',
        array(),
        $chart_js_version,
        true
    );

    $datalabels_version = '2.2.0';
    wp_register_script(
        'chartjs-plugin-datalabels',
        'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@' . $datalabels_version . '/dist/chartjs-plugin-datalabels.min.js',
        array('chart-js'),
        $datalabels_version,
        true
    );

    $css_file_path = KASHIWAZAKI_POLL_DIR . 'assets/css/front.css';
    // get_plugin_data は管理画面用の関数で公開ページでは読み込まれていないことがあるため、定数を使う。
    $css_default_version = KASHIWAZAKI_POLL_VERSION;
    $css_version = file_exists($css_file_path) ? filemtime($css_file_path) : $css_default_version;
    wp_enqueue_style(
        'kashiwazaki-front-css',
        KASHIWAZAKI_POLL_URL . 'assets/css/front.css',
        array(),
        $css_version
    );

    // Poll 単一投稿ページでカラーテーマのCSS変数を設定
    if (is_singular('poll')) {
        $theme_data = kashiwazaki_poll_get_color_theme();
        $color_theme = $theme_data['name'];
        $current_theme = $theme_data['colors'];

        $custom_css = "
        :root {
            --usage-bg: " . ($color_theme === 'dark' ? $current_theme['header_bg'] : '#ffffff') . ";
            --usage-border: " . ($color_theme === 'dark' ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.08)') . ";
            --usage-label-color: " . $current_theme['body_color'] . ";
            --usage-link-color: " . $current_theme['link_color'] . ";
            --usage-accent: " . $current_theme['accent_color'] . ";
        }
        ";
        wp_add_inline_style('kashiwazaki-front-css', $custom_css);
    }

    $js_file_path = KASHIWAZAKI_POLL_DIR . 'assets/js/poll-frontend.js';
    $js_default_version = KASHIWAZAKI_POLL_VERSION;
    $js_version = file_exists($js_file_path) ? filemtime($js_file_path) : $js_default_version;
    wp_register_script(
        'kashiwazaki-poll-frontend-js',
        KASHIWAZAKI_POLL_URL . 'assets/js/poll-frontend.js',
        array('chart-js', 'chartjs-plugin-datalabels'),
        $js_version,
        true
    );

});

add_action( 'admin_enqueue_scripts', function( $hook_suffix ) {
    // 本プラグインの画面（データセット一覧・編集・基本設定）だけで読み込む。
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    $is_poll_screen = $screen && 'poll' === $screen->post_type;
    if ( ! $is_poll_screen && ! ( function_exists( 'kashiwazaki_poll_is_settings_screen' ) && kashiwazaki_poll_is_settings_screen() ) ) {
        return;
    }
    $admin_css_path = KASHIWAZAKI_POLL_DIR . 'assets/css/admin.css';
    $admin_css_default_version = KASHIWAZAKI_POLL_VERSION;
    $admin_css_version = file_exists($admin_css_path) ? filemtime($admin_css_path) : $admin_css_default_version;
    wp_enqueue_style( 'kashiwazaki-poll-admin', KASHIWAZAKI_POLL_URL . 'assets/css/admin.css', array(), $admin_css_version);
});