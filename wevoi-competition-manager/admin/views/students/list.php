<?php
// Direct access kontrolü
if (!defined('ABSPATH')) {
    exit;
}

// List Table sınıfını yükle
require_once WEVOI_CM_PLUGIN_DIR . 'admin/class-students-list-table.php';
require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/shared/email-modal.php';

// Tablo örneğini oluştur
$studentsTable = new Students_List_Table();
$studentsTable->prepare_items();

// Toplam öğrenci sayısını al
$total_items = $studentsTable->get_total_items();

// Ülke ve okul listelerini al
global $wpdb;
$countries = $wpdb->get_results("SELECT countryid, countryname FROM {$wpdb->prefix}wevoi_countries ORDER BY countryname");
$schools = $wpdb->get_results("SELECT schoolid, schoolname FROM {$wpdb->prefix}wevoi_schools ORDER BY schoolname");
?>

<style>
.tablenav .tablenav-pages {
    margin: 0;
}
.tablenav-pages .pagination-links {
    margin-left: 10px;
}
</style>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Students', 'wevoi-competition-manager'); ?></h1>
    
    <a href="<?php echo esc_url(add_query_arg([
        'action' => 'export_csv',
        'tab' => 'students',
        'page' => 'wevoi-competitions',
        'country' => isset($_GET['country']) ? sanitize_text_field($_GET['country']) : '',
        'school' => isset($_GET['school']) ? sanitize_text_field($_GET['school']) : '',
        'competition_status' => isset($_GET['competition_status']) ? sanitize_text_field($_GET['competition_status']) : '',
        's' => isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '',
        'orderby' => isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : '',
        'order' => isset($_GET['order']) ? sanitize_text_field($_GET['order']) : ''
    ], admin_url('admin.php'))); ?>" class="page-title-action export-csv-button">
        <span class="dashicons dashicons-media-spreadsheet" style="margin-right: 5px;"></span>
        <?php _e('Export CSV', 'wevoi-competition-manager'); ?>
    </a>
    
    <hr class="wp-header-end">

    <!-- Filtreler -->
    <form method="get" id="students-filter-form">
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page']; ?>">
        <input type="hidden" name="tab" value="students">
        
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

                <!-- Yarışma Katılım Durumu Filtresi -->
                <select name="competition_status" id="filter-by-competition-status">
                    <option value=""><?php _e('All Students', 'wevoi-competition-manager'); ?></option>
                    <option value="participating" <?php selected(isset($_GET['competition_status']) ? $_GET['competition_status'] : '', 'participating'); ?>>
                        <?php _e('Participating', 'wevoi-competition-manager'); ?>
                    </option>
                    <option value="not_participating" <?php selected(isset($_GET['competition_status']) ? $_GET['competition_status'] : '', 'not_participating'); ?>>
                        <?php _e('Not Participating', 'wevoi-competition-manager'); ?>
                    </option>
                </select>

                <?php submit_button(__('Filter', 'wevoi-competition-manager'), 'action', false, false); ?>
            </div>



            <div class="alignright">
                <p class="search-box">
                    <label class="screen-reader-text" for="student-search">
                        <?php _e('Search Students', 'wevoi-competition-manager'); ?>:
                    </label>
                    <input type="search" id="student-search" name="s" value="<?php _admin_search_query(); ?>">
                    <?php submit_button(__('Search', 'wevoi-competition-manager'), 'action', false, false, ['id' => 'search-submit']); ?>
                </p>
            </div>
            <br class="clear">
        </div>


        <!-- Liste Görünümü -->
        <div id="students-list-view">
            <?php 
            wp_nonce_field('bulk-students'); 
            $studentsTable->display(); 
            ?>
        </div>
    </form>
</div>

<!-- Detail Modal Template -->
<div id="student-details-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php _e('Student Details', 'wevoi-competition-manager'); ?></h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <!-- AJAX ile doldurulacak -->
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Ülke seçimi değiştiğinde
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
                    // Okulları ekle
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

    // Dinamik view-details butonları için delegasyon
    $(document).on('click', '.view-details', function() {
        const studentId = $(this).data('student-id');
        const modal = $('#student-details-modal');
        
        // Modal yükleniyor göstergesi
        modal.find('.modal-body').html('<div class="loading">Loading...</div>');
        modal.show();
        
        // AJAX ile detayları getir
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_student_details',
                student_id: studentId,
                nonce: '<?php echo wp_create_nonce('get_student_details'); ?>',
                security: '<?php echo wp_create_nonce('wevoi_ajax_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    modal.find('.modal-body').html(response.data);
                } else {
                    modal.find('.modal-body').html('<div class="error">Error: ' + (response.data || 'Veri yüklenemedi.') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                modal.find('.modal-body').html('<div class="error">Error loading data</div>');
            }
        });
    });

    $('.modal-close').on('click', function() {
        $('#student-details-modal').hide();
    });

    $(document).on('click', function(e) {
        const modal = $('#student-details-modal');
        if ($(e.target).is('.modal')) {
            modal.hide();
        }
    });
});
</script>