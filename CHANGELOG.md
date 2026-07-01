# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.6] - 2026-07-01

### Fixed
- 1.0.5 以前からの更新時に集計データファイルが再生成されず、データセットページやダウンロードが 404（ソフト404含む）になる問題を修正。旧版（1.0.4 以前）はデータファイルをプラグインフォルダ内に保存していたため、WordPress の更新（旧フォルダ削除）でファイルが失われ、1.0.5 の「コピー移行」方式では復旧できなかった。更新後、DB に残る投票データから全公開分のデータファイルと専用サイトマップを自動的に再生成する方式に変更（バックグラウンド実行と管理画面アクセス時の実行の両経路で復旧。全形式が揃うまで一定間隔で再試行。投票データには影響しない）

## [1.0.5] - 2026-07-01

### Security
- 構造化データ（JSON-LD）およびスクリプトへの出力エスケープを強化し、クロスサイトスクリプティング（XSS）を防止
- CSVダウンロードの数式インジェクション（=、+、-、@ で始まるセル）を無害化
- ショートコード掲載ページ一覧の公開表示を公開済み投稿のみに限定（未公開投稿の情報漏洩を防止）
- キャッシュクリアのリダイレクトを wp_safe_redirect に変更（オープンリダイレクト対策）
- global $post 不在時の URL 生成を home_url 基準に変更（ホストヘッダ注入対策）
- クライアントIP取得を集約し、未設定時の防御とサニタイズを追加

### Fixed
- 集計データファイルの保存先をアップロードディレクトリへ変更し、プラグイン更新時のデータ消失を防止（既存データは自動移行）
- リバースプロキシ／CDN配下で最初の1人しか投票できない問題に対応（信頼プロキシ設定を追加）
- 投票日時・集計時点の表示をタイムゾーンに沿って統一（保存・表示ともに標準方式へ）
- 調査期間が設定されている場合に公開日メタが欠落する不具合を修正
- フォーマット別データセット一覧のページ数が実際の表示件数とずれる問題を修正
- サブディレクトリ設置環境でデータセットページのルーティングが正しく動作しない問題を修正

### Changed
- 集計データフォーマットへのリンクをサイトのURL設定基準で生成
- 質問文を管理画面で設定した見出しレベルで出力し、投票フォームの並び順をアクセシビリティに配慮
- データセット詳細ページの構造化データ（Dataset）の二重出力を解消
- 未使用コードの削除、メタボックスの説明文を実挙動に合わせて修正

## [1.0.4] - 2026-02-18

### Changed
- フォーマット別データセットページ（CSV/XML/YAML/JSON/SVG）のcanonical URLを中央の詳細ページ（/datasets/detail-{ID}/）に集約
- SEOクラスタリングの改善：5つのフォーマットページが1つの正規URLに統合

## [1.0.3] - 2026-02-05

### Fixed
- 編集画面から公開できない問題を修正（publishパラメータが送信されない問題）
- 投票済みユーザーが再度投票できる問題を修正（投票フォームの表示制御）
- 初期データなし時のプレビュー表示問題を修正

## [1.0.2] - 2026-01-02

### Added
- 調査期間表示機能を追加（最初の投票日〜最後の投票日）
- `kashiwazaki_poll_get_survey_period()` ヘルパー関数を追加
- データセット出力（CSV/XML/YAML/JSON）に調査期間フィールドを追加
- 構造化データにtemporalCoverageを追加
- 凡例テキスト省略機能（15文字以上で...表示）
- 凡例ホバー時のツールチップ表示（全文表示）

## [1.0.1] - 2025-12-05

### Fixed
- データセット詳細ページ（/datasets/detail-{id}/）の構造化データでurl/identifierがfalseになる問題を修正
- kashiwazaki_poll_get_single_dataset_page_url()がfile_type='html'の場合に正しいURLを返すよう修正

## [1.0.0] - 2024-10-31

### Added
- カスタム投稿タイプ「poll」実装
- 投票機能（単一選択/複数選択）
- 重複投票防止機能（IPアドレス + Cookie）
- 5種類のデータフォーマット自動生成
  - CSV（UTF-8 BOM付き、Excel互換）
  - XML（メタデータ含む）
  - YAML（設定ファイル形式）
  - JSON（API連携用）
  - SVG（パイチャート画像）
- Schema.org Dataset型JSON-LD構造化データ
- Dublin Coreメタタグ
- Chart.js グラフ表示機能
- ショートコード `[tk_poll id="123"]`
- データセット専用URL構造
- 管理画面メタボックス
  - 投票設定
  - 詳細説明
  - ライセンス選択
  - 見出しレベル選択
  - データセットバージョン
  - データセットキーワード
- 基本設定ページ
  - 構造化データ設定
  - Creator設定
  - データセットページタイトル設定
  - カラーテーマ設定
  - 地理的範囲設定
- データセット一括生成機能
- サイトマップ生成機能
- ショートコード使用状況キャッシュ管理

## [Unreleased]

### Planned
- データエクスポート機能の拡張
- 追加のグラフタイプ（棒グラフ、折れ線グラフ）
- データセットの期限設定機能
- REST API エンドポイント

[1.0.6]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-poll/releases/tag/v1.0.6
[1.0.5]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-poll/releases/tag/v1.0.5
[1.0.4]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-poll/releases/tag/v1.0.4
[1.0.3]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-poll/releases/tag/v1.0.3
[1.0.2]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-poll/releases/tag/v1.0.2
[1.0.1]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-poll/releases/tag/v1.0.1
[1.0.0]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-poll/releases/tag/v1.0.0
