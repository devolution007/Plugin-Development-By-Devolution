<?php
if (!defined('ABSPATH')) exit;

/**
 * Gutenberg block "Monthly Achievers" (server-side rendered, no build step).
 */
class MA_Block {

    public static function init() {
        add_action('init', array(__CLASS__, 'register'), 20);
    }

    public static function register() {
        if (!function_exists('register_block_type')) return;

        $path = 'assets/js/block.js';
        $ver  = MA_VERSION . '.' . (@filemtime(MA_DIR . $path) ?: 0);
        wp_register_script(
            'monthly-achievers-block',
            MA_URL . $path,
            array('wp-blocks', 'wp-element', 'wp-i18n', 'wp-block-editor', 'wp-components', 'wp-server-side-render'),
            $ver,
            true
        );
        wp_localize_script('monthly-achievers-block', 'MA_Block', array(
            'campuses' => MA_Groups::campuses(),
            'sections' => MA_Groups::sections(),
            'groups'   => array_map(function ($g) { return $g['menu_name']; }, MA_Groups::all()),
        ));

        register_block_type('monthly-achievers/achievers', array(
            'api_version'     => 2,
            'title'           => __('Monthly Achievers', 'monthly-achievers'),
            'editor_script'   => 'monthly-achievers-block',
            'style'           => 'monthly-achievers',
            'render_callback' => array(__CLASS__, 'render'),
            'attributes'      => array(
                'groups'  => array('type' => 'array', 'items' => array('type' => 'string'), 'default' => array('students', 'huffaz', 'employees')),
                'month'   => array('type' => 'string', 'default' => 'latest'),
                'campus'  => array('type' => 'string', 'default' => ''),
                'section' => array('type' => 'string', 'default' => ''),
                'perPage' => array('type' => 'number', 'default' => 3),
            ),
        ));
    }

    public static function render($attrs) {
        $groups = isset($attrs['groups']) && is_array($attrs['groups']) ? implode(',', array_map('sanitize_key', $attrs['groups'])) : 'students,huffaz,employees';
        return MA_Shortcodes::render_tabs(array(
            'groups'   => $groups,
            'month'    => isset($attrs['month']) ? $attrs['month'] : 'latest',
            'campus'   => isset($attrs['campus']) ? $attrs['campus'] : '',
            'section'  => isset($attrs['section']) ? $attrs['section'] : '',
            'per_page' => isset($attrs['perPage']) ? (int) $attrs['perPage'] : 3,
        ));
    }
}
