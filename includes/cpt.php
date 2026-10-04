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
        'menu_name'          => 'Kashiwazaki SEO Poll'
    );
    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'exclude_from_search'=> false,
        'publicly_queryable' => true,
        'show_ui'            => true,
        // 左メニューは「Kashiwazaki SEO Poll」の 1 つだけ（クリックでデータセット一覧）。
        // 一覧と基本設定は画面上部のタブで切り替える（kashiwazaki_poll_render_admin_tabs）。
        'show_in_menu'       => true,
        'menu_position'      => 81,
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

// 「Kashiwazaki SEO Poll」メニュー。
// 左メニューは投稿タイプ poll のトップメニュー 1 つだけにし（リンク先はデータセット一覧）、
// サブメニューは出さない。基本設定はこのメニューの下のページとして登録し
// (admin.php?page=kashiwazaki_poll_settings)、一覧と基本設定の行き来は画面上部のタブで行う。
add_action( 'admin_menu', 'kashiwazaki_poll_add_settings_menu' );
function kashiwazaki_poll_add_settings_menu() {
    $hook = add_submenu_page(
        'edit.php?post_type=poll',
        'Kashiwazaki SEO Poll 基本設定',
        '基本設定',
        'manage_options',
        'kashiwazaki_poll_settings',
        'kashiwazaki_poll_settings_page_html'
    );
    if ( $hook ) {
        $GLOBALS['kashiwazaki_poll_settings_hook'] = $hook;
    }
}

// サブメニュー（データセット一覧・新規追加・基本設定）を左メニューの表示から外す。
// WordPress はアクセス判定 (user_can_access_admin_page) と画面タイトル・選択中のメニューの
// 判定をサブメニューの一覧を使って行い、その後で左メニューを描く。admin_menu の段階で外すと
// 基本設定のページが「親の無いページ」と判定されて開けなくなるため、判定が済んだ後
// (admin_head。左メニューを描く直前) に外す。
add_action( 'admin_head', 'kashiwazaki_poll_hide_submenus' );
function kashiwazaki_poll_hide_submenus() {
    remove_submenu_page( 'edit.php?post_type=poll', 'edit.php?post_type=poll' );
    remove_submenu_page( 'edit.php?post_type=poll', 'post-new.php?post_type=poll' );
    remove_submenu_page( 'edit.php?post_type=poll', 'kashiwazaki_poll_settings' );
}

/**
 * 基本設定の画面かどうか。
 */
function kashiwazaki_poll_is_settings_screen() {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    $hook   = isset( $GLOBALS['kashiwazaki_poll_settings_hook'] ) ? $GLOBALS['kashiwazaki_poll_settings_hook'] : '';
    return $screen && $hook && $screen->id === $hook;
}

// データセット一覧と基本設定の上部にタブを出す。
// 本文の領域 (#wpbody-content) の中に出すため all_admin_notices の先頭で描く
// (in_admin_header は左メニューと同じ配置の範囲で、タブの枠が左メニューの下まで回り込む)。
add_action( 'all_admin_notices', 'kashiwazaki_poll_render_admin_tabs', 0 );
function kashiwazaki_poll_render_admin_tabs() {
    $screen = get_current_screen();
    if ( ! $screen ) {
        return;
    }
    $is_list     = ( 'edit-poll' === $screen->id );
    $is_settings = kashiwazaki_poll_is_settings_screen();
    if ( ! $is_list && ! $is_settings ) {
        return;
    }
    $tabs = array(
        array( 'label' => 'データセット一覧', 'url' => admin_url( 'edit.php?post_type=poll' ), 'active' => $is_list ),
    );
    if ( current_user_can( 'manage_options' ) ) {
        $tabs[] = array( 'label' => '基本設定', 'url' => admin_url( 'admin.php?page=kashiwazaki_poll_settings' ), 'active' => $is_settings );
    }
    echo '<div class="kspoll-tabs-wrap"><nav class="nav-tab-wrapper kspoll-tabs" aria-label="Kashiwazaki SEO Poll">';
    foreach ( $tabs as $tab ) {
        printf(
            '<a href="%1$s" class="nav-tab%2$s"%3$s>%4$s</a>',
            esc_url( $tab['url'] ),
            $tab['active'] ? ' nav-tab-active' : '',
            $tab['active'] ? ' aria-current="page"' : '',
            esc_html( $tab['label'] )
        );
    }
    echo '</nav></div>';
}

