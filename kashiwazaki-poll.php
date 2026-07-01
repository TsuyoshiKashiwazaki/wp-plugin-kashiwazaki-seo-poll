<?php
/**
 * Plugin Name: Kashiwazaki SEO Poll
 * Plugin URI:  https://www.tsuyoshikashiwazaki.jp/
 * Description: WordPressで複数のアンケートを作成・管理できる投票プラグインです。ショートコード [tk_poll id=123] を任意の投稿や固定ページに挿入することでアンケートフォームを簡単に設置できます。単一選択・複数選択に対応しており、投票結果はChart.jsを利用したグラフでリアルタイム表示されます。また、IPアドレスとCookieを利用した重複投票防止機能や、SEO向けの構造化データ（Dataset）の自動生成機能も備えています。
 * Version:     1.0.6
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
 * データファイル復旧（1回のみ、WP-Cron 単発イベント経由）。
 *
 * 旧版（1.0.4 以前）はプラグインディレクトリ内 datasets/ にデータファイルを生成していた。
 * 新版は uploads 配下に置くよう変更したが、WordPress のプラグイン更新は旧プラグイン
 * フォルダを丸ごと削除してから新版を展開するため、旧ファイルは更新時点で失われる。
 * 「旧フォルダからのコピー移行」では復旧できない（コピー元が既に存在しない）ので、
 * DB に残る投票データ（_kashiwazaki_poll_counts）から全公開 poll のファイルを再生成する。
 * 投票データ自体には一切触れない。
 *
 * 実行経路は 2 つ用意する（どちらか片方でも復旧できるようにする）:
 * - WP-Cron 単発イベント: フロント／管理どちらのアクセスでも次の cron 実行で走るため、
 *   自動更新後に管理画面へ来ないサイトでも復旧する。重い全件再生成を投票などの通常
 *   リクエストや公開 AJAX（admin-ajax.php）の同期処理として走らせないためでもある。
 * - 管理画面の即時実行（admin_init、管理者・非 AJAX 限定）: DISABLE_WP_CRON かつ外部
 *   cron 未設定で cron が発火しない環境でも、管理者が更新後に管理画面を開けば復旧する。
 * 両経路は共通の実行関数を短時間トランジェントロックで保護し、二重処理を防ぐ。
 *
 * ガードキーは v1.0.6 専用の新しいものを使い、旧「コピー移行」フラグ
 * kashiwazaki_poll_files_migrated_uploads が立ってしまった壊れた環境でも 1 回確実に走らせる。
 * 全対象 poll の全 5 形式が揃うまで（無限ループ回避のため上限つきで）再試行し、途中失敗を
 * 恒久スキップしない。
 */
add_action( 'init', 'kashiwazaki_poll_maybe_schedule_regen' );
function kashiwazaki_poll_maybe_schedule_regen() {
    if ( '1' === get_option( 'kashiwazaki_poll_datasets_regenerated_v106' ) ) {
        return;
    }
    if ( ! wp_next_scheduled( 'kashiwazaki_poll_regen_event' ) ) {
        wp_schedule_single_event( time() + 30, 'kashiwazaki_poll_regen_event' );
    }
}

/**
 * 管理画面アクセス時の即時実行（WP-Cron のバックアップ経路）。
 *
 * DISABLE_WP_CRON かつ外部 cron 未設定の環境では単発イベントが発火しないため、管理者が
 * プラグイン更新後に管理画面を開いた時点で即時に復旧を試みる。公開 AJAX（admin-ajax.php）
 * や cron、非管理者リクエストでは走らせない（重処理の公開起動と多重実行を防ぐ）。
 */
