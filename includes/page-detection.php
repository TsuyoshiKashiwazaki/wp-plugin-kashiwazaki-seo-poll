<?php
/**
 * カスタムページ検出機能
 *
 * Poll プラグインが提供するすべてのカスタムページを
 * 他のプラグインから検出可能にする仕組み
 *
 * @package Kashiwazaki_SEO_Poll
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Poll が提供するすべてのページタイプを登録
 *
 * @param array $page_types 既存のページタイプ配列
 * @return array 拡張されたページタイプ配列
 */
function kashiwazaki_poll_register_page_types($page_types) {
    // Poll のすべてのカスタムページタイプを配列で返す
    // キー: 識別子（一意）
    // 値: 人間が読めるラベル

    $poll_page_types = array(
        // 単一データセットページ（フォーマット別）
        'poll_dataset_csv'  => 'CSVデータセット詳細',
        'poll_dataset_xml'  => 'XMLデータセット詳細',
        'poll_dataset_yaml' => 'YAMLデータセット詳細',
        'poll_dataset_json' => 'JSONデータセット詳細',
        'poll_dataset_svg'  => 'SVGチャート詳細',

        // 一覧ページ（フォーマット別）
        'poll_archive_csv'  => 'CSVデータセット一覧',
        'poll_archive_xml'  => 'XMLデータセット一覧',
        'poll_archive_yaml' => 'YAMLデータセット一覧',
        'poll_archive_json' => 'JSONデータセット一覧',
        'poll_archive_svg'  => 'SVGチャート一覧',

        // 全体一覧ページ
        'poll_archive_all'  => '全データセット一覧',

        // 単一投稿（カスタム投稿タイプ poll）
        'poll_single'       => 'Poll詳細ページ',
    );

    return array_merge($page_types, $poll_page_types);
}

/**
 * 現在表示中のページタイプを検出
 *
 * @param string|false $current_type 既に検出されているページタイプ
 * @return string|false Poll のページタイプ識別子、または false
 */
function kashiwazaki_poll_detect_current_page($current_type) {
    // 既に他のプラグインでページタイプが検出されている場合はそれを優先
    if ($current_type) {
        return $current_type;
    }

    // Poll が登録している独自のクエリ変数をチェック

    // 1. フォーマット別単一データセットページ (/datasets/{format}/detail-{ID}/)
    $single_dataset_type = get_query_var('kashiwazaki_poll_single_dataset_type');
    $single_dataset_id = get_query_var('kashiwazaki_poll_single_dataset_id');

    if ($single_dataset_type && $single_dataset_id) {
        // フォーマットに応じたページタイプを返す
        $format_map = array(
            'csv'  => 'poll_dataset_csv',
            'xml'  => 'poll_dataset_xml',
            'yaml' => 'poll_dataset_yaml',
            'json' => 'poll_dataset_json',
            'svg'  => 'poll_dataset_svg',
        );

        if (isset($format_map[$single_dataset_type])) {
            return $format_map[$single_dataset_type];
        }
    }

    // 2. フォーマット別一覧ページ (/datasets/{format}/)
    $format_listing = get_query_var('kashiwazaki_poll_format_listing');
    $format_type = get_query_var('kashiwazaki_poll_format_type');

    if ($format_listing && $format_type) {
        // フォーマットに応じたアーカイブページタイプを返す
        $archive_map = array(
            'csv'  => 'poll_archive_csv',
            'xml'  => 'poll_archive_xml',
            'yaml' => 'poll_archive_yaml',
            'json' => 'poll_archive_json',
            'svg'  => 'poll_archive_svg',
        );

        if (isset($archive_map[$format_type])) {
            return $archive_map[$format_type];
        }
    }

    // 3. 全体一覧ページ (/datasets/)
    $datasets_page = get_query_var('kashiwazaki_poll_datasets_page');

    if ($datasets_page) {
        return 'poll_archive_all';
    }

    // 4. 単一投稿ページ (カスタム投稿タイプ poll: /datasets/detail-{ID}/)
    if (is_singular('poll')) {
        return 'poll_single';
    }

    // Poll のページではない
    return false;
}

/**
 * 現在のページに関する詳細情報を提供
 *
 * @param array $page_info 既存のページ情報
 * @return array 拡張されたページ情報
 */
function kashiwazaki_poll_get_page_info($page_info) {
    $page_type = kashiwazaki_poll_detect_current_page(false);

    if (!$page_type) {
        return $page_info;
    }

    $poll_info = array(
        'page_type' => $page_type,
        'is_poll_page' => true,
    );

    // フォーマット別単一データセットページの場合
    $single_dataset_type = get_query_var('kashiwazaki_poll_single_dataset_type');
    $single_dataset_id = get_query_var('kashiwazaki_poll_single_dataset_id');

    if ($single_dataset_type && $single_dataset_id) {
        $poll_info['format'] = $single_dataset_type;
        $poll_info['poll_id'] = intval($single_dataset_id);
        $poll_info['is_singular'] = true;
        $poll_info['is_archive'] = false;
    }

    // フォーマット別一覧ページの場合
    $format_listing = get_query_var('kashiwazaki_poll_format_listing');
    $format_type = get_query_var('kashiwazaki_poll_format_type');

    if ($format_listing && $format_type) {
        $poll_info['format'] = $format_type;
        $poll_info['is_singular'] = false;
        $poll_info['is_archive'] = true;
    }

    // 全体一覧ページの場合
    $datasets_page = get_query_var('kashiwazaki_poll_datasets_page');

    if ($datasets_page) {
        $poll_info['is_singular'] = false;
        $poll_info['is_archive'] = true;
        $poll_info['format'] = 'all';
    }

    // 単一投稿ページの場合
    if (is_singular('poll')) {
        global $post;
        $poll_info['poll_id'] = $post->ID;
        $poll_info['is_singular'] = true;
        $poll_info['is_archive'] = false;
        $poll_info['format'] = 'single';
    }

    return array_merge($page_info, $poll_info);
}

