<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
delete_option('dfbf_settings');
delete_option('dfbf_connection');
delete_option('dfbf_cache_version');
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_dfbf\_%' OR option_name LIKE '\_transient\_timeout\_dfbf\_%'");