// 管理画面にキャッシュクリア完了メッセージを表示
add_action( 'admin_notices', 'kashiwazaki_poll_admin_notices' );
function kashiwazaki_poll_admin_notices() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'poll' ) {
        return;
    }

    if ( isset( $_GET['cache_cleared'] ) ) {
        echo '<div class="notice notice-success is-dismissible"><p>掲載ページの情報を再取得しました。</p></div>';
    }

    if ( isset( $_GET['all_cache_cleared'] ) ) {
        echo '<div class="notice notice-success is-dismissible"><p>全データセットの掲載ページ情報を再取得しました。</p></div>';
    }

    if ( isset( $_GET['kashiwazaki_poll_locked'] ) ) {
        $state = sanitize_key( wp_unslash( $_GET['kashiwazaki_poll_locked'] ) );
        if ( 'locked' === $state ) {
            echo '<div class="notice notice-success is-dismissible"><p>投票の受付を締め切りました（ロック）。</p></div>';
        } elseif ( 'open' === $state ) {
            echo '<div class="notice notice-success is-dismissible"><p>投票の受付を再開しました。</p></div>';
        } elseif ( 'busy' === $state ) {
            echo '<div class="notice notice-error is-dismissible"><p>他の処理（投票など）と重なったため、受付の状態は変えませんでした。少し待ってから、もう一度お試しください。</p></div>';
        } elseif ( 'failed' === $state ) {
            echo '<div class="notice notice-error is-dismissible"><p>受付の状態を保存できませんでした。もう一度お試しください。</p></div>';
        }
    }
}

add_filter( 'removable_query_args', function( $args ) {
    $args[] = 'kashiwazaki_poll_locked';
    return $args;
} );

// データセット一覧の上部に「掲載ページ情報の再取得」ボタンを置く
add_action( 'manage_posts_extra_tablenav', 'kashiwazaki_poll_list_tablenav' );
function kashiwazaki_poll_list_tablenav( $which ) {
    $screen = get_current_screen();
    if ( 'top' !== $which || ! $screen || 'edit-poll' !== $screen->id || ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $clear_all_url = wp_nonce_url(
        admin_url( 'edit.php?post_type=poll&action=clear_all_poll_usage_cache' ),
        'clear_all_poll_usage_cache'
    );
    echo '<div class="alignleft actions">';
    echo '<a href="' . esc_url( $clear_all_url ) . '" class="button" title="「使用記事」列が正しく表示されないときに使います">掲載ページ情報を再取得</a>';
    echo '</div>';
}

// 一覧の行アクションに「受付を締め切る／再開する」を追加
add_filter( 'post_row_actions', 'kashiwazaki_poll_row_actions', 10, 2 );
function kashiwazaki_poll_row_actions( $actions, $post ) {
    if ( 'poll' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) || 'trash' === $post->post_status ) {
        return $actions;
    }
    $locked = kashiwazaki_poll_is_locked( $post->ID );
    $url = wp_nonce_url(
        admin_url( 'admin-post.php?action=kashiwazaki_poll_toggle_lock&post=' . $post->ID . '&state=' . ( $locked ? 'open' : 'locked' ) ),
        'kashiwazaki_poll_toggle_lock_' . $post->ID
    );
    $actions['kashiwazaki_poll_lock'] = '<a href="' . esc_url( $url ) . '">' . ( $locked ? '受付を再開' : '受付を締め切る' ) . '</a>';
    return $actions;
}

add_action( 'admin_post_kashiwazaki_poll_toggle_lock', 'kashiwazaki_poll_handle_toggle_lock' );
function kashiwazaki_poll_handle_toggle_lock() {
    $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
    $state   = isset( $_GET['state'] ) ? sanitize_key( wp_unslash( $_GET['state'] ) ) : '';
    check_admin_referer( 'kashiwazaki_poll_toggle_lock_' . $post_id );
    if ( ! $post_id || 'poll' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
        wp_die( esc_html__( 'この操作を行う権限がありません。', 'kashiwazaki-seo-poll' ), 403 );
    }
    if ( ! in_array( $state, array( 'locked', 'open' ), true ) ) {
        wp_die( esc_html__( '不正なリクエストです。', 'kashiwazaki-seo-poll' ), 400 );
    }
    // 受付の切り替えも、編集画面の保存と同じ共通の入口（データセット単位の排他）を通す（同時の保存で上書きし合わないように）。
    $mutation = kashiwazaki_poll_mutate_vote_state( $post_id, function( $s ) use ( $state ) {
        return array( 'set' => array( '_kashiwazaki_poll_locked' => 'locked' === $state ? '1' : null ) );
    } );
    if ( ! $mutation['ok'] ) {
        $state = 'busy' === $mutation['error'] ? 'busy' : 'failed';
    }
    $back = wp_get_referer();
    if ( ! $back ) {
        $back = admin_url( 'edit.php?post_type=poll' );
    }
    wp_safe_redirect( add_query_arg( 'kashiwazaki_poll_locked', $state, remove_query_arg( 'kashiwazaki_poll_locked', $back ) ) );
    exit;
}

