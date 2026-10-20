<?php
if (!defined('ABSPATH')) exit;

/**
 * [students_of_month], [huffaz_of_month], [employees_of_month]
 */
class MA_Shortcodes {

    public static function init() {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_assets'));
        foreach (MA_Groups::all() as $key => $group) {
            add_shortcode($group['shortcode'], function ($atts) use ($key) {
                return MA_Shortcodes::render($key, $atts);
            });
        }
    }

    public static function register_assets() {
        wp_register_style('monthly-achievers', MA_URL . 'assets/css/frontend.css', array(), MA_VERSION);
    }

    public static function render($key, $atts) {
        $groups = MA_Groups::all();
        $group  = $groups[$key];

        $atts = shortcode_atts(array(
            'month'   => 'latest',
            'campus'  => '',
            'section' => '',
            'columns' => 3,
            'limit'   => -1,
        ), $atts, $group['shortcode']);

        $month = sanitize_text_field($atts['month']);
        if ($month === 'latest' || !preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = self::latest_month($group['post_type'], $atts);
        }

        wp_enqueue_style('monthly-achievers');

        if (!$month) {
            return '<div class="ma-empty">' . esc_html__('No achievers have been announced yet.', 'monthly-achievers') . '</div>';
        }

        $posts = get_posts(array(
            'post_type'      => $group['post_type'],
            'post_status'    => 'publish',
            'posts_per_page' => (int) $atts['limit'],
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => self::meta_query($month, $atts),
        ));

        if (!$posts) {
            return '<div class="ma-empty">' . esc_html__('No achievers found for this selection.', 'monthly-achievers') . '</div>';
        }

        $columns = max(1, min(6, (int) $atts['columns']));

        ob_start();
        ?>
        <section class="ma-wrap ma-<?php echo esc_attr($key); ?>" style="--ma-accent:<?php echo esc_attr($group['accent']); ?>;--ma-cols:<?php echo (int) $columns; ?>">
            <header class="ma-header">
                <h2 class="ma-title"><?php echo esc_html($group['menu_name']); ?></h2>
                <span class="ma-month"><?php echo esc_html(MA_Groups::format_month($month)); ?></span>
            </header>
            <div class="ma-grid">
                <?php foreach ($posts as $post) :
                    $m = function ($k) use ($post) { return get_post_meta($post->ID, '_ma_' . $k, true); };
                    $name = get_the_title($post);
                    ?>
                    <article class="ma-card">
                        <span class="ma-badge"><?php echo esc_html($group['badge']); ?></span>
                        <div class="ma-avatar" aria-hidden="true"><?php echo esc_html(self::initials($name)); ?></div>
                        <h3 class="ma-name"><?php echo esc_html($name); ?></h3>
                        <ul class="ma-meta">
                            <?php if ($group['has_grade'] && $m('grade') !== '') : ?>
                                <li><span><?php esc_html_e('Grade', 'monthly-achievers'); ?></span><?php echo esc_html($m('grade')); ?></li>
                            <?php endif; ?>
                            <li><span><?php echo esc_html($group['id_label']); ?></span><?php echo esc_html($m('id')); ?></li>
                            <li><span><?php esc_html_e('Section', 'monthly-achievers'); ?></span><?php echo esc_html($m('section')); ?></li>
                        </ul>
                        <div class="ma-campus"><?php echo esc_html($m('campus')); ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function meta_query($month, $atts) {
        $mq = array(array('key' => '_ma_month', 'value' => $month));
        if ($atts['campus'] !== '')  $mq[] = array('key' => '_ma_campus',  'value' => sanitize_text_field($atts['campus']));
        if ($atts['section'] !== '') $mq[] = array('key' => '_ma_section', 'value' => sanitize_text_field($atts['section']));
        return $mq;
    }

    /** Most recent month that has at least one published record for this filter. */
    private static function latest_month($post_type, $atts) {
        $mq = array(array('key' => '_ma_month', 'compare' => 'EXISTS'));
        if ($atts['campus'] !== '')  $mq[] = array('key' => '_ma_campus',  'value' => sanitize_text_field($atts['campus']));
        if ($atts['section'] !== '') $mq[] = array('key' => '_ma_section', 'value' => sanitize_text_field($atts['section']));

        $latest = get_posts(array(
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => '_ma_month',
            'orderby'        => 'meta_value',
            'order'          => 'DESC',
            'meta_query'     => $mq,
        ));
        return $latest ? get_post_meta($latest[0], '_ma_month', true) : '';
    }

    private static function initials($name) {
        $parts = preg_split('/\s+/', trim($name));
        $out   = '';
        foreach (array_slice($parts, 0, 2) as $p) {
            $out .= function_exists('mb_substr') ? mb_strtoupper(mb_substr($p, 0, 1)) : strtoupper(substr($p, 0, 1));
        }
        return $out;
    }
}
