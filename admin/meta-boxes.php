<?php
/**
 * Kashiwazaki Poll Meta Boxes (Admin)
 *
 * このファイルは、アンケート投稿（poll）の編集画面で
 * アンケート設定、詳細な説明、ライセンス選択、見出しレベル選択、投票データリセットのメタボックスを定義します。
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'kashiwazaki_poll_options_metabox' ) ) {

    add_action( 'add_meta_boxes_poll', function() {
        add_meta_box(
            'kashiwazaki_poll_options',
            '投票設定',
            'kashiwazaki_poll_options_metabox',
            'poll',
            'normal',
            'high'
        );
    } );

    function kashiwazaki_poll_options_metabox( $post ) {
        wp_nonce_field( 'kashiwazaki_poll_save_metabox', 'kashiwazaki_poll_nonce' );

        $options = get_post_meta( $post->ID, '_kashiwazaki_poll_options', true );
        $options = is_array( $options ) ? implode( "\n", $options ) : '';

        $poll_type = get_post_meta( $post->ID, '_kashiwazaki_poll_type', true );
        if ( ! in_array( $poll_type, array( 'single', 'multiple' ) ) ) {
            $poll_type = 'multiple';
        }

        $description = get_post_meta( $post->ID, '_kashiwazaki_poll_description', true );

        $license = get_post_meta( $post->ID, '_kashiwazaki_poll_license', true );
        if ( empty( $license ) ) {
            $license = 'https://creativecommons.org/licenses/by/4.0/';
        }

        $heading_level = get_post_meta( $post->ID, '_kashiwazaki_poll_heading_level', true );
        if ( empty( $heading_level ) ) {
            $heading_level = 'h3';
        }
        ?>

        <p>投票形式</p>
        <label>
            <input type="radio" name="kashiwazaki_poll_type" value="multiple" <?php checked( $poll_type, 'multiple' ); ?>>
            複数選択
        </label>
        <p class="description" style="margin-left:25px;">
            ※ 回答者は複数の選択肢を同時に選択可能です。（例：趣味を複数選ぶ場合）
        </p>

        <label>
            <input type="radio" name="kashiwazaki_poll_type" value="single" <?php checked( $poll_type, 'single' ); ?>>
            単一選択
        </label>
        <p class="description" style="margin-left:25px;">
            ※ 回答者は1つの選択肢のみ選択可能です。（例：好きな色を1つだけ選ぶ場合）
        </p>

        <hr>
        <p>選択肢（1行につき1つ）</p>
        <textarea name="kashiwazaki_poll_options" rows="6" style="width:100%;"><?php echo esc_textarea( $options ); ?></textarea>

        <hr>
        <p>詳細な説明 (description)</p>
        <textarea name="kashiwazaki_poll_description" rows="6" style="width:100%;"><?php echo esc_textarea( $description ); ?></textarea>
        <p class="description" style="margin-left:25px;">
            ※ 150文字以上設定しない場合は、構造化マークアップは出力されません。
        </p>

        <hr>
        <p>ライセンス</p>
        <select name="kashiwazaki_poll_license" style="width:100%;">
            <option value="https://creativecommons.org/licenses/by/4.0/" <?php selected( $license, 'https://creativecommons.org/licenses/by/4.0/' ); ?>>CC BY 4.0</option>
            <option value="https://creativecommons.org/licenses/by-sa/4.0/" <?php selected( $license, 'https://creativecommons.org/licenses/by-sa/4.0/' ); ?>>CC BY-SA 4.0</option>
            <option value="https://creativecommons.org/licenses/by-nc/4.0/" <?php selected( $license, 'https://creativecommons.org/licenses/by-nc/4.0/' ); ?>>CC BY-NC 4.0</option>
            <option value="https://creativecommons.org/publicdomain/zero/1.0/" <?php selected( $license, 'https://creativecommons.org/publicdomain/zero/1.0/' ); ?>>CC0 1.0</option>
            <option value="https://creativecommons.org/licenses/by-nc-sa/4.0/" <?php selected( $license, 'https://creativecommons.org/licenses/by-nc-sa/4.0/' ); ?>>CC BY-NC-SA 4.0</option>
            <option value="https://creativecommons.org/licenses/by-nd/4.0/" <?php selected( $license, 'https://creativecommons.org/licenses/by-nd/4.0/' ); ?>>CC BY-ND 4.0</option>
            <option value="https://creativecommons.org/licenses/by-nc-nd/4.0/" <?php selected( $license, 'https://creativecommons.org/licenses/by-nc-nd/4.0/' ); ?>>CC BY-NC-ND 4.0</option>
            <option value="https://creativecommons.org/publicdomain/mark/1.0/" <?php selected( $license, 'https://creativecommons.org/publicdomain/mark/1.0/' ); ?>>Public Domain Mark 1.0</option>
            <option value="https://creativecommons.org/licenses/by/3.0/" <?php selected( $license, 'https://creativecommons.org/licenses/by/3.0/' ); ?>>CC BY 3.0</option>
            <option value="https://creativecommons.org/licenses/by/2.0/" <?php selected( $license, 'https://creativecommons.org/licenses/by/2.0/' ); ?>>CC BY 2.0</option>
        </select>
        <p class="description" style="margin-left:25px;">
            ※ 適用するライセンスを選択してください。
        </p>

        <hr>
        <p>見出しレベル (Heading Level)</p>
        <select name="kashiwazaki_poll_heading_level" style="width:100px;">
            <?php
            $levels = array( 'h1','h2','h3','h4','h5','h6' );
            foreach ( $levels as $lvl ) {
                echo '<option value="'. esc_attr($lvl) .'" ' . selected( $heading_level, $lvl, false ) . '>'. esc_html($lvl) .'</option>';
            }
            ?>
        </select>
        <p class="description" style="margin-left:25px;">
            ※ 質問文をどのレベルの見出しタグで表示するかを選択してください。
        </p>

        <hr>
        <p>データセットバージョン</p>
        <?php
        $dataset_version = get_post_meta( $post->ID, 'dataset_version', true );
        if ( empty( $dataset_version ) ) {
            $dataset_version = '1.0';
        }
        ?>
        <input type="text" name="dataset_version" value="<?php echo esc_attr( $dataset_version ); ?>" style="width:100px;" />
        <p class="description" style="margin-left:25px;">
            ※ データセットのバージョンを設定してください。
        </p>

        <hr>
        <p>データセットキーワード</p>
        <?php
        $dataset_keywords = get_post_meta( $post->ID, 'dataset_keywords', true );
        ?>
        <input type="text" name="dataset_keywords" value="<?php echo esc_attr( $dataset_keywords ); ?>" style="width:100%;" />
        <p class="description" style="margin-left:25px;">
            ※ データセットに関連するキーワードをカンマ区切りで入力してください。例：マーケティング,消費者行動,統計
        </p>

        <hr>
        <p style="color: #c00; font-weight: bold; margin-bottom: 0.5em;">【重要】投票エラーに関する注意</p>
        <div style="background-color: #fff9e6; border: 1px solid #ffe599; padding: 10px 15px; margin-bottom: 1em;">
            <p style="margin-top:0;">フォームを表示したページで「不正なリクエストです。（nonce）」といったエラーが出て投票できない場合、多くは<strong>ページキャッシュ</strong>が原因と考えられます。</p>
            <p>このエラーが発生した場合、以下の対応をお試しください:</p>
            <ul style="margin-left: 1.5em; list-style: disc;">
                <li>ご利用のキャッシュプラグイン（例: WP-Optimize, WP Super Cache など）でキャッシュを削除する。</li>
                <li>サーバー側で有効になっているキャッシュ（ホスティング会社の機能）をクリアする。</li>
                <li>CDNサービス（例: Cloudflare など）をご利用の場合、CDN上のキャッシュをパージ（削除）する。</li>
                <li><strong>【推奨】</strong>キャッシュプラグインやCDNの設定で、<strong>このフォームを表示しているページのURLをキャッシュ対象から除外する</strong>設定を行う。</li>
            </ul>
            <p style="margin-bottom:0;">※ 設定方法はご利用のプラグインやサービスにより異なります。詳細は各ドキュメントをご参照ください。</p>
        </div>


        <hr>
        <?php
        $shortcode_str = '[tk_poll id="' . $post->ID . '"]';
        ?>
        <p>ショートコード</p>
        <div style="margin-left:25px;">
            <input type="text" id="kashiwazaki_poll_shortcode_field" readonly style="width:calc(100% - 90px);" value="<?php echo esc_attr( $shortcode_str ); ?>">
            <button type="button" class="button" id="kashiwazaki_poll_copy_btn">コピー</button>
            <p class="description">投稿・固定ページ本文に貼り付けてください。</p>
        </div>

        <?php
        // 現在のショートコード使用記事一覧を表示
        if (function_exists('kashiwazaki_poll_get_shortcode_usage')) {
            $usage_posts = kashiwazaki_poll_get_shortcode_usage($post->ID);
            ?>
            <div style="margin-top: 15px; padding: 12px; background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px;">
                <p style="margin: 0 0 10px 0; font-weight: bold; color: #333;">掲載中のページ:</p>
                <?php if (!empty($usage_posts)): ?>
                    <div class="usage-articles-list">
                        <?php foreach ($usage_posts as $usage_post):
                            $edit_url = get_edit_post_link($usage_post->ID);
                            $view_url = get_permalink($usage_post->ID);
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

                            // ステータスの日本語化
                            $status_translations = array(
                                'publish' => '公開済み',
                                'draft' => '下書き',
                                'private' => '非公開',
                                'pending' => 'レビュー待ち'
                            );
                            $status_label = isset($status_translations[$usage_post->post_status]) ?
                                           $status_translations[$usage_post->post_status] : $usage_post->post_status;
                        ?>
                            <div style="margin-bottom: 10px; padding: 8px; background-color: #fff; border: 1px solid #ddd; border-radius: 3px;">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                                    <?php if ($edit_url): ?>
                                        <a href="<?php echo esc_url($edit_url); ?>" style="text-decoration: none; color: #0073aa; font-weight: 500;">
                                            <?php echo esc_html($usage_post->post_title ?: '(タイトルなし)'); ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #333; font-weight: 500;">
                                            <?php echo esc_html($usage_post->post_title ?: '(タイトルなし)'); ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($usage_post->post_status !== 'publish'): ?>
                                        <span style="color: #d63638; font-size: 12px; font-weight: 500;">
                                            (<?php echo esc_html($status_label); ?>)
                                        </span>
                                    <?php endif; ?>

                                    <?php if (isset($usage_post->shortcode_count) && $usage_post->shortcode_count > 1): ?>
                                        <span style="color: #d63638; font-size: 12px; font-weight: 500;">
                                            (<?php echo $usage_post->shortcode_count; ?>回使用)
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div style="font-size: 12px; color: #666; display: flex; align-items: center; gap: 15px;">
                                    <span><strong>種別:</strong> <?php echo esc_html($post_type_label); ?></span>
                                    <span><strong>更新:</strong> <?php echo get_the_modified_date('Y/m/d H:i', $usage_post->ID); ?></span>

                                    <?php if ($usage_post->post_status === 'publish' && $view_url): ?>
                                        <a href="<?php echo esc_url($view_url); ?>" target="_blank" style="color: #0073aa; text-decoration: none; font-size: 11px;">
                                            表示 ↗
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px; padding-top: 8px; border-top: 1px solid #ddd;">
                        <p style="margin: 0; font-size: 12px; color: #666;">
                            <strong>合計:</strong> <?php echo count($usage_posts); ?>件の記事で使用されています
                        </p>

                        <?php
                        $clear_cache_url = wp_nonce_url(
                            admin_url('post.php?post=' . $post->ID . '&action=edit&cache_cleared=1&clear_poll_usage_cache=' . $post->ID),
                            'clear_poll_usage_cache_' . $post->ID
                        );
                        ?>
                        <a href="<?php echo esc_url($clear_cache_url); ?>"
                           style="font-size: 11px; color: #0073aa; text-decoration: none;"
                           onclick="return confirm('この記事の使用状況キャッシュをクリアしますか？')">
                            🔄 キャッシュ更新
                        </a>
                    </div>
                <?php else: ?>
                    <p style="margin: 0; color: #666; font-style: italic;">
                        現在このショートコードを掲載しているページはありません。
                    </p>
                <?php endif; ?>
            </div>
            <?php
        }
        ?>

        <script>
        (function(){
            document.addEventListener('click', function(e){
                if(e.target && e.target.id === 'kashiwazaki_poll_copy_btn'){
                    var inputEl = document.getElementById('kashiwazaki_poll_shortcode_field');
                    if(!inputEl) return;
                    inputEl.select();
                    document.execCommand('copy');
                    alert('ショートコードをコピーしました。');
                }
            });
        })();
        </script>
        <?php
    }
}

if ( ! function_exists( 'kashiwazaki_poll_save_metabox' ) ) {
    add_action( 'save_post_poll', 'kashiwazaki_poll_save_metabox' );
    function kashiwazaki_poll_save_metabox( $post_id ) {
        if ( ! isset( $_POST['kashiwazaki_poll_nonce'] ) ||
             ! wp_verify_nonce( $_POST['kashiwazaki_poll_nonce'], 'kashiwazaki_poll_save_metabox' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( 'poll' !== get_post_type($post_id) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $poll_type = isset( $_POST['kashiwazaki_poll_type'] ) ? sanitize_key( $_POST['kashiwazaki_poll_type'] ) : 'multiple';
        if ( ! in_array( $poll_type, array( 'single', 'multiple' ) ) ) {
            $poll_type = 'multiple';
        }
        update_post_meta( $post_id, '_kashiwazaki_poll_type', $poll_type );

        $raw_options = isset( $_POST['kashiwazaki_poll_options'] ) ? sanitize_textarea_field( $_POST['kashiwazaki_poll_options'] ) : '';
        $arr = explode( "\n", $raw_options );
        $cleaned = array();
        foreach ( $arr as $line ) {
            $line = trim( $line );
            if ( $line !== '' ) {
                $cleaned[] = sanitize_text_field($line);
            }
        }
        update_post_meta( $post_id, '_kashiwazaki_poll_options', $cleaned );

        $description = isset( $_POST['kashiwazaki_poll_description'] ) ? sanitize_textarea_field( $_POST['kashiwazaki_poll_description'] ) : '';
        update_post_meta( $post_id, '_kashiwazaki_poll_description', $description );

        $license = isset( $_POST['kashiwazaki_poll_license'] ) ? esc_url_raw( $_POST['kashiwazaki_poll_license'] ) : '';
        update_post_meta( $post_id, '_kashiwazaki_poll_license', $license );

        $heading_level = isset( $_POST['kashiwazaki_poll_heading_level'] ) ? sanitize_key( $_POST['kashiwazaki_poll_heading_level'] ) : 'h3';
        if ( ! in_array( $heading_level, array( 'h1','h2','h3','h4','h5','h6' ) ) ) {
            $heading_level = 'h3';
        }
        update_post_meta( $post_id, '_kashiwazaki_poll_heading_level', $heading_level );

        $dataset_version = isset( $_POST['dataset_version'] ) ? sanitize_text_field( $_POST['dataset_version'] ) : '1.0';
        update_post_meta( $post_id, 'dataset_version', $dataset_version );

        $dataset_keywords = isset( $_POST['dataset_keywords'] ) ? sanitize_text_field( $_POST['dataset_keywords'] ) : '';
        update_post_meta( $post_id, 'dataset_keywords', $dataset_keywords );
    }
}

// 投稿者メタボックスをサイドバーに移動
add_action( 'add_meta_boxes_poll', function() {
    remove_meta_box( 'authordiv', 'poll', 'normal' );
    add_meta_box(
        'authordiv',
        '投稿者',
        'post_author_meta_box',
        'poll',
        'side',
        'high'
    );
});

if ( ! function_exists( 'kashiwazaki_poll_reset_data_metabox' ) ) {
    add_action( 'add_meta_boxes_poll', function() {
        add_meta_box(
            'kashiwazaki_poll_reset_data',
            '投票データリセット',
            'kashiwazaki_poll_reset_data_metabox',
            'poll',
            'side',
            'default'
        );
    } );

        function kashiwazaki_poll_reset_data_metabox( $post ) {
        wp_nonce_field( 'kashiwazaki_poll_reset_data_action', 'kashiwazaki_poll_reset_data_nonce' );
        $counts = get_post_meta( $post->ID, '_kashiwazaki_poll_counts', true );
        $total_votes = 0;
        if ( is_array($counts) && !empty($counts) ) {
             $total_votes = array_sum( $counts );
        }
        $voted_ips = get_post_meta( $post->ID, '_kashiwazaki_poll_voted_ips', true );
        $voter_count = is_array($voted_ips) ? count($voted_ips) : 0;

        echo '<p>現在の総投票数 (延べ): ' . esc_html( $total_votes ) . ' 票</p>';
        echo '<p>現在の投票者数 (IP基準): ' . esc_html( $voter_count ) . ' 人</p>';
        echo '<p>このボタンを押すと、これまでの投票データ（票数と投票者IP）が完全にリセットされます。<br><strong>この操作は元に戻せません。</strong></p>';
        echo '<p><input type="submit" name="kashiwazaki_poll_reset_data_submit" value="集計データを全削除する" class="button button-secondary delete" onclick="return confirm(\'本当にこの集計データをすべて削除してもよろしいですか？\\n\\nこの操作は元に戻せません。\');"></p>';
    }
}

if ( ! function_exists( 'kashiwazaki_poll_reset_data_save' ) ) {
    add_action( 'save_post_poll', 'kashiwazaki_poll_reset_data_save' );
    function kashiwazaki_poll_reset_data_save( $post_id ) {
        if ( ! isset( $_POST['kashiwazaki_poll_reset_data_submit'] ) ) {
            return;
        }
        if ( ! isset( $_POST['kashiwazaki_poll_reset_data_nonce'] ) || ! wp_verify_nonce( $_POST['kashiwazaki_poll_reset_data_nonce'], 'kashiwazaki_poll_reset_data_action' ) ) {
             add_filter( 'redirect_post_location', function( $location ) {
                return add_query_arg( 'kashiwazaki_poll_reset_error', 'nonce', $location );
             } );
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
             add_filter( 'redirect_post_location', function( $location ) {
                return add_query_arg( 'kashiwazaki_poll_reset_error', 'permission', $location );
             } );
            return;
        }
        if ( 'poll' !== get_post_type($post_id) ) {
             return;
        }

        delete_post_meta( $post_id, '_kashiwazaki_poll_counts' );
        delete_post_meta( $post_id, '_kashiwazaki_poll_voted_ips' );

        add_filter( 'redirect_post_location', function( $location ) {
            $location = remove_query_arg( array('kashiwazaki_poll_reset_error'), $location );
            return add_query_arg( 'kashiwazaki_poll_reset_data', 'success', $location );
        } );
    }
}

add_action( 'admin_notices', function() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'poll' ) {
        return;
    }

    if ( isset( $_GET['post'] ) ) {
        if ( isset( $_GET['kashiwazaki_poll_reset_data'] ) && $_GET['kashiwazaki_poll_reset_data'] === 'success' ) {
            echo '<div class="notice notice-success is-dismissible"><p>投票データがリセットされました。</p></div>';
        }
        if ( isset( $_GET['kashiwazaki_poll_reset_error'] ) ) {
             $message = '';
             switch ( $_GET['kashiwazaki_poll_reset_error'] ) {
                 case 'nonce':
                     $message = '投票データのリセットに失敗しました。(Nonceエラー)';
                     break;
                 case 'permission':
                     $message = '投票データのリセット権限がありません。';
                     break;
                 default:
                     $message = '投票データのリセット中にエラーが発生しました。';
             }
             echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }
});

// 投稿一覧ページに基本設定へのリンクを追加（admin_notices）
add_action('admin_notices', function() {
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'edit-poll') {
        return;
    }

    $settings_url = admin_url('admin.php?page=kashiwazaki_poll_settings');
    ?>
    <div class="notice notice-info" style="border-left-color: #0073aa;">
        <p style="margin: 10px 0;">
            <strong>📋 データ管理</strong> -
            データセットページのタイトルやカラーテーマなどの設定は
            <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary" style="margin-left: 10px;">⚙ 基本設定</a>
            から変更できます。
        </p>
    </div>
    <?php
});
