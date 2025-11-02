<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', function() {
    $labels = array(
        'name'               => 'データセット',
        'singular_name'      => 'データセット',
        'add_new'            => '新規追加',
        'add_new_item'       => '新しいデータセットを追加',
        'edit_item'          => 'データセットを編集',
        'new_item'           => '新しいデータセット',
        'all_items'          => 'データセット一覧',
        'view_item'          => 'データセットを見る',
        'search_items'       => 'データセットを検索',
        'not_found'          => 'データセットはありません',
        'not_found_in_trash' => 'ゴミ箱にデータセットはありません',
        'menu_name'          => 'データセット'
    );
    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'exclude_from_search'=> false,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'menu_position'      => 5, // 投稿の下に表示
        'menu_icon'          => 'dashicons-chart-pie',
        'capability_type'    => 'post',
        'hierarchical'       => false,
        'supports'           => array( 'title', 'author' ),
        'has_archive'        => false,
        'rewrite'            => false, // 手動でリライトルールを設定
    );
    register_post_type( 'poll', $args );
});

// 投稿IDベースのパーマリンクを生成: /datasets/detail-123/
add_filter('post_type_link', 'kashiwazaki_poll_post_type_link', 10, 2);
function kashiwazaki_poll_post_type_link($post_link, $post) {
    if ($post->post_type === 'poll') {
        return home_url('/datasets/detail-' . $post->ID . '/');
    }
    return $post_link;
}

// 投稿IDベースのリライトルールを追加: /datasets/detail-123/
add_action('init', 'kashiwazaki_poll_add_single_post_rewrite', 20);
function kashiwazaki_poll_add_single_post_rewrite() {
    add_rewrite_rule(
        '^datasets/detail-([0-9]+)/?$',
        'index.php?post_type=poll&p=$matches[1]',
        'top'
    );
}

// Kashiwazaki SEO Poll 設定メニューを追加
add_action( 'admin_menu', 'kashiwazaki_poll_add_settings_menu' );
function kashiwazaki_poll_add_settings_menu() {
    // トップレベルメニュー「Kashiwazaki SEO Poll」を位置81に追加
    add_menu_page(
        'Kashiwazaki SEO Poll 基本設定',   // ページタイトル
        'Kashiwazaki SEO Poll',            // メニュータイトル
        'manage_options',                   // 権限
        'kashiwazaki_poll_settings',        // メニュースラッグ
        'kashiwazaki_poll_settings_page_html', // コールバック関数
        'dashicons-admin-settings',         // アイコン
        81                                  // 位置（元の位置）
    );

    // サブメニュー: 基本設定（トップレベルと同じページ）
    add_submenu_page(
        'kashiwazaki_poll_settings',        // 親メニュースラッグ
        '基本設定',                         // ページタイトル
        '基本設定',                         // メニュータイトル
        'manage_options',                   // 権限
        'kashiwazaki_poll_settings',        // メニュースラッグ
        'kashiwazaki_poll_settings_page_html' // コールバック関数
    );
}

// 管理画面にキャッシュクリア機能を追加
add_action('admin_notices', 'kashiwazaki_poll_admin_notices');
function kashiwazaki_poll_admin_notices() {
    $screen = get_current_screen();
    if ($screen->post_type !== 'poll') {
        return;
    }

    // キャッシュクリア完了メッセージ
    if (isset($_GET['cache_cleared'])) {
        if ($screen->base === 'post') {
            echo '<div class="notice notice-success is-dismissible"><p>ショートコード使用状況キャッシュをクリアしました。</p></div>';
        } else {
            echo '<div class="notice notice-success is-dismissible"><p>ショートコード使用状況のキャッシュをクリアしました。</p></div>';
        }
    }

    if (isset($_GET['all_cache_cleared'])) {
        echo '<div class="notice notice-success is-dismissible"><p>全データのショートコード使用状況キャッシュをクリアしました。</p></div>';
    }

    // 一覧ページでのみキャッシュクリアボタンを表示
    if ($screen->base === 'edit' && $screen->post_type === 'poll') {
        $clear_all_url = wp_nonce_url(
            admin_url('edit.php?post_type=poll&action=clear_all_poll_usage_cache'),
            'clear_all_poll_usage_cache'
        );

        echo '<div class="notice notice-info">';
        echo '<p>ショートコード使用状況が正しく表示されない場合は、キャッシュをクリアしてください。</p>';
        echo '<p><a href="' . esc_url($clear_all_url) . '" class="button" onclick="return confirm(\'全データのキャッシュをクリアしますか？\')">全キャッシュをクリア</a></p>';
        echo '</div>';
    }
}

// 管理画面の投稿一覧にカスタム列を追加
add_filter( 'manage_poll_posts_columns', 'kashiwazaki_poll_add_admin_columns' );
function kashiwazaki_poll_add_admin_columns( $columns ) {
    // 日付列の前にカスタム列を挿入
    $new_columns = array();
    foreach ( $columns as $key => $value ) {
        if ( $key === 'date' ) {
            $new_columns['dataset_keywords'] = 'データセットキーワード';
            $new_columns['shortcode_usage'] = '使用記事';
        }
        $new_columns[$key] = $value;
    }
    return $new_columns;
}

