<?php
/**
 * Plugin Name: Youtube Feed WP
 * Description: Display a YouTube channel or playlist feed on your site with a simple shortcode: [devo_youtube_feed].
 * Version: 1.0.0
 * Author: Devolution
 * License: GPL-2.0-or-later
 * Text Domain: youtube-feed-wp
 */

if (!defined('ABSPATH')) exit;

define('DYTF_VERSION', '1.0.0');
define('DYTF_FILE', __FILE__);
define('DYTF_DIR', plugin_dir_path(__FILE__));
define('DYTF_URL', plugin_dir_url(__FILE__));

require_once DYTF_DIR . 'includes/class-dytf-api.php';
require_once DYTF_DIR . 'includes/class-dytf-admin.php';
require_once DYTF_DIR . 'includes/class-dytf-shortcode.php';

add_action('plugins_loaded', function () {
    DYTF_Admin::init();
    DYTF_Shortcode::init();
});

register_activation_hook(__FILE__, function () {
    add_option('dytf_settings', array(
        'api_key' => '',
        'channel' => '',
        'cache_minutes' => 60,
    ));
});
