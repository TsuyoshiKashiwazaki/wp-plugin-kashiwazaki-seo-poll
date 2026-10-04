<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// メニューはcpt.phpで登録されているため、ここでは登録しない

add_action( 'admin_init', 'kashiwazaki_poll_register_plugin_settings' );
function kashiwazaki_poll_register_plugin_settings() {
    register_setting(
        'kashiwazaki_poll_options_group',
        'kashiwazaki_poll_settings',
        'kashiwazaki_poll_settings_sanitize'
    );

    add_settings_section(
        'kashiwazaki_poll_settings_section_structured_data',
        '構造化データ',
        function() {
            echo '<p>データセットページと投票フォームに出力する構造化データ（Dataset）の設定です。</p>';
        },
        'kashiwazaki_poll_settings_page_id'
    );

    add_settings_section(
        'kashiwazaki_poll_settings_section_dataset_page',
        'データセットページ',
        function() {
            echo '<p>集計結果を公開するデータセットページ（/datasets/）の表示設定です。</p>';
        },
        'kashiwazaki_poll_settings_page_id'
    );

    add_settings_field(
        'breadcrumb_structured_data_field',
        'パンくずリスト',
        'kashiwazaki_poll_settings_field_breadcrumb_cb',
        'kashiwazaki_poll_settings_page_id',
        'kashiwazaki_poll_settings_section_structured_data',
        array( 'label_for' => 'kashiwazaki_poll_breadcrumb_structured_data' )
    );

    add_settings_field(
        'structured_data_provider_field',
        'プラグイン作者情報',
        'kashiwazaki_poll_settings_field_provider_cb',
        'kashiwazaki_poll_settings_page_id',
        'kashiwazaki_poll_settings_section_structured_data',
        array( 'label_for' => 'kashiwazaki_poll_structured_data_provider' )
    );

    add_settings_field(
        'structured_data_email_field',
        '連絡先メールアドレス',
        'kashiwazaki_poll_settings_field_email_cb',
        'kashiwazaki_poll_settings_page_id',
        'kashiwazaki_poll_settings_section_structured_data',
        array( 'label_for' => 'kashiwazaki_poll_structured_data_email' )
    );

    add_settings_field(
        'structured_data_creator_type_field',
        '作成者（Creator）',
        'kashiwazaki_poll_settings_field_creator_type_cb',
        'kashiwazaki_poll_settings_page_id',
        'kashiwazaki_poll_settings_section_structured_data',
        array( 'label_for' => 'kashiwazaki_poll_creator_type' )
    );

    add_settings_field(
        'structured_data_creator_person_field',
        '個人の情報',
        'kashiwazaki_poll_settings_field_creator_person_cb',
        'kashiwazaki_poll_settings_page_id',
        'kashiwazaki_poll_settings_section_structured_data'
    );

    add_settings_field(
        'structured_data_creator_organization_field',
        '組織の情報',
        'kashiwazaki_poll_settings_field_creator_organization_cb',
        'kashiwazaki_poll_settings_page_id',
        'kashiwazaki_poll_settings_section_structured_data'
    );

    add_settings_field(
        'dataset_page_title_field',
        '一覧ページのタイトル',
        'kashiwazaki_poll_settings_field_dataset_page_title_cb',
        'kashiwazaki_poll_settings_page_id',
        'kashiwazaki_poll_settings_section_dataset_page',
        array( 'label_for' => 'kashiwazaki_poll_dataset_page_title' )
    );

    add_settings_field(
        'dataset_page_color_theme_field',
        'カラーテーマ',
        'kashiwazaki_poll_settings_field_color_theme_cb',
        'kashiwazaki_poll_settings_page_id',
        'kashiwazaki_poll_settings_section_dataset_page',
        array( 'label_for' => 'kashiwazaki_poll_dataset_color_theme' )
    );

    add_settings_field(
        'dataset_spatial_coverage_field',
        '地理的範囲',
        'kashiwazaki_poll_settings_field_dataset_spatial_coverage_cb',
        'kashiwazaki_poll_settings_page_id',
        'kashiwazaki_poll_settings_section_dataset_page',
        array( 'label_for' => 'kashiwazaki_poll_dataset_spatial_coverage' )
    );
}

