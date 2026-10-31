<?php
// Direct access control
if (!defined('ABSPATH')) {
    exit;
}

// Load List Table class
require_once WEVOI_CM_PLUGIN_DIR . 'admin/class-topics-list-table.php';

// Create table instance
$topicsTable = new Topics_List_Table();
$topicsTable->prepare_items();

// Get competitions for filter dropdown
global $wpdb;
$competitions = $wpdb->get_results(
    "SELECT competition_code, competition_name FROM {$wpdb->prefix}wevoi_competitions ORDER BY competition_code DESC",
    ARRAY_A
);

// Age categories from wpie_wevoi_age_categories
$age_categories = wevoi_get_age_categories();

// Handle messages
$message = isset($_GET['message']) ? intval($_GET['message']) : 0;
$deleted = isset($_GET['deleted']) ? intval($_GET['deleted']) : 0;
$error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';

if ($message) {
    switch ($message) {
        case 1:
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Topic added successfully.', 'wevoi-competition-manager') . '</p></div>';
            break;
        case 2:
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Topic updated successfully.', 'wevoi-competition-manager') . '</p></div>';
            break;
        case 3:
            if ($deleted > 0) {
                echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(__('%d topic(s) deleted successfully.', 'wevoi-competition-manager'), $deleted) . '</p></div>';
            } else {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Topic deleted successfully.', 'wevoi-competition-manager') . '</p></div>';
            }
            break;
        case 4:
            echo '<div class="notice notice-error is-dismissible"><p>' . __('An error occurred.', 'wevoi-competition-manager') . '</p></div>';
            break;
    }
}

if ($error) {
    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(urldecode($error)) . '</p></div>';
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php _e('Topics', 'wevoi-competition-manager'); ?>
    </h1>

    <a href="?page=<?php echo $_REQUEST['page']; ?>&tab=topics&action=add"
       class="page-title-action">
        <?php _e('Add New Topic', 'wevoi-competition-manager'); ?>
    </a>

    <hr class="wp-header-end">

    <!-- Search and filter form -->
    <form method="get">
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page']; ?>">
        <input type="hidden" name="tab" value="topics">

        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <label for="competition_filter" class="screen-reader-text"><?php _e('Filter by competition', 'wevoi-competition-manager'); ?></label>
                <select name="competition_filter" id="competition_filter">
                    <option value=""><?php _e('All Competitions', 'wevoi-competition-manager'); ?></option>
                    <?php foreach ($competitions as $competition): ?>
                        <option value="<?php echo esc_attr($competition['competition_code']); ?>"
                            <?php selected(isset($_GET['competition_filter']) ? $_GET['competition_filter'] : '', $competition['competition_code']); ?>>
                            <?php echo esc_html($competition['competition_code'] . ' - ' . $competition['competition_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="age_category_filter" class="screen-reader-text"><?php _e('Filter by age category', 'wevoi-competition-manager'); ?></label>
                <select name="age_category_filter" id="age_category_filter">
                    <option value=""><?php _e('All Age Categories', 'wevoi-competition-manager'); ?></option>
                    <?php foreach ($age_categories as $category): ?>
                        <option value="<?php echo esc_attr($category); ?>"
                            <?php selected(isset($_GET['age_category_filter']) ? $_GET['age_category_filter'] : '', $category); ?>>
                            <?php echo esc_html($category); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="submit" class="button" value="<?php _e('Filter', 'wevoi-competition-manager'); ?>">
            </div>

            <div class="alignright actions">
                <label for="topic-search-input" class="screen-reader-text"><?php _e('Search topics', 'wevoi-competition-manager'); ?></label>
                <input type="search" id="topic-search-input" name="s" value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>" placeholder="<?php _e('Search topics...', 'wevoi-competition-manager'); ?>">
                <input type="submit" class="button" value="<?php _e('Search', 'wevoi-competition-manager'); ?>">
            </div>
        </div>
    </form>

    <!-- Simple Table Display -->
    <form method="post" action="<?php echo admin_url('admin.php'); ?>">
        <input type="hidden" name="page" value="wevoi-competitions">
        <input type="hidden" name="tab" value="topics">
        <?php wp_nonce_field('bulk-topics'); ?>

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
                    <th scope="col" id="id" class="manage-column column-id sortable desc">
                        <a href="?page=wevoi-competitions&tab=topics&orderby=id&order=asc">
                            <span>ID</span>
                            <span class="sorting-indicators">
                                <span class="sorting-indicator asc" aria-hidden="true"></span>
                            </span>
                        </a>
                    </th>
                    <th scope="col" id="competition_code" class="manage-column column-competition_code sortable">
                        <a href="?page=wevoi-competitions&tab=topics&orderby=competition_code&order=asc">
                            <span>Competition Code</span>
                            <span class="sorting-indicators">
                                <span class="sorting-indicator asc" aria-hidden="true"></span>
                            </span>
                        </a>
                    </th>
                    <th scope="col" id="competition_name" class="manage-column column-competition_name">
                        Competition Name
                    </th>
                    <th scope="col" id="wevoi_age_category" class="manage-column column-wevoi_age_category sortable">
                        <a href="?page=wevoi-competitions&tab=topics&orderby=wevoi_age_category&order=asc">
                            <span>Age Category</span>
                            <span class="sorting-indicators">
                                <span class="sorting-indicator asc" aria-hidden="true"></span>
                            </span>
                        </a>
                    </th>
                    <th scope="col" id="topic" class="manage-column column-topic sortable">
                        <a href="?page=wevoi-competitions&tab=topics&orderby=topic&order=asc">
                            <span>Topic</span>
                            <span class="sorting-indicators">
                                <span class="sorting-indicator asc" aria-hidden="true"></span>
                            </span>
                        </a>
                    </th>
                    <th scope="col" id="actions" class="manage-column column-actions">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody id="the-list">
                <?php if (!empty($topicsTable->items)): ?>
                    <?php foreach ($topicsTable->items as $item): ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="topic[]" value="<?php echo esc_attr($item['id']); ?>">
                            </th>
                            <td class="column-id"><?php echo esc_html($item['id']); ?></td>
                            <td class="column-competition_code"><?php echo esc_html($item['competition_code']); ?></td>
                            <td class="column-competition_name"><?php echo esc_html($item['competition_name'] ?: 'N/A'); ?></td>
                            <td class="column-wevoi_age_category"><?php echo esc_html($item['wevoi_age_category']); ?></td>
                            <td class="column-topic"><?php echo esc_html(wp_trim_words($item['topic'], 10)); ?></td>
                            <td class="column-actions">
                                <a href="?page=wevoi-competitions&tab=topics&action=edit&id=<?php echo esc_attr($item['id']); ?>" class="button button-small">Edit</a>
                                <a href="<?php echo wp_nonce_url('admin.php?page=wevoi-competitions&tab=topics&action=delete&id=' . $item['id'], 'delete_topic_' . $item['id']); ?>" class="button button-small button-link-delete" onclick="return confirm('Are you sure you want to delete this topic?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr class="no-items">
                        <td class="colspanchange" colspan="7">No topics found.</td>
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
    $('.bulkactions select[name="action"]').on('change', function() {
        if ($(this).val() === 'delete') {
            if (!confirm('<?php _e('Are you sure you want to delete the selected topics?', 'wevoi-competition-manager'); ?>')) {
                $(this).val('');
                return false;
            }
        }
    });

    // Handle individual delete links
    $('.delete-topic').on('click', function() {
        return confirm('<?php _e('Are you sure you want to delete this topic?', 'wevoi-competition-manager'); ?>');
    });
});
</script>
