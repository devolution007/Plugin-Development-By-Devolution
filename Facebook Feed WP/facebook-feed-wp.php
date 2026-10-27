<?php
/**
 * Plugin Name: Facebook Feed WP
 * Description: Display a Facebook Page feed on your site with a simple shortcode: [devo_facebook_feed].
 * Version: 1.1.0
 * Requires PHP: 8.3
 * Requires at least: 6.6
 * Author: Devolution
 * License: GPL-2.0-or-later
 * Text Domain: facebook-feed-wp
 */

declare(strict_types=1);

if (!defined('ABSPATH')) exit;

// Keep this guard free of PHP 8.3-only syntax so older versions can parse it and show a notice
// instead of a fatal error (the "Requires PHP" header normally blocks activation first).
if (PHP_VERSION_ID < 80300) {
    add_action('admin_notices', function () {
        if (!current_user_can('activate_plugins')) return;
        echo '<div class="notice notice-error"><p>' . esc_html(sprintf(
            /* translators: %s: current PHP version */
            __('Facebook Feed WP requires PHP 8.3 or newer. Your server runs PHP %s. Ask your host to upgrade PHP, then reactivate the plugin.', 'facebook-feed-wp'),
            PHP_VERSION
        )) . '</p></div>';
    });
    return;
}

define('DFBF_VERSION', '1.1.0');
define('DFBF_FILE', __FILE__);
define('DFBF_DIR', plugin_dir_path(__FILE__));
define('DFBF_URL', plugin_dir_url(__FILE__));

require_once DFBF_DIR . 'includes/class-dfbf-api.php';
require_once DFBF_DIR . 'includes/class-dfbf-admin.php';
require_once DFBF_DIR . 'includes/class-dfbf-shortcode.php';

add_action('plugins_loaded', static function (): void {
    DFBF_Admin::init();
    DFBF_Shortcode::init();
});

register_activation_hook(__FILE__, static function (): void {
    add_option('dfbf_settings', array(
        'app_id' => '',
        'app_secret' => '',
        'page' => '',
        'cache_minutes' => 60,
    ), '', false); // autoload off: the app secret is not loaded on every request
});
