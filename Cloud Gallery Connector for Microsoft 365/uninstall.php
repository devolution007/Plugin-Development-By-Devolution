<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
delete_option('msog_settings');
delete_option('msog_refresh_token');
