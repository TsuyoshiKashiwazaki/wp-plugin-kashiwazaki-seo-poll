<?php
/**
 * Kashiwazaki SEO Poll 編集画面（管理画面）
 *
 * データセット（poll）の編集画面に、本プラグインの入力パネルを配置する。
 * - タイトル直下: 設問と選択肢 / 投票数 / データセット情報 / ショートコードと掲載ページ
 * - 右サイドバー最上部: 投票の受付と集計（受付の締切・集計・全削除）→ 公開ボックス
 * 他のプラグインが追加するメタボックスは、これらの下に並ぶ。
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** タイトル直下に描画する独自コンテキスト名。 */
if ( ! defined( 'KASHIWAZAKI_POLL_TOP_CONTEXT' ) ) {
    define( 'KASHIWAZAKI_POLL_TOP_CONTEXT', 'kashiwazaki_poll_top' );
}

/** 投票数として入力できる上限。 */
if ( ! defined( 'KASHIWAZAKI_POLL_MAX_COUNT' ) ) {
    define( 'KASHIWAZAKI_POLL_MAX_COUNT', 999999999 );
}

/**
 * 本プラグインが位置を固定するメタボックスのID。
 *
 * @return string[]
 */
function kashiwazaki_poll_pinned_metabox_ids() {
    return array(
        'kashiwazaki_poll_question',
        'kashiwazaki_poll_counts',
        'kashiwazaki_poll_dataset',
        'kashiwazaki_poll_embed',
        'kashiwazaki_poll_status',
        'submitdiv',
    );
}

add_action( 'add_meta_boxes_poll', 'kashiwazaki_poll_register_metaboxes' );
function kashiwazaki_poll_register_metaboxes() {
    $top = KASHIWAZAKI_POLL_TOP_CONTEXT;
    add_meta_box( 'kashiwazaki_poll_question', 'Kashiwazaki SEO Poll：設問と選択肢', 'kashiwazaki_poll_question_metabox', 'poll', $top, 'high' );
    add_meta_box( 'kashiwazaki_poll_counts', 'Kashiwazaki SEO Poll：投票数', 'kashiwazaki_poll_counts_metabox', 'poll', $top, 'high' );
    add_meta_box( 'kashiwazaki_poll_dataset', 'Kashiwazaki SEO Poll：データセット情報', 'kashiwazaki_poll_dataset_metabox', 'poll', $top, 'high' );
    add_meta_box( 'kashiwazaki_poll_embed', 'Kashiwazaki SEO Poll：ショートコードと掲載ページ', 'kashiwazaki_poll_embed_metabox', 'poll', $top, 'high' );
    add_meta_box( 'kashiwazaki_poll_status', 'Kashiwazaki SEO Poll：投票の受付と集計', 'kashiwazaki_poll_status_metabox', 'poll', 'side', 'high' );

    // 投稿者は本文側から右サイドバーの下の方へ移す。
    remove_meta_box( 'authordiv', 'poll', 'normal' );
    add_meta_box( 'authordiv', '投稿者', 'post_author_meta_box', 'poll', 'side', 'low' );
}

/**
 * 右サイドバーの先頭を「投票の受付と集計」→「公開」の順に固定する。
 * 他プラグインが同じ high 優先度で先に登録していても、その前に来るよう並べ替える。
 */
add_action( 'add_meta_boxes_poll', 'kashiwazaki_poll_pin_side_metaboxes', PHP_INT_MAX );
function kashiwazaki_poll_pin_side_metaboxes() {
    global $wp_meta_boxes;
    if ( empty( $wp_meta_boxes['poll']['side'] ) ) {
        return;
    }
    $side  = &$wp_meta_boxes['poll']['side'];
    $first = array();
    foreach ( array( 'kashiwazaki_poll_status', 'submitdiv' ) as $id ) {
        foreach ( array( 'high', 'core', 'default', 'low' ) as $priority ) {
            if ( isset( $side[ $priority ][ $id ] ) && false !== $side[ $priority ][ $id ] ) {
                $first[ $id ] = $side[ $priority ][ $id ];
                unset( $side[ $priority ][ $id ] );
                break;
            }
        }
    }
    $side['high'] = $first + ( isset( $side['high'] ) ? $side['high'] : array() );
}

/**
 * ユーザーがドラッグで保存した並び順から本プラグインの固定ボックスを除外し、
 * 常にタイトル直下・サイドバー先頭に表示されるようにする。
 */
add_filter( 'get_user_option_meta-box-order_poll', 'kashiwazaki_poll_filter_saved_metabox_order' );
function kashiwazaki_poll_filter_saved_metabox_order( $order ) {
    if ( ! is_array( $order ) ) {
        return $order;
    }
    $pinned = kashiwazaki_poll_pinned_metabox_ids();
    foreach ( $order as $context => $ids ) {
        $list = array_filter( explode( ',', (string) $ids ), function( $id ) use ( $pinned ) {
            return '' !== $id && ! in_array( $id, $pinned, true );
        } );
        $order[ $context ] = implode( ',', $list );
    }
    return $order;
}

/** タイトル直下に本プラグインの入力パネルを描画する。 */
add_action( 'edit_form_after_title', 'kashiwazaki_poll_render_top_metaboxes' );
function kashiwazaki_poll_render_top_metaboxes( $post ) {
    if ( ! $post || 'poll' !== $post->post_type ) {
        return;
    }
    echo '<div class="kashiwazaki-poll-top-boxes">';
    do_meta_boxes( get_current_screen(), KASHIWAZAKI_POLL_TOP_CONTEXT, $post );
    echo '</div>';
}

/**
 * 保存済みの選択肢と、選択肢数に揃えた得票数を返す。
 *
 * @param int $post_id
 * @return array{0:array,1:int[]}
 */
