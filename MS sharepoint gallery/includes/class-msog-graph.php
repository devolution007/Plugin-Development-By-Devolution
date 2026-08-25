<?php
if (!defined('ABSPATH')) exit;

class MSOG_Graph {
    const GRAPH = 'https://graph.microsoft.com/v1.0';

    public static function settings() {
        return wp_parse_args((array) get_option('msog_settings', array()), array(
            'client_id' => '', 'client_secret' => '', 'tenant' => 'common', 'cache_minutes' => 10,
        ));
    }

    public static function redirect_uri() {
        return admin_url('admin-post.php?action=msog_oauth_callback');
    }

    public static function authorize_url($state) {
        $s = self::settings();
        return add_query_arg(array(
            'client_id' => $s['client_id'], 'response_type' => 'code',
            'redirect_uri' => self::redirect_uri(), 'response_mode' => 'query',
            'scope' => 'openid profile offline_access User.Read Files.Read.All Sites.Read.All',
            'state' => $state, 'prompt' => 'select_account',
        ), 'https://login.microsoftonline.com/' . rawurlencode($s['tenant']) . '/oauth2/v2.0/authorize');
    }

    public static function exchange_code($code) {
        $s = self::settings();
        return self::token_request(array(
            'client_id' => $s['client_id'], 'client_secret' => $s['client_secret'],
            'code' => $code, 'redirect_uri' => self::redirect_uri(),
            'grant_type' => 'authorization_code',
            'scope' => 'openid profile offline_access User.Read Files.Read.All Sites.Read.All',
        ));
    }

    private static function token_request($body) {
        $s = self::settings();
        $res = wp_remote_post('https://login.microsoftonline.com/' . rawurlencode($s['tenant']) . '/oauth2/v2.0/token', array(
            'timeout' => 25, 'body' => $body,
        ));
        if (is_wp_error($res)) return $res;
        $json = json_decode(wp_remote_retrieve_body($res), true);
        if (wp_remote_retrieve_response_code($res) >= 300 || empty($json['access_token'])) {
            return new WP_Error('msog_token', isset($json['error_description']) ? sanitize_text_field($json['error_description']) : __('Microsoft sign-in failed.', 'cloud-gallery-connector-for-microsoft-365'));
        }
        return $json;
    }

    public static function save_tokens($tokens) {
        if (!empty($tokens['refresh_token'])) update_option('msog_refresh_token', self::encrypt($tokens['refresh_token']), false);
        set_transient('msog_access_token', $tokens['access_token'], max(60, ((int) ($tokens['expires_in'] ?? 3600)) - 120));
    }

    public static function disconnect() {
        delete_option('msog_refresh_token');
        delete_transient('msog_access_token');
    }

    public static function access_token() {
        $token = get_transient('msog_access_token');
        if ($token) return $token;
        $stored = get_option('msog_refresh_token');
        if (!$stored) return new WP_Error('msog_not_connected', __('Microsoft account is not connected.', 'cloud-gallery-connector-for-microsoft-365'));
        $refresh = self::decrypt($stored);
        if (!$refresh) return new WP_Error('msog_token_invalid', __('Stored Microsoft login is invalid. Reconnect the account.', 'cloud-gallery-connector-for-microsoft-365'));
        $s = self::settings();
        $tokens = self::token_request(array(
            'client_id' => $s['client_id'], 'client_secret' => $s['client_secret'],
            'refresh_token' => $refresh, 'grant_type' => 'refresh_token',
            'scope' => 'openid profile offline_access User.Read Files.Read.All Sites.Read.All',
        ));
        if (is_wp_error($tokens)) return $tokens;
        self::save_tokens($tokens);
        return $tokens['access_token'];
    }

    public static function get($path, $query = array(), $cache = true) {
        $key = 'msog_' . md5($path . wp_json_encode($query));
        if ($cache && ($hit = get_transient($key)) !== false) return $hit;
        $token = self::access_token();
        if (is_wp_error($token)) return $token;
        if (strpos($path, 'http') === 0) {
            $parts = wp_parse_url($path);
            if (empty($parts['host']) || strtolower($parts['host']) !== 'graph.microsoft.com' || strtolower($parts['scheme'] ?? '') !== 'https') {
                return new WP_Error('msog_invalid_graph_url', __('Microsoft Graph returned an invalid pagination URL.', 'cloud-gallery-connector-for-microsoft-365'));
            }
            $url = $path;
        } else {
            $url = self::GRAPH . '/' . ltrim($path, '/');
        }
        if ($query) $url = add_query_arg($query, $url);
        $res = wp_remote_get($url, array('timeout' => 25, 'headers' => array('Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json')));
        if (is_wp_error($res)) return $res;
        $json = json_decode(wp_remote_retrieve_body($res), true);
        if (wp_remote_retrieve_response_code($res) >= 300) {
            $message = $json['error']['message'] ?? __('Microsoft Graph request failed.', 'cloud-gallery-connector-for-microsoft-365');
            return new WP_Error('msog_graph', sanitize_text_field($message));
        }
        if ($cache) {
            $minutes = max(1, min(60, (int) self::settings()['cache_minutes']));
            set_transient($key, $json, $minutes * MINUTE_IN_SECONDS);
        }
        return $json;
    }

    public static function children($drive_id, $item_id = 'root') {
        // Microsoft Graph addresses the root as /root/children, not /items/root/children.
        $path = $item_id === 'root'
            ? 'drives/' . rawurlencode($drive_id) . '/root/children'
            : 'drives/' . rawurlencode($drive_id) . '/items/' . rawurlencode($item_id) . '/children';
        // Do not restrict $select: the default driveItem response can include the
        // short-lived @microsoft.graph.downloadUrl used for full-size lightboxes.
        $data = self::get($path, array('$expand' => 'thumbnails', '$top' => 200));
        if (is_wp_error($data)) return $data;
        $items = $data['value'] ?? array(); $next = $data['@odata.nextLink'] ?? '';
        // Follow Graph pagination so folders do not disappear in large libraries.
        for ($page = 0; $next && $page < 9; $page++) {
            $more = self::get($next);
            if (is_wp_error($more)) return $more;
            $items = array_merge($items, $more['value'] ?? array());
            $next = $more['@odata.nextLink'] ?? '';
        }
        $data['value'] = $items;
        return $data;
    }

    public static function encrypt($plain) {
        if (!function_exists('openssl_encrypt')) return base64_encode($plain);
        $key = hash('sha256', wp_salt('auth'), true); $iv = random_bytes(16);
        return base64_encode($iv . openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv));
    }
    public static function decrypt($encoded) {
        $raw = base64_decode($encoded, true);
        if ($raw === false) return false;
        if (!function_exists('openssl_decrypt')) return $raw;
        if (strlen($raw) < 17) return false;
        $key = hash('sha256', wp_salt('auth'), true);
        return openssl_decrypt(substr($raw, 16), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, substr($raw, 0, 16));
    }
}

