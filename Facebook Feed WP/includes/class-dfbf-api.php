<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit;

/**
 * Facebook Graph API client: "Login with Facebook" connection handling, Page token storage, cached post fetching.
 */
final class DFBF_API {
    private const string VERSION = 'v21.0';
    private const string BASE = 'https://graph.facebook.com/' . self::VERSION . '/';
    private const string SCOPES = 'pages_show_list,pages_read_engagement,business_management';

    /** @return array{app_id:string,app_secret:string,page:string,cache_minutes:int,excerpt:int,link_label:string,strip_urls:int,strip_hashtags:int} */
    public static function settings(): array {
        $s = wp_parse_args((array) get_option('dfbf_settings', array()), array(
            'app_id' => '',
            'app_secret' => '',
            'page' => '',
            'cache_minutes' => 60,
            'excerpt' => 30,
            'link_label' => '',
            'strip_urls' => 0,
            'strip_hashtags' => 0,
        ));
        return array(
            'app_id' => (string) $s['app_id'],
            'app_secret' => (string) $s['app_secret'],
            'page' => (string) $s['page'],
            'cache_minutes' => (int) $s['cache_minutes'],
            'excerpt' => max(0, min(200, (int) $s['excerpt'])),
            'link_label' => trim((string) $s['link_label']) !== '' ? trim((string) $s['link_label']) : __('View on Facebook', 'facebook-feed-wp'),
            'strip_urls' => empty($s['strip_urls']) ? 0 : 1,
            'strip_hashtags' => empty($s['strip_hashtags']) ? 0 : 1,
        );
    }

    /** @return array{user:string,pages:array<string,array{name:string,token:string}>} */
    public static function connection(): array {
        $c = (array) get_option('dfbf_connection', array());
        $pages = array();
        foreach ((array) ($c['pages'] ?? array()) as $id => $p) {
            $id = (string) $id;
            if (!self::is_valid_id($id) || !is_array($p) || !self::is_valid_token($p['token'] ?? null)) continue;
            $pages[$id] = array('name' => (string) ($p['name'] ?? $id), 'token' => $p['token']);
        }
        return array('user' => (string) ($c['user'] ?? ''), 'pages' => $pages);
    }

    public static function disconnect(): void {
        delete_option('dfbf_connection');
        self::bump_cache();
    }

    public static function bump_cache(): void {
        update_option('dfbf_cache_version', time(), false);
    }

    public static function is_valid_id(string $id): bool {
        return (bool) preg_match('/^\d{1,30}$/D', $id);
    }

    public static function is_valid_cursor(string $cursor): bool {
        // Cursors are opaque base64-style strings that can be long; only reject anything that could break out of a query value.
        return (bool) preg_match('~^[A-Za-z0-9_=+/.~-]{0,4000}$~D', $cursor);
    }

    private static function is_valid_token(mixed $token): bool {
        return is_string($token) && preg_match('/^[A-Za-z0-9_-]{10,1000}$/D', $token) === 1;
    }

    /** Only accept images served from Facebook's CDNs over HTTPS. */
    private static function safe_image(string $url): string {
        $p = wp_parse_url($url);
        $host = is_array($p) ? ($p['host'] ?? '') : '';
        if (($p['scheme'] ?? '') !== 'https' || !preg_match('/(^|\.)(fbcdn\.net|fbsbx\.com|facebook\.com)$/Di', $host)) return '';
        return $url;
    }

    /** Only accept post links that point at facebook.com over HTTPS. */
    private static function safe_link(string $url): string {
        $p = wp_parse_url($url);
        $host = is_array($p) ? ($p['host'] ?? '') : '';
        if (($p['scheme'] ?? '') !== 'https' || !preg_match('/(^|\.)(facebook\.com|fb\.com)$/Di', $host)) return '';
        return $url;
    }

    /* ---------- Login with Facebook (OAuth) ---------- */

    public static function redirect_uri(): string {
        return admin_url('admin-post.php?action=dfbf_oauth');
    }

    public static function login_url(string $state): string {
        return add_query_arg(array(
            'client_id' => self::settings()['app_id'],
            'redirect_uri' => self::redirect_uri(),
            'state' => $state,
            'scope' => self::SCOPES,
            'response_type' => 'code',
        ), 'https://www.facebook.com/' . self::VERSION . '/dialog/oauth');
    }