function kashiwazaki_poll_get_options_and_counts( $post_id ) {
    $options = get_post_meta( $post_id, '_kashiwazaki_poll_options', true );
    // 表示用に4バイト文字の文字参照を元の文字に戻す（署名は呼び出し側で保存されたままの値から作る）。
    $options = is_array( $options ) ? array_values( array_map( 'kashiwazaki_poll_decode_stored_text', $options ) ) : array();
    $counts  = kashiwazaki_poll_normalize_counts( get_post_meta( $post_id, '_kashiwazaki_poll_counts', true ), count( $options ) );
    return array( $options, $counts );
}


/**
 * 編集画面で表示・保存する設定のメタキー（票数や投票済みの記録など、投票で変わるものは含めない）。
 */
function kashiwazaki_poll_form_meta_keys() {
    return array(
        '_kashiwazaki_poll_options',
        '_kashiwazaki_poll_type',
        '_kashiwazaki_poll_description',
        '_kashiwazaki_poll_license',
        '_kashiwazaki_poll_heading_level',
        '_kashiwazaki_poll_locked',
        'dataset_version',
        'dataset_keywords',
    );
}

/**
 * 編集画面に出す値（画面と同じく get_post_meta で読む）。
 */
function kashiwazaki_poll_form_values( $post_id ) {
    $values = array();
    foreach ( kashiwazaki_poll_form_meta_keys() as $key ) {
        $values[ $key ] = get_post_meta( $post_id, $key, true );
    }
    return $values;
}

function kashiwazaki_poll_form_signature( $values ) {
    $normalized = array();
    foreach ( kashiwazaki_poll_form_meta_keys() as $key ) {
        $normalized[ $key ] = kashiwazaki_poll_normalize_meta_for_compare( isset( $values[ $key ] ) ? $values[ $key ] : '' );
    }
    return md5( (string) wp_json_encode( $normalized ) );
}


// ---------------------------------------------------------------------------
// 設問と選択肢
// ---------------------------------------------------------------------------
function kashiwazaki_poll_question_metabox( $post ) {
    wp_nonce_field( 'kashiwazaki_poll_save_metabox', 'kashiwazaki_poll_nonce' );
    // 「確認待ち」の通知が出ているとき（印が 60 秒以上前のもの）は、その印の値を送り、保存したときにこの印だけを外す。
    $repair_seen = kashiwazaki_poll_get_repair_marker( $post->ID );
    if ( false !== $repair_seen && (int) $repair_seen <= time() - 60 ) {
        echo '<input type="hidden" name="kashiwazaki_poll_repair_seen" value="' . esc_attr( (string) $repair_seen ) . '">';
    }

    // 画面に出す設定の値の署名。保存のときにデータベースの値と照らし、画面を開いた後に設定が変わった場合や、
    // 開いたときの読み取りに失敗して空の欄が表示された場合に、空や古い値で上書きしないようにする。
    echo '<input type="hidden" name="kashiwazaki_poll_form_sig" value="' . esc_attr( kashiwazaki_poll_form_signature( kashiwazaki_poll_form_values( $post->ID ) ) ) . '">';
    // 画面ごとの印（この画面が保存した結果を、次の保存で古い画面とみなさないため）。
    echo '<input type="hidden" name="kashiwazaki_poll_form_token" value="' . esc_attr( strtolower( wp_generate_password( 20, false, false ) ) ) . '">';

    $options = get_post_meta( $post->ID, '_kashiwazaki_poll_options', true );
    $options_text = is_array( $options ) ? implode( "\n", array_map( 'kashiwazaki_poll_decode_stored_text', $options ) ) : '';

    $poll_type = get_post_meta( $post->ID, '_kashiwazaki_poll_type', true );
    if ( ! in_array( $poll_type, array( 'single', 'multiple' ), true ) ) {
        $poll_type = 'multiple';
    }

    $heading_level = get_post_meta( $post->ID, '_kashiwazaki_poll_heading_level', true );
    if ( ! in_array( $heading_level, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ) {
        $heading_level = 'h3';
    }
    ?>
    <p class="kspoll-help">上のタイトル欄が「質問文」になります。ここでは回答の形式と選択肢を設定します。</p>
    <table class="form-table kspoll-form-table" role="presentation">
        <tr>
            <th scope="row">投票形式</th>
            <td>
                <fieldset>
                    <label><input type="radio" name="kashiwazaki_poll_type" value="multiple" <?php checked( $poll_type, 'multiple' ); ?>> 複数選択</label>
                    <span class="description">回答者は複数の選択肢を同時に選べます（例：趣味を複数選ぶ）。</span><br>
                    <label><input type="radio" name="kashiwazaki_poll_type" value="single" <?php checked( $poll_type, 'single' ); ?>> 単一選択</label>
                    <span class="description">回答者は1つだけ選べます（例：好きな色を1つ選ぶ）。</span>
                </fieldset>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="kashiwazaki_poll_options_field">選択肢</label></th>
            <td>
                <textarea id="kashiwazaki_poll_options_field" name="kashiwazaki_poll_options" rows="6" class="large-text"><?php echo esc_textarea( $options_text ); ?></textarea>
                <p class="description">1行につき1つ入力します。並べ替えても票は選択肢ごとに引き継がれます。文字を書き換えた選択肢は別の選択肢として扱われ、票は0から始まります。</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="kashiwazaki_poll_heading_level_field">質問文の見出し</label></th>
            <td>
                <select id="kashiwazaki_poll_heading_level_field" name="kashiwazaki_poll_heading_level">
                    <?php foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $lvl ) : ?>
                        <option value="<?php echo esc_attr( $lvl ); ?>" <?php selected( $heading_level, $lvl ); ?>><?php echo esc_html( $lvl ); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description">記事に埋め込んだとき、質問文をどの見出しタグで表示するかを選びます。</p>
            </td>
        </tr>
    </table>
    <?php
}