// カスタム列の内容を表示
add_action( 'manage_poll_posts_custom_column', 'kashiwazaki_poll_show_admin_columns', 10, 2 );
function kashiwazaki_poll_show_admin_columns( $column, $post_id ) {
    if ( $column === 'dataset_keywords' ) {
        $keywords = get_post_meta( $post_id, 'dataset_keywords', true );
        if ( ! empty( $keywords ) ) {
            $keywords_array = array_map( 'trim', explode( ',', $keywords ) );
            $keywords_array = array_filter( $keywords_array ); // 空の要素を除去
            if ( ! empty( $keywords_array ) ) {
                echo '<span class="poll-keywords">' . esc_html( implode( ', ', $keywords_array ) ) . '</span>';
            } else {
                echo '<span class="poll-keywords-empty">—</span>';
            }
        } else {
            echo '<span class="poll-keywords-empty">—</span>';
        }
    } elseif ( $column === 'shortcode_usage' ) {
                // ショートコード使用記事を表示
        if ( function_exists( 'kashiwazaki_poll_get_shortcode_usage' ) ) {
            $usage_posts = kashiwazaki_poll_get_shortcode_usage( $post_id );
            if ( ! empty( $usage_posts ) ) {
                echo '<div class="shortcode-usage-list">';
                $max_display = 5; // 最大5つまで表示
                $count = 0;
                foreach ( $usage_posts as $usage_post ) {
                    if ( $count >= $max_display ) {
                        $remaining = count( $usage_posts ) - $max_display;
                        echo '<div class="usage-more">+ 他 ' . $remaining . '件の記事で使用中</div>';
                        break;
                    }
                    $edit_url = get_edit_post_link( $usage_post->ID );
                    $view_url = get_permalink( $usage_post->ID );
                    $post_type_obj = get_post_type_object( $usage_post->post_type );
                    $type_label = $post_type_obj ? $post_type_obj->labels->singular_name : $usage_post->post_type;

                    // 投稿タイプの日本語化
                    $type_translations = array(
                        'post' => '投稿',
                        'page' => '固定ページ',
                        'product' => '商品',
                        'event' => 'イベント'
                    );
                    $type_label_jp = isset( $type_translations[ $usage_post->post_type ] ) ? $type_translations[ $usage_post->post_type ] : $type_label;

                    // 投稿ステータス
                    $status = get_post_status( $usage_post->ID );
                    $status_label = '';
                    if ( $status === 'draft' ) {
                        $status_label = ' [下書き]';
                    } elseif ( $status === 'private' ) {
                        $status_label = ' [非公開]';
                    } elseif ( $status === 'pending' ) {
                        $status_label = ' [レビュー待ち]';
                    }

                    // 投稿日時
                    $post_date = get_the_date( 'Y/m/d', $usage_post->ID );

                    echo '<div class="usage-item">';
                    echo '<div class="usage-title-row">';
                    if ( $edit_url && current_user_can( 'edit_post', $usage_post->ID ) ) {
                        echo '<a href="' . esc_url( $edit_url ) . '" class="usage-edit-link" title="編集: ' . esc_attr( $usage_post->post_title ) . '">';
                        echo '<span class="usage-title">' . esc_html( mb_substr( $usage_post->post_title, 0, 25 ) . ( mb_strlen( $usage_post->post_title ) > 25 ? '...' : '' ) ) . '</span>';
                        echo '<span class="usage-status">' . esc_html( $status_label ) . '</span>';
                        echo '</a>';
                    } else {
                        echo '<span class="usage-title">' . esc_html( mb_substr( $usage_post->post_title, 0, 25 ) . ( mb_strlen( $usage_post->post_title ) > 25 ? '...' : '' ) ) . '</span>';
                        echo '<span class="usage-status">' . esc_html( $status_label ) . '</span>';
                    }
                    echo '</div>';
                    echo '<div class="usage-meta">';
                    echo '<span class="usage-type">' . esc_html( $type_label_jp ) . '</span>';
                    echo '<span class="usage-date"> | ' . esc_html( $post_date ) . '</span>';
                    // ショートコード使用回数を表示
                    if ( isset( $usage_post->shortcode_count ) && $usage_post->shortcode_count > 1 ) {
                        echo '<span class="usage-count"> | ' . $usage_post->shortcode_count . '回使用</span>';
                    }
                    if ( $view_url && $status === 'publish' ) {
                        echo ' | <a href="' . esc_url( $view_url ) . '" target="_blank" class="usage-view-link">表示</a>';
                    }
                    echo '</div>';
                    echo '</div>';
                    $count++;
                }
                echo '</div>';
            } else {
                echo '<span class="usage-empty">未使用</span>';
            }
        } else {
            echo '<span class="usage-error">—</span>';
        }
    }
}

