<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 投票者のクライアントIPを取得する。
 *
 * 既定では $_SERVER['REMOTE_ADDR'] のみを使用する（クライアントから偽装できないため、
 * 重複投票防止の基準として安全）。リバースプロキシ／CDN（Cloudflare 等）の配下では
 * REMOTE_ADDR が全訪問者で同一のプロキシIPになり最初の1人以外が投票できなくなるため、
 * 信頼できる構成に限り wp-config.php 等で
 *   define( 'KASHIWAZAKI_POLL_TRUST_PROXY', true );
 * を定義すると CF-Connecting-IP / X-Forwarded-For を優先する（opt-in。既定では無効で
 * ヘッダ偽装による重複投票回避を防ぐ）。
 *
 * @return string sanitize 済みIP文字列（取得不能時は空文字）。
 */
function kashiwazaki_poll_get_client_ip() {
    $ip = '';
    if ( defined( 'KASHIWAZAKI_POLL_TRUST_PROXY' ) && KASHIWAZAKI_POLL_TRUST_PROXY ) {
        if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $parts = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
            $ip = trim( $parts[0] ); // 最左 = 元クライアント。
        }
    }
    if ( '' === $ip && isset( $_SERVER['REMOTE_ADDR'] ) ) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // IPv4/IPv6 で使う文字のみ許容。
    return preg_replace( '/[^0-9A-Fa-f:.]/', '', (string) $ip );
}

function kashiwazaki_poll_is_already_voted( $poll_id, $ip, $cookie_key ) {
    // 全体リセット時刻と当該pollの個別リセット時刻の新しい方を基準にする。
    // これにより per-poll リセット後は古い投票Cookie/IPが無効化され再投票できる。
    $global_reset_ts = (int) get_option( 'kashiwazaki_poll_reset_timestamp', 0 );
    $poll_reset_ts   = (int) get_post_meta( $poll_id, '_kashiwazaki_poll_reset_ts', true );
    $reset_ts = max( $global_reset_ts, $poll_reset_ts );
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
    // Cookie による重複投票判定（IP変化時の補助）。Cookie値は投票時刻(Unix秒)を保持し、
    // リセット時刻より後の投票のみ有効とみなす（リセット後は再投票を許可）。
    // 妥当なUnix時刻(>= 2001年)のみ有効とし、旧形式の値 '1' 等は無視する。
    // v2 Cookie（time() = 真UTC基準）。
    if ( $cookie_key && isset( $_COOKIE[ $cookie_key ] ) ) {
        $cookie_time = (int) $_COOKIE[ $cookie_key ];
        if ( $cookie_time >= 1000000000 && $cookie_time >= $reset_ts ) {
            return true;
        }
    }
    // 旧 v1 Cookie（current_time('timestamp') = UTC + GMTオフセット基準）を UTC へ正規化して
    // 後方互換で判定する。これにより旧Cookie保持者の重複投票防止を維持しつつ、
    // 負のGMTオフセット環境での基準ズレも回避する（v2 移行前に投票した利用者向け）。
    $legacy_cookie_key = 'kashiwazaki_poll_voted_' . (int) $poll_id;
    if ( isset( $_COOKIE[ $legacy_cookie_key ] ) ) {
        $legacy_time = (int) $_COOKIE[ $legacy_cookie_key ];
        if ( $legacy_time >= 1000000000 ) {
            $offset = (int) round( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS );
            if ( ( $legacy_time - $offset ) >= $reset_ts ) {
                return true;
            }
        }
    }
    return false;
}

/**
 * アンケートの調査期間（最初の投票日と最後の投票日）を取得
 *
 * @param int $poll_id アンケートID
 * @return array|null 調査期間の配列 ['start' => timestamp, 'end' => timestamp] または投票がない場合はnull
 */
function kashiwazaki_poll_get_survey_period( $poll_id ) {
    $voted_ips = get_post_meta( $poll_id, '_kashiwazaki_poll_voted_ips', true );
    if ( ! is_array( $voted_ips ) || empty( $voted_ips ) ) {
        return null;
    }
    return array(
        'start' => min( $voted_ips ),
        'end'   => max( $voted_ips ),
    );
}

