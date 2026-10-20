<?php
if (!defined('ABSPATH')) exit;

/**
 * Settings > Monthly Achievers: manage the Campus dropdown values.
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
        foreach (array('ma_campuses') as $option) {
            register_setting('ma_settings', $option, array(
                'type'              => 'array',
                'sanitize_callback' => array(__CLASS__, 'sanitize_list'),
                'default'           => array(),
            ));
        }
        register_setting('ma_settings', 'ma_design', array(
            'type'              => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize_design'),
            'default'           => array(),
        ));
    }

    public static function sanitize_design($value) {
        $value = is_array($value) ? $value : array();
        $out = array('style' => (isset($value['style']) && $value['style'] === 'dark') ? 'dark' : 'light');
        foreach (array_keys(MA_Groups::all()) as $key) {
            $c = isset($value['accent_' . $key]) ? sanitize_hex_color($value['accent_' . $key]) : '';
            $out['accent_' . $key] = $c ? $c : '';
        }
        return $out;
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
            <p><?php esc_html_e('Manage the values shown in the Campus dropdown. Enter one value per line.', 'monthly-achievers'); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields('ma_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="ma_campuses"><?php esc_html_e('Campuses', 'monthly-achievers'); ?></label></th>
                        <td><textarea id="ma_campuses" name="ma_campuses" rows="8" class="large-text"><?php echo esc_textarea(implode("\n", MA_Groups::campuses())); ?></textarea></td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Design', 'monthly-achievers'); ?></h2>
                <?php $design = MA_Groups::design(); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="ma_style"><?php esc_html_e('Card style', 'monthly-achievers'); ?></label></th>
                        <td>
                            <select id="ma_style" name="ma_design[style]">
                                <option value="light" <?php selected($design['style'], 'light'); ?>><?php esc_html_e('Light cards', 'monthly-achievers'); ?></option>
                                <option value="dark" <?php selected($design['style'], 'dark'); ?>><?php esc_html_e('Dark cards', 'monthly-achievers'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <?php foreach (MA_Groups::all() as $key => $group) : ?>
                        <tr>
                            <th scope="row"><label for="ma_accent_<?php echo esc_attr($key); ?>"><?php echo esc_html(sprintf(__('%s colour', 'monthly-achievers'), $group['menu_name'])); ?></label></th>
                            <td><input type="color" id="ma_accent_<?php echo esc_attr($key); ?>" name="ma_design[accent_<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($group['accent']); ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <?php submit_button(); ?>
            </form>

            <h2><?php esc_html_e('Shortcodes', 'monthly-achievers'); ?></h2>
            <table class="widefat striped" style="max-width:720px">
                <tr>
                    <td><?php esc_html_e('All groups (tabs + carousel + month dropdown)', 'monthly-achievers'); ?></td>
                    <td><code>[monthly_achievers]</code></td>
                </tr>
                <?php foreach (MA_Groups::all() as $group) : ?>
                    <tr>
                        <td><?php echo esc_html($group['menu_name']); ?></td>
                        <td><code>[<?php echo esc_html($group['shortcode']); ?>]</code></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <p class="description">
                <?php esc_html_e('Optional attributes: month="2026-10" (default: latest month), campus="", columns="3", limit="-1". [monthly_achievers] also accepts groups="students,huffaz,employees" and per_page="3".', 'monthly-achievers'); ?>
            </p>
        </div>
        <?php
    }
}