function kashiwazaki_poll_settings_sanitize( $input ) {
    $sanitized_input = array();

    if ( isset( $input['breadcrumb_structured_data'] ) ) {
        $sanitized_input['breadcrumb_structured_data'] = 1;
    } else {
        $sanitized_input['breadcrumb_structured_data'] = 0;
    }

    if ( isset( $input['structured_data_provider'] ) ) {
        $sanitized_input['structured_data_provider'] = 1;
    } else {
        $sanitized_input['structured_data_provider'] = 0;
    }

    $sanitized_input['structured_data_email'] = isset( $input['structured_data_email'] ) ? 1 : 0;

    // Creator Type
    if ( isset( $input['creator_type'] ) && in_array( $input['creator_type'], array( 'organization_only', 'person_only', 'both' ) ) ) {
        $sanitized_input['creator_type'] = $input['creator_type'];
    } else {
        $sanitized_input['creator_type'] = 'organization_only';
    }

    // Person Creator Settings
    if ( isset( $input['creator_person_name'] ) ) {
        $sanitized_input['creator_person_name'] = sanitize_text_field( $input['creator_person_name'] );
    }
    if ( isset( $input['creator_person_url'] ) ) {
        $sanitized_input['creator_person_url'] = esc_url_raw( $input['creator_person_url'] );
    }

    // Organization Creator Settings
    if ( isset( $input['creator_organization_name'] ) ) {
        $sanitized_input['creator_organization_name'] = sanitize_text_field( $input['creator_organization_name'] );
    }
    if ( isset( $input['creator_organization_url'] ) ) {
        $sanitized_input['creator_organization_url'] = esc_url_raw( $input['creator_organization_url'] );
    }
    if ( isset( $input['creator_organization_email'] ) ) {
        $sanitized_input['creator_organization_email'] = sanitize_email( $input['creator_organization_email'] );
    }

    // Dataset Page Title
    if ( isset( $input['dataset_page_title'] ) ) {
        $sanitized_input['dataset_page_title'] = sanitize_text_field( $input['dataset_page_title'] );
    } else {
        $sanitized_input['dataset_page_title'] = '集計データ一覧';
    }

    // Dataset Page Color Theme
    if ( isset( $input['dataset_color_theme'] ) && in_array( $input['dataset_color_theme'], array( 'blue', 'green', 'orange', 'purple', 'dark', 'minimal' ) ) ) {
        $sanitized_input['dataset_color_theme'] = $input['dataset_color_theme'];
    } else {
        $sanitized_input['dataset_color_theme'] = 'minimal';
    }

    // Dataset Spatial Coverage
    if ( isset( $input['dataset_spatial_coverage'] ) ) {
        $sanitized_input['dataset_spatial_coverage'] = sanitize_text_field( $input['dataset_spatial_coverage'] );
    } else {
        $sanitized_input['dataset_spatial_coverage'] = '日本';
    }

    return $sanitized_input;
}

function kashiwazaki_poll_settings_field_breadcrumb_cb() {
    $options = get_option( 'kashiwazaki_poll_settings', array('breadcrumb_structured_data' => 0) );
    $checked = ( isset( $options['breadcrumb_structured_data'] ) && $options['breadcrumb_structured_data'] == 1 ) ? 'checked' : '';
    echo '<label><input type="checkbox" id="kashiwazaki_poll_breadcrumb_structured_data" name="kashiwazaki_poll_settings[breadcrumb_structured_data]" value="1" ' . $checked . ' /> パンくずリストの構造化データ (BreadcrumbList) を出力する</label>';
    echo '<p class="description">' . esc_html__( '他のプラグインでパンくずリストを管理している場合は、重複を避けるためOFFにしてください。デフォルトはOFFです。', 'kashiwazaki-seo-poll') . '</p>';
}

