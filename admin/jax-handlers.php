<?php
/**
 * Kashiwazaki SEO Poll Meta Boxes Ajax Handlers
 *
 * このファイルは、アンケート投稿（poll）のメタボックス保存に関するAjaxリクエストを処理します。
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('wp_ajax_kashiwazaki_poll_save_meta_box', 'kashiwazaki_poll_save_meta_box_ajax');
function kashiwazaki_poll_save_meta_box_ajax() {
    if ( ! isset( $_POST['kashiwazaki_poll_nonce'] ) || ! wp_verify_nonce( $_POST['kashiwazaki_poll_nonce'], 'kashiwazaki_poll_save_metabox' ) ) {
         wp_send_json_error('Nonce error');
         wp_die();
    }
    $post_id = intval($_POST['post_id']);
    if ( ! $post_id ) {
         wp_send_json_error('Invalid post ID');
         wp_die();
    }
    // 権限・投稿タイプ検証（nonce はCSRF対策であり認可ではない）。
    // 共有nonceで任意 post_id のメタを上書きされるのを防ぐ。
    if ( 'poll' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
         wp_send_json_error('権限がありません。');
         wp_die();
    }
    // --- 入力の検証を全て先に行い、検証失敗時は一切のメタ更新を行わない（部分更新防止）---
    $poll_type = isset($_POST['poll_type']) ? sanitize_text_field($_POST['poll_type']) : 'single';
    if ( ! in_array( $poll_type, array( 'single', 'multiple' ), true ) ) {
         $poll_type = 'single';
    }
    $raw_options = isset($_POST['poll_options']) ? trim($_POST['poll_options']) : '';
    $options = array();
    if ( ! empty($raw_options) ) {
         $arr = explode("\n", $raw_options);
         foreach ( $arr as $line ) {
             $line = trim($line);
             if ( $line !== '' ) {
                 $options[] = sanitize_text_field($line);
             }
         }
    }
    $description = isset($_POST['poll_description']) ? sanitize_textarea_field($_POST['poll_description']) : '';
    // 文字数(マルチバイト)で判定し、UIの「150文字以上」表記と一致させる。
    if ( mb_strlen( strip_tags($description) ) < 150 ) {
        wp_send_json_error('詳細な説明は150文字以上入力してください。');
        wp_die();
    }
    $license = isset($_POST['poll_license']) ? sanitize_text_field($_POST['poll_license']) : '';

    // --- 検証通過後にまとめて更新 ---
    update_post_meta($post_id, '_kashiwazaki_poll_type', $poll_type);
    // 選択肢更新の「前」に得票数を新選択肢へ再マッピング（票の破壊/付替を防止）。
    kashiwazaki_poll_remap_counts_for_new_options( $post_id, $options );
    update_post_meta($post_id, '_kashiwazaki_poll_options', $options);
    update_post_meta($post_id, '_kashiwazaki_poll_description', $description);
    update_post_meta($post_id, '_kashiwazaki_poll_license', $license);

    // 選択肢/得票数の変更を反映してデータファイルを再生成（公開pollのみ／options更新後に実行）。
    if ( get_post_status( $post_id ) === 'publish' && function_exists( 'kashiwazaki_poll_generate_all_data_files' ) ) {
        $current_counts = get_post_meta( $post_id, '_kashiwazaki_poll_counts', true );
        kashiwazaki_poll_generate_all_data_files( $post_id, is_array( $current_counts ) ? $current_counts : array() );
    }

    wp_send_json_success('Meta box data saved successfully.');
    wp_die();
}