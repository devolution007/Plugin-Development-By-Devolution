<?php
if (!defined('ABSPATH')) exit;

/**
 * Thin Facebook Graph API client with transient caching.
 */
class DFBF_API {
    const BASE = 'https://graph.facebook.com/v21.0/';

    public static function settings() {
        return wp_parse_args(get_option('dfbf_settings', array()), array(
            'access_token' => '',
            'page' => '',
            'cache_minutes' => 60,
        ));
    }

    public static function bump_cache() {
        update_option('dfbf_cache_version', time(), false);
    }

    private static function request($endpoint, array $args) {
        $s = self::settings();
        if ($s['access_token'] === '') {
            return new WP_Error('dfbf_no_token', __('No Facebook access token configured. Add one under Settings → Facebook Feed.', 'facebook-feed-wp'));
        }
        $res = wp_remote_get(add_query_arg($args, self::BASE . $endpoint), array(
            'timeout' => 15,
            'headers' => array('Authorization' => 'Bearer ' . $s['access_token']),
        ));
        if (is_wp_error($res)) return $res;
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (wp_remote_retrieve_response_code($res) !== 200 || !is_array($body)) {
            $msg = isset($body['error']['message']) ? wp_strip_all_tags($body['error']['message']) : __('Unexpected response from Facebook.', 'facebook-feed-wp');
            return new WP_Error('dfbf_api', $msg);
        }
        return $body;
    }

    private static function cached($key, $callback) {
        $ttl = max(1, (int) self::settings()['cache_minutes']) * MINUTE_IN_SECONDS;
        $key = 'dfbf_' . md5($key . '|' . get_option('dfbf_cache_version', 0));
        $hit = get_transient($key);
        if ($hit !== false) return $hit;
        $value = call_user_func($callback);
        if (!is_wp_error($value)) set_transient($key, $value, $ttl);
        return $value;
    }

    /** Accepts a numeric Page ID, a Page username, or a facebook.com URL; returns the numeric Page ID. */
    public static function resolve_page($page = '') {
        $page = trim($page);
        if ($page === '') $page = trim(self::settings()['page']);
        if ($page === '') return new WP_Error('dfbf_no_page', __('No Facebook Page specified.', 'facebook-feed-wp'));

        if (preg_match('~facebook\.com/profile\.php\?id=(\d+)~i', $page, $m)) {
            $page = $m[1];
        } elseif (preg_match('~facebook\.com/(?:p/)?([A-Za-z0-9.\-]+?)(?:-(\d{6,}))?/?(?:[?#].*)?$~i', $page, $m)) {
            $page = !empty($m[2]) ? $m[2] : $m[1];
        }
        $page = ltrim($page, '@');

        if (preg_match('/^\d+$/', $page)) return $page;
        if (!preg_match('/^[A-Za-z0-9.\-]+$/', $page)) return new WP_Error('dfbf_bad', __('Invalid Facebook Page.', 'facebook-feed-wp'));

        return self::cached('page|' . $page, function () use ($page) {
            $r = self::request(rawurlencode($page), array('fields' => 'id'));
            if (is_wp_error($r)) return $r;
            return !empty($r['id']) ? (string) $r['id'] : new WP_Error('dfbf_nopage', __('Facebook Page not found.', 'facebook-feed-wp'));
        });
    }

    /** @return array|WP_Error {items: [{id,message,image,link,date,video}], next: string} */
    public static function posts($page_id, $per_page = 9, $after = '') {
        $per_page = max(1, min(50, (int) $per_page));
        return self::cached("posts|$page_id|$per_page|$after", function () use ($page_id, $per_page, $after) {
            $args = array(
                'fields' => 'id,message,full_picture,permalink_url,created_time,attachments{media_type}',
                'limit' => $per_page,
            );
            if ($after !== '') $args['after'] = $after;
            $r = self::request(rawurlencode($page_id) . '/posts', $args);
            if (is_wp_error($r)) return $r;
            $items = array();
            foreach ((array) ($r['data'] ?? array()) as $p) {
                $message = isset($p['message']) ? (string) $p['message'] : '';
                $image = isset($p['full_picture']) ? (string) $p['full_picture'] : '';
                if (empty($p['id']) || empty($p['permalink_url']) || ($message === '' && $image === '')) continue;
                $media = $p['attachments']['data'][0]['media_type'] ?? '';
                $items[] = array(
                    'id' => $p['id'],
                    'message' => $message,
                    'image' => $image,
                    'link' => $p['permalink_url'],
                    'date' => $p['created_time'] ?? '',
                    'video' => $media === 'video',
                );
            }
            $next = !empty($r['paging']['next']) ? ($r['paging']['cursors']['after'] ?? '') : '';
            return array('items' => $items, 'next' => $next);
        });
    }
}