/**
 * データセットファイルの保存ベースディレクトリ（wp_upload_dir 配下）。
 *
 * 以前はプラグインディレクトリ内(KASHIWAZAKI_POLL_DIR.'datasets/')に生成していたが、
 * WordPress のプラグイン更新時にプラグインフォルダが削除→再配置されるため全データが
 * 消失していた。uploads 配下に置くことで更新の影響を受けないようにする。
 *
 * @return string 末尾スラッシュ付きの絶対パス。
 */
function kashiwazaki_poll_datasets_base_dir() {
    $upload = wp_upload_dir();
    return trailingslashit( $upload['basedir'] ) . 'kashiwazaki-poll-datasets/';
}

/**
 * データセットファイルの公開ベースURL（wp_upload_dir 配下）。
 *
 * @return string 末尾スラッシュ付きのURL。
 */
function kashiwazaki_poll_datasets_base_url() {
    $upload = wp_upload_dir();
    return trailingslashit( $upload['baseurl'] ) . 'kashiwazaki-poll-datasets/';
}

function kashiwazaki_poll_get_dataset_file_path( $poll_id, $file_type ) {
    $allowed_types = ['csv', 'xml', 'yaml', 'json', 'svg'];
    if ( ! in_array( $file_type, $allowed_types ) ) {
        return false;
    }
    return kashiwazaki_poll_datasets_base_dir() . "{$file_type}/{$poll_id}.{$file_type}";
}