function kashiwazaki_poll_settings_field_provider_cb() {
    $options = get_option( 'kashiwazaki_poll_settings', array('structured_data_provider' => 0) );
    $checked = ( isset( $options['structured_data_provider'] ) && $options['structured_data_provider'] == 1 ) ? 'checked' : '';
    echo '<label><input type="checkbox" id="kashiwazaki_poll_structured_data_provider" name="kashiwazaki_poll_settings[structured_data_provider]" value="1" ' . $checked . ' /> 構造化データにプラグイン開発者の情報 (provider) を含める</label>';
    echo '<p class="description">' . esc_html__( 'このプラグインの作者（柏崎剛）を Dataset の `provider` として明記します。', 'kashiwazaki-seo-poll') . '</p>';
}

function kashiwazaki_poll_settings_field_email_cb() {
    $options = get_option( 'kashiwazaki_poll_settings', array() );
    $checked = ( is_array( $options ) && ! empty( $options['structured_data_email'] ) ) ? 'checked' : '';
    echo '<label><input type="checkbox" id="kashiwazaki_poll_structured_data_email" name="kashiwazaki_poll_settings[structured_data_email]" value="1" ' . $checked . ' /> ' . esc_html__( '構造化データに組織のメールアドレスを含める', 'kashiwazaki-seo-poll' ) . '</label>';
    echo '<p class="description">' . esc_html__( 'ON にすると、下の「組織の情報」のメールアドレスを、作成者・発行者の連絡先として公開ページの構造化データに出力します（誰でも見られます）。既定は OFF で、メールアドレスは出力しません。', 'kashiwazaki-seo-poll' ) . '</p>';
}

function kashiwazaki_poll_settings_field_creator_type_cb() {
    $options = get_option( 'kashiwazaki_poll_settings', array( 'creator_type' => 'organization_only' ) );
    $creator_type = $options['creator_type'];
    $html = '<select name="kashiwazaki_poll_settings[creator_type]" id="kashiwazaki_poll_creator_type">';
    $html .= '<option value="organization_only" ' . selected( $creator_type, 'organization_only', false ) . '>' . esc_html__( '組織（Organization）のみ', 'kashiwazaki-seo-poll' ) . '</option>';
    $html .= '<option value="person_only" ' . selected( $creator_type, 'person_only', false ) . '>' . esc_html__( '個人（Person）のみ', 'kashiwazaki-seo-poll' ) . '</option>';
    $html .= '<option value="both" ' . selected( $creator_type, 'both', false ) . '>' . esc_html__( '個人と組織の両方', 'kashiwazaki-seo-poll' ) . '</option>';
    $html .= '</select>';
    echo $html;
    echo '<p class="description">' . esc_html__( '構造化データに含める Creator の種類を選択します。', 'kashiwazaki-seo-poll' ) . '</p>';
}

function kashiwazaki_poll_settings_field_creator_person_cb() {
    $options = get_option( 'kashiwazaki_poll_settings', array(
        'creator_type' => 'organization_only',
        'creator_person_name' => '',
        'creator_person_url' => ''
    ) );

    echo '<div id="creator_person_fields" style="display: none;">';
    echo '<p>' . esc_html__( 'Person Creator の設定を行います。', 'kashiwazaki-seo-poll' ) . '</p>';
    echo '<p><label for="kashiwazaki_poll_creator_person_name">' . esc_html__( 'Person の名前', 'kashiwazaki-seo-poll' ) . ':</label><br><input type="text" id="kashiwazaki_poll_creator_person_name" name="kashiwazaki_poll_settings[creator_person_name]" value="' . esc_attr( $options['creator_person_name'] ) . '" style="width: 300px;" /></p>';
    echo '<p><label for="kashiwazaki_poll_creator_person_url">' . esc_html__( 'Person の URL', 'kashiwazaki-seo-poll' ) . ':</label><br><input type="url" id="kashiwazaki_poll_creator_person_url" name="kashiwazaki_poll_settings[creator_person_url]" value="' . esc_attr( $options['creator_person_url'] ) . '" style="width: 300px;" /></p>';
    echo '</div>';
}

