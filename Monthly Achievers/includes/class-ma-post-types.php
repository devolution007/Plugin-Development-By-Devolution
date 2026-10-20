<?php
if (!defined('ABSPATH')) exit;

/**
 * Registers the three post types, their meta box, validation and list-table columns.
 */
class MA_Post_Types {

    const NONCE = 'ma_save_record';

    public static function init() {
        add_action('init', array(__CLASS__, 'register'));
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_box'));
        add_action('save_post', array(__CLASS__, 'save'), 10, 2);
        add_action('admin_notices', array(__CLASS__, 'notices'));
        add_filter('enter_title_here', array(__CLASS__, 'title_placeholder'), 10, 2);
        add_action('restrict_manage_posts', array(__CLASS__, 'filters'));
        add_action('pre_get_posts', array(__CLASS__, 'admin_query'));

        foreach (MA_Groups::all() as $group) {
            $pt = $group['post_type'];
            add_filter("manage_{$pt}_posts_columns", array(__CLASS__, 'columns'));
            add_action("manage_{$pt}_posts_custom_column", array(__CLASS__, 'column_content'), 10, 2);
            add_filter("manage_edit-{$pt}_sortable_columns", array(__CLASS__, 'sortable'));
        }
    }

    public static function register() {
        foreach (MA_Groups::all() as $group) {
            register_post_type($group['post_type'], array(
                'labels' => array(
                    'name'               => $group['menu_name'],
                    'singular_name'      => $group['singular'],
                    'menu_name'          => $group['menu_name'],
                    'all_items'          => __('All Records', 'monthly-achievers'),
                    'add_new'            => __('Add New', 'monthly-achievers'),
                    'add_new_item'       => sprintf(__('Add New %s', 'monthly-achievers'), $group['singular']),
                    'edit_item'          => sprintf(__('Edit %s', 'monthly-achievers'), $group['singular']),
                    'new_item'           => sprintf(__('New %s', 'monthly-achievers'), $group['singular']),
                    'search_items'       => __('Search Records', 'monthly-achievers'),
                    'not_found'          => __('No records found.', 'monthly-achievers'),
                    'not_found_in_trash' => __('No records found in Trash.', 'monthly-achievers'),
                ),
                'public'              => false,
                'show_ui'             => true,
                'show_in_menu'        => true,
                'show_in_rest'        => false,
                'exclude_from_search' => true,
                'menu_icon'           => $group['icon'],
                'menu_position'       => $group['position'],
                'supports'            => array('title'),
                'capability_type'     => 'post',
            ));
        }
    }

    public static function title_placeholder($text, $post) {
        $group = MA_Groups::by_post_type($post->post_type);
        return $group ? $group['name_label'] : $text;
    }

    /* ---------------------------------------------------------------- Meta box */

    public static function add_meta_box() {
        foreach (MA_Groups::all() as $group) {
            add_meta_box('ma_details', __('Achiever Details', 'monthly-achievers'), array(__CLASS__, 'render_box'), $group['post_type'], 'normal', 'high');
        }
    }