/**
 * Poll のページであるかどうかを判定する便利関数
 *
 * @return bool
 */
function is_poll_page() {
    return (bool) kashiwazaki_poll_detect_current_page(false);
}

/**
 * Poll のフォーマット別データセットページであるかどうかを判定
 *
 * @param string|null $format 特定のフォーマット（csv, xml, yaml, json, svg）を指定可能
 * @return bool
 */
function is_poll_dataset($format = null) {
    $page_type = kashiwazaki_poll_detect_current_page(false);

    if (!$page_type) {
        return false;
    }

    $dataset_types = array('poll_dataset_csv', 'poll_dataset_xml', 'poll_dataset_yaml', 'poll_dataset_json', 'poll_dataset_svg');

    if (!in_array($page_type, $dataset_types)) {
        return false;
    }

    if ($format) {
        $format_map = array(
            'csv'  => 'poll_dataset_csv',
            'xml'  => 'poll_dataset_xml',
            'yaml' => 'poll_dataset_yaml',
            'json' => 'poll_dataset_json',
            'svg'  => 'poll_dataset_svg',
        );

        return isset($format_map[$format]) && $page_type === $format_map[$format];
    }

    return true;
}

/**
 * Poll のアーカイブページであるかどうかを判定
 *
 * @param string|null $format 特定のフォーマット（csv, xml, yaml, json, svg, all）を指定可能
 * @return bool
 */
function is_poll_archive($format = null) {
    $page_type = kashiwazaki_poll_detect_current_page(false);

    if (!$page_type) {
        return false;
    }

    $archive_types = array('poll_archive_csv', 'poll_archive_xml', 'poll_archive_yaml', 'poll_archive_json', 'poll_archive_svg', 'poll_archive_all');

    if (!in_array($page_type, $archive_types)) {
        return false;
    }

    if ($format) {
        $format_map = array(
            'csv'  => 'poll_archive_csv',
            'xml'  => 'poll_archive_xml',
            'yaml' => 'poll_archive_yaml',
            'json' => 'poll_archive_json',
            'svg'  => 'poll_archive_svg',
            'all'  => 'poll_archive_all',
        );

        return isset($format_map[$format]) && $page_type === $format_map[$format];
    }

    return true;
}

/**
 * 現在のページのフォーマットを取得
 *
 * @return string|false フォーマット（csv, xml, yaml, json, svg, all, single）または false
 */
function get_poll_current_format() {
    $page_info = kashiwazaki_poll_get_page_info(array());

    return isset($page_info['format']) ? $page_info['format'] : false;
}

// フィルターフックへの登録
// 様々なプラグインが使用する可能性のあるフィルターフックに登録

// ページタイプ登録フィルター
$registration_filters = array(
    'kssctb_archive_types',        // Schema Content Type Builder (アーカイブ)
    'kssctb_page_types',           // Schema Content Type Builder (全ページ)
    'custom_page_types',           // 汎用
    'seo_page_types',              // SEO プラグイン
    'breadcrumb_page_types',       // パンくずリスト
    'sitemap_page_types',          // サイトマップ
    'analytics_page_types',        // アナリティクス
    'cache_page_types',            // キャッシュ
);

foreach ($registration_filters as $filter_name) {
    add_filter($filter_name, 'kashiwazaki_poll_register_page_types', 10, 1);
}

// 現在のページタイプ検出フィルター
$detection_filters = array(
    'kssctb_current_archive_type', // Schema Content Type Builder
    'kssctb_current_page_type',    // Schema Content Type Builder
    'current_page_type',           // 汎用
    'seo_current_page_type',       // SEO プラグイン
    'breadcrumb_page_type',        // パンくずリスト
    'analytics_page_type',         // アナリティクス
);

foreach ($detection_filters as $filter_name) {
    add_filter($filter_name, 'kashiwazaki_poll_detect_current_page', 10, 1);
}

// ページ情報提供フィルター
$info_filters = array(
    'kssctb_page_info',            // Schema Content Type Builder
    'current_page_info',           // 汎用
    'seo_page_info',               // SEO プラグイン
    'breadcrumb_page_info',        // パンくずリスト
);

foreach ($info_filters as $filter_name) {
    add_filter($filter_name, 'kashiwazaki_poll_get_page_info', 10, 1);
}
