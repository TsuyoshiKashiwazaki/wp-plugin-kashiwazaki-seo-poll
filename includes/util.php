<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 投票者のクライアントIPを取得する。
 *
 * 既定では $_SERVER['REMOTE_ADDR'] のみを使用する（クライアントから偽装できないため、
 * 重複投票防止の基準として安全）。リバースプロキシ／CDN（Cloudflare 等）の配下では
 * REMOTE_ADDR が全訪問者で同一のプロキシIPになり最初の1人以外が投票できなくなるため、
 * 信頼できる構成に限り wp-config.php 等で
 *   define( 'KASHIWAZAKI_POLL_TRUST_PROXY', true );
 * を定義すると CF-Connecting-IP / X-Forwarded-For を優先する（opt-in。既定では無効で
 * ヘッダ偽装による重複投票回避を防ぐ）。
 *
 * @return string sanitize 済みIP文字列（取得不能時は空文字）。
 */
function kashiwazaki_poll_get_client_ip() {
    $ip = '';
    if ( defined( 'KASHIWAZAKI_POLL_TRUST_PROXY' ) && KASHIWAZAKI_POLL_TRUST_PROXY ) {
        if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $parts = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
            $ip = trim( $parts[0] ); // 最左 = 元クライアント。
        }
    }
    if ( '' === $ip && isset( $_SERVER['REMOTE_ADDR'] ) ) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // IPv4/IPv6 で使う文字のみ許容。
    return preg_replace( '/[^0-9A-Fa-f:.]/', '', (string) $ip );
}

function kashiwazaki_poll_is_already_voted( $poll_id, $ip, $cookie_key ) {
    // 全体リセット時刻と当該pollの個別リセット時刻の新しい方を基準にする。
    // これにより per-poll リセット後は古い投票Cookie/IPが無効化され再投票できる。
    $global_reset_ts = (int) get_option( 'kashiwazaki_poll_reset_timestamp', 0 );
    $poll_reset_ts   = (int) get_post_meta( $poll_id, '_kashiwazaki_poll_reset_ts', true );
    $reset_ts = max( $global_reset_ts, $poll_reset_ts );
    $voted_ips = get_post_meta( $poll_id, '_kashiwazaki_poll_voted_ips', true );
    if ( ! is_array( $voted_ips ) ) {
        $voted_ips = array();
    }
    if ( isset( $voted_ips[ $ip ] ) ) {
        $vote_time = (int) $voted_ips[ $ip ];
        if ( $vote_time >= $reset_ts ) {
            return true;
        }
    }
    // Cookie による重複投票判定（IP変化時の補助）。Cookie値は投票時刻(Unix秒)を保持し、
    // リセット時刻より後の投票のみ有効とみなす（リセット後は再投票を許可）。
    // 妥当なUnix時刻(>= 2001年)のみ有効とし、旧形式の値 '1' 等は無視する。
    // v2 Cookie（time() = 真UTC基準）。
    if ( $cookie_key && isset( $_COOKIE[ $cookie_key ] ) ) {
        $cookie_time = (int) $_COOKIE[ $cookie_key ];
        if ( $cookie_time >= 1000000000 && $cookie_time >= $reset_ts ) {
            return true;
        }
    }
    // 旧 v1 Cookie（current_time('timestamp') = UTC + GMTオフセット基準）を UTC へ正規化して
    // 後方互換で判定する。これにより旧Cookie保持者の重複投票防止を維持しつつ、
    // 負のGMTオフセット環境での基準ズレも回避する（v2 移行前に投票した利用者向け）。
    $legacy_cookie_key = 'kashiwazaki_poll_voted_' . (int) $poll_id;
    if ( isset( $_COOKIE[ $legacy_cookie_key ] ) ) {
        $legacy_time = (int) $_COOKIE[ $legacy_cookie_key ];
        if ( $legacy_time >= 1000000000 ) {
            $offset = (int) round( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS );
            if ( ( $legacy_time - $offset ) >= $reset_ts ) {
                return true;
            }
        }
    }
    return false;
}

