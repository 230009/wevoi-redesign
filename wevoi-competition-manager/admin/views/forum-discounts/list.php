<?php
// Direct access control
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Handle bulk delete
if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['discount'])) {
    check_admin_referer('bulk-forum-discounts');

    $discount_ids = array_map('intval', $_POST['discount']);
    $placeholders = implode(',', array_fill(0, count($discount_ids), '%d'));

    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->prefix}wevoi_forum_discounts WHERE id IN ($placeholders)",
        ...$discount_ids
    ));

    if ($deleted) {
        wp_redirect(add_query_arg(['page' => 'wevoi-competitions', 'tab' => 'forum-discounts', 'message' => 3, 'deleted' => $deleted], admin_url('admin.php')));
        exit;
    }
}

// Get all forum discounts with competition names
$discounts = $wpdb->get_results(
    "SELECT fd.*, c.competition_name, c.competition_type
     FROM {$wpdb->prefix}wevoi_forum_discounts fd
     LEFT JOIN {$wpdb->prefix}wevoi_competitions c ON fd.competition_code = c.competition_code
     ORDER BY fd.competition_code DESC, fd.is_active DESC, fd.created_at DESC",
    ARRAY_A
);

// Get Forum competitions for filter
$forum_competitions = $wpdb->get_results(
    "SELECT competition_code, competition_name
     FROM {$wpdb->prefix}wevoi_competitions
     WHERE competition_type = 'Forum'
     ORDER BY competition_code DESC",
    ARRAY_A
);

// Handle messages
$message = isset($_GET['message']) ? intval($_GET['message']) : 0;
$deleted = isset($_GET['deleted']) ? intval($_GET['deleted']) : 0;