// ---------------------------------------------------------------------------
// 投票数（編集）
// ---------------------------------------------------------------------------
function kashiwazaki_poll_counts_metabox( $post ) {
    list( $options, $counts ) = kashiwazaki_poll_get_options_and_counts( $post->ID );
    if ( empty( $options ) ) {
        echo '<p class="kspoll-help">選択肢を入力して保存すると、ここで選択肢ごとの投票数を確認・編集できます。</p>';
        return;
    }
    $total = array_sum( $counts );
    $raw_options = get_post_meta( $post->ID, '_kashiwazaki_poll_options', true );
    ?>
    <p class="kspoll-help">数値を書き換えて「更新」を押すと投票数が保存されます。書き換えていない選択肢は、編集中に入った投票もそのまま残ります。</p>
    <input type="hidden" name="kashiwazaki_poll_counts_sig" value="<?php echo esc_attr( kashiwazaki_poll_options_signature( $raw_options ) ); ?>">
    <table class="widefat striped kspoll-counts-table">
        <thead>
            <tr>
                <th scope="col">選択肢</th>
                <th scope="col" class="kspoll-col-count">投票数</th>
                <th scope="col" class="kspoll-col-percent">割合</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $options as $i => $label ) : ?>
            <tr>
                <th scope="row"><label for="kashiwazaki_poll_count_<?php echo (int) $i; ?>"><?php echo esc_html( $label ); ?></label></th>
                <td class="kspoll-col-count">
                    <input type="hidden" name="kashiwazaki_poll_counts_orig[<?php echo (int) $i; ?>]" value="<?php echo esc_attr( $counts[ $i ] ); ?>">
                    <input type="number" id="kashiwazaki_poll_count_<?php echo (int) $i; ?>" class="small-text kspoll-count-input" name="kashiwazaki_poll_counts_edit[<?php echo (int) $i; ?>]" value="<?php echo esc_attr( $counts[ $i ] ); ?>" min="0" max="<?php echo esc_attr( KASHIWAZAKI_POLL_MAX_COUNT ); ?>" step="1" inputmode="numeric"> 票
                </td>
                <td class="kspoll-col-percent"><span class="kspoll-percent"><?php echo esc_html( $total > 0 ? round( $counts[ $i ] / $total * 100, 1 ) . '%' : '—' ); ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th scope="row">合計</th>
                <td class="kspoll-col-count"><strong class="kspoll-total"><?php echo esc_html( $total ); ?></strong> 票</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    <p class="description">選択肢を書き換えたときは、先に「更新」で選択肢を保存してから投票数を編集してください（同時に変更すると投票数の編集は保存されません）。投票数を編集しても、投票済みの人の再投票制限は変わりません。</p>
    <?php
}

// ---------------------------------------------------------------------------
// データセット情報
// ---------------------------------------------------------------------------
function kashiwazaki_poll_license_choices() {
    return array(
        'https://creativecommons.org/licenses/by/4.0/'       => 'CC BY 4.0',
        'https://creativecommons.org/licenses/by-sa/4.0/'    => 'CC BY-SA 4.0',
        'https://creativecommons.org/licenses/by-nc/4.0/'    => 'CC BY-NC 4.0',
        'https://creativecommons.org/publicdomain/zero/1.0/' => 'CC0 1.0',
        'https://creativecommons.org/licenses/by-nc-sa/4.0/' => 'CC BY-NC-SA 4.0',
        'https://creativecommons.org/licenses/by-nd/4.0/'    => 'CC BY-ND 4.0',
        'https://creativecommons.org/licenses/by-nc-nd/4.0/' => 'CC BY-NC-ND 4.0',
        'https://creativecommons.org/publicdomain/mark/1.0/' => 'Public Domain Mark 1.0',
        'https://creativecommons.org/licenses/by/3.0/'       => 'CC BY 3.0',
        'https://creativecommons.org/licenses/by/2.0/'       => 'CC BY 2.0',
    );
}

