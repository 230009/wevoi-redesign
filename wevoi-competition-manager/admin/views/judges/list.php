<?php
// Direct access control
if (!defined('ABSPATH')) {
    exit;
}

// Load List Table class
$type = 'judge'; 
require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/shared/edit-modal.php';
require_once WEVOI_CM_PLUGIN_DIR . 'includes/class-judges-list-table.php';
require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/shared/email-modal.php';

// Create table instance
$judgesTable = new Judges_List_Table();
$judgesTable->prepare_items();

// Get countries and schools
global $wpdb;
$countries = $wpdb->get_results("SELECT countryid, countryname FROM {$wpdb->prefix}wevoi_countries ORDER BY countryname");
$schools = $wpdb->get_results("SELECT schoolid, schoolname FROM {$wpdb->prefix}wevoi_schools ORDER BY schoolname");
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Judges', 'wevoi-competition-manager'); ?></h1>
    
    <a href="<?php echo esc_url(add_query_arg([
        'action' => 'export_csv',
        'tab' => 'judges',
        'page' => 'wevoi-competitions',
        'country' => isset($_GET['country']) ? sanitize_text_field($_GET['country']) : '',
        'school' => isset($_GET['school']) ? sanitize_text_field($_GET['school']) : '',
        'evaluation_status' => isset($_GET['evaluation_status']) ? sanitize_text_field($_GET['evaluation_status']) : '',
        's' => isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '',
        'orderby' => isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'user_registered',
        'order' => isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC'
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
                
                <select name="evaluation_status" id="filter-by-evaluation-status">
    <option value=""><?php _e('All Statuses', 'wevoi-competition-manager'); ?></option>
    <option value="not_started" <?php selected(isset($_GET['evaluation_status']) ? $_GET['evaluation_status'] : '', 'not_started'); ?>>
        <?php _e('Not Started', 'wevoi-competition-manager'); ?>
    </option>
    <option value="evaluating" <?php selected(isset($_GET['evaluation_status']) ? $_GET['evaluation_status'] : '', 'evaluating'); ?>>
        <?php _e('Evaluating', 'wevoi-competition-manager'); ?>
    </option>
    <option value="completed" <?php selected(isset($_GET['evaluation_status']) ? $_GET['evaluation_status'] : '', 'completed'); ?>>
        <?php _e('Completed', 'wevoi-competition-manager'); ?>
    </option>
</select>
                
                
                
            </div>

            <div class="alignright">
                <p class="search-box">
                    <label class="screen-reader-text" for="judge-search">
                        <?php _e('Search Judges', 'wevoi-competition-manager'); ?>:
                    </label>
                    <input type="search" id="judge-search" name="s" value="<?php _admin_search_query(); ?>">
                    <?php submit_button(__('Search', 'wevoi-competition-manager'), 'action', false, false, ['id' => 'search-submit']); ?>
                </p>
            </div>
        </div>

        <!-- Table View -->
        <div id="judges-table-view" class="judges-view active">
            <form method="post">
                <?php 
                // Bulk action nonce
                wp_nonce_field('bulk-judges'); 
                $judgesTable->display(); 
                ?>
            </form>
        </div>

        <!-- Card View -->
        <div id="judges-card-view" class="judges-view">
            <div class="judges-grid">
                <?php 
                if ($judgesTable->items): 
                    foreach ($judgesTable->items as $judge): ?>
                        <div class="judge-card">
                            <div class="judge-card-header">
                                <h3><?php echo esc_html(wevoi_proper_name($judge->display_name)); ?></h3>
<div class="judge-email">
    <a href="#" class="email-trigger" data-email="<?php echo esc_attr($judge->user_email); ?>">
        <?php echo esc_html($judge->user_email); ?>
    </a>
