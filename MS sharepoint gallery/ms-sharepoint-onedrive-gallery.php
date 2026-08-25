<?php
/**
 * Plugin Name: Cloud Gallery Connector for Microsoft 365
 * Description: Connect WordPress to Microsoft 365 and embed OneDrive or SharePoint folders with shortcodes.
 * Version: 1.3.0
 * Author: Devolution
 * License: GPL-2.0-or-later
 * Text Domain: ms-sharepoint-onedrive-gallery
 */

if (!defined('ABSPATH')) exit;

define('MSOG_VERSION', '1.3.0');
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
        add_action('admin_init', array(__CLASS__, 'privacy_policy'));
    }
    public static function privacy_policy() {
        if (!function_exists('wp_add_privacy_policy_content')) return;
        $content = '<p>' . esc_html__('This plugin connects to Microsoft Graph only after a site administrator configures and authorizes a Microsoft application. It sends the connected account authorization token and requested drive, site, folder, and file identifiers to Microsoft in order to retrieve names, metadata, thumbnails, and temporary download URLs. Gallery visitors may load images and thumbnails directly from Microsoft-hosted URLs. Configure only content intended for the audience of the WordPress page.', 'ms-sharepoint-onedrive-gallery') . '</p>';
        $content .= '<p>' . wp_kses_post(__('See the <a href="https://privacy.microsoft.com/privacystatement">Microsoft Privacy Statement</a> and <a href="https://www.microsoft.com/servicesagreement">Microsoft Services Agreement</a>.', 'ms-sharepoint-onedrive-gallery')) . '</p>';
        wp_add_privacy_policy_content(__('Cloud Gallery Connector for Microsoft 365', 'ms-sharepoint-onedrive-gallery'), $content);
    }
}
add_action('plugins_loaded', array('MSOG_Plugin', 'init'));

register_activation_hook(__FILE__, function () {
    add_option('msog_settings', array(
        'tenant' => 'common',
        'cache_minutes' => 10,
    ));
});
