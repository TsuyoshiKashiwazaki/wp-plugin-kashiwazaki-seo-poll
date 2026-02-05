# Kashiwazaki SEO Poll

![Version](https://img.shields.io/badge/Version-1.0.3-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)
![License](https://img.shields.io/badge/License-GPLv2-green.svg)

Google Dataset検索最適化対応の高機能データ収集プラグイン。投票・調査データを5種類のフォーマット（CSV/XML/YAML/JSON/SVG）で自動生成し、完全な構造化データでSEO効果を最大化します。

## 概要

**Kashiwazaki SEO Poll** は、単なる投票プラグインではありません。Google Dataset検索に完全対応し、収集したデータを自動的にSEO最適化されたデータセットとして公開できる、次世代のデータ収集プラグインです。

### Google Dataset検索最適化

このプラグインの最大の特徴は、**Google Dataset検索への完全対応**です：

- **5種類のデータフォーマット自動生成**: CSV、XML、YAML、JSON、SVGで集計データを自動出力
- **完全な構造化データ**: Schema.org Dataset型のJSON-LD、Dublin Coreメタタグを自動生成
- **専用サイトマップ**: データセット専用のXMLサイトマップ（sitemap-poll-datasets.xml）を自動生成
- **検索エンジン最適化**: Google Dataset検索に必要なすべてのメタデータを網羅

## 主な機能

### データ収集機能
- 単一選択（ラジオボタン）/ 複数選択（チェックボックス）に対応
- IPアドレス + Cookie による重複投票防止
- リセット日時による期間限定投票制限解除
- 投票データの個別リセット機能

### データセット自動生成
- **CSV**: ExcelやGoogleスプレッドシート互換の表形式データ
- **XML**: 構造化マークアップ、システム間連携用
- **YAML**: 設定ファイル形式、Automation向け
- **JSON**: API連携用の完全なJSONデータ
- **SVG**: レスポンシブなパイチャート画像（凡例付き）

### SEO・構造化データ
- Schema.org Dataset型のJSON-LD構造化データ
- Dublin Coreメタタグ（DC.title, DC.description, DC.typeなど）
- データセット専用サイトマップ（sitemap-poll-datasets.xml）
- パンくずリスト構造化データ（オプション）
- クリエイター情報（Person/Organization）の詳細設定

### ビジュアライゼーション
- Chart.js による円グラフ表示
- chartjs-plugin-datalabels でラベル表示
- グラフのPNG/JPEGダウンロード機能
- 投票前のプレビュー表示

### 管理機能
- ショートコード使用状況の自動追跡
- データセット一括生成機能
- カラーテーマ6種類（minimal, blue, green, orange, purple, dark）
- Creative Commonsライセンス選択（10種類以上）
- データセットバージョン管理
- キーワード設定

## インストール

1. プラグインファイル一式を `/wp-content/plugins/kashiwazaki-seo-poll/` にアップロード
2. WordPress管理画面の「プラグイン」ページで「Kashiwazaki SEO Poll」を有効化
3. 「Kashiwazaki SEO Poll」メニューから新規データを作成
4. タイトルに質問文を入力し、選択肢を設定して公開
5. ショートコード `[tk_poll id="123"]` を任意のページに挿入

## 使い方

### ショートコード

```
[tk_poll id="123"]
```

投稿・固定ページに上記ショートコードを挿入するだけで、投票フォームと集計グラフが表示されます。

### URL構造

プラグインは以下の専用URLを自動生成します：

- `/datasets/` - 全データセット一覧
- `/datasets/csv/` - CSV形式データセット一覧
- `/datasets/csv/detail-123/` - CSV形式個別データ
- `/datasets/detail-123/` - 投票ページ（単一投稿）

## データフォーマット

| フォーマット | 用途 | 特徴 |
|-----------|------|------|
| CSV | Excel、Google Sheets | UTF-8 BOM付き、著作権情報含む |
| XML | システム間連携 | メタデータ、タイトル、タイムスタンプ |
| YAML | 設定・自動化 | YAML形式で人間が読みやすい |
| JSON | API、プログラム連携 | フルメタデータ、分析ツール向け |
| SVG | ビジュアル表示、印刷 | レスポンシブパイチャート、凡例付き |

## 構造化データ

### Schema.org Dataset型

```json
{
  "@context": "https://schema.org/",
  "@type": "Dataset",
  "name": "質問文",
  "description": "詳細説明",
  "creator": [Person, Organization],
  "publisher": Organization,
  "datePublished": "ISO8601形式",
  "license": "CCライセンスURL",
  "variableMeasured": [PropertyValue...],
  "distribution": [DataDownload...]
}
```

### Dublin Coreメタタグ

- DC.title
- DC.description
- DC.type: Dataset
- DC.format
- DC.language
- DC.subject

## 技術仕様

- **WordPress**: 5.0以上
- **PHP**: 7.4以上
- **Chart.js**: 4.4.8（CDN経由）
- **chartjs-plugin-datalabels**: 2.2.0（CDN経由）

## セキュリティ

- Nonce検証（投票、リセット、設定保存）
- 入力サニタイズ（sanitize_text_field, sanitize_textarea_field）
- 出力エスケープ（esc_html, esc_attr, esc_url）
- 権限チェック（manage_options, edit_post）
- 直接アクセス防止（ABSPATH定義チェック）

## 更新履歴

詳細は [CHANGELOG.md](CHANGELOG.md) を参照してください。

## ライセンス

GPLv2 or later

Copyright 2025 柏崎剛 (Tsuyoshi Kashiwazaki)

## 作者

**柏崎剛 (Tsuyoshi Kashiwazaki)**
- Website: https://www.tsuyoshikashiwazaki.jp/
- Plugin URI: https://www.tsuyoshikashiwazaki.jp/

## サポート

プラグインに関するご意見・ご要望・バグ報告は、以下のサイトまでお知らせください。
https://www.tsuyoshikashiwazaki.jp/
