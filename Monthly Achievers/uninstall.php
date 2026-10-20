<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

// Settings only. Achiever records are kept so data is never lost by accident.
delete_option('ma_campuses');
delete_option('ma_sections');
delete_option('ma_design');
