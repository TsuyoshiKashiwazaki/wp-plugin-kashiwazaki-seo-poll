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

    $ip = $_SERVER['REMOTE_ADDR'];
    $cookie_key = 'kashiwazaki_poll_voted_' . $poll_id;

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

    $options = get_post_meta( $poll_id, '_kashiwazaki_poll_options', true );
    if ( ! is_array( $options ) || empty( $options ) ) {
        error_log("[Poll {$poll_id} Vote] Poll options not found.");
        wp_send_json( array( 'status' => 'error', 'message' => '選択肢がありません。' ) );
        wp_die();
    }

    $selected_indices = isset( $_POST['poll_options'] ) ? (array) $_POST['poll_options'] : array();
    if ( empty( $selected_indices ) ) {
        error_log("[Poll {$poll_id} Vote] No options selected.");
        wp_send_json( array( 'status' => 'error', 'message' => '選択肢が選ばれていません。' ) );
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
    $vote_timestamp = current_time( 'timestamp' );

    $update_counts_success = update_post_meta( $poll_id, '_kashiwazaki_poll_counts', $counts );
    if (!$update_counts_success) {
         error_log("[Poll {$poll_id} Vote] Failed to update counts meta.");
    }

    $voted_ips = get_post_meta( $poll_id, '_kashiwazaki_poll_voted_ips', true );
    if ( ! is_array( $voted_ips ) ) { $voted_ips = array(); }
    $voted_ips[ $ip ] = $vote_timestamp;
    update_post_meta( $poll_id, '_kashiwazaki_poll_voted_ips', $voted_ips );

    setcookie( $cookie_key, '1', time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

    kashiwazaki_poll_generate_all_data_files( $poll_id, $counts );

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