/**
 * アンケートの調査期間（最初の投票日と最後の投票日）を取得
 *
 * @param int $poll_id アンケートID
 * @return array|null 調査期間の配列 ['start' => timestamp, 'end' => timestamp] または投票がない場合はnull
 */
function kashiwazaki_poll_get_survey_period( $poll_id ) {
    $voted_ips = get_post_meta( $poll_id, '_kashiwazaki_poll_voted_ips', true );
    if ( ! is_array( $voted_ips ) || empty( $voted_ips ) ) {
        return null;
    }
    return array(
        'start' => min( $voted_ips ),
        'end'   => max( $voted_ips ),
    );
}

/**
 * データセットファイルの保存ベースディレクトリ（wp_upload_dir 配下）。
 *
 * 以前はプラグインディレクトリ内(KASHIWAZAKI_POLL_DIR.'datasets/')に生成していたが、
 * WordPress のプラグイン更新時にプラグインフォルダが削除→再配置されるため全データが
 * 消失していた。uploads 配下に置くことで更新の影響を受けないようにする。
 *
 * @return string 末尾スラッシュ付きの絶対パス。
 */
function kashiwazaki_poll_datasets_base_dir() {
    $upload = wp_upload_dir();
    return trailingslashit( $upload['basedir'] ) . 'kashiwazaki-poll-datasets/';
}

/**
 * データセットファイルの公開ベースURL（wp_upload_dir 配下）。
 *
 * @return string 末尾スラッシュ付きのURL。
 */
function kashiwazaki_poll_datasets_base_url() {
    $upload = wp_upload_dir();
    return trailingslashit( $upload['baseurl'] ) . 'kashiwazaki-poll-datasets/';
}

function kashiwazaki_poll_get_dataset_file_path( $poll_id, $file_type ) {
    $allowed_types = ['csv', 'xml', 'yaml', 'json', 'svg'];
    if ( ! in_array( $file_type, $allowed_types ) ) {
        return false;
    }
    return kashiwazaki_poll_datasets_base_dir() . "{$file_type}/{$poll_id}.{$file_type}";
}

function kashiwazaki_poll_get_dataset_file_url( $poll_id, $file_type ) {
    $allowed_types = ['csv', 'xml', 'yaml', 'json', 'svg'];
    if ( ! in_array( $file_type, $allowed_types ) ) {
        return false;
    }
    return kashiwazaki_poll_datasets_base_url() . "{$file_type}/{$poll_id}.{$file_type}";
}

function kashiwazaki_poll_get_dataset_index_url() {
    return home_url( '/datasets/' );
}

function kashiwazaki_poll_get_single_dataset_page_url( $poll_id, $file_type ) {
    if ( $file_type === 'html' ) {
        return home_url( "/datasets/detail-{$poll_id}/" );
    }
    $allowed_types = ['csv', 'xml', 'yaml', 'json', 'svg'];
    if ( ! in_array( $file_type, $allowed_types ) ) {
        return false;
    }
    return home_url( "/datasets/{$file_type}/detail-{$poll_id}/" );
}

/**
 * カラーテーマ設定を取得
 *
 * @return array カラーテーマ名と設定値の配列
 */
