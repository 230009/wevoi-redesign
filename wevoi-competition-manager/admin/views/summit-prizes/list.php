<?php
// Direct access control
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Handle bulk delete
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['prize']) && is_array($_POST['prize'])) {
    check_admin_referer('bulk-summit-prizes');

    $prize_ids = array_map('intval', $_POST['prize']);
    $prize_ids = array_filter($prize_ids);

    if (!empty($prize_ids)) {
        $placeholders = implode(',', array_fill(0, count($prize_ids), '%d'));
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}wevoi_summit_prizes WHERE id IN ($placeholders)",
            ...$prize_ids
        ));

        if ($deleted) {
            wp_redirect(add_query_arg([
                'page' => 'wevoi-competitions',
                'tab' => 'summit-prizes',
                'message' => 3,
                'deleted' => $deleted
            ], admin_url('admin.php')));
            exit;
        }
    }
}

$competition_filter = isset($_GET['competition_filter']) ? intval($_GET['competition_filter']) : 0;
$age_category_filter = isset($_GET['age_category_filter']) ? sanitize_text_field($_GET['age_category_filter']) : '';
$age_categories = wevoi_get_age_categories();

$where = [];
$values = [];

if ($competition_filter > 0) {
    $where[] = 'sp.competition_code = %d';
    $values[] = $competition_filter;
}

if ($age_category_filter !== '' && in_array($age_category_filter, $age_categories, true)) {
    $where[] = 'sp.wevoi_age_category = %s';
    $values[] = $age_category_filter;
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$query = "SELECT sp.*, c.competition_name
          FROM {$wpdb->prefix}wevoi_summit_prizes sp
          LEFT JOIN {$wpdb->prefix}wevoi_competitions c ON sp.competition_code = c.competition_code
          {$where_sql}
          ORDER BY sp.competition_code DESC, sp.wevoi_age_category ASC, sp.student_rank ASC";

if (!empty($values)) {
    $prizes = $wpdb->get_results($wpdb->prepare($query, $values), ARRAY_A);
} else {
    $prizes = $wpdb->get_results($query, ARRAY_A);
}

$competitions = $wpdb->get_results(
    "SELECT DISTINCT sp.competition_code, c.competition_name
     FROM {$wpdb->prefix}wevoi_summit_prizes sp
     LEFT JOIN {$wpdb->prefix}wevoi_competitions c ON sp.competition_code = c.competition_code
     ORDER BY sp.competition_code DESC",
    ARRAY_A
);

$final_competitions = $wpdb->get_results(
    "SELECT competition_code, competition_name
     FROM {$wpdb->prefix}wevoi_competitions
     WHERE competition_type = 'Final'
     ORDER BY competition_code DESC",
    ARRAY_A
);

// Prefer Final competitions in filter; fall back to competitions that already have prizes
$filter_competitions = !empty($final_competitions) ? $final_competitions : $competitions;

$message = isset($_GET['message']) ? intval($_GET['message']) : 0;
$deleted = isset($_GET['deleted']) ? intval($_GET['deleted']) : 0;
$error = isset($_GET['error']) ? sanitize_text_field(wp_unslash($_GET['error'])) : '';

if ($message) {
    switch ($message) {
        case 1:
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Summit prize added successfully.', 'wevoi-competition-manager') . '</p></div>';
            break;
        case 2:
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Summit prize updated successfully.', 'wevoi-competition-manager') . '</p></div>';
            break;
        case 3:
            if ($deleted > 0) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(__('%d summit prize(s) deleted successfully.', 'wevoi-competition-manager'), $deleted)) . '</p></div>';
            } else {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Summit prize deleted successfully.', 'wevoi-competition-manager') . '</p></div>';
            }
            break;
        case 4:
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('An error occurred.', 'wevoi-competition-manager') . '</p></div>';
            break;
    }
}

