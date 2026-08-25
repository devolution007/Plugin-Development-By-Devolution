=== Cloud Gallery Connector for Microsoft 365 ===
Contributors: devolution
Tags: onedrive, sharepoint, gallery, microsoft 365, shortcode
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect WordPress to Microsoft 365 and embed authorized OneDrive or SharePoint folders as galleries, lists, or interactive folder browsers.

== Description ==
Cloud Gallery Connector for Microsoft 365 lets a site administrator authorize a Microsoft account and generate shortcodes for OneDrive and SharePoint document-library folders. It supports responsive image galleries, file lists, interactive folder navigation, and an accessible image lightbox.

This is an independent connector and is not affiliated with, endorsed by, or sponsored by Microsoft. Microsoft, Microsoft 365, OneDrive, and SharePoint are trademarks of the Microsoft group of companies.

== Installation ==
1. Upload the plugin folder and activate it.
2. Open Cloud Gallery in WordPress admin.
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

== External services ==
This plugin connects to Microsoft's identity platform and Microsoft Graph. These services are required for the plugin's core purpose and are contacted only after a site administrator supplies Microsoft app credentials and explicitly signs in.

The plugin sends OAuth authorization information and the requested site, drive, folder, and file identifiers to Microsoft. Microsoft Graph returns file and folder names, metadata, thumbnails, web links, and short-lived download URLs. On gallery pages, visitor browsers may request thumbnails or images from Microsoft-hosted URLs. Do not embed folders containing content that is not intended for the page's visitors.

Service documentation and policies:
* Microsoft Graph: https://learn.microsoft.com/graph/
* Microsoft Privacy Statement: https://privacy.microsoft.com/privacystatement
* Microsoft Services Agreement: https://www.microsoft.com/servicesagreement

== Frequently Asked Questions ==
= Does this plugin upload files to WordPress? =
No. Files remain in Microsoft 365. The plugin retrieves metadata and temporary Microsoft-hosted image URLs.

= Who can configure the Microsoft connection? =
Only WordPress administrators with the manage_options capability.

= Are private files automatically made public? =
The plugin does not change Microsoft sharing permissions. However, gallery image and thumbnail URLs displayed on a public WordPress page can be requested by that page's visitors. Only embed content intended for that audience.

= How do I disconnect the account? =
Open Cloud Gallery in WordPress admin and select Disconnect. Uninstalling the plugin also deletes its saved settings and refresh token.

== Privacy ==
The plugin does not include analytics, advertising, or telemetry. It stores Microsoft app settings and an encrypted Microsoft refresh token in the WordPress options table. It adds suggested disclosure text to WordPress's Privacy Policy Guide.

== Changelog ==
= 1.3.1 =
* Added WordPress nonces to public AJAX and admin folder navigation.
* Hardened output escaping and HTML allow-listing.
* Added external-service, privacy, trademark, and security disclosures.
* Updated plugin branding for WordPress.org trademark compliance.
* Added complete WordPress.org release metadata.

== Upgrade Notice ==
= 1.3.1 =
Security and directory-compliance update. Existing settings and shortcodes are preserved.

