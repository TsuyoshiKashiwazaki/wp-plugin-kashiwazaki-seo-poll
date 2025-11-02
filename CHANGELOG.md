# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[1.0.0]: https://github.com/TsuyoshiKashiwazaki/kashiwazaki-seo-poll/releases/tag/v1.0.0