    /** Exchanges the OAuth code for a long-lived user token, then stores the managed Pages and their tokens. */
    public static function connect(string $code): true|WP_Error {
        $s = self::settings();
        if ($s['app_id'] === '' || $s['app_secret'] === '') {
            return new WP_Error('dfbf_no_app', __('Save your Facebook App ID and App Secret first.', 'facebook-feed-wp'));
        }
        $no_token = new WP_Error('dfbf_api', __('Facebook did not return an access token.', 'facebook-feed-wp'));

        $short = self::graph('oauth/access_token', array(
            'client_id' => $s['app_id'], 'client_secret' => $s['app_secret'],
            'redirect_uri' => self::redirect_uri(), 'code' => $code,
        ));
        if (is_wp_error($short)) return $short;
        if (!self::is_valid_token($short['access_token'] ?? null)) return $no_token;

        $long = self::graph('oauth/access_token', array(
            'grant_type' => 'fb_exchange_token', 'client_id' => $s['app_id'], 'client_secret' => $s['app_secret'],
            'fb_exchange_token' => $short['access_token'],
        ));
        if (is_wp_error($long)) return $long;
        if (!self::is_valid_token($long['access_token'] ?? null)) return $no_token;
        $token = $long['access_token'];

        $me = self::graph('me', array('fields' => 'name', 'access_token' => $token));
        if (is_wp_error($me)) return $me;
        $accounts = self::graph('me/accounts', array('fields' => 'id,name,access_token', 'limit' => 100, 'access_token' => $token));
        if (is_wp_error($accounts)) return $accounts;

        $pages = array();
        foreach ((array) ($accounts['data'] ?? array()) as $p) {
            self::collect_page($pages, $p);
        }

        // Pages managed through a Business Portfolio often don't appear in /me/accounts; read the Pages granted in the login dialog instead.
        $debug = self::graph('debug_token', array('input_token' => $token, 'access_token' => $s['app_id'] . '|' . $s['app_secret']));
        $granted = array();
        $scopes = array();
        if (!is_wp_error($debug)) {
            $scopes = array_filter((array) ($debug['data']['scopes'] ?? array()), 'is_string');
            foreach ((array) ($debug['data']['granular_scopes'] ?? array()) as $g) {
                if (is_array($g) && in_array($g['scope'] ?? '', array('pages_show_list', 'pages_read_engagement'), true)) {
                    foreach ((array) ($g['target_ids'] ?? array()) as $tid) {
                        if (is_string($tid) || is_int($tid)) $granted[] = (string) $tid;
                    }
                }
            }
        }
        foreach (array_unique($granted) as $id) {
            if (isset($pages[$id]) || !self::is_valid_id($id)) continue;
            $p = self::graph($id, array('fields' => 'id,name,access_token', 'access_token' => $token));
            if (!is_wp_error($p)) self::collect_page($pages, $p);
        }

        if (!$pages) {
            return new WP_Error('dfbf_no_pages', sprintf(
                /* translators: %s: comma-separated list of permissions Facebook granted */
                __('Connected, but Facebook returned no Pages. Permissions granted: %s. Click Reconnect, choose "Opt in to current Pages only" or select your Pages in the Facebook dialog, and make sure pages_show_list, pages_read_engagement and business_management are enabled for the app.', 'facebook-feed-wp'),
                $scopes ? implode(', ', array_map('sanitize_text_field', $scopes)) : __('unknown', 'facebook-feed-wp')
            ));
        }
        update_option('dfbf_connection', array('user' => sanitize_text_field((string) ($me['name'] ?? '')), 'pages' => $pages), false);

        if (!isset($pages[$s['page']])) {
            $s['page'] = (string) array_key_first($pages);
            update_option('dfbf_settings', $s);
        }
        self::bump_cache();
        return true;
    }

    /** @param array<string,array{name:string,token:string}> $pages */
    private static function collect_page(array &$pages, mixed $p): void {
        if (!is_array($p)) return;
        $id = (string) ($p['id'] ?? '');
        if (!self::is_valid_id($id) || !self::is_valid_token($p['access_token'] ?? null)) return;
        $pages[$id] = array('name' => sanitize_text_field((string) ($p['name'] ?? $id)), 'token' => $p['access_token']);
    }

    /* ---------- HTTP + cache ---------- */

