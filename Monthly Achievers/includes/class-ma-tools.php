<?php
if (!defined('ABSPATH')) exit;

/**
 * Tools > Monthly Achievers Import/Export (CSV).
 *
 * Column order (first row is a header and is skipped):
 *   Students / Huffaz: Name, Grade, GR No, Campus, Month
 *   Employees:         Name, Designation, Employee ID, Campus, Month
 * Month accepts 2026-10, 10/2026 or "October 2026".
 */
class MA_Tools {

    const MAX_BYTES = 2097152; // 2 MB
    const MAX_ROWS  = 2000;

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_ma_export', array(__CLASS__, 'export'));
        add_action('admin_post_ma_import', array(__CLASS__, 'import'));
    }

    public static function menu() {
        add_management_page(
            __('Monthly Achievers Import/Export', 'monthly-achievers'),
            __('Monthly Achievers', 'monthly-achievers'),
            'manage_options',
            'ma-tools',
            array(__CLASS__, 'render')
        );
    }

    private static function columns($group) {
        $cols = array($group['name_label']);
        if ($group['has_designation']) $cols[] = __('Designation', 'monthly-achievers');
        if ($group['has_grade']) $cols[] = __('Grade', 'monthly-achievers');
        return array_merge($cols, array($group['id_label'], __('Campus', 'monthly-achievers'), __('Month', 'monthly-achievers')));
    }

    /* ------------------------------------------------------------- Export */

    public static function export() {
        if (!current_user_can('manage_options')) wp_die(esc_html__('You do not have permission to do this.', 'monthly-achievers'));
        check_admin_referer('ma_export');

        $all = MA_Groups::all();
        $key = isset($_GET['group']) ? sanitize_key($_GET['group']) : '';
        if (!isset($all[$key])) wp_die(esc_html__('Unknown group.', 'monthly-achievers'));
        $group = $all[$key];

        $posts = get_posts(array(
            'post_type'      => $group['post_type'],
            'post_status'    => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'meta_key'       => '_ma_month',
            'orderby'        => array('meta_value' => 'DESC', 'title' => 'ASC'),
        ));

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($key . '-of-the-month-' . gmdate('Y-m-d') . '.csv') . '"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads non-Latin names correctly
        fputcsv($out, self::columns($group));
        foreach ($posts as $post) {
            $m   = function ($k) use ($post) { return get_post_meta($post->ID, '_ma_' . $k, true); };
            $row = array(get_the_title($post));
            if ($group['has_designation']) $row[] = $m('designation');
            if ($group['has_grade']) $row[] = $m('grade');
            $row = array_merge($row, array($m('id'), $m('campus'), $m('month')));
            fputcsv($out, array_map(array(__CLASS__, 'safe_cell'), $row));
        }
        fclose($out);
        exit;
    }

    /** Neutralise spreadsheet formula injection. */
    private static function safe_cell($v) {
        $v = (string) $v;
        return ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v;
    }

    /* ------------------------------------------------------------- Import */

    public static function import() {
        if (!current_user_can('manage_options')) wp_die(esc_html__('You do not have permission to do this.', 'monthly-achievers'));
        check_admin_referer('ma_import');

        $back = admin_url('tools.php?page=ma-tools');
        $all  = MA_Groups::all();
        $key  = isset($_POST['group']) ? sanitize_key($_POST['group']) : '';
        $file = isset($_FILES['ma_csv']) ? $_FILES['ma_csv'] : null;

        $fail = function ($msg) use ($back) {
            set_transient('ma_import_' . get_current_user_id(), array('error' => $msg), 120);
            wp_safe_redirect($back);
            exit;
        };

        if (!isset($all[$key])) $fail(__('Please choose a group.', 'monthly-achievers'));
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) $fail(__('The file could not be uploaded.', 'monthly-achievers'));
        if ($file['size'] > self::MAX_BYTES) $fail(__('The file is larger than 2 MB.', 'monthly-achievers'));
        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') $fail(__('Please upload a .csv file.', 'monthly-achievers'));

        $group      = $all[$key];
        $create     = !empty($_POST['create_missing']);
        $campuses   = MA_Groups::campuses();
        $expected   = count(self::columns($group));
        $imported   = 0;
        $skipped    = 0;
        $errors     = array();
        $new_campus = array();

        $fh = fopen($file['tmp_name'], 'r');
        if (!$fh) $fail(__('The file could not be read.', 'monthly-achievers'));

        $line = 0;
        while (($row = fgetcsv($fh)) !== false) {
            $line++;
            if ($line === 1) continue; // header
            if ($line > self::MAX_ROWS + 1) { $errors[] = sprintf(__('Stopped after %d rows.', 'monthly-achievers'), self::MAX_ROWS); break; }
            if (count($row) === 1 && trim((string) $row[0]) === '') continue; // blank line

            $row = array_map(function ($c) { return sanitize_text_field((string) $c); }, $row);
            if (count($row) < $expected) { $errors[] = sprintf(__('Row %d: expected %d columns.', 'monthly-achievers'), $line, $expected); $skipped++; continue; }

            $name = $row[0];
            $i    = 1;
            $designation = '';
            if ($group['has_designation']) $designation = $row[$i++];
            $grade = '';
            if ($group['has_grade']) $grade = $row[$i++];
            $id      = $row[$i++];
            $campus  = $row[$i++];
            $month   = self::parse_month($row[$i]);

            $problem = '';
            if ($name === '' || ($group['has_grade'] && $grade === '') || ($group['has_designation'] && $designation === '') || $id === '' || $campus === '') $problem = __('a required value is empty', 'monthly-achievers');
            elseif ($group['id_numeric'] && !ctype_digit($id)) $problem = sprintf(__('%s must be a number', 'monthly-achievers'), $group['id_label']);
            elseif (!$month) $problem = __('invalid month', 'monthly-achievers');
            elseif (!in_array($campus, $campuses, true) && !$create) $problem = sprintf(__('unknown campus "%s"', 'monthly-achievers'), $campus);
            elseif (MA_Post_Types::is_duplicate(0, $group['post_type'], $id, $month)) $problem = sprintf(__('%1$s %2$s already exists for %3$s', 'monthly-achievers'), $group['id_label'], $id, MA_Groups::format_month($month));

            if ($problem) {
                $skipped++;
                if (count($errors) < 25) $errors[] = sprintf(__('Row %1$d: %2$s.', 'monthly-achievers'), $line, $problem);
                continue;
            }

            if (!in_array($campus, $campuses, true))  { $campuses[] = $campus;  $new_campus[] = $campus; }

            $meta = array('_ma_id' => $id, '_ma_campus' => $campus, '_ma_month' => $month);
            if ($group['has_grade']) $meta['_ma_grade'] = $grade;
            if ($group['has_designation']) $meta['_ma_designation'] = $designation;

            $post_id = wp_insert_post(array(
                'post_type'   => $group['post_type'],
                'post_title'  => $name,
                'post_status' => 'publish',
                'meta_input'  => $meta,
            ), true);

            if (is_wp_error($post_id)) { $skipped++; $errors[] = sprintf(__('Row %d: could not be saved.', 'monthly-achievers'), $line); continue; }
            $imported++;
        }
        fclose($fh);

        if ($new_campus) update_option('ma_campuses', $campuses);

        set_transient('ma_import_' . get_current_user_id(), array(
            'imported' => $imported,
            'skipped'  => $skipped,
            'errors'   => $errors,
            'created'  => $new_campus,
        ), 120);
        wp_safe_redirect($back);
        exit;
    }

    private static function parse_month($v) {
        $v = trim($v);
        $y = $mo = 0;
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $v, $m))      { $y = (int) $m[1]; $mo = (int) $m[2]; }
        elseif (preg_match('/^(\d{1,2})\/(\d{4})$/', $v, $m)) { $y = (int) $m[2]; $mo = (int) $m[1]; }
        elseif ($v !== '' && ($t = strtotime('1 ' . $v)))     { $y = (int) gmdate('Y', $t); $mo = (int) gmdate('n', $t); }
        if ($y < 2000 || $y > 2100 || $mo < 1 || $mo > 12) return '';
        return sprintf('%04d-%02d', $y, $mo);
    }

    /* --------------------------------------------------------------- Page */

    public static function render() {
        if (!current_user_can('manage_options')) return;

        $key    = 'ma_import_' . get_current_user_id();
        $result = get_transient($key);
        if ($result) delete_transient($key);
        $groups = MA_Groups::all();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Monthly Achievers: Import / Export', 'monthly-achievers'); ?></h1>

            <?php if ($result) : ?>
                <?php if (!empty($result['error'])) : ?>
                    <div class="notice notice-error"><p><?php echo esc_html($result['error']); ?></p></div>
                <?php else : ?>
                    <div class="notice <?php echo $result['imported'] ? 'notice-success' : 'notice-warning'; ?>">
                        <p><?php printf(esc_html__('%1$d imported, %2$d skipped.', 'monthly-achievers'), (int) $result['imported'], (int) $result['skipped']); ?>
                        <?php if (!empty($result['created'])) echo ' ' . esc_html(sprintf(__('Added to settings: %s.', 'monthly-achievers'), implode(', ', $result['created']))); ?></p>
                        <?php if (!empty($result['errors'])) : ?>
                            <ul style="list-style:disc;margin-left:20px">
                                <?php foreach ($result['errors'] as $e) echo '<li>' . esc_html($e) . '</li>'; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <h2><?php esc_html_e('Export', 'monthly-achievers'); ?></h2>
            <p><?php esc_html_e('Download every record of a group as CSV. An empty group gives you a blank template with the right columns.', 'monthly-achievers'); ?></p>
            <p>
                <?php foreach ($groups as $k => $g) : ?>
                    <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ma_export&group=' . $k), 'ma_export')); ?>"><?php echo esc_html($g['menu_name']); ?></a>
                <?php endforeach; ?>
            </p>

            <h2><?php esc_html_e('Import', 'monthly-achievers'); ?></h2>
            <p><?php esc_html_e('The first row must be a header row. Column order:', 'monthly-achievers'); ?></p>
            <ul style="list-style:disc;margin-left:20px">
                <?php foreach ($groups as $g) : ?>
                    <li><strong><?php echo esc_html($g['menu_name']); ?>:</strong> <?php echo esc_html(implode(', ', self::columns($g))); ?></li>
                <?php endforeach; ?>
            </ul>
            <p class="description"><?php esc_html_e('Month can be 2026-10, 10/2026 or "October 2026". Records that already exist for the same ID and month are skipped. Maximum 2 MB and 2000 rows.', 'monthly-achievers'); ?></p>

            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="ma_import">
                <?php wp_nonce_field('ma_import'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="ma_group"><?php esc_html_e('Group', 'monthly-achievers'); ?></label></th>
                        <td>
                            <select id="ma_group" name="group" required>
                                <?php foreach ($groups as $k => $g) echo '<option value="' . esc_attr($k) . '">' . esc_html($g['menu_name']) . '</option>'; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ma_csv"><?php esc_html_e('CSV file', 'monthly-achievers'); ?></label></th>
                        <td><input type="file" id="ma_csv" name="ma_csv" accept=".csv,text/csv" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Options', 'monthly-achievers'); ?></th>
                        <td><label><input type="checkbox" name="create_missing" value="1"> <?php esc_html_e('Add unknown campuses to Settings instead of skipping those rows', 'monthly-achievers'); ?></label></td>
                    </tr>
                </table>
                <?php submit_button(__('Import', 'monthly-achievers')); ?>
            </form>
        </div>
        <?php
    }
}
