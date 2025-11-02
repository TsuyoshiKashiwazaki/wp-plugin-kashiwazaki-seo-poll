=== Kashiwazaki SEO Poll ===
Contributors: kashiwazaki
Tags: poll, survey, dataset, google-dataset, structured-data, seo, data-collection
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Google Dataset検索最適化対応の高機能データ収集プラグイン。投票・調査データを5種類のフォーマット（CSV/XML/YAML/JSON/SVG）で自動生成し、完全な構造化データでSEO効果を最大化します。

== Description ==

**Kashiwazaki SEO Poll** は、単なる投票プラグインではありません。Google Dataset検索に完全対応し、収集したデータを自動的にSEO最適化されたデータセットとして公開できる、次世代のデータ収集プラグインです。

= Google Dataset検索最適化 =

このプラグインの最大の特徴は、**Google Dataset検索への完全対応**です：

* **5種類のデータフォーマット自動生成**: CSV、XML、YAML、JSON、SVGで集計データを自動出力
* **完全な構造化データ**: Schema.org Dataset型のJSON-LD、Dublin Coreメタタグを自動生成
* **専用サイトマップ**: データセット専用のXMLサイトマップ（sitemap-poll-datasets.xml）を自動生成
* **検索エンジン最適化**: Google Dataset検索に必要なすべてのメタデータを網羅

= 主な機能 =

**データ収集機能**
* 単一選択（ラジオボタン）/ 複数選択（チェックボックス）に対応
* IPアドレス + Cookie による重複投票防止
* リセット日時による期間限定投票制限解除
* 投票データの個別リセット機能

**データセット自動生成**
* **CSV**: ExcelやGoogleスプレッドシート互換の表形式データ
* **XML**: 構造化マークアップ、システム間連携用
* **YAML**: 設定ファイル形式、Automation向け
* **JSON**: API連携用の完全なJSONデータ
* **SVG**: レスポンシブなパイチャート画像（凡例付き）

**SEO・構造化データ**
* Schema.org Dataset型のJSON-LD構造化データ
* Dublin Coreメタタグ（DC.title, DC.description, DC.typeなど）
* データセット専用サイトマップ（sitemap-poll-datasets.xml）
* パンくずリスト構造化データ（オプション）
* クリエイター情報（Person/Organization）の詳細設定

**ビジュアライゼーション**
* Chart.js による円グラフ表示
* chartjs-plugin-datalabels でラベル表示
* グラフのPNG/JPEGダウンロード機能
* 投票前のプレビュー表示

**管理機能**
* ショートコード使用状況の自動追跡
* データセット一括生成機能
* カラーテーマ6種類（minimal, blue, green, orange, purple, dark）
* Creative Commonsライセンス選択（10種類以上）
* データセットバージョン管理
* キーワード設定

= ショートコード =

```
[tk_poll id="123"]
```

投稿・固定ページに上記ショートコードを挿入するだけで、投票フォームと集計グラフが表示されます。

= URL構造 =

プラグインは以下の専用URLを自動生成します：

* `/datasets/` - 全データセット一覧
* `/datasets/csv/` - CSV形式データセット一覧
* `/datasets/csv/detail-123/` - CSV形式個別データ
* `/datasets/detail-123/` - 投票ページ（単一投稿）

== Installation ==

1. プラグインファイル一式を `/wp-content/plugins/kashiwazaki-seo-poll/` にアップロード
2. WordPress管理画面の「プラグイン」ページで「Kashiwazaki SEO Poll」を有効化
3. 「Kashiwazaki SEO Poll」メニューから新規データを作成
4. タイトルに質問文を入力し、選択肢を設定して公開
5. ショートコード `[tk_poll id="123"]` を任意のページに挿入

== Frequently Asked Questions ==

= Google Dataset検索に表示されますか？ =

はい。このプラグインは以下の対応により、Google Dataset検索での発見性を最大化しています：

* Schema.org Dataset型のJSON-LD構造化データ
* Dublin Coreメタタグ
* 複数フォーマット（CSV/XML/YAML/JSON）での配布
* 専用サイトマップ（sitemap-poll-datasets.xml）

詳細説明を150文字以上設定することで、完全な構造化データが出力されます。

= どのようなデータ形式に対応していますか？ =

5種類のデータフォーマットに対応しています：

* **CSV**: Excel、Googleスプレッドシート互換
* **XML**: システム間連携用の構造化データ
* **YAML**: 設定ファイル形式、人間が読みやすい
* **JSON**: API連携、プログラム処理用
* **SVG**: ベクターグラフ画像、印刷・表示用

各フォーマットは投票データ更新時に自動生成されます。

= 重複投票を防げますか？ =

IPアドレスとCookieの二重チェックで重複投票を防止します。ただし、完全に防ぐことは技術的に困難です（VPN、シークレットモード等）。

管理画面の「投票制限の解除」機能で、リセット日時を更新すれば全ユーザーが再投票可能になります。

= データセットのライセンスを設定できますか？ =

はい。Creative Commonsライセンスを含む10種類以上のライセンスから選択できます：

* CC BY 4.0
* CC BY-SA 4.0
* CC BY-NC 4.0
* CC0 1.0（パブリックドメイン）
* その他多数

選択したライセンスは構造化データに自動反映されます。

= ショートコード使用状況を確認できますか？ =

はい。管理画面の投稿一覧に「使用記事」列が表示され、どのページでショートコードが使われているか確認できます。キャッシュ機能により高速に表示されます。

== Screenshots ==

1. 管理画面 - 投票設定メタボックス
2. フロントエンド - 投票フォーム表示
3. フロントエンド - 集計結果グラフ（Chart.js）
4. データセット一覧ページ（CSV/XML/YAML/JSON/SVG）
5. 個別データセットページ - ダウンロードリンク
6. 管理画面 - 基本設定ページ

== Changelog ==

= 1.0.0 - 2024-10-31 =
* 初回リリース
* カスタム投稿タイプ「poll」実装
* 投票機能（単一選択/複数選択）実装
* 5種類のデータフォーマット生成（CSV/XML/YAML/JSON/SVG）
* Schema.org Dataset型構造化データ対応
* Chart.js グラフ表示
* データセット専用URL構造実装

== Upgrade Notice ==

= 1.0.0 =
初回リリースです。Google Dataset検索最適化に完全対応した高機能データ収集プラグインをお試しください。

== Additional Notes ==

**Google Dataset検索最適化の詳細:**

このプラグインは、Googleの「Dataset Search」(https://datasetsearch.research.google.com/) に最適化されています。以下の要素により、データセットの発見性を最大化します：

* Schema.org Dataset型の完全な構造化データ
* creator / publisher / license の明示
* variableMeasured による変数定義
* distribution による配布形式の明示（CSV/XML/YAML/JSON）
* temporalCoverage / spatialCoverage のサポート

**技術仕様:**

* WordPress 5.0以上
* PHP 7.4以上
* Chart.js 4.4.8（CDN経由）
* chartjs-plugin-datalabels 2.2.0（CDN経由）

**著作権:**

Copyright 2025 柏崎剛 (Tsuyoshi Kashiwazaki)
Website: https://www.tsuyoshikashiwazaki.jp/

このプラグインはGPLv2ライセンスで配布されます。