if ($error) {
    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(urldecode($error)) . '</p></div>';
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Summit Prizes', 'wevoi-competition-manager'); ?>
    </h1>

    <a href="?page=<?php echo esc_attr($_REQUEST['page']); ?>&tab=summit-prizes&action=add"
       class="page-title-action">
        <?php _e('Add New Summit Prize', 'wevoi-competition-manager'); ?>
    </a>

    <hr class="wp-header-end">

    <div class="notice notice-info">
        <p><?php _e('Prize amounts used when Save Rankings runs for Final (Summit) competitions. Each competition needs ranks 1–3 for every age category.', 'wevoi-competition-manager'); ?></p>
    </div>

    <form method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
        <input type="hidden" name="tab" value="summit-prizes">

        <div class="tablenav top">
            <div class="alignleft actions">
                <label for="competition_filter" class="screen-reader-text"><?php _e('Filter by competition', 'wevoi-competition-manager'); ?></label>
                <select name="competition_filter" id="competition_filter">
                    <option value=""><?php _e('All Competitions', 'wevoi-competition-manager'); ?></option>
                    <?php foreach ($filter_competitions as $competition): ?>
                        <option value="<?php echo esc_attr($competition['competition_code']); ?>"
                            <?php selected($competition_filter, $competition['competition_code']); ?>>
                            <?php echo esc_html($competition['competition_code'] . ' - ' . ($competition['competition_name'] ?: 'N/A')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="age_category_filter" class="screen-reader-text"><?php _e('Filter by age category', 'wevoi-competition-manager'); ?></label>
                <select name="age_category_filter" id="age_category_filter">
                    <option value=""><?php _e('All Age Categories', 'wevoi-competition-manager'); ?></option>
                    <?php foreach ($age_categories as $category): ?>
                        <option value="<?php echo esc_attr($category); ?>" <?php selected($age_category_filter, $category); ?>>
                            <?php echo esc_html($category); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'wevoi-competition-manager'); ?>">
            </div>
        </div>
    </form>

    <form method="post" action="">
        <?php wp_nonce_field('bulk-summit-prizes'); ?>

        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-top" class="screen-reader-text"><?php _e('Select bulk action', 'wevoi-competition-manager'); ?></label>
                <select name="action" id="bulk-action-selector-top">
                    <option value="-1"><?php _e('Bulk actions', 'wevoi-competition-manager'); ?></option>
                    <option value="delete"><?php _e('Delete', 'wevoi-competition-manager'); ?></option>
                </select>
                <input type="submit" id="doaction" class="button action" value="<?php esc_attr_e('Apply', 'wevoi-competition-manager'); ?>"
                       onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete the selected prizes?', 'wevoi-competition-manager')); ?>');">
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column">
                        <input id="cb-select-all-1" type="checkbox">
                    </td>
                    <th scope="col"><?php _e('ID', 'wevoi-competition-manager'); ?></th>
                    <th scope="col"><?php _e('Competition', 'wevoi-competition-manager'); ?></th>
                    <th scope="col"><?php _e('Age Category', 'wevoi-competition-manager'); ?></th>
                    <th scope="col"><?php _e('Rank', 'wevoi-competition-manager'); ?></th>
                    <th scope="col"><?php _e('Prize Amount', 'wevoi-competition-manager'); ?></th>
                    <th scope="col"><?php _e('Actions', 'wevoi-competition-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($prizes)): ?>
                    <?php foreach ($prizes as $item): ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="prize[]" value="<?php echo esc_attr($item['id']); ?>">
                            </th>
                            <td><?php echo esc_html($item['id']); ?></td>
                            <td>
                                <strong><?php echo esc_html($item['competition_code']); ?></strong>
                                <?php if (!empty($item['competition_name'])): ?>
                                    <br><span class="description"><?php echo esc_html($item['competition_name']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($item['wevoi_age_category']); ?></td>
                            <td><?php echo esc_html($item['student_rank']); ?></td>
                            <td><strong>£<?php echo esc_html(number_format((float) $item['prize'], 2)); ?></strong></td>
                            <td>
                                <a href="?page=wevoi-competitions&tab=summit-prizes&action=edit&id=<?php echo esc_attr($item['id']); ?>"
                                   class="button button-small"><?php _e('Edit', 'wevoi-competition-manager'); ?></a>
                                <a href="<?php echo esc_url(wp_nonce_url(
                                    'admin.php?page=wevoi-competitions&tab=summit-prizes&action=delete&id=' . $item['id'],
                                    'delete_summit_prize_' . $item['id']
                                )); ?>"
                                   class="button button-small button-link-delete"
                                   onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this prize?', 'wevoi-competition-manager')); ?>');">
                                    <?php _e('Delete', 'wevoi-competition-manager'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr class="no-items">
                        <td class="colspanchange" colspan="7"><?php _e('No summit prizes found.', 'wevoi-competition-manager'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#cb-select-all-1').on('change', function() {
        $('input[name="prize[]"]').prop('checked', $(this).prop('checked'));
    });
});
</script>
