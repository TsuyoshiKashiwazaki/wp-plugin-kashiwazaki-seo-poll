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
    $poll_type = isset($_POST['poll_type']) ? sanitize_text_field($_POST['poll_type']) : 'single';
    update_post_meta($post_id, '_kashiwazaki_poll_type', $poll_type);
    $raw_options = isset($_POST['poll_options']) ? trim($_POST['poll_options']) : '';
    $options = array();
    if ( ! empty($raw_options) ) {
         $arr = explode("\n", $raw_options);
         foreach ( $arr as $line ) {
             $line = trim($line);
             if ( $line !== '' ) {
                 $options[] = $line;
             }
         }
    }
    update_post_meta($post_id, '_kashiwazaki_poll_options', $options);
    $description = isset($_POST['poll_description']) ? sanitize_textarea_field($_POST['poll_description']) : '';
    if ( strlen( strip_tags($description) ) < 150 ) {
        wp_send_json_error('詳細な説明は150文字以上入力してください。');
        wp_die();
    }
    update_post_meta($post_id, '_kashiwazaki_poll_description', $description);
    $license = isset($_POST['poll_license']) ? sanitize_text_field($_POST['poll_license']) : '';
    update_post_meta($post_id, '_kashiwazaki_poll_license', $license);
    wp_send_json_success('Meta box data saved successfully.');
    wp_die();
}