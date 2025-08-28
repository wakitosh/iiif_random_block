# **IIIF Random Block**

## **Introduction**

This module provides a Drupal block that displays a carousel of random images sourced from a user-defined list of IIIF manifests. It is designed to be simple, stable, and configurable through the UI.

## **Requirements**

* Drupal 10 or 11

## **Installation**

1. Place the `iiif_random_block` directory within your Drupal site's `/modules/custom` directory.
2. Go to the "Extend" page (`/admin/modules`) in your Drupal admin UI.
3. Find "IIIF Random Block" and check the box to enable it.
4. Click "Install".

## **Configuration and Usage**

After installation, configure the module at **Administration \> Configuration \> Media \> IIIF Random Block Settings** (`/admin/config/media/iiif-random-block`).

### **How it Works**

The main workflow is:

1. You provide a list of IIIF Manifest URLs.
2. You configure display options like the number of images and image size.
3. When you **save the configuration form**, the module immediately fetches a new random set of images and saves them to the database.
4. The block displays this saved set of images.
5. A **Cron job** runs periodically in the background to automatically refresh this set of images according to your "Update interval" setting.

### **Settings Page Sections**

The settings page has six main sections:

#### **1\. Currently Displayed Images**

This section shows a list of the images that are currently selected for display in the block. Each item includes links to the Image, the IIIF Manifest, and the original Source Page. This list is updated every time you save the configuration form or when the cron job runs.

#### **2\. Source Information**

* **Source Name**: The name of the institution providing the data (e.g., "University of Tsukuba Library Digital Collections"). Leave blank to hide this information in the block's footer.
* **Source URL**: The URL to the institution's main page.

#### **3\. Display Settings**

* **Number of images to display**: The number of random images to select and display in the carousel (e.g., 5).
* **Carousel duration**: The time in seconds to display each image before switching to the next one.
* **Image size (max pixels)**: The maximum width (in pixels) for the fetched IIIF image. The height will be scaled proportionally. A value of `800` is a good starting point.
* **Aspect ratio**: Controls the rendered box ratio and uses client-side cropping via CSS. Options: `1:1` (default), `4:3`, `16:9`, or **Custom ratio** (enter width and height). Cropping is applied with `aspect-ratio` and `object-fit: cover`. If you need server-side square images, consider IIIF Image API features (e.g., `region=square`) depending on your image server's support.

#### **4\. Image Selection Rules**

This section allows you to define advanced rules for selecting an image based on the total number of canvases (pages) in a manifest. The module evaluates rules from top to bottom and uses the first one that matches. If no rules match, a random image is selected from all available canvases.

Enter one rule per line in the format: `Condition => Action`

**Conditions:**

* `5`: Matches if there are exactly 5 canvases.
* `1-4`: Matches if there are between 1 and 4 canvases (inclusive).
* `10+`: Matches if there are 10 or more canvases.

**Actions:**

* `3`: Selects the 3rd canvas.
* `last`: Selects the very last canvas.
* `random`: Selects a random canvas from all available pages.
* `random(2-last)`: Selects a random canvas from the 2nd page to the last page.
* `random(1-last-1)`: Selects a random canvas from the 1st page to the second-to-last page (effectively excluding the last page).

#### **5\. Update Frequency**

* **Update interval**: This determines how often the list of displayed images is **automatically refreshed** by the Cron job. The value is in seconds.
  * *Common values:*
    * 1 hour: `3600`
    * 12 hours: `43200`
    * 24 hours: `86400` (Default)

#### **6\. Manifest URLs**

* **IIIF Manifest URLs**: A textarea where you must paste the list of IIIF manifest URLs, one URL per line. The module will randomly select from this list.

## **Cron Job**

While saving the settings form updates the images immediately, this module also relies on Drupal's cron system for **automatic, periodic updates**.

### **What it does**

The cron job for this module performs the same action as saving the settings form: it fetches a new random set of images based on your current settings and saves them to the database. This happens automatically based on the "Update interval" you set.

### **Why it is important**

The cron job ensures that the content of the block stays fresh over time without requiring you to manually save the settings form every day.

### **How to configure cron**

For a production website, you should configure a proper cron job on your server's command line. Please refer to the official Drupal documentation for detailed instructions on how to configure cron for your specific server environment.

## **日本語版 (Japanese Version)**

### **概要**

このモジュールは、ユーザーが定義したIIIFマニフェストのリストからランダムな画像を選択し、カルーセル形式で表示するDrupalブロックを提供します。シンプルで安定しており、UIを通じて設定できるように設計されています。

### **要件**

* Drupal 10 または 11

### **インストール**

1. `iiif_random_block` ディレクトリを、Drupalサイトの `/modules/custom` ディレクトリ内に配置します。
2. Drupalの管理画面で「機能拡張」ページ（`/admin/modules`）に移動します。
3. 「IIIF Random Block」を見つけてチェックボックスをオンにします。
4. 「インストール」ボタンをクリックします。