function kashiwazaki_poll_get_color_theme() {
    $settings = get_option( 'kashiwazaki_poll_settings', array( 'dataset_color_theme' => 'minimal' ) );
    $color_theme_name = isset($settings['dataset_color_theme']) ? $settings['dataset_color_theme'] : 'minimal';

    // カラーテーマ定義
    $themes = array(
        'minimal' => array(
            'body_bg' => '#ffffff',
            'body_color' => '#333333',
            'header_bg' => '#f8f9fa',
            'header_color' => '#333333',
            'accent_color' => '#6c757d',
            'button_primary' => '#6c757d',
            'button_secondary' => '#e9ecef',
            'border_color' => '#ddd',
            'tag_bg' => '#95a5a6',
            'link_color' => '#6c757d'
        ),
        'blue' => array(
            'body_bg' => '#ffffff',
            'body_color' => '#333333',
            'header_bg' => '#0073aa',
            'header_color' => '#ffffff',
            'accent_color' => '#0073aa',
            'button_primary' => '#0073aa',
            'button_secondary' => '#e9ecef',
            'border_color' => '#ddd',
            'tag_bg' => '#0073aa',
            'link_color' => '#0073aa'
        ),
        'green' => array(
            'body_bg' => '#ffffff',
            'body_color' => '#333333',
            'header_bg' => '#28a745',
            'header_color' => '#ffffff',
            'accent_color' => '#28a745',
            'button_primary' => '#28a745',
            'button_secondary' => '#e9ecef',
            'border_color' => '#ddd',
            'tag_bg' => '#28a745',
            'link_color' => '#28a745'
        ),
        'orange' => array(
            'body_bg' => '#ffffff',
            'body_color' => '#333333',
            'header_bg' => '#fd7e14',
            'header_color' => '#ffffff',
            'accent_color' => '#fd7e14',
            'button_primary' => '#fd7e14',
            'button_secondary' => '#e9ecef',
            'border_color' => '#ddd',
            'tag_bg' => '#fd7e14',
            'link_color' => '#fd7e14'
        ),
        'purple' => array(
            'body_bg' => '#ffffff',
            'body_color' => '#333333',
            'header_bg' => '#6f42c1',
            'header_color' => '#ffffff',
            'accent_color' => '#6f42c1',
            'button_primary' => '#6f42c1',
            'button_secondary' => '#e9ecef',
            'border_color' => '#ddd',
            'tag_bg' => '#6f42c1',
            'link_color' => '#6f42c1'
        ),
        'dark' => array(
            'body_bg' => '#2c3e50',
            'body_color' => '#ecf0f1',
            'header_bg' => '#34495e',
            'header_color' => '#ecf0f1',
            'accent_color' => '#3498db',
            'button_primary' => '#3498db',
            'button_secondary' => '#34495e',
            'border_color' => '#555',
            'tag_bg' => '#7f8c8d',
            'link_color' => '#3498db'
        )
    );

    $theme_colors = isset($themes[$color_theme_name]) ? $themes[$color_theme_name] : $themes['minimal'];

    return array(
        'name' => $color_theme_name,
        'colors' => $theme_colors
    );
}

/**
 * 選択肢が編集された時、得票数(counts)を選択肢テキストで再マッピングする。
 *
 * counts は配列index基準のため、選択肢の追加/削除/並べ替えをそのまま保存すると
 * 過去票が別ラベルに付け替わったり破損する。本関数は旧選択肢テキスト→得票数の
 * 対応を作り、新選択肢の順で再構築する。一致する選択肢の票は維持され、削除された
 * 選択肢の票は破棄、新規選択肢は0で開始する（テキスト変更=改名は別選択肢扱い）。
 *
 * 旧選択肢メタを読むため、_kashiwazaki_poll_options を update する「前」に呼ぶこと。
 *
 * @param int   $post_id     投票投稿ID
 * @param array $new_options 新しい選択肢配列（サニタイズ済み）
 * @return void
 */
function kashiwazaki_poll_remap_counts_for_new_options( $post_id, $new_options ) {
    if ( ! is_array( $new_options ) ) {
        return;
    }
    $old_options = get_post_meta( $post_id, '_kashiwazaki_poll_options', true );
    $old_counts  = get_post_meta( $post_id, '_kashiwazaki_poll_counts', true );
    // 旧データが無い（新規poll等）、または票が無いならリマップ不要。
    if ( ! is_array( $old_options ) || ! is_array( $old_counts ) || empty( $old_counts ) ) {
        return;
    }
    // 選択肢に変更が無ければ何もしない。
    if ( $old_options === $new_options ) {
        return;
    }
    // 旧: 選択肢テキスト => 得票数（同一テキストは合算）。
    $by_text = array();
    foreach ( $old_options as $i => $txt ) {
        $c = isset( $old_counts[ $i ] ) ? intval( $old_counts[ $i ] ) : 0;
        $by_text[ $txt ] = ( isset( $by_text[ $txt ] ) ? $by_text[ $txt ] : 0 ) + $c;
    }
    // 新選択肢の順で再構築（マッチしたテキストは一度だけ消費）。
    $new_counts = array();
    foreach ( $new_options as $txt ) {
        if ( array_key_exists( $txt, $by_text ) ) {
            $new_counts[] = $by_text[ $txt ];
            unset( $by_text[ $txt ] );
        } else {
            $new_counts[] = 0;
        }
    }
    update_post_meta( $post_id, '_kashiwazaki_poll_counts', $new_counts );
    // 注: データファイル(CSV/JSON等)の再生成は呼び出し側で、新しい
    // _kashiwazaki_poll_options を保存した「後」に行うこと。generator は
    // options をメタから読むため、ここで再生成すると旧ラベル×新カウントで
    // ファイルが破損する。
}