add_action( 'admin_init', 'kashiwazaki_poll_maybe_run_regen_on_admin' );
function kashiwazaki_poll_maybe_run_regen_on_admin() {
    if ( '1' === get_option( 'kashiwazaki_poll_datasets_regenerated_v106' ) ) {
        return;
    }
    if ( wp_doing_ajax() || wp_doing_cron() ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    kashiwazaki_poll_run_data_file_regen();
}

add_action( 'kashiwazaki_poll_regen_event', 'kashiwazaki_poll_run_data_file_regen' );
function kashiwazaki_poll_run_data_file_regen() {
    if ( '1' === get_option( 'kashiwazaki_poll_datasets_regenerated_v106' ) ) {
        return;
    }
    if ( ! function_exists( 'kashiwazaki_poll_generate_all_data_files' )
        || ! function_exists( 'kashiwazaki_poll_get_dataset_file_path' ) ) {
        return; // 依存関数が未ロードなら次の cron に委ねる。
    }

    // スロットル: 前回の試行から 300 秒以内は再実行しない。admin_init 即時実行は管理者が
    // 管理画面を連続遷移するだけで走り得るため、これが無いと一過性の書込エラー（権限・容量）
    // が解消する前に attempts 上限（5 回）を一気に消費し、恒久打ち切りに至ってしまう。cron の
    // 再試行間隔（300 秒）と揃え、どちらの経路でも試行が時間分散されるようにする。
    $last_attempt = (int) get_option( 'kashiwazaki_poll_datasets_regen_last_v106', 0 );
    if ( $last_attempt && ( time() - $last_attempt ) < 300 ) {
        return;
    }

    // admin_init 即時実行と cron が同時に走っても二重処理しないよう短時間ロックを取る。
    if ( get_transient( 'kashiwazaki_poll_regen_lock' ) ) {
        return;
    }
    set_transient( 'kashiwazaki_poll_regen_lock', 1, 5 * MINUTE_IN_SECONDS );
    update_option( 'kashiwazaki_poll_datasets_regen_last_v106', time() );

    $types = array( 'csv', 'xml', 'yaml', 'json', 'svg' );

    // uploads 配下のディレクトリを用意（activation を挟まない更新に備える）。
    if ( function_exists( 'kashiwazaki_poll_datasets_base_dir' ) ) {
        $base = kashiwazaki_poll_datasets_base_dir();
        foreach ( $types as $type ) {
            $dir = $base . $type;
            if ( ! file_exists( $dir ) ) {
                wp_mkdir_p( $dir );
            }
        }
    }

    // 公開 poll のみ対象（draft/private を公開URL配下に生成しない）。
    $poll_ids = get_posts( array(
        'post_type'   => 'poll',
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields'      => 'ids',
    ) );

    // 全公開 poll を再生成し、実ファイル（全 5 形式）の存在で「完全生成できた件数」を数える。
    // generate_all_data_files は個別書込失敗でも true を返すため、戻り値は信用しない。
    // ループ中は $skip_sitemap=true でサイトマップ再生成を抑止し（O(N^2) 回避）、
    // ループ完了後にまとめて 1 回だけ再生成する。
    $fully_generated = 0;
    foreach ( $poll_ids as $poll_id ) {
        $counts = get_post_meta( $poll_id, '_kashiwazaki_poll_counts', true );
        kashiwazaki_poll_generate_all_data_files( $poll_id, is_array( $counts ) ? $counts : array(), true );

        $all_present = true;
        foreach ( $types as $type ) {
            $path = kashiwazaki_poll_get_dataset_file_path( $poll_id, $type );
            if ( ! $path || ! file_exists( $path ) ) {
                $all_present = false;
                break;
            }
        }
        if ( $all_present ) {
            $fully_generated++;
        }
    }

    // サイトマップはループ完了後に一度だけ再生成する。
    if ( function_exists( 'kashiwazaki_poll_generate_sitemap_poll' ) ) {
        kashiwazaki_poll_generate_sitemap_poll();
    }

    // 対象 0 件、または全対象 poll の全 5 形式が揃った場合のみ「完了」とする。
    if ( empty( $poll_ids ) || $fully_generated === count( $poll_ids ) ) {
        update_option( 'kashiwazaki_poll_datasets_regenerated_v106', '1' );
        delete_option( 'kashiwazaki_poll_datasets_regen_attempts_v106' );
        delete_option( 'kashiwazaki_poll_datasets_regen_last_v106' );
        delete_transient( 'kashiwazaki_poll_regen_lock' );
        return;
    }

    // 部分失敗（書込不可・容量不足など）。無限再試行を避けるため上限つきで再スケジュールする。
    // 上限到達後は完了扱いにして打ち切る（残りは投票時の自動生成／管理画面の一括生成で復旧可能）。
    $attempts = (int) get_option( 'kashiwazaki_poll_datasets_regen_attempts_v106', 0 ) + 1;
    if ( $attempts >= 5 ) {
        update_option( 'kashiwazaki_poll_datasets_regenerated_v106', '1' );
        delete_option( 'kashiwazaki_poll_datasets_regen_attempts_v106' );
        delete_option( 'kashiwazaki_poll_datasets_regen_last_v106' );
        error_log( sprintf(
            '[Kashiwazaki SEO Poll] データファイルの自動再生成を %d 回試行しましたが未完了のまま打ち切りました（%d/%d 件完成）。uploads ディレクトリの書き込み権限・空き容量を確認のうえ、設定画面の「データセット一括生成」を実行してください。',
            $attempts,
            $fully_generated,
            count( $poll_ids )
        ) );
    } else {
        update_option( 'kashiwazaki_poll_datasets_regen_attempts_v106', $attempts );
        if ( ! wp_next_scheduled( 'kashiwazaki_poll_regen_event' ) ) {
            wp_schedule_single_event( time() + 300, 'kashiwazaki_poll_regen_event' );
        }
    }

    delete_transient( 'kashiwazaki_poll_regen_lock' );
}

function kashiwazaki_poll_deactivate() {
    flush_rewrite_rules();
    // 予約済みの復旧イベントが残らないよう掃除する。
    wp_clear_scheduled_hook( 'kashiwazaki_poll_regen_event' );
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