### **設定と使い方**

インストール後、**管理 \> 環境設定 \> メディア \> IIIF Random Block Settings** (`/admin/config/media/iiif-random-block`) でモジュールの設定を行ってください。

#### **動作の仕組み**

主なワークフローは以下の通りです。

1. あなたがIIIFマニフェストのURLリストを提供します。
2. 表示する画像の数やサイズなどの表示オプションを設定します。
3. **設定フォームを保存する**と、モジュールは**即座に**新しいランダムな画像セットを取得し、データベースに保存します。
4. ブロックは、この保存された画像セットを表示します。
5. **Cronジョブ**がバックグラウンドで定期的に実行され、「更新間隔」の設定に従ってこの画像セットを自動的に更新します。

#### **設定ページのセクション**

設定ページには6つの主要なセクションがあります。

##### **1\. 現在表示中の画像 (Currently Displayed Images)**

このセクションには、現在ブロックでの表示対象として選択されている画像のリストが表示されます。各項目には、画像そのもの、IIIFマニフェスト、そして元の資料ページへのリンクが含まれます。このリストは、設定フォームを保存するか、Cronジョブが実行されるたびに更新されます。

##### **2\. 資料提供元情報 (Source Information)**

* **Source Name**: 資料を提供する機関名（例: 「筑波大学デジタルコレクション」）。空欄にすると、この情報はブロックのフッターに表示されません。
* **Source URL**: 資料提供機関のメインページへのURL。

##### **3\. 表示設定 (Display Settings)**

* **Number of images to display**: カルーセルで表示するために選択するランダムな画像の数（例: 5）。
* **Carousel duration**: 各画像が次の画像に切り替わるまでの表示時間（秒単位）。
* **Image size (max pixels)**: 取得するIIIF画像の最大**幅**（ピクセル単位）。高さは縦横比を維持して自動的に調整されます。800 が手始めとして良い値です。
* **Aspect ratio（アスペクト比）**: 表示ボックスの比率を指定します。CSS によるクライアント側のトリミングで反映されます。選択肢は `1:1`（既定）、`4:3`、`16:9`、**Custom**（幅・高さを入力）です。`aspect-ratio` と `object-fit: cover` を用いて中央トリミングを行います。サーバ側で正方形を取得したい場合は、IIIF Image API の `region=square` などの機能（サーバ実装に依存）を検討してください。

##### **4\. 画像選択ルール (Image Selection Rules)**

このセクションでは、マニフェスト内のキャンバス（ページ）の総数に基づいて、どの画像を選択するかの高度なルールを定義できます。モジュールはルールを上から順に評価し、最初に一致したものを適用します。どのルールにも一致しない場合は、利用可能なすべてのキャンバスからランダムに1つが選択されます。

`条件 => アクション` の形式で、1行に1つのルールを記述します。

**条件 (Conditions):**

* `5`: キャンバスがちょうど5つの場合に一致します。
* `1-4`: キャンバスが1つ以上4つ以下の場合に一致します。
* `10+`: キャンバスが10個以上の場合に一致します。

**アクション (Actions):**

* `3`: 3番目のキャンバスを選択します。
* `last`: 最後のキャンバスを選択します。
* `random`: 利用可能なすべてのページからランダムに選択します。
* `random(2-last)`: 2ページ目から最後のページまでの範囲でランダムに選択します。
* `random(1-last-1)`: 1ページ目から最後から2番目のページまでの範囲でランダムに選択します（事実上、最終ページを除外します）。

##### **5\. 更新間隔 (Update Frequency)**

* **Update interval**: 表示される画像のリストが、Cronジョブによって**自動的に更新される**頻度を決定します。値は秒単位です。
  * *一般的な値:*
    * 1時間: `3600`
    * 12時間: `43200`
    * 24時間: `86400` (デフォルト)

##### **6\. マニフェストURL (Manifest URLs)**

* **IIIF Manifest URLs**: IIIFマニフェストのURLのリストを、1行に1URLの形式で貼り付けるテキストエリアです。モジュールはこのリストからランダムに選択します。

### **Cronジョブ**

設定フォームを保存すると画像は即座に更新されますが、このモジュールは**定期的・自動的な更新**のためにDrupalのCronシステムも利用します。

#### **Cronの役割**

このモジュールのCronジョブは、設定フォームの保存時と同じアクションを実行します。つまり、現在の設定に基づいて新しいランダムな画像セットを取得し、データベースに保存します。これは、あなたが設定した「更新間隔」に従って自動的に行われます。

#### **Cronの重要性**

Cronジョブは、あなたが毎日手動で設定フォームを保存しなくても、ブロックのコンテンツを長期的に新鮮に保つことを保証します。

#### **Cronの設定方法**

本番のウェブサイトでは、サーバーのコマンドラインで適切なCronジョブを設定すべきです。詳細な設定方法については、Drupalの公式ドキュメントを参照してください。