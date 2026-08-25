<?php
if (!defined('ABSPATH')) exit;

class MSOG_Shortcode {
    public static function init() {
        add_shortcode('msog_gallery', array(__CLASS__, 'render'));
        add_action('wp_ajax_msog_browse_folder', array(__CLASS__, 'ajax_browse'));
        add_action('wp_ajax_nopriv_msog_browse_folder', array(__CLASS__, 'ajax_browse'));
    }
    public static function render($atts) {
        $a = shortcode_atts(array('drive'=>'', 'folder'=>'root', 'view'=>'gallery', 'columns'=>4, 'limit'=>100, 'images_only'=>'no', 'open'=>'microsoft'), $atts, 'msog_gallery');
        if (!$a['drive']) return self::error(__('Gallery is missing its Drive ID.', 'ms-sharepoint-onedrive-gallery'));
        $view = in_array($a['view'], array('gallery','list','folders'), true) ? $a['view'] : 'gallery';
        $columns = max(1, min(6, (int) $a['columns'])); $limit = max(1, min(200, (int) $a['limit']));
        $data = MSOG_Graph::children(sanitize_text_field($a['drive']), sanitize_text_field($a['folder']));
        if (is_wp_error($data)) return current_user_can('manage_options') ? self::error($data->get_error_message()) : self::error(__('This gallery is temporarily unavailable.', 'ms-sharepoint-onedrive-gallery'));
        wp_enqueue_style('msog-gallery', MSOG_URL . 'assets/gallery.css', array(), MSOG_VERSION);
        if ($view === 'folders') {
            wp_enqueue_style('msog-browser', MSOG_URL . 'assets/browser.css', array('msog-gallery'), MSOG_VERSION);
            wp_enqueue_script('msog-browser', MSOG_URL . 'assets/browser.js', array(), MSOG_VERSION, true);
            wp_localize_script('msog-browser', 'MSOG_BROWSER', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'loading' => __('Loading folder…', 'ms-sharepoint-onedrive-gallery'),
                'error' => __('The folder could not be loaded.', 'ms-sharepoint-onedrive-gallery'),
            ));
            $payload = self::browser_payload($data['value'] ?? array(), sanitize_text_field($a['drive']));
            return '<div class="msog-browser" data-drive="' . esc_attr($a['drive']) . '" data-folder="' . esc_attr($a['folder']) . '" data-token="' . esc_attr(self::sign($a['drive'], $a['folder'])) . '"><div class="msog-browser-nav" hidden><button type="button" class="msog-back">&larr; ' . esc_html__('Back', 'ms-sharepoint-onedrive-gallery') . '</button><span class="msog-browser-path"></span></div><div class="msog-browser-status" role="status" aria-live="polite"></div><div class="msog-gallery msog-view-folders" style="--msog-columns:' . esc_attr($columns) . '">' . self::browser_items_html($payload) . '</div></div>';
        }
        $items = array_slice($data['value'] ?? array(), 0, $limit); $images_only = strtolower($a['images_only']) === 'yes';
        ob_start(); echo '<div class="msog-gallery msog-view-' . esc_attr($view) . '" style="--msog-columns:' . esc_attr($columns) . '">';
        $rendered = 0;
        foreach ($items as $item) {
            if (isset($item['folder'])) continue;
            $mime = $item['file']['mimeType'] ?? ''; $is_image = strpos($mime, 'image/') === 0;
            if ($images_only && !$is_image) continue;
            $thumb = $item['thumbnails'][0]['large']['url'] ?? ($item['thumbnails'][0]['medium']['url'] ?? '');
            $url = esc_url($item['webUrl'] ?? '#'); $name = esc_html($item['name'] ?? '');
            echo '<article class="msog-item ' . ($is_image ? 'is-image' : 'is-file') . '"><a href="' . $url . '" target="_blank" rel="noopener noreferrer">';
            if ($view === 'gallery') { echo '<span class="msog-preview">'; if ($thumb) echo '<img src="' . esc_url($thumb) . '" alt="' . esc_attr($item['name']) . '" loading="lazy">'; else echo '<span class="msog-file-icon" aria-hidden="true">' . esc_html(strtoupper(pathinfo($item['name'], PATHINFO_EXTENSION) ?: 'FILE')) . '</span>'; echo '</span>'; }
            echo '<span class="msog-name">' . $name . '</span>';
            if ($view === 'list') echo '<span class="msog-meta">' . esc_html(self::size((int) ($item['size'] ?? 0))) . '</span>';
            echo '</a></article>';
            $rendered++;
        }
        echo '</div>';
        if (!$rendered) echo self::error(__('This folder contains no displayable files. Files inside subfolders are not shown automatically.', 'ms-sharepoint-onedrive-gallery'));
        return ob_get_clean();
    }
    private static function size($bytes) { return size_format($bytes, 1); }
    private static function error($message) { return '<div class="msog-message" role="status">' . esc_html($message) . '</div>'; }

    private static function sign($drive, $folder) {
        return hash_hmac('sha256', $drive . '|' . $folder, wp_salt('nonce'));
    }
    private static function browser_payload($items, $drive) {
        $out = array();
        foreach ($items as $item) {
            $is_folder = isset($item['folder']);
            $out[] = array(
                'id' => sanitize_text_field($item['id'] ?? ''), 'name' => sanitize_text_field($item['name'] ?? ''),
                'folder' => $is_folder, 'token' => $is_folder ? self::sign($drive, $item['id']) : '',
                'url' => esc_url_raw($item['webUrl'] ?? ''),
                'thumbnail' => esc_url_raw($item['thumbnails'][0]['large']['url'] ?? ($item['thumbnails'][0]['medium']['url'] ?? '')),
            );
        }
        return $out;
    }
    private static function browser_items_html($items) {
        if (!$items) return '<div class="msog-browser-empty">' . esc_html__('This folder is empty.', 'ms-sharepoint-onedrive-gallery') . '</div>';
        $html = '';
        foreach ($items as $item) {
            if ($item['folder']) {
                $html .= '<button type="button" class="msog-folder-card" data-folder="' . esc_attr($item['id']) . '" data-token="' . esc_attr($item['token']) . '" data-name="' . esc_attr($item['name']) . '"><span class="msog-folder-icon" aria-hidden="true"></span><span class="msog-name">' . esc_html($item['name']) . '</span><span class="msog-folder-open" aria-hidden="true">&rsaquo;</span></button>';
            } else {
                $html .= '<article class="msog-item is-file"><a href="' . esc_url($item['url']) . '" target="_blank" rel="noopener noreferrer"><span class="msog-preview">';
                if ($item['thumbnail']) $html .= '<img src="' . esc_url($item['thumbnail']) . '" alt="' . esc_attr($item['name']) . '" loading="lazy">';
                else $html .= '<span class="msog-file-icon" aria-hidden="true">' . esc_html(strtoupper(pathinfo($item['name'], PATHINFO_EXTENSION) ?: 'FILE')) . '</span>';
                $html .= '</span><span class="msog-name">' . esc_html($item['name']) . '</span></a></article>';
            }
        }
        return $html;
    }
    public static function ajax_browse() {
        $drive = sanitize_text_field(wp_unslash($_POST['drive'] ?? ''));
        $folder = sanitize_text_field(wp_unslash($_POST['folder'] ?? ''));
        $token = sanitize_text_field(wp_unslash($_POST['token'] ?? ''));
        if (!$drive || !$folder || !$token || !hash_equals(self::sign($drive, $folder), $token)) {
            wp_send_json_error(array('message' => __('Invalid folder request.', 'ms-sharepoint-onedrive-gallery')), 403);
        }
        $data = MSOG_Graph::children($drive, $folder);
        if (is_wp_error($data)) wp_send_json_error(array('message' => $data->get_error_message()), 502);
        wp_send_json_success(array('html' => self::browser_items_html(self::browser_payload($data['value'] ?? array(), $drive))));
    }
}
