=== Kashiwazaki SEO XML VitalCheck ===
Contributors: tsuyoshikashiwazaki
Tags: xml, sitemap, seo, monitoring, feed, checker, xml validator, sitemap checker
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

複数のXMLファイル（サイトマップ、RSSフィード等）の健全性を一括チェック。定期実行とメール通知機能で、SEO対策に必要なXMLファイルの監視を自動化します。

== Description ==

**Kashiwazaki SEO XML VitalCheck**は、WordPressサイトのXMLファイル（サイトマップ、RSSフィード等）の健全性を監視するプラグインです。

= 主な機能 =

* **複数URL一括チェック** - 複数のXMLファイルURLを登録し、一括でチェック
* **XML解析** - XMLバージョン、フォーマットタイプ（Sitemap、RSS、Atom等）、アイテム数を自動検出
* **到達性チェック** - XMLファイル内のURLが実際にアクセス可能かを確認
* **定期実行** - 指定時刻に自動でチェックを実行（日本時間対応）
* **メール通知** - チェック結果を指定メールアドレスに自動送信
* **エラー検出** - XMLの解析エラーや空のコンテンツを検出して通知

= 対応XMLフォーマット =

* Sitemap Index
* Sitemap URL Set
* RSS 2.0
* Atom Feed
* その他のXML形式

= 使用シーン =

* SEO対策でサイトマップの健全性を定期監視したい
* 複数のXMLフィードを一元管理したい
* XMLファイルのエラーを早期発見したい
* サイトマップのインデックス状況を把握したい

== Installation ==

1. プラグインファイルを `/wp-content/plugins/kashiwazaki-xml-vitalcheck` ディレクトリにアップロード
2. WordPressの「プラグイン」メニューからプラグインを有効化
3. 「設定」→「XML VitalCheck」メニューから設定画面にアクセス
4. チェックしたいXMLファイルのURLを入力（1行に1URL）
5. 必要に応じて定期実行とメール通知を設定

== Frequently Asked Questions ==

= どのようなXMLファイルをチェックできますか？ =

サイトマップ（sitemap.xml）、RSSフィード、Atomフィード、その他の一般的なXML形式に対応しています。

= 定期実行の時刻は変更できますか？ =

はい、管理画面から日本時間で任意の時刻を設定できます。

= メール通知にはどのような情報が含まれますか？ =

各XMLファイルの状態（正常/エラー）、フォーマットタイプ、アイテム数、エラーの詳細が含まれます。

= SSL証明書のエラーが出る場合は？ =

プラグインはSSL証明書の検証を柔軟に処理しますが、問題が続く場合はサーバー管理者にご相談ください。

= チェック結果はどこに保存されますか？ =

最新のチェック結果はWordPressのオプションテーブルに保存され、管理画面でいつでも確認できます。

== Screenshots ==

1. 管理画面 - URLリストと設定
2. XML解析結果の表示
3. 到達性チェック結果
4. メール通知のサンプル

== Changelog ==

= 1.0.0 =
* 初回リリース
* 複数URL一括チェック機能
* XMLフォーマット自動検出
* 定期実行機能（Cron）
* メール通知機能
* 到達性チェック機能
* 日本時間対応
* SSL証明書の柔軟な処理
* ユーザーエージェント最適化

== Upgrade Notice ==

= 1.0.0 =
初回リリース。安定版です。

== システム要件 ==

* WordPress 5.8以上
* PHP 7.4以上
* wp_remote_get関数が使用可能
* Cronジョブが実行可能（定期実行機能を使用する場合）

== サポート ==

ご質問やバグ報告は、以下までお願いします：

* プラグインページ: https://tsuyoshikashiwazaki.jp/
* 作者サイト: https://tsuyoshikashiwazaki.jp/

== プライバシー ==

このプラグインは：

* 外部サービスにデータを送信しません（チェック対象のXMLファイルへのアクセスを除く）
* 個人情報を収集しません
* Cookieを使用しません
* トラッキングコードを含みません

== クレジット ==

開発: 柏崎剛（SEO対策研究室）