</div>
                            </div>
                            <div class="judge-card-body">
                                <div class="judge-location">
                                    <?php if (!empty($judge->countryname)): ?>
                                        <div class="country" title="ID: <?php echo esc_attr($judge->countryid); ?>">
                                            <span class="dashicons dashicons-location"></span>
                                            <?php echo esc_html($judge->countryname); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($judge->schoolname)): ?>
                                        <div class="school" title="ID: <?php echo esc_attr($judge->schoolid); ?>">
                                            <span class="dashicons dashicons-building"></span>
                                            <?php echo esc_html($judge->schoolname); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="judge-meta">
                                    <div class="registration-date">
                                        <strong><?php _e('Registered:', 'wevoi-competition-manager'); ?></strong>
                                        <?php echo date_i18n(get_option('date_format'), strtotime($judge->user_registered)); ?>
                                    </div>
                                    <div class="assignments-count">
                                        <strong><?php _e('Assignments:', 'wevoi-competition-manager'); ?></strong>
                                        <?php echo intval($judge->assignment_count); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="judge-card-footer">
                                <button type="button" class="button button-secondary view-details" 
                                        data-judge-id="<?php echo esc_attr($judge->ID); ?>">
                                    <?php _e('View Details', 'wevoi-competition-manager'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach;
                endif; 
                ?>
            </div>
        </div>
    </form>
</div>

<!-- Details Modal Template -->
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

<!-- Email Modal Template -->
<?php require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/shared/email-modal.php'; ?>

<style>
/* View stilleri */
.judges-view {
    display: none;
}
.judges-view.active {
    display: block;
}
.judges-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    padding: 20px 0;
}

/* Modal stilleri */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    z-index: 159900;
}
.modal-content {
    position: relative;
    background: #fff;
    margin: 30px auto;
    max-width: 800px;
    max-height: calc(100vh - 60px);
    overflow-y: auto;
}
.modal-header {
    padding: 15px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-header h2 {
    margin: 0;
    padding: 0;
}
.modal-body {
    padding: 15px;
}
.modal-close {
    cursor: pointer;
    background: none;
    border: none;
    font-size: 24px;
    color: #666;
}

/* Card stilleri */
.judge-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
}
.judge-card-header {
    padding: 15px;
    border-bottom: 1px solid #ccd0d4;
}
.judge-card-header h3 {
    margin: 0 0 5px 0;
}
.judge-card-body {
    padding: 15px;
}
.judge-location {
    margin-bottom: 15px;
}
.judge-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.judge-card-footer {
    padding: 15px;
    border-top: 1px solid #ccd0d4;
    text-align: right;
}



</style>

<script type="text/javascript">
jQuery(document).ready(function($) {

    // Modal işlemleri
    $('.view-details').on('click', function() {
        const judgeId = $(this).data('judge-id');
        const modal = $('#judge-details-modal');
        
        // AJAX ile detayları getir
        $.post(ajaxurl, {
            action: 'get_judge_details',
            judge_id: judgeId,
            nonce: '<?php echo wp_create_nonce('get_judge_details'); ?>'
        }, function(response) {
            if (response.success) {
                modal.find('.modal-body').html(response.data);
                modal.show();
            }
        });
    });

    $('.modal-close, .modal').on('click', function(e) {
        if (e.target === this) {
            $('#judge-details-modal').hide();
        }
    });

    // Ülke seçimine göre okul listesini güncelle
    $('#filter-by-country').on('change', function() {
        const countryId = $(this).val();
        const schoolSelect = $('#filter-by-school');
        
        // Okul seçimini sıfırla
        schoolSelect.html('<option value=""><?php _e('All Schools', 'wevoi-competition-manager'); ?></option>');
        
        if (countryId) {
            // AJAX ile okulları getir
            $.post(ajaxurl, {
                action: 'get_schools_by_country',
                country_id: countryId,
                nonce: '<?php echo wp_create_nonce('get_schools_by_country'); ?>'
            }, function(response) {
                if (response.success && response.data) {
                    // Okulları listele
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
});
</script>