# 🚀 Kashiwazaki SEO XML VitalCheck

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.0.0-orange.svg)](https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-xml-vitalcheck/releases)

複数のXMLファイルURLをチェックし、XMLバージョン、フォーマットタイプ、件数を可視化するWordPressプラグインです。SEO対策研究室による開発。

> 🎯 **XMLサイトマップとRSSフィードの健全性をワンクリックで一括チェック**

## 主な機能

- **📊 XML解析機能**: XMLバージョン、フォーマットタイプ（Sitemap、RSS、Atom等）、件数を自動判定
- **🔍 到達性チェック**: XML内に含まれる各URLの生存確認を実行
- **⏰ スケジュール実行**: 定期的な自動チェック機能（cron対応）
- **📧 メール通知**: チェック結果を指定メールアドレスに自動送信
- **🚨 緊急停止機能**: 処理中の強制停止・セッションクリーンアップ
- **🛡️ セキュリティ**: 管理者権限必須、nonce検証、適切なサニタイズ処理

## 🚀 クイックスタート

1. プラグインファイルをWordPressの`wp-content/plugins/`ディレクトリにアップロード
2. WordPress管理画面で「プラグイン」→「インストール済みプラグイン」へ移動
3. 「Kashiwazaki SEO XML VitalCheck」を有効化
4. 管理画面に表示される「設定」リンクをクリック
5. URLリストにXMLサイトマップやRSSフィードのURLを入力
6. 「XMLを解析」ボタンでチェック実行

## 使い方

### 基本操作

1. **URLリスト設定**: XMLサイトマップやRSSフィードのURLを1行に1つずつ入力
   ```
   https://yoursite.com/sitemap.xml
   https://yoursite.com/feed/
   https://yoursite.com/sitemap_index.xml
   ```

2. **即座にチェック**: 「XMLを解析」ボタンで解析結果を表示

3. **到達性テスト**: 解析結果から「到達性をチェック」ボタンでXML内の各URLを検証

### 自動実行設定

- **定期実行**: チェックボックスで定期実行を有効化
- **実行時刻**: 日本時間での実行時刻を指定
- **通知設定**: メールアドレス入力でチェック結果を自動送信

## 技術仕様

- **対応WordPress**: 5.0以上
- **対応PHP**: 7.4以上
- **対応XML形式**: 
  - XMLサイトマップ（Sitemap Index、URL Set）
  - RSSフィード（RSS 2.0）
  - Atomフィード
- **cURL要件**: PHP cURL拡張が必要
- **権限**: 管理者権限（ログイン必須）

## 更新履歴

### [1.0.0] - 2025-09-10
- 初回リリース
- XML解析機能の実装
- 到達性チェック機能の追加
- スケジュール実行機能の実装
- メール通知機能の追加

## ライセンス
GPL-2.0-or-later

## サポート・開発者
**開発者**: 柏崎剛 (Tsuyoshi Kashiwazaki)  
**ウェブサイト**: https://www.tsuyoshikashiwazaki.jp/  
**サポート**: プラグインに関するご質問や不具合報告は、開発者ウェブサイトまでお問い合わせください。

## 🤝 貢献

- バグ報告や機能提案はGitHubのIssuesページで受け付けています
- プルリクエストによる改善提案も歓迎します
- 開発者ウェブサイトからの直接連絡も可能です

## 📞 サポート

- **技術サポート**: 開発者ウェブサイトのお問い合わせフォーム
- **バグ報告**: GitHubリポジトリのIssues
- **機能要望**: 開発者ウェブサイトまたはGitHub Issues

---

<div align="center">

**🔍 Keywords**: WordPress, XML, Sitemap, RSS, SEO, Cron, Health Check, Monitoring  

Made with ❤️ by [Tsuyoshi Kashiwazaki](https://github.com/TsuyoshiKashiwazaki)

</div>