function kashiwazaki_poll_get_dataset_file_url( $poll_id, $file_type ) {
    $allowed_types = ['csv', 'xml', 'yaml', 'json', 'svg'];
    if ( ! in_array( $file_type, $allowed_types ) ) {
        return false;
    }
    return kashiwazaki_poll_datasets_base_url() . "{$file_type}/{$poll_id}.{$file_type}";
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

/**
 * 選択肢が編集された時の得票数(counts)を、選択肢テキストで付け替えて計算する（保存はしない）。
 *
 * counts は配列index基準のため、選択肢の追加/削除/並べ替えをそのまま保存すると
 * 過去票が別ラベルに付け替わったり破損する。本関数は旧選択肢テキスト→得票数の
 * 対応を作り、新選択肢の順で再構築する。一致する選択肢の票は維持され、削除された
 * 選択肢の票は破棄、新規選択肢は0で開始する（テキスト変更=改名は別選択肢扱い）。
 *
 * @param mixed $old_options 保存済みの選択肢
 * @param mixed $old_counts  保存済みの得票数
 * @param array $new_options 新しい選択肢配列（サニタイズ済み）
 * @return int[]|null 付け替え後の得票数。付け替え不要（旧データ・票が無い、選択肢が同じ）なら null
 */
function kashiwazaki_poll_remap_counts( $old_options, $old_counts, $new_options ) {
    if ( ! is_array( $new_options ) || ! is_array( $old_options ) || ! is_array( $old_counts ) || empty( $old_counts ) ) {
        return null;
    }
    if ( $old_options === $new_options ) {
        return null;
    }
    // 旧: 選択肢テキスト => 得票数（同一テキストは合算）。保存形式の違い（4バイト文字の文字参照）で別物と扱わないよう、元の文字に戻した値で照らす。
    $by_text = array();
    foreach ( $old_options as $i => $txt ) {
        $key = kashiwazaki_poll_decode_stored_text( $txt );
        $c   = isset( $old_counts[ $i ] ) ? max( 0, (int) $old_counts[ $i ] ) : 0;
        $by_text[ $key ] = ( isset( $by_text[ $key ] ) ? $by_text[ $key ] : 0 ) + $c;
    }
    // 新選択肢の順で再構築（マッチしたテキストは一度だけ消費）。
    $new_counts = array();
    foreach ( $new_options as $txt ) {
        $key = kashiwazaki_poll_decode_stored_text( $txt );
        if ( array_key_exists( $key, $by_text ) ) {
            $new_counts[] = $by_text[ $key ];
            unset( $by_text[ $key ] );
        } else {
            $new_counts[] = 0;
        }
    }
    return $new_counts;
}

/**
 * 票に関わる値（選択肢・票数・投票済みの記録など）を書き換える唯一の入口。
 *
 * 投票・選択肢の付け替え・投票数の編集・集計データの全削除は、すべてこの関数を通す。
 * 1. データセット単位の排他を取る（取れなければ書き換えずに error=busy）。
 * 2. 排他の中で最新の値を読み直し、$mutator に渡す。$mutator は書き換える値を計算するだけで、自分では保存しない。
 *    戻り値: array( 'set' => array( メタキー => 新しい値（null は削除） ), 'result' => 任意 )
 *            または array( 'error' => コード, 'result' => 任意 )（何も書き換えない）。
 * 3. 書き換えをトランザクションにまとめ、保存後に読み直して全部が期待どおりかを確かめる。
 * 4. 1 つでも違えば ROLLBACK し、トランザクション非対応のテーブルに備えて以前の値に戻ったかを読み直し、
 *    戻っていなければ書き戻す。それでも戻せなければ管理者に通知を出す（error=failed）。
 *
 * @param int      $poll_id
 * @param callable $mutator function( array $state ): array。$state はメタキー => 保存済みの値（無ければ ''）
 * @return array{ok:bool,error:string,changed:bool,keys:string[],result:mixed}
 */
function kashiwazaki_poll_mutate_vote_state( $poll_id, $mutator ) {
    global $wpdb;
    // 排他（GET_LOCK）とトランザクションはデータベースの接続ごとのもの。wpdb は接続が切れると黙って再接続し、
    // 同じ SQL をやり直すため、排他を持たない新しい接続で書き込んでしまう。排他を持つ間は再接続させず
    // （接続が切れたらその場で処理を止め、データベース側で未確定の書き込みが取り消される）、終わったら元に戻す。
    // 長い処理で接続が古くなっている場合に備え、排他を取る前に一度だけ接続を確かめておく。
    $wpdb->check_connection( false );
    $retries = $wpdb->reconnect_retries;
    $wpdb->reconnect_retries = 0;
    try {
        return kashiwazaki_poll_mutate_vote_state_locked( (int) $poll_id, $mutator );
    } finally {
        $wpdb->reconnect_retries = $retries;
    }
}

/**
 * この接続がデータセットの排他を持っているか（IS_USED_LOCK は排他を持つ接続の ID を返す）。
 * 1 つの SQL で調べるため、接続が入れ替わっていれば必ず false になる。
 */
function kashiwazaki_poll_holds_lock( $poll_id ) {
    global $wpdb;
    return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT IS_USED_LOCK(%s) = CONNECTION_ID()', kashiwazaki_poll_lock_name( $poll_id ) ) );
}

/**
 * kashiwazaki_poll_mutate_vote_state の本体（再接続を止めた状態で呼ぶ）。
 */
function kashiwazaki_poll_mutate_vote_state_locked( $poll_id, $mutator ) {
    global $wpdb;
    $out = array( 'ok' => false, 'error' => '', 'changed' => false, 'keys' => array(), 'result' => null );
    $lock_state = kashiwazaki_poll_acquire_lock_state( $poll_id );
    if ( 'busy' === $lock_state ) {
        $out['error'] = 'busy';
        return $out;
    }
    // 排他を持っているかの確認。GET_LOCK が使えないデータベース（NULL を返す）では、以前どおり排他なしで続ける。
    $owns_lock = function () use ( $poll_id, $lock_state ) {
        return 'unavailable' === $lock_state || kashiwazaki_poll_holds_lock( $poll_id );
    };
    // 排他を本当にこの接続で持っているかを確かめてから読む（持っていなければ何も書かない）。
    if ( ! $owns_lock() ) {
        kashiwazaki_poll_release_lock( $poll_id );
        $out['error'] = 'failed';
        return $out;
    }
    // 最新の値はデータベースから直接読み、読み取りのエラーを確かめる（エラーを「値が無い」と取り違えると、
    // 付け替えを飛ばしたり、全削除で古い票を残したまま成功と表示したりするため）。読めなければ何も書かない。
    wp_cache_delete( $poll_id, 'post_meta' );
    $state = kashiwazaki_poll_read_meta_strict( $poll_id, array( '_kashiwazaki_poll_options', '_kashiwazaki_poll_counts', '_kashiwazaki_poll_voted_ips', '_kashiwazaki_poll_reset_ts', '_kashiwazaki_poll_locked' ) );
    if ( null === $state ) {
        kashiwazaki_poll_release_lock( $poll_id );
        $out['error'] = 'failed';
        return $out;
    }
    $plan = call_user_func( $mutator, $state );
    $out['result'] = ( is_array( $plan ) && array_key_exists( 'result', $plan ) ) ? $plan['result'] : null;
    if ( ! is_array( $plan ) || ! empty( $plan['error'] ) ) {
        kashiwazaki_poll_release_lock( $poll_id );
        $out['error'] = is_array( $plan ) && ! empty( $plan['error'] ) ? (string) $plan['error'] : 'invalid';
        return $out;
    }

    // 書き換える値と以前の値を揃える（保存済みと同じ値は書かない）。
    $set   = isset( $plan['set'] ) && is_array( $plan['set'] ) ? $plan['set'] : array();
    $extra = kashiwazaki_poll_read_meta_strict( $poll_id, array_diff( array_keys( $set ), array_keys( $state ) ) );
    if ( null === $extra ) {
        kashiwazaki_poll_release_lock( $poll_id );
        $out['error'] = 'failed';
        return $out;
    }
    $previous = array();
    foreach ( $set as $key => $value ) {
        $previous[ $key ] = array_key_exists( $key, $state ) ? $state[ $key ] : $extra[ $key ];
        if ( kashiwazaki_poll_normalize_meta_for_compare( $value ) === kashiwazaki_poll_normalize_meta_for_compare( $previous[ $key ] ) ) {
            unset( $set[ $key ], $previous[ $key ] );
        }
    }
    $out['keys'] = array_keys( $set );
    if ( empty( $set ) ) {
        kashiwazaki_poll_release_lock( $poll_id );
        $out['ok'] = true;
        return $out;
    }

    // 書き始める前に「確認待ち」の印（管理画面に通知が出る記録）をトランザクションの外で残す。保存の途中で接続が切れて
    // 処理が止まっても印が残るので、トランザクション非対応のテーブルで一部だけ保存された状態を管理者が見つけられる。
    // 成功したとき、または以前の値に戻せたときだけ消す（以前から残っていた印は消さない）。
    // 印の値は書くたびに新しくする（編集画面で管理者が確認した印と、その後に別の処理が残した印を区別するため）。
    // 終わったら以前の状態（無ければ削除、あれば以前の値）に戻す。
    $repair_key  = 'kashiwazaki_poll_vote_repair_' . $poll_id;
    $prev_repair = kashiwazaki_poll_get_repair_marker( $poll_id );
    // 読んだ値から計算している間に排他を失っていないかを、書き始める直前にもう一度確かめる。
    if ( ! $owns_lock() || ! update_option( $repair_key, kashiwazaki_poll_new_repair_marker(), false ) ) {
        kashiwazaki_poll_release_lock( $poll_id );
        $out['error'] = 'failed';
        return $out;
    }
    $settle_repair = function () use ( $repair_key, $prev_repair ) {
        if ( false === $prev_repair ) {
            delete_option( $repair_key );
        } else {
            update_option( $repair_key, $prev_repair, false );
        }
    };
    if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
        $settle_repair();
        kashiwazaki_poll_release_lock( $poll_id );
        $out['error'] = 'failed';
        return $out;
    }
    foreach ( $set as $key => $value ) {
        if ( null === $value ) {
            delete_post_meta( $poll_id, $key );
        } else {
            // update_post_meta は値を wp_unslash するため、バックスラッシュを含む文字列が削られないよう wp_slash してから渡す。
            update_post_meta( $poll_id, $key, wp_slash( $value ) );
        }
    }
    // 確定は、確定前の読み直しが一致し、まだ排他を持っているときだけ行う。成功は「COMMIT の成功」
    // 「確定後も排他を持っている」「確定後に読み直した値が期待どおり」の 3 つがそろったときだけ。
    $committed = kashiwazaki_poll_meta_matches( $poll_id, $set ) && $owns_lock()
        && ( false !== $wpdb->query( 'COMMIT' ) );
    if ( $committed && $owns_lock() && kashiwazaki_poll_meta_matches( $poll_id, $set ) ) {
        $settle_repair();
        kashiwazaki_poll_release_lock( $poll_id );
        $out['ok']      = true;
        $out['changed'] = true;
        return $out;
    }

    // 書き戻しは、その直前に排他を持っていることを確かめられたときだけ行う（排他が無いまま書き戻すと、
    // その間に確定した他の投票を消してしまうため）。確かめられなければ書き戻さずに管理者へ知らせる。
    $restored = false;
    if ( $owns_lock() ) {
        if ( ! $committed ) {
            $wpdb->query( 'ROLLBACK' );
        }
        // トランザクション非対応のテーブルでは ROLLBACK が効かないため、以前の値に戻ったかを読み直し、戻っていなければ書き戻す。
        $restored = true;
        foreach ( $previous as $key => $value ) {
            $restored = $owns_lock() && kashiwazaki_poll_restore_post_meta( $poll_id, $key, $value ) && $restored;
        }
    }
    // 元に戻せたときだけ印を消す。戻せなかったときは印を残し、管理者に知らせる（編集画面で投票数を確認してもらう）。
    if ( $restored ) {
        $settle_repair();
    }
    error_log( "[Poll {$poll_id}] Could not save vote data (" . implode( ',', array_keys( $set ) ) . ')' . ( $committed ? '; commit ok' : '' ) . ( $restored ? '; restored.' : '; not restored.' ) );
    kashiwazaki_poll_release_lock( $poll_id );
    $out['error'] = 'failed';
    return $out;
}