// カスタム列をソート可能にする
add_filter( 'manage_edit-poll_sortable_columns', 'kashiwazaki_poll_sortable_columns' );
function kashiwazaki_poll_sortable_columns( $columns ) {
    $columns['dataset_keywords'] = 'dataset_keywords';
    $columns['shortcode_usage'] = 'shortcode_usage';
    return $columns;
}

// ソート処理
add_action( 'pre_get_posts', 'kashiwazaki_poll_sort_by_custom_columns' );
function kashiwazaki_poll_sort_by_custom_columns( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }

    $orderby = $query->get( 'orderby' );

    if ( 'dataset_keywords' === $orderby ) {
        $query->set( 'meta_key', 'dataset_keywords' );
        $query->set( 'orderby', 'meta_value' );
    } elseif ( 'shortcode_usage' === $orderby ) {
        // 使用記事数でソートするために、カスタムメタクエリを使用
        $query->set( 'meta_key', '_poll_usage_count' );
        $query->set( 'orderby', 'meta_value_num' );

        // 使用記事数のメタデータが存在しない場合は0として扱う
        add_filter( 'posts_clauses', 'kashiwazaki_poll_usage_sort_clauses', 10, 2 );
    }
}

// 使用記事数ソート用のSQL句調整
function kashiwazaki_poll_usage_sort_clauses( $clauses, $query ) {
    global $wpdb;

    if ( is_admin() && $query->is_main_query() && $query->get( 'orderby' ) === 'shortcode_usage' ) {
        // LEFT JOINを使用して、メタデータが存在しない場合も含める
        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS pm_usage ON {$wpdb->posts}.ID = pm_usage.post_id AND pm_usage.meta_key = '_poll_usage_count'";
        $clauses['orderby'] = "CAST(COALESCE(pm_usage.meta_value, 0) AS SIGNED) " . $query->get( 'order', 'ASC' );

        // 重複を避けるためにGROUP BYを追加
        $clauses['groupby'] = "{$wpdb->posts}.ID";

        // フィルターを削除（1回だけ実行）
        remove_filter( 'posts_clauses', 'kashiwazaki_poll_usage_sort_clauses', 10 );
    }

    return $clauses;
}

// 使用記事数をメタデータとして保存・更新する関数
function kashiwazaki_poll_update_usage_count( $poll_id ) {
    if ( function_exists( 'kashiwazaki_poll_get_shortcode_usage' ) ) {
        $usage_posts = kashiwazaki_poll_get_shortcode_usage( $poll_id );
        $count = is_array( $usage_posts ) ? count( $usage_posts ) : 0;
        update_post_meta( $poll_id, '_poll_usage_count', $count );
    }
}

// 投稿保存時に使用記事数を更新
add_action( 'save_post', 'kashiwazaki_poll_update_all_usage_counts', 20 );
function kashiwazaki_poll_update_all_usage_counts( $post_id ) {
    // 自動保存やリビジョンをスキップ
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // 最後の実行時刻をチェック（24時間間隔で実行）
    $last_update = get_option( 'kashiwazaki_poll_last_usage_update', 0 );
    $current_time = current_time( 'timestamp' );

    // 24時間（86400秒）経過していない場合はスキップ
    if ( ( $current_time - $last_update ) < DAY_IN_SECONDS ) {
        return;
    }

    // 実行時刻を更新
    update_option( 'kashiwazaki_poll_last_usage_update', $current_time );

    // 保存された投稿のコンテンツにショートコードが含まれている可能性があるため、
    // すべてのpoll投稿の使用数を更新
    $polls = get_posts( array(
        'post_type' => 'poll',
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids'
    ) );

    foreach ( $polls as $poll_id ) {
        // キャッシュをクリアしてから更新
        if ( function_exists( 'kashiwazaki_poll_clear_usage_cache' ) ) {
            kashiwazaki_poll_clear_usage_cache( $poll_id );
        }
        kashiwazaki_poll_update_usage_count( $poll_id );
    }
}

// プラグイン有効化時に既存のアンケートの使用記事数を初期化
add_action( 'admin_init', 'kashiwazaki_poll_init_usage_counts' );
function kashiwazaki_poll_init_usage_counts() {
    // 既に初期化済みかチェック
    if ( get_option( 'kashiwazaki_poll_usage_counts_initialized' ) ) {
        return;
    }

    // すべてのpoll投稿の使用記事数を初期化
    $polls = get_posts( array(
        'post_type' => 'poll',
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids'
    ) );

    foreach ( $polls as $poll_id ) {
        kashiwazaki_poll_update_usage_count( $poll_id );
    }

    // 初期化完了フラグを設定
    update_option( 'kashiwazaki_poll_usage_counts_initialized', true );
}

// Poll 投稿タイプのテンプレートをプラグイン内から読み込む
add_filter('single_template', 'kashiwazaki_poll_single_template');
function kashiwazaki_poll_single_template($template) {
    if (is_singular('poll')) {
        $plugin_template = KASHIWAZAKI_POLL_DIR . 'templates/single-poll.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
}