function kashiwazaki_poll_settings_field_creator_organization_cb() {
    $options = get_option( 'kashiwazaki_poll_settings', array(
        'creator_type' => 'organization_only',
        'creator_organization_name' => get_bloginfo('name'),
        'creator_organization_url' => home_url(),
        'creator_organization_email' => ''
    ) );

    echo '<div id="creator_organization_fields" style="display: none;">';
    echo '<p>' . esc_html__( 'Organization Creator の設定を行います。', 'kashiwazaki-seo-poll' ) . '</p>';
    echo '<p><label for="kashiwazaki_poll_creator_organization_name">' . esc_html__( 'Organization の名前', 'kashiwazaki-seo-poll' ) . ':</label><br><input type="text" id="kashiwazaki_poll_creator_organization_name" name="kashiwazaki_poll_settings[creator_organization_name]" value="' . esc_attr( $options['creator_organization_name'] ) . '" style="width: 300px;" /></p>';
    echo '<p><label for="kashiwazaki_poll_creator_organization_url">' . esc_html__( 'Organization の URL', 'kashiwazaki-seo-poll' ) . ':</label><br><input type="url" id="kashiwazaki_poll_creator_organization_url" name="kashiwazaki_poll_settings[creator_organization_url]" value="' . esc_attr( $options['creator_organization_url'] ) . '" style="width: 300px;" /></p>';
    echo '<p><label for="kashiwazaki_poll_creator_organization_email">' . esc_html__( 'Organization のメールアドレス', 'kashiwazaki-seo-poll' ) . ':</label><br><input type="email" id="kashiwazaki_poll_creator_organization_email" name="kashiwazaki_poll_settings[creator_organization_email]" value="' . esc_attr( $options['creator_organization_email'] ) . '" style="width: 300px;" /></p>';
    echo '</div>';
}

function kashiwazaki_poll_settings_field_dataset_page_title_cb() {
    $options = get_option( 'kashiwazaki_poll_settings', array( 'dataset_page_title' => '集計データ一覧' ) );
    $dataset_page_title = $options['dataset_page_title'];

    echo '<input type="text" id="kashiwazaki_poll_dataset_page_title" name="kashiwazaki_poll_settings[dataset_page_title]" value="' . esc_attr( $dataset_page_title ) . '" style="width: 300px;" />';
    echo '<p class="description">' . esc_html__( 'データセット一覧ページのタイトルを設定してください。パンくずナビゲーションやページタイトルに使用されます。', 'kashiwazaki-seo-poll' ) . '</p>';
}

function kashiwazaki_poll_settings_field_dataset_spatial_coverage_cb() {
    $options = get_option( 'kashiwazaki_poll_settings', array( 'dataset_spatial_coverage' => '日本' ) );
    $spatial_coverage = $options['dataset_spatial_coverage'];

    echo '<input type="text" id="kashiwazaki_poll_dataset_spatial_coverage" name="kashiwazaki_poll_settings[dataset_spatial_coverage]" value="' . esc_attr( $spatial_coverage ) . '" style="width: 300px;" />';
    echo '<p class="description">' . esc_html__( 'データセットが対象とする地理的範囲を設定してください。例：日本、東京都、全世界など。', 'kashiwazaki-seo-poll' ) . '</p>';
}