/**
 * 「確認待ち」の印（管理画面の通知に使う記録）の値を、キャッシュを使わずに読む。無ければ false。
 */
function kashiwazaki_poll_get_repair_marker( $poll_id ) {
    $key = 'kashiwazaki_poll_vote_repair_' . (int) $poll_id;
    wp_cache_delete( $key, 'options' );
    wp_cache_delete( 'notoptions', 'options' );
    return get_option( $key );
}

/**
 * 印の新しい値（作った時刻 | 毎回違う文字列）。
 */
function kashiwazaki_poll_new_repair_marker() {
    return time() . '|' . wp_generate_password( 12, false, false );
}

/**
 * 管理者が編集画面で確認した印だけを、データセットの排他の中で消す（その後に別の処理が残した印は消さない）。
 *
 * @param int    $poll_id
 * @param string $seen 編集画面を表示したときの印の値
 */
function kashiwazaki_poll_clear_repair_marker( $poll_id, $seen ) {
    $seen = (string) $seen;
    if ( '' === $seen ) {
        return;
    }
    $lock_state = kashiwazaki_poll_acquire_lock_state( $poll_id );
    if ( 'busy' === $lock_state ) {
        return;
    }
    if ( kashiwazaki_poll_get_repair_marker( $poll_id ) === $seen ) {
        delete_option( 'kashiwazaki_poll_vote_repair_' . (int) $poll_id );
    }
    if ( 'locked' === $lock_state ) {
        kashiwazaki_poll_release_lock( $poll_id );
    }
}

