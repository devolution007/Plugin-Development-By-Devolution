<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit;

/**
 * Thin YouTube Data API v3 client with transient caching.
 */
final class DYTF_API {
    private const BASE = 'https://www.googleapis.com/youtube/v3/';

    /** @return array{api_key:string,channel:string,cache_minutes:int} */
    public static function settings(): array {
        $s = wp_parse_args((array) get_option('dytf_settings', array()), array(
            'api_key' => '',
            'channel' => '',
            'cache_minutes' => 60,
        ));
        return array(
            'api_key' => (string) $s['api_key'],
            'channel' => (string) $s['channel'],
            'cache_minutes' => (int) $s['cache_minutes'],
        );
    }

    public static function bump_cache(): void {
        update_option('dytf_cache_version', time(), false);
    }

    /** @return array<string,mixed>|WP_Error */
    private static function request(string $endpoint, array $args): array|WP_Error {
        $s = self::settings();
        if ($s['api_key'] === '') {
            return new WP_Error('dytf_no_key', __('No YouTube API key configured. Add one under Settings → YouTube Feed.', 'youtube-feed-wp'));
        }
        $args['key'] = $s['api_key'];
        $res = wp_remote_get(add_query_arg($args, self::BASE . $endpoint), array(
            'timeout' => 15,
            'redirection' => 0,
            'sslverify' => true,
            'limit_response_size' => 2 * MB_IN_BYTES,
        ));
        if (is_wp_error($res)) {
            // Never expose the request URL (it contains the API key).
            return new WP_Error('dytf_http', __('Could not reach the YouTube API.', 'youtube-feed-wp'));
        }
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (wp_remote_retrieve_response_code($res) !== 200 || !is_array($body)) {
            $msg = is_array($body) && isset($body['error']['message']) && is_string($body['error']['message'])
                ? wp_strip_all_tags($body['error']['message'])
                : __('Unexpected response from YouTube.', 'youtube-feed-wp');
            return new WP_Error('dytf_api', $msg);
        }
        return $body;
    }

    private static function cached(string $key, callable $callback): mixed {
        $ttl = max(1, self::settings()['cache_minutes']) * MINUTE_IN_SECONDS;
        $key = 'dytf_' . md5($key . '|' . (string) get_option('dytf_cache_version', 0));
        $hit = get_transient($key);
        if ($hit !== false) return $hit;
        $value = $callback();
        if (!is_wp_error($value)) set_transient($key, $value, $ttl);
        return $value;
    }

    public static function is_valid_id(string $id): bool {
        return (bool) preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $id);
    }

    /** Only accept thumbnails served from YouTube's image CDNs over HTTPS. */
    private static function safe_thumb(string $url): string {
        $p = wp_parse_url($url);
        $host = is_array($p) ? ($p['host'] ?? '') : '';
        if (($p['scheme'] ?? '') !== 'https' || !preg_match('/(^|\.)(ytimg\.com|ggpht\.com)$/Di', $host)) return '';
        return $url;
    }

    /** Accepts a channel ID (UC...), @handle, playlist ID, or a youtube.com URL; returns the uploads/playlist ID. */
    public static function resolve_playlist(string $channel = '', string $playlist = ''): string|WP_Error {
        $playlist = trim($playlist);
        if ($playlist !== '') {
            if (preg_match('/[?&]list=([A-Za-z0-9_-]+)/', $playlist, $m)) $playlist = $m[1];
            return self::is_valid_id($playlist) ? $playlist : new WP_Error('dytf_bad', __('Invalid playlist ID.', 'youtube-feed-wp'));
        }
        $channel = trim($channel);
        if ($channel === '') $channel = trim(self::settings()['channel']);
        if ($channel === '') return new WP_Error('dytf_no_channel', __('No channel or playlist specified.', 'youtube-feed-wp'));

        if (preg_match('~youtube\.com/channel/(UC[A-Za-z0-9_-]{22})~', $channel, $m)) $channel = $m[1];
        elseif (preg_match('~youtube\.com/(@[A-Za-z0-9._-]+)~', $channel, $m)) $channel = $m[1];

        if (preg_match('/^UC[A-Za-z0-9_-]{22}$/D', $channel)) return 'UU' . substr($channel, 2);

        if ($channel[0] !== '@') $channel = '@' . $channel;
        if (!preg_match('/^@[A-Za-z0-9._-]{1,100}$/D', $channel)) {
            return new WP_Error('dytf_bad', __('Invalid channel handle.', 'youtube-feed-wp'));
        }
        return self::cached('handle|' . $channel, static function () use ($channel) {
            $r = self::request('channels', array('part' => 'contentDetails', 'forHandle' => $channel));
            if (is_wp_error($r)) return $r;
            $uploads = $r['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? '';
            if (!is_string($uploads) || !self::is_valid_id($uploads)) {
                return new WP_Error('dytf_nochannel', __('YouTube channel not found.', 'youtube-feed-wp'));
            }
            return $uploads;
        });
    }

    /** @return array{items:list<array{id:string,title:string,thumb:string,date:string}>,next:string}|WP_Error */
    public static function videos(string $playlist_id, int $per_page = 9, string $page_token = ''): array|WP_Error {
        $per_page = max(1, min(50, $per_page));
        return self::cached("pl|$playlist_id|$per_page|$page_token", static function () use ($playlist_id, $per_page, $page_token) {
            $args = array('part' => 'snippet,contentDetails', 'playlistId' => $playlist_id, 'maxResults' => $per_page);
            if ($page_token !== '') $args['pageToken'] = $page_token;
            $r = self::request('playlistItems', $args);
            if (is_wp_error($r)) return $r;

            $items = array();
            foreach ((array) ($r['items'] ?? array()) as $it) {
                $sn = $it['snippet'] ?? array();
                $id = (string) ($it['contentDetails']['videoId'] ?? ($sn['resourceId']['videoId'] ?? ''));
                $title = (string) ($sn['title'] ?? '');
                if (!preg_match('/^[A-Za-z0-9_-]{11}$/D', $id) || empty($sn['thumbnails']) || in_array($title, array('Private video', 'Deleted video'), true)) continue;

                $thumb = '';
                foreach (array('maxres', 'standard', 'high', 'medium', 'default') as $size) {
                    if (!empty($sn['thumbnails'][$size]['url'])) {
                        $thumb = self::safe_thumb((string) $sn['thumbnails'][$size]['url']);
                        if ($thumb !== '') break;
                    }
                }
                if ($thumb === '') continue;

                $items[] = array(
                    'id' => $id,
                    'title' => $title,
                    'thumb' => $thumb,
                    'date' => (string) ($it['contentDetails']['videoPublishedAt'] ?? ($sn['publishedAt'] ?? '')),
                );
            }
            $next = (string) ($r['nextPageToken'] ?? '');
            return array('items' => $items, 'next' => preg_match('/^[A-Za-z0-9_-]{0,100}$/D', $next) ? $next : '');
        });
    }
}
