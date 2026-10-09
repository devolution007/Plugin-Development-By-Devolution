=== Youtube Feed WP ===
Contributors: devolution
Tags: youtube, video, feed, shortcode, channel
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
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
= 1.0.0 =
* Initial release.
