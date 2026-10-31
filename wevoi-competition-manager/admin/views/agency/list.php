<?php
if (!defined('ABSPATH')) {
    exit;
}

$type = 'agency';
require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/shared/edit-modal.php';
require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/shared/email-modal.php';
require_once WEVOI_CM_PLUGIN_DIR . 'includes/class-agency-list-table.php';

$agencyTable = new Agency_List_Table();
$agencyTable->prepare_items();

global $wpdb;
$countries = $wpdb->get_results("SELECT countryid, countryname FROM {$wpdb->prefix}wevoi_countries ORDER BY countryname");
$schools = $wpdb->get_results("SELECT schoolid, schoolname FROM {$wpdb->prefix}wevoi_schools ORDER BY schoolname");
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Agencies', 'wevoi-competition-manager'); ?></h1>

    <a href="<?php echo esc_url(add_query_arg([
        'action' => 'export_csv',
        'tab' => 'agency',
        'page' => 'wevoi-competitions',
        'country' => isset($_GET['country']) ? sanitize_text_field($_GET['country']) : '',
        'school' => isset($_GET['school']) ? sanitize_text_field($_GET['school']) : '',
        's' => isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '',
        'orderby' => isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'user_registered',
        'order' => isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC'
    ], admin_url('admin.php'))); ?>" class="page-title-action export-csv-button">
        <span class="dashicons dashicons-media-spreadsheet" style="margin-right: 5px;"></span>
        <?php _e('Export CSV', 'wevoi-competition-manager'); ?>
    </a>

    <hr class="wp-header-end">

    <form method="get" id="agency-filter-form">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
        <input type="hidden" name="tab" value="agency">

        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="country" id="filter-by-country">
                    <option value=""><?php _e('All Countries', 'wevoi-competition-manager'); ?></option>
                    <?php foreach ($countries as $country): ?>
                        <option value="<?php echo esc_attr($country->countryid); ?>"
                            <?php selected(isset($_GET['country']) ? $_GET['country'] : '', $country->countryid); ?>>
                            <?php echo esc_html($country->countryname); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="school" id="filter-by-school">
                    <option value=""><?php _e('All Schools', 'wevoi-competition-manager'); ?></option>
                    <?php if (isset($_GET['country']) && !empty($_GET['country'])):
                        $filtered_schools = $wpdb->get_results($wpdb->prepare(
                            "SELECT schoolid, schoolname
                             FROM {$wpdb->prefix}wevoi_schools
                             WHERE countryid = %d
                             ORDER BY schoolname",
                            intval($_GET['country'])
                        ));
                        foreach ($filtered_schools as $school): ?>
                            <option value="<?php echo esc_attr($school->schoolid); ?>"
                                <?php selected(isset($_GET['school']) ? $_GET['school'] : '', $school->schoolid); ?>>
                                <?php echo esc_html($school->schoolname); ?>
                            </option>
                        <?php endforeach;
                    endif; ?>
                </select>

                <?php submit_button(__('Filter', 'wevoi-competition-manager'), 'action', false, false); ?>
            </div>

            <div class="alignright">
                <p class="search-box">
                    <label class="screen-reader-text" for="agency-search"><?php _e('Search Agencies', 'wevoi-competition-manager'); ?>:</label>
                    <input type="search" id="agency-search" name="s" value="<?php _admin_search_query(); ?>">
                    <?php submit_button(__('Search', 'wevoi-competition-manager'), 'action', false, false, ['id' => 'search-submit']); ?>
                </p>
            </div>
        </div>

        <div id="agency-table-view" class="agency-table-view">
            <form method="post">
                <?php
                wp_nonce_field('bulk-agencies');
                $agencyTable->display();
                ?>
            </form>
        </div>
    </form>

    <div id="agency-details-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?php _e('Agency Details', 'wevoi-competition-manager'); ?></h2>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body"></div>
        </div>
    </div>
</div>

<style>
.agency-table-view .wp-list-table .column-registration_date { width: 140px; }
.agency-table-view .wp-list-table .column-agency_details { width: 180px; }
.agency-table-view .wp-list-table .column-agency_phone { width: 120px; }
.agency-table-view .wp-list-table .column-country { width: 120px; }
.agency-table-view .wp-list-table .column-school { width: 140px; }
.agency-table-view .wp-list-table .column-total_students { width: 80px; }
.agency-table-view .wp-list-table .column-video_submissions { width: 100px; }
.agency-table-view .wp-list-table .column-pending_videos { width: 100px; }
.video-count { font-weight: bold; color: #2271b1; }
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#filter-by-country').on('change', function() {
        var countryId = $(this).val();
        var schoolSelect = $('#filter-by-school');
        schoolSelect.html('<option value=""><?php echo esc_js(__('All Schools', 'wevoi-competition-manager')); ?></option>');
        if (countryId) {
            $.post(ajaxurl, {
                action: 'get_schools_by_country',
                country_id: countryId,
                nonce: '<?php echo wp_create_nonce('get_schools_by_country'); ?>'
            }, function(response) {
                if (response.success && response.data) {
                    response.data.forEach(function(school) {
                        schoolSelect.append($('<option></option>').val(school.schoolid).text(school.schoolname));
                    });
                }
            });
        }
    });

    $('.modal-close, #agency-details-modal').on('click', function(e) {
        if (e.target === this) {
            $('#agency-details-modal').hide();
        }
    });
});
</script>