// 管理画面の投稿一覧にカスタム列を追加
add_filter( 'manage_poll_posts_columns', 'kashiwazaki_poll_add_admin_columns' );
function kashiwazaki_poll_add_admin_columns( $columns ) {
    // 日付列の前にカスタム列を挿入
    $new_columns = array();
    foreach ( $columns as $key => $value ) {
        if ( $key === 'date' ) {
            $new_columns['poll_status'] = '受付';
            $new_columns['poll_votes'] = '総投票数';
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
    if ( $column === 'poll_status' ) {
        if ( kashiwazaki_poll_is_locked( $post_id ) ) {
            echo '<span class="kspoll-list-badge is-locked">🔒 締切</span>';
        } else {
            echo '<span class="kspoll-list-badge is-open">受付中</span>';
        }
    } elseif ( $column === 'poll_votes' ) {
        $counts = get_post_meta( $post_id, '_kashiwazaki_poll_counts', true );
        $options = get_post_meta( $post_id, '_kashiwazaki_poll_options', true );
        $counts = kashiwazaki_poll_normalize_counts( $counts, is_array( $options ) ? count( $options ) : 0 );
        echo esc_html( number_format_i18n( array_sum( $counts ) ) ) . ' 票';
    } elseif ( $column === 'dataset_keywords' ) {
        $keywords = kashiwazaki_poll_decode_stored_text( get_post_meta( $post_id, 'dataset_keywords', true ) );
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

                    // 投稿日時
                    $post_date = get_the_date( 'Y/m/d', $usage_post->ID );

                    echo '<div class="usage-item">';
                    echo '<div class="usage-title-row">';
                    if ( $edit_url && current_user_can( 'edit_post', $usage_post->ID ) ) {
                        echo '<a href="' . esc_url( $edit_url ) . '" class="usage-edit-link" title="編集: ' . esc_attr( $usage_post->post_title ) . '">';
                        echo '<span class="usage-title">' . esc_html( mb_substr( $usage_post->post_title, 0, 25 ) . ( mb_strlen( $usage_post->post_title ) > 25 ? '...' : '' ) ) . '</span>';
                        echo '</a>';
                    } else {
                        echo '<span class="usage-title">' . esc_html( mb_substr( $usage_post->post_title, 0, 25 ) . ( mb_strlen( $usage_post->post_title ) > 25 ? '...' : '' ) ) . '</span>';
                    }
                    echo '</div>';
                    echo '<div class="usage-meta">';
                    echo '<span class="usage-type">' . esc_html( $type_label_jp ) . '</span>';
                    echo '<span class="usage-date"> | ' . esc_html( $post_date ) . '</span>';
                    // ショートコード使用回数を表示
                    if ( isset( $usage_post->shortcode_count ) && $usage_post->shortcode_count > 1 ) {
                        echo '<span class="usage-count"> | ' . $usage_post->shortcode_count . '回使用</span>';
                    }
                    if ( $view_url && 'publish' === get_post_status( $usage_post->ID ) ) {
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

    if ( 'dataset_keywords' === $orderby && 'poll' === $query->get( 'post_type' ) ) {
        // meta_key を指定するとキーワードのメタが無いデータセットが一覧から消えるため、LEFT JOIN で並べ替える。
        add_filter( 'posts_clauses', 'kashiwazaki_poll_keyword_sort_clauses', 10, 2 );
    } elseif ( 'shortcode_usage' === $orderby && 'poll' === $query->get( 'post_type' ) ) {
        // 使用記事数で並べ替える。meta_key を指定すると件数のメタが無いデータセットが一覧から消えるため、
        // 並べ替えの識別子はそのまま残し、SQL 句の調整（LEFT JOIN で無い場合は0扱い）だけで並べ替える。
        add_filter( 'posts_clauses', 'kashiwazaki_poll_usage_sort_clauses', 10, 2 );
    }
}

// キーワード列のソート用のSQL句調整（メタが無いデータセットは空文字として扱う）
function kashiwazaki_poll_keyword_sort_clauses( $clauses, $query ) {
    global $wpdb;
    if ( is_admin() && $query->is_main_query() && $query->get( 'orderby' ) === 'dataset_keywords' ) {
        $order = ( 'DESC' === strtoupper( (string) $query->get( 'order' ) ) ) ? 'DESC' : 'ASC';
        $clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} AS pm_kw ON {$wpdb->posts}.ID = pm_kw.post_id AND pm_kw.meta_key = 'dataset_keywords'";
        $clauses['orderby'] = "COALESCE(pm_kw.meta_value, '') {$order}, {$wpdb->posts}.ID {$order}";
        $clauses['groupby'] = "{$wpdb->posts}.ID";
        remove_filter( 'posts_clauses', 'kashiwazaki_poll_keyword_sort_clauses', 10 );
    }
    return $clauses;
}

// 使用記事数ソート用のSQL句調整
function kashiwazaki_poll_usage_sort_clauses( $clauses, $query ) {
    global $wpdb;

    if ( is_admin() && $query->is_main_query() && $query->get( 'orderby' ) === 'shortcode_usage' ) {
        // LEFT JOINを使用して、メタデータが存在しない場合も含める
        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS pm_usage ON {$wpdb->posts}.ID = pm_usage.post_id AND pm_usage.meta_key = '_poll_usage_count'";
        $order = ( 'DESC' === strtoupper( (string) $query->get( 'order' ) ) ) ? 'DESC' : 'ASC';
        $clauses['orderby'] = "CAST(COALESCE(pm_usage.meta_value, 0) AS SIGNED) {$order}, {$wpdb->posts}.ID {$order}";

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
    $current_time = time();

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
