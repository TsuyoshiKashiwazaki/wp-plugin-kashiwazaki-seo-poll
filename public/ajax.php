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
    // 未公開(draft/private/trash)pollへの投票を拒否（編集権限者を除く）。
    if ( $poll_post->post_status !== 'publish' && ! current_user_can( 'edit_post', $poll_id ) ) {
        wp_send_json( array( 'status' => 'error', 'message' => 'データが見つかりません。' ) );
        wp_die();
    }

    $options = get_post_meta( $poll_id, '_kashiwazaki_poll_options', true );
    if ( ! is_array( $options ) || empty( $options ) ) {
        error_log("[Poll {$poll_id} Vote] Poll options not found.");
        wp_send_json( array( 'status' => 'error', 'message' => '選択肢がありません。' ) );
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

    $counts = get_post_meta( $poll_id, '_kashiwazaki_poll_counts', true );
    $current_option_count = count($options);
    if ( ! is_array( $counts ) ) {
        $counts = array_fill( 0, $current_option_count, 0 );
    } else if ( count( $counts ) < $current_option_count ) {
        $counts = array_pad( $counts, $current_option_count, 0 );
    } else if ( count( $counts ) > $current_option_count && $current_option_count > 0) {
        $counts = array_slice( $counts, 0, $current_option_count );
    } elseif ($current_option_count === 0) {
        $counts = [];
    }

    $valid_vote_found = false;
    foreach ( $selected_indices as $s ) {
        $idx = intval( $s );
        if ( isset( $counts[ $idx ] ) ) {
             $counts[ $idx ]++;
             $valid_vote_found = true;
        } else {
             error_log("[Poll {$poll_id} Vote] Invalid option index received: " . $idx);
        }
    }
    if (!$valid_vote_found && !empty($selected_indices)) {
         error_log("[Poll {$poll_id} Vote] No valid option indices were processed.");
         wp_send_json( array( 'status' => 'error', 'message' => '無効な選択肢です。' ) );
         wp_die();
    }

    $new_total_votes = array_sum( $counts );
    $vote_timestamp = time();

    $update_counts_success = update_post_meta( $poll_id, '_kashiwazaki_poll_counts', $counts );
    if (!$update_counts_success) {
         error_log("[Poll {$poll_id} Vote] Failed to update counts meta.");
    }

    $voted_ips = get_post_meta( $poll_id, '_kashiwazaki_poll_voted_ips', true );
    if ( ! is_array( $voted_ips ) ) { $voted_ips = array(); }
    $voted_ips[ $ip ] = $vote_timestamp;
    update_post_meta( $poll_id, '_kashiwazaki_poll_voted_ips', $voted_ips );

    // Cookie値に投票時刻を保存（is_already_voted がリセット時刻と比較して重複判定に使う）。
    setcookie( $cookie_key, (string) $vote_timestamp, time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

    // 公開pollのみ静的データファイルを生成（編集権限者のdraftプレビュー投票で
    // 未公開pollの公開ファイルが生成されるのを防ぐ）。サイトマップも再生成し、
    // 各データセットURLの lastmod（=データファイルの filemtime）を最新化する。
    if ( $poll_post->post_status === 'publish' ) {
        kashiwazaki_poll_generate_all_data_files( $poll_id, $counts );
    }

    if ( ! is_array( $options ) ) { $options = []; }
    wp_send_json( array(
        'status'  => 'ok',
        'poll_id' => $poll_id,
        'labels'  => $options,
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
    if ( $poll_post->post_status !== 'publish' && ! current_user_can( 'edit_post', $poll_id ) ) { wp_send_json( array( 'status' => 'error', 'message' => 'データが見つかりません。' ) ); wp_die(); }
    $options = get_post_meta( $poll_id, '_kashiwazaki_poll_options', true );
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
