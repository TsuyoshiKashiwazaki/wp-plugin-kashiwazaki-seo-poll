<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'query_vars', 'kashiwazaki_poll_add_query_vars' );
function kashiwazaki_poll_add_query_vars( $vars ) {
    $vars[] = 'kashiwazaki_poll_datasets_page';
    $vars[] = 'kashiwazaki_poll_single_dataset_id';
    $vars[] = 'kashiwazaki_poll_single_dataset_type';
    $vars[] = 'kashiwazaki_poll_format_listing';
    $vars[] = 'kashiwazaki_poll_format_type';
    $vars[] = 'kashiwazaki_poll_legacy_redirect';
    $vars[] = 'kashiwazaki_poll_legacy_id';
    $vars[] = 'kashiwazaki_poll_legacy_format';
    $vars[] = 'paged';
    return $vars;
}

add_action( 'init', 'kashiwazaki_poll_add_rewrite_rules', 10, 0 );
function kashiwazaki_poll_add_rewrite_rules() {
    // フォーマット別個別データ: /datasets/csv/detail-123/
    add_rewrite_rule(
        '^datasets/(csv|xml|yaml|json|svg)/detail-([0-9]+)/?$',
        'index.php?kashiwazaki_poll_single_dataset_type=$matches[1]&kashiwazaki_poll_single_dataset_id=$matches[2]',
        'top'
    );
    // フォーマット別一覧ページネーション: /datasets/csv/page-2/
    add_rewrite_rule(
        '^datasets/(csv|xml|yaml|json|svg)/page-([0-9]+)/?$',
        'index.php?kashiwazaki_poll_format_listing=1&kashiwazaki_poll_format_type=$matches[1]&paged=$matches[2]',
        'top'
    );
    // フォーマット別一覧トップ: /datasets/csv/
    add_rewrite_rule(
        '^datasets/(csv|xml|yaml|json|svg)/?$',
        'index.php?kashiwazaki_poll_format_listing=1&kashiwazaki_poll_format_type=$matches[1]',
        'top'
    );

    // 古いURL構造からのリダイレクト: /datasets/poll/{id}/{format}/ → /datasets/{format}/{id}/
    add_rewrite_rule(
        '^datasets/poll/([0-9]+)/(csv|xml|yaml|json|svg)/?$',
        'index.php?kashiwazaki_poll_legacy_redirect=1&kashiwazaki_poll_legacy_id=$matches[1]&kashiwazaki_poll_legacy_format=$matches[2]',
        'top'
    );

    // ページネーション: /datasets/page-2/
    add_rewrite_rule(
        '^datasets/page-([0-9]+)/?$',
        'index.php?kashiwazaki_poll_datasets_page=1&paged=$matches[1]',
        'top'
    );
    // 一覧トップ: /datasets/
    add_rewrite_rule(
        '^datasets/?$',
        'index.php?kashiwazaki_poll_datasets_page=1',
        'top'
    );
}

// プラグインの書き換えルール更新時にパーマリンクを再生成
add_action('init', 'kashiwazaki_poll_check_rewrite_rules');
function kashiwazaki_poll_check_rewrite_rules() {
    $rewrite_version = get_option('kashiwazaki_poll_rewrite_version', '1.0');
    $current_version = '4.2'; // 古いURL構造からのリダイレクト追加版

    // プラグイン有効化後の初回フラッシュ
    if (get_option('kashiwazaki_poll_flush_rewrite_rules')) {
        flush_rewrite_rules();
        delete_option('kashiwazaki_poll_flush_rewrite_rules');
    }

    if (version_compare($rewrite_version, $current_version, '<')) {
        flush_rewrite_rules();
        update_option('kashiwazaki_poll_rewrite_version', $current_version);
    }

    // URL パラメータでのフラッシュ要求対応（デバッグ用）
    if (isset($_GET['flush_kashiwazaki_rewrite_rules']) && current_user_can('manage_options')) {
        flush_rewrite_rules();
        wp_redirect(remove_query_arg('flush_kashiwazaki_rewrite_rules'));
        exit;
    }


}

// プラグイン有効化時にリライトルールをフラッシュ（メインファイルで処理）





add_action( 'wp', 'kashiwazaki_poll_wp_datasets', 1 );
function kashiwazaki_poll_wp_datasets() {
    // 直接URLパターンをチェック（最早タイミングで処理）
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $parsed_path = parse_url($request_uri, PHP_URL_PATH);
    $request_path = trim($parsed_path ?: '', '/');

    // サブディレクトリ設置(例 https://example.com/wp/)に対応:
    // home_url のパス接頭辞を取り除いてから datasets パターンを判定する。
    $home_path = trim( (string) parse_url( home_url(), PHP_URL_PATH ), '/' );
    if ( $home_path !== '' ) {
        if ( strpos( $request_path, $home_path . '/' ) === 0 ) {
            $request_path = ltrim( substr( $request_path, strlen( $home_path ) ), '/' );
        } elseif ( $request_path === $home_path ) {
            $request_path = '';
        }
    }

    // 古いURL構造からのリダイレクト処理: /datasets/poll/{id}/{format}/ → /datasets/{format}/detail-{id}/
    if (preg_match('/^datasets\/poll\/(\d+)\/(csv|xml|yaml|json|svg)$/', $request_path, $matches)) {
        $poll_id = intval($matches[1]);
        $format = $matches[2];
        $new_url = home_url("/datasets/{$format}/detail-{$poll_id}/");
        wp_redirect($new_url, 301);
        exit;
    }

    // 旧URL構造からのリダイレクト処理: /datasets/{format}/{id}/ → /datasets/{format}/detail-{id}/
    if (preg_match('/^datasets\/(csv|xml|yaml|json|svg)\/(\d+)$/', $request_path, $matches)) {
        $format = $matches[1];
        $poll_id = intval($matches[2]);

        // IDが存在するか確認
        $poll_post = get_post($poll_id);
        if ( kashiwazaki_poll_is_public_poll( $poll_post ) ) {
            $new_url = home_url("/datasets/{$format}/detail-{$poll_id}/");
            wp_redirect($new_url, 301);
            exit;
        }
    }

    if ($request_path === 'datasets') {
        kashiwazaki_poll_render_datasets_index_page();
        exit;
    }

    // ページネーション: /datasets/page-2/
    if (preg_match('/^datasets\/page-(\d+)$/', $request_path, $matches)) {
        global $wp_query;
        $wp_query->set('paged', intval($matches[1]));
        kashiwazaki_poll_render_datasets_index_page();
        exit;
    }

    // 個別投稿: /datasets/detail-123/ は WordPress のカスタム投稿タイプで処理される

    // フォーマット別個別データ: /datasets/csv/detail-123/
    if (preg_match('/^datasets\/(csv|xml|yaml|json|svg)\/detail-(\d+)$/', $request_path, $matches)) {
        kashiwazaki_poll_render_single_dataset_page( intval($matches[2]), $matches[1] );
        exit;
    }

    // フォーマット別一覧ページネーション: /datasets/csv/page-2/
    if (preg_match('/^datasets\/(csv|xml|yaml|json|svg)\/page-(\d+)$/', $request_path, $matches)) {
        global $wp_query;
        $wp_query->set('paged', intval($matches[2]));
        kashiwazaki_poll_render_format_listing_page($matches[1]);
        exit;
    }

    if (preg_match('/^datasets\/(csv|xml|yaml|json|svg)\/?$/', $request_path, $matches)) {
        kashiwazaki_poll_render_format_listing_page($matches[1]);
        exit;
    }
}



function kashiwazaki_poll_get_dataset_page_title() {
    $settings = get_option( 'kashiwazaki_poll_settings', array( 'dataset_page_title' => '集計データ一覧' ) );
    return !empty($settings['dataset_page_title']) ? $settings['dataset_page_title'] : '集計データ一覧';
}

function kashiwazaki_poll_get_header_footer_content() {
    $settings = get_option( 'kashiwazaki_poll_settings', array(
        'creator_type' => 'organization_only',
        'creator_organization_name' => get_bloginfo('name'),
        'creator_organization_url' => home_url(),
        'dataset_color_theme' => 'minimal'
    ) );

    $site_name = get_bloginfo('name');
    $site_url = home_url();

    // Creator情報を取得
    $creator_info = array();
    if ( $settings['creator_type'] === 'organization_only' || $settings['creator_type'] === 'both' ) {
        $org_name = !empty($settings['creator_organization_name']) ? $settings['creator_organization_name'] : $site_name;
        $org_url = !empty($settings['creator_organization_url']) ? $settings['creator_organization_url'] : $site_url;
        $creator_info['organization'] = array(
            'name' => $org_name,
            'url' => $org_url
        );
    }

    if ( $settings['creator_type'] === 'person_only' || $settings['creator_type'] === 'both' ) {
        if ( !empty($settings['creator_person_name']) ) {
            $creator_info['person'] = array(
                'name' => $settings['creator_person_name'],
                'url' => !empty($settings['creator_person_url']) ? $settings['creator_person_url'] : $site_url
            );
        }
    }

    return array(
        'site_name' => $site_name,
        'site_url' => $site_url,
        'creator_info' => $creator_info,
        'color_theme' => $settings['dataset_color_theme']
    );
}

