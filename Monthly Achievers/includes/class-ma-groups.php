<?php
if (!defined('ABSPATH')) exit;

/**
 * Definition of the three achiever groups. Everything else is driven by this list.
 */
class MA_Groups {

    /** Display options saved in Settings. */
    public static function design() {
        return wp_parse_args((array) get_option('ma_design', array()), array(
            'style'           => 'light',
            'accent_students'  => '',
            'accent_huffaz'    => '',
            'accent_employees' => '',
        ));
    }

    public static function all() {
        $groups = self::defaults();
        $design = self::design();
        foreach ($groups as $key => $group) {
            $c = isset($design['accent_' . $key]) ? $design['accent_' . $key] : '';
            if (preg_match('/^#[0-9a-f]{6}$/i', $c)) $groups[$key]['accent'] = $c;
        }
        return $groups;
    }

    private static function defaults() {
        return array(
            'students' => array(
                'post_type'  => 'ma_student',
                'shortcode'  => 'students_of_month',
                'menu_name'  => __('Students of the Month', 'monthly-achievers'),
                'singular'   => __('Student of the Month', 'monthly-achievers'),
                'name_label' => __('Student Name', 'monthly-achievers'),
                'icon'       => 'dashicons-awards',
                'position'   => 26,
                'has_grade'  => true,
                'has_designation' => false,
                'id_label'   => __('GR No', 'monthly-achievers'),
                'id_numeric' => true,
                'badge'      => __('Student of the Month', 'monthly-achievers'),
                'accent'     => '#2563eb',
            ),
            'huffaz' => array(
                'post_type'  => 'ma_hafiz',
                'shortcode'  => 'huffaz_of_month',
                'menu_name'  => __('Huffaz of the Month', 'monthly-achievers'),
                'singular'   => __('Hafiz of the Month', 'monthly-achievers'),
                'name_label' => __('Student Name', 'monthly-achievers'),
                'icon'       => 'dashicons-book-alt',
                'position'   => 27,
                'has_grade'  => true,
                'has_designation' => false,
                'id_label'   => __('GR No', 'monthly-achievers'),
                'id_numeric' => true,
                'badge'      => __('Hafiz of the Month', 'monthly-achievers'),
                'accent'     => '#059669',
            ),
            'employees' => array(
                'post_type'  => 'ma_employee',
                'shortcode'  => 'employees_of_month',
                'menu_name'  => __('Employees of the Month', 'monthly-achievers'),
                'singular'   => __('Employee of the Month', 'monthly-achievers'),
                'name_label' => __('Employee Name', 'monthly-achievers'),
                'icon'       => 'dashicons-businessperson',
                'position'   => 28,
                'has_grade'  => false,
                'has_designation' => true,
                'id_label'   => __('Employee ID', 'monthly-achievers'),
                'id_numeric' => false,
                'badge'      => __('Employee of the Month', 'monthly-achievers'),
                'accent'     => '#d97706',
            ),
        );
    }

    /** Find a group config by its post type. */
    public static function by_post_type($post_type) {
        foreach (self::all() as $key => $group) {
            if ($group['post_type'] === $post_type) {
                $group['key'] = $key;
                return $group;
            }
        }
        return null;
    }

    public static function campuses() {
        return (array) get_option('ma_campuses', array());
    }

    /** "2026-10" => "October 2026" */
    public static function format_month($ym) {
        if (!preg_match('/^(\d{4})-(\d{2})$/', (string) $ym, $m)) return '';
        return date_i18n('F Y', mktime(0, 0, 0, (int) $m[2], 1, (int) $m[1]));
    }
}
