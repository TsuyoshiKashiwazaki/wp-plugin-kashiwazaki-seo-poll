<?php
/**
 * Poll 投稿タイプの個別ページテンプレート
 *
 * @package Kashiwazaki_SEO_Poll
 */

// Google Dataset用の構造化データをwp_headに追加
add_action('wp_head', function() {
    $poll_id = get_the_ID();
    $poll_post = get_post($poll_id);

    if (!$poll_post || $poll_post->post_type !== 'poll') {
        return;
    }

    // 既存の構造化データ関数を活用
    if (function_exists('kashiwazaki_poll_get_single_dataset_structured_data')) {
        $structured_data = kashiwazaki_poll_get_single_dataset_structured_data($poll_id, 'html');
        if (!empty($structured_data)) {
            echo '<script type="application/ld+json">' . "\n";
            echo wp_json_encode($structured_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            echo '</script>' . "\n";
        }
    }

    // Dublin Core メタタグ
    $poll_description = get_post_meta($poll_id, '_kashiwazaki_poll_description', true);
    $poll_keywords = get_post_meta($poll_id, 'dataset_keywords', true);

    echo '<meta name="DC.title" content="' . esc_attr(get_the_title()) . '">' . "\n";
    echo '<meta name="DC.description" content="' . esc_attr($poll_description) . '">' . "\n";
    echo '<meta name="DC.type" content="Dataset">' . "\n";
    echo '<meta name="DC.format" content="text/html">' . "\n";
    echo '<meta name="DC.language" content="ja">' . "\n";

    if (!empty($poll_keywords)) {
        echo '<meta name="DC.subject" content="' . esc_attr($poll_keywords) . '">' . "\n";
    }
});

get_header(); ?>

<?php while (have_posts()) : the_post(); ?>

    <?php if (function_exists('kspb_display_breadcrumbs')) : kspb_display_breadcrumbs(); endif; ?>

    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        <header class="entry-header">
            <h1 class="entry-title"><?php the_title(); ?></h1>

            <div class="entry-meta">
                <?php
                $post_date = get_the_date('Y年m月d日');
                $post_modified = get_the_modified_date('Y年m月d日');
                ?>
                <time datetime="<?php echo get_the_date('c'); ?>" class="meta-badge date-badge">
                    投稿 <?php echo $post_date; ?>
                </time>
                <time datetime="<?php echo get_the_modified_date('c'); ?>" class="meta-badge modified-badge">
                    更新 <?php echo $post_modified; ?>
                </time>
                <span class="meta-badge author-badge">
                    <?php echo get_the_author(); ?>
                </span>
            </div>
        </header>

        <div class="entry-content">
            <?php
            // 説明文を表示
            $poll_description = get_post_meta(get_the_ID(), '_kashiwazaki_poll_description', true);
            if ($poll_description) {
                echo '<div class="poll-description">';
                echo '<p>' . esc_html($poll_description) . '</p>';
                echo '</div>';
            }

            // 集計結果を表示（ショートコード使用）
            $poll_id = get_the_ID();

            // ショートコード内の「このデータセットの詳細ページを見る」を非表示にするフィルター
            add_filter('kashiwazaki_poll_show_detail_link', '__return_false');

            echo '<div class="poll-result-section">';
            echo '<h2>集計結果</h2>';
            echo do_shortcode('[tk_poll id="' . $poll_id . '"]');
            echo '</div>';

            remove_filter('kashiwazaki_poll_show_detail_link', '__return_false');

            // データファイルへのリンク
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

            echo '<div class="dataset-downloads">';
            echo '<h2>データセットのダウンロード</h2>';
            echo '<p>集計データを様々な形式でダウンロード・閲覧できます。</p>';
            echo '<ul class="download-links">';

            foreach ($file_types as $type) {
                if (function_exists('kashiwazaki_poll_get_dataset_file_path')) {
                    $file_path = kashiwazaki_poll_get_dataset_file_path($poll_id, $type);
                    if (file_exists($file_path)) {
                        $display_name = isset($file_type_names[$type]) ? $file_type_names[$type] : strtoupper($type);
                        $description = isset($file_type_descriptions[$type]) ? $file_type_descriptions[$type] : '';

                        if (function_exists('kashiwazaki_poll_get_single_dataset_page_url')) {
                            $url = kashiwazaki_poll_get_single_dataset_page_url($poll_id, $type);
                            echo '<li>';
                            echo '<a href="' . esc_url($url) . '">';
                            echo '<strong>' . esc_html($display_name) . '</strong>';
                            if ($description) {
                                echo ' - ' . esc_html($description);
                            }
                            echo '</a>';
                            echo '</li>';
                        }
                    }
                }
            }

            echo '</ul>';
            echo '</div>';

            // 掲載中のページを表示
            if (function_exists('kashiwazaki_poll_render_shortcode_usage')) {
                kashiwazaki_poll_render_shortcode_usage($poll_id, false);
            }

            // データセット一覧に戻るリンク
            echo '<div style="margin-top: 40px; text-align: center;">';
            echo '<a href="' . esc_url(home_url('/datasets/')) . '" style="display: inline-block; padding: 8px 16px; border: 1px solid; border-radius: 3px; text-decoration: none; font-size: 0.9em; transition: opacity 0.2s;">データセット一覧に戻る</a>';
            echo '</div>';
            ?>
        </div>
    </article>

<?php endwhile; ?>

<?php get_footer(); ?>