function kashiwazaki_poll_render_unified_pagination($current_page, $max_pages, $base_url, $current_theme) {
    if ($max_pages <= 1) return;

    ?>
    <div class="datasets-pagination">
        <div class="pagination-container">
            <?php if ($current_page > 1): ?>
                <a href="<?php echo esc_url($base_url . ($current_page > 2 ? 'page-' . ($current_page - 1) . '/' : '')); ?>" class="pagination-link pagination-prev">
                    <span>&larr;</span>前のページ
                </a>
            <?php endif; ?>

            <div class="pagination-numbers">
                <?php
                $start = max(1, $current_page - 2);
                $end = min($max_pages, $current_page + 2);

                if ($start > 1): ?>
                    <a href="<?php echo esc_url($base_url); ?>" class="pagination-link">1</a>
                    <?php if ($start > 2): ?>
                        <span class="pagination-ellipsis">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i == $current_page): ?>
                        <span class="pagination-current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo esc_url($base_url . ($i > 1 ? 'page-' . $i . '/' : '')); ?>" class="pagination-link"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $max_pages): ?>
                    <?php if ($end < $max_pages - 1): ?>
                        <span class="pagination-ellipsis">...</span>
                    <?php endif; ?>
                    <a href="<?php echo esc_url($base_url . 'page-' . $max_pages . '/'); ?>" class="pagination-link"><?php echo $max_pages; ?></a>
                <?php endif; ?>
            </div>

            <?php if ($current_page < $max_pages): ?>
                <a href="<?php echo esc_url($base_url . 'page-' . ($current_page + 1) . '/'); ?>" class="pagination-link pagination-next">
                    次のページ<span>&rarr;</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function kashiwazaki_poll_output_breadcrumb_structured_data($breadcrumbs) {
    if (empty($breadcrumbs)) return;

    // 設定でBreadcrumbList出力が無効化されている場合は出力しない
    $settings = get_option('kashiwazaki_poll_settings', array('breadcrumb_structured_data' => 0));
    if (empty($settings['breadcrumb_structured_data'])) {
        return;
    }

    $list_items = array();
    foreach ($breadcrumbs as $index => $crumb) {
        $list_items[] = array(
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $crumb['name'],
            'item' => !empty($crumb['url']) ? $crumb['url'] : null
        );
    }

    $structured_data = array(
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $list_items
    );

    echo '<script type="application/ld+json">';
    echo wp_json_encode($structured_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    echo '</script>' . "\n";
}

function kashiwazaki_poll_output_google_datasets_meta($page_type, $data = null) {
    $header_data = kashiwazaki_poll_get_header_footer_content();

    // 共通のCreator情報を取得
    $creator = array();
    if (!empty($header_data['creator_info']['organization'])) {
        $creator[] = array(
            '@type' => 'Organization',
            'name' => $header_data['creator_info']['organization']['name'],
            'url' => $header_data['creator_info']['organization']['url']
        );
    }
    if (!empty($header_data['creator_info']['person'])) {
        $creator[] = array(
            '@type' => 'Person',
            'name' => $header_data['creator_info']['person']['name'],
            'url' => $header_data['creator_info']['person']['url']
        );
    }

    echo '<meta name="DC.creator" content="' . esc_attr($header_data['site_name']) . '">' . "\n";
    echo '<meta name="DC.publisher" content="' . esc_attr($header_data['site_name']) . '">' . "\n";
    echo '<meta name="DC.rights" content="© ' . date('Y') . ' ' . esc_attr($header_data['site_name']) . '">' . "\n";
    echo '<meta name="DC.type" content="Dataset">' . "\n";
    echo '<meta name="DC.format" content="text/html">' . "\n";
    echo '<meta name="DC.language" content="ja">' . "\n";

    if ($page_type === 'catalog') {
        // データカタログ用のmeta情報
        echo '<meta name="DC.title" content="' . esc_attr(kashiwazaki_poll_get_dataset_page_title() . ' - ' . $header_data['site_name']) . '">' . "\n";
        echo '<meta name="DC.description" content="当サイトで実施された調査の集計データセット一覧です。様々な形式でデータをダウンロード・閲覧できます。">' . "\n";
        echo '<meta name="DC.subject" content="集計データ,データセット,統計,調査">' . "\n";
    } elseif ($page_type === 'format_listing' && $data) {
        // フォーマット別一覧用のmeta情報
        $format_names = array(
            'csv' => 'CSV',
            'xml' => 'XML',
            'yaml' => 'YAML',
            'json' => 'JSON',
            'svg' => 'SVG'
        );
        $format_name = $format_names[$data['format']] ?? strtoupper($data['format']);

        echo '<meta name="DC.title" content="' . esc_attr($format_name . 'データセット一覧 - ' . $header_data['site_name']) . '">' . "\n";
        echo '<meta name="DC.description" content="' . esc_attr($format_name . '形式の集計データ一覧です。当サイトで実施された調査の' . $format_name . 'データセットを閲覧・ダウンロードできます。') . '">' . "\n";
        echo '<meta name="DC.subject" content="' . esc_attr($format_name . ',集計データ,データセット') . '">' . "\n";
        echo '<meta name="DC.format" content="' . esc_attr(strtolower($format_name)) . '">' . "\n";
    } elseif ($page_type === 'single_dataset' && $data) {
        // 個別データセット用のmeta情報
        $format_name = strtoupper($data['file_type']);
        echo '<meta name="DC.title" content="' . esc_attr($data['poll_title'] . ' - ' . $format_name . 'データセット') . '">' . "\n";
        echo '<meta name="DC.description" content="' . esc_attr($data['poll_description']) . '">' . "\n";
        echo '<meta name="DC.subject" content="' . esc_attr($format_name . ',集計結果,' . $data['poll_title']) . '">' . "\n";
        echo '<meta name="DC.format" content="' . esc_attr(strtolower($data['file_type'])) . '">' . "\n";
        // 最新投票時刻を取得
        $last_vote_time = kashiwazaki_poll_get_last_updated_time( $data['poll_id'] );
        $meta_date = $last_vote_time > 0 ? $last_vote_time : $data['file_mtime'];
        echo '<meta name="DC.date" content="' . esc_attr(wp_date('Y-m-d', $meta_date)) . '">' . "\n";
        echo '<meta name="DC.extent" content="' . esc_attr($data['total_votes'] . ' votes') . '">' . "\n";
    }
}

function kashiwazaki_poll_output_google_dataset_search_meta($data) {
    // プラグイン設定から値を取得
    $settings = get_option('kashiwazaki_poll_settings', array());
    $poll_post = get_post($data['poll_id']);

    // データセット名
    echo '<meta itemprop="name" content="' . esc_attr($data['poll_title'] . ' - ' . strtoupper($data['file_type']) . 'データセット') . '">' . "\n";

    // ランディングページのURL
    echo '<meta itemprop="url" content="' . esc_attr($data['page_url']) . '">' . "\n";

    // 関連ページのURL（アンケートフォームが掲載されているページ）
    if ($poll_post && $poll_post->post_status === 'publish') {
        echo '<meta itemprop="sameAs" content="' . esc_attr(get_permalink($poll_post->ID)) . '">' . "\n";
    }

    // ライセンスのURL（個別アンケート投稿から取得）
    $license_url = get_post_meta($data['poll_id'], '_kashiwazaki_poll_license', true);
    if (empty($license_url)) {
        $license_url = 'https://creativecommons.org/licenses/by/4.0/';
    }
    echo '<meta itemprop="license" content="' . esc_attr($license_url) . '">' . "\n";

    // 引用情報
    $creator_name = !empty($settings['creator_organization_name']) ? $settings['creator_organization_name'] : get_bloginfo('name');
    // 最新投票時刻を取得してcitationで使用
    $last_vote_time = kashiwazaki_poll_get_last_updated_time( $data['poll_id'] );
    $citation_date = $last_vote_time > 0 ? $last_vote_time : $data['file_mtime'];
    $citation = $creator_name . ' (' . wp_date('Y', $citation_date) . '). ' . $data['poll_title'] . '. ' . get_bloginfo('name') . '.';
    echo '<meta itemprop="citation" content="' . esc_attr($citation) . '">' . "\n";

            // キーワード（個別アンケート投稿のカスタムキーワードがある場合のみ出力）
    $poll_keywords = kashiwazaki_poll_decode_stored_text( get_post_meta( $data['poll_id'], 'dataset_keywords', true ) );
    if (!empty($poll_keywords)) {
        $custom_keywords = array_map('trim', explode(',', $poll_keywords));
        $keywords = array_unique($custom_keywords);
        foreach ($keywords as $keyword) {
            if (!empty(trim($keyword))) {
                echo '<meta itemprop="keywords" content="' . esc_attr(trim($keyword)) . '">' . "\n";
            }
        }
    }

    // 公開日（datePublished で後段でも使用するため、分岐前に必ず代入しておく）。
    $poll_date = get_the_date('Y-m-d', $poll_post);

    // 時間的範囲（調査期間）
    $survey_period = kashiwazaki_poll_get_survey_period($data['poll_id']);
    if ($survey_period) {
        $survey_start = wp_date('Y-m-d', $survey_period['start']);
        $survey_end = wp_date('Y-m-d', $survey_period['end']);
        echo '<meta itemprop="temporalCoverage" content="' . esc_attr($survey_start . '/' . $survey_end) . '">' . "\n";
    } else {
        $current_date = current_time('Y-m-d');
        echo '<meta itemprop="temporalCoverage" content="' . esc_attr($poll_date . '/' . $current_date) . '">' . "\n";
    }

    // 地理的範囲
    $default_spatial = '日本';
    $spatial_coverage = !empty($settings['dataset_spatial_coverage']) ? $settings['dataset_spatial_coverage'] : $default_spatial;
    echo '<meta itemprop="spatialCoverage" content="' . esc_attr($spatial_coverage) . '">' . "\n";

    // 公開日
    echo '<meta itemprop="datePublished" content="' . esc_attr($poll_date) . '">' . "\n";

    // 更新日（最新投票時刻を使用）
    $last_vote_time = kashiwazaki_poll_get_last_updated_time( $data['poll_id'] );
    $modified_date = $last_vote_time > 0 ? $last_vote_time : $data['file_mtime'];
    echo '<meta itemprop="dateModified" content="' . esc_attr(wp_date('Y-m-d', $modified_date)) . '">' . "\n";

    // バージョン
    $version = kashiwazaki_poll_decode_stored_text( get_post_meta( $data['poll_id'], 'dataset_version', true ) );
    if (empty($version)) {
        $version = '1.0';
    }
    echo '<meta itemprop="version" content="' . esc_attr($version) . '">' . "\n";
}




function kashiwazaki_poll_get_shortcode_usage($poll_id, $include_unpublished = false) {
    // 1時間キャッシュを確認（キャッシュ時間を短縮）。公開/全件でキャッシュキーを分け、
    // 公開側に未公開投稿が混ざらないようにする。
    // 公開側のキーは、一般公開されていない投稿タイプを除くようにした版で作り直す（古いキャッシュを使わない）。
    $cache_key = 'poll_shortcode_usage_' . $poll_id . ( $include_unpublished ? '_all' : '_pubv' );
    $cached_result = get_transient($cache_key);

    if ($cached_result !== false) {
        return $cached_result;
    }

    // より幅広い投稿タイプを対象とする
    $all_post_types = get_post_types(array(), 'names');
    // pollとrevisionは除外
    $excluded_types = array('revision', 'nav_menu_item', 'attachment', 'poll');
    $all_post_types = array_diff($all_post_types, $excluded_types);
    // 公開側の表示では、訪問者が見られる投稿タイプだけを対象にする（パターン（wp_block）や非公開の
    // カスタム投稿タイプの内部タイトルを、データセットページの「掲載中のページ」に出さない）。
    if ( ! $include_unpublished ) {
        $all_post_types = array_filter( $all_post_types, 'is_post_type_viewable' );
        if ( empty( $all_post_types ) ) {
            set_transient( $cache_key, array(), HOUR_IN_SECONDS );
            return array();
        }
    }

    global $wpdb;

    // より包括的なLIKE検索パターン
    $like_patterns = array(
        '%[tk_poll id="' . $poll_id . '"]%',
        "%[tk_poll id='" . $poll_id . "']%",
        '%[tk_poll id=' . $poll_id . ']%',
        '%[tk_poll id = "' . $poll_id . '"]%',
        "%[tk_poll id = '" . $poll_id . "']%",
        '%[tk_poll id = ' . $poll_id . ']%',
        // 空白やタブ、改行を含むパターン
        '%[tk_poll%id%"' . $poll_id . '"%',
        "%[tk_poll%id%'" . $poll_id . "'%",
        '%[tk_poll%id%' . $poll_id . '%',
        // 属性の順序が違うパターン
        '%id="' . $poll_id . '"%tk_poll%',
        "%id='" . $poll_id . "'%tk_poll%",
        '%id=' . $poll_id . '%tk_poll%'
    );

    $where_conditions = array();
    $prepare_values = array();

    foreach ($like_patterns as $pattern) {
        $where_conditions[] = 'post_content LIKE %s';
        $prepare_values[] = $pattern;
    }

    // 公開側の表示では publish のみ。管理画面コンテキスト($include_unpublished=true)でのみ
    // 未公開ステータスを含める（未公開投稿のタイトル/URLの一般公開を防ぐ）。
    $status_in = $include_unpublished ? "'publish', 'private', 'draft', 'pending'" : "'publish'";
    $posts_with_shortcode = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, post_title, post_type, post_content, post_status
         FROM {$wpdb->posts}
         WHERE post_status IN ($status_in)
         AND post_type IN ('" . implode("','", array_map('esc_sql', $all_post_types)) . "')
         AND (" . implode(' OR ', $where_conditions) . ")",
        ...$prepare_values
    ));

    // 正規表現による厳密なマッチングで再検証
    $valid_posts = array();
    foreach ($posts_with_shortcode as $post) {
        $content = $post->post_content;

        // WordPressのショートコード処理と同様のロジックを使用
        // do_shortcode関数の内部処理を参考にした、より精密なマッチング
        // id は完全一致で比べる（id="12" の検索で id="123" に一致しないように）。
        $quoted_id = preg_quote( (string) intval( $poll_id ), '/' );
        $shortcode_regex = '/\[tk_poll\b[^\]]*(?<![\w-])id\s*=\s*(?:"' . $quoted_id . '"|\'' . $quoted_id . '\'|' . $quoted_id . '(?![0-9]))[^\]]*\]/i';

        $matches = array();
        $count = preg_match_all($shortcode_regex, $content, $matches);

        if ($count > 0) {
            $post->shortcode_count = $count;
            $valid_posts[] = $post;
        }
    }

    // 結果を1時間キャッシュ（短縮）
    set_transient($cache_key, $valid_posts, HOUR_IN_SECONDS);

    return $valid_posts;
}