/**
 * 保存されている値が、期待する値（メタキー => 値、null は削除）とすべて一致するかを読み直して確かめる。
 */
function kashiwazaki_poll_meta_matches( $poll_id, $expected ) {
    wp_cache_delete( (int) $poll_id, 'post_meta' );
    $current = kashiwazaki_poll_read_meta_strict( $poll_id, array_keys( $expected ) );
    if ( null === $current ) {
        return false;
    }
    foreach ( $expected as $key => $value ) {
        if ( kashiwazaki_poll_normalize_meta_for_compare( $current[ $key ] ) !== kashiwazaki_poll_normalize_meta_for_compare( $value ) ) {
            return false;
        }
    }
    return true;
}

/**
 * メタをデータベースから直接読む（キャッシュやフィルタを通さない）。読み取りでエラーが起きたら null を返す
 * （get_post_meta はエラーでも空の値を返すため、「値が無い」と区別できない）。無いキーは ''（get_post_meta と同じ）。
 *
 * @param int      $poll_id
 * @param string[] $keys
 * @return array|null メタキー => 値（同じキーが複数あれば最初の 1 つ）
 */
function kashiwazaki_poll_read_meta_strict( $poll_id, $keys ) {
    global $wpdb;
    $keys = array_values( array_unique( array_map( 'strval', (array) $keys ) ) );
    if ( empty( $keys ) ) {
        return array();
    }
    $placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key IN ($placeholders) ORDER BY meta_id ASC",
        array_merge( array( (int) $poll_id ), $keys )
    ) );
    if ( '' !== $wpdb->last_error || ! is_array( $rows ) ) {
        return null;
    }
    $values = array_fill_keys( $keys, '' );
    $found  = array();
    foreach ( $rows as $row ) {
        if ( isset( $found[ $row->meta_key ] ) ) {
            continue;
        }
        $found[ $row->meta_key ]  = true;
        $values[ $row->meta_key ] = maybe_unserialize( $row->meta_value );
    }
    return $values;
}

