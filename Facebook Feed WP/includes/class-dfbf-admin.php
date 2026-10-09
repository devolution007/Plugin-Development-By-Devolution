<?php
if (!defined('ABSPATH')) exit;

class DFBF_Admin {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_init', array(__CLASS__, 'register'));
        add_action('admin_post_dfbf_clear_cache', array(__CLASS__, 'clear_cache'));
        add_action('admin_post_dfbf_connect', array(__CLASS__, 'connect'));
        add_action('admin_post_dfbf_oauth', array(__CLASS__, 'oauth_callback'));
        add_action('admin_post_dfbf_disconnect', array(__CLASS__, 'disconnect'));
        add_filter('plugin_action_links_' . plugin_basename(DFBF_FILE), function ($links) {
            array_unshift($links, '<a href="' . esc_url(self::url()) . '">' . esc_html__('Settings', 'facebook-feed-wp') . '</a>');
            return $links;
        });
    }

    private static function url(array $args = array()) {
        return add_query_arg($args, admin_url('options-general.php?page=dfbf'));
    }

    private static function guard() {
        if (!current_user_can('manage_options')) wp_die(esc_html__('Not allowed.', 'facebook-feed-wp'));
    }

    public static function menu() {
        add_options_page(__('Facebook Feed', 'facebook-feed-wp'), __('Facebook Feed', 'facebook-feed-wp'), 'manage_options', 'dfbf', array(__CLASS__, 'page'));
    }

    public static function register() {
        register_setting('dfbf', 'dfbf_settings', array('sanitize_callback' => array(__CLASS__, 'sanitize')));
    }

    public static function sanitize($in) {
        $old = DFBF_API::settings();
        DFBF_API::bump_cache();
        $page = isset($in['page']) ? sanitize_text_field($in['page']) : $old['page'];
        return array(
            'app_id' => isset($in['app_id']) ? preg_replace('/\D/', '', $in['app_id']) : $old['app_id'],
            'app_secret' => isset($in['app_secret']) ? trim(sanitize_text_field($in['app_secret'])) : $old['app_secret'],
            'page' => $page,
            'cache_minutes' => max(1, min(1440, (int) ($in['cache_minutes'] ?? $old['cache_minutes']))),
        );
    }

    public static function clear_cache() {
        self::guard();
        check_admin_referer('dfbf_clear_cache');
        DFBF_API::bump_cache();
        wp_safe_redirect(self::url(array('cleared' => 1)));
        exit;
    }

    /** Step 1: send the admin to Facebook's login dialog. */
    public static function connect() {
        self::guard();
        check_admin_referer('dfbf_connect');
        $s = DFBF_API::settings();
        if ($s['app_id'] === '' || $s['app_secret'] === '') {
            wp_safe_redirect(self::url(array('dfbf_error' => rawurlencode(__('Save your App ID and App Secret first.', 'facebook-feed-wp')))));
            exit;
        }
        // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Facebook login dialog.
        wp_redirect(DFBF_API::login_url(wp_create_nonce('dfbf_oauth')));
        exit;
    }

    /** Step 2: Facebook redirects back here with ?code=... */
    public static function oauth_callback() {
        self::guard();
        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        if (!wp_verify_nonce($state, 'dfbf_oauth')) wp_die(esc_html__('Invalid or expired connection request. Please try again.', 'facebook-feed-wp'));

        if (isset($_GET['error']) || empty($_GET['code'])) {
            $msg = isset($_GET['error_description']) ? sanitize_text_field(wp_unslash($_GET['error_description'])) : __('Facebook login was cancelled.', 'facebook-feed-wp');
            wp_safe_redirect(self::url(array('dfbf_error' => rawurlencode($msg))));
            exit;
        }
        $result = DFBF_API::connect(sanitize_text_field(wp_unslash($_GET['code'])));
        if (is_wp_error($result)) {
            wp_safe_redirect(self::url(array('dfbf_error' => rawurlencode($result->get_error_message()))));
        } else {
            wp_safe_redirect(self::url(array('connected' => 1)));
        }
        exit;
    }

    public static function disconnect() {
        self::guard();
        check_admin_referer('dfbf_disconnect');
        DFBF_API::disconnect();
        wp_safe_redirect(self::url(array('disconnected' => 1)));
        exit;
    }

    public static function page() {
        self::guard();
        $s = DFBF_API::settings();
        $conn = DFBF_API::connection();
        $has_app = $s['app_id'] !== '' && $s['app_secret'] !== '';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Facebook Feed', 'facebook-feed-wp'); ?></h1>
            <?php if (isset($_GET['cleared'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Cache cleared.', 'facebook-feed-wp'); ?></p></div>
            <?php endif; ?>
            <?php if (isset($_GET['connected'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Connected to Facebook.', 'facebook-feed-wp'); ?></p></div>
            <?php endif; ?>
            <?php if (isset($_GET['disconnected'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Disconnected from Facebook.', 'facebook-feed-wp'); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($_GET['dfbf_error'])) : ?>
                <div class="notice notice-error"><p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['dfbf_error']))); ?></p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('dfbf'); ?>
                <h2><?php esc_html_e('1. Facebook app', 'facebook-feed-wp'); ?></h2>
                <p><?php
                echo wp_kses_post(__('Create an app at <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener">Meta for Developers</a> and add the <em>Facebook Login</em> product. Under Facebook Login → Settings, add the redirect URI below to <em>Valid OAuth Redirect URIs</em>.', 'facebook-feed-wp')); ?></p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Redirect URI', 'facebook-feed-wp'); ?></th>
                        <td><input type="text" readonly value="<?php echo esc_attr(DFBF_API::redirect_uri()); ?>" class="large-text code" onfocus="this.select()"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dfbf_app_id"><?php esc_html_e('App ID', 'facebook-feed-wp'); ?></label></th>
                        <td><input type="text" id="dfbf_app_id" name="dfbf_settings[app_id]" value="<?php echo esc_attr($s['app_id']); ?>" class="regular-text" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dfbf_app_secret"><?php esc_html_e('App Secret', 'facebook-feed-wp'); ?></label></th>
                        <td><input type="password" id="dfbf_app_secret" name="dfbf_settings[app_secret]" value="<?php echo esc_attr($s['app_secret']); ?>" class="regular-text" autocomplete="off"></td>
                    </tr>
                    <?php if ($conn['pages']) : ?>
                    <tr>
                        <th scope="row"><label for="dfbf_page"><?php esc_html_e('Default Page', 'facebook-feed-wp'); ?></label></th>
                        <td>
                            <select id="dfbf_page" name="dfbf_settings[page]">
                                <?php foreach ($conn['pages'] as $id => $p) : ?>
                                    <option value="<?php echo esc_attr($id); ?>" <?php selected((string) $s['page'], (string) $id); ?>><?php echo esc_html($p['name'] . ' (' . $id . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Used when the shortcode has no page attribute.', 'facebook-feed-wp'); ?></p>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th scope="row"><label for="dfbf_cache"><?php esc_html_e('Cache (minutes)', 'facebook-feed-wp'); ?></label></th>
                        <td>
                            <input type="number" id="dfbf_cache" name="dfbf_settings[cache_minutes]" value="<?php echo esc_attr($s['cache_minutes']); ?>" min="1" max="1440" class="small-text">
                            <p class="description"><?php esc_html_e('Caching keeps you within Facebook rate limits and makes the feed load faster.', 'facebook-feed-wp'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <h2><?php esc_html_e('2. Connect your Page', 'facebook-feed-wp'); ?></h2>
            <?php if ($conn['pages']) : ?>
                <p>
                    <?php
                    /* translators: 1: Facebook user name, 2: number of Pages */
                    echo esc_html(sprintf(_n('Connected as %1$s — %2$d Page available.', 'Connected as %1$s — %2$d Pages available.', count($conn['pages']), 'facebook-feed-wp'), $conn['user'], count($conn['pages'])));
                    ?>
                </p>
            <?php elseif (!$has_app) : ?>
                <p><?php esc_html_e('Save your App ID and App Secret above, then connect.', 'facebook-feed-wp'); ?></p>
            <?php endif; ?>
            <p>
                <?php if ($has_app) : ?>
                    <a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=dfbf_connect'), 'dfbf_connect')); ?>">
                        <?php echo esc_html($conn['pages'] ? __('Reconnect with Facebook', 'facebook-feed-wp') : __('Connect with Facebook', 'facebook-feed-wp')); ?>
                    </a>
                <?php endif; ?>
                <?php if ($conn['pages']) : ?>
                    <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=dfbf_disconnect'), 'dfbf_disconnect')); ?>"><?php esc_html_e('Disconnect', 'facebook-feed-wp'); ?></a>
                <?php endif; ?>
            </p>
            <p class="description"><?php esc_html_e('Log in with a Facebook account that manages the Page, and approve the Page(s) you want to show. Page tokens obtained this way do not expire.', 'facebook-feed-wp'); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="dfbf_clear_cache">
                <?php wp_nonce_field('dfbf_clear_cache'); submit_button(__('Clear cache', 'facebook-feed-wp'), 'secondary'); ?>
            </form>

            <hr>
            <h2><?php esc_html_e('Usage', 'facebook-feed-wp'); ?></h2>
            <p><code>[devo_facebook_feed]</code> — <?php esc_html_e('uses the default Page above.', 'facebook-feed-wp'); ?></p>
            <p><code>[devo_facebook_feed page="123456789" limit="6" columns="3"]</code></p>
            <p><code>[devo_facebook_feed layout="list" open="facebook" excerpt="20"]</code></p>
            <table class="widefat striped" style="max-width:760px">
                <thead><tr><th><?php esc_html_e('Attribute', 'facebook-feed-wp'); ?></th><th><?php esc_html_e('Values (default first)', 'facebook-feed-wp'); ?></th></tr></thead>
                <tbody>
                    <tr><td>page</td><td><?php esc_html_e('ID or name of a connected Page (default: settings)', 'facebook-feed-wp'); ?></td></tr>
                    <tr><td>limit</td><td>9 (1–50) — <?php esc_html_e('posts per page', 'facebook-feed-wp'); ?></td></tr>
                    <tr><td>columns</td><td>3 (1–6)</td></tr>
                    <tr><td>layout</td><td>grid | list</td></tr>
                    <tr><td>open</td><td>lightbox | facebook — <?php esc_html_e('popup viewer or open the post on Facebook (video posts always open on Facebook)', 'facebook-feed-wp'); ?></td></tr>
                    <tr><td>excerpt</td><td>30 (0–200) — <?php esc_html_e('words of post text shown on the card; 0 hides it', 'facebook-feed-wp'); ?></td></tr>
                    <tr><td>show_date</td><td>yes | no</td></tr>
                    <tr><td>load_more</td><td>yes | no</td></tr>
                </tbody>
            </table>
        </div>
        <?php
    }
}