/**
 * 集計結果を静的HTMLテーブルとして出力する（中央詳細ページ用）。
 *
 * JSON-LD / Chart.js とは別に、JS非実行のクローラーやスクリーンリーダーにも
 * 数値が届くようサーバーサイドで静的レンダリングする。数値ソースは
 * file-generators と同一（_kashiwazaki_poll_counts）、割合の丸めも同一規則
 * （round(count/total*100, 2)）で出力し、CSV/JSON 等とずれないようにする。
 *
 * @param int $poll_id 投票投稿ID
 * @return string テーブルHTML（選択肢が無ければ空文字）
 */
function kashiwazaki_poll_render_results_table( $poll_id ) {
    $poll_id = intval( $poll_id );
    $options = get_post_meta( $poll_id, '_kashiwazaki_poll_options', true );
    if ( ! is_array( $options ) || empty( $options ) ) {
        return '';
    }
    // counts を options 数に正規化（file-generators.php と同一規則）。
    // これにより総投票数・割合が CSV/JSON 等の正規データファイルと完全一致する。
    $option_count = count( $options );
    $counts = get_post_meta( $poll_id, '_kashiwazaki_poll_counts', true );
    if ( ! is_array( $counts ) ) {
        $counts = array_fill( 0, $option_count, 0 );
    } elseif ( count( $counts ) < $option_count ) {
        $counts = array_pad( $counts, $option_count, 0 );
    } elseif ( count( $counts ) > $option_count ) {
        $counts = array_slice( $counts, 0, $option_count );
    }
    $total_votes = 0;
    foreach ( $counts as $c ) {
        $total_votes += intval( $c );
    }
    $question = get_the_title( $poll_id );

    // 集計時点（最終更新）＝ 最新投票時刻
    $voted_ips = get_post_meta( $poll_id, '_kashiwazaki_poll_voted_ips', true );
    $last_vote_time = ( is_array( $voted_ips ) && ! empty( $voted_ips ) ) ? max( $voted_ips ) : 0;

    ob_start();
    ?>
    <div class="kashiwazaki-poll-results-table-wrap">
        <table class="kashiwazaki-poll-results-table">
            <caption><?php echo esc_html( $question ); ?> ― 集計結果</caption>
            <thead>
                <tr>
                    <th scope="col" class="col-option">選択肢</th>
                    <th scope="col" class="col-count">得票数</th>
                    <th scope="col" class="col-percent">割合</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $options as $i => $opt ) :
                $count = isset( $counts[ $i ] ) ? intval( $counts[ $i ] ) : 0;
                ?>
                <tr>
                    <th scope="row" class="col-option"><?php echo esc_html( $opt ); ?></th>
                    <td class="col-count"><?php echo esc_html( $count ); ?>票</td>
                    <td class="col-percent"><?php
                        if ( $total_votes > 0 ) {
                            echo esc_html( round( ( $count / $total_votes ) * 100, 2 ) ) . '%';
                        } else {
                            echo '—';
                        }
                    ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th scope="row" class="col-option">総投票数</th>
                    <td class="col-count" colspan="2"><?php echo esc_html( $total_votes ); ?>票</td>
                </tr>
            </tfoot>
        </table>
        <?php if ( $total_votes > 0 && $last_vote_time > 0 ) : ?>
        <p class="kashiwazaki-poll-results-asof">集計時点: <?php echo esc_html( wp_date( 'Y/m/d H:i:s', $last_vote_time ) ); ?></p>
        <?php elseif ( $total_votes === 0 ) : ?>
        <p class="kashiwazaki-poll-results-asof no-votes">まだ投票がありません。</p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
