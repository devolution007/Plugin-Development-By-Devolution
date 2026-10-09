<?php
if (!defined('ABSPATH')) exit;

/**
 * Facebook Graph API client: "Login with Facebook" connection handling, Page token storage, cached post fetching.
 */
class DFBF_API {
    const VERSION = 'v21.0';
    const BASE = 'https://graph.facebook.com/v21.0/';
    const SCOPES = 'pages_show_list,pages_read_engagement,business_management';

    public static function settings() {
        return wp_parse_args(get_option('dfbf_settings', array()), array(
            'app_id' => '',
            'app_secret' => '',
            'page' => '',
            'cache_minutes' => 60,
        ));
    }

    /** @return array {user: string, pages: [id => {name, token}]} */
    public static function connection() {
        $c = get_option('dfbf_connection', array());
        return array(
            'user' => isset($c['user']) ? (string) $c['user'] : '',
            'pages' => isset($c['pages']) && is_array($c['pages']) ? $c['pages'] : array(),
        );
    }

    public static function disconnect() {
        delete_option('dfbf_connection');
        self::bump_cache();
    }

    public static function bump_cache() {
        update_option('dfbf_cache_version', time(), false);
    }

    /* ---------- Login with Facebook (OAuth) ---------- */

    public static function redirect_uri() {
        return admin_url('admin-post.php?action=dfbf_oauth');
    }

    public static function login_url($state) {
        return add_query_arg(array(
            'client_id' => self::settings()['app_id'],
            'redirect_uri' => self::redirect_uri(),
            'state' => $state,
            'scope' => self::SCOPES,
            'response_type' => 'code',
        ), 'https://www.facebook.com/' . self::VERSION . '/dialog/oauth');
    }

    /** Exchanges the OAuth code for a long-lived user token, then stores the managed Pages and their tokens. */
    public static function connect($code) {
        $s = self::settings();
        if ($s['app_id'] === '' || $s['app_secret'] === '') {
            return new WP_Error('dfbf_no_app', __('Save your Facebook App ID and App Secret first.', 'facebook-feed-wp'));
        }
        $short = self::graph('oauth/access_token', array(
            'client_id' => $s['app_id'], 'client_secret' => $s['app_secret'],
            'redirect_uri' => self::redirect_uri(), 'code' => $code,
        ));
        if (is_wp_error($short)) return $short;
        $long = self::graph('oauth/access_token', array(
            'grant_type' => 'fb_exchange_token', 'client_id' => $s['app_id'], 'client_secret' => $s['app_secret'],
            'fb_exchange_token' => $short['access_token'] ?? '',
        ));
        if (is_wp_error($long)) return $long;
        $token = $long['access_token'] ?? '';

        $me = self::graph('me', array('fields' => 'name', 'access_token' => $token));
        if (is_wp_error($me)) return $me;
        $accounts = self::graph('me/accounts', array('fields' => 'id,name,access_token', 'limit' => 100, 'access_token' => $token));
        if (is_wp_error($accounts)) return $accounts;

        $pages = array();
        foreach ((array) ($accounts['data'] ?? array()) as $p) {
            if (!empty($p['id']) && !empty($p['access_token'])) {
                $pages[(string) $p['id']] = array('name' => (string) ($p['name'] ?? $p['id']), 'token' => (string) $p['access_token']);
            }
        }

        // Pages managed through a Business Portfolio often don't appear in /me/accounts; read the Pages granted in the login dialog instead.
        $debug = self::graph('debug_token', array('input_token' => $token, 'access_token' => $s['app_id'] . '|' . $s['app_secret']));
        $granted = array();
        $scopes = array();
        if (!is_wp_error($debug)) {
            $scopes = (array) ($debug['data']['scopes'] ?? array());
            foreach ((array) ($debug['data']['granular_scopes'] ?? array()) as $g) {
                if (in_array($g['scope'] ?? '', array('pages_show_list', 'pages_read_engagement'), true)) {
                    $granted = array_merge($granted, array_map('strval', (array) ($g['target_ids'] ?? array())));
                }
            }
        }
        foreach (array_unique($granted) as $id) {
            if (isset($pages[$id])) continue;
            $p = self::graph(rawurlencode($id), array('fields' => 'id,name,access_token', 'access_token' => $token));
            if (!is_wp_error($p) && !empty($p['access_token'])) {
                $pages[$id] = array('name' => (string) ($p['name'] ?? $id), 'token' => (string) $p['access_token']);
            }
        }

        if (!$pages) {
            /* translators: %s: comma-separated list of permissions Facebook granted */
            return new WP_Error('dfbf_no_pages', sprintf(
                __('Connected, but Facebook returned no Pages. Permissions granted: %s. Click Reconnect, choose "Opt in to current Pages only" or select your Pages in the Facebook dialog, and make sure pages_show_list, pages_read_engagement and business_management are enabled for the app.', 'facebook-feed-wp'),
                $scopes ? implode(', ', array_map('sanitize_text_field', $scopes)) : __('unknown', 'facebook-feed-wp')
            ));
        }
        update_option('dfbf_connection', array('user' => (string) ($me['name'] ?? ''), 'pages' => $pages), false);

        if (!isset($pages[(string) $s['page']])) {
            $s['page'] = (string) key($pages);
            update_option('dfbf_settings', $s);
        }
        self::bump_cache();
        return true;
    }

    /* ---------- HTTP + cache ---------- */

    private static function graph($endpoint, array $args) {
        $res = wp_remote_get(add_query_arg($args, self::BASE . $endpoint), array('timeout' => 15));
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

    /** Accepts a connected Page's ID, name, or facebook.com URL (falls back to the default Page); returns its ID. */
    public static function resolve_page($page = '') {
        $pages = self::connection()['pages'];
        if (!$pages) return new WP_Error('dfbf_not_connected', __('No Facebook Page connected. Use "Connect with Facebook" under Settings → Facebook Feed.', 'facebook-feed-wp'));

        $page = trim($page);
        if ($page === '') $page = trim((string) self::settings()['page']);
        if ($page === '') return (string) key($pages);

        if (preg_match('~facebook\.com/profile\.php\?id=(\d+)~i', $page, $m) || preg_match('~facebook\.com/.*?-(\d{6,})/?(?:[?#].*)?$~i', $page, $m)) {
            $page = $m[1];
        }
        if (isset($pages[$page])) return (string) $page;
        foreach ($pages as $id => $p) {
            if (strcasecmp($p['name'], $page) === 0) return (string) $id;
        }
        return new WP_Error('dfbf_bad', __('That Page is not connected. Connect an account that manages it.', 'facebook-feed-wp'));
    }

    /** @return array|WP_Error {items: [{id,message,image,link,date,video}], next: string} */
    public static function posts($page_id, $per_page = 9, $after = '') {
        $page_id = (string) $page_id;
        $pages = self::connection()['pages'];
        if (empty($pages[$page_id]['token'])) return new WP_Error('dfbf_bad', __('That Page is not connected.', 'facebook-feed-wp'));
        $token = $pages[$page_id]['token'];

        $per_page = max(1, min(50, (int) $per_page));
        return self::cached("posts|$page_id|$per_page|$after", function () use ($page_id, $per_page, $after, $token) {
            $args = array(
                'fields' => 'id,message,full_picture,permalink_url,created_time,attachments{media_type}',
                'limit' => $per_page,
                'access_token' => $token,
            );
            if ($after !== '') $args['after'] = $after;
            $r = self::graph(rawurlencode($page_id) . '/posts', $args);
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
