<?php
/**
 * Plugin Name: MS SharePoint & OneDrive Gallery
 * Description: Connect WordPress to Microsoft 365 and embed OneDrive or SharePoint folders with shortcodes.
 * Version: 1.2.0
 * Author: Devolution
 * License: GPL-2.0-or-later
 * Text Domain: ms-sharepoint-onedrive-gallery
 */

if (!defined('ABSPATH')) exit;

define('MSOG_VERSION', '1.2.0');
define('MSOG_FILE', __FILE__);
define('MSOG_DIR', plugin_dir_path(__FILE__));
define('MSOG_URL', plugin_dir_url(__FILE__));

require_once MSOG_DIR . 'includes/class-msog-graph.php';
require_once MSOG_DIR . 'includes/class-msog-admin.php';
require_once MSOG_DIR . 'includes/class-msog-shortcode.php';

final class MSOG_Plugin {
    public static function init() {
        MSOG_Admin::init();
        MSOG_Shortcode::init();
    }
}
add_action('plugins_loaded', array('MSOG_Plugin', 'init'));

register_activation_hook(__FILE__, function () {
    add_option('msog_settings', array(
        'tenant' => 'common',
        'cache_minutes' => 10,
    ));
});
