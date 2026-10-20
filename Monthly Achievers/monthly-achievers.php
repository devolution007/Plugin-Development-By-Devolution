<?php
/**
 * Plugin Name: Monthly Achievers
 * Description: Manage and showcase Students of the Month, Huffaz of the Month and Employees of the Month with simple shortcodes.
 * Version: 1.1.0
 * Author: Devolution
 * License: GPL-2.0-or-later
 * Text Domain: monthly-achievers
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('MA_VERSION', '1.1.0');
define('MA_FILE', __FILE__);
define('MA_DIR', plugin_dir_path(__FILE__));
define('MA_URL', plugin_dir_url(__FILE__));

require_once MA_DIR . 'includes/class-ma-groups.php';
require_once MA_DIR . 'includes/class-ma-settings.php';
require_once MA_DIR . 'includes/class-ma-post-types.php';
require_once MA_DIR . 'includes/class-ma-shortcodes.php';
require_once MA_DIR . 'includes/class-ma-tools.php';
require_once MA_DIR . 'includes/class-ma-block.php';

add_action('plugins_loaded', function () {
    load_plugin_textdomain('monthly-achievers', false, dirname(plugin_basename(MA_FILE)) . '/languages');
    MA_Settings::init();
    MA_Post_Types::init();
    MA_Shortcodes::init();
    MA_Tools::init();
    MA_Block::init();
});

register_activation_hook(__FILE__, function () {
    add_option('ma_campuses', array('Main Campus'));
    add_option('ma_sections', array('Section A', 'Section B'));
});