function kashiwazaki_poll_dataset_metabox( $post ) {
    $description = kashiwazaki_poll_decode_stored_text( get_post_meta( $post->ID, '_kashiwazaki_poll_description', true ) );
    $license = get_post_meta( $post->ID, '_kashiwazaki_poll_license', true );
    if ( empty( $license ) ) {
        $license = 'https://creativecommons.org/licenses/by/4.0/';
    }
    $choices = kashiwazaki_poll_license_choices();
    $dataset_version = kashiwazaki_poll_decode_stored_text( get_post_meta( $post->ID, 'dataset_version', true ) );
    if ( '' === (string) $dataset_version ) {
        $dataset_version = '1.0';
    }
    $dataset_keywords = kashiwazaki_poll_decode_stored_text( get_post_meta( $post->ID, 'dataset_keywords', true ) );
    ?>
    <p class="kspoll-help">データセットページと構造化データ（Dataset）に出力される情報です。</p>
    <table class="form-table kspoll-form-table" role="presentation">
        <tr>
            <th scope="row"><label for="kashiwazaki_poll_description_field">詳細な説明</label></th>
            <td>
                <textarea id="kashiwazaki_poll_description_field" name="kashiwazaki_poll_description" rows="5" class="large-text"><?php echo esc_textarea( $description ); ?></textarea>
                <p class="description">構造化データの説明（description）に使われます。150文字以上がおすすめです。現在 <strong class="kspoll-desc-count"><?php echo esc_html( mb_strlen( (string) $description ) ); ?></strong> 文字。</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="kashiwazaki_poll_license_field">ライセンス</label></th>
            <td>
                <select id="kashiwazaki_poll_license_field" name="kashiwazaki_poll_license">
                    <?php if ( ! isset( $choices[ $license ] ) ) : ?>
                        <option value="<?php echo esc_attr( $license ); ?>" selected><?php echo esc_html( $license ); ?></option>
                    <?php endif; ?>
                    <?php foreach ( $choices as $url => $label ) : ?>
                        <option value="<?php echo esc_attr( $url ); ?>" <?php selected( $license, $url ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="kashiwazaki_poll_dataset_version_field">バージョン</label></th>
            <td><input type="text" id="kashiwazaki_poll_dataset_version_field" name="dataset_version" value="<?php echo esc_attr( $dataset_version ); ?>" class="small-text"></td>
        </tr>
        <tr>
            <th scope="row"><label for="kashiwazaki_poll_dataset_keywords_field">キーワード</label></th>
            <td>
                <input type="text" id="kashiwazaki_poll_dataset_keywords_field" name="dataset_keywords" value="<?php echo esc_attr( $dataset_keywords ); ?>" class="large-text">
                <p class="description">カンマ区切りで入力します。例：マーケティング,消費者行動,統計</p>
            </td>
        </tr>
    </table>
    <?php
}

// ---------------------------------------------------------------------------
// ショートコードと掲載ページ
// ---------------------------------------------------------------------------
function kashiwazaki_poll_embed_metabox( $post ) {
    $shortcode_str = '[tk_poll id="' . $post->ID . '"]';
    ?>
    <div class="kspoll-shortcode-row">
        <label for="kashiwazaki_poll_shortcode_field" class="kspoll-label">ショートコード</label>
        <input type="text" id="kashiwazaki_poll_shortcode_field" readonly class="regular-text code" value="<?php echo esc_attr( $shortcode_str ); ?>">
        <button type="button" class="button" id="kashiwazaki_poll_copy_btn">コピー</button>
        <span class="kspoll-copy-done" aria-live="polite"></span>
    </div>
    <p class="description">投稿や固定ページの本文に貼り付けると、投票フォームが表示されます。</p>

    <?php
    if ( function_exists( 'kashiwazaki_poll_get_shortcode_usage' ) ) {
        // 管理画面では未公開(draft/private/pending)も含めて編集者に提示する。
        $usage_posts = kashiwazaki_poll_get_shortcode_usage( $post->ID, true );
        // 閲覧できない記事（他人の下書き・非公開など）はタイトルや日時も出さない。
        $usage_posts = array_values( array_filter( (array) $usage_posts, function( $usage_post ) {
            return current_user_can( 'read_post', $usage_post->ID );
        } ) );
        $status_translations = array(
            'publish' => '公開済み',
            'draft'   => '下書き',
            'private' => '非公開',
            'pending' => 'レビュー待ち',
            'future'  => '予約済み',
        );
        ?>
        <h4 class="kspoll-subheading">掲載中のページ</h4>
        <?php if ( ! empty( $usage_posts ) ) : ?>
            <ul class="kspoll-usage-list">
            <?php foreach ( $usage_posts as $usage_post ) :
                $edit_url = get_edit_post_link( $usage_post->ID );
                $view_url = get_permalink( $usage_post->ID );
                $type_obj = get_post_type_object( $usage_post->post_type );
                $type_label = $type_obj ? $type_obj->labels->singular_name : $usage_post->post_type;
                $status_label = isset( $status_translations[ $usage_post->post_status ] ) ? $status_translations[ $usage_post->post_status ] : $usage_post->post_status;
                ?>
                <li>
                    <?php if ( $edit_url ) : ?>
                        <a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $usage_post->post_title ? $usage_post->post_title : '(タイトルなし)' ); ?></a>
                    <?php else : ?>
                        <?php echo esc_html( $usage_post->post_title ? $usage_post->post_title : '(タイトルなし)' ); ?>
                    <?php endif; ?>
                    <span class="kspoll-usage-meta">
                        <?php echo esc_html( $type_label ); ?>
                        <?php if ( 'publish' !== $usage_post->post_status ) : ?>
                            ・<span class="kspoll-warn"><?php echo esc_html( $status_label ); ?></span>
                        <?php endif; ?>
                        <?php if ( isset( $usage_post->shortcode_count ) && $usage_post->shortcode_count > 1 ) : ?>
                            ・<span class="kspoll-warn"><?php echo (int) $usage_post->shortcode_count; ?>回使用</span>
                        <?php endif; ?>
                        ・更新 <?php echo esc_html( get_the_modified_date( 'Y/m/d H:i', $usage_post->ID ) ); ?>
                        <?php if ( 'publish' === $usage_post->post_status && $view_url ) : ?>
                            ・<a href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener">表示 ↗</a>
                        <?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p class="kspoll-help">現在このショートコードを掲載しているページはありません。</p>
        <?php endif; ?>
        <?php
        $clear_cache_url = wp_nonce_url(
            admin_url( 'post.php?post=' . $post->ID . '&action=edit&cache_cleared=1&clear_poll_usage_cache=' . $post->ID ),
            'clear_poll_usage_cache_' . $post->ID
        );
        ?>
        <p class="kspoll-usage-footer">
            <?php echo esc_html( sprintf( '合計 %d 件', count( (array) $usage_posts ) ) ); ?>
            ・<a href="<?php echo esc_url( $clear_cache_url ); ?>">掲載ページの情報を再取得</a>
        </p>
        <?php
    }
    ?>
    <details class="kspoll-details">
        <summary>投票時に「不正なリクエストです。（nonce）」と表示される場合</summary>
        <p>多くの場合、ページキャッシュが原因です。次の対応を試してください。</p>
        <ul>
            <li>キャッシュプラグイン（WP-Optimize、WP Super Cache など）のキャッシュを削除する。</li>
            <li>サーバー側のキャッシュ（ホスティング会社の機能）をクリアする。</li>
            <li>CDN（Cloudflare など）のキャッシュをパージする。</li>
            <li><strong>おすすめ:</strong> 投票フォームを表示しているページのURLを、キャッシュの対象から除外する。</li>
        </ul>
    </details>
    <?php
}

