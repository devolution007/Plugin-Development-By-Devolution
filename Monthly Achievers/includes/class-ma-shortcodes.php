<?php
if (!defined('ABSPATH')) exit;

/**
 * [monthly_achievers]  - all groups in tabs, carousel per tab, month dropdown
 * [students_of_month], [huffaz_of_month], [employees_of_month] - single group grid
 */
class MA_Shortcodes {

    private static $instance = 0;

    public static function init() {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_assets'));
        add_shortcode('monthly_achievers', array(__CLASS__, 'render_tabs'));
        add_action('wp_ajax_ma_load_month', array(__CLASS__, 'ajax_month'));
        add_action('wp_ajax_nopriv_ma_load_month', array(__CLASS__, 'ajax_month'));
        foreach (MA_Groups::all() as $key => $group) {
            add_shortcode($group['shortcode'], function ($atts) use ($key) {
                return MA_Shortcodes::render($key, $atts);
            });
        }
    }

    /** Version by file modification time so browser/cache plugins fetch updated assets. */
    private static function asset_version($rel) {
        $mtime = @filemtime(MA_DIR . $rel);
        return $mtime ? MA_VERSION . '.' . $mtime : MA_VERSION;
    }

    public static function register_assets() {
        wp_register_style('monthly-achievers', MA_URL . 'assets/css/frontend.css', array(), self::asset_version('assets/css/frontend.css'));
        wp_register_script('monthly-achievers', MA_URL . 'assets/js/frontend.js', array(), self::asset_version('assets/js/frontend.js'), true);
        wp_localize_script('monthly-achievers', 'MA_Config', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'noMatch' => __('No achievers match these filters.', 'monthly-achievers'),
        ));
    }

    /* --------------------------------------------- [monthly_achievers] */

    public static function render_tabs($atts) {
        $atts = shortcode_atts(array(
            'groups'   => 'students,huffaz,employees',
            'month'    => 'latest',
            'campus'   => '',
            'section'  => '',
            'per_page' => 3,
        ), $atts, 'monthly_achievers');

        $keys = self::parse_groups($atts['groups']);
        if (!$keys) return '';

        wp_enqueue_style('monthly-achievers');
        wp_enqueue_script('monthly-achievers');

        $months = self::available_months($keys);
        if (!$months) {
            return '<div class="ma-empty">' . esc_html__('No achievers have been announced yet.', 'monthly-achievers') . '</div>';
        }
        $month = sanitize_text_field($atts['month']);
        if (!in_array($month, $months, true)) $month = $months[0];

        $groups   = MA_Groups::all();
        $uid      = 'ma-' . (++self::$instance);
        $per_page = max(1, min(6, (int) $atts['per_page']));
        $campuses = MA_Groups::campuses();
        $sections = MA_Groups::sections();

        ob_start();
        ?>
        <section class="ma-wrap ma-tabs" id="<?php echo esc_attr($uid); ?>" style="--ma-per:<?php echo (int) $per_page; ?>"
                 data-groups="<?php echo esc_attr(implode(',', $keys)); ?>"
                 data-campus="<?php echo esc_attr($atts['campus']); ?>"
                 data-section="<?php echo esc_attr($atts['section']); ?>">
            <div class="ma-toolbar">
                <?php if (count($keys) > 1) : ?>
                    <div class="ma-tablist" role="tablist">
                        <?php foreach ($keys as $i => $key) : ?>
                            <button type="button" role="tab" class="ma-tab<?php echo $i === 0 ? ' is-active' : ''; ?>"
                                    id="<?php echo esc_attr("$uid-tab-$key"); ?>" aria-controls="<?php echo esc_attr("$uid-panel-$key"); ?>"
                                    aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>" tabindex="<?php echo $i === 0 ? '0' : '-1'; ?>"
                                    style="--ma-accent:<?php echo esc_attr($groups[$key]['accent']); ?>">
                                <?php echo esc_html($groups[$key]['menu_name']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="ma-filters">
                    <?php if ($atts['campus'] === '' && count($campuses) > 1) : ?>
                        <select class="ma-filter" data-filter="campus" aria-label="<?php esc_attr_e('Campus', 'monthly-achievers'); ?>">
                            <option value=""><?php esc_html_e('All campuses', 'monthly-achievers'); ?></option>
                            <?php foreach ($campuses as $c) echo '<option value="' . esc_attr($c) . '">' . esc_html($c) . '</option>'; ?>
                        </select>
                    <?php endif; ?>
                    <?php if ($atts['section'] === '' && count($sections) > 1) : ?>
                        <select class="ma-filter" data-filter="section" aria-label="<?php esc_attr_e('Section', 'monthly-achievers'); ?>">
                            <option value=""><?php esc_html_e('All sections', 'monthly-achievers'); ?></option>
                            <?php foreach ($sections as $sec) echo '<option value="' . esc_attr($sec) . '">' . esc_html($sec) . '</option>'; ?>
                        </select>
                    <?php endif; ?>
                    <select class="ma-month-select" aria-label="<?php esc_attr_e('Month', 'monthly-achievers'); ?>">
                        <?php foreach ($months as $m) echo '<option value="' . esc_attr($m) . '"' . selected($m, $month, false) . '>' . esc_html(MA_Groups::format_month($m)) . '</option>'; ?>
                    </select>
                </div>
            </div>
            <div class="ma-panels" aria-live="polite">
                <?php echo self::panels($keys, $month, $atts['campus'], $atts['section'], $uid); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    /** Markup for every group's carousel panel for one month. */
    private static function panels($keys, $month, $campus, $section, $uid) {
        $groups = MA_Groups::all();
        $atts   = array('campus' => $campus, 'section' => $section);
        $out    = '';
        foreach ($keys as $i => $key) {
            $group = $groups[$key];
            $posts = self::query($group, $month, $atts);
            $out .= '<div class="ma-panel' . ($i === 0 ? ' is-active' : '') . '" role="tabpanel" id="' . esc_attr("$uid-panel-$key") . '"'
                . ' aria-labelledby="' . esc_attr("$uid-tab-$key") . '" style="--ma-accent:' . esc_attr($group['accent']) . '"' . ($i === 0 ? '' : ' hidden') . '>';
            if (!$posts) {
                $out .= '<div class="ma-empty">' . esc_html__('No achievers announced for this month.', 'monthly-achievers') . '</div>';
            } else {
                $out .= '<div class="ma-carousel"><button type="button" class="ma-arrow ma-prev" aria-label="' . esc_attr__('Previous', 'monthly-achievers') . '">&#8249;</button>'
                    . '<div class="ma-track" tabindex="0">';
                foreach ($posts as $post) $out .= self::card($group, $post);
                $out .= '</div><button type="button" class="ma-arrow ma-next" aria-label="' . esc_attr__('Next', 'monthly-achievers') . '">&#8250;</button></div>'
                    . '<div class="ma-dots"></div><div class="ma-nomatch" hidden></div>';
            }
            $out .= '</div>';
        }
        return $out;
    }

    public static function ajax_month() {
        $keys    = self::parse_groups(isset($_POST['groups']) ? sanitize_text_field(wp_unslash($_POST['groups'])) : '');
        $month   = isset($_POST['month']) ? sanitize_text_field(wp_unslash($_POST['month'])) : '';
        $campus  = isset($_POST['campus']) ? sanitize_text_field(wp_unslash($_POST['campus'])) : '';
        $section = isset($_POST['section']) ? sanitize_text_field(wp_unslash($_POST['section'])) : '';
        $uid     = isset($_POST['uid']) ? preg_replace('/[^a-z0-9\-]/i', '', wp_unslash($_POST['uid'])) : 'ma-0';

        if (!$keys || !in_array($month, self::available_months($keys), true)) {
            wp_send_json_error();
        }
        wp_send_json_success(array('html' => self::panels($keys, $month, $campus, $section, $uid)));
    }

    private static function parse_groups($list) {
        $all  = MA_Groups::all();
        $keys = array();
        foreach (explode(',', (string) $list) as $k) {
            $k = trim($k);
            if (isset($all[$k]) && !in_array($k, $keys, true)) $keys[] = $k;
        }
        return $keys;
    }

    /** Distinct months (newest first) with at least one published record in the given groups. */
    private static function available_months($keys) {
        global $wpdb;
        $groups = MA_Groups::all();
        $types  = array();
        foreach ($keys as $k) $types[] = $groups[$k]['post_type'];
        $in  = implode(',', array_fill(0, count($types), '%s'));
        $sql = "SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE pm.meta_key = '_ma_month' AND pm.meta_value <> '' AND p.post_status = 'publish' AND p.post_type IN ($in)
                ORDER BY pm.meta_value DESC";
        return $wpdb->get_col($wpdb->prepare($sql, $types)); // phpcs:ignore WordPress.DB.PreparedSQL
    }

    /* ----------------------------------- single-group grid shortcodes */

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

        $posts = self::query($group, $month, $atts, (int) $atts['limit']);
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
                <?php foreach ($posts as $post) echo self::card($group, $post); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    /* ------------------------------------------------------- helpers */

    private static function query($group, $month, $atts, $limit = -1) {
        $mq = array(array('key' => '_ma_month', 'value' => $month));
        if (!empty($atts['campus']))  $mq[] = array('key' => '_ma_campus',  'value' => sanitize_text_field($atts['campus']));
        if (!empty($atts['section'])) $mq[] = array('key' => '_ma_section', 'value' => sanitize_text_field($atts['section']));

        return get_posts(array(
            'post_type'      => $group['post_type'],
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => $mq,
        ));
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

    /** One winner card. */
    private static function card($group, $post) {
        $m    = function ($k) use ($post) { return get_post_meta($post->ID, '_ma_' . $k, true); };
        $name = get_the_title($post);
        ob_start();
        ?>
        <article class="ma-card" data-campus="<?php echo esc_attr($m('campus')); ?>" data-section="<?php echo esc_attr($m('section')); ?>">
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
        <?php
        return ob_get_clean();
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
