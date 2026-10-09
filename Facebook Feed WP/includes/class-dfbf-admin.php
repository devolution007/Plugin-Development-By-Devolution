<?php
if (!defined('ABSPATH')) exit;

class DFBF_Admin {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_init', array(__CLASS__, 'register'));
        add_action('admin_post_dfbf_clear_cache', array(__CLASS__, 'clear_cache'));
        add_filter('plugin_action_links_' . plugin_basename(DFBF_FILE), function ($links) {
            array_unshift($links, '<a href="' . esc_url(admin_url('options-general.php?page=dfbf')) . '">' . esc_html__('Settings', 'facebook-feed-wp') . '</a>');
            return $links;
        });
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
        return array(
            'access_token' => isset($in['access_token']) ? trim(sanitize_text_field($in['access_token'])) : '',
            'page' => isset($in['page']) ? sanitize_text_field($in['page']) : '',
            'cache_minutes' => max(1, min(1440, (int) ($in['cache_minutes'] ?? $old['cache_minutes']))),
        );
    }

    public static function clear_cache() {
        if (!current_user_can('manage_options')) wp_die(esc_html__('Not allowed.', 'facebook-feed-wp'));
        check_admin_referer('dfbf_clear_cache');
        DFBF_API::bump_cache();
        wp_safe_redirect(admin_url('options-general.php?page=dfbf&cleared=1'));
        exit;
    }

    public static function page() {
        if (!current_user_can('manage_options')) return;
        $s = DFBF_API::settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Facebook Feed', 'facebook-feed-wp'); ?></h1>
            <?php if (isset($_GET['cleared'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Cache cleared.', 'facebook-feed-wp'); ?></p></div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('dfbf'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="dfbf_token"><?php esc_html_e('Page access token', 'facebook-feed-wp'); ?></label></th>
                        <td>
                            <input type="password" id="dfbf_token" name="dfbf_settings[access_token]" value="<?php echo esc_attr($s['access_token']); ?>" class="regular-text" autocomplete="off">
                            <p class="description"><?php
                            echo wp_kses_post(__('Create an app at <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener">Meta for Developers</a>, then generate a long-lived <em>Page access token</em> for a Page you manage in the <a href="https://developers.facebook.com/tools/explorer/" target="_blank" rel="noopener">Graph API Explorer</a> (permissions: <code>pages_read_engagement</code>, <code>pages_show_list</code>). Long-lived Page tokens generated from a long-lived user token do not expire.', 'facebook-feed-wp')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dfbf_page"><?php esc_html_e('Default Page', 'facebook-feed-wp'); ?></label></th>
                        <td>
                            <input type="text" id="dfbf_page" name="dfbf_settings[page]" value="<?php echo esc_attr($s['page']); ?>" class="regular-text" placeholder="<?php esc_attr_e('Page ID, username, or URL', 'facebook-feed-wp'); ?>">
                            <p class="description"><?php esc_html_e('Used when the shortcode has no page attribute. The token must belong to this Page.', 'facebook-feed-wp'); ?></p>
                        </td>
                    </tr>
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
                    <tr><td>page</td><td><?php esc_html_e('Page ID, username, or facebook.com URL (default: settings)', 'facebook-feed-wp'); ?></td></tr>
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