    /** @return array<string,mixed>|WP_Error */
    private static function graph(string $endpoint, array $args): array|WP_Error {
        $res = wp_remote_get(add_query_arg(array_map('rawurlencode', array_map('strval', $args)), self::BASE . $endpoint), array(
            'timeout' => 15,
            'redirection' => 0,
            'sslverify' => true,
            'limit_response_size' => 2 * MB_IN_BYTES,
        ));
        if (is_wp_error($res)) {
            // Never expose the request URL (it contains tokens and the app secret).
            return new WP_Error('dfbf_http', __('Could not reach the Facebook API.', 'facebook-feed-wp'));
        }
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (wp_remote_retrieve_response_code($res) !== 200 || !is_array($body)) {
            $msg = is_array($body) && isset($body['error']['message']) && is_string($body['error']['message'])
                ? wp_strip_all_tags($body['error']['message'])
                : __('Unexpected response from Facebook.', 'facebook-feed-wp');
            return new WP_Error('dfbf_api', $msg);
        }
        return $body;
    }

    private static function cached(string $key, callable $callback): mixed {
        $ttl = max(1, self::settings()['cache_minutes']) * MINUTE_IN_SECONDS;
        $key = 'dfbf_' . md5($key . '|' . (string) get_option('dfbf_cache_version', 0));
        $hit = get_transient($key);
        if ($hit !== false) return $hit;
        $value = $callback();
        if (!is_wp_error($value)) set_transient($key, $value, $ttl);
        return $value;
    }

    /** Accepts a connected Page's ID, name, or facebook.com URL (falls back to the default Page); returns its ID. */
    public static function resolve_page(string $page = ''): string|WP_Error {
        $pages = self::connection()['pages'];
        if (!$pages) return new WP_Error('dfbf_not_connected', __('No Facebook Page connected. Use "Connect with Facebook" under Settings → Facebook Feed.', 'facebook-feed-wp'));

        $page = trim($page);
        if ($page === '') $page = trim(self::settings()['page']);
        if ($page === '') return (string) array_key_first($pages);

        if (preg_match('~facebook\.com/profile\.php\?id=(\d+)~i', $page, $m) || preg_match('~facebook\.com/.*?-(\d{6,})/?(?:[?#].*)?$~iD', $page, $m)) {
            $page = $m[1];
        }
        if (isset($pages[$page])) return $page;
        foreach ($pages as $id => $p) {
            if (strcasecmp($p['name'], $page) === 0) return (string) $id;
        }
        return new WP_Error('dfbf_bad', __('That Page is not connected. Connect an account that manages it.', 'facebook-feed-wp'));
    }

    /** @return array{items:list<array{id:string,message:string,image:string,link:string,date:string,video:bool}>,next:string}|WP_Error */
    public static function posts(string $page_id, int $per_page = 9, string $after = ''): array|WP_Error {
        $pages = self::connection()['pages'];
        if (!isset($pages[$page_id])) return new WP_Error('dfbf_bad', __('That Page is not connected.', 'facebook-feed-wp'));
        if (!self::is_valid_cursor($after)) return new WP_Error('dfbf_bad', __('Invalid pagination cursor.', 'facebook-feed-wp'));
        $token = $pages[$page_id]['token'];

        $per_page = max(1, min(50, $per_page));
        return self::cached("posts|$page_id|$per_page|$after", static function () use ($page_id, $per_page, $after, $token) {
            $args = array(
                'fields' => 'id,message,full_picture,permalink_url,created_time,attachments{media_type}',
                'limit' => $per_page,
                'access_token' => $token,
            );
            if ($after !== '') $args['after'] = $after;
            $r = self::graph($page_id . '/posts', $args);
            if (is_wp_error($r)) return $r;

            $items = array();
            foreach ((array) ($r['data'] ?? array()) as $p) {
                if (!is_array($p)) continue;
                $message = (string) ($p['message'] ?? '');
                $image = self::safe_image((string) ($p['full_picture'] ?? ''));
                $link = self::safe_link((string) ($p['permalink_url'] ?? ''));
                if ($link === '' || ($message === '' && $image === '')) continue;
                $items[] = array(
                    'id' => (string) ($p['id'] ?? ''),
                    'message' => $message,
                    'image' => $image,
                    'link' => $link,
                    'date' => (string) ($p['created_time'] ?? ''),
                    'video' => ($p['attachments']['data'][0]['media_type'] ?? '') === 'video',
                );
            }
            $next = !empty($r['paging']['next']) ? (string) ($r['paging']['cursors']['after'] ?? '') : '';
            return array('items' => $items, 'next' => self::is_valid_cursor($next) ? $next : '');
        });
    }
}
