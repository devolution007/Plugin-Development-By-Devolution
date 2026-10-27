=== Facebook Feed WP ===
Contributors: devolution
Tags: facebook, feed, posts, shortcode, social
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 8.3
Stable tag: 1.1.0
License: GPLv2 or later

Show a Facebook Page feed on your site with a shortcode.

== Description ==
Add `[devo_facebook_feed]` to any page or post. Responsive grid or list, lightbox viewer, load-more button, and cached API responses.

Connect with "Login with Facebook" (Settings > Facebook Feed) using your own Facebook app and an account that manages the Page you want to display.

Examples:
`[devo_facebook_feed page="123456789" limit="6" columns="3"]`
`[devo_facebook_feed layout="list" open="facebook" excerpt="20"]`

Attributes: page, limit, columns, layout (grid|list), open (lightbox|facebook), excerpt, link_text, show_date, load_more.

Excerpt options (Settings > Facebook Feed): default length in words, remove links, remove hashtags, and custom link text such as "Read more".

== External services ==
This plugin calls the Facebook Graph API (graph.facebook.com) from your server, using the Page access token obtained when you log in with Facebook, to fetch post text, images, links and dates. Visitors load post images from Facebook's CDN (fbcdn.net) and follow links to facebook.com. See https://www.facebook.com/privacy/policy and https://www.facebook.com/terms.

== Changelog ==
= 1.1.0 =
* Login with Facebook: connect a Page with one click; non-expiring Page tokens are stored for you (including Business Portfolio Pages).
* Excerpt controls: default length, remove links/hashtags from card text, custom link text (shortcode `excerpt` and `link_text` override).
* Requires PHP 8.3; strict types and typed signatures throughout.
* Hardening: strict ID/token/cursor validation, Facebook-only image and link hosts, no redirects, response size limit, secrets never exposed in errors, settings not autoloaded.

= 1.0.0 =
* Initial release.
