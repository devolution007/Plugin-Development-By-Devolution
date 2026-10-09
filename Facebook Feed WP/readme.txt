=== Facebook Feed WP ===
Contributors: devolution
Tags: facebook, feed, posts, shortcode, social
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Show a Facebook Page feed on your site with a shortcode.

== Description ==
Add `[devo_facebook_feed]` to any page or post. Responsive grid or list, lightbox viewer, load-more button, and cached API responses.

Requires a Facebook Page access token (Settings > Facebook Feed). The token must belong to the Page you want to display.

Examples:
`[devo_facebook_feed page="123456789" limit="6" columns="3"]`
`[devo_facebook_feed layout="list" open="facebook" excerpt="20"]`

Attributes: page, limit, columns, layout (grid|list), open (lightbox|facebook), excerpt, show_date, load_more.

== External services ==
This plugin calls the Facebook Graph API (graph.facebook.com) from your server, using your Page access token, to fetch post text, images, links and dates. Visitors load post images from Facebook's CDN (fbcdn.net) and follow links to facebook.com. See https://www.facebook.com/privacy/policy and https://www.facebook.com/terms.

== Changelog ==
= 1.0.0 =
* Initial release.
