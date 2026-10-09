<?php
if (!defined('ABSPATH')) exit;

class DFBF_Shortcode {
    public static function init() {
        add_shortcode('devo_facebook_feed', array(__CLASS__, 'render'));
        add_action('wp_ajax_dfbf_more', array(__CLASS__, 'ajax_more'));
        add_action('wp_ajax_nopriv_dfbf_more', array(__CLASS__, 'ajax_more'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_assets'));
    }

    public static function register_assets() {
        wp_register_style('dfbf', DFBF_URL . 'assets/feed.css', array(), DFBF_VERSION);
        wp_register_script('dfbf', DFBF_URL . 'assets/feed.js', array(), DFBF_VERSION, true);
        wp_localize_script('dfbf', 'DFBF', array(
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dfbf_more'),
            'close' => __('Close', 'facebook-feed-wp'),
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
            'open' => ($a['open'] ?? '') === 'facebook' ? 'facebook' : 'lightbox',
            'excerpt' => max(0, min(200, (int) ($a['excerpt'] ?? 30))),
            'show_date' => self::yes($a['show_date'] ?? 'yes') ? 'yes' : 'no',
            'load_more' => self::yes($a['load_more'] ?? 'yes') ? 'yes' : 'no',
        );
    }

    public static function render($atts) {
        $atts = shortcode_atts(array(
            'page' => '', 'limit' => 9, 'columns' => 3, 'layout' => 'grid',
            'open' => 'lightbox', 'excerpt' => 30, 'show_date' => 'yes', 'load_more' => 'yes',
        ), $atts, 'devo_facebook_feed');
        $o = self::normalize($atts);

        $page = DFBF_API::resolve_page($atts['page']);
        $data = is_wp_error($page) ? $page : DFBF_API::posts($page, $o['limit']);
        if (is_wp_error($data)) return self::error($data);
        if (empty($data['items'])) return '<p class="dfbf-empty">' . esc_html__('No posts found.', 'facebook-feed-wp') . '</p>';

        wp_enqueue_style('dfbf');
        wp_enqueue_script('dfbf');

        $cls = 'dfbf-feed dfbf-' . $o['layout'] . ' dfbf-cols-' . $o['columns'];
        ob_start();
        printf('<div class="%s" data-page="%s" data-limit="%d" data-open="%s" data-excerpt="%d" data-date="%s">',
            esc_attr($cls), esc_attr($page), $o['limit'], esc_attr($o['open']), $o['excerpt'], esc_attr($o['show_date']));
        echo '<div class="dfbf-items">' . self::items_html($data['items'], $o) . '</div>';
        if ($o['load_more'] === 'yes' && $data['next'] !== '') {
            printf('<button type="button" class="dfbf-more" data-next="%s">%s</button>', esc_attr($data['next']), esc_html__('Load more', 'facebook-feed-wp'));
        }
        echo '</div>';
        return ob_get_clean();
    }

    private static function error($err) {
        // Only site admins see diagnostics; visitors see nothing.
        if (!current_user_can('manage_options')) return '';
        /* translators: %s: error message */
        return '<p class="dfbf-error">' . esc_html(sprintf(__('Facebook Feed: %s', 'facebook-feed-wp'), $err->get_error_message())) . '</p>';
    }

    private static function items_html(array $items, array $o) {
        $html = '';
        foreach ($items as $p) {
            $lightbox = $o['open'] === 'lightbox' && !$p['video'];
            $html .= '<article class="dfbf-item' . ($p['image'] === '' ? ' dfbf-text-only' : '') . '">';
            if ($p['image'] !== '') {
                $html .= '<a class="dfbf-thumb" href="' . esc_url($p['link']) . '" target="_blank" rel="noopener"'
                    . ($lightbox ? ' data-full="' . esc_url($p['image']) . '"' : '') . '>'
                    . '<img src="' . esc_url($p['image']) . '" alt="" loading="lazy">'
                    . ($p['video'] ? '<span class="dfbf-play" aria-hidden="true"></span>' : '')
                    . '</a>';
            }
            $html .= '<div class="dfbf-meta">';
            if ($o['show_date'] === 'yes' && $p['date'] !== '') {
                $html .= '<time class="dfbf-date" datetime="' . esc_attr($p['date']) . '">' . esc_html(mysql2date(get_option('date_format'), $p['date'])) . '</time>';
            }
            if ($o['excerpt'] > 0 && $p['message'] !== '') {
                $html .= '<p class="dfbf-text">' . esc_html(wp_trim_words($p['message'], $o['excerpt'])) . '</p>';
            }
            $html .= '<a class="dfbf-link" href="' . esc_url($p['link']) . '" target="_blank" rel="noopener">' . esc_html__('View on Facebook', 'facebook-feed-wp') . '</a>';
            $html .= '</div>';
            if ($lightbox && $p['message'] !== '') {
                $html .= '<div class="dfbf-full" hidden>' . nl2br(esc_html($p['message'])) . '</div>';
            }
            $html .= '</article>';
        }
        return $html;
    }

    public static function ajax_more() {
        check_ajax_referer('dfbf_more', 'nonce');
        $page = isset($_POST['page']) ? sanitize_text_field(wp_unslash($_POST['page'])) : '';
        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
        if (!preg_match('/^\d+$/', $page) || !preg_match('/^[A-Za-z0-9_=\-]*$/', $token)) wp_send_json_error();

        $o = self::normalize(array(
            'limit' => isset($_POST['limit']) ? absint($_POST['limit']) : 9,
            'open' => isset($_POST['open']) ? sanitize_key(wp_unslash($_POST['open'])) : '',
            'excerpt' => isset($_POST['excerpt']) ? absint($_POST['excerpt']) : 30,
            'show_date' => isset($_POST['show_date']) ? sanitize_key(wp_unslash($_POST['show_date'])) : 'yes',
        ));
        $data = DFBF_API::posts($page, $o['limit'], $token);
        if (is_wp_error($data)) wp_send_json_error();
        wp_send_json_success(array('html' => self::items_html($data['items'], $o), 'next' => $data['next']));
    }
}
