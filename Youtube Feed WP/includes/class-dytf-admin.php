<?php
if (!defined('ABSPATH')) exit;

class DYTF_Admin {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_init', array(__CLASS__, 'register'));
        add_action('admin_post_dytf_clear_cache', array(__CLASS__, 'clear_cache'));
        add_filter('plugin_action_links_' . plugin_basename(DYTF_FILE), function ($links) {
            array_unshift($links, '<a href="' . esc_url(admin_url('options-general.php?page=dytf')) . '">' . esc_html__('Settings', 'youtube-feed-wp') . '</a>');
            return $links;
        });
    }

    public static function menu() {
        add_options_page(__('YouTube Feed', 'youtube-feed-wp'), __('YouTube Feed', 'youtube-feed-wp'), 'manage_options', 'dytf', array(__CLASS__, 'page'));
    }

    public static function register() {
        register_setting('dytf', 'dytf_settings', array('sanitize_callback' => array(__CLASS__, 'sanitize')));
    }

    public static function sanitize($in) {
        $old = DYTF_API::settings();
        DYTF_API::bump_cache();
        return array(
            'api_key' => isset($in['api_key']) ? trim(sanitize_text_field($in['api_key'])) : '',
            'channel' => isset($in['channel']) ? sanitize_text_field($in['channel']) : '',
            'cache_minutes' => max(1, min(1440, (int) ($in['cache_minutes'] ?? $old['cache_minutes']))),
        );
    }

    public static function clear_cache() {
        if (!current_user_can('manage_options')) wp_die(esc_html__('Not allowed.', 'youtube-feed-wp'));
        check_admin_referer('dytf_clear_cache');
        DYTF_API::bump_cache();
        wp_safe_redirect(admin_url('options-general.php?page=dytf&cleared=1'));
        exit;
    }

    public static function page() {
        if (!current_user_can('manage_options')) return;
        $s = DYTF_API::settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('YouTube Feed', 'youtube-feed-wp'); ?></h1>
            <?php if (isset($_GET['cleared'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Cache cleared.', 'youtube-feed-wp'); ?></p></div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('dytf'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="dytf_api_key"><?php esc_html_e('YouTube API key', 'youtube-feed-wp'); ?></label></th>
                        <td>
                            <input type="password" id="dytf_api_key" name="dytf_settings[api_key]" value="<?php echo esc_attr($s['api_key']); ?>" class="regular-text" autocomplete="off">
                            <p class="description"><?php
                            echo wp_kses_post(__('Create a key in <a href="https://console.cloud.google.com/apis/library/youtube.googleapis.com" target="_blank" rel="noopener">Google Cloud Console</a>: enable <em>YouTube Data API v3</em>, then create an API key under Credentials.', 'youtube-feed-wp')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dytf_channel"><?php esc_html_e('Default channel', 'youtube-feed-wp'); ?></label></th>
                        <td>
                            <input type="text" id="dytf_channel" name="dytf_settings[channel]" value="<?php echo esc_attr($s['channel']); ?>" class="regular-text" placeholder="@handle or UC…">
                            <p class="description"><?php esc_html_e('Channel handle (@name) or channel ID. Used when the shortcode has no channel or playlist.', 'youtube-feed-wp'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dytf_cache"><?php esc_html_e('Cache (minutes)', 'youtube-feed-wp'); ?></label></th>
                        <td>
                            <input type="number" id="dytf_cache" name="dytf_settings[cache_minutes]" value="<?php echo esc_attr($s['cache_minutes']); ?>" min="1" max="1440" class="small-text">
                            <p class="description"><?php esc_html_e('Caching keeps you within the free API quota and makes the feed load faster.', 'youtube-feed-wp'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="dytf_clear_cache">
                <?php wp_nonce_field('dytf_clear_cache'); submit_button(__('Clear cache', 'youtube-feed-wp'), 'secondary'); ?>
            </form>

            <hr>
            <h2><?php esc_html_e('Usage', 'youtube-feed-wp'); ?></h2>
            <p><code>[devo_youtube_feed]</code> — <?php esc_html_e('uses the default channel above.', 'youtube-feed-wp'); ?></p>
            <p><code>[devo_youtube_feed channel="@GoogleDevelopers" limit="6" columns="3"]</code></p>
            <p><code>[devo_youtube_feed playlist="PLxxxxxxxx" layout="list" play="youtube"]</code></p>
            <table class="widefat striped" style="max-width:760px">
                <thead><tr><th><?php esc_html_e('Attribute', 'youtube-feed-wp'); ?></th><th><?php esc_html_e('Values (default first)', 'youtube-feed-wp'); ?></th></tr></thead>
                <tbody>
                    <tr><td>channel</td><td><?php esc_html_e('@handle, channel ID, or channel URL (default: settings)', 'youtube-feed-wp'); ?></td></tr>
                    <tr><td>playlist</td><td><?php esc_html_e('Playlist ID or URL (overrides channel)', 'youtube-feed-wp'); ?></td></tr>
                    <tr><td>limit</td><td>9 (1–50) — <?php esc_html_e('videos per page', 'youtube-feed-wp'); ?></td></tr>
                    <tr><td>columns</td><td>3 (1–6)</td></tr>
                    <tr><td>layout</td><td>grid | list</td></tr>
                    <tr><td>play</td><td>lightbox | youtube — <?php esc_html_e('popup player or open on YouTube', 'youtube-feed-wp'); ?></td></tr>
                    <tr><td>show_title</td><td>yes | no</td></tr>
                    <tr><td>show_date</td><td>yes | no</td></tr>
                    <tr><td>load_more</td><td>yes | no</td></tr>
                </tbody>
            </table>
        </div>
        <?php
    }
}
