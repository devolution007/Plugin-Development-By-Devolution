=== MS SharePoint & OneDrive Gallery ===
Contributors: devolution
Tags: onedrive, sharepoint, gallery, microsoft 365, shortcode
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later

Connect WordPress to Microsoft 365 and embed OneDrive or SharePoint folders.

== Installation ==
1. Upload the plugin folder and activate it.
2. Open Microsoft Gallery in WordPress admin.
3. In Microsoft Entra admin center, create an App Registration.
4. Add the Web redirect URI shown by the plugin.
5. Add delegated Microsoft Graph permissions: User.Read, Files.Read.All, Sites.Read.All.
6. Create a client secret, save the Client ID, secret and tenant in WordPress, then sign in.
7. Browse a folder and copy its shortcode.

== Shortcode ==
[msog_gallery drive="DRIVE_ID" folder="FOLDER_ITEM_ID" view="gallery" columns="4"]

Options: view="gallery|list|folders", columns="1-6", limit="1-200", images_only="yes|no".

Interactive folder browser:
[msog_gallery drive="DRIVE_ID" folder="FOLDER_ITEM_ID" view="folders" columns="4"]

Files remain protected by Microsoft 365. Visitors see thumbnails and links generated through the connected account; links open on Microsoft where normal sharing/access rules apply.
