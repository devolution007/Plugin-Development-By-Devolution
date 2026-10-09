<?php
if (!defined('ABSPATH')) exit;

class DYTF_Shortcode {
    public static function init() {
        add_shortcode('devo_youtube_feed', array(__CLASS__, 'render'));
        add_action('wp_ajax_dytf_more', array(__CLASS__, 'ajax_more'));
        add_action('wp_ajax_nopriv_dytf_more', array(__CLASS__, 'ajax_more'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_assets'));
    }

    public static function register_assets() {
        wp_register_style('dytf', DYTF_URL . 'assets/feed.css', array(), DYTF_VERSION);
        wp_register_script('dytf', DYTF_URL . 'assets/feed.js', array(), DYTF_VERSION, true);
        wp_localize_script('dytf', 'DYTF', array(
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dytf_more'),
            'close' => __('Close', 'youtube-feed-wp'),
        ));
    }

    private static function yes($v) {
        return !in_array(strtolower((string) $v), array('no', 'false', '0', 'off'), true);
    }

    private static function normalize($a) {
        return array(
            'limit' => max(1, min(50, (int) ($a['limit'] ?? 9))),
            'columns' => max(1, min(6, (int) ($a['columns'] ?? 3))),
            'layout' => ($a['layout'] ?? '') === 'list' ? 'list' : 'grid',
            'play' => ($a['play'] ?? '') === 'youtube' ? 'youtube' : 'lightbox',
            'show_title' => self::yes($a['show_title'] ?? 'yes') ? 'yes' : 'no',
            'show_date' => self::yes($a['show_date'] ?? 'yes') ? 'yes' : 'no',
            'load_more' => self::yes($a['load_more'] ?? 'yes') ? 'yes' : 'no',
        );
    }

    public static function render($atts) {
        $atts = shortcode_atts(array(
            'channel' => '', 'playlist' => '', 'limit' => 9, 'columns' => 3, 'layout' => 'grid',
            'play' => 'lightbox', 'show_title' => 'yes', 'show_date' => 'yes', 'load_more' => 'yes',
        ), $atts, 'devo_youtube_feed');
        $o = self::normalize($atts);

        $playlist = DYTF_API::resolve_playlist($atts['channel'], $atts['playlist']);
        $data = is_wp_error($playlist) ? $playlist : DYTF_API::videos($playlist, $o['limit']);
        if (is_wp_error($data)) return self::error($data);
        if (empty($data['items'])) return '<p class="dytf-empty">' . esc_html__('No videos found.', 'youtube-feed-wp') . '</p>';

        wp_enqueue_style('dytf');
        wp_enqueue_script('dytf');

        $cls = 'dytf-feed dytf-' . $o['layout'] . ' dytf-cols-' . $o['columns'];
        ob_start();
        printf('<div class="%s" data-playlist="%s" data-limit="%d" data-play="%s" data-title="%s" data-date="%s">',
            esc_attr($cls), esc_attr($playlist), $o['limit'], esc_attr($o['play']), esc_attr($o['show_title']), esc_attr($o['show_date']));
        echo '<div class="dytf-items">' . self::items_html($data['items'], $o) . '</div>';
        if ($o['load_more'] === 'yes' && $data['next'] !== '') {
            printf('<button type="button" class="dytf-more" data-next="%s">%s</button>', esc_attr($data['next']), esc_html__('Load more', 'youtube-feed-wp'));
        }
        echo '</div>';
        return ob_get_clean();
    }

    private static function error($err) {
        // Only site admins see diagnostics; visitors see nothing.
        if (!current_user_can('manage_options')) return '';
        /* translators: %s: error message */
        return '<p class="dytf-error">' . esc_html(sprintf(__('YouTube Feed: %s', 'youtube-feed-wp'), $err->get_error_message())) . '</p>';
    }

    private static function items_html(array $items, array $o) {
        $html = '';
        foreach ($items as $v) {
            $url = 'https://www.youtube.com/watch?v=' . rawurlencode($v['id']);
            $html .= '<a class="dytf-item" href="' . esc_url($url) . '" data-video="' . esc_attr($v['id']) . '"'
                . ($o['play'] === 'youtube' ? ' target="_blank" rel="noopener"' : '') . '>';
            $html .= '<span class="dytf-thumb"><img src="' . esc_url($v['thumb']) . '" alt="" loading="lazy"><span class="dytf-play" aria-hidden="true"></span></span>';
            $html .= '<span class="dytf-meta">';
            if ($o['show_title'] === 'yes') $html .= '<span class="dytf-title">' . esc_html($v['title']) . '</span>';
            if ($o['show_date'] === 'yes' && $v['date'] !== '') {
                $html .= '<time class="dytf-date" datetime="' . esc_attr($v['date']) . '">' . esc_html(mysql2date(get_option('date_format'), $v['date'])) . '</time>';
            }
            $html .= '</span></a>';
        }
        return $html;
    }

    public static function ajax_more() {
        check_ajax_referer('dytf_more', 'nonce');
        $playlist = isset($_POST['playlist']) ? sanitize_text_field(wp_unslash($_POST['playlist'])) : '';
        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $playlist) || !preg_match('/^[A-Za-z0-9_-]*$/', $token)) wp_send_json_error();

        $o = self::normalize(array(
            'limit' => isset($_POST['limit']) ? absint($_POST['limit']) : 9,
            'play' => isset($_POST['play']) ? sanitize_key(wp_unslash($_POST['play'])) : '',
            'show_title' => isset($_POST['show_title']) ? sanitize_key(wp_unslash($_POST['show_title'])) : 'yes',
            'show_date' => isset($_POST['show_date']) ? sanitize_key(wp_unslash($_POST['show_date'])) : 'yes',
        ));
        $data = DYTF_API::videos($playlist, $o['limit'], $token);
        if (is_wp_error($data)) wp_send_json_error();
        wp_send_json_success(array('html' => self::items_html($data['items'], $o), 'next' => $data['next']));
    }
}