// ショートコード使用状況キャッシュをクリアする関数
function kashiwazaki_poll_clear_usage_cache($poll_id = null) {
    if ($poll_id) {
        // 特定のアンケートのキャッシュをクリア（公開/全件の両キー）
        delete_transient('poll_shortcode_usage_' . $poll_id . '_pubv');
        delete_transient('poll_shortcode_usage_' . $poll_id . '_pub'); // 旧キー
        delete_transient('poll_shortcode_usage_' . $poll_id . '_all');
        delete_transient('poll_shortcode_usage_' . $poll_id); // 旧キー互換
    } else {
        // 全アンケートのキャッシュをクリア
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_poll_shortcode_usage_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_poll_shortcode_usage_%'");
    }
}

// 管理画面でキャッシュクリアのアクションを処理
add_action('admin_init', 'kashiwazaki_poll_handle_cache_clear');
function kashiwazaki_poll_handle_cache_clear() {
    // 個別記事編集画面でのキャッシュクリア
    if (isset($_GET['clear_poll_usage_cache']) && isset($_GET['post']) &&
        isset($_GET['action']) && $_GET['action'] === 'edit' && current_user_can('edit_posts')) {

        $poll_id = intval($_GET['clear_poll_usage_cache']);
        if (!wp_verify_nonce(isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '', 'clear_poll_usage_cache_' . $poll_id)) {
            wp_die('セキュリティチェックに失敗しました。');
        }

        kashiwazaki_poll_clear_usage_cache($poll_id);

        // 編集画面にリダイレクト
        $redirect_url = admin_url('post.php?post=' . intval($_GET['post']) . '&action=edit&cache_cleared=1');
        wp_redirect($redirect_url);
        exit;
    }
    if (isset($_GET['action']) && $_GET['action'] === 'clear_poll_usage_cache' &&
        isset($_GET['poll_id']) && current_user_can('manage_options')) {

        if (!wp_verify_nonce(isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '', 'clear_poll_usage_cache_' . $_GET['poll_id'])) {
            wp_die('セキュリティチェックに失敗しました。');
        }

        $poll_id = intval($_GET['poll_id']);
        kashiwazaki_poll_clear_usage_cache($poll_id);

        // リダイレクト先を決定
        $redirect_url = admin_url('edit.php?post_type=poll');
        if (isset($_GET['return_url'])) {
            $redirect_url = esc_url_raw($_GET['return_url']);
        }

        // wp_safe_redirect: return_url が外部ドメインを指す場合のオープンリダイレクトを防ぐ。
        wp_safe_redirect(add_query_arg(array('cache_cleared' => '1'), $redirect_url));
        exit;
    }

    // 全キャッシュクリア
    if (isset($_GET['action']) && $_GET['action'] === 'clear_all_poll_usage_cache' &&
        current_user_can('manage_options')) {

        if (!wp_verify_nonce(isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '', 'clear_all_poll_usage_cache')) {
            wp_die('セキュリティチェックに失敗しました。');
        }

        kashiwazaki_poll_clear_usage_cache();

        $redirect_url = admin_url('edit.php?post_type=poll');
        wp_redirect(add_query_arg(array('all_cache_cleared' => '1'), $redirect_url));
        exit;
    }
}


function kashiwazaki_poll_render_shortcode_usage($poll_id, $show_border = true) {
    $posts_with_shortcode = kashiwazaki_poll_get_shortcode_usage($poll_id);

    $border_class = $show_border ? 'with-border' : 'without-border';
    ?>
    <?php if (!empty($posts_with_shortcode)): ?>
    <div class="shortcode-usage <?php echo $border_class; ?>">
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <span class="usage-label">掲載中のページ</span>
            <?php foreach ($posts_with_shortcode as $index => $usage_post):
                $usage_url = get_permalink($usage_post->ID);
                $usage_title = $usage_post->post_title;

                // 投稿タイプの日本語ラベルを取得
                $post_type_object = get_post_type_object($usage_post->post_type);
                $post_type_label = $post_type_object ? $post_type_object->labels->singular_name : $usage_post->post_type;

                // 主要な投稿タイプの日本語化
                $type_translations = array(
                    'post' => '投稿',
                    'page' => 'ページ',
                    'poll' => 'Poll'
                );

                if (isset($type_translations[$usage_post->post_type])) {
                    $post_type_label = $type_translations[$usage_post->post_type];
                }
            ?>
                <a href="<?php echo esc_url($usage_url); ?>" class="usage-link">
                    <?php echo esc_html($usage_title); ?><span class="post-type-label"><?php echo esc_html($post_type_label); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php
}

