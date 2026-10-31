<?php
/**
 * Judges List View
 *
 * @package Wevoi_Competition_Manager
 * @subpackage Judges
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

wp_nonce_field('wevoi_ajax_nonce', 'wevoi_nonce');

// Get countries and schools from database
global $wpdb;
$countries = $wpdb->get_results("SELECT countryid, countryname FROM {$wpdb->prefix}wevoi_countries ORDER BY countryname");
$schools = $wpdb->get_results("SELECT schoolid, schoolname FROM {$wpdb->prefix}wevoi_schools ORDER BY schoolname");

// Get judges from service
$page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$country = isset($_GET['country']) ? intval($_GET['country']) : 0;
$school = isset($_GET['school']) ? intval($_GET['school']) : 0;
$evaluation_status = isset($_GET['evaluation_status']) ? sanitize_text_field($_GET['evaluation_status']) : '';
$orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'user_registered';
$order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';

$result = Wevoi_Judge_Module::get_instance()->get_service()->get_judges_for_list_table([
    'per_page' => 20,
    'page' => $page,
    'search' => $search,
    'country' => $country,
    'school' => $school,
    'evaluation_status' => $evaluation_status,
    'orderby' => $orderby,
    'order' => $order
]);

$judges = $result['items'];
$total_items = $result['total_items'];
$total_pages = $result['total_pages'];
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Judges', 'wevoi-competition-manager'); ?></h1>
    
    <a href="<?php echo esc_url(add_query_arg([
        'action' => 'export_csv',
        'tab' => 'judges',
        'page' => 'wevoi-competitions',
        'country' => $country,
        'school' => $school,
        'evaluation_status' => $evaluation_status,
        's' => $search,
        'orderby' => $orderby,
        'order' => $order
    ], admin_url('admin.php'))); ?>" class="page-title-action export-csv-button">
        <span class="dashicons dashicons-media-spreadsheet" style="margin-right: 5px;"></span>
        <?php _e('Export CSV', 'wevoi-competition-manager'); ?>
    </a>
    
    <hr class="wp-header-end">

    <!-- Filters -->
    <form method="get" id="judges-filter-form">
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page']; ?>">
        <input type="hidden" name="tab" value="judges">
        <?php if (isset($_REQUEST['paged'])): ?>
            <input type="hidden" name="paged" value="<?php echo intval($_REQUEST['paged']); ?>">
        <?php endif; ?>
        
        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="country" id="filter-by-country">
                    <option value=""><?php _e('All Countries', 'wevoi-competition-manager'); ?></option>
                    <?php foreach ($countries as $country_item): ?>
                        <option value="<?php echo esc_attr($country_item->countryid); ?>" 
                            <?php selected($country, $country_item->countryid); ?>>
                            <?php echo esc_html($country_item->countryname); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="school" id="filter-by-school">
                    <option value=""><?php _e('All Schools', 'wevoi-competition-manager'); ?></option>
                    <?php if ($country): 
                        $filtered_schools = $wpdb->get_results($wpdb->prepare(
                            "SELECT schoolid, schoolname 
                             FROM {$wpdb->prefix}wevoi_schools 
                             WHERE countryid = %d 
                             ORDER BY schoolname",
                            $country
                        ));
                        foreach ($filtered_schools as $school_item): ?>
                            <option value="<?php echo esc_attr($school_item->schoolid); ?>"
                                <?php selected($school, $school_item->schoolid); ?>>
                                <?php echo esc_html($school_item->schoolname); ?>
                            </option>
                        <?php endforeach;
                    endif; ?>
                </select>
                
                <select name="evaluation_status" id="filter-by-evaluation-status">
                    <option value=""><?php _e('All Statuses', 'wevoi-competition-manager'); ?></option>
                    <option value="not_started" <?php selected($evaluation_status, 'not_started'); ?>>
                        <?php _e('Not Started', 'wevoi-competition-manager'); ?>
                    </option>
                    <option value="evaluating" <?php selected($evaluation_status, 'evaluating'); ?>>
                        <?php _e('Evaluating', 'wevoi-competition-manager'); ?>
                    </option>
                    <option value="completed" <?php selected($evaluation_status, 'completed'); ?>>
                        <?php _e('Completed', 'wevoi-competition-manager'); ?>
                    </option>
                </select>

                <?php submit_button(__('Filter', 'wevoi-competition-manager'), 'action', false, false); ?>
            </div>

            <div class="alignright">
                <p class="search-box">
                    <label class="screen-reader-text" for="judge-search">
                        <?php _e('Search Judges', 'wevoi-competition-manager'); ?>:
                    </label>
                    <input type="search" id="judge-search" name="s" value="<?php echo esc_attr($search); ?>">
                    <?php submit_button(__('Search', 'wevoi-competition-manager'), 'action', false, false, ['id' => 'search-submit']); ?>
                </p>
            </div>
        </div>

        <!-- Judges Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all-1">
                    </th>
                    <th scope="col" class="manage-column column-registration_date sortable <?php echo $orderby === 'user_registered' ? esc_attr($order) : ''; ?>">
                        <a href="<?php echo add_query_arg(['orderby' => 'user_registered', 'order' => $orderby === 'user_registered' && $order === 'asc' ? 'desc' : 'asc']); ?>">
                            <span><?php _e('Registration Date', 'wevoi-competition-manager'); ?></span>
                            <span class="sorting-indicator"></span>
                        </a>
                    </th>
                    <th scope="col" class="manage-column column-judge_details sortable <?php echo $orderby === 'display_name' ? esc_attr($order) : ''; ?>">
                        <a href="<?php echo add_query_arg(['orderby' => 'display_name', 'order' => $orderby === 'display_name' && $order === 'asc' ? 'desc' : 'asc']); ?>">
                            <span><?php _e('Judge Details', 'wevoi-competition-manager'); ?></span>
                            <span class="sorting-indicator"></span>
                        </a>
                    </th>
                    <th scope="col" class="manage-column column-location">
                        <?php _e('Country & School', 'wevoi-competition-manager'); ?>
                    </th>
                    <th scope="col" class="manage-column column-assignments sortable <?php echo $orderby === 'evaluated_count' ? esc_attr($order) : ''; ?>">
                        <a href="<?php echo add_query_arg(['orderby' => 'evaluated_count', 'order' => $orderby === 'evaluated_count' && $order === 'asc' ? 'desc' : 'asc']); ?>">
                            <span><?php _e('Evaluations', 'wevoi-competition-manager'); ?> <span class="assignment-help"><?php _e('(Evaluated/Assigned)', 'wevoi-competition-manager'); ?></span></span>
                            <span class="sorting-indicator"></span>
                        </a>
                    </th>
                    <th scope="col" class="manage-column column-actions">
                        <?php _e('Actions', 'wevoi-competition-manager'); ?>
                    </th>
                </tr>
            </thead>
            
            <tbody>
                <?php if (empty($judges)): ?>
                    <tr>
                        <td colspan="6"><?php _e('No judges found.', 'wevoi-competition-manager'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($judges as $judge): ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="judges[]" value="<?php echo esc_attr($judge->id); ?>">
                            </th>
                            <td>
                                <?php echo esc_html($judge->get_registration_date()); ?>
                            </td>
                            <td>
                                <div class="judge-name"><?php echo esc_html($judge->get_name()); ?></div>
                                <div class="judge-email">
                                    <a href="#" class="email-trigger" data-email="<?php echo esc_attr($judge->get_email()); ?>">
                                        <?php echo esc_html($judge->get_email()); ?>
                                    </a>
                                </div>
                            </td>
                            <td>
                                <?php if ($judge->country_name): ?>
                                    <div title="ID: <?php echo esc_attr($judge->country_id); ?>"><?php echo esc_html($judge->country_name); ?></div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                                
                                <?php if ($judge->school_name): ?>
                                    <div title="ID: <?php echo esc_attr($judge->school_id); ?>"><?php echo esc_html($judge->school_name); ?></div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $status = $judge->get_evaluation_status();
                                $status_text = $judge->get_evaluation_status_text();
                                ?>
                                <span class="assignment-count <?php echo esc_attr($status); ?>">
                                    <?php echo esc_html($judge->evaluated_count . '/' . $judge->assignment_count); ?>
                                </span>
                                <?php if ($status_text): ?>
                                    <span class="evaluation-status <?php echo esc_attr($status); ?>">
                                        (<?php echo esc_html($status_text); ?>)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small edit-details" data-judge-id="<?php echo esc_attr($judge->id); ?>">
                                    <?php _e('Edit', 'wevoi-competition-manager'); ?>
                                </button>
                                
                                <button type="button" class="button button-small view-details" data-judge-id="<?php echo esc_attr($judge->id); ?>">
                                    <?php _e('View Details', 'wevoi-competition-manager'); ?>
                                </button>
                                
                                <a href="<?php echo wp_nonce_url(
                                    sprintf(
                                        'admin.php?page=wevoi-competitions&tab=judges&action=delete&judge=%d',
                                        $judge->id
                                    ),
                                    'delete_judge_' . $judge->id
                                ); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this judge?', 'wevoi-competition-manager'); ?>');">
                                    <?php _e('Delete', 'wevoi-competition-manager'); ?>
                                </a>
                                
                                <button type="button" class="button button-small email-compose" data-email="<?php echo esc_attr($judge->get_email()); ?>">
                                    <span class="dashicons dashicons-email"></span>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            
            <tfoot>
                <tr>
                    <th scope="col" class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all-2">
                    </th>
                    <th scope="col" class="manage-column column-registration_date sortable <?php echo $orderby === 'user_registered' ? esc_attr($order) : ''; ?>">
                        <a href="<?php echo add_query_arg(['orderby' => 'user_registered', 'order' => $orderby === 'user_registered' && $order === 'asc' ? 'desc' : 'asc']); ?>">
                            <span><?php _e('Registration Date', 'wevoi-competition-manager'); ?></span>
                            <span class="sorting-indicator"></span>
                        </a>
                    </th>
                    <th scope="col" class="manage-column column-judge_details sortable <?php echo $orderby === 'display_name' ? esc_attr($order) : ''; ?>">
                        <a href="<?php echo add_query_arg(['orderby' => 'display_name', 'order' => $orderby === 'display_name' && $order === 'asc' ? 'desc' : 'asc']); ?>">
                            <span><?php _e('Judge Details', 'wevoi-competition-manager'); ?></span>
                            <span class="sorting-indicator"></span>
                        </a>
                    </th>
                    <th scope="col" class="manage-column column-location">
                        <?php _e('Country & School', 'wevoi-competition-manager'); ?>
                    </th>
                    <th scope="col" class="manage-column column-assignments sortable <?php echo $orderby === 'evaluated_count' ? esc_attr($order) : ''; ?>">
                        <a href="<?php echo add_query_arg(['orderby' => 'evaluated_count', 'order' => $orderby === 'evaluated_count' && $order === 'asc' ? 'desc' : 'asc']); ?>">
                            <span><?php _e('Evaluations', 'wevoi-competition-manager'); ?> <span class="assignment-help"><?php _e('(Evaluated/Assigned)', 'wevoi-competition-manager'); ?></span></span>
                            <span class="sorting-indicator"></span>
                        </a>
                    </th>
                    <th scope="col" class="manage-column column-actions">
                        <?php _e('Actions', 'wevoi-competition-manager'); ?>
                    </th>
                </tr>
            </tfoot>
        </table>
        
        <div class="tablenav bottom">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-bottom" class="screen-reader-text"><?php _e('Select bulk action', 'wevoi-competition-manager'); ?></label>
                <select name="action2" id="bulk-action-selector-bottom">
                    <option value="-1"><?php _e('Bulk Actions', 'wevoi-competition-manager'); ?></option>
                    <option value="delete"><?php _e('Delete', 'wevoi-competition-manager'); ?></option>
                </select>
                <?php submit_button(__('Apply', 'wevoi-competition-manager'), 'action', '', false); ?>
            </div>
            
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(
                        _n('%s item', '%s items', $total_items, 'wevoi-competition-manager'),
                        number_format_i18n($total_items)
                    ); ?>
                </span>
                
                <?php if ($total_pages > 1): ?>
                    <span class="pagination-links">
                        <?php
                        $page_links = paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $page,
                        ));

                        echo $page_links;
                        ?>
                    </span>
                <?php endif; ?>
            </div>
            <br class="clear">
        </div>
    </form>
</div>

<!-- Details Modal -->
<div id="judge-details-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php _e('Judge Details', 'wevoi-competition-manager'); ?></h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <!-- AJAX ile doldurulacak -->
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="judge-edit-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php _e('Edit Judge', 'wevoi-competition-manager'); ?></h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <!-- AJAX ile doldurulacak -->
        </div>
    </div>
</div>

<!-- Email Modal -->
<?php require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/shared/email-modal.php'; ?>