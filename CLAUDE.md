# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## プロジェクト概要

PTA Group Manager & Auto-Slug - WordPressプラグイン
- 区連（ブロック）単位のコンテンツ編集権限管理
- 投稿／固定ページ保存時のASCII・英訳スラッグ自動生成

**動作環境:**
- WordPress 6.0以上
- PHP 8.1以上
- マルチサイト非対応（将来的に拡張可能な構造）

## 開発コマンド

```bash
# WordPressコーディング規約チェック
phpcs --standard=WordPress .
phpcbf --standard=WordPress .  # 自動修正

# 国際化対応
wp i18n make-pot . languages/pta-plugin.pot

# プラグイン有効化
wp plugin activate pta-plugin
```

## アーキテクチャ

```
pta-plugin/
├─ pta-plugin.php         # メインプラグインファイル
├─ includes/
│  ├─ class-roles.php     # ロール管理
│  ├─ class-access-control.php  # アクセス制御ロジック
│  ├─ class-slug-generator.php  # スラッグ生成
│  ├─ class-settings.php  # 設定ページ
│  └─ helpers.php         # ヘルパー関数
└─ languages/
   └─ pta-plugin.pot      # 国際化テンプレート
```

## カスタムロールと権限

| ロール | 権限 | 説明 |
|--------|------|------|
| `pta_sys_admin` | administrator相当 | フルアクセス |
| `pta_city_officer` | editor相当 | 市協議会本部：全CRUD可能 |
| `pta_block_officer` | editor相当 | 自区連配下のみCRUD可能 |
| その他閲覧ロール | author + read_private_pages | 閲覧のみ |

ユーザーは`pta_block`メタデータで区連に紐付けられます。

## 主要フック

| Hook | 優先度 | ファイル | 概要 |
|------|--------|----------|------|
| `init` | 11 | class-roles.php | ロール登録 |
| `user_register` | 10 | class-roles.php | 初期pta_block設定 |
| `wp_insert_post_data` | 10 | class-slug-generator.php | 新規スラッグ生成 |
| `save_post` | 10 | class-slug-generator.php | 既存スラッグ再生成 |
| `pre_get_posts` | 10 | class-access-control.php | 一覧絞り込み |
| `admin_menu` | 99 | class-settings.php | 設定画面追加 |

## 設定可能パラメータ

| キー | デフォルト値 | 説明 |
|------|--------------|------|
| `pta_city_name` | "XXXX市" | 管理画面で自由入力 |
| `pta_blocks` | [ward-1...ward-10] | 区連スラッグ配列 |
| `translation_provider` | `mymemory` | mymemory/deepl/none |
| `mymemory_api_key` | ― | MyMemory APIキー |
| `ascii_fallback` | `true` | 翻訳失敗時のローマナイズ |

## アクセス制御ロジック

1. 投稿/固定ページ/メディアのパスから`/block-slug/`を解析
2. ユーザーの`pta_block`と不一致なら読み取りのみ許可
3. 一覧画面では`pre_get_posts`でブロック外アイテムを除外
4. `pta_sys_admin`と`pta_city_officer`はフルアクセス

## スラッグ自動生成

- 新規下書き保存時: `post_name`が空なら実行
- 既存記事更新時: スラッグが空に戻された場合のみ再生成
- 処理: タイトル→翻訳API→ASCII化→重複チェック→保存
- 翻訳結果は24時間transientキャッシュ

## セキュリティ要件

- 全ての管理操作でnonce・capability確認必須
- 外部APIはHTTPS必須
- ユーザー入力はWordPress関数でサニタイズ
- `current_user_can()`による権限チェック

## コーディング規約

- WordPress Coding Standards (PHPCS)準拠
- PSR-12準拠
- 全ての文言は国際化関数（`__()`, `_e()`等）でラップ
- エスケープ関数（`esc_html()`, `esc_attr()`等）の適切な使用