// ---------------------------------------------------------------------------
// 投票の受付と集計（右サイドバー）
// ---------------------------------------------------------------------------
function kashiwazaki_poll_status_metabox( $post ) {
    $locked = kashiwazaki_poll_is_locked( $post->ID );
    list( , $counts ) = kashiwazaki_poll_get_options_and_counts( $post->ID );
    $total_votes = array_sum( $counts );
    $voted_ips = get_post_meta( $post->ID, '_kashiwazaki_poll_voted_ips', true );
    $voter_count = is_array( $voted_ips ) ? count( $voted_ips ) : 0;
    $last_updated = kashiwazaki_poll_get_last_updated_time( $post->ID );
    ?>
    <div class="kspoll-status">
        <p class="kspoll-status-badge <?php echo $locked ? 'is-locked' : 'is-open'; ?>">
            <?php echo $locked ? '🔒 受付終了（ロック中）' : '● 投票受付中'; ?>
        </p>
        <fieldset class="kspoll-lock-field">
            <legend class="screen-reader-text">投票の受付</legend>
            <label><input type="radio" name="kashiwazaki_poll_lock" value="open" <?php checked( ! $locked ); ?>> 受け付ける</label><br>
            <label><input type="radio" name="kashiwazaki_poll_lock" value="locked" <?php checked( $locked ); ?>> 締め切る（ロック）</label>
        </fieldset>
        <p class="description">切り替えたら「更新」を押してください。ロック中は投票フォームの代わりに結果が表示され、新しい投票は受け付けません。解除すると、また投票できるようになります。</p>

        <table class="kspoll-summary">
            <tr><th scope="row">総投票数</th><td><?php echo esc_html( number_format_i18n( $total_votes ) ); ?> 票</td></tr>
            <tr><th scope="row">投票者数（IP）</th><td><?php echo esc_html( number_format_i18n( $voter_count ) ); ?> 人</td></tr>
            <tr><th scope="row">最終更新</th><td><?php echo $last_updated ? esc_html( wp_date( 'Y/m/d H:i', $last_updated ) ) : '—'; ?></td></tr>
        </table>

        <?php if ( 'publish' === $post->post_status ) : ?>
            <p><a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" target="_blank" rel="noopener" class="button button-small">データセットページを見る ↗</a></p>
        <?php endif; ?>

        <details class="kspoll-details kspoll-danger">
            <summary>集計データを全削除する</summary>
            <?php wp_nonce_field( 'kashiwazaki_poll_reset_data_action', 'kashiwazaki_poll_reset_data_nonce' ); ?>
            <p>票数と投票者の記録（IP）をすべて削除し、0票に戻します。削除後は、投票済みの人も再び投票できます。<strong>この操作は元に戻せません。</strong></p>
            <input type="hidden" name="kashiwazaki_poll_reset_data_action" value="0" id="kashiwazaki_poll_reset_action_field">
            <p><button type="button" class="button button-link-delete" id="kashiwazaki_poll_reset_btn">集計データを全削除する</button></p>
        </details>
    </div>
    <?php
}