if ($message) {
    switch ($message) {
        case 1:
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Forum discount added successfully.', 'wevoi-competition-manager') . '</p></div>';
            break;
        case 2:
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Forum discount updated successfully.', 'wevoi-competition-manager') . '</p></div>';
            break;
        case 3:
            if ($deleted > 0) {
                echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(__('%d forum discount(s) deleted successfully.', 'wevoi-competition-manager'), $deleted) . '</p></div>';
            } else {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Forum discount deleted successfully.', 'wevoi-competition-manager') . '</p></div>';
            }
            break;
        case 4:
            echo '<div class="notice notice-error is-dismissible"><p>' . __('An error occurred.', 'wevoi-competition-manager') . '</p></div>';
            break;
    }
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Forum Discounts', 'wevoi-competition-manager'); ?>
    </h1>

    <a href="?page=<?php echo $_REQUEST['page']; ?>&tab=forum-discounts&action=add"
       class="page-title-action">
        <?php _e('Add New Forum Discount', 'wevoi-competition-manager'); ?>
    </a>

    <hr class="wp-header-end">

    <!-- Filter form -->
    <form method="get">
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page']; ?>">
        <input type="hidden" name="tab" value="forum-discounts">

        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <?php if ($forum_competitions): ?>
                    <label for="competition_filter" class="screen-reader-text"><?php _e('Filter by competition', 'wevoi-competition-manager'); ?></label>
                    <select name="competition_filter" id="competition_filter">
                        <option value=""><?php _e('All Forum Competitions', 'wevoi-competition-manager'); ?></option>
                        <?php foreach ($forum_competitions as $comp): ?>
                            <option value="<?php echo esc_attr($comp->competition_code); ?>"
                                <?php selected(isset($_GET['competition_filter']) ? $_GET['competition_filter'] : '', $comp->competition_code); ?>>
                                <?php echo esc_html($comp->competition_code . ' - ' . $comp->competition_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="status_filter" class="screen-reader-text"><?php _e('Filter by status', 'wevoi-competition-manager'); ?></label>
                    <select name="status_filter" id="status_filter">
                        <option value=""><?php _e('All Statuses', 'wevoi-competition-manager'); ?></option>
                        <option value="1" <?php selected(isset($_GET['status_filter']) ? $_GET['status_filter'] : '', '1'); ?>>
                            <?php _e('Active', 'wevoi-competition-manager'); ?>
                        </option>
                        <option value="0" <?php selected(isset($_GET['status_filter']) ? $_GET['status_filter'] : '', '0'); ?>>
                            <?php _e('Inactive', 'wevoi-competition-manager'); ?>
                        </option>
                    </select>

                    <input type="submit" class="button" value="<?php _e('Filter', 'wevoi-competition-manager'); ?>">
                <?php endif; ?>
            </div>
        </div>
    </form>

    <!-- Table form -->
    <form method="post" action="<?php echo admin_url('admin.php'); ?>">
        <input type="hidden" name="page" value="wevoi-competitions">
        <input type="hidden" name="tab" value="forum-discounts">
        <?php wp_nonce_field('bulk-forum-discounts'); ?>

        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label>
                <select name="action" id="bulk-action-selector-top">
                    <option value="-1">Bulk actions</option>
                    <option value="delete">Delete</option>
                </select>
                <input type="submit" id="doaction" class="button action" value="Apply">
            </div>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column">
                        <input id="cb-select-all-1" type="checkbox">
                    </td>
                    <th scope="col" id="id" class="manage-column column-id">
                        ID
                    </th>
                    <th scope="col" id="competition_code" class="manage-column column-competition_code">
                        Competition Code
                    </th>
                    <th scope="col" id="competition_name" class="manage-column column-competition_name">
                        Competition Name
                    </th>
                    <th scope="col" id="gold_discount" class="manage-column column-gold_discount">
                        Gold Discount (%)
                    </th>
                    <th scope="col" id="silver_discount" class="manage-column column-silver_discount">
                        Silver Discount (%)
                    </th>
                    <th scope="col" id="bronze_discount" class="manage-column column-bronze_discount">
                        Bronze Discount (%)
                    </th>
                    <th scope="col" id="is_active" class="manage-column column-is_active">
                        Status
                    </th>
                    <th scope="col" id="created_at" class="manage-column column-created_at">
                        Created At
                    </th>
                    <th scope="col" id="actions" class="manage-column column-actions">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody id="the-list">
                <?php if (!empty($discounts)): ?>
                    <?php
                    // Apply filters
                    $filtered_discounts = $discounts;
                    if (isset($_GET['competition_filter']) && $_GET['competition_filter'] !== '') {
                        $filtered_discounts = array_filter($filtered_discounts, function($d) {
                            return $d['competition_code'] == $_GET['competition_filter'];
                        });
                    }
                    if (isset($_GET['status_filter']) && $_GET['status_filter'] !== '') {
                        $filtered_discounts = array_filter($filtered_discounts, function($d) {
                            return $d['is_active'] == $_GET['status_filter'];
                        });
                    }
                    ?>
                    <?php foreach ($filtered_discounts as $discount): ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="discount[]" value="<?php echo esc_attr($discount['id']); ?>">
                            </th>
                            <td class="column-id"><?php echo esc_html($discount['id']); ?></td>
                            <td class="column-competition_code"><?php echo esc_html($discount['competition_code']); ?></td>
                            <td class="column-competition_name">
                                <?php echo esc_html($discount['competition_name'] ?: 'N/A'); ?>
                            </td>
                            <td class="column-gold_discount">
                                <strong><?php echo esc_html(number_format($discount['gold_discount'], 2)); ?>%</strong>
                            </td>
                            <td class="column-silver_discount">
                                <strong><?php echo esc_html(number_format($discount['silver_discount'], 2)); ?>%</strong>
                            </td>
                            <td class="column-bronze_discount">
                                <strong><?php echo esc_html(number_format($discount['bronze_discount'], 2)); ?>%</strong>
                            </td>
                            <td class="column-is_active">
                                <?php if ($discount['is_active']): ?>
                                    <span class="status-badge status-active" style="background: #00a32a; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px;">
                                        <?php _e('Active', 'wevoi-competition-manager'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-inactive" style="background: #dba617; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px;">
                                        <?php _e('Inactive', 'wevoi-competition-manager'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="column-created_at">
                                <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($discount['created_at']))); ?>
                            </td>
                            <td class="column-actions">
                                <a href="?page=wevoi-competitions&tab=forum-discounts&action=edit&id=<?php echo esc_attr($discount['id']); ?>" class="button button-small"><?php _e('Edit', 'wevoi-competition-manager'); ?></a>
                                <a href="<?php echo wp_nonce_url('admin.php?page=wevoi-competitions&tab=forum-discounts&action=delete&id=' . $discount['id'], 'delete_forum_discount_' . $discount['id']); ?>"
                                   class="button button-small button-link-delete"
                                   onclick="return confirm('<?php _e('Are you sure you want to delete this forum discount?', 'wevoi-competition-manager'); ?>');">
                                    <?php _e('Delete', 'wevoi-competition-manager'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr class="no-items">
                        <td class="colspanchange" colspan="10"><?php _e('No forum discounts found.', 'wevoi-competition-manager'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-bottom" class="screen-reader-text">Select bulk action</label>
                <select name="action2" id="bulk-action-selector-bottom">
                    <option value="-1">Bulk actions</option>
                    <option value="delete">Delete</option>
                </select>
                <input type="submit" id="doaction2" class="button action" value="Apply">
            </div>
        </div>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Handle bulk actions
    $('.bulkactions select[name="action"], .bulkactions select[name="action2"]').on('change', function() {
        if ($(this).val() === 'delete') {
            if (!confirm('<?php _e('Are you sure you want to delete the selected forum discounts?', 'wevoi-competition-manager'); ?>')) {
                $(this).val('');
                return false;
            }
        }
    });

    // Select all checkbox
    $('#cb-select-all-1').on('change', function() {
        $('tbody input[type="checkbox"]').prop('checked', $(this).prop('checked'));
    });
});
</script>
