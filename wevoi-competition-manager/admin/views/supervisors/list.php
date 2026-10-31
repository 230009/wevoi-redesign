<?php
// Direct access control
if (!defined('ABSPATH')) {
    exit;
}

// Load List Table class
$type = 'supervisor';
require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/shared/edit-modal.php';
require_once WEVOI_CM_PLUGIN_DIR . 'includes/class-supervisors-list-table.php';
require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/shared/email-modal.php';

// Create table instance
$supervisorsTable = new Supervisors_List_Table();
$supervisorsTable->prepare_items();

// Get countries and schools
global $wpdb;
$countries = $wpdb->get_results("SELECT countryid, countryname FROM {$wpdb->prefix}wevoi_countries ORDER BY countryname");
$schools = $wpdb->get_results("SELECT schoolid, schoolname FROM {$wpdb->prefix}wevoi_schools ORDER BY schoolname");
?>


<div class="wrap wevoi-supervisors-wrap">
    <div class="wevoi-page-title-row">
        <h1 class="wp-heading-inline"><?php _e('Supervisors', 'wevoi-competition-manager'); ?></h1>
        <a href="<?php echo esc_url(add_query_arg([
            'action' => 'export_csv',
            'tab' => 'supervisors',
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
    </div>
    <hr class="wp-header-end">

    <!-- Filtreler -->
    <form method="get" id="supervisors-filter-form">
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page']; ?>">
        <input type="hidden" name="tab" value="supervisors">
        
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
                    <label class="screen-reader-text" for="supervisor-search">
                        <?php _e('Search Supervisors', 'wevoi-competition-manager'); ?>:
                    </label>
                    <input type="search" id="supervisor-search" name="s" value="<?php _admin_search_query(); ?>">
                    <?php submit_button(__('Search', 'wevoi-competition-manager'), 'action', false, false, ['id' => 'search-submit']); ?>
                </p>
            </div>
        </div>

        <!-- Table View -->
        <div id="supervisors-table-view">
            <form method="post">
                <?php 
                wp_nonce_field('bulk-supervisors'); 
                $supervisorsTable->display(); 
                ?>
            </form>
        </div>



<!-- Details Modal Template -->
<div id="supervisor-details-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php _e('Supervisor Details', 'wevoi-competition-manager'); ?></h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <!-- AJAX ile doldurulacak -->
        </div>
    </div>
</div>

<style>
/* View styles */
.supervisors-view {
    display: none;
}
.supervisors-view.active {
    display: block;
}
.supervisors-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    padding: 20px 0;
}

/* Card styles */
.supervisor-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
}
.supervisor-card-header {
    padding: 15px;
    border-bottom: 1px solid #ccd0d4;
}
.supervisor-card-header h3 {
    margin: 0 0 5px 0;
}
.supervisor-card-body {
    padding: 15px;
}
.supervisor-location {
    margin-bottom: 15px;
}
.supervisor-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.supervisor-card-footer {
    padding: 15px;
    border-top: 1px solid #ccd0d4;
    text-align: right;
}

/* Additional styles */
.video-count {
    font-weight: bold;
    color: #2271b1;
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // View toggle handlers
    $('.view-toggle').on('click', function() {
        $('.view-toggle').removeClass('active');
        $(this).addClass('active');
        
        const view = $(this).data('view');
        $('.supervisors-view').removeClass('active');
        $(`#supervisors-${view}-view`).addClass('active');
    });

    // Details modal handlers
// Modal işlemleri
$('.view-details').on('click', function() {
    const supervisorId = $(this).data('supervisor-id');
    const modal = $('#supervisor-details-modal');
    
    // Modal yükleniyor göstergesi
    modal.find('.modal-body').html('<div class="loading">Loading...</div>');
    modal.show();
    
    // AJAX ile detayları getir
    $.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'get_supervisor_details',
            supervisor_id: supervisorId,
            nonce: '<?php echo wp_create_nonce('get_supervisor_details'); ?>',
            security: '<?php echo wp_create_nonce('wevoi_ajax_nonce'); ?>' // İkinci bir nonce ekleyin
        },
        success: function(response) {
            if (response.success) {
                modal.find('.modal-body').html(response.data);
            } else {
                modal.find('.modal-body').html('<div class="error">Error: ' + (response.data || 'Veri yüklenemedi.') + '</div>');
                console.error('AJAX Error:', response);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Request Failed:', status, error, xhr.responseText);
            modal.find('.modal-body').html('<div class="error">Error loading data. Please check console for details.</div>');
        }
    });
});

    // Country-School filter handlers
    $('#filter-by-country').on('change', function() {
        const countryId = $(this).val();
        const schoolSelect = $('#filter-by-school');
        
        schoolSelect.html('<option value=""><?php _e('All Schools', 'wevoi-competition-manager'); ?></option>');
        
        if (countryId) {
            $.post(ajaxurl, {
                action: 'get_schools_by_country',
                country_id: countryId,
                nonce: '<?php echo wp_create_nonce('get_schools_by_country'); ?>'
            }, function(response) {
                if (response.success && response.data) {
                    response.data.forEach(function(school) {
                        schoolSelect.append(
                            $('<option></option>')
                                .val(school.schoolid)
                                .text(school.schoolname)
                        );
                    });
                }
            });
        }
    });

    // Modal close handlers
    $('.modal-close, .modal').on('click', function(e) {
        if (e.target === this) {
            $('#supervisor-details-modal').hide();
        }
    });
});
</script>