/**
 * 保存前の値と読み直した値を比べられる形にする（スカラーは文字列で保存されるため文字列に、削除は '' に揃える）。
 */
function kashiwazaki_poll_normalize_meta_for_compare( $value ) {
    if ( null === $value || false === $value ) {
        return '';
    }
    if ( is_scalar( $value ) ) {
        return (string) $value;
    }
    return $value;
}

/**
 * 集計結果を静的HTMLテーブルとして出力する（中央詳細ページ用）。
 *
 * JSON-LD / Chart.js とは別に、JS非実行のクローラーやスクリーンリーダーにも
 * 数値が届くようサーバーサイドで静的レンダリングする。数値ソースは
 * file-generators と同一（_kashiwazaki_poll_counts）、割合の丸めも同一規則
 * （round(count/total*100, 2)）で出力し、CSV/JSON 等とずれないようにする。
 *
 * @param int $poll_id 投票投稿ID
 * @return string テーブルHTML（選択肢が無ければ空文字）
 */
function kashiwazaki_poll_render_results_table( $poll_id ) {
    $poll_id = intval( $poll_id );
    $options = kashiwazaki_poll_get_display_options( $poll_id );
    if ( ! is_array( $options ) || empty( $options ) ) {
        return '';
    }
    // counts を options 数に正規化（file-generators.php と同一規則）。
    // これにより総投票数・割合が CSV/JSON 等の正規データファイルと完全一致する。
    $option_count = count( $options );
    $counts = get_post_meta( $poll_id, '_kashiwazaki_poll_counts', true );
    if ( ! is_array( $counts ) ) {
        $counts = array_fill( 0, $option_count, 0 );
    } elseif ( count( $counts ) < $option_count ) {
        $counts = array_pad( $counts, $option_count, 0 );
    } elseif ( count( $counts ) > $option_count ) {
        $counts = array_slice( $counts, 0, $option_count );
    }
    $total_votes = 0;
    foreach ( $counts as $c ) {
        $total_votes += intval( $c );
    }
    $question = get_the_title( $poll_id );

    // 集計時点（最終更新）＝ 最新投票時刻と管理画面での投票数編集時刻の新しい方
    $last_vote_time = kashiwazaki_poll_get_last_updated_time( $poll_id );

    ob_start();
    ?>
    <div class="kashiwazaki-poll-results-table-wrap">
        <table class="kashiwazaki-poll-results-table">
            <caption><?php echo esc_html( $question ); ?> ― 集計結果</caption>
            <thead>
                <tr>
                    <th scope="col" class="col-option">選択肢</th>
                    <th scope="col" class="col-count">得票数</th>
                    <th scope="col" class="col-percent">割合</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $options as $i => $opt ) :
                $count = isset( $counts[ $i ] ) ? intval( $counts[ $i ] ) : 0;
                ?>
                <tr>
                    <th scope="row" class="col-option"><?php echo esc_html( $opt ); ?></th>
                    <td class="col-count"><?php echo esc_html( $count ); ?>票</td>
                    <td class="col-percent"><?php
                        if ( $total_votes > 0 ) {
                            echo esc_html( round( ( $count / $total_votes ) * 100, 2 ) ) . '%';
                        } else {
                            echo '—';
                        }
                    ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th scope="row" class="col-option">総投票数</th>
                    <td class="col-count" colspan="2"><?php echo esc_html( $total_votes ); ?>票</td>
                </tr>
            </tfoot>
        </table>
        <?php if ( $total_votes > 0 && $last_vote_time > 0 ) : ?>
        <p class="kashiwazaki-poll-results-asof">集計時点: <?php echo esc_html( wp_date( 'Y/m/d H:i:s', $last_vote_time ) ); ?></p>
        <?php elseif ( $total_votes === 0 ) : ?>
        <p class="kashiwazaki-poll-results-asof no-votes">まだ投票がありません。</p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * 投票の受付を締め切っている（ロック中）かどうか。
 *
 * ロック中は新しい投票を受け付けない（public/ajax.php で拒否し、
 * ショートコードは投票フォームの代わりに結果と締切表示を出す）。
 *
 * @param int $poll_id 投票投稿ID
 * @return bool
 */