function kashiwazaki_poll_render_format_listing_page($format_type) {
    // カラーテーマ設定を取得
    $theme_data = kashiwazaki_poll_get_color_theme();
    $color_theme = $theme_data['name'];
    $current_theme = $theme_data['colors'];

    status_header(200);
    nocache_headers();

    // フォーマット名の表示用変換
    $format_names = array(
        'csv' => 'CSV',
        'xml' => 'XML',
        'yaml' => 'YAML',
        'json' => 'JSON',
        'svg' => 'SVG'
    );

    $format_display_name = $format_names[$format_type] ?? strtoupper($format_type);
    $page_title = $format_display_name . ' ' . kashiwazaki_poll_get_dataset_page_title();

    // SEOメタデータをwp_headフックで出力
    add_action('wp_head', function() use ($page_title, $format_display_name, $format_type, $current_theme, $color_theme) {
        ?>
        <title><?php echo esc_html($page_title); ?> - <?php echo esc_html(get_bloginfo('name')); ?></title>
        <meta name="description" content="<?php echo esc_attr($format_display_name); ?>形式の集計データ一覧です。当サイトで実施された調査の<?php echo esc_attr($format_display_name); ?>データセットを閲覧・ダウンロードできます。">
        <meta name="robots" content="index, follow">
        <link rel="canonical" href="<?php echo esc_url(home_url("/datasets/{$format_type}/")); ?>">

        <!-- Google Datasets用meta情報 -->
        <?php kashiwazaki_poll_output_google_datasets_meta('format_listing', array('format' => $format_type)); ?>

        <!-- パンくず構造化データ -->
        <?php
        // パンくず構造化データ
        $breadcrumbs_for_json = array(
            array('name' => get_bloginfo('name'), 'url' => home_url()),
            array('name' => kashiwazaki_poll_get_dataset_page_title(), 'url' => home_url('/datasets/')),
            array('name' => $format_display_name, 'url' => home_url("/datasets/{$format_type}/"))
        );
        kashiwazaki_poll_output_breadcrumb_structured_data($breadcrumbs_for_json);
        ?>

        <style>
        .kashiwazaki-datasets-page {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .kashiwazaki-datasets-page h1 {
            border-bottom: 3px solid <?php echo $current_theme['accent_color']; ?>;
            padding-bottom: 10px;
        }
        .kashiwazaki-datasets-page .poll-item {
            background: <?php echo $color_theme === 'dark' ? '#34495e' : '#ffffff'; ?>;
            border: 1px solid <?php echo $current_theme['accent_color']; ?>;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .kashiwazaki-datasets-page .poll-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            gap: 15px;
        }
        .kashiwazaki-datasets-page .poll-item h2 {
            margin: 0;
            font-size: 1.2rem;
            flex: 1;
        }
        .kashiwazaki-datasets-page .last-updated-tag {
            background-color: <?php echo $current_theme['accent_color']; ?>;
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 500;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .kashiwazaki-datasets-page .last-updated-tag.no-votes {
        background-color: <?php echo $current_theme['tag_bg']; ?>;
        color: white;
    }
    .kashiwazaki-datasets-page .file-links {
        margin-top: 15px;
    }
    .kashiwazaki-datasets-page .formats-label {
        font-size: 0.9rem;
        color: <?php echo $current_theme['body_color']; ?>;
        margin: 10px 0 8px 0;
        font-weight: 500;
        opacity: 0.7;
    }
    .kashiwazaki-datasets-page .format-links {
        line-height: 1.8;
    }
    .kashiwazaki-datasets-page .format-link {
        color: <?php echo $current_theme['link_color']; ?>;
        text-decoration: underline;
        transition: all 0.2s ease;
        display: inline;
        padding: 2px 4px;
        border-radius: 3px;
    }
    .kashiwazaki-datasets-page .format-link:hover {
        background-color: <?php echo $current_theme['button_primary']; ?>;
        color: white;
        text-decoration: none;
    }
    .datasets-pagination {
        text-align: center;
        margin: 30px 0;
        clear: both;
    }
    .datasets-pagination .pagination-container {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .datasets-pagination .pagination-numbers {
        display: flex;
        gap: 5px;
        align-items: center;
    }
    .datasets-pagination .pagination-link,
    .datasets-pagination .pagination-current,
    .datasets-pagination .pagination-ellipsis {
        display: inline-block;
        padding: 8px 12px;
        text-decoration: none;
        border: 1px solid <?php echo $current_theme['accent_color']; ?>;
        border-radius: 4px;
        transition: all 0.2s ease;
        background-color: <?php echo $color_theme === 'dark' ? $current_theme['header_bg'] : '#ffffff'; ?>;
        color: <?php echo $current_theme['body_color']; ?>;
        min-width: 40px;
        text-align: center;
    }
    .datasets-pagination .pagination-link:hover {
        background-color: <?php echo $current_theme['accent_color']; ?>;
        color: white;
    }
    .datasets-pagination .pagination-current {
        background-color: <?php echo $current_theme['accent_color']; ?>;
        color: white;
        font-weight: bold;
    }
    .datasets-pagination .pagination-ellipsis {
        border: none;
        background: none;
    }
    .datasets-pagination .pagination-prev,
    .datasets-pagination .pagination-next {
        padding: 8px 16px;
    }
    .kashiwazaki-datasets-page .back-to-home {
        text-align: center;
        margin: 30px 0;
        padding: 20px 0;
        border-top: 1px solid <?php echo $current_theme['border_color']; ?>;
        clear: both;
    }
    .kashiwazaki-datasets-page .back-to-home a {
        color: <?php echo $current_theme['link_color']; ?>;
        text-decoration: none;
        margin: 0 10px;
    }
    .kashiwazaki-datasets-page .back-to-home a:hover {
        text-decoration: underline;
    }
    </style>
    <?php
    });

    // ページタイトルをフィルター
    add_filter('pre_get_document_title', function() use ($page_title) {
        return $page_title . ' - ' . get_bloginfo('name');
    });

    // ページネーション設定
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
    $posts_per_page = 10;

    // 全投稿を取得して最新投票時刻順にソート
    $all_polls = get_posts(array(
        'post_type'      => 'poll',
        'post_status'    => 'publish',
        'has_password'   => false, // パスワード保護されたデータセットは載せない
        'numberposts'    => -1, // 全投稿を取得
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ));

    // 当該フォーマットのデータファイルを持つpollのみに限定する。
    // （件数・ページ数を実表示対象と一致させ、ファイル無pollによるページングのズレを防ぐ。
    //  表示ループ側の file_exists スキップより前にここで除外する。）
    $all_polls = array_values( array_filter( $all_polls, function( $p ) use ( $format_type ) {
        $fp = kashiwazaki_poll_get_dataset_file_path( $p->ID, $format_type );
        return $fp && file_exists( $fp );
    } ) );

    // 最新投票時刻順にソート
    usort($all_polls, function($a, $b) {
        $last_vote_a = kashiwazaki_poll_get_last_updated_time( $a->ID );
        $last_vote_b = kashiwazaki_poll_get_last_updated_time( $b->ID );

        // 降順（最新順）
        return $last_vote_b - $last_vote_a;
    });

    // 総投稿数を取得
    $total_polls = count($all_polls);

    // ページネーション用にスライス
    $offset = ($paged - 1) * $posts_per_page;
    $polls = array_slice($all_polls, $offset, $posts_per_page);

    // $wp_query を完全に設定（他のプラグイン・テーマとの互換性のため）
    global $wp_query;

    // 投稿データ
    $wp_query->posts = $polls;
    $wp_query->post_count = count($polls);
    $wp_query->found_posts = $total_polls;
    $wp_query->max_num_pages = ceil($total_polls / $posts_per_page);

    // クエリ変数
    $wp_query->query_vars['post_type'] = 'poll';
    $wp_query->query_vars['paged'] = $paged;
    $wp_query->query_vars['posts_per_page'] = $posts_per_page;

    // クエリ対象オブジェクト
    $wp_query->queried_object = get_post_type_object('poll');
    $wp_query->queried_object_id = 0;

    // ループ制御
    $wp_query->current_post = -1;
    $wp_query->in_the_loop = false;

    // ページング状態
    $wp_query->is_paged = ($paged > 1);

    // クエリ配列
    $wp_query->query = array(
        'post_type' => 'poll',
        'paged' => $paged
    );

    // 条件フラグ
    $wp_query->is_archive = true;
    $wp_query->is_post_type_archive = true;
    $wp_query->is_singular = false;
    $wp_query->is_single = false;
    $wp_query->is_page = false;
    $wp_query->is_home = false;
    $wp_query->is_front_page = false;

    get_header();
    ?>

    <?php if (function_exists('kspb_display_breadcrumbs')) : kspb_display_breadcrumbs(); endif; ?>

    <div class="kashiwazaki-datasets-page">
        <div class="container">
            <h1><?php echo esc_html($page_title); ?></h1>
            <p><?php echo esc_html($format_display_name); ?>形式の集計データ一覧です。以下のデータセットを<?php echo esc_html($format_display_name); ?>形式で閲覧・ダウンロードできます。</p>
            <?php

            if ( $polls ) : ?>
                <div class="polls-list">
                    <?php foreach ( $polls as $poll ) :
                        $poll_id = $poll->ID;
                        $poll_title = $poll->post_title;
                        $poll_description = kashiwazaki_poll_decode_stored_text( get_post_meta( $poll_id, '_kashiwazaki_poll_description', true ) );

                        // 該当フォーマットのファイルが存在するかチェック
                        $file_path = kashiwazaki_poll_get_dataset_file_path($poll_id, $format_type);
                        if (!$file_path || !file_exists($file_path)) {
                            continue; // ファイルが存在しない場合はスキップ
                        }
                        ?>
                        <div class="poll-item">
                            <div class="poll-header">
                                <h2><?php echo esc_html( $poll_title ); ?></h2>
                                <?php
                                // 最終更新日（最新投票時刻）を取得
                                $last_vote_time = kashiwazaki_poll_get_last_updated_time( $poll_id );
                                ?>
                                <?php if ($last_vote_time > 0): ?>
                                    <span class="last-updated-tag">最終更新: <?php echo wp_date('Y/m/d H:i', $last_vote_time); ?></span>
                                <?php else: ?>
                                    <span class="last-updated-tag no-votes">投票待ち</span>
                                <?php endif; ?>
                            </div>
                            <?php if ( ! empty( $poll_description ) ): ?>
                                <p><?php echo esc_html( strip_tags( $poll_description ) ); ?></p>
                            <?php endif; ?>

                            <div class="file-links">
                                <div class="formats-label"><?php echo esc_html($format_display_name); ?>データ:</div>
                                <div class="format-links">
                                    <a href="<?php echo esc_url(kashiwazaki_poll_get_single_dataset_page_url($poll_id, $format_type)); ?>"
                                       class="format-link"><?php echo esc_html($format_display_name); ?></a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php
                // ページネーション表示
                if ($total_polls > $posts_per_page) {
                    $max_pages = ceil($total_polls / $posts_per_page);
                    kashiwazaki_poll_render_unified_pagination($paged, $max_pages, home_url("/datasets/{$format_type}/"), $current_theme);
                }
                ?>

            <?php else: ?>
                <p class="no-polls"><?php echo esc_html($format_display_name); ?>形式のデータはありません。</p>
            <?php endif; ?>
            <div class="back-to-home">
                <a href="<?php echo esc_url( home_url('/datasets/') ); ?>"><?php echo esc_html(kashiwazaki_poll_get_dataset_page_title()); ?>に戻る</a> |
                <a href="<?php echo esc_url( home_url() ); ?>">ホームに戻る</a>
            </div>
        </div>
    </div>

    <?php get_footer(); ?>
<?php
}



function kashiwazaki_poll_render_datasets_index_page() {
    status_header(200);
    nocache_headers();

    // SEOメタデータをwp_headフックで出力
    add_action('wp_head', 'kashiwazaki_poll_output_datasets_head_meta');

    // ページタイトルをフィルター
    add_filter('pre_get_document_title', function() {
        return kashiwazaki_poll_get_dataset_page_title() . ' - ' . get_bloginfo('name');
    });

    // ページネーション設定
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
    $posts_per_page = 10;

    // 全投稿を取得して最新投票時刻順にソート
    $all_polls = get_posts(array(
        'post_type'      => 'poll',
        'post_status'    => 'publish',
        'has_password'   => false, // パスワード保護されたデータセットは載せない
        'numberposts'    => -1,
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ));

    // 最新投票時刻順にソート
    usort($all_polls, function($a, $b) {
        $last_vote_a = kashiwazaki_poll_get_last_updated_time( $a->ID );
        $last_vote_b = kashiwazaki_poll_get_last_updated_time( $b->ID );

        return $last_vote_b - $last_vote_a;
    });

    $total_polls = count($all_polls);
    $offset = ($paged - 1) * $posts_per_page;
    $polls = array_slice($all_polls, $offset, $posts_per_page);

    // $wp_query を完全に設定（他のプラグイン・テーマとの互換性のため）
    global $wp_query;

    // 投稿データ
    $wp_query->posts = $polls;
    $wp_query->post_count = count($polls);
    $wp_query->found_posts = $total_polls;
    $wp_query->max_num_pages = ceil($total_polls / $posts_per_page);

    // クエリ変数
    $wp_query->query_vars['post_type'] = 'poll';
    $wp_query->query_vars['paged'] = $paged;
    $wp_query->query_vars['posts_per_page'] = $posts_per_page;

    // クエリ対象オブジェクト
    $wp_query->queried_object = get_post_type_object('poll');
    $wp_query->queried_object_id = 0;

    // ループ制御
    $wp_query->current_post = -1;
    $wp_query->in_the_loop = false;

    // ページング状態
    $wp_query->is_paged = ($paged > 1);

    // クエリ配列
    $wp_query->query = array(
        'post_type' => 'poll',
        'paged' => $paged
    );

    // 条件フラグ
    $wp_query->is_archive = true;
    $wp_query->is_post_type_archive = true;
    $wp_query->is_singular = false;
    $wp_query->is_single = false;
    $wp_query->is_page = false;
    $wp_query->is_home = false;
    $wp_query->is_front_page = false;

    get_header();
    ?>

    <?php if (function_exists('kspb_display_breadcrumbs')) : kspb_display_breadcrumbs(); endif; ?>

    <article class="page type-page status-publish hentry datasets-archive-page">
        <header class="entry-header">
            <h1 class="entry-title"><?php echo esc_html(kashiwazaki_poll_get_dataset_page_title()); ?></h1>
        </header>

        <div class="entry-content">
            <p><?php esc_html_e('以下は、当サイトで実施された調査の集計データセットです。各データをクリックすると、詳細なデータが様々な形式で閲覧・ダウンロードできます。', 'kashiwazaki-seo-poll'); ?></p>

            <?php

            if ( $polls ) : ?>
                <div class="polls-list dataset-polls-grid">
                    <?php foreach ( $polls as $poll ) :
                        $poll_title = esc_html( $poll->post_title );
                        $poll_id = $poll->ID;
                        $poll_description = kashiwazaki_poll_decode_stored_text( get_post_meta( $poll_id, '_kashiwazaki_poll_description', true ) );
                    ?>
                        <div class="poll-item dataset-poll-card">
                            <div class="poll-header">
                                <h2><a href="<?php echo esc_url( get_permalink($poll_id) ); ?>"><?php echo $poll_title; ?></a></h2>
                                <?php
                                $last_vote_time = kashiwazaki_poll_get_last_updated_time( $poll_id );
                                ?>
                                <?php if ($last_vote_time > 0): ?>
                                    <span class="last-updated-tag meta-badge date-badge">最終更新: <?php echo wp_date('Y/m/d H:i', $last_vote_time); ?></span>
                                <?php else: ?>
                                    <span class="last-updated-tag meta-badge no-votes">投票待ち</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($poll_description): ?>
                                <p><?php echo esc_html($poll_description); ?></p>
                            <?php endif; ?>
                            <div class="file-links">
                                <?php
                                $file_types = ['csv', 'xml', 'yaml', 'json', 'svg'];
                                $file_type_names = array(
                                    'csv' => 'CSV',
                                    'xml' => 'XML',
                                    'yaml' => 'YAML',
                                    'json' => 'JSON',
                                    'svg' => 'SVG'
                                );
                                $file_type_descriptions = array(
                                    'csv' => 'Excel対応の表形式データ',
                                    'xml' => '構造化マークアップデータ',
                                    'yaml' => '人間が読みやすい設定形式',
                                    'json' => 'API連携用軽量データ',
                                    'svg' => 'ベクターグラフ画像'
                                );

                                $available_formats = array();
                                foreach ($file_types as $type) {
                                    $file_path = kashiwazaki_poll_get_dataset_file_path($poll_id, $type);
                                    if (file_exists($file_path)) {
                                        $available_formats[] = $type;
                                    }
                                }

                                if (!empty($available_formats)) {
                                    echo '<p class="formats-label"><strong>利用可能な形式:</strong></p>';
                                    echo '<div class="format-links">';
                                    foreach ($available_formats as $index => $type) {
                                        $display_name = isset($file_type_names[$type]) ? $file_type_names[$type] : strtoupper($type);
                                        $description = isset($file_type_descriptions[$type]) ? $file_type_descriptions[$type] : '';
                                        echo '<a href="' . esc_url( kashiwazaki_poll_get_single_dataset_page_url($poll_id, $type) ) . '" class="format-link">';
                                        echo '<span class="format-name">' . esc_html($display_name) . '</span>';
                                        if ($description) {
                                            echo '<span class="format-desc">（' . esc_html($description) . '）</span>';
                                        }
                                        echo '</a>';
                                        if ($index < count($available_formats) - 1) {
                                            echo '<span class="format-separator"> ・ </span>';
                                        }
                                    }
                                    echo '</div>';
                                }
                                ?>
                            </div>

                            <?php kashiwazaki_poll_render_shortcode_usage($poll_id, true); ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php
                if ($total_polls > $posts_per_page) {
                    $max_pages = ceil($total_polls / $posts_per_page);
                    $current_page = max(1, $paged);
                    $current_url = home_url('/datasets/');

                    echo '<nav class="navigation pagination" aria-label="Datasets pagination">';
                    echo '<h2 class="screen-reader-text">Datasets pagination</h2>';
                    echo '<div class="nav-links">';

                    $pagination_args = array(
                        'base' => $current_url . '%_%',
                        'format' => 'page-%#%/',
                        'current' => $current_page,
                        'total' => $max_pages,
                        'prev_text' => __('前のページ', 'kashiwazaki-seo-poll'),
                        'next_text' => __('次のページ', 'kashiwazaki-seo-poll'),
                        'mid_size' => 2,
                        'end_size' => 1,
                        'add_args' => false,
                    );

                    echo paginate_links($pagination_args);
                    echo '</div></nav>';
                }
                ?>

            <?php else: ?>
                <p class="no-polls"><?php esc_html_e( '登録されているデータはありません。', 'kashiwazaki-seo-poll' ); ?></p>
            <?php endif; ?>

            <div class="back-to-home" style="text-align: center; margin-top: 30px;">
                <a href="<?php echo esc_url( home_url() ); ?>" class="button"><?php echo esc_html( get_bloginfo('name') ); ?>に戻る</a>
            </div>
        </div>
    </article>

    <?php
    // 基本設定の「カラーテーマ」を一覧ページにも効かせる（下のスタイルが使う色をここで決める）。
    $index_theme = kashiwazaki_poll_get_color_theme();
    $index_colors = $index_theme['colors'];
    $index_is_dark = ( 'dark' === $index_theme['name'] );
    ?>
    <style>
    .datasets-archive-page {
        --border-color: <?php echo esc_html( $index_colors['border_color'] ); ?>;
        --card-background: <?php echo esc_html( $index_colors['body_bg'] ); ?>;
        --link-color: <?php echo esc_html( $index_colors['link_color'] ); ?>;
        --link-hover-color: <?php echo esc_html( $index_colors['accent_color'] ); ?>;
        --text-color: <?php echo esc_html( $index_colors['body_color'] ); ?>;
        --text-color-secondary: <?php echo $index_is_dark ? '#bdc3c7' : '#666666'; ?>;
        --button-background: <?php echo esc_html( $index_colors['button_secondary'] ); ?>;
        --button-hover-background: <?php echo esc_html( $index_colors['border_color'] ); ?>;
        --text-on-accent: #ffffff;
    }
    </style>
    <style>
    /* データセット一覧ページ専用スタイル */
    .dataset-polls-grid {
        display: grid;
        gap: 24px;
        margin: 30px 0;
    }

    .dataset-polls-grid .poll-item {
        padding: 24px;
        border: 1px solid;
        border-color: var(--border-color);
        border-radius: 8px;
        background: var(--card-background);
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        transition: box-shadow 0.2s ease, transform 0.2s ease;
    }

    .dataset-polls-grid .poll-item:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }

    /* カードヘッダー */
    .dataset-polls-grid .poll-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid;
        border-color: var(--border-color);
    }

    .dataset-polls-grid .poll-header h2 {
        margin: 0;
        font-size: 1.25rem;
        line-height: 1.4;
        flex: 1;
        font-weight: 600;
    }

    .dataset-polls-grid .poll-header h2 a {
        color: var(--link-color);
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .dataset-polls-grid .poll-header h2 a:hover {
        color: var(--link-hover-color);
        text-decoration: underline;
    }

    .dataset-polls-grid .last-updated-tag {
        flex-shrink: 0;
        font-size: 0.8rem;
        white-space: nowrap;
        align-self: flex-start;
    }

    /* アンケート説明文 */
    .dataset-polls-grid .poll-item > p {
        margin: 0 0 16px 0;
        line-height: 1.6;
        color: var(--text-color-secondary);
    }

    /* ファイル形式リンク */
    .dataset-polls-grid .file-links {
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid;
        border-color: var(--border-color);
    }

    .dataset-polls-grid .formats-label {
        margin: 0 0 10px 0;
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-color);
    }

    .dataset-polls-grid .format-links {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        line-height: 1.6;
    }

    .dataset-polls-grid .format-link {
        display: inline-flex;
        align-items: baseline;
        gap: 4px;
        padding: 6px 12px;
        border: 1px solid;
        border-color: var(--border-color);
        border-radius: 4px;
        background: var(--button-background);
        text-decoration: none;
        transition: all 0.2s ease;
        font-size: 0.9rem;
    }

    .dataset-polls-grid .format-link:hover {
        background: var(--button-hover-background);
        border-color: var(--link-color);
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .dataset-polls-grid .format-name {
        font-weight: 700;
        color: var(--link-color);
    }

    .dataset-polls-grid .format-desc {
        font-size: 0.85em;
        color: var(--text-color-secondary);
    }

    .dataset-polls-grid .format-separator {
        display: none;
    }

    /* ショートコード使用状況 */
    .dataset-polls-grid .shortcode-usage {
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid;
        border-color: var(--border-color);
    }

    .dataset-polls-grid .usage-label {
        margin: 0 0 10px 0;
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-color);
    }

    .dataset-polls-grid .usage-links {
        line-height: 1.8;
    }

    .dataset-polls-grid .usage-link {
        text-decoration: underline;
        color: var(--link-color);
    }

    .dataset-polls-grid .usage-link:hover {
        text-decoration: none;
    }

    .dataset-polls-grid .post-type-label {
        font-size: 0.85em;
        color: var(--text-color-secondary);
    }

    .dataset-polls-grid .usage-separator {
        margin: 0 6px;
        color: var(--text-color-secondary);
    }

    .dataset-polls-grid .no-usage {
        font-style: italic;
        color: var(--text-color-secondary);
    }

    /* ページネーション */
    .datasets-archive-page .navigation.pagination {
        margin: 40px 0 30px 0;
        clear: both;
    }

    .datasets-archive-page .pagination .nav-links {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .datasets-archive-page .pagination .nav-links a,
    .datasets-archive-page .pagination .nav-links .current {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 40px;
        min-height: 40px;
        padding: 8px 12px;
        border: 1px solid;
        border-color: var(--border-color);
        border-radius: 4px;
        background: var(--button-background);
        text-decoration: none;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .datasets-archive-page .pagination .nav-links a:hover {
        background: var(--button-hover-background);
        border-color: var(--link-color);
        color: var(--link-color);
        transform: translateY(-1px);
    }

    .datasets-archive-page .pagination .nav-links .current {
        background: var(--link-color);
        color: var(--text-on-accent);
        border-color: var(--link-color);
        cursor: default;
        font-weight: 600;
    }

    .datasets-archive-page .pagination .nav-links .prev,
    .datasets-archive-page .pagination .nav-links .next {
        font-weight: 600;
        padding: 8px 16px;
    }

    .datasets-archive-page .pagination .nav-links .dots {
        padding: 8px 4px;
        color: var(--text-color-secondary);
    }

    /* 投稿なし表示 */
    .datasets-archive-page .no-polls {
        padding: 40px 20px;
        text-align: center;
        background: var(--card-background);
        border-radius: 8px;
        color: var(--text-color);
    }

    /* サイトトップに戻るボタン */
    .datasets-archive-page .back-to-home {
        text-align: center;
        margin-top: 40px;
        padding-top: 30px;
        border-top: 1px solid;
        border-color: var(--border-color);
    }

    .datasets-archive-page .back-to-home a {
        display: inline-block;
        padding: 12px 24px;
        border: 1px solid;
        border-color: var(--border-color);
        border-radius: 4px;
        background: var(--button-background);
        text-decoration: none;
        font-weight: 600;
        transition: all 0.2s ease;
    }

    .datasets-archive-page .back-to-home a:hover {
        background: var(--button-hover-background);
        border-color: var(--link-color);
        transform: translateY(-1px);
    }

    /* レスポンシブ対応 */
    @media (max-width: 768px) {
        .dataset-polls-grid .poll-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }

        .dataset-polls-grid .last-updated-tag {
            align-self: flex-start;
        }

        .dataset-polls-grid .format-links {
            flex-direction: column;
            gap: 6px;
        }

        .dataset-polls-grid .format-link {
            width: 100%;
            justify-content: space-between;
        }

        .datasets-archive-page .pagination .nav-links {
            gap: 4px;
        }

        .datasets-archive-page .pagination .nav-links a,
        .datasets-archive-page .pagination .nav-links .current {
            min-width: 36px;
            min-height: 36px;
            padding: 6px 10px;
            font-size: 0.9rem;
        }
    }
    </style>

    <?php
    get_footer();
}

function kashiwazaki_poll_get_single_dataset_structured_data( $poll_id, $file_type ) {
    $poll_post = get_post( $poll_id );
    if ( ! $poll_post || $poll_post->post_type !== 'poll' ) {
        return [];
    }

    $question = kashiwazaki_poll_decode_stored_text( $poll_post->post_title );
    $poll_description = kashiwazaki_poll_decode_stored_text( get_post_meta( $poll_id, '_kashiwazaki_poll_description', true ) );
    $description_plain = strip_tags( $poll_description );
    $datePublished = get_the_date( 'c', $poll_post );

    // 形式別ページはそのファイルの更新時刻、詳細ページ（html）は集計の最終更新（最後の投票・投票数の編集）を使う。
    // どちらも無いときはデータセットの更新日時。ページを開いた時刻は使わない。
    $dataset_file_path = kashiwazaki_poll_get_dataset_file_path( $poll_id, $file_type );
    if ( $dataset_file_path && file_exists( $dataset_file_path ) ) {
        $last_updated_time = filemtime( $dataset_file_path );
    } else {
        $last_updated_time = kashiwazaki_poll_get_last_updated_time( $poll_id );
        if ( ! $last_updated_time ) {
            $last_updated_time = (int) get_post_modified_time( 'U', true, $poll_post );
        }
    }
    $dateModified = wp_date( 'c', $last_updated_time );

    // 調査期間を取得
    $survey_period = kashiwazaki_poll_get_survey_period( $poll_id );
    $survey_period_start = $survey_period ? wp_date( 'Y-m-d', $survey_period['start'] ) : null;
    $survey_period_end = $survey_period ? wp_date( 'Y-m-d', $survey_period['end'] ) : null;

    $poll_license = get_post_meta( $poll_id, '_kashiwazaki_poll_license', true );
    if ( empty( $poll_license ) ) {
        $poll_license = 'https://creativecommons.org/licenses/by/4.0/';
    }

    $site_organization_name = get_bloginfo('name');
    $site_organization_url = home_url();
    $site_admin_email = get_bloginfo('admin_email');

    // Creator情報を設定から取得
    $plugin_settings = get_option( 'kashiwazaki_poll_settings', array(
        'creator_type' => 'organization_only',
        'creator_person_name' => '',
        'creator_person_url' => '',
        'creator_organization_name' => get_bloginfo('name'),
        'creator_organization_url' => home_url(),
        'creator_organization_email' => ''
    ) );

    $creator_info = [];

    if ( $plugin_settings['creator_type'] === 'person_only' || $plugin_settings['creator_type'] === 'both' ) {
        $person_name = !empty($plugin_settings['creator_person_name']) ? $plugin_settings['creator_person_name'] : 'Unknown';
        $person_url = !empty($plugin_settings['creator_person_url']) ? $plugin_settings['creator_person_url'] : $site_organization_url;

        $creator_info[] = [
            "@type" => "Person",
            "name" => $person_name,
            "url" => $person_url
        ];
    }

    if ( $plugin_settings['creator_type'] === 'organization_only' || $plugin_settings['creator_type'] === 'both' ) {
        $org_name = !empty($plugin_settings['creator_organization_name']) ? $plugin_settings['creator_organization_name'] : $site_organization_name;
        $org_url = !empty($plugin_settings['creator_organization_url']) ? $plugin_settings['creator_organization_url'] : $site_organization_url;
        $creator_info[] = kashiwazaki_poll_ld_organization_creator( $org_name, $org_url );
    }

    $options_data = kashiwazaki_poll_get_display_options( $poll_id );
    $current_counts = get_post_meta( $poll_id, '_kashiwazaki_poll_counts', true );
    if ( ! is_array( $options_data ) ) { $options_data = []; }
    if ( ! is_array( $current_counts ) ) { $current_counts = array_fill( 0, count( $options_data ), 0 ); }
    elseif ( count( $current_counts ) < count( $options_data ) ) { $current_counts = array_pad( $current_counts, count( $options_data ), 0 ); }
    elseif ( count( $current_counts ) > count( $options_data ) ) { $current_counts = array_slice( $current_counts, 0, count( $options_data ) ); }

    $variableMeasured = [];
    foreach ( $options_data as $i => $opt ) {
        $value = isset( $current_counts[$i] ) ? intval($current_counts[$i]) : 0;
        $variableMeasured[] = [
            "@type" => "PropertyValue",
            "name" => $opt,
            "value" => $value
        ];
    }

    $keywords = [];
    // 個別に設定されたキーワードのみ使用
    $poll_keywords = kashiwazaki_poll_decode_stored_text( get_post_meta( $poll_id, 'dataset_keywords', true ) );
    if (!empty($poll_keywords)) {
        $custom_keywords = array_map('trim', explode(',', $poll_keywords));
        $keywords = array_filter($custom_keywords, function($keyword) {
            return !empty(trim($keyword));
        });
    }

    // distribution: 該当フォーマットのみを記載
    $distribution = [];
    $file_formats_map = [
        'csv'  => ['format' => 'text/csv', 'name' => 'CSV'],
        'xml'  => ['format' => 'application/xml', 'name' => 'XML'],
        'yaml' => ['format' => 'application/x-yaml', 'name' => 'YAML'],
        'json' => ['format' => 'application/json', 'name' => 'JSON'],
        'svg'  => ['format' => 'image/svg+xml', 'name' => 'SVG'],
        'html' => ['format' => 'text/html', 'name' => 'HTML'],
    ];

    // 指定されたフォーマットのみを追加
    if (isset($file_formats_map[$file_type])) {
        $details = $file_formats_map[$file_type];

        if ($file_type === 'html') {
            // HTML個別ページの場合
            $distribution[] = [
                "@type" => "DataDownload",
                "name" => "Poll Data in " . $details['name'],
                "contentUrl" => get_permalink($poll_id),
                "encodingFormat" => $details['format']
            ];
        } else {
            // データファイルの場合
            $file_url = kashiwazaki_poll_get_dataset_file_url($poll_id, $file_type);
            if ( $file_url && file_exists(kashiwazaki_poll_get_dataset_file_path($poll_id, $file_type)) ) {
                $distribution[] = [
                    "@type" => "DataDownload",
                    "name" => "Poll Data in " . $details['name'],
                    "contentUrl" => $file_url,
                    "encodingFormat" => $details['format']
                ];
            }
        }
    }

    $publisher_info = kashiwazaki_poll_ld_publisher();

    $structured_data = [
        "@context" => "https://schema.org/",
        "@type" => "Dataset",
        "name" => $question . ' - ' . strtoupper($file_type) . '形式の集計データ',
        "description" => '「' . $question . '」の集計結果を' . strtoupper($file_type) . '形式で公開するデータセットです。各選択肢の得票数と割合が含まれており、詳細な分析にご利用いただけます。' . (!empty($description_plain) ? 'データの説明: ' . $description_plain : ''),
        "url" => kashiwazaki_poll_get_single_dataset_page_url($poll_id, $file_type),
        "identifier" => kashiwazaki_poll_get_single_dataset_page_url($poll_id, $file_type),
        "creator" => $creator_info,
        "publisher" => $publisher_info,
        "datePublished" => $datePublished,
        "dateModified" => $dateModified,
        "license" => $poll_license,
        "isAccessibleForFree" => true,
        "spatialCoverage" => ( function() {
            $s = get_option( 'kashiwazaki_poll_settings', array() );
            return ( is_array( $s ) && ! empty( $s['dataset_spatial_coverage'] ) ) ? $s['dataset_spatial_coverage'] : '日本';
        } )(),
        "temporalCoverage" => $survey_period_start && $survey_period_end ? $survey_period_start . "/" . $survey_period_end : $datePublished . "/" . $dateModified,
        "measurementTechnique" => "Survey polling",
        "version" => ( '' !== (string) kashiwazaki_poll_decode_stored_text( get_post_meta( $poll_id, 'dataset_version', true ) ) ) ? (string) kashiwazaki_poll_decode_stored_text( get_post_meta( $poll_id, 'dataset_version', true ) ) : '1.0',
        "includedInDataCatalog" => [
            "@type" => "DataCatalog",
            "name" => $site_organization_name . ' 調査データカタログ',
            "url" => kashiwazaki_poll_get_dataset_index_url()
        ],
        "variableMeasured" => $variableMeasured,
        "distribution" => $distribution,
    ];

    if ( ! empty( $keywords ) ) {
        $structured_data["keywords"] = array_unique($keywords);
    }

    $plugin_settings = get_option( 'kashiwazaki_poll_settings', array('structured_data_provider' => 1) );
    if ( isset($plugin_settings['structured_data_provider']) && $plugin_settings['structured_data_provider'] == 1 ) {
        $structured_data["provider"] = [
            "@type" => "Person",
            "name" => "柏崎剛",
            "url" => "https://www.tsuyoshikashiwazaki.jp/",
            "affiliation" => [
                "@type" => "Organization",
                "name" => "SEO対策研究室"
            ]
        ];
    }

    return $structured_data;
}

function kashiwazaki_poll_output_datasets_head_meta() {
    ?>
    <meta name="description" content="<?php esc_attr_e( 'このページでは、当サイトで公開している調査の集計データを一覧で閲覧できます。各データセットはCSV、XML、YAML、JSON、SVG形式で提供されています。', 'kashiwazaki-seo-poll' ); ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo esc_url( kashiwazaki_poll_get_dataset_index_url() ); ?>">

    <!-- Google Datasets用meta情報 -->
    <?php kashiwazaki_poll_output_google_datasets_meta('catalog'); ?>

    <!-- パンくず構造化データ -->
    <?php
    $breadcrumbs_for_json = array(
        array('name' => get_bloginfo('name'), 'url' => home_url()),
        array('name' => kashiwazaki_poll_get_dataset_page_title(), 'url' => kashiwazaki_poll_get_dataset_index_url())
    );
    kashiwazaki_poll_output_breadcrumb_structured_data($breadcrumbs_for_json);
    ?>

    <!-- 構造化データ：データカタログ -->
    <?php
    // 手で JSON を組み立てると引用符などで壊れるため、配列から wp_json_encode で出力する。
    // 更新日時はページを開いた時刻ではなく、データセットの最終更新日時を使う。
    $catalog_last_modified = get_lastpostmodified( 'gmt', 'poll' );
    $catalog = array(
        '@context'            => 'https://schema.org/',
        '@type'               => 'DataCatalog',
        'name'                => get_bloginfo( 'name' ) . ' 集計データカタログ',
        'description'         => '当サイトで公開している調査の集計データを一覧で閲覧できます。各データセットはCSV、XML、YAML、JSON、SVG形式で提供されています。',
        'url'                 => kashiwazaki_poll_get_dataset_index_url(),
        'publisher'           => kashiwazaki_poll_ld_publisher(),
        'license'             => 'https://creativecommons.org/licenses/by/4.0/',
        'dateModified'        => $catalog_last_modified ? gmdate( 'c', strtotime( $catalog_last_modified . ' UTC' ) ) : gmdate( 'c' ),
        'isAccessibleForFree' => true,
        'inLanguage'          => get_locale(),
    );
    echo '<script type="application/ld+json">' . wp_json_encode( $catalog, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>' . "\n";
}

function kashiwazaki_poll_render_single_dataset_page( $poll_id, $file_type ) {
    $poll_id = intval($poll_id);
    $poll_post = get_post($poll_id);

    if ( ! kashiwazaki_poll_is_public_poll( $poll_post ) ) {
        wp_die('指定されたデータが見つかりません。', '404 Not Found', array('response' => 404));
    }

    // 動的なページデータを準備
    $page_data = kashiwazaki_poll_prepare_dataset_page_data($poll_id, $file_type);

    if (!$page_data) {
        wp_die('指定されたデータセットが見つかりません。', '404 Not Found', array('response' => 404));
    }

    // 完全なHTMLページとして出力
    kashiwazaki_poll_output_standalone_dataset_page($page_data);
    exit;
}

function kashiwazaki_poll_prepare_dataset_page_data($poll_id, $file_type) {
    $poll_post = get_post($poll_id);
    $current_file_path = kashiwazaki_poll_get_dataset_file_path($poll_id, $file_type);

    if (!$current_file_path || !file_exists($current_file_path)) {
        return false;
    }

    $file_content = file_get_contents($current_file_path);
    $file_mtime = filemtime($current_file_path);

    $poll_title = kashiwazaki_poll_decode_stored_text( $poll_post->post_title );
    $poll_description = kashiwazaki_poll_decode_stored_text( get_post_meta( $poll_id, '_kashiwazaki_poll_description', true ) );
    $options_data = kashiwazaki_poll_get_display_options( $poll_id );
    $counts = get_post_meta($poll_id, '_kashiwazaki_poll_counts', true);

    // Normalize data
    if (!is_array($options_data)) $options_data = [];
    if (!is_array($counts)) $counts = array_fill(0, count($options_data), 0);
    elseif (count($counts) < count($options_data)) $counts = array_pad($counts, count($options_data), 0);
    elseif (count($counts) > count($options_data)) $counts = array_slice($counts, 0, count($options_data));

    $total_votes = array_sum($counts);

    // Generate breadcrumb data
    $site_name = get_bloginfo('name');
    $home_url = home_url();
    $datasets_url = home_url('/datasets/');
    $page_url = kashiwazaki_poll_get_single_dataset_page_url($poll_id, $file_type);

    $breadcrumb_data = array(
        "@context" => "https://schema.org",
        "@type" => "BreadcrumbList",
        "itemListElement" => array(
            array(
                "@type" => "ListItem",
                "position" => 1,
                "name" => $site_name,
                "item" => $home_url
            ),
            array(
                "@type" => "ListItem",
                "position" => 2,
                "name" => kashiwazaki_poll_get_dataset_page_title(),
                "item" => $datasets_url
            ),
            array(
                "@type" => "ListItem",
                "position" => 3,
                "name" => $poll_title . " - " . strtoupper($file_type) . " データセット",
                "item" => $page_url
            )
        )
    );

    // Generate dataset structured data
    $dataset_data = kashiwazaki_poll_get_single_dataset_structured_data($poll_id, $file_type);

    return array(
        'poll_id' => $poll_id,
        'poll_title' => $poll_title,
        'poll_description' => $poll_description,
        'file_type' => $file_type,
        'file_content' => $file_content,
        'file_mtime' => $file_mtime,
        'options_data' => $options_data,
        'counts' => $counts,
        'total_votes' => $total_votes,
        'breadcrumb_data' => $breadcrumb_data,
        'dataset_data' => $dataset_data,
        'page_url' => $page_url
    );
}

function kashiwazaki_poll_output_standalone_dataset_page($data) {
    $site_name = get_bloginfo('name');
    $file_ext_upper = strtoupper($data['file_type']);
    $page_title = $data['poll_title'] . ' - ' . $file_ext_upper . ' データセット';

    // カラーテーマ設定を取得
    $theme_data = kashiwazaki_poll_get_color_theme();
    $color_theme = $theme_data['name'];
    $current_theme = $theme_data['colors'];

    status_header(200);
    nocache_headers();

    // SEOメタデータをwp_headフックで出力
    add_action('wp_head', function() use ( $data, $page_title, $site_name, $color_theme, $current_theme ) {
        ?>
        <title><?php echo esc_html($page_title); ?> - <?php echo esc_html($site_name); ?></title>
        <meta name="description" content="<?php echo esc_attr($data['poll_description']); ?>">
        <?php
        // データセットキーワードをmeta keywordsとして出力
        $poll_keywords = kashiwazaki_poll_decode_stored_text( get_post_meta( $data['poll_id'], 'dataset_keywords', true ) );
        if (!empty($poll_keywords)) {
            $keywords_array = array_map('trim', explode(',', $poll_keywords));
            $keywords_array = array_filter($keywords_array); // 空の要素を除去
            if (!empty($keywords_array)) {
                echo '<meta name="keywords" content="' . esc_attr(implode(', ', $keywords_array)) . '">' . "\n";
            }
        }
        ?>
        <meta name="robots" content="index, follow">
        <?php
        // フォーマット別ページのcanonicalを中央のデータセット詳細ページに集約
        $canonical_url = home_url( "/datasets/detail-{$data['poll_id']}/" );
        ?>
        <link rel="canonical" href="<?php echo esc_url($canonical_url); ?>">

        <!-- Google Datasets用meta情報 -->
        <?php
        kashiwazaki_poll_output_google_datasets_meta('single_dataset', $data);
        kashiwazaki_poll_output_google_dataset_search_meta($data);
        ?>

        <!-- 構造化データ出力 -->
        <?php
        // パンくず構造化データ
        $format_names = array(
            'csv' => 'CSV',
            'xml' => 'XML',
            'yaml' => 'YAML',
            'json' => 'JSON',
            'svg' => 'SVG'
        );
        $format_display_name = $format_names[$data['file_type']] ?? strtoupper($data['file_type']);

        $breadcrumbs_for_json = array(
            array('name' => get_bloginfo('name'), 'url' => home_url()),
            array('name' => kashiwazaki_poll_get_dataset_page_title(), 'url' => home_url('/datasets/')),
            array('name' => $format_display_name, 'url' => home_url("/datasets/{$data['file_type']}/")),
            array('name' => $data['poll_title'], 'url' => '')
        );
        kashiwazaki_poll_output_breadcrumb_structured_data($breadcrumbs_for_json);
        ?>
        <script type="application/ld+json">
        <?php echo json_encode($data['dataset_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP); ?>
        </script>

        <!-- CSS -->
        <style>
        .kashiwazaki-dataset-page {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .dataset-meta {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #0073aa;
        }
        .svg-content {
            text-align: center;
            margin: 20px 0;
        }
        .dataset-actions {
            margin-top: 30px;
            text-align: center;
        }
        .download-btn, .back-btn {
            display: inline-block;
            margin: 0 8px;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        .download-btn {
            background: #0073aa;
            color: white;
            border: 2px solid #0073aa;
        }
        .download-btn:hover {
            background: transparent;
            color: #0073aa;
        }
        .back-btn {
            background: transparent;
            color: #333;
            border: 2px solid #0073aa;
        }
        .back-btn:hover {
            background: #0073aa;
            color: white;
        }
        pre {
            background: #f4f4f4;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            border: 1px solid #ddd;
        }
        .chart-container {
            margin: 20px 0;
            padding: 20px;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .kashiwazaki-poll-chart-container {
            height: 400px;
            position: relative;
        }
        .shortcode-usage {
            margin: 30px 0;
            padding: 20px;
            background: <?php echo $color_theme === 'dark' ? $current_theme['header_bg'] : '#ffffff'; ?>;
            border: 1px solid <?php echo $color_theme === 'dark' ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.08)'; ?>;
            border-radius: 8px;
        }
        .shortcode-usage.with-border {
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .usage-label {
            display: inline-block;
            margin: 0 16px 0 0;
            padding: 0;
            background: none;
            color: <?php echo $current_theme['body_color']; ?>;
            font-size: 0.9rem;
            font-weight: 700;
            white-space: nowrap;
            border-bottom: 2px solid <?php echo $current_theme['accent_color']; ?>;
            padding-bottom: 2px;
        }
        .usage-link {
            display: inline-block;
            margin: 0 8px 0 0;
            padding: 5px 0;
            color: <?php echo $current_theme['link_color']; ?>;
            text-decoration: none;
            font-size: 0.95rem;
            border-bottom: 1px solid transparent;
            transition: border-color 0.2s ease;
        }
        .usage-link:hover {
            border-bottom-color: <?php echo $current_theme['accent_color']; ?>;
        }
        .post-type-label {
            margin-left: 6px;
            color: <?php echo $current_theme['body_color']; ?>;
            opacity: 0.4;
            font-size: 0.8em;
        }
        @media (max-width: 768px) {
            .kashiwazaki-dataset-page {
                padding: 0 10px;
            }
            .download-btn, .back-btn {
                display: block;
                margin: 8px auto;
                width: auto;
                min-width: 160px;
                text-align: center;
            }
        }
        </style>
        <?php
    });

    // ページタイトルをフィルター
    add_filter('pre_get_document_title', function() use ($page_title, $site_name) {
        return $page_title . ' - ' . $site_name;
    });

    // グローバル変数とクエリオブジェクトを設定（他のプラグイン・テーマとの互換性のため）
    global $post, $wp_query;

    // Poll 投稿オブジェクトを取得
    $post = get_post($data['poll_id']);

    // WordPress クエリオブジェクトを正しく設定
    $wp_query->queried_object = $post;
    $wp_query->queried_object_id = $data['poll_id'];
    $wp_query->post = $post;

    // テンプレートタグ (the_title(), the_content() など) が正しく動作するように設定
    setup_postdata($post);

    // 条件フラグを設定
    $wp_query->is_singular = true;
    $wp_query->is_single = true;
    $wp_query->is_page = false;
    $wp_query->is_archive = false;
    $wp_query->is_post_type_archive = false;
    $wp_query->is_home = false;
    $wp_query->is_front_page = false;

    get_header();
    ?>

    <?php if (function_exists('kspb_display_breadcrumbs')) : kspb_display_breadcrumbs(); endif; ?>

        <div class="kashiwazaki-dataset-page">
            <h1><?php echo esc_html($data['poll_title']); ?> - <?php echo $file_ext_upper; ?> データセット</h1>
            <p><?php echo esc_html($data['poll_description']); ?></p>

            <div class="dataset-meta">
                <?php
                // 調査期間を取得
                $survey_period = kashiwazaki_poll_get_survey_period($data['poll_id']);
                if ($survey_period) {
                    $survey_start = wp_date('Y年m月d日', $survey_period['start']);
                    $survey_end = wp_date('Y年m月d日', $survey_period['end']);
                    echo '<p><strong>調査期間:</strong> ' . esc_html($survey_start) . '〜' . esc_html($survey_end) . '</p>';
                } else {
                    echo '<p><strong>調査期間:</strong> まだ投票がありません</p>';
                }
                ?>
                <p><strong>最終更新:</strong> <?php
                // 最新投票時刻を取得（リストページと同じロジック）
                $last_vote_time = kashiwazaki_poll_get_last_updated_time( $data['poll_id'] );

                if ($last_vote_time > 0) {
                    echo wp_date('Y/m/d H:i:s', $last_vote_time);
                } else {
                    echo '投票待ち';
                }
            ?></p>
                <p><strong>総投票数:</strong> <?php echo $data['total_votes']; ?>票</p>
                <?php if ( kashiwazaki_poll_is_locked( $data['poll_id'] ) ) : ?>
                <p><strong>投票ページ:</strong> 受付終了（<a href="<?php echo esc_url(get_permalink($data['poll_id'])); ?>" style="color: <?php echo esc_attr( $current_theme['link_color'] ); ?>;">結果を見る</a>）</p>
                <?php else : ?>
                <p><strong>投票ページ:</strong> <a href="<?php echo esc_url(get_permalink($data['poll_id'])); ?>" style="color: <?php echo esc_attr( $current_theme['link_color'] ); ?>;">投票する</a></p>
                <?php endif; ?>
            </div>

            <h2><?php echo $file_ext_upper; ?> データ</h2>
            <?php if ($data['file_type'] === 'svg'): ?>
                <div class="svg-content"><?php echo $data['file_content']; ?></div>
            <?php else: ?>
                <pre><?php echo esc_html($data['file_content']); ?></pre>
            <?php endif; ?>

            <?php kashiwazaki_poll_render_shortcode_usage($data['poll_id'], false); ?>

            <div class="dataset-actions">
                <?php
                // ファイル形式の簡潔な説明
                $format_downloads = array(
                    'csv' => 'CSV Download',
                    'xml' => 'XML Download',
                    'yaml' => 'YAML Download',
                    'json' => 'JSON Download',
                    'svg' => 'SVG Download'
                );
                $current_download = isset($format_downloads[$data['file_type']]) ? $format_downloads[$data['file_type']] : strtoupper($data['file_type']) . ' Download';
                ?>
                <a href="<?php echo esc_url(kashiwazaki_poll_get_dataset_file_url($data['poll_id'], $data['file_type'])); ?>"
                   download class="download-btn"><?php echo esc_html($current_download); ?></a>
                <a href="<?php echo esc_url(home_url('/datasets/' . $data['file_type'] . '/')); ?>" class="back-btn"><?php echo strtoupper($data['file_type']); ?>一覧に戻る</a>
            </div>
        </div>

        <?php
        get_footer();

        // グローバル変数をリセット（他のコードへの影響を防ぐ）
        wp_reset_postdata();
        ?>
    <?php
}
