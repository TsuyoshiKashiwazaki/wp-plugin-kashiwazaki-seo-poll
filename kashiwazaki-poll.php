<?php
/**
 * Plugin Name: Kashiwazaki SEO Poll
 * Plugin URI:  https://www.tsuyoshikashiwazaki.jp/
 * Description: WordPressで複数のアンケートを作成・管理できる投票プラグインです。ショートコード [tk_poll id=123] を任意の投稿や固定ページに挿入することでアンケートフォームを簡単に設置できます。単一選択・複数選択に対応しており、投票結果はChart.jsを利用したグラフでリアルタイム表示されます。また、IPアドレスとCookieを利用した重複投票防止機能や、SEO向けの構造化データ（Dataset）の自動生成機能も備えています。
 * Version:     1.0.3
 * Author:      柏崎剛
 * Author URI:  https://www.tsuyoshikashiwazaki.jp/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kashiwazaki-seo-poll
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'KASHIWAZAKI_POLL_FILE', __FILE__ );
define( 'KASHIWAZAKI_POLL_DIR', plugin_dir_path( __FILE__ ) );
define( 'KASHIWAZAKI_POLL_URL', plugin_dir_url( __FILE__ ) );

register_activation_hook( __FILE__, 'kashiwazaki_poll_activate' );
register_deactivation_hook( __FILE__, 'kashiwazaki_poll_deactivate' );

function kashiwazaki_poll_activate() {
    $dirs_to_create = [
        KASHIWAZAKI_POLL_DIR . 'datasets/csv',
        KASHIWAZAKI_POLL_DIR . 'datasets/xml',
        KASHIWAZAKI_POLL_DIR . 'datasets/yaml',
        KASHIWAZAKI_POLL_DIR . 'datasets/json',
        KASHIWAZAKI_POLL_DIR . 'datasets/svg',
    ];
    foreach ($dirs_to_create as $dir) {
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
            @file_put_contents($dir . '/index.php', '<?php // Silence is golden.');
        }
    }
    if ( false === get_option( 'kashiwazaki_poll_settings' ) ) {
        $default_settings = array(
            'structured_data_provider' => 0,
            'dataset_page_title' => '集計データ一覧',
            'dataset_spatial_coverage' => '日本',
        );
        update_option( 'kashiwazaki_poll_settings', $default_settings );
    }

    // リライトルールをフラッシュ（ファイル読み込み後に実行される）
    add_option('kashiwazaki_poll_flush_rewrite_rules', 1);
}

function kashiwazaki_poll_deactivate() {
    flush_rewrite_rules();
}

require_once KASHIWAZAKI_POLL_DIR . 'includes/util.php';
require_once KASHIWAZAKI_POLL_DIR . 'includes/file-generators.php';
require_once KASHIWAZAKI_POLL_DIR . 'includes/cpt.php';
require_once KASHIWAZAKI_POLL_DIR . 'includes/enqueue.php';
require_once KASHIWAZAKI_POLL_DIR . 'includes/routes.php';
require_once KASHIWAZAKI_POLL_DIR . 'includes/page-detection.php';

if ( is_admin() ) {
    require_once KASHIWAZAKI_POLL_DIR . 'admin/jax-handlers.php';
    require_once KASHIWAZAKI_POLL_DIR . 'admin/meta-boxes.php';
    require_once KASHIWAZAKI_POLL_DIR . 'admin/settings-page.php';
}

require_once KASHIWAZAKI_POLL_DIR . 'public/shortcode.php';
require_once KASHIWAZAKI_POLL_DIR . 'public/ajax.php';

// プラグイン一覧に「設定」リンクを追加
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'kashiwazaki_poll_add_settings_link' );
function kashiwazaki_poll_add_settings_link( $links ) {
    $settings_link = '<a href="' . admin_url( 'admin.php?page=kashiwazaki_poll_settings' ) . '">' . __( '設定', 'kashiwazaki-seo-poll' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
