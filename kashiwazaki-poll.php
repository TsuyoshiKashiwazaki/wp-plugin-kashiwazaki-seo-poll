<?php
/**
 * Plugin Name: Kashiwazaki SEO Poll
 * Plugin URI:  https://www.tsuyoshikashiwazaki.jp/
 * Description: WordPressで複数のアンケートを作成・管理できる投票プラグインです。ショートコード [tk_poll id=123] を任意の投稿や固定ページに挿入することでアンケートフォームを簡単に設置できます。単一選択・複数選択に対応しており、投票結果はChart.jsを利用したグラフでリアルタイム表示されます。また、IPアドレスとCookieを利用した重複投票防止機能や、SEO向けの構造化データ（Dataset）の自動生成機能も備えています。
 * Version:     1.0.5
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
    // データファイルは uploads 配下に置く（プラグイン更新でフォルダが消えないようにする）。
    require_once KASHIWAZAKI_POLL_DIR . 'includes/util.php';
    $base = kashiwazaki_poll_datasets_base_dir();
    foreach ( array( 'csv', 'xml', 'yaml', 'json', 'svg' ) as $type ) {
        $dir = $base . $type;
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }
    }
    if ( false === get_option( 'kashiwazaki_poll_settings' ) ) {
        update_option( 'kashiwazaki_poll_settings', kashiwazaki_poll_default_settings() );
    }

    // リライトルールをフラッシュ（ファイル読み込み後に実行される）
    add_option('kashiwazaki_poll_flush_rewrite_rules', 1);
}

/**
 * 設定のデフォルト値。activation と既存サイトのアップグレード補完で共有する。
 *
 * @return array
 */
function kashiwazaki_poll_default_settings() {
    return array(
        'structured_data_provider'     => 0,
        'dataset_page_title'           => '集計データ一覧',
        'dataset_spatial_coverage'     => '日本',
        'dataset_color_theme'          => 'minimal',
        'creator_type'                 => 'organization_only',
        'creator_person_name'          => '',
        'creator_person_url'           => '',
        'creator_organization_name'    => get_bloginfo( 'name' ),
        'creator_organization_url'     => home_url(),
        'creator_organization_email'   => get_bloginfo( 'admin_email' ),
    );
}

/**
 * 既存インストールの設定に不足キーを補完する（旧バージョンからの更新対策）。
 * activation は新規時のみ走るため、更新サイトでは未定義キー参照の警告が出る。
 */
add_action( 'plugins_loaded', 'kashiwazaki_poll_maybe_upgrade_settings' );
function kashiwazaki_poll_maybe_upgrade_settings() {
    $settings = get_option( 'kashiwazaki_poll_settings' );
    if ( ! is_array( $settings ) ) {
        return; // 未作成時は activation 側が処理。
    }
    $merged = wp_parse_args( $settings, kashiwazaki_poll_default_settings() );
    if ( $merged !== $settings ) {
        update_option( 'kashiwazaki_poll_settings', $merged );
    }
}

/**
 * タイムスタンプ移行（1回のみ）。
 *
 * 投票時刻/リセット時刻の保存を current_time('timestamp')（= UTC + サイトのGMTオフセット）から
 * time()（真のUTC）へ変更したため、既存データの値からオフセット分を差し引いて基準を揃える。
 * これをしないと、リセット済みpollで「投票時刻 < リセット時刻」の比較がオフセット分ズレ、
 * リセット直後に重複投票が一時的に可能になる等の不整合が生じる。
 * Cookie はクライアント側のため移行できないが、旧Cookie値(オフセット込み=より大きい)は
 * 新基準のリセット時刻以上となり重複判定が成立するため、安全側に倒れる。
 */
add_action( 'plugins_loaded', 'kashiwazaki_poll_maybe_migrate_timestamps' );
function kashiwazaki_poll_maybe_migrate_timestamps() {
    if ( '1' === get_option( 'kashiwazaki_poll_ts_migrated_utc' ) ) {
        return;
    }
    $offset = (int) round( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS );
    if ( 0 !== $offset ) {
        // グローバルリセット時刻
        $g = (int) get_option( 'kashiwazaki_poll_reset_timestamp', 0 );
        if ( $g > 0 ) {
            update_option( 'kashiwazaki_poll_reset_timestamp', $g - $offset );
        }
        // poll ごとの投票IPタイムスタンプと個別リセット時刻
        $poll_ids = get_posts( array(
            'post_type'   => 'poll',
            'post_status' => 'any',
            'numberposts' => -1,
            'fields'      => 'ids',
        ) );
        foreach ( $poll_ids as $pid ) {
            $vips = get_post_meta( $pid, '_kashiwazaki_poll_voted_ips', true );
            if ( is_array( $vips ) && ! empty( $vips ) ) {
                foreach ( $vips as $k => $v ) {
                    $vips[ $k ] = (int) $v - $offset;
                }
                update_post_meta( $pid, '_kashiwazaki_poll_voted_ips', $vips );
            }
            $rt = (int) get_post_meta( $pid, '_kashiwazaki_poll_reset_ts', true );
            if ( $rt > 0 ) {
                update_post_meta( $pid, '_kashiwazaki_poll_reset_ts', $rt - $offset );
            }
        }
    }
    update_option( 'kashiwazaki_poll_ts_migrated_utc', '1' );
}

/**
 * データファイル移行（1回のみ）。
 *
 * 旧版はプラグインディレクトリ内 datasets/ にファイルを生成していた。新版は uploads 配下に
 * 置くため、既存ファイルを uploads 側へコピーしてダウンロードURLの 404 を防ぐ。
 * （コピーが失敗しても次回の投票/保存で再生成されるため致命的ではない。）
 */
add_action( 'plugins_loaded', 'kashiwazaki_poll_maybe_migrate_data_files' );
function kashiwazaki_poll_maybe_migrate_data_files() {
    if ( '1' === get_option( 'kashiwazaki_poll_files_migrated_uploads' ) ) {
        return;
    }
    $old_base = KASHIWAZAKI_POLL_DIR . 'datasets/';
    if ( is_dir( $old_base ) && function_exists( 'kashiwazaki_poll_datasets_base_dir' ) ) {
        $new_base = kashiwazaki_poll_datasets_base_dir();
        foreach ( array( 'csv', 'xml', 'yaml', 'json', 'svg' ) as $type ) {
            $old_dir = $old_base . $type . '/';
            if ( ! is_dir( $old_dir ) ) {
                continue;
            }
            $new_dir = $new_base . $type . '/';
            if ( ! file_exists( $new_dir ) ) {
                wp_mkdir_p( $new_dir );
            }
            foreach ( (array) glob( $old_dir . '*.' . $type ) as $src ) {
                $dest = $new_dir . basename( $src );
                if ( ! file_exists( $dest ) ) {
                    @copy( $src, $dest );
                }
            }
        }
    }
    update_option( 'kashiwazaki_poll_files_migrated_uploads', '1' );
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
