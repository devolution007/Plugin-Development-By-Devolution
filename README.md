# Plugin Development By Devolution

A collection of WordPress plugins by **Devolution**. Each plugin lives in its own folder with its own source, `readme.txt`, and (where released) an installable ZIP.

| Plugin | Version | Shortcode | Summary |
| --- | --- | --- | --- |
| [Cloud Gallery Connector for Microsoft 365](#cloud-gallery-connector-for-microsoft-365) | 1.3.1 | `[msog_gallery]` | Embed OneDrive / SharePoint folders as galleries, file lists, or folder browsers. |
| [Youtube Feed WP](#youtube-feed-wp) | 1.0.0 | `[devo_youtube_feed]` | Show a YouTube channel or playlist feed with a lightbox player. |
| [Facebook Feed WP](#facebook-feed-wp) | 1.0.0 | `[devo_facebook_feed]` | Show a Facebook Page feed with a lightbox viewer. |

## Repository structure

```text
Plugin Development By Devolution/
|-- README.md
|-- PRODUCT-LISTING.md
|-- Cloud Gallery Connector for Microsoft 365/
|   |-- assets/
|   |   |-- admin.css
|   |   |-- admin.js
|   |   |-- browser.css
|   |   |-- browser.js
|   |   |-- gallery.css
|   |   |-- lightbox.css
|   |   `-- lightbox.js
|   |-- includes/
|   |   |-- class-msog-admin.php
|   |   |-- class-msog-graph.php
|   |   `-- class-msog-shortcode.php
|   |-- cloud-gallery-connector-for-microsoft-365-1.3.1.zip
|   |-- ms-sharepoint-onedrive-gallery.php
|   |-- product-cover.png
|   |-- readme.txt
|   `-- uninstall.php
|-- Youtube Feed WP/
    |-- assets/
    |   |-- feed.css
    |   `-- feed.js
    |-- includes/
    |   |-- class-dytf-admin.php
    |   |-- class-dytf-api.php
    |   `-- class-dytf-shortcode.php
    |-- readme.txt
    |-- uninstall.php
    `-- youtube-feed-wp.php
`-- Facebook Feed WP/
    |-- assets/
    |   |-- feed.css
    |   `-- feed.js
    |-- includes/
    |   |-- class-dfbf-admin.php
    |   |-- class-dfbf-api.php
    |   `-- class-dfbf-shortcode.php
    |-- facebook-feed-wp.php
    |-- readme.txt
    `-- uninstall.php
```

> The hidden `.git/` directory is omitted from the tree.

`PRODUCT-LISTING.md` holds marketing/listing copy for the plugins.

### Conventions

- Each plugin is self-contained: a main bootstrap file, an `includes/` folder of PHP classes (one class per file, `class-<prefix>-<name>.php`), an `assets/` folder for CSS/JS, `readme.txt`, and `uninstall.php` for cleanup.
- Every plugin uses its own constant/class/option prefix (`MSOG_` for Cloud Gallery, `DYTF_` / `dytf_` for Youtube Feed WP, `DFBF_` / `dfbf_` for Facebook Feed WP).
- To release, zip the plugin folder so the plugin directory is the top level of the archive, then upload via **Plugins > Add New > Upload Plugin**.

---

## Cloud Gallery Connector for Microsoft 365

Connects WordPress to Microsoft 365 through Microsoft Graph so OneDrive and SharePoint folders can be embedded with shortcodes.

- **Version:** 1.3.1 | **Requires WordPress:** 6.0+ | **Tested up to:** 7.1 | **PHP:** 7.4+ | **License:** GPL-2.0-or-later

### Files

| File | Purpose |
| --- | --- |
| `ms-sharepoint-onedrive-gallery.php` | Main bootstrap: loads classes, declares version, registers privacy-policy content. |
| `includes/class-msog-admin.php` | Settings, Microsoft authorization, admin navigation, shortcode generation. |
| `includes/class-msog-graph.php` | Microsoft Graph client: tokens, caching, drives, folders, files. |
| `includes/class-msog-shortcode.php` | Registers and renders `[msog_gallery]`. |
| `assets/admin.*` | Admin page styles and interactions. |
| `assets/browser.*` | Interactive folder browser. |
| `assets/gallery.css` | Gallery and file-list layouts. |
| `assets/lightbox.*` | Accessible image lightbox. |
| `readme.txt` | WordPress.org-style documentation, FAQ, privacy details, changelog. |
| `uninstall.php` | Removes settings and stored authorization data. |
| `cloud-gallery-connector-for-microsoft-365-1.3.1.zip` | Installable release package. |

### Installation

1. In WordPress, go to **Plugins > Add New > Upload Plugin** and upload `cloud-gallery-connector-for-microsoft-365-1.3.1.zip`.
2. Activate the plugin and open **Cloud Gallery** in the admin menu.
3. Create an app registration in Microsoft Entra and add the Web redirect URI shown by the plugin.
4. Add delegated Microsoft Graph permissions: `User.Read`, `Files.Read.All`, `Sites.Read.All`.
5. Create a client secret and save the Client ID, secret, and tenant in the plugin settings.
6. Sign in with Microsoft, browse to a folder, and copy its shortcode.

### Shortcode

```text
[msog_gallery drive="DRIVE_ID" folder="FOLDER_ITEM_ID" view="gallery" columns="4"]
[msog_gallery drive="DRIVE_ID" folder="FOLDER_ITEM_ID" view="folders" columns="4"]
```

| Attribute | Values |
| --- | --- |
| `drive` | Microsoft Graph drive ID |
| `folder` | Folder item ID |
| `view` | `gallery`, `list`, or `folders` |
| `columns` | `1` through `6` |
| `limit` | `1` through `200` |
| `images_only` | `yes` or `no` |

### External service

Uses Microsoft's identity platform and Microsoft Graph after an administrator configures app credentials and authorizes an account. Files stay in Microsoft 365; the plugin retrieves metadata, thumbnails, links, and short-lived download URLs. Independent project, not affiliated with or endorsed by Microsoft.

---

## Youtube Feed WP

Displays a YouTube channel or playlist feed on the front end with a shortcode.

- **Version:** 1.0.0 | **Requires WordPress:** 5.8+ | **PHP:** 7.4+ | **License:** GPL-2.0-or-later

### Features

- Grid or list layout, 1–6 columns, responsive down to one column on phones.
- Lightbox player using the privacy-friendly `youtube-nocookie.com` embed, or open videos on YouTube.
- "Load more" pagination via AJAX.
- Accepts a channel ID, `@handle`, channel URL, playlist ID, or playlist URL.
- Transient caching (default 60 minutes) to stay within the free API quota; one-click cache clear.
- Private and deleted videos are skipped; errors are shown to administrators only.

### Files

| File | Purpose |
| --- | --- |
| `youtube-feed-wp.php` | Main bootstrap: constants, class loading, default settings on activation. |
| `includes/class-dytf-api.php` | YouTube Data API v3 client with caching and channel/playlist resolution. |
| `includes/class-dytf-admin.php` | **Settings > YouTube Feed** page: API key, default channel, cache duration, usage guide. |
| `includes/class-dytf-shortcode.php` | Registers `[devo_youtube_feed]`, renders markup, handles the AJAX load-more endpoint. |
| `assets/feed.css` | Grid/list layout, play button, lightbox, responsive rules. |
| `assets/feed.js` | Lightbox player and load-more behavior. |
| `readme.txt` | WordPress.org-style documentation and external-service disclosure. |
| `uninstall.php` | Removes settings and cached transients. |

### Setup

1. Zip the `Youtube Feed WP` folder and upload it via **Plugins > Add New > Upload Plugin**, then activate.
2. In [Google Cloud Console](https://console.cloud.google.com/apis/library/youtube.googleapis.com), enable **YouTube Data API v3** and create an API key.
3. Go to **Settings > YouTube Feed**, paste the API key, and optionally set a default channel.
4. Add the shortcode to any page or post.

### Shortcode

```text
[devo_youtube_feed]
[devo_youtube_feed channel="@GoogleDevelopers" limit="6" columns="3"]
[devo_youtube_feed playlist="PLxxxxxxxx" layout="list" play="youtube"]
```

| Attribute | Values (default first) |
| --- | --- |
| `channel` | `@handle`, channel ID (`UC…`), or channel URL; defaults to the setting |
| `playlist` | Playlist ID or URL (overrides `channel`) |
| `limit` | `9` (1–50 videos per page) |
| `columns` | `3` (1–6) |
| `layout` | `grid` or `list` |
| `play` | `lightbox` or `youtube` |
| `show_title`, `show_date`, `load_more` | `yes` or `no` |

### External service

Your server calls the YouTube Data API (`googleapis.com`) with your API key to fetch titles, thumbnails, and dates. Visitors load thumbnails from `ytimg.com`, and the lightbox loads `youtube-nocookie.com`. Independent project, not affiliated with or endorsed by YouTube or Google.

---

## Facebook Feed WP

Displays a Facebook Page's posts on the front end with a shortcode.

- **Version:** 1.0.0 | **Requires WordPress:** 5.8+ | **PHP:** 7.4+ | **License:** GPL-2.0-or-later

### Features

- Grid or list layout, 1–6 columns, responsive down to one column on phones.
- Lightbox viewer (image plus full post text), or open posts on Facebook. Video posts always open on Facebook.
- "Load more" pagination via AJAX.
- Accepts a numeric Page ID, Page username, or facebook.com URL.
- Transient caching (default 60 minutes) to respect Graph API rate limits; one-click cache clear.
- Posts with no text and no image are skipped; errors are shown to administrators only.

### Files

| File | Purpose |
| --- | --- |
| `facebook-feed-wp.php` | Main bootstrap: constants, class loading, default settings on activation. |
| `includes/class-dfbf-api.php` | Facebook Graph API client with caching and Page resolution. |
| `includes/class-dfbf-admin.php` | **Settings > Facebook Feed** page: Page access token, default Page, cache duration, usage guide. |
| `includes/class-dfbf-shortcode.php` | Registers `[devo_facebook_feed]`, renders markup, handles the AJAX load-more endpoint. |
| `assets/feed.css` | Card grid/list layout, lightbox, responsive rules. |
| `assets/feed.js` | Lightbox viewer and load-more behavior. |
| `readme.txt` | WordPress.org-style documentation and external-service disclosure. |
| `uninstall.php` | Removes settings and cached transients. |

### Setup

1. Zip the `Facebook Feed WP` folder and upload it via **Plugins > Add New > Upload Plugin**, then activate.
2. Create an app at [Meta for Developers](https://developers.facebook.com/apps/) and, in the Graph API Explorer, generate a long-lived **Page access token** (`pages_read_engagement`, `pages_show_list`) for a Page you manage.
3. Go to **Settings > Facebook Feed**, paste the token, and set the default Page.
4. Add the shortcode to any page or post.

> Reading a Page's posts requires a token for that Page (or Meta's "Page Public Content Access" feature after app review). The plugin cannot display arbitrary third-party Pages without that approval.

### Shortcode

```text
[devo_facebook_feed]
[devo_facebook_feed page="123456789" limit="6" columns="3"]
[devo_facebook_feed layout="list" open="facebook" excerpt="20"]
```

| Attribute | Values (default first) |
| --- | --- |
| `page` | Page ID, username, or URL; defaults to the setting |
| `limit` | `9` (1–50 posts per page) |
| `columns` | `3` (1–6) |
| `layout` | `grid` or `list` |
| `open` | `lightbox` or `facebook` |
| `excerpt` | `30` (0–200 words; `0` hides text) |
| `show_date`, `load_more` | `yes` or `no` |

### External service

Your server calls the Facebook Graph API (`graph.facebook.com`) with your Page access token to fetch post text, images, links, and dates. Visitors load images from Facebook's CDN. Independent project, not affiliated with or endorsed by Meta or Facebook.
