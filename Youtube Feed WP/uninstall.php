<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
delete_option('dytf_settings');
delete_option('dytf_cache_version');
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_dytf\_%' OR option_name LIKE '\_transient\_timeout\_dytf\_%'");
