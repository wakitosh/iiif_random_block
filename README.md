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

## **Configuration**

After installation, configure the module at **Administration \> Configuration \> Media \> IIIF Random Block Settings** (`/admin/config/media/iiif-random-block`).

The settings page has four sections:

### **1\. Source Information**

* **Source Name**: The name of the institution providing the data (e.g., "University of Tsukuba Library Digital Collections"). This is displayed in the block's footer.
* **Source URL**: The URL to the institution's main page. The source name will link to this URL.

### **2\. Display Settings**

* **Number** of **images to display**: The number of random images to select and display in the carousel (e.g., 5).
* **Carousel duration**: The time in seconds to display each image before switching to the next one.
* **Image size (max pixels)**: The maximum size (in pixels) for the longest side of the fetched IIIF image. This controls the image quality and file size. A value of `800` is a good starting point.

### **3\. Image Selection Rules**

This section allows you to define advanced rules for selecting an image based on the total number of canvases (pages) in a manifest. The module evaluates rules from top to bottom and uses the first one that matches. If no rules match, a random image is selected from all available canvases.

Enter one rule per line in the format: Condition \=\> Action

**Conditions:**

* 5: Matches if there are exactly 5 canvases.  
* 1-4: Matches if there are between 1 and 4 canvases (inclusive).  
* 10+: Matches if there are 10 or more canvases.

**Actions:**

* 3: Selects the 3rd canvas.  
* last: Selects the very last canvas.  
* random: Selects a random canvas from all available pages.  
* random(2-last): Selects a random canvas from the 2nd page to the last page.  
* random(1-last-1): Selects a random canvas from the 1st page to the second-to-last page (effectively excluding the last page).

### **4\. Update Frequency**

* **Update interval**: This determines how often the list of displayed images is refreshed with a new random set. The value is in seconds. This process is handled by Cron.
  * *Common values:*
    * 1 hour: `3600`
    * 12 hours: `43200`
    * 24 hours: `86400` (Default)

### **5\. Manifest URLs**

* **IIIF Manifest URLs**: A textarea where you must paste the list of IIIF manifest URLs, one URL per line. The module will randomly select from this list.

## **Cron Job**

This module relies on Drupal's cron system to function correctly.

### **What it does**

The cron job for this module performs the following tasks based on the "Update interval" you set:

1. It randomly selects a number of manifest URLs from your list (based on the "Number of images to display" setting).
2. For each selected manifest, it fetches the data, randomly picks an image (excluding the first page/canvas if there are multiple), and builds an IIIF image URL with the specified size.
3. It saves this new list of images to the database.

**If** cron does not **run, the images in the block will never be updated.** After the initial setup, you must run cron once to get the first set of images.

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

### **設定**

インストール後、**管理** \> 環境設定 \> メディア \> IIIF Random Block Settings (`/admin/config/media/iiif-random-block`) でモジュールの設定を行ってください。

設定ページには4つのセクションがあります。

#### **1\. 資料提供元情報 (Source Information)**

* **Source Name**: 資料を提供する機関名（例: 「筑波大学デジタルコレクション」）。このテキストはブロックのフッターに表示されます。
* **Source URL**: 資料提供機関のメインページへのURL。機関名はこのURLにリンクされます。

#### **2\. 表示設定 (Display Settings)**

* **Number of images to display**: カルーセルで表示するために選択するランダムな画像の数（例: 5）。
* **Carousel duration**: 各画像が次の画像に切り替わるまでの表示時間（秒単位）。
* **Image size (max pixels)**: 取得するIIIF画像の長辺の最大サイズ（ピクセル単位）。これは画質とファイルサイズを制御します。800 が手始めとして良い値です。

#### **3\. 画像選択ルール (Image Selection Rules)**

このセクションでは、マニフェスト内のキャンバス（ページ）の総数に基づいて、どの画像を選択するかの高度なルールを定義できます。モジュールはルールを上から順に評価し、最初に一致したものを適用します。どのルールにも一致しない場合は、利用可能なすべてのキャンバスからランダムに1つが選択されます。

条件 \=\> アクション の形式で、1行に1つのルールを記述します。

**条件 (Conditions):**

* 5: キャンバスがちょうど5つの場合に一致します。  
* 1-4: キャンバスが1つ以上4つ以下の場合に一致します。  
* 10+: キャンバスが10個以上の場合に一致します。

**アクション (Actions):**

* 3: 3番目のキャンバスを選択します。  
* last: 最後のキャンバスを選択します。  
* random: 利用可能なすべてのページからランダムに選択します。  
* random(2-last): 2ページ目から最後のページまでの範囲でランダムに選択します。  
* random(1-last-1): 1ページ目から最後から2番目のページまでの範囲でランダムに選択します（事実上、最終ページを除外します）。

#### **4\. 更新間隔 (Update Frequency)**

* **Update interval**: 表示される画像のリストが、新しいランダムなセットに更新される頻度を決定します。値は秒単位です。この処理はCronによって実行されます。
  * *一般的な値:*
    * 1時間: `3600`
    * 12時間: `43200`
    * 24時間: `86400` (デフォルト)

#### **5\. マニフェストURL (Manifest URLs)**

* **IIIF Manifest URLs**: IIIFマニフェストのURLのリストを、1行に1URLの形式で貼り付けるテキストエリアです。モジュールはこのリストからランダムに選択します。

### **Cronジョブ**

このモジュールが正しく機能するためには、DrupalのCronシステムに依存しています。

#### **Cronの役割**

このモジュールのCronジョブは、設定した「更新間隔」に基づいて以下のタスクを実行します。

1. あなたのURLリストから、ランダムにマニフェストURLをいくつか選択します（「表示する画像の数」の設定に基づきます）。
2. 選択された各マニフェストについて、データを取得し、ランダムに画像を選択し（複数ページある場合は1ページ目を除く）、指定されたサイズのIIIF画像URLを構築します。
3. この新しい画像のリストをデータベースに保存します。

**Cronが実行されないと、ブロックの画像は一切更新されません。** 初回設定後、最初の画像セットを取得するために一度Cronを実行する必要があります。
