<?php
/**
 * Plugin Name: Facebook Feed WP
 * Description: Display a Facebook Page feed on your site with a simple shortcode: [devo_facebook_feed].
 * Version: 1.0.0
 * Author: Devolution
 * License: GPL-2.0-or-later
 * Text Domain: facebook-feed-wp
 */

if (!defined('ABSPATH')) exit;

define('DFBF_VERSION', '1.0.0');
define('DFBF_FILE', __FILE__);
define('DFBF_DIR', plugin_dir_path(__FILE__));
define('DFBF_URL', plugin_dir_url(__FILE__));

require_once DFBF_DIR . 'includes/class-dfbf-api.php';
require_once DFBF_DIR . 'includes/class-dfbf-admin.php';
require_once DFBF_DIR . 'includes/class-dfbf-shortcode.php';

add_action('plugins_loaded', function () {
    DFBF_Admin::init();
    DFBF_Shortcode::init();
});

register_activation_hook(__FILE__, function () {
    add_option('dfbf_settings', array(
        'app_id' => '',
        'app_secret' => '',
        'page' => '',
        'cache_minutes' => 60,
    ));
});
