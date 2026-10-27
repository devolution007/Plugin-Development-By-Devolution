<?php
/**
 * Plugin Name: Youtube Feed WP
 * Description: Display a YouTube channel or playlist feed on your site with a simple shortcode: [devo_youtube_feed].
 * Version: 1.1.0
 * Requires PHP: 8.3
 * Requires at least: 6.6
 * Author: Devolution
 * License: GPL-2.0-or-later
 * Text Domain: youtube-feed-wp
 */

declare(strict_types=1);

if (!defined('ABSPATH')) exit;

// Keep this guard free of PHP 8.3-only syntax so older versions can parse it and show a notice
// instead of a fatal error (the "Requires PHP" header normally blocks activation first).
if (PHP_VERSION_ID < 80300) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>' . esc_html__('Youtube Feed WP requires PHP 8.3 or newer and has been disabled.', 'youtube-feed-wp') . '</p></div>';
    });
    return;
}

define('DYTF_VERSION', '1.1.0');
define('DYTF_FILE', __FILE__);
define('DYTF_DIR', plugin_dir_path(__FILE__));
define('DYTF_URL', plugin_dir_url(__FILE__));

require_once DYTF_DIR . 'includes/class-dytf-api.php';
require_once DYTF_DIR . 'includes/class-dytf-admin.php';
require_once DYTF_DIR . 'includes/class-dytf-shortcode.php';

add_action('plugins_loaded', static function (): void {
    DYTF_Admin::init();
    DYTF_Shortcode::init();
});

register_activation_hook(__FILE__, static function (): void {
    add_option('dytf_settings', array(
        'api_key' => '',
        'channel' => '',
        'cache_minutes' => 60,
    ), '', false); // autoload off: the API key is not loaded on every request
});
