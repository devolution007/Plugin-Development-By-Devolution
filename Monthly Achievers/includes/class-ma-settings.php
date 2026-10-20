<?php
if (!defined('ABSPATH')) exit;

/**
 * Settings > Monthly Achievers: manage the Campus and Section dropdown values.
 */
class MA_Settings {

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_init', array(__CLASS__, 'register'));
        add_filter('plugin_action_links_' . plugin_basename(MA_FILE), array(__CLASS__, 'action_links'));
    }

    public static function menu() {
        add_options_page(
            __('Monthly Achievers', 'monthly-achievers'),
            __('Monthly Achievers', 'monthly-achievers'),
            'manage_options',
            'monthly-achievers',
            array(__CLASS__, 'render')
        );
    }

    public static function action_links($links) {
        array_unshift($links, '<a href="' . esc_url(admin_url('options-general.php?page=monthly-achievers')) . '">' . esc_html__('Settings', 'monthly-achievers') . '</a>');
        return $links;
    }

    public static function register() {
        foreach (array('ma_campuses', 'ma_sections') as $option) {
            register_setting('ma_settings', $option, array(
                'type'              => 'array',
                'sanitize_callback' => array(__CLASS__, 'sanitize_list'),
                'default'           => array(),
            ));
        }
    }

    /** Accepts a textarea string (one value per line) and returns a clean array. */
    public static function sanitize_list($value) {
        if (!is_array($value)) {
            $value = preg_split('/\r\n|\r|\n/', (string) $value);
        }
        $clean = array();
        foreach ($value as $item) {
            $item = sanitize_text_field($item);
            if ($item !== '') $clean[$item] = $item;
        }
        return array_values($clean);
    }

    public static function render() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Monthly Achievers', 'monthly-achievers'); ?></h1>
            <p><?php esc_html_e('Manage the values shown in the Campus and Section dropdowns. Enter one value per line.', 'monthly-achievers'); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields('ma_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="ma_campuses"><?php esc_html_e('Campuses', 'monthly-achievers'); ?></label></th>
                        <td><textarea id="ma_campuses" name="ma_campuses" rows="8" class="large-text"><?php echo esc_textarea(implode("\n", MA_Groups::campuses())); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ma_sections"><?php esc_html_e('Sections', 'monthly-achievers'); ?></label></th>
                        <td><textarea id="ma_sections" name="ma_sections" rows="8" class="large-text"><?php echo esc_textarea(implode("\n", MA_Groups::sections())); ?></textarea></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <h2><?php esc_html_e('Shortcodes', 'monthly-achievers'); ?></h2>
            <table class="widefat striped" style="max-width:720px">
                <?php foreach (MA_Groups::all() as $group) : ?>
                    <tr>
                        <td><?php echo esc_html($group['menu_name']); ?></td>
                        <td><code>[<?php echo esc_html($group['shortcode']); ?>]</code></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <p class="description">
                <?php esc_html_e('Optional attributes: month="2026-10" (default: latest month), campus="", section="", columns="3", limit="-1".', 'monthly-achievers'); ?>
            </p>
        </div>
        <?php
    }
}
