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
        '構造化データ設定',
        null,
        'kashiwazaki_poll_settings_page_id'
    );

    add_settings_field(
        'breadcrumb_structured_data_field',
        'パンくずリスト構造化データ',
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
        'structured_data_creator_type_field',
        'Creator設定',
        'kashiwazaki_poll_settings_field_creator_type_cb',
        'kashiwazaki_poll_settings_page_id',
        'kashiwazaki_poll_settings_section_structured_data',
        array( 'label_for' => 'kashiwazaki_poll_creator_type' )
    );

    add_settings_field(
        'structured_data_creator_person_field',
        'Person Creator設定',
        'kashiwazaki_poll_settings_field_creator_person_cb',
        'kashiwazaki_poll_settings_page_id',
        'kashiwazaki_poll_settings_section_structured_data'
    );

    add_settings_field(
        'structured_data_creator_organization_field',
        'Organization Creator設定',
        'kashiwazaki_poll_settings_field_creator_organization_cb',
        'kashiwazaki_poll_settings_page_id',
        'kashiwazaki_poll_settings_section_structured_data'
    );

    add_settings_field(
        'dataset_page_title_field',
        'データセットページタイトル',
        'kashiwazaki_poll_settings_field_dataset_page_title_cb',
        'kashiwazaki_poll_settings_page_id',
        'kashiwazaki_poll_settings_section_structured_data',
        array( 'label_for' => 'kashiwazaki_poll_dataset_page_title' )
    );

    add_settings_field(
        'dataset_page_color_theme_field',
        'データセットページカラーテーマ',
        'kashiwazaki_poll_settings_field_color_theme_cb',
        'kashiwazaki_poll_settings_page_id',
        'kashiwazaki_poll_settings_section_structured_data',
        array( 'label_for' => 'kashiwazaki_poll_dataset_color_theme' )
    );

    add_settings_field(
        'dataset_spatial_coverage_field',
        'データセット地理的範囲',
        'kashiwazaki_poll_settings_field_dataset_spatial_coverage_cb',
        'kashiwazaki_poll_settings_page_id',
        'kashiwazaki_poll_settings_section_structured_data',
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

function kashiwazaki_poll_settings_field_creator_type_cb() {
    $options = get_option( 'kashiwazaki_poll_settings', array( 'creator_type' => 'organization_only' ) );
    $creator_type = $options['creator_type'];
    $html = '<select name="kashiwazaki_poll_settings[creator_type]" id="kashiwazaki_poll_creator_type">';
    $html .= '<option value="organization_only" ' . selected( $creator_type, 'organization_only', false ) . '>' . esc_html__( 'Organization Only', 'kashiwazaki-seo-poll' ) . '</option>';
    $html .= '<option value="person_only" ' . selected( $creator_type, 'person_only', false ) . '>' . esc_html__( 'Person Only', 'kashiwazaki-seo-poll' ) . '</option>';
    $html .= '<option value="both" ' . selected( $creator_type, 'both', false ) . '>' . esc_html__( 'Both', 'kashiwazaki-seo-poll' ) . '</option>';
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
        'creator_organization_email' => get_bloginfo('admin_email')
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
            'description' => '白背景、グレーアクセント'
        ),
        'blue' => array(
            'name' => 'ブルー',
            'description' => '青ヘッダー、白背景'
        ),
        'green' => array(
            'name' => 'グリーン',
            'description' => '緑ヘッダー、白背景'
        ),
        'orange' => array(
            'name' => 'オレンジ',
            'description' => 'オレンジヘッダー、白背景'
        ),
        'purple' => array(
            'name' => 'パープル',
            'description' => '紫ヘッダー、白背景'
        ),
        'dark' => array(
            'name' => 'ダーク',
            'description' => '黒背景、白文字'
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
    echo '<p class="description">' . esc_html__( 'データセットページの色合いを選択してください。', 'kashiwazaki-seo-poll' ) . '</p>';
}

function kashiwazaki_poll_settings_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'kashiwazaki-seo-poll' ) );
    }

    $reset_date_message = '';
    if ( isset( $_POST['kashiwazaki_poll_reset_date_submit'] ) && isset( $_POST['_wpnonce_reset_date'] ) ) {
        if ( wp_verify_nonce( $_POST['_wpnonce_reset_date'], 'kashiwazaki_poll_reset_date_action' ) ) {
            update_option( 'kashiwazaki_poll_reset_timestamp', time() );
            $reset_date_message = '<div id="message" class="updated notice is-dismissible"><p>' . esc_html__( 'リセット日時を現在時刻に更新しました。', 'kashiwazaki-seo-poll' ) . '</p></div>';
        } else {
            $reset_date_message = '<div id="message" class="error notice is-dismissible"><p>' . esc_html__( 'Nonce検証に失敗しました。もう一度お試しください。', 'kashiwazaki-seo-poll' ) . '</p></div>';
        }
    }

    $sitemap_regenerate_message = '';
    if ( isset( $_POST['kashiwazaki_poll_sitemap_regenerate_submit'] ) && isset( $_POST['_wpnonce_sitemap_regenerate'] ) ) {
        if ( wp_verify_nonce( $_POST['_wpnonce_sitemap_regenerate'], 'kashiwazaki_poll_sitemap_regenerate_action' ) ) {
            kashiwazaki_poll_generate_sitemap_poll();
            $sitemap_regenerate_message = '<div id="message" class="updated notice is-dismissible"><p>' . esc_html__( 'サイトマップを再生成しました。', 'kashiwazaki-seo-poll' ) . '</p></div>';
        } else {
            $sitemap_regenerate_message = '<div id="message" class="error notice is-dismissible"><p>' . esc_html__( 'Nonce検証に失敗しました。もう一度お試しください。', 'kashiwazaki-seo-poll' ) . '</p></div>';
        }
    }

    $batch_generate_message = '';
    if ( isset( $_POST['kashiwazaki_poll_batch_generate_submit'] ) && isset( $_POST['_wpnonce_batch_generate'] ) ) {
        if ( wp_verify_nonce( $_POST['_wpnonce_batch_generate'], 'kashiwazaki_poll_batch_generate_action' ) ) {
            $generation_triggered = true;
            $generated_count = 0;
            $error_count = 0;

            // 公開pollのみ対象（draft/private のデータを公開URL配下に生成しない）。
            $poll_ids = get_posts( array(
                'post_type'      => 'poll',
                'post_status'    => 'publish',
                'numberposts'    => -1,
                'fields'         => 'ids',
            ) );

            if ( ! empty( $poll_ids ) ) {
                foreach ( $poll_ids as $poll_id ) {
                    $counts = get_post_meta($poll_id, '_kashiwazaki_poll_counts', true);
                    $result = kashiwazaki_poll_generate_all_data_files( $poll_id, $counts );
                    if ( $result ) {
                        $generated_count++;
                    } else {
                        $error_count++;
                        error_log("[Poll Batch Gen on Settings Page] Error generating files for poll ID: " . $poll_id);
                    }
                }
            } else {
                 error_log("[Poll Batch Gen on Settings Page] No polls found to generate data for.");
            }

            kashiwazaki_poll_generate_sitemap_poll();

            if ( $generated_count > 0 && $error_count === 0 ) {
                $batch_generate_message = '<div id="message" class="updated notice is-dismissible"><p>' . sprintf( esc_html__( '%d 件のデータについてファイルの一括生成（更新）が完了しました。サイトマップも更新されました。', 'kashiwazaki-seo-poll' ), $generated_count ) . '</p></div>';
            } elseif ( $generated_count > 0 && $error_count > 0 ) {
                 $batch_generate_message = '<div id="message" class="notice notice-warning is-dismissible"><p>' . sprintf( esc_html__( '%d 件のデータについてファイルの生成を試みましたが、%d 件でエラーが発生しました。詳細はエラーログを確認してください。サイトマップは更新されました。', 'kashiwazaki-seo-poll' ), $generated_count + $error_count, $error_count ) . '</p></div>';
            } elseif ( $generated_count === 0 && $error_count > 0 ) {
                 $batch_generate_message = '<div id="message" class="error notice is-dismissible"><p>' . sprintf( esc_html__( '%d 件のデータでファイルの生成中にエラーが発生しました。詳細はエラーログを確認してください。サイトマップは更新されました。', 'kashiwazaki-seo-poll' ), $error_count ) . '</p></div>';
            } elseif ( $generated_count === 0 && $error_count === 0 && empty($poll_ids) ) {
                 $batch_generate_message = '<div id="message" class="notice notice-info is-dismissible"><p>' . esc_html__( '処理対象のデータが見つかりませんでした。サイトマップは更新されました。', 'kashiwazaki-seo-poll' ) . '</p></div>';
            } elseif ( $generated_count === 0 && $error_count === 0 && !empty($poll_ids) && $generation_triggered ) {
                 $batch_generate_message = '<div id="message" class="notice notice-info is-dismissible"><p>' . esc_html__( 'データファイルは既に最新か、生成対象のデータがありませんでした。サイトマップは更新されました。', 'kashiwazaki-seo-poll' ) . '</p></div>';
            }

        } else {
            $batch_generate_message = '<div id="message" class="error notice is-dismissible"><p>' . esc_html__( 'Nonce検証に失敗しました。もう一度お試しください。', 'kashiwazaki-seo-poll' ) . '</p></div>';
        }
    }

    ?>
    <div class="wrap kashiwazaki-poll-settings-wrap">
        <h1><?php esc_html_e( 'Kashiwazaki SEO Poll 基本設定', 'kashiwazaki-seo-poll' ); ?></h1>

        <div class="notice notice-info" style="border-left-color: #0073aa; margin-top: 20px;">
            <p style="margin: 10px 0;">
                <strong>📋 データ管理</strong> -
                新しいデータの作成や既存データの編集は
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=poll')); ?>" class="button button-primary" style="margin-left: 10px;">📋 投稿一覧</a>
                から行えます。
            </p>
        </div>

        <?php settings_errors(); ?>

        <form method="post" action="options.php">
            <?php
            settings_fields( 'kashiwazaki_poll_options_group' );
            do_settings_sections( 'kashiwazaki_poll_settings_page_id' );
            submit_button( __( '設定を保存', 'kashiwazaki-seo-poll' ) );
            ?>
        </form>

        <hr>

        <h2><?php esc_html_e( '投票制限の解除', 'kashiwazaki-seo-poll' ); ?></h2>
        <?php echo $reset_date_message; ?>
        <p><?php esc_html_e( 'このボタンを押すと、これまで投票した人も改めて投票できるようになります。投票データ自体は消えずにそのまま残ります。', 'kashiwazaki-seo-poll' ); ?></p>
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

        <hr>

        <h2><?php esc_html_e( 'サイトマップ', 'kashiwazaki-seo-poll' ); ?></h2>
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

        <hr>

        <h2><?php esc_html_e( 'データセット一括生成', 'kashiwazaki-seo-poll' ); ?></h2>
        <?php echo $batch_generate_message; ?>
        <p><?php esc_html_e( 'このボタンをクリックすると、全てのデータについて、最新の集計結果に基づきデータファイル（CSV, XML, YAML, JSON, SVG）を生成または更新します。同時に、サイトマップも更新されます。', 'kashiwazaki-seo-poll' ); ?></p>
        <p><?php esc_html_e( 'データ数が多い場合、処理に時間がかかることがあります。', 'kashiwazaki-seo-poll' ); ?></p>
        <form method="post">
            <?php wp_nonce_field( 'kashiwazaki_poll_batch_generate_action', '_wpnonce_batch_generate' ); ?>
            <input type="hidden" name="kashiwazaki_poll_batch_generate_submit" value="1">
            <?php submit_button( __( 'データファイルを一括生成する', 'kashiwazaki-seo-poll' ), 'primary', 'kashiwazaki_poll_batch_generate_submit_btn' ); ?>
        </form>

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
