<?php
if (!defined('ABSPATH')) exit;

class MSOG_Shortcode {
    public static function init() { add_shortcode('msog_gallery', array(__CLASS__, 'render')); }
    public static function render($atts) {
        $a = shortcode_atts(array('drive'=>'', 'folder'=>'root', 'view'=>'gallery', 'columns'=>4, 'limit'=>100, 'images_only'=>'no', 'open'=>'microsoft'), $atts, 'msog_gallery');
        if (!$a['drive']) return self::error(__('Gallery is missing its Drive ID.', 'ms-sharepoint-onedrive-gallery'));
        $view = in_array($a['view'], array('gallery','list'), true) ? $a['view'] : 'gallery';
        $columns = max(1, min(6, (int) $a['columns'])); $limit = max(1, min(200, (int) $a['limit']));
        $data = MSOG_Graph::children(sanitize_text_field($a['drive']), sanitize_text_field($a['folder']));
        if (is_wp_error($data)) return current_user_can('manage_options') ? self::error($data->get_error_message()) : self::error(__('This gallery is temporarily unavailable.', 'ms-sharepoint-onedrive-gallery'));
        wp_enqueue_style('msog-gallery', MSOG_URL . 'assets/gallery.css', array(), MSOG_VERSION);
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
}