// ---------------------------------------------------------------------------
// 保存
// ---------------------------------------------------------------------------
if ( ! function_exists( 'kashiwazaki_poll_save_metabox' ) ) {
    add_action( 'save_post_poll', 'kashiwazaki_poll_save_metabox' );
    function kashiwazaki_poll_save_metabox( $post_id ) {
        if ( ! isset( $_POST['kashiwazaki_poll_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['kashiwazaki_poll_nonce'] ) ), 'kashiwazaki_poll_save_metabox' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( wp_is_post_revision( $post_id ) || 'poll' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // $_POST は WordPress により自動でスラッシュが付くため、必ず wp_unslash してから扱う
        // （付いたまま比較すると、引用符を含む選択肢が毎回「変更あり」と判定され票が消える）。
        $post_data = wp_unslash( $_POST );

        // この画面の設定（選択肢・投票数・回答形式・説明・ライセンス・見出し・データセット情報・受付）は、
        // 票の書き換えの共通の入口で 1 回にまとめて保存する。排他の中で、画面を開いたときの設定の値と、いまの
        // データベースの値（直接読む）を照らし、同じときだけ保存する。違うとき（別の画面で変わった、開いたときの
        // 読み取りに失敗して空の欄が出ていた）は何も変えずに知らせる（空や古い値で上書きして票や設定を失わないように）。
        // ただし同じ画面が直前に保存した結果（プレビューで保存した場合など）は、画面ごとの印で見分けて通す。
        $form_sig   = isset( $post_data['kashiwazaki_poll_form_sig'] ) && is_scalar( $post_data['kashiwazaki_poll_form_sig'] ) ? (string) $post_data['kashiwazaki_poll_form_sig'] : '';
        $form_token = isset( $post_data['kashiwazaki_poll_form_token'] ) && is_scalar( $post_data['kashiwazaki_poll_form_token'] ) ? sanitize_key( $post_data['kashiwazaki_poll_form_token'] ) : '';
        $token_key  = '' !== $form_token ? 'kspoll_form_' . md5( $post_id . '|' . $form_token ) : '';

        $poll_type = isset( $post_data['kashiwazaki_poll_type'] ) ? sanitize_key( $post_data['kashiwazaki_poll_type'] ) : 'multiple';
        if ( ! in_array( $poll_type, array( 'single', 'multiple' ), true ) ) {
            $poll_type = 'multiple';
        }

        $raw_options = isset( $post_data['kashiwazaki_poll_options'] ) ? sanitize_textarea_field( $post_data['kashiwazaki_poll_options'] ) : '';
        $cleaned = array();
        foreach ( explode( "\n", $raw_options ) as $line ) {
            $line = trim( $line );
            if ( $line !== '' ) {
                $cleaned[] = sanitize_text_field( $line );
            }
        }
        // データベースが4バイト文字（絵文字・「𠮷」など）を保存できない設定のときは、文字参照に変えてから保存する
        // （そのまま保存すると選択肢全体の保存に失敗するため。表示・出力のときに元の文字へ戻す）。
        $cleaned = array_map( 'kashiwazaki_poll_encode_text_for_storage', $cleaned );
        // 同じ文字の選択肢は1つにまとめる（別々に表示されて票が片方にだけ付くのを防ぐ）。
        $cleaned = array_values( array_unique( $cleaned ) );

        $count_edits  = ! isset( $post_data['kashiwazaki_poll_reset_data_submit'] ) && isset( $post_data['kashiwazaki_poll_counts_edit'] ) && is_array( $post_data['kashiwazaki_poll_counts_edit'] ) ? $post_data['kashiwazaki_poll_counts_edit'] : array();
        $count_origs  = isset( $post_data['kashiwazaki_poll_counts_orig'] ) && is_array( $post_data['kashiwazaki_poll_counts_orig'] ) ? $post_data['kashiwazaki_poll_counts_orig'] : array();
        $count_sig    = isset( $post_data['kashiwazaki_poll_counts_sig'] ) ? (string) $post_data['kashiwazaki_poll_counts_sig'] : '';
        // 投票数の欄は保存のたびに送信されるので、表示時の値から書き換えられた欄があるときだけ投票数の編集として扱う。
        $wants_counts = false;
        foreach ( $count_edits as $index => $value ) {
            $original = isset( $count_origs[ $index ] ) && is_scalar( $count_origs[ $index ] ) ? trim( (string) $count_origs[ $index ] ) : null;
            if ( ! is_scalar( $value ) || null === $original || trim( (string) $value ) !== $original ) {
                $wants_counts = true;
                break;
            }
        }

        $heading_level = isset( $post_data['kashiwazaki_poll_heading_level'] ) ? sanitize_key( $post_data['kashiwazaki_poll_heading_level'] ) : 'h3';
        if ( ! in_array( $heading_level, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ) {
            $heading_level = 'h3';
        }
        // 説明・バージョン・キーワードも、データベースが4バイト文字を保存できない設定のときは文字参照に変えて保存する
        // （選択肢と同じ。表示・出力のときに元の文字へ戻す。そのまま保存すると設定全体の保存に失敗するため）。
        $settings = array(
            '_kashiwazaki_poll_type'          => $poll_type,
            '_kashiwazaki_poll_description'   => kashiwazaki_poll_encode_text_for_storage( isset( $post_data['kashiwazaki_poll_description'] ) ? sanitize_textarea_field( $post_data['kashiwazaki_poll_description'] ) : '' ),
            '_kashiwazaki_poll_license'       => isset( $post_data['kashiwazaki_poll_license'] ) ? esc_url_raw( $post_data['kashiwazaki_poll_license'] ) : '',
            '_kashiwazaki_poll_heading_level' => $heading_level,
            'dataset_version'                 => kashiwazaki_poll_encode_text_for_storage( isset( $post_data['dataset_version'] ) ? sanitize_text_field( $post_data['dataset_version'] ) : '1.0' ),
            'dataset_keywords'                => kashiwazaki_poll_encode_text_for_storage( isset( $post_data['dataset_keywords'] ) ? sanitize_text_field( $post_data['dataset_keywords'] ) : '' ),
        );
        // 投票の受付（ロック）。ラジオが送られたときだけ変える。
        $lock = isset( $post_data['kashiwazaki_poll_lock'] ) ? sanitize_key( $post_data['kashiwazaki_poll_lock'] ) : '';
        if ( 'locked' === $lock ) {
            $settings['_kashiwazaki_poll_locked'] = '1';
        } elseif ( 'open' === $lock ) {
            $settings['_kashiwazaki_poll_locked'] = null;
        }

        $mutation = kashiwazaki_poll_mutate_vote_state( $post_id, function( $state ) use ( $post_id, $form_sig, $token_key, $settings, $cleaned, $wants_counts, $count_edits, $count_origs, $count_sig ) {
            $form_now = kashiwazaki_poll_read_meta_strict( $post_id, kashiwazaki_poll_form_meta_keys() );
            if ( null === $form_now ) {
                return array( 'error' => 'failed' );
            }
            $sig_now    = kashiwazaki_poll_form_signature( $form_now );
            $token_sig  = '' !== $token_key ? get_transient( $token_key ) : false;
            $form_fresh = ( '' !== $form_sig && hash_equals( $sig_now, $form_sig ) ) || ( is_string( $token_sig ) && hash_equals( $sig_now, $token_sig ) );
            if ( ! $form_fresh ) {
                return array( 'error' => 'form_stale' );
            }
            $set             = $settings;
            $prev_options    = $state['_kashiwazaki_poll_options'];
            $counts          = $state['_kashiwazaki_poll_counts'];
            $options_changed = ( $prev_options !== $cleaned );
            if ( $options_changed ) {
                $set['_kashiwazaki_poll_options'] = $cleaned;
                $remapped = kashiwazaki_poll_remap_counts( $prev_options, $counts, $cleaned );
                if ( null !== $remapped ) {
                    $set['_kashiwazaki_poll_counts'] = $remapped;
                    $counts = $remapped;
                }
            }
            $edit = array( 'changed' => false, 'notice' => '' );
            if ( $wants_counts ) {
                $edit = kashiwazaki_poll_compute_count_edits(
                    $counts,
                    $count_edits,
                    $count_origs,
                    $count_sig,
                    kashiwazaki_poll_options_signature( $prev_options ),
                    $options_changed,
                    count( $cleaned )
                );
                if ( $edit['changed'] ) {
                    $set['_kashiwazaki_poll_counts']           = $edit['counts'];
                    $set['_kashiwazaki_poll_counts_edited_ts'] = time();
                }
            }
            // この保存が書いた後の設定の値（排他の中で読んだ値に、この保存で書く値を重ねたもの）から、画面ごとの印に
            // 残す署名を作る（排他を出た後に読み直すと、その間の別の画面の保存を自分の結果として覚えてしまうため）。
            $after = $form_now;
            foreach ( kashiwazaki_poll_form_meta_keys() as $key ) {
                if ( array_key_exists( $key, $set ) ) {
                    $after[ $key ] = null === $set[ $key ] ? '' : $set[ $key ];
                }
            }
            return array(
                'set'    => $set,
                'result' => array(
                    'notice'     => $edit['notice'],
                    'was_locked' => '1' === (string) $form_now['_kashiwazaki_poll_locked'],
                    'after_sig'  => kashiwazaki_poll_form_signature( $after ),
                ),
            );
        } );

        $notice = '';
        if ( ! $mutation['ok'] ) {
            $codes  = array( 'busy' => 'options_busy', 'form_stale' => 'form_stale' );
            $notice = isset( $codes[ $mutation['error'] ] ) ? $codes[ $mutation['error'] ] : 'settings_failed';
        } else {
            $notice = $mutation['result']['notice'];
            // この画面が保存した結果を覚えておく（プレビューの後に同じ画面から保存しても、古い画面とみなさない）。
            if ( '' !== $token_key ) {
                set_transient( $token_key, $mutation['result']['after_sig'], 12 * HOUR_IN_SECONDS );
            }
            // 管理者が保存した（投票数を確認した）ので、編集画面を開いたときに出ていた「確認待ち」の通知だけを外す。
            if ( isset( $post_data['kashiwazaki_poll_repair_seen'] ) && is_scalar( $post_data['kashiwazaki_poll_repair_seen'] ) ) {
                kashiwazaki_poll_clear_repair_marker( $post_id, $post_data['kashiwazaki_poll_repair_seen'] );
            }
            // 受付の状態が変わったときは、一覧から切り替えたときと同じ通知を出す。
            if ( array_key_exists( '_kashiwazaki_poll_locked', $settings ) ) {
                $is_locked = ( '1' === $settings['_kashiwazaki_poll_locked'] );
                if ( $mutation['result']['was_locked'] !== $is_locked ) {
                    $lock_notice = $is_locked ? 'locked' : 'unlocked';
                    add_filter( 'redirect_post_location', function( $location ) use ( $lock_notice ) {
                        return add_query_arg( 'kashiwazaki_poll_lock_notice', $lock_notice, $location );
                    } );
                }
            }
        }

        // データファイルの作り直しは、すべての保存が終わった後の wp_after_insert_post でまとめて行う
        // （タイトルやライセンスだけを変えた場合も、最新の内容で作り直すため）。

        if ( '' !== $notice ) {
            add_filter( 'redirect_post_location', function( $location ) use ( $notice ) {
                return add_query_arg( 'kashiwazaki_poll_notice', $notice, $location );
            } );
        }
    }
}

/**
 * 管理画面で書き換えられた投票数から、保存する票数を計算する（保存はしない）。
 *
 * 書き換えられた選択肢（表示時の値と異なるもの）だけを上書きし、それ以外は
 * 保存時点の値を残す。表示後に選択肢が変わっていた場合は何も変えない。
 *
 * @param mixed  $counts          保存時点の票数（排他の中で読み直した値）
 * @param array  $edits           kashiwazaki_poll_counts_edit（index => 入力値）
 * @param array  $originals       kashiwazaki_poll_counts_orig（index => 表示時の値）
 * @param string $signature       表示時の選択肢の署名
 * @param string $prev_signature  保存直前の選択肢の署名
 * @param bool   $options_changed 同じ送信で選択肢が変更されたか
 * @param int    $option_count    保存後の選択肢数
 * @return array{changed:bool,notice:string,counts:int[]}
 */
function kashiwazaki_poll_compute_count_edits( $counts, $edits, $originals, $signature, $prev_signature, $options_changed, $option_count ) {
    $counts    = kashiwazaki_poll_normalize_counts( $counts, $option_count );
    $requested = array();
    $invalid   = false;
    foreach ( $edits as $index => $value ) {
        if ( ! is_scalar( $value ) || ! is_numeric( $index ) ) {
            continue;
        }
        $index = (int) $index;
        $value = trim( (string) $value );
        $original = isset( $originals[ $index ] ) && is_scalar( $originals[ $index ] ) ? trim( (string) $originals[ $index ] ) : null;
        if ( null !== $original && $value === $original ) {
            continue; // 書き換えられていない。
        }
        if ( ! preg_match( '/^\d{1,9}$/', $value ) || (int) $value > KASHIWAZAKI_POLL_MAX_COUNT ) {
            $invalid = true;
            continue;
        }
        $requested[ $index ] = (int) $value;
    }

    if ( empty( $requested ) ) {
        return array( 'changed' => false, 'notice' => $invalid ? 'counts_invalid' : '', 'counts' => $counts );
    }
    if ( $options_changed || ! hash_equals( (string) $prev_signature, (string) $signature ) ) {
        return array( 'changed' => false, 'notice' => 'counts_skipped', 'counts' => $counts );
    }

    $changed = false;
    foreach ( $requested as $index => $value ) {
        if ( $index >= 0 && $index < $option_count && $counts[ $index ] !== $value ) {
            $counts[ $index ] = $value;
            $changed = true;
        }
    }
    if ( ! $changed ) {
        return array( 'changed' => false, 'notice' => $invalid ? 'counts_invalid' : '', 'counts' => $counts );
    }
    return array( 'changed' => true, 'notice' => $invalid ? 'counts_partial' : 'counts_saved', 'counts' => $counts );
}

if ( ! function_exists( 'kashiwazaki_poll_reset_data_save' ) ) {
    add_action( 'save_post_poll', 'kashiwazaki_poll_reset_data_save' );
    function kashiwazaki_poll_reset_data_save( $post_id ) {
        if ( ! isset( $_POST['kashiwazaki_poll_reset_data_submit'] ) ) {
            return;
        }
        if ( ! isset( $_POST['kashiwazaki_poll_reset_data_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['kashiwazaki_poll_reset_data_nonce'] ) ), 'kashiwazaki_poll_reset_data_action' ) ) {
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
        if ( 'poll' !== get_post_type( $post_id ) ) {
            return;
        }

        // 票数と投票者の記録は、票の書き換えの共通の入口で消す（投票と同時に走っても記録が残らないよう排他の中で消し、
        // 両方を消せたときだけ完了とする。片方だけ消えると、古い票が残ったまま再投票の制限だけが外れる、といった
        // 食い違いが起きるため、消し切れなければ両方を元に戻して知らせる）。リセット時刻も同じ書き換えに含める
        // （これより前の投票Cookie/IPは無効化され、リセット後にユーザーが再投票できる）。
        $mutation = kashiwazaki_poll_mutate_vote_state( $post_id, function( $state ) {
            return array(
                'set' => array(
                    '_kashiwazaki_poll_counts'           => null,
                    '_kashiwazaki_poll_voted_ips'        => null,
                    '_kashiwazaki_poll_counts_edited_ts' => null,
                    '_kashiwazaki_poll_reset_ts'         => time(),
                ),
            );
        } );
        if ( ! $mutation['ok'] ) {
            $reset_error = ( 'busy' === $mutation['error'] ) ? 'busy' : 'failed';
            add_filter( 'redirect_post_location', function( $location ) use ( $reset_error ) {
                return add_query_arg( 'kashiwazaki_poll_reset_error', $reset_error, $location );
            } );
            return;
        }

        // 生成済みデータファイルもリセット。古い集計が直URL/DLリンクから取得できる
        // 状態を解消する（公開していればこの後の wp_after_insert_post で0票の内容で作り直す）。
        kashiwazaki_poll_delete_data_files( $post_id );

        add_filter( 'redirect_post_location', function( $location ) {
            $location = remove_query_arg( array( 'kashiwazaki_poll_reset_error', 'kashiwazaki_poll_notice' ), $location );
            return add_query_arg( 'kashiwazaki_poll_reset_data', 'success', $location );
        }, 20 );
    }
}

// ---------------------------------------------------------------------------
// 通知
// ---------------------------------------------------------------------------
add_action( 'admin_notices', 'kashiwazaki_poll_edit_screen_notices' );
function kashiwazaki_poll_edit_screen_notices() {
    $screen = get_current_screen();
    if ( ! $screen || 'poll' !== $screen->post_type || 'post' !== $screen->base ) {
        return;
    }

    if ( isset( $_GET['kashiwazaki_poll_reset_data'] ) && 'success' === $_GET['kashiwazaki_poll_reset_data'] ) {
        echo '<div class="notice notice-success is-dismissible"><p>集計データを全削除しました。</p></div>';
    }
    if ( isset( $_GET['kashiwazaki_poll_reset_error'] ) ) {
        switch ( sanitize_key( wp_unslash( $_GET['kashiwazaki_poll_reset_error'] ) ) ) {
            case 'nonce':
                $message = '集計データの削除に失敗しました。ページを再読み込みしてやり直してください。';
                break;
            case 'permission':
                $message = '集計データを削除する権限がありません。';
                break;
            case 'busy':
                $message = '他の処理（投票など）と重なったため、集計データは削除しませんでした。少し待ってから、もう一度お試しください。';
                break;
            case 'failed':
                $message = '集計データを削除できませんでした。集計データは元のままです。もう一度お試しください。';
                break;
            default:
                $message = '集計データの削除中にエラーが発生しました。';
        }
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
    }

    if ( isset( $_GET['kashiwazaki_poll_lock_notice'] ) ) {
        $lock_key = sanitize_key( wp_unslash( $_GET['kashiwazaki_poll_lock_notice'] ) );
        if ( 'locked' === $lock_key ) {
            echo '<div class="notice notice-success is-dismissible"><p>投票の受付を締め切りました（ロック）。</p></div>';
        } elseif ( 'unlocked' === $lock_key ) {
            echo '<div class="notice notice-success is-dismissible"><p>投票の受付を再開しました。</p></div>';
        }
    }

    if ( isset( $_GET['kashiwazaki_poll_notice'] ) ) {
        $messages = array(
            'counts_saved'   => array( 'success', '投票数を更新しました。' ),
            'counts_partial' => array( 'warning', '投票数を更新しましたが、0以上の整数でない入力は保存しませんでした。' ),
            'counts_invalid' => array( 'error', '投票数は0以上の整数で入力してください。投票数は変更していません。' ),
            'counts_skipped' => array( 'warning', '選択肢が変更されたため、投票数の編集は保存しませんでした。表示されている投票数を確認して、もう一度編集してください。' ),
            'counts_failed'  => array( 'error', '投票数を保存できませんでした。投票数は変更していません。もう一度「更新」を押してください。' ),
            'options_busy'   => array( 'error', '他の処理（投票など）と重なったため、選択肢・投票数・データセット情報・受付の状態は保存しませんでした（タイトルと本文は保存されています）。少し待ってから、もう一度「更新」を押してください。' ),
            'settings_failed' => array( 'error', '設定を保存できませんでした。選択肢・投票数・データセット情報・受付の状態は変更していません（タイトルと本文は保存されています）。もう一度「更新」を押してください。' ),
            'options_failed' => array( 'error', '選択肢を保存できませんでした。選択肢と投票数は変更していません。使えない文字が含まれていないか確認してください。' ),
            'form_stale'     => array( 'error', '画面を開いた後に設定が変わったか、画面の読み込みに失敗したため、選択肢・投票数・データセット情報・受付の状態は保存しませんでした（タイトルと本文は保存されています）。ページを再読み込みして内容を確認してから、もう一度「更新」を押してください。' ),
        );
        $key = sanitize_key( wp_unslash( $_GET['kashiwazaki_poll_notice'] ) );
        if ( isset( $messages[ $key ] ) ) {
            printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $key ][0] ), esc_html( $messages[ $key ][1] ) );
        }
    }
}

/** 通知用のクエリ引数を、WordPress が付け直す URL から取り除く。 */
add_filter( 'removable_query_args', function( $args ) {
    $args[] = 'kashiwazaki_poll_notice';
    $args[] = 'kashiwazaki_poll_lock_notice';
    $args[] = 'kashiwazaki_poll_reset_data';
    $args[] = 'kashiwazaki_poll_reset_error';
    $args[] = 'cache_cleared';
    $args[] = 'all_cache_cleared';
    return $args;
} );

// ---------------------------------------------------------------------------
// 編集画面のスクリプト
// ---------------------------------------------------------------------------
add_action( 'admin_enqueue_scripts', 'kashiwazaki_poll_enqueue_edit_screen_script' );
function kashiwazaki_poll_enqueue_edit_screen_script( $hook_suffix ) {
    if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
        return;
    }
    $screen = get_current_screen();
    if ( ! $screen || 'poll' !== $screen->post_type ) {
        return;
    }
    $path = KASHIWAZAKI_POLL_DIR . 'assets/js/admin-edit.js';
    wp_enqueue_script(
        'kashiwazaki-poll-admin-edit',
        KASHIWAZAKI_POLL_URL . 'assets/js/admin-edit.js',
        array( 'jquery' ),
        file_exists( $path ) ? (string) filemtime( $path ) : null,
        true
    );
}
