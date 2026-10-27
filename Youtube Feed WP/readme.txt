=== Youtube Feed WP ===
Contributors: devolution
Tags: youtube, video, feed, shortcode, channel
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 8.5
Stable tag: 1.1.0
License: GPLv2 or later

Show a YouTube channel or playlist feed on your site with a shortcode.

== Description ==
Add `[devo_youtube_feed]` to any page or post. Responsive grid or list, lightbox player (privacy-friendly youtube-nocookie), load-more button, and cached API responses.

Requires a free YouTube Data API v3 key (Settings > YouTube Feed).

Examples:
`[devo_youtube_feed channel="@GoogleDevelopers" limit="6" columns="3"]`
`[devo_youtube_feed playlist="PLxxxxxxxx" layout="list" play="youtube"]`

Attributes: channel, playlist, limit, columns, layout (grid|list), play (lightbox|youtube), show_title, show_date, load_more.

== External services ==
This plugin calls the YouTube Data API (googleapis.com) from your server, using your API key, to fetch video titles, thumbnails and dates. Visitors load thumbnails from ytimg.com, and the lightbox player loads youtube-nocookie.com. See https://policies.google.com/privacy and https://www.youtube.com/t/terms.

== Changelog ==
= 1.1.0 =
* Requires PHP 8.5; strict types and typed signatures throughout.
* Hardening: strict ID/token/thumbnail-host validation, API key never exposed in errors, response size limit, API key no longer autoloaded.

= 1.0.0 =
* Initial release.
