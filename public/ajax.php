<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function kashiwazaki_poll_vote_ajax() {
    $poll_id = isset( $_POST['poll_id'] ) ? intval( $_POST['poll_id'] ) : 0;
    $nonce_value = isset($_POST['_wpnonce']) ? sanitize_text_field($_POST['_wpnonce']) : '';

    if ( ! wp_verify_nonce( $nonce_value, 'kashiwazaki_poll_vote_' . $poll_id ) ) {
        error_log("[Poll {$poll_id} Vote] Nonce verification FAILED.");
        wp_send_json( array( 'status' => 'error', 'message' => '不正なリクエストです。（nonce）' ) );
        wp_die();
    }

    // 受付を締め切った（ロック中の）投票は、キャッシュ済みページのフォームから
    // 送信されても受け付けない。
    if ( kashiwazaki_poll_is_locked( $poll_id ) ) {
        wp_send_json( array( 'status' => 'error', 'locked' => true, 'message' => 'この投票の受付は終了しました。' ) );
        wp_die();
    }

    $ip = kashiwazaki_poll_get_client_ip();
    // Cookieキーは v2（UTC基準）。タイムスタンプ移行に伴い旧キー(現地時刻オフセット基準)の
    // Cookieは読まない。これにより負のGMTオフセット環境での基準ズレを回避する。
    $cookie_key = 'kashiwazaki_poll_voted2_' . $poll_id;

    if ( kashiwazaki_poll_is_already_voted( $poll_id, $ip, $cookie_key ) ) {
        wp_send_json( array( 'status' => 'error', 'message' => '既に投票しています' ) );
        wp_die();
    }

    $poll_post = get_post( $poll_id );
    if ( ! $poll_post || $poll_post->post_type !== 'poll' ) {
        error_log("[Poll {$poll_id} Vote] Poll post not found.");
        wp_send_json( array( 'status' => 'error', 'message' => 'データが見つかりません。' ) );
        wp_die();
    }
    // 未公開(draft/private/trash)・パスワード保護されたpollへの投票を拒否（編集権限者を除く）。
    if ( ! kashiwazaki_poll_is_public_poll( $poll_post ) && ! current_user_can( 'edit_post', $poll_id ) ) {
        wp_send_json( array( 'status' => 'error', 'message' => 'データが見つかりません。' ) );
        wp_die();
    }

    $options = get_post_meta( $poll_id, '_kashiwazaki_poll_options', true );
    if ( ! is_array( $options ) || empty( $options ) ) {
        error_log("[Poll {$poll_id} Vote] Poll options not found.");
        wp_send_json( array( 'status' => 'error', 'message' => '選択肢がありません。' ) );
        wp_die();
    }

    // フォームを表示した後に選択肢が並べ替え・追加・削除されていたら、別の選択肢に票が入らないよう受け付けない。
    $options_sig = isset( $_POST['options_sig'] ) ? sanitize_key( wp_unslash( $_POST['options_sig'] ) ) : '';
    if ( '' === $options_sig || ! hash_equals( kashiwazaki_poll_options_signature( $options ), $options_sig ) ) {
        wp_send_json( array( 'status' => 'error', 'message' => '選択肢が変更されました。ページを再読み込みしてから投票してください。' ) );
        wp_die();
    }

    // 選択肢indexを整数化し重複排除（同一選択肢への多重加算を防止）。
    $selected_indices = isset( $_POST['poll_options'] ) ? (array) $_POST['poll_options'] : array();
    $selected_indices = array_values( array_unique( array_map( 'intval', $selected_indices ) ) );
    if ( empty( $selected_indices ) ) {
        error_log("[Poll {$poll_id} Vote] No options selected.");
        wp_send_json( array( 'status' => 'error', 'message' => '選択肢が選ばれていません。' ) );
        wp_die();
    }
    // 単一選択pollでは複数選択肢への同時投票を拒否（サーバー側で強制）。
    $poll_type = get_post_meta( $poll_id, '_kashiwazaki_poll_type', true );
    if ( $poll_type === 'single' && count( $selected_indices ) > 1 ) {
        wp_send_json( array( 'status' => 'error', 'message' => 'この設問では1つだけ選択できます。' ) );
        wp_die();
    }

    // 票数と投票済みの記録の書き換えは共通の入口を通す（排他・保存後の確認・失敗時の取り消しと管理者への通知）。
    // 排他の中で最新の値を読み直し、投票済みの判定と選択肢の確認をもう一度行う。
    // 投票時刻は排他を取って投票済みの判定を終えた後に決める（排他を待つ間に全削除が終わると、リセットより前の時刻で
    // 記録されて二重投票を許してしまうため）。記録とCookieには同じ時刻を使う。
    $mutation = kashiwazaki_poll_mutate_vote_state( $poll_id, function( $state ) use ( $poll_id, $ip, $cookie_key, $options_sig, $selected_indices ) {
        // 排他の中で、受付を締め切っていないかを直接読んだ最新の値で確かめる（待つ間に締め切られた場合も受け付けない）。
        if ( '1' === (string) $state['_kashiwazaki_poll_locked'] ) {
            return array( 'error' => 'locked' );
        }
        // 投票済みの判定は、直接読んだ最新の値でも行う（キャッシュの読み取りのエラーで判定が外れないように）。
        $voted    = is_array( $state['_kashiwazaki_poll_voted_ips'] ) ? $state['_kashiwazaki_poll_voted_ips'] : array();
        $reset_ts = max( (int) get_option( 'kashiwazaki_poll_reset_timestamp', 0 ), (int) $state['_kashiwazaki_poll_reset_ts'] );
        if ( ( isset( $voted[ $ip ] ) && (int) $voted[ $ip ] >= $reset_ts ) || kashiwazaki_poll_is_already_voted( $poll_id, $ip, $cookie_key ) ) {
            return array( 'error' => 'already_voted' );
        }
        $options = $state['_kashiwazaki_poll_options'];
        if ( ! is_array( $options ) || empty( $options ) || ! hash_equals( kashiwazaki_poll_options_signature( $options ), $options_sig ) ) {
            return array( 'error' => 'options_changed' );
        }
        $counts = kashiwazaki_poll_normalize_counts( $state['_kashiwazaki_poll_counts'], count( $options ) );
        $valid_vote_found = false;
        foreach ( $selected_indices as $idx ) {
            if ( isset( $counts[ $idx ] ) ) {
                $counts[ $idx ]++;
                $valid_vote_found = true;
            }
        }
        if ( ! $valid_vote_found ) {
            return array( 'error' => 'invalid_option' );
        }
        $voted_ips = is_array( $state['_kashiwazaki_poll_voted_ips'] ) ? $state['_kashiwazaki_poll_voted_ips'] : array();
        $vote_timestamp   = time();
        $voted_ips[ $ip ] = $vote_timestamp;
        return array(
            'set'    => array(
                '_kashiwazaki_poll_counts'    => $counts,
                '_kashiwazaki_poll_voted_ips' => $voted_ips,
            ),
            'result' => array( 'options' => $options, 'counts' => $counts, 'timestamp' => $vote_timestamp ),
        );
    } );
    if ( ! $mutation['ok'] ) {
        $messages = array(
            'busy'            => '混み合っています。少し待ってからもう一度お試しください。',
            'locked'          => 'この投票の受付は終了しました。',
            'already_voted'   => '既に投票しています',
            'options_changed' => '選択肢が変更されました。ページを再読み込みしてから投票してください。',
            'invalid_option'  => '無効な選択肢です。',
        );
        $message = isset( $messages[ $mutation['error'] ] ) ? $messages[ $mutation['error'] ] : '投票を記録できませんでした。時間をおいてもう一度お試しください。';
        $response = array( 'status' => 'error', 'message' => $message );
        if ( 'locked' === $mutation['error'] ) {
            $response['locked'] = true; // 受付終了の表示に切り替える（排他の前の判定と同じ形）
        }
        wp_send_json( $response );
        wp_die();
    }
    $options = $mutation['result']['options'];
    $counts  = $mutation['result']['counts'];
    $vote_timestamp = $mutation['result']['timestamp'];
    $new_total_votes = array_sum( $counts );

    // Cookie値に投票時刻を保存（is_already_voted がリセット時刻と比較して重複判定に使う）。
    setcookie( $cookie_key, (string) $vote_timestamp, time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

    // 静的データファイルとサイトマップを作り直す（生成関数が排他の中で公開してよいかを確かめるため、
    // 編集権限者の下書きプレビュー投票やパスワード保護されたpollではファイルを作らない）。
    // 混み合っていて作り直せなかったときは、少し後に作り直しを予約する（データファイルが古いまま残らないように）。
    if ( ! kashiwazaki_poll_generate_all_data_files( $poll_id ) && kashiwazaki_poll_is_public_poll( $poll_id ) ) {
        kashiwazaki_poll_schedule_regeneration( $poll_id );
    }

    wp_send_json( array(
        'status'  => 'ok',
        'poll_id' => $poll_id,
        'labels'  => array_map( 'kashiwazaki_poll_decode_stored_text', $options ),
        'counts'  => $counts,
        'total'   => $new_total_votes
    ) );
    wp_die();
}
add_action( 'wp_ajax_kashiwazaki_poll_vote', 'kashiwazaki_poll_vote_ajax' );
add_action( 'wp_ajax_nopriv_kashiwazaki_poll_vote', 'kashiwazaki_poll_vote_ajax' );

function kashiwazaki_poll_result_ajax() {
    $poll_id = isset( $_POST['poll_id'] ) ? intval( $_POST['poll_id'] ) : 0;
    if ( ! $poll_id ) { wp_send_json( array( 'status' => 'error', 'message' => 'poll_id がありません。' ) ); wp_die(); }
    $poll_post = get_post( $poll_id );
    if ( ! $poll_post || $poll_post->post_type !== 'poll' ) { wp_send_json( array( 'status' => 'error', 'message' => 'データが見つかりません。' ) ); wp_die(); }
    // 未公開pollの集計結果を未認証ユーザーに返さない（情報漏洩防止）。
    if ( ! kashiwazaki_poll_is_public_poll( $poll_post ) && ! current_user_can( 'edit_post', $poll_id ) ) { wp_send_json( array( 'status' => 'error', 'message' => 'データが見つかりません。' ) ); wp_die(); }
    $options = kashiwazaki_poll_get_display_options( $poll_id );
    $counts  = get_post_meta( $poll_id, '_kashiwazaki_poll_counts', true );
    if ( ! is_array( $options ) ) { $options = []; }
    $current_option_count = count($options);
    if ( ! is_array( $counts ) ) { $counts = array_fill(0, $current_option_count, 0); }
    else if (count($counts) < $current_option_count) { $counts = array_pad($counts, $current_option_count, 0); }
    else if (count($counts) > $current_option_count && $current_option_count > 0) { $counts = array_slice($counts, 0, $current_option_count); }
    elseif ($current_option_count === 0) { $counts = [];}
    $total = empty($counts) ? 0 : array_sum( $counts );
    wp_send_json( array( 'status' => 'ok', 'poll_id' => $poll_id, 'labels' => $options, 'counts' => $counts, 'total' => $total ) );
    wp_die();
}
add_action( 'wp_ajax_kashiwazaki_poll_result', 'kashiwazaki_poll_result_ajax' );
add_action( 'wp_ajax_nopriv_kashiwazaki_poll_result', 'kashiwazaki_poll_result_ajax' );