function kashiwazaki_poll_is_locked( $poll_id ) {
    // データベースから直接読み、読めなかったときは締め切っているものとして扱う（読み取りのエラーで、
    // 締め切った投票のフォームを出したり投票を受け付けたりしないように）。
    $meta = kashiwazaki_poll_read_meta_strict( (int) $poll_id, array( '_kashiwazaki_poll_locked' ) );
    return null === $meta || '1' === (string) $meta['_kashiwazaki_poll_locked'];
}

/**
 * 構造化データに出すメールアドレス（作成者の組織・発行者）。
 * 基本設定で「構造化データに組織のメールアドレスを含める」を ON にし、メールアドレスを入力しているときだけ返す。
 * それ以外は出さない（サイトの管理者メールアドレスなどを本人の選択なしに公開しないため）。
 *
 * @return string 空文字のときは出力しない
 */
function kashiwazaki_poll_get_publisher_email() {
    $settings = get_option( 'kashiwazaki_poll_settings', array() );
    if ( is_array( $settings ) && ! empty( $settings['structured_data_email'] ) && ! empty( $settings['creator_organization_email'] ) ) {
        return (string) $settings['creator_organization_email'];
    }
    return '';
}

/**
 * 構造化データの発行者（サイトの組織）。メールアドレスは基本設定にあるときだけ入れる。
 */
function kashiwazaki_poll_ld_publisher() {
    $publisher = array(
        '@type' => 'Organization',
        'name'  => get_bloginfo( 'name' ),
        'url'   => home_url(),
    );
    $email = kashiwazaki_poll_get_publisher_email();
    if ( '' !== $email ) {
        $publisher['email'] = $email;
    }
    return $publisher;
}

/**
 * 構造化データの作成者（組織）。問い合わせ先のメールアドレスは基本設定にあるときだけ入れる。
 */
function kashiwazaki_poll_ld_organization_creator( $name, $url ) {
    $creator = array(
        '@type' => 'Organization',
        'name'  => $name,
        'url'   => $url,
    );
    $email = kashiwazaki_poll_get_publisher_email();
    if ( '' !== $email ) {
        $creator['contactPoint'] = array(
            '@type'       => 'ContactPoint',
            'contactType' => 'customer service',
            'email'       => $email,
        );
    }
    return $creator;
}

/**
 * 保存用に、データベースが保存できない4バイト文字（U+10000 以降。絵文字・「𠮷」など）を数値文字参照に変える。
 * postmeta が utf8mb4 のときは変えない。
 */
function kashiwazaki_poll_encode_text_for_storage( $text ) {
    global $wpdb;
    $text = (string) $text;
    if ( 'utf8mb4' === $wpdb->get_col_charset( $wpdb->postmeta, 'meta_value' ) ) {
        return $text;
    }
    $encoded = preg_replace_callback( '/[\x{10000}-\x{10FFFF}]/u', function ( $m ) {
        // 4バイトの UTF-8 からコードポイントを求める（mbstring に頼らない）。
        $b  = array_values( unpack( 'C*', $m[0] ) );
        $cp = ( ( $b[0] & 0x07 ) << 18 ) | ( ( $b[1] & 0x3F ) << 12 ) | ( ( $b[2] & 0x3F ) << 6 ) | ( $b[3] & 0x3F );
        return sprintf( '&#x%x;', $cp );
    }, $text );
    return null === $encoded ? $text : $encoded;
}

/**
 * 表示・出力用に、4バイト文字の数値文字参照（&#x1F600; など）だけを元の文字に戻す（それ以外の文字参照はそのまま）。
 */
