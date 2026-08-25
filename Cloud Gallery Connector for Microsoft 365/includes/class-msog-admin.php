<?php
if (!defined('ABSPATH')) exit;

class MSOG_Admin {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_init', array(__CLASS__, 'register'));
        add_action('admin_post_msog_connect', array(__CLASS__, 'connect'));
        add_action('admin_post_msog_oauth_callback', array(__CLASS__, 'callback'));
        add_action('admin_post_msog_disconnect', array(__CLASS__, 'disconnect'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
    }
    public static function menu() {
        add_menu_page(__('Cloud Gallery', 'cloud-gallery-connector-for-microsoft-365'), __('Cloud Gallery', 'cloud-gallery-connector-for-microsoft-365'), 'manage_options', 'msog', array(__CLASS__, 'page'), 'dashicons-images-alt2', 58);
    }
    public static function register() {
        register_setting('msog', 'msog_settings', array('sanitize_callback' => array(__CLASS__, 'sanitize')));
    }
    public static function sanitize($v) {
        $old = MSOG_Graph::settings();
        return array(
            'client_id' => sanitize_text_field($v['client_id'] ?? ''),
            'client_secret' => !empty($v['client_secret']) ? sanitize_text_field($v['client_secret']) : $old['client_secret'],
            'tenant' => sanitize_text_field($v['tenant'] ?? 'common') ?: 'common',
            'cache_minutes' => max(1, min(60, (int) ($v['cache_minutes'] ?? 10))),
        );
    }
    public static function assets($hook) {
        if ($hook !== 'toplevel_page_msog') return;
        wp_enqueue_style('msog-admin', MSOG_URL . 'assets/admin.css', array(), MSOG_VERSION);
        wp_enqueue_script('msog-admin', MSOG_URL . 'assets/admin.js', array(), MSOG_VERSION, true);
    }
    private static function guard() {
        if (!current_user_can('manage_options')) wp_die(esc_html__('Permission denied.', 'cloud-gallery-connector-for-microsoft-365'));
    }
    public static function connect() {
        self::guard(); check_admin_referer('msog_connect');
        $s = MSOG_Graph::settings();
        if (!$s['client_id'] || !$s['client_secret']) {
            set_transient('msog_admin_error_' . get_current_user_id(), __('Save the Client ID and Client Secret first.', 'cloud-gallery-connector-for-microsoft-365'), MINUTE_IN_SECONDS);
            wp_safe_redirect(admin_url('admin.php?page=msog'));
        }
        else {
            $state = wp_create_nonce('msog_oauth_' . get_current_user_id());
            set_transient('msog_oauth_' . get_current_user_id(), $state, 10 * MINUTE_IN_SECONDS);
            add_filter('allowed_redirect_hosts', array(__CLASS__, 'allow_microsoft_login_host'));
            wp_safe_redirect(MSOG_Graph::authorize_url($state));
            remove_filter('allowed_redirect_hosts', array(__CLASS__, 'allow_microsoft_login_host'));
        }
        exit;
    }
    public static function callback() {
        self::guard();
        $expected = get_transient('msog_oauth_' . get_current_user_id());
        // OAuth callbacks cannot contain a WordPress nonce; the one-time state
        // value below provides CSRF protection for this external callback.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $state = sanitize_text_field(wp_unslash($_GET['state'] ?? ''));
        if (!$expected || !wp_verify_nonce($state, 'msog_oauth_' . get_current_user_id()) || !hash_equals($expected, $state)) wp_die(esc_html__('Invalid or expired Microsoft sign-in request.', 'cloud-gallery-connector-for-microsoft-365'));
        delete_transient('msog_oauth_' . get_current_user_id());
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!empty($_GET['error'])) {
            set_transient('msog_admin_error_' . get_current_user_id(), __('Microsoft sign-in was cancelled or denied.', 'cloud-gallery-connector-for-microsoft-365'), MINUTE_IN_SECONDS);
            wp_safe_redirect(admin_url('admin.php?page=msog'));
        }
        else {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $tokens = MSOG_Graph::exchange_code(sanitize_text_field(wp_unslash($_GET['code'] ?? '')));
            if (is_wp_error($tokens)) set_transient('msog_admin_error_' . get_current_user_id(), $tokens->get_error_message(), MINUTE_IN_SECONDS);
            else MSOG_Graph::save_tokens($tokens);
            wp_safe_redirect(admin_url('admin.php?page=msog'));
        }
        exit;
    }
    public static function disconnect() {
        self::guard(); check_admin_referer('msog_disconnect'); MSOG_Graph::disconnect();
        wp_safe_redirect(admin_url('admin.php?page=msog')); exit;
    }
    public static function allow_microsoft_login_host($hosts) {
        $hosts[] = 'login.microsoftonline.com';
        return array_unique($hosts);
    }
    private static function notice() {
        $msg = get_transient('msog_admin_error_' . get_current_user_id());
        if ($msg) { delete_transient('msog_admin_error_' . get_current_user_id()); echo '<div class="notice notice-error"><p>' . esc_html($msg) . '</p></div>'; }
    }
    public static function page() {
        self::guard(); self::notice(); $s = MSOG_Graph::settings(); $connected = (bool) get_option('msog_refresh_token');
        echo '<div class="wrap msog-admin"><h1>' . esc_html__('Cloud Gallery Connector for Microsoft 365', 'cloud-gallery-connector-for-microsoft-365') . '</h1>';
        echo '<div class="msog-card"><h2>1. ' . esc_html__('Microsoft app settings', 'cloud-gallery-connector-for-microsoft-365') . '</h2>';
        echo '<p>' . wp_kses_post(__('Create an app registration in Microsoft Entra, add this exact Web redirect URI, and grant delegated permissions: <code>User.Read</code>, <code>Files.Read.All</code>, and <code>Sites.Read.All</code>.', 'cloud-gallery-connector-for-microsoft-365')) . '</p>';
        echo '<div class="msog-copy"><code>' . esc_html(MSOG_Graph::redirect_uri()) . '</code><button type="button" class="button msog-copy-btn">' . esc_html__('Copy', 'cloud-gallery-connector-for-microsoft-365') . '</button></div>';
        echo '<form method="post" action="options.php">'; settings_fields('msog');
        echo '<table class="form-table"><tr><th><label for="msog-client">Client ID</label></th><td><input class="regular-text" id="msog-client" name="msog_settings[client_id]" value="' . esc_attr($s['client_id']) . '" required></td></tr>';
        echo '<tr><th><label for="msog-secret">Client secret</label></th><td><input class="regular-text" type="password" id="msog-secret" name="msog_settings[client_secret]" value="" placeholder="' . esc_attr($s['client_secret'] ? __('Saved — leave blank to keep', 'cloud-gallery-connector-for-microsoft-365') : '') . '"></td></tr>';
        echo '<tr><th><label for="msog-tenant">Tenant</label></th><td><input class="regular-text" id="msog-tenant" name="msog_settings[tenant]" value="' . esc_attr($s['tenant']) . '"><p class="description">Use <code>common</code>, <code>organizations</code>, or your Microsoft tenant ID.</p></td></tr>';
        echo '<tr><th><label for="msog-cache">Cache (minutes)</label></th><td><input type="number" min="1" max="60" id="msog-cache" name="msog_settings[cache_minutes]" value="' . esc_attr($s['cache_minutes']) . '"></td></tr></table>'; submit_button(); echo '</form></div>';
        echo '<div class="msog-card"><h2>2. ' . esc_html__('Connect account', 'cloud-gallery-connector-for-microsoft-365') . '</h2><p><span class="msog-status ' . esc_attr($connected ? 'is-on' : '') . '"></span>' . esc_html($connected ? __('Connected', 'cloud-gallery-connector-for-microsoft-365') : __('Not connected', 'cloud-gallery-connector-for-microsoft-365')) . '</p>';
        if ($connected) { echo '<a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=msog_disconnect'), 'msog_disconnect')) . '">' . esc_html__('Disconnect', 'cloud-gallery-connector-for-microsoft-365') . '</a>'; }
        else { echo '<a class="button button-primary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=msog_connect'), 'msog_connect')) . '">' . esc_html__('Sign in with Microsoft', 'cloud-gallery-connector-for-microsoft-365') . '</a>'; }
        echo '</div>';
        if ($connected) self::browser();
        echo '</div>';
    }
    private static function browser() {
        // Only detect whether a protected browser request is present. Values are
        // read from $_GET only after check_admin_referer() succeeds.
        $has_request = null !== filter_input(INPUT_GET, 'drive') || null !== filter_input(INPUT_GET, 'folder') || null !== filter_input(INPUT_GET, 'site_url');
        if ($has_request) check_admin_referer('msog_browse_admin');
        $drive = sanitize_text_field(wp_unslash($_GET['drive'] ?? '')); $folder = sanitize_text_field(wp_unslash($_GET['folder'] ?? 'root'));
        $site_url = esc_url_raw(wp_unslash($_GET['site_url'] ?? ''));
        $site_drives = array();
        if ($site_url) {
            $parts = wp_parse_url($site_url); $host = $parts['host'] ?? ''; $path = trim($parts['path'] ?? '', '/');
            if ($host && $path) {
                $site = MSOG_Graph::get('sites/' . rawurlencode($host) . ':/' . str_replace('%2F', '/', rawurlencode($path)));
                if (!is_wp_error($site) && !empty($site['id'])) {
                    $libraries = MSOG_Graph::get('sites/' . rawurlencode($site['id']) . '/drives');
                    if (!is_wp_error($libraries)) $site_drives = $libraries['value'] ?? array();
                }
            }
        }
        if (!$drive) { $d = MSOG_Graph::get('me/drive'); if (!is_wp_error($d)) $drive = $d['id']; }
        echo '<div class="msog-card"><h2>3. ' . esc_html__('Choose a folder and copy its shortcode', 'cloud-gallery-connector-for-microsoft-365') . '</h2>';
        echo '<p class="description">' . esc_html__('This browser starts in your OneDrive. To use SharePoint, enter the URL of a site and select one of its document libraries.', 'cloud-gallery-connector-for-microsoft-365') . '</p>';
        echo '<form method="get"><input type="hidden" name="page" value="msog">'; wp_nonce_field('msog_browse_admin'); echo '<label><strong>SharePoint site URL</strong> <input class="regular-text" type="url" name="site_url" placeholder="https://company.sharepoint.com/sites/Marketing" value="' . esc_attr($site_url) . '"></label> <button class="button">' . esc_html__('Find libraries', 'cloud-gallery-connector-for-microsoft-365') . '</button></form>';
        if ($site_url && !$site_drives) echo '<p class="msog-error">' . esc_html__('No document libraries were found. Check the site URL and Microsoft permissions.', 'cloud-gallery-connector-for-microsoft-365') . '</p>';
        if ($site_drives) { echo '<p><strong>' . esc_html__('Document libraries', 'cloud-gallery-connector-for-microsoft-365') . ':</strong> ';
            foreach ($site_drives as $library) echo '<a class="button" href="' . esc_url(add_query_arg(array('page'=>'msog','drive'=>$library['id'],'_wpnonce'=>wp_create_nonce('msog_browse_admin')), admin_url('admin.php'))) . '">' . esc_html($library['name']) . '</a> ';
            echo '</p>'; }
        echo '<form method="get"><input type="hidden" name="page" value="msog">'; wp_nonce_field('msog_browse_admin'); echo '<label><strong>Drive ID</strong> <input class="regular-text" name="drive" value="' . esc_attr($drive) . '"></label> <button class="button">' . esc_html__('Open', 'cloud-gallery-connector-for-microsoft-365') . '</button></form>';
        if (!$drive) { echo '<p>' . esc_html__('Could not load a drive. Check permissions and reconnect.', 'cloud-gallery-connector-for-microsoft-365') . '</p></div>'; return; }
        $data = MSOG_Graph::children($drive, $folder);
        $shortcode = '[msog_gallery drive="' . $drive . '" folder="' . $folder . '" view="gallery" columns="4"]';
        echo '<div class="msog-copy"><code>' . esc_html($shortcode) . '</code><button type="button" class="button msog-copy-btn">' . esc_html__('Copy shortcode', 'cloud-gallery-connector-for-microsoft-365') . '</button></div>';
        if (is_wp_error($data)) echo '<p class="msog-error">' . esc_html($data->get_error_message()) . '</p>';
        else { echo '<table class="widefat striped"><thead><tr><th>Name</th><th>Type</th><th>Shortcode</th></tr></thead><tbody>';
            foreach (($data['value'] ?? array()) as $item) { $is_folder = isset($item['folder']); echo '<tr><td>';
                if ($is_folder) echo '<a href="' . esc_url(add_query_arg(array('page'=>'msog','drive'=>$drive,'folder'=>$item['id'],'_wpnonce'=>wp_create_nonce('msog_browse_admin')), admin_url('admin.php'))) . '"><span class="dashicons dashicons-portfolio"></span> ' . esc_html($item['name']) . '</a>';
                else echo esc_html($item['name']); echo '</td><td>' . esc_html($is_folder ? 'Folder' : ($item['file']['mimeType'] ?? 'File')) . '</td><td>';
                if ($is_folder) { $sc = '[msog_gallery drive="' . $drive . '" folder="' . sanitize_text_field($item['id']) . '" view="gallery" columns="4"]'; echo '<code>' . esc_html($sc) . '</code> <button type="button" class="button button-small msog-copy-btn">' . esc_html__('Copy', 'cloud-gallery-connector-for-microsoft-365') . '</button>'; }
                echo '</td></tr>'; }
            echo '</tbody></table>'; }
        echo '<p><strong>Folder browser:</strong> <code>[msog_gallery drive=&quot;' . esc_html($drive) . '&quot; folder=&quot;' . esc_html($folder) . '&quot; view=&quot;folders&quot; columns=&quot;4&quot;]</code></p>';
        echo '<p><strong>List layout:</strong> <code>[msog_gallery drive=&quot;' . esc_html($drive) . '&quot; folder=&quot;' . esc_html($folder) . '&quot; view=&quot;list&quot;]</code></p></div>';
    }
}