function kashiwazaki_poll_settings_field_color_theme_cb() {
    $options = get_option( 'kashiwazaki_poll_settings', array( 'dataset_color_theme' => 'minimal' ) );
    $theme = $options['dataset_color_theme'];

    $themes = array(
        'minimal' => array(
            'name' => 'ミニマル（白ベース）',
            'description' => 'グレーのアクセント'
        ),
        'blue' => array(
            'name' => 'ブルー',
            'description' => '青のアクセント'
        ),
        'green' => array(
            'name' => 'グリーン',
            'description' => '緑のアクセント'
        ),
        'orange' => array(
            'name' => 'オレンジ',
            'description' => 'オレンジのアクセント'
        ),
        'purple' => array(
            'name' => 'パープル',
            'description' => '紫のアクセント'
        ),
        'dark' => array(
            'name' => 'ダーク',
            'description' => '濃い色のカード・明るい文字'
        )
    );

    echo '<div class="color-theme-selector">';
    foreach ( $themes as $key => $theme_data ) {
        $checked = checked( $theme, $key, false );
        echo '<div style="margin-bottom: 10px;">';
        echo '<label>';
        echo '<input type="radio" name="kashiwazaki_poll_settings[dataset_color_theme]" value="' . esc_attr($key) . '" ' . $checked . '>';
        echo ' <strong>' . esc_html($theme_data['name']) . '</strong>';
        echo '<span style="color: #666; margin-left: 10px;">(' . esc_html($theme_data['description']) . ')</span>';
        echo '</label>';
        echo '</div>';
    }
    echo '</div>';
    echo '<p class="description">' . esc_html__( 'データセットページの表・カード・リンク・ボタンの色合いを選択してください（ページの背景やヘッダーの色はテーマに従います）。', 'kashiwazaki-seo-poll' ) . '</p>';
}

/**
 * 基本設定画面のメンテナンス操作（投票制限の解除・サイトマップ再生成・データファイル一括生成）を
 * 画面の出力前に処理し、結果を URL 引数に付けて同じ画面へリダイレクトする。
 */
add_action( 'admin_init', 'kashiwazaki_poll_handle_maintenance_post' );
function kashiwazaki_poll_handle_maintenance_post() {
    if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
        return;
    }
    if ( ! isset( $_GET['page'] ) || 'kashiwazaki_poll_settings' !== $_GET['page'] ) {
        return;
    }
    $actions = array(
        'reset'   => array( 'kashiwazaki_poll_reset_date_submit', '_wpnonce_reset_date', 'kashiwazaki_poll_reset_date_action' ),
        'sitemap' => array( 'kashiwazaki_poll_sitemap_regenerate_submit', '_wpnonce_sitemap_regenerate', 'kashiwazaki_poll_sitemap_regenerate_action' ),
        'batch'   => array( 'kashiwazaki_poll_batch_generate_submit', '_wpnonce_batch_generate', 'kashiwazaki_poll_batch_generate_action' ),
    );
    $action = '';
    foreach ( $actions as $key => $def ) {
        if ( isset( $_POST[ $def[0] ] ) ) {
            $action = $key;
            break;
        }
    }
    if ( '' === $action ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'この操作を行う権限がありません。', 'kashiwazaki-seo-poll' ), '', array( 'response' => 403 ) );
    }
    $def   = $actions[ $action ];
    $nonce = isset( $_POST[ $def[1] ] ) ? sanitize_text_field( wp_unslash( $_POST[ $def[1] ] ) ) : '';
    $args  = array();
    if ( ! wp_verify_nonce( $nonce, $def[2] ) ) {
        $args['kspoll_maint'] = $action . '_nonce';
    } elseif ( 'reset' === $action ) {
        update_option( 'kashiwazaki_poll_reset_timestamp', time() );
        $args['kspoll_maint'] = 'reset_done';
    } elseif ( 'sitemap' === $action ) {
        $args['kspoll_maint'] = kashiwazaki_poll_generate_sitemap_poll() ? 'sitemap_done' : 'sitemap_failed';
    } else {
        $ok = 0;
        $ng = 0;
        // 公開pollのみ対象（draft/private のデータを公開URL配下に生成しない）。
        $poll_ids = get_posts( array(
            'post_type'   => 'poll',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields'      => 'ids',
        ) );
        // パスワード保護されたデータセットはファイルを作らない（公開してよいものだけが対象）。
        $poll_ids = array_values( array_filter( $poll_ids, 'kashiwazaki_poll_is_public_poll' ) );
        foreach ( $poll_ids as $poll_id ) {
            if ( kashiwazaki_poll_generate_all_data_files( $poll_id, null, true ) ) {
                $ok++;
            } else {
                $ng++;
                error_log( '[Poll Batch Gen on Settings Page] Error generating files for poll ID: ' . $poll_id );
            }
        }
        // 公開していないデータセットのファイル（旧版で残ったものなど）を消す。
        $purge_failed = kashiwazaki_poll_purge_orphan_files();
        $args = array(
            'kspoll_maint' => 'batch_done',
            'kspoll_ok'    => $ok,
            'kspoll_ng'    => $ng,
            'kspoll_pf'    => $purge_failed,
            'kspoll_sm'    => kashiwazaki_poll_generate_sitemap_poll() ? '1' : '0',
        );
    }
    wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=kashiwazaki_poll_settings' ) ) );
    exit;
}