    public static function render_box($post) {
        $group = MA_Groups::by_post_type($post->post_type);
        $get   = function ($key) use ($post) { return get_post_meta($post->ID, '_ma_' . $key, true); };
        wp_nonce_field(self::NONCE, 'ma_nonce');
        ?>
        <p class="description"><?php printf(esc_html__('Enter the %s in the title field above, then complete all fields below.', 'monthly-achievers'), esc_html(strtolower($group['name_label']))); ?></p>
        <table class="form-table" role="presentation">
            <?php if ($group['has_grade']) : ?>
                <tr>
                    <th><label for="ma_grade"><?php esc_html_e('Grade', 'monthly-achievers'); ?> *</label></th>
                    <td><input type="text" id="ma_grade" name="ma_grade" class="regular-text" required value="<?php echo esc_attr($get('grade')); ?>"></td>
                </tr>
            <?php endif; ?>
            <tr>
                <th><label for="ma_id"><?php echo esc_html($group['id_label']); ?> *</label></th>
                <td>
                    <?php if ($group['id_numeric']) : ?>
                        <input type="number" id="ma_id" name="ma_id" class="regular-text" min="0" step="1" required value="<?php echo esc_attr($get('id')); ?>">
                    <?php else : ?>
                        <input type="text" id="ma_id" name="ma_id" class="regular-text" required value="<?php echo esc_attr($get('id')); ?>">
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label for="ma_campus"><?php esc_html_e('Campus', 'monthly-achievers'); ?> *</label></th>
                <td><?php self::select('ma_campus', MA_Groups::campuses(), $get('campus')); ?></td>
            </tr>
            <tr>
                <th><label for="ma_section"><?php esc_html_e('Section', 'monthly-achievers'); ?> *</label></th>
                <td><?php self::select('ma_section', MA_Groups::sections(), $get('section')); ?></td>
            </tr>
            <tr>
                <th><label for="ma_month"><?php esc_html_e('Month', 'monthly-achievers'); ?> *</label></th>
                <td>
                    <input type="month" id="ma_month" name="ma_month" required pattern="\d{4}-\d{2}" placeholder="YYYY-MM" value="<?php echo esc_attr($get('month')); ?>">
                    <p class="description"><?php esc_html_e('Only the month and year are used.', 'monthly-achievers'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    private static function select($name, array $options, $current) {
        // Keep a value that was later removed from Settings so editing does not silently drop it.
        if ($current !== '' && !in_array($current, $options, true)) $options[] = $current;
        echo '<select id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" required>';
        echo '<option value="">' . esc_html__('— Select —', 'monthly-achievers') . '</option>';
        foreach ($options as $opt) {
            echo '<option value="' . esc_attr($opt) . '"' . selected($current, $opt, false) . '>' . esc_html($opt) . '</option>';
        }
        echo '</select>';
    }

    /* -------------------------------------------------------------------- Save */

    public static function save($post_id, $post) {
        $group = MA_Groups::by_post_type($post->post_type);
        if (!$group) return;
        if (!isset($_POST['ma_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ma_nonce'])), self::NONCE)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) return;

        $in = function ($key) {
            return isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
        };

        $data = array(
            'id'      => $in('ma_id'),
            'campus'  => $in('ma_campus'),
            'section' => $in('ma_section'),
            'month'   => $in('ma_month'),
        );
        if ($group['has_grade']) $data['grade'] = $in('ma_grade');
        if ($group['id_numeric']) $data['id'] = ctype_digit($data['id']) ? $data['id'] : '';
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $data['month'])) $data['month'] = '';

        foreach ($data as $key => $value) {
            update_post_meta($post_id, '_ma_' . $key, $value);
        }

        $errors = array();
        if (trim($post->post_title) === '') $errors[] = $group['name_label'];
        if ($group['has_grade'] && $data['grade'] === '') $errors[] = __('Grade', 'monthly-achievers');
        if ($data['id'] === '') $errors[] = $group['id_label'];
        if ($data['campus'] === '') $errors[] = __('Campus', 'monthly-achievers');
        if ($data['section'] === '') $errors[] = __('Section', 'monthly-achievers');
        if ($data['month'] === '') $errors[] = __('Month', 'monthly-achievers');

        $message = '';
        if ($errors) {
            $message = sprintf(__('Missing or invalid: %s. The record was saved as a draft.', 'monthly-achievers'), implode(', ', $errors));
        } elseif (self::is_duplicate($post_id, $post->post_type, $data['id'], $data['month'])) {
            $message = sprintf(__('%1$s %2$s is already recorded for %3$s. The record was saved as a draft.', 'monthly-achievers'), $group['id_label'], $data['id'], MA_Groups::format_month($data['month']));
        }

        if ($message) {
            set_transient('ma_notice_' . get_current_user_id(), $message, 60);
            if ($post->post_status === 'publish') {
                remove_action('save_post', array(__CLASS__, 'save'), 10);
                wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'));
                add_action('save_post', array(__CLASS__, 'save'), 10, 2);
            }
        }
    }

    public static function is_duplicate($post_id, $post_type, $id, $month) {
        $found = get_posts(array(
            'post_type'      => $post_type,
            'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
            'post__not_in'   => array($post_id),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array('key' => '_ma_id', 'value' => $id),
                array('key' => '_ma_month', 'value' => $month),
            ),
        ));
        return !empty($found);
    }

    public static function notices() {
        $key = 'ma_notice_' . get_current_user_id();
        $msg = get_transient($key);
        if ($msg) {
            delete_transient($key);
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($msg) . '</p></div>';
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->base === 'edit' && ($group = MA_Groups::by_post_type($screen->post_type))) {
            $code = '[' . $group['shortcode'] . ']';
            echo '<div class="notice notice-info"><p>' . esc_html__('Display these records on any page with the shortcode:', 'monthly-achievers')
                . ' <input type="text" readonly size="24" value="' . esc_attr($code) . '" onclick="this.select()" style="font-family:monospace"></p></div>';
        }
    }

    /* ------------------------------------------------------------ List screens */

    public static function columns($cols) {
        $post_type = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : '';
        $group = MA_Groups::by_post_type($post_type);
        $new = array('cb' => $cols['cb'], 'title' => $group ? $group['name_label'] : __('Name', 'monthly-achievers'));
        if ($group && $group['has_grade']) $new['ma_grade'] = __('Grade', 'monthly-achievers');
        $new['ma_id']      = $group ? $group['id_label'] : __('ID', 'monthly-achievers');
        $new['ma_campus']  = __('Campus', 'monthly-achievers');
        $new['ma_section'] = __('Section', 'monthly-achievers');
        $new['ma_month']   = __('Month', 'monthly-achievers');
        return $new;
    }

    public static function column_content($column, $post_id) {
        if (strpos($column, 'ma_') !== 0) return;
        $value = get_post_meta($post_id, '_' . $column, true);
        echo esc_html($column === 'ma_month' ? MA_Groups::format_month($value) : $value);
    }

    public static function sortable($cols) {
        $cols['ma_month'] = 'ma_month';
        $cols['ma_id']    = 'ma_id';
        return $cols;
    }

    public static function filters($post_type) {
        if (!MA_Groups::by_post_type($post_type)) return;
        $current = isset($_GET['ma_campus']) ? sanitize_text_field(wp_unslash($_GET['ma_campus'])) : '';
        echo '<select name="ma_campus"><option value="">' . esc_html__('All campuses', 'monthly-achievers') . '</option>';
        foreach (MA_Groups::campuses() as $campus) {
            echo '<option value="' . esc_attr($campus) . '"' . selected($current, $campus, false) . '>' . esc_html($campus) . '</option>';
        }
        echo '</select>';
        $month = isset($_GET['ma_month']) ? sanitize_text_field(wp_unslash($_GET['ma_month'])) : '';
        echo '<input type="month" name="ma_month" value="' . esc_attr($month) . '" title="' . esc_attr__('Filter by month', 'monthly-achievers') . '"> ';
    }

    public static function admin_query($query) {
        if (!is_admin() || !$query->is_main_query()) return;
        $post_type = $query->get('post_type');
        if (!is_string($post_type) || !MA_Groups::by_post_type($post_type)) return;

        $meta = array();
        if (!empty($_GET['ma_campus'])) {
            $meta[] = array('key' => '_ma_campus', 'value' => sanitize_text_field(wp_unslash($_GET['ma_campus'])));
        }
        if (!empty($_GET['ma_month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['ma_month'])) {
            $meta[] = array('key' => '_ma_month', 'value' => $_GET['ma_month']);
        }
        if ($meta) $query->set('meta_query', $meta);

        $orderby = $query->get('orderby');
        if ($orderby === 'ma_month') {
            $query->set('meta_key', '_ma_month');
            $query->set('orderby', 'meta_value');
        } elseif ($orderby === 'ma_id') {
            $query->set('meta_key', '_ma_id');
            $query->set('orderby', 'meta_value_num');
        }
    }
}
