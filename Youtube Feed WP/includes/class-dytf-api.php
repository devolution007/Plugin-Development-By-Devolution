<?php
if (!defined('ABSPATH')) exit;

/**
 * Thin YouTube Data API v3 client with transient caching.
 */
class DYTF_API {
    const BASE = 'https://www.googleapis.com/youtube/v3/';

    public static function settings() {
        return wp_parse_args(get_option('dytf_settings', array()), array(
            'api_key' => '',
            'channel' => '',
            'cache_minutes' => 60,
        ));
    }

    public static function bump_cache() {
        update_option('dytf_cache_version', time(), false);
    }

    private static function request($endpoint, array $args) {
        $s = self::settings();
        if ($s['api_key'] === '') {
            return new WP_Error('dytf_no_key', __('No YouTube API key configured. Add one under Settings → YouTube Feed.', 'youtube-feed-wp'));
        }
        $args['key'] = $s['api_key'];
        $res = wp_remote_get(add_query_arg($args, self::BASE . $endpoint), array('timeout' => 15));
        if (is_wp_error($res)) return $res;
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (wp_remote_retrieve_response_code($res) !== 200 || !is_array($body)) {
            $msg = isset($body['error']['message']) ? wp_strip_all_tags($body['error']['message']) : __('Unexpected response from YouTube.', 'youtube-feed-wp');
            return new WP_Error('dytf_api', $msg);
        }
        return $body;
    }

    private static function cached($key, $callback) {
        $ttl = max(1, (int) self::settings()['cache_minutes']) * MINUTE_IN_SECONDS;
        $key = 'dytf_' . md5($key . '|' . get_option('dytf_cache_version', 0));
        $hit = get_transient($key);
        if ($hit !== false) return $hit;
        $value = call_user_func($callback);
        if (!is_wp_error($value)) set_transient($key, $value, $ttl);
        return $value;
    }

    /** Accepts a channel ID (UC...), @handle, playlist ID, or a youtube.com URL; returns the uploads/playlist ID. */
    public static function resolve_playlist($channel = '', $playlist = '') {
        $playlist = trim($playlist);
        if ($playlist !== '') {
            if (preg_match('/[?&]list=([A-Za-z0-9_-]+)/', $playlist, $m)) $playlist = $m[1];
            return preg_match('/^[A-Za-z0-9_-]+$/', $playlist) ? $playlist : new WP_Error('dytf_bad', __('Invalid playlist ID.', 'youtube-feed-wp'));
        }
        $channel = trim($channel);
        if ($channel === '') $channel = trim(self::settings()['channel']);
        if ($channel === '') return new WP_Error('dytf_no_channel', __('No channel or playlist specified.', 'youtube-feed-wp'));

        if (preg_match('~youtube\.com/channel/(UC[A-Za-z0-9_-]{22})~', $channel, $m)) $channel = $m[1];
        elseif (preg_match('~youtube\.com/(@[A-Za-z0-9._-]+)~', $channel, $m)) $channel = $m[1];

        if (preg_match('/^UC[A-Za-z0-9_-]{22}$/', $channel)) return 'UU' . substr($channel, 2);

        if ($channel[0] !== '@') $channel = '@' . $channel;
        return self::cached('handle|' . $channel, function () use ($channel) {
            $r = self::request('channels', array('part' => 'contentDetails', 'forHandle' => $channel));
            if (is_wp_error($r)) return $r;
            if (empty($r['items'][0]['contentDetails']['relatedPlaylists']['uploads'])) {
                return new WP_Error('dytf_nochannel', __('YouTube channel not found.', 'youtube-feed-wp'));
            }
            return $r['items'][0]['contentDetails']['relatedPlaylists']['uploads'];
        });
    }

    /** @return array|WP_Error {items: [{id,title,thumb,date}], next: string} */
    public static function videos($playlist_id, $per_page = 9, $page_token = '') {
        $per_page = max(1, min(50, (int) $per_page));
        return self::cached("pl|$playlist_id|$per_page|$page_token", function () use ($playlist_id, $per_page, $page_token) {
            $args = array('part' => 'snippet,contentDetails', 'playlistId' => $playlist_id, 'maxResults' => $per_page);
            if ($page_token !== '') $args['pageToken'] = $page_token;
            $r = self::request('playlistItems', $args);
            if (is_wp_error($r)) return $r;
            $items = array();
            foreach ((array) ($r['items'] ?? array()) as $it) {
                $sn = $it['snippet'] ?? array();
                $id = $it['contentDetails']['videoId'] ?? ($sn['resourceId']['videoId'] ?? '');
                if ($id === '' || empty($sn['thumbnails']) || in_array($sn['title'] ?? '', array('Private video', 'Deleted video'), true)) continue;
                $th = $sn['thumbnails'];
                foreach (array('maxres', 'standard', 'high', 'medium', 'default') as $size) {
                    if (!empty($th[$size]['url'])) { $thumb = $th[$size]['url']; break; }
                }
                $items[] = array(
                    'id' => $id,
                    'title' => $sn['title'] ?? '',
                    'thumb' => $thumb,
                    'date' => $it['contentDetails']['videoPublishedAt'] ?? ($sn['publishedAt'] ?? ''),
                );
            }
            return array('items' => $items, 'next' => $r['nextPageToken'] ?? '');
        });
    }
}