add_filter( 'removable_query_args', 'kashiwazaki_poll_maintenance_removable_args' );
function kashiwazaki_poll_maintenance_removable_args( $args ) {
    return array_merge( $args, array( 'kspoll_maint', 'kspoll_ok', 'kspoll_ng', 'kspoll_pf', 'kspoll_sm' ) );
}

function kashiwazaki_poll_settings_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'kashiwazaki-seo-poll' ) );
    }

    // メンテナンス操作の結果は、処理後のリダイレクト先の URL 引数から表示する
    // （送信をそのまま表示すると、再読み込みで同じ操作が再実行されるため）。
    $reset_date_message = '';
    $sitemap_regenerate_message = '';
    $batch_generate_message = '';
    $maint = isset( $_GET['kspoll_maint'] ) ? sanitize_key( wp_unslash( $_GET['kspoll_maint'] ) ) : '';
    $maint_ok = isset( $_GET['kspoll_ok'] ) ? absint( $_GET['kspoll_ok'] ) : 0;
    $maint_ng = isset( $_GET['kspoll_ng'] ) ? absint( $_GET['kspoll_ng'] ) : 0;
    $nonce_error = '<div class="error notice is-dismissible"><p>' . esc_html__( 'Nonce検証に失敗しました。もう一度お試しください。', 'kashiwazaki-seo-poll' ) . '</p></div>';
    switch ( $maint ) {
        case 'reset_done':
            $reset_date_message = '<div class="updated notice is-dismissible"><p>' . esc_html__( 'リセット日時を現在時刻に更新しました。', 'kashiwazaki-seo-poll' ) . '</p></div>';
            break;
        case 'reset_nonce':
            $reset_date_message = $nonce_error;
            break;
        case 'sitemap_done':
            $sitemap_regenerate_message = '<div class="updated notice is-dismissible"><p>' . esc_html__( 'サイトマップを再生成しました。', 'kashiwazaki-seo-poll' ) . '</p></div>';
            break;
        case 'sitemap_failed':
            $sitemap_regenerate_message = '<div class="error notice is-dismissible"><p>' . esc_html__( 'サイトマップを書き込めませんでした。サイトのフォルダに書き込めるか、サーバーの設定を確認してください。', 'kashiwazaki-seo-poll' ) . '</p></div>';
            break;
        case 'sitemap_nonce':
            $sitemap_regenerate_message = $nonce_error;
            break;
        case 'batch_done':
            if ( $maint_ok === 0 && $maint_ng === 0 ) {
                $batch_generate_message = '<div class="notice notice-info is-dismissible"><p>' . esc_html__( '処理対象のデータが見つかりませんでした。サイトマップは更新されました。', 'kashiwazaki-seo-poll' ) . '</p></div>';
            } elseif ( $maint_ng === 0 ) {
                $batch_generate_message = '<div class="updated notice is-dismissible"><p>' . sprintf( esc_html__( '%d 件のデータについてファイルの一括生成（更新）が完了しました。サイトマップも更新されました。', 'kashiwazaki-seo-poll' ), $maint_ok ) . '</p></div>';
            } elseif ( $maint_ok > 0 ) {
                $batch_generate_message = '<div class="notice notice-warning is-dismissible"><p>' . sprintf( esc_html__( '%1$d 件のデータについてファイルの生成を試みましたが、%2$d 件で書き込めないファイルがありました。サーバーの書き込み権限を確認してください。', 'kashiwazaki-seo-poll' ), $maint_ok + $maint_ng, $maint_ng ) . '</p></div>';
            } else {
                $batch_generate_message = '<div class="error notice is-dismissible"><p>' . sprintf( esc_html__( '%d 件のデータすべてでファイルを書き込めませんでした。サーバーの書き込み権限を確認してください。', 'kashiwazaki-seo-poll' ), $maint_ng ) . '</p></div>';
            }
            if ( isset( $_GET['kspoll_pf'] ) && absint( $_GET['kspoll_pf'] ) > 0 ) {
                $batch_generate_message .= '<div class="error notice is-dismissible"><p>' . sprintf( esc_html__( '公開していないデータセットのデータファイルが %d 件削除できませんでした。データファイルの保存用フォルダの書き込み権限を確認してください。', 'kashiwazaki-seo-poll' ), absint( $_GET['kspoll_pf'] ) ) . '</p></div>';
            }
            if ( isset( $_GET['kspoll_sm'] ) && '0' === $_GET['kspoll_sm'] ) {
                $batch_generate_message .= '<div class="error notice is-dismissible"><p>' . esc_html__( 'サイトマップを書き込めませんでした。サイトのフォルダに書き込めるか、サーバーの設定を確認してください。', 'kashiwazaki-seo-poll' ) . '</p></div>';
            }
            break;
        case 'batch_nonce':
            $batch_generate_message = $nonce_error;
            break;
    }
    ?>
    <div class="wrap kashiwazaki-poll-settings-wrap">
        <h1><?php esc_html_e( 'Kashiwazaki SEO Poll 基本設定', 'kashiwazaki-seo-poll' ); ?></h1>

        <?php settings_errors(); ?>

        <form method="post" action="options.php">
            <?php
            settings_fields( 'kashiwazaki_poll_options_group' );
            do_settings_sections( 'kashiwazaki_poll_settings_page_id' );
            submit_button( __( '設定を保存', 'kashiwazaki-seo-poll' ) );
            ?>
        </form>

        <h2 class="kspoll-maint-title"><?php esc_html_e( 'メンテナンス', 'kashiwazaki-seo-poll' ); ?></h2>
        <div class="kspoll-maint-grid">
        <div class="card kspoll-card">
        <h3><?php esc_html_e( '全データセットの投票制限を解除', 'kashiwazaki-seo-poll' ); ?></h3>
        <?php echo $reset_date_message; ?>
        <p><?php esc_html_e( 'すべてのデータセットで、これまで投票した人も改めて投票できるようになります。投票数は消えません。', 'kashiwazaki-seo-poll' ); ?></p>
        <form method="post">
            <?php wp_nonce_field( 'kashiwazaki_poll_reset_date_action', '_wpnonce_reset_date' ); ?>
            <input type="hidden" name="kashiwazaki_poll_reset_date_submit" value="1">
            <?php submit_button( __( 'リセット日時を現在に更新', 'kashiwazaki-seo-poll' ), 'secondary', 'kashiwazaki_poll_reset_date_submit_btn' ); ?>
        </form>
        <?php
        $ts = get_option( 'kashiwazaki_poll_reset_timestamp', 0 );
        if ( $ts ) {
            echo '<p>' . sprintf( esc_html__( '現在のリセット日時: %s', 'kashiwazaki-seo-poll' ), wp_date( 'Y-m-d H:i:s', $ts ) ) . '</p>';
        } else {
            echo '<p>' . esc_html__( 'まだリセット日時は設定されていません。', 'kashiwazaki-seo-poll' ) . '</p>';
        }
        ?>

        </div>

        <div class="card kspoll-card">
        <h3><?php esc_html_e( 'サイトマップ', 'kashiwazaki-seo-poll' ); ?></h3>
        <?php echo $sitemap_regenerate_message; ?>
        <?php
        $sitemap_url = home_url( 'sitemap-poll-datasets.xml' );
        $sitemap_file_path = ABSPATH . 'sitemap-poll-datasets.xml';
        $sitemap_exists = file_exists( $sitemap_file_path );
        ?>
        <p>
            <strong><?php esc_html_e( 'サイトマップURL:', 'kashiwazaki-seo-poll' ); ?></strong>
            <?php if ( $sitemap_exists ) : ?>
                <a href="<?php echo esc_url( $sitemap_url ); ?>" target="_blank"><?php echo esc_html( $sitemap_url ); ?></a>
                <span style="color: #00a32a; margin-left: 10px;">✓ <?php esc_html_e( 'ファイルが存在します', 'kashiwazaki-seo-poll' ); ?></span>
            <?php else : ?>
                <?php echo esc_html( $sitemap_url ); ?>
                <span style="color: #d63638; margin-left: 10px;">⚠ <?php esc_html_e( 'ファイルが存在しません', 'kashiwazaki-seo-poll' ); ?></span>
            <?php endif; ?>
        </p>
        <p><?php esc_html_e( 'データセット専用のサイトマップです。投票時やデータ一括生成時に自動更新されますが、手動で再生成することもできます。', 'kashiwazaki-seo-poll' ); ?></p>
        <p style="color: #666; font-size: 0.9em;"><?php esc_html_e( '※ 旧sitemap-poll.xmlは廃止されました。Google Search Consoleには上記URLを登録してください。', 'kashiwazaki-seo-poll' ); ?></p>
        <form method="post">
            <?php wp_nonce_field( 'kashiwazaki_poll_sitemap_regenerate_action', '_wpnonce_sitemap_regenerate' ); ?>
            <input type="hidden" name="kashiwazaki_poll_sitemap_regenerate_submit" value="1">
            <?php submit_button( __( 'サイトマップを再生成する', 'kashiwazaki-seo-poll' ), 'secondary', 'kashiwazaki_poll_sitemap_regenerate_submit_btn' ); ?>
        </form>

        </div>

        <div class="card kspoll-card">
        <h3><?php esc_html_e( 'データファイルの一括生成', 'kashiwazaki-seo-poll' ); ?></h3>
        <?php echo $batch_generate_message; ?>
        <p><?php esc_html_e( 'このボタンをクリックすると、全てのデータについて、最新の集計結果に基づきデータファイル（CSV, XML, YAML, JSON, SVG）を生成または更新します。同時に、サイトマップも更新されます。', 'kashiwazaki-seo-poll' ); ?></p>
        <p><?php esc_html_e( 'データ数が多い場合、処理に時間がかかることがあります。', 'kashiwazaki-seo-poll' ); ?></p>
        <form method="post">
            <?php wp_nonce_field( 'kashiwazaki_poll_batch_generate_action', '_wpnonce_batch_generate' ); ?>
            <input type="hidden" name="kashiwazaki_poll_batch_generate_submit" value="1">
            <?php submit_button( __( 'データファイルを一括生成する', 'kashiwazaki-seo-poll' ), 'secondary', 'kashiwazaki_poll_batch_generate_submit_btn' ); ?>
        </form>
        </div>
        </div>

    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        function toggleCreatorFields() {
            var creatorType = $('#kashiwazaki_poll_creator_type').val();

            // Hide all fields first
            $('#creator_person_fields').hide();
            $('#creator_organization_fields').hide();

            // Show appropriate fields based on selection
            if (creatorType === 'person_only') {
                $('#creator_person_fields').show();
            } else if (creatorType === 'organization_only') {
                $('#creator_organization_fields').show();
            } else if (creatorType === 'both') {
                $('#creator_person_fields').show();
                $('#creator_organization_fields').show();
            }
        }

        // Initialize on page load
        toggleCreatorFields();

        // Handle change event
        $('#kashiwazaki_poll_creator_type').change(toggleCreatorFields);
    });
    </script>

    <?php
}
