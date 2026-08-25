# Cloud Gallery Connector for Microsoft 365

This repository contains the source code and installable ZIP package for the **Cloud Gallery Connector for Microsoft 365** WordPress plugin.

## Directory structure

```text
Plugin Development By Devolution/
|-- README.md
`-- Cloud Gallery Connector for Microsoft 365/
    |-- assets/
    |   |-- admin.css
    |   |-- admin.js
    |   |-- browser.css
    |   |-- browser.js
    |   |-- gallery.css
    |   |-- lightbox.css
    |   `-- lightbox.js
    |-- includes/
    |   |-- class-msog-admin.php
    |   |-- class-msog-graph.php
    |   `-- class-msog-shortcode.php
    |-- cloud-gallery-connector-for-microsoft-365-1.3.1.zip
    |-- ms-sharepoint-onedrive-gallery.php
    |-- readme.txt
    `-- uninstall.php
```

> The hidden `.git/` directory contains Git repository metadata and is intentionally omitted from the project tree.

## Directory contents

### `Cloud Gallery Connector for Microsoft 365/`

The WordPress plugin's source and release directory.

### `assets/`

Front-end and WordPress administration assets.

| File | Purpose |
| --- | --- |
| `admin.css` | Styles the plugin's WordPress administration page. |
| `admin.js` | Provides administration-page interactions. |
| `browser.css` | Styles the interactive folder browser. |
| `browser.js` | Handles interactive folder navigation. |
| `gallery.css` | Styles gallery and file-list layouts. |
| `lightbox.css` | Styles the accessible image lightbox. |
| `lightbox.js` | Provides image lightbox behavior. |

### `includes/`

The plugin's PHP classes.

| File | Purpose |
| --- | --- |
| `class-msog-admin.php` | Manages settings, Microsoft authorization, admin navigation, and shortcode generation. |
| `class-msog-graph.php` | Communicates with Microsoft Graph and handles tokens, caching, drives, folders, and files. |
| `class-msog-shortcode.php` | Registers and renders the `[msog_gallery]` shortcode and its supported views. |

### Root plugin files

| File | Purpose |
| --- | --- |
| `ms-sharepoint-onedrive-gallery.php` | Main plugin bootstrap file. It loads the PHP classes, declares version `1.3.1`, and registers privacy-policy content. |
| `readme.txt` | WordPress.org-compatible plugin documentation, metadata, FAQ, privacy details, and changelog. |
| `uninstall.php` | Removes plugin settings and stored authorization data when the plugin is uninstalled. |
| `cloud-gallery-connector-for-microsoft-365-1.3.1.zip` | Installable release package for version `1.3.1`. |

## Plugin details

- **Name:** Cloud Gallery Connector for Microsoft 365
- **Version:** 1.3.1
- **Author:** Devolution
- **Requires WordPress:** 6.0 or newer
- **Tested up to:** WordPress 7.1
- **Requires PHP:** 7.4 or newer
- **License:** GPL-2.0-or-later

## Features

- Connects WordPress to Microsoft 365 through Microsoft Graph.
- Browses OneDrive and SharePoint document-library folders.
- Displays content as a responsive gallery, file list, or interactive folder browser.
- Includes an accessible image lightbox.
- Generates folder-specific WordPress shortcodes.
- Supports cached Graph responses and encrypted refresh-token storage.

## Installation

1. In WordPress, go to **Plugins > Add New > Upload Plugin**.
2. Upload `cloud-gallery-connector-for-microsoft-365-1.3.1.zip`.
3. Activate **Cloud Gallery Connector for Microsoft 365**.
4. Open **Cloud Gallery** in the WordPress administration menu.
5. Create an app registration in Microsoft Entra.
6. Add the Web redirect URI displayed by the plugin.
7. Add these delegated Microsoft Graph permissions:
   - `User.Read`
   - `Files.Read.All`
   - `Sites.Read.All`
8. Create a client secret and save the Client ID, client secret, and tenant in the plugin settings.
9. Sign in with Microsoft, browse to a folder, and copy its shortcode.

## Shortcode usage

Gallery view:

```text
[msog_gallery drive="DRIVE_ID" folder="FOLDER_ITEM_ID" view="gallery" columns="4"]
```

Interactive folder browser:

```text
[msog_gallery drive="DRIVE_ID" folder="FOLDER_ITEM_ID" view="folders" columns="4"]
```

Supported shortcode options:

| Attribute | Values |
| --- | --- |
| `drive` | Microsoft Graph drive ID |
| `folder` | Folder item ID |
| `view` | `gallery`, `list`, or `folders` |
| `columns` | `1` through `6` |
| `limit` | `1` through `200` |
| `images_only` | `yes` or `no` |

## External service

The plugin uses Microsoft's identity platform and Microsoft Graph after a WordPress administrator configures Microsoft app credentials and authorizes an account. Files remain stored in Microsoft 365; the plugin retrieves file and folder metadata, thumbnails, links, and short-lived download URLs required to display the selected content.

This project is independent and is not affiliated with, endorsed by, or sponsored by Microsoft.