function kashiwazaki_poll_decode_stored_text( $text ) {
    $decoded = preg_replace_callback( '/&#x([0-9a-fA-F]{5,6});|&#(\d{5,7});/', function ( $m ) {
        $cp = ( '' !== $m[1] ) ? hexdec( $m[1] ) : (int) $m[2];
        if ( $cp < 0x10000 || $cp > 0x10FFFF ) {
            return $m[0];
        }
        // コードポイントを4バイトの UTF-8 に戻す（mbstring に頼らない）。
        return chr( 0xF0 | ( $cp >> 18 ) ) . chr( 0x80 | ( ( $cp >> 12 ) & 0x3F ) ) . chr( 0x80 | ( ( $cp >> 6 ) & 0x3F ) ) . chr( 0x80 | ( $cp & 0x3F ) );
    }, (string) $text );
    return null === $decoded ? (string) $text : $decoded;
}

/**
 * 表示・出力用の選択肢（4バイト文字を元に戻したもの）。投票の照合に使う署名は、保存されたままの値で作ること。
 *
 * @return string[]
 */
function kashiwazaki_poll_get_display_options( $poll_id ) {
    $options = get_post_meta( (int) $poll_id, '_kashiwazaki_poll_options', true );
    return is_array( $options ) ? array_map( 'kashiwazaki_poll_decode_stored_text', $options ) : array();
}

/**
 * メタを以前の値に戻し、戻ったことを読み直して確かめる（以前の値が無ければ削除する）。
 *
 * @return bool 以前の値になっているとき true
 */
function kashiwazaki_poll_restore_post_meta( $post_id, $key, $previous ) {
    wp_cache_delete( (int) $post_id, 'post_meta' );
    $has_previous = ( '' !== $previous && null !== $previous && false !== $previous );
    // 読み取りのエラーを「戻っている」と取り違えないよう、直接読んで確かめる。
    $current = kashiwazaki_poll_read_meta_strict( $post_id, array( $key ) );
    if ( null !== $current && ( $has_previous ? ( $current[ $key ] === $previous ) : ( '' === $current[ $key ] ) ) ) {
        return true;
    }
    if ( $has_previous ) {
        update_post_meta( $post_id, $key, wp_slash( $previous ) );
    } else {
        delete_post_meta( $post_id, $key );
    }
    wp_cache_delete( (int) $post_id, 'post_meta' );
    $current = kashiwazaki_poll_read_meta_strict( $post_id, array( $key ) );
    return null !== $current && ( $has_previous ? ( $current[ $key ] === $previous ) : ( '' === $current[ $key ] ) );
}

/**
 * 選択肢の保存状態を表す署名。表示時と保存時（投票時）で比べ、選択肢が変わっていたら
 * 投票数の編集や投票を受け付けない（別の選択肢に票が付くのを防ぐ）。
 */
function kashiwazaki_poll_options_signature( $options ) {
    return md5( (string) wp_json_encode( $options ) );
}

/**
 * 集計データの最終更新時刻（Unix秒、UTC）。
 *
 * 最新の投票時刻と、管理画面で投票数を編集した時刻のうち新しい方を返す。
 * どちらも無ければ 0。
 *
 * @param int $poll_id 投票投稿ID
 * @return int
 */
function kashiwazaki_poll_get_last_updated_time( $poll_id ) {
    $voted_ips = get_post_meta( (int) $poll_id, '_kashiwazaki_poll_voted_ips', true );
    $last_vote = ( is_array( $voted_ips ) && ! empty( $voted_ips ) ) ? (int) max( $voted_ips ) : 0;
    $edited_ts = (int) get_post_meta( (int) $poll_id, '_kashiwazaki_poll_counts_edited_ts', true );
    return max( $last_vote, $edited_ts );
}

/**
 * 得票数配列を選択肢の数に揃える（不足は0で補い、余分は切り捨てる）。
 *
 * @param mixed $counts       保存済みの得票数（配列以外は空扱い）
 * @param int   $option_count 選択肢の数
 * @return int[]
 */
function kashiwazaki_poll_normalize_counts( $counts, $option_count ) {
    $option_count = max( 0, (int) $option_count );
    $counts = is_array( $counts ) ? array_values( $counts ) : array();
    $normalized = array();
    for ( $i = 0; $i < $option_count; $i++ ) {
        $normalized[] = isset( $counts[ $i ] ) ? max( 0, (int) $counts[ $i ] ) : 0;
    }
    return $normalized;
}
