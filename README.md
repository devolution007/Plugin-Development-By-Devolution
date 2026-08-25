# MS SharePoint & OneDrive Gallery

This repository contains **MS SharePoint & OneDrive Gallery**, a WordPress plugin by Devolution that connects WordPress to Microsoft 365 through Microsoft Graph. It lets administrators browse OneDrive or SharePoint document libraries and display a selected folder through a shortcode.

## Repository structure

```text
Plugin Development By Devolution/
|-- README.md
|-- MS sharepoint gallery.zip
`-- MS sharepoint gallery/
    |-- assets/
    |   |-- admin.css
    |   |-- admin.js
    |   `-- gallery.css
    |-- includes/
    |   |-- class-msog-admin.php
    |   |-- class-msog-graph.php
    |   `-- class-msog-shortcode.php
    |-- ms-sharepoint-onedrive-gallery.php
    |-- ms-sharepoint-onedrive-gallery-1.0.0.zip
    |-- readme.txt
    `-- uninstall.php
```

## File and folder guide

| Path | Purpose |
| --- | --- |
| `MS sharepoint gallery/` | WordPress plugin source directory. |
| `assets/admin.css` | Styles for the Microsoft Gallery administration screen. |
| `assets/admin.js` | Admin-side interactions, including copy-to-clipboard controls. |
| `assets/gallery.css` | Front-end gallery and list presentation styles. |
| `includes/class-msog-admin.php` | Admin menu, plugin settings, OAuth connection flow, SharePoint/OneDrive browser, and shortcode generator. |
| `includes/class-msog-graph.php` | Microsoft Graph requests, OAuth token handling, caching, pagination, and refresh-token storage. |
| `includes/class-msog-shortcode.php` | Registers and renders the `[msog_gallery]` shortcode. |
| `ms-sharepoint-onedrive-gallery.php` | Main plugin bootstrap file and activation defaults. |
| `readme.txt` | WordPress-style plugin metadata and installation instructions. |
| `uninstall.php` | Removes the plugin settings and stored refresh token during uninstall. |
| `ms-sharepoint-onedrive-gallery-1.0.0.zip` | Versioned installable plugin package. |
| `MS sharepoint gallery.zip` | Repository-level packaged copy of the plugin. |

## Requirements

- WordPress 6.0 or newer
- PHP 7.4 or newer
- A Microsoft 365 account
- A Microsoft Entra app registration
- Delegated Microsoft Graph permissions:
  - `User.Read`
  - `Files.Read.All`
  - `Sites.Read.All`

## Installation

1. In WordPress, open **Plugins > Add New > Upload Plugin**.
2. Upload `ms-sharepoint-onedrive-gallery-1.0.0.zip` and activate it.
3. Open **Microsoft Gallery** in the WordPress admin menu.
4. Create an app registration in Microsoft Entra admin center.
5. Add the Web redirect URI displayed on the plugin settings page.
6. Add the delegated Microsoft Graph permissions listed above.
7. Create a client secret and save the Client ID, client secret, and tenant in WordPress.
8. Select **Sign in with Microsoft** to connect the account.
9. Browse to a OneDrive or SharePoint folder and copy its generated shortcode.

## Shortcode

```text
[msog_gallery drive="DRIVE_ID" folder="FOLDER_ITEM_ID" view="gallery" columns="4"]
```

### Options

| Attribute | Accepted values | Default | Description |
| --- | --- | --- | --- |
| `drive` | Microsoft Graph drive ID | Required | Selects the OneDrive or SharePoint document library. |
| `folder` | Microsoft Graph folder item ID or `root` | `root` | Selects the folder to display. |
| `view` | `gallery`, `list` | `gallery` | Controls the front-end layout. |
| `columns` | `1` to `6` | `4` | Sets the number of gallery columns. |
| `limit` | `1` to `200` | `100` | Limits the number of displayed files. |
| `images_only` | `yes`, `no` | `no` | Hides non-image files when enabled. |

Example list view:

```text
[msog_gallery drive="DRIVE_ID" folder="root" view="list" limit="50"]
```

## Security and data handling

- OAuth requests use a time-limited state value to help prevent request forgery.
- Administrative actions require the `manage_options` capability.
- The Microsoft refresh token is stored using AES-256-CBC encryption when OpenSSL is available.
- Access tokens and Graph responses are cached with WordPress transients.
- Files remain in Microsoft 365; generated links open on Microsoft and remain subject to the configured Microsoft sharing and access rules.
- Uninstalling the plugin deletes its saved settings and refresh token.

## Version

Current plugin version: **1.0.0**

## License

GPL-2.0-or-later
