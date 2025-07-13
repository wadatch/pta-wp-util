# PTA Group Manager & Auto-Slug — 仕様書

> **目的**  
> 1. 区連（ブロック）単位のコンテンツ編集権限管理  
> 2. 投稿／固定ページ保存時の ASCII・英訳スラッグ自動生成  

---

## 1. プロジェクト概要

| 項目 | 内容 |
| --- | --- |
| **プラグイン名称（仮）** | PTA Group Manager & Auto-Slug |
| **対象 WP バージョン** | 6.0 以上 |
| **対応 PHP** | 8.1 以上 |
| **マルチサイト** | 非対応（将来的に拡張可能な構造にする） |

---

## 2. 用語／可変パラメータ

| キー | デフォルト値 | 説明 |
| --- | --- | --- |
| `pta_city_name` | "XXXX市" | 管理画面で自由入力 |
| `pta_blocks` | `[ward-1, ward-2, ward-3, ward-4, rard-5, ward-6, ward-7, ward-8, ward-9, ward-10]` | 区連スラッグ配列。順序・数は自由に変更可 |
| `translation_provider` | `mymemory` | `mymemory` / `deepl` / `none` |
| `mymemory_api_key` | ― | Provider が MyMemory の場合のみ必須 |
| `ascii_fallback` | `true` | 翻訳失敗時にローマナイズのみでスラッグ生成 |
| `charset_conversion_enabled` | `true` | 4バイト文字のHTML参照文字列変換の有効/無効 |

---

## 3. 機能要件

### 3.1 ロール & グループ管理

| カスタムロール | 基底 Capability | 追加／削除ポイント |
| --- | --- | --- |
| `pta_sys_admin` | `administrator` と同等 | WP 標準 `administrator` とは独立 |
| `pta_city_officer` | `editor` 相当 | 市協議会本部：CRUD 可能 |
| `pta_city_executive`<br>`pta_city_director`<br>`pta_project_committee`<br>`pta_pr_committee` | `author` + `read_private_pages` | 閲覧のみ |
| `pta_block_officer` | `editor` 相当 | **自区連配下のみ** CRUD 可 |
| `pta_school_officer` | `subscriber` + `read_private_pages` | 閲覧のみ |

* **グループ紐付け**  
  `user_meta` に `pta_block`（例: `urawa`）を保持し、プロフィール編集画面で選択可能にする。

### 3.2 アクセス制御ロジック

1. 投稿 / 固定ページ / メディアの **パス**を解析し、`/block-slug/` が含まれていれば区連を特定。  
2. `current_user` の `pta_block` と不一致なら `read` のみ許可。  
3. 一覧画面（投稿・メディア）は `pre_get_posts` でブロック外アイテムを除外。  
4. `pta_sys_admin` と `pta_city_officer` はフルアクセス。

### 3.3 ASCII・英訳スラッグ自動生成

| タイミング | フック | 処理 |
| --- | --- | --- |
| 新規下書き保存 | `wp_insert_post_data` | `post_name` が空なら実行 |
| 既存記事更新 | `save_post` | 編集者がスラッグを空に戻した場合のみ再生成 |

処理手順  
1. タイトル取得 → 文字変換（必要に応じて4バイト文字をHTML参照文字列に変換）。  
2. 翻訳 API 呼び出し（MyMemory）。  
3. レスポンスを `sanitize_title()` で ASCII 化。  
4. 既存衝突チェックし `-2`, `-3` … 付与。  
5. 失敗時は `remove_accents()` → ASCII 化（`ascii_fallback` が true の場合）。

### 3.4 文字変換システム

UTF-8（3バイト制限）環境での4バイト文字対応

| 対象文字 | 変換方法 | 例 |
| --- | --- | --- |
| 絵文字 | HTML数値参照 | 😀 → `&#128512;` |
| 特殊記号 | HTML数値参照 | ∞ → `&#8734;` |
| 通貨記号 | HTML数値参照 | € → `&#8364;` |

**動作条件**  
- データベースが `utf8`（`utf8mb4` 以外）の場合に自動適用  
- 設定で有効/無効の切り替え可能  
- スラッグ生成時とDB保存時に適用

### 3.5 設定ページ

`設定 → PTA` サブメニューを追加し、以下タブを提供。

| タブ | 内容 |
| --- | --- |
| **基本設定** | 市名、ブロック一覧（リピートフィールド） |
| **翻訳 API** | Provider 選択、API キー入力、文字変換設定 |
| **ロール/権限** | ロール表示名編集・マッピング確認 |

---

## 4. 非機能要件

| 区分 | 要件 |
| --- | --- |
| **セキュリティ** | nonce・capability チェック。外部 API は HTTPS。 |
| **i18n** | `load_plugin_textdomain()` による多言語対応（`.pot` 同梱）。 |
| **パフォーマンス** | 翻訳 API 結果は `transient` で 24h キャッシュ。 |
| **データベース互換性** | UTF-8（3バイト制限）環境での4バイト文字自動変換対応。 |
| **コーディング規約** | WordPress Coding Standards (PHPCS) & PSR-12 準拠。 |

---

## 5. 推奨フォルダ構成

```
pta-plugin/
├─ pta-plugin.php # メインプラグインファイル
├─ includes/
│ ├─ class-roles.php
│ ├─ class-access-control.php
│ ├─ class-slug-generator.php
│ ├─ class-settings.php
│ ├─ class-charset-converter.php
│ └─ helpers.php
└─ languages/
└─ pta-plugin.pot
```


---

## 6. 主要フック一覧

| Hook | 優先度 | ファイル | 概要 |
| --- | --- | --- | --- |
| `init` | 11 | class-roles.php | ロール登録 |
| `user_register` | 10 | class-roles.php | 初期 `pta_block` 設定 |
| `wp_insert_post_data` | 10 | class-slug-generator.php | 新規スラッグ生成 |
| `save_post` | 10 | class-slug-generator.php | 既存スラッグ再生成 |
| `pre_get_posts` | 10 | class-access-control.php | 一覧絞り込み |
| `admin_menu` | 99 | class-settings.php | 設定画面追加 |

---

## 7. 将来拡張アイデア（参考）

1. **REST API 連携**: 学校／区連データの外部共有  
2. **メール通知**: 区連内記事の公開・更新を自動配信  
3. **マルチサイト対応**: 各区連をサブサイト化し集中管理  

---

## 8. ClaudeCode への指示例

```text
### 指示
上記仕様書に従い、WordPress プラグインを PHP で生成してください。
- WP 6.0+ / PHP 8.1+
- フォルダ構成を保持
- ロール管理・アクセス制御・ASCII 英訳スラッグ生成を実装
- 外部翻訳 API は MyMemory をデフォルト実装、他 Provider はインターフェースのみ
- すべての定数・文言は i18n 関数でラップ

