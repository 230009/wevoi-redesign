<?php
// Direct access prevention
if (!defined('ABSPATH')) {
    exit;
}

if (!isset($supervisor_id)) {
    wp_die(__('Supervisor ID is required.', 'wevoi-competition-manager'));
}

global $wpdb;

$supervisor = $wpdb->get_row($wpdb->prepare(
    "SELECT u.*, 
            um_country.meta_value as countryid,
            c.countryname,
            um_school.meta_value as schoolid,
            s.schoolname
     FROM {$wpdb->users} u
     LEFT JOIN {$wpdb->usermeta} um_country ON u.ID = um_country.user_id AND um_country.meta_key = 'countryid'
     LEFT JOIN {$wpdb->usermeta} um_school ON u.ID = um_school.user_id AND um_school.meta_key = 'schoolid'
     LEFT JOIN {$wpdb->prefix}wevoi_countries c ON c.countryid = um_country.meta_value
     LEFT JOIN {$wpdb->prefix}wevoi_schools s ON s.schoolid = um_school.meta_value
     WHERE u.ID = %d",
    $supervisor_id
));

if (!$supervisor) {
    wp_die(__('Supervisor not found.', 'wevoi-competition-manager'));
}

// Students assigned to this supervisor in wevoi_registrations
$students = $wpdb->get_results($wpdb->prepare(
    "SELECT DISTINCT u.*, u.ID as student_id
     FROM {$wpdb->prefix}wevoi_registrations r
     JOIN {$wpdb->users} u ON r.user_id = u.ID
     JOIN {$wpdb->usermeta} um_role ON u.ID = um_role.user_id AND um_role.meta_key = '{$wpdb->prefix}capabilities'
     WHERE r.supervisor_id = %d
     AND um_role.meta_value LIKE '%\"student\"%'
     ORDER BY u.display_name",
    $supervisor_id
));

// Get competition registrations for these students
$student_ids = array();
if (!empty($students)) {
    $student_ids = wp_list_pluck($students, 'student_id');
}

$registrations = array();
if (!empty($student_ids)) {
    $registrations = $wpdb->get_results("
        SELECT r.*, 
               u.display_name as student_name, 
               u.user_email as student_email,
               c.competition_name
        FROM {$wpdb->prefix}wevoi_registrations r
        JOIN {$wpdb->users} u ON r.user_id = u.ID
        JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
        WHERE r.user_id IN (" . implode(',', $student_ids) . ")
        ORDER BY c.competition_name, u.display_name
    ");
}

// Supervisor'ın sertifikalarını al
$has_certificates_table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wevoi_certificates'") !== null;
$certificates = array();

if ($has_certificates_table) {
    $certificates = $wpdb->get_results($wpdb->prepare(
        "SELECT cert.*, c.competition_name 
         FROM {$wpdb->prefix}wevoi_certificates cert
         LEFT JOIN {$wpdb->prefix}wevoi_competitions c ON cert.competition_code = c.competition_code
         WHERE cert.user_id = %d AND cert.role = 'supervisor'
         ORDER BY cert.issue_date DESC",
        $supervisor_id
    ));
}
?>

<div class="supervisor-details-wrapper">
    <!-- Süpervizör Bilgileri -->
    <div class="supervisor-info section">
        <h3><?php _e('Supervisor Information', 'wevoi-competition-manager'); ?></h3>
        <table class="widefat striped">
            <tr>
                <th><?php _e('Name:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo esc_html(wevoi_proper_name($supervisor->display_name)); ?></td>
            </tr>
            <tr>
                <th><?php _e('Email:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo esc_html($supervisor->user_email); ?></td>
            </tr>
            <tr>
                <th><?php _e('Registration Date:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo date_i18n(get_option('date_format'), strtotime($supervisor->user_registered)); ?></td>
            </tr>
            <tr>
                <th><?php _e('Country:', 'wevoi-competition-manager'); ?></th>
                <td>
                    <?php 
                    if ($supervisor->countryname) {
                        printf('%s (ID: %d)', esc_html($supervisor->countryname), intval($supervisor->countryid));
                    } else {
                        echo '-';
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th><?php _e('School:', 'wevoi-competition-manager'); ?></th>
                <td>
                    <?php 
                    if ($supervisor->schoolname) {
                        printf('%s (ID: %d)', esc_html($supervisor->schoolname), intval($supervisor->schoolid));
                    } else {
                        echo '-';
                    }
                    ?>
                </td>
            </tr>
        </table>
    </div>

    <!-- Danışmanlık Yapılan Öğrenciler -->
    <div class="supervised-students section">
        <h3><?php _e('Supervised Students', 'wevoi-competition-manager'); ?></h3>
        <?php if (!empty($students)): ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Student Name', 'wevoi-competition-manager'); ?></th>
                        <th><?php _e('Email', 'wevoi-competition-manager'); ?></th>
                        <th><?php _e('Registration Date', 'wevoi-competition-manager'); ?></th>
                        <th><?php _e('Actions', 'wevoi-competition-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html(wevoi_proper_name($student->display_name)); ?></strong>
                            </td>
                            <td>
                                <a href="mailto:<?php echo esc_attr($student->user_email); ?>">
                                    <?php echo esc_html($student->user_email); ?>
                                </a>
                            </td>
                            <td>
                                <?php echo date_i18n(get_option('date_format'), strtotime($student->user_registered)); ?>
                            </td>
                            <td>
                                <a href="?page=wevoi-competitions&tab=students&action=details&student_id=<?php echo esc_attr($student->student_id); ?>" 
                                   class="button button-small">
                                    <?php _e('View Details', 'wevoi-competition-manager'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-items"><?php _e('No students found.', 'wevoi-competition-manager'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Öğrencilerin Yarışma Sonuçları -->
    <div class="student-results section">
        <h3><?php _e('Student Competition Results', 'wevoi-competition-manager'); ?></h3>
        <?php if (!empty($registrations)): ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Competition', 'wevoi-competition-manager'); ?></th>
                        <th><?php _e('Student', 'wevoi-competition-manager'); ?></th>
                        <th><?php _e('Status', 'wevoi-competition-manager'); ?></th>
                        <th><?php _e('Result', 'wevoi-competition-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registrations as $reg): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($reg->competition_name); ?></strong>
                                <div class="row-actions">
                                    <span class="code">Code: <?php echo esc_html($reg->competition_code); ?></span>
                                </div>
                            </td>
                            <td>
                                <div><?php echo esc_html(wevoi_proper_name($reg->student_name)); ?></div>
                                <div class="student-email">
                                    <a href="mailto:<?php echo esc_attr($reg->student_email); ?>">
                                        <?php echo esc_html($reg->student_email); ?>
                                    </a>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($reg->application_status); ?>">
                                    <?php echo esc_html($reg->application_status); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($reg->student_medal): ?>
                                    <span class="medal-badge medal-<?php echo strtolower($reg->student_medal); ?>">
                                        <span class="dashicons dashicons-awards"></span>
                                        <?php echo esc_html($reg->student_medal); ?>
                                    </span>
                                    <div class="result-details">
                                        <?php if ($reg->student_point): ?>
                                            <div><?php _e('Points:', 'wevoi-competition-manager'); ?> <?php echo number_format($reg->student_point, 2); ?></div>
                                        <?php endif; ?>
                                        <?php if ($reg->student_rank): ?>
                                            <div><?php _e('Rank:', 'wevoi-competition-manager'); ?> #<?php echo intval($reg->student_rank); ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-items"><?php _e('No competition results found.', 'wevoi-competition-manager'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Süpervizör Sertifikaları -->
    <div class="supervisor-certificates section">
        <h3><?php _e('Supervisor Certificates', 'wevoi-competition-manager'); ?></h3>
        <?php if (!empty($certificates)): ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Competition', 'wevoi-competition-manager'); ?></th>
                        <th><?php _e('Certificate', 'wevoi-competition-manager'); ?></th>
                        <th><?php _e('Issue Date', 'wevoi-competition-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($certificates as $cert): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($cert->competition_name); ?></strong>
                                <div class="row-actions">
                                    <span class="code">Code: <?php echo esc_html($cert->competition_code); ?></span>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($cert->certificate_link)): ?>
                                    <a href="<?php echo esc_url($cert->certificate_link); ?>" 
                                       target="_blank" 
                                       class="certificate-link">
                                        <span class="dashicons dashicons-media-document"></span>
                                        <?php _e('View Certificate', 'wevoi-competition-manager'); ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo date_i18n(get_option('date_format'), strtotime($cert->issue_date)); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <!-- Eski verileri gösterme uyumluluğu için -->
            <?php 
            $certs_from_registrations = false;
            // Video gönderen öğrencilerin sayısını kontrol et
            $has_student_videos = false;
            if (!empty($registrations)) {
                foreach ($registrations as $reg) {
                    if (!empty($reg->youtube_link)) {
                        $has_student_videos = true;
                        break;
                    }
                }
            }
            
            if ($has_student_videos): 
                // Video yüklenen yarışmalar için sertifika gösterimi
                $competitions = array();
                foreach ($registrations as $reg) {
                    if (!empty($reg->youtube_link) && !isset($competitions[$reg->competition_code])) {
                        $competitions[$reg->competition_code] = $reg;
                    }
                }
                
                if (!empty($competitions)):
                    $certs_from_registrations = true;
                ?>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php _e('Competition', 'wevoi-competition-manager'); ?></th>
                            <th><?php _e('Certificate', 'wevoi-competition-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($competitions as $comp): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($comp->competition_name); ?></strong>
                                    <div class="row-actions">
                                        <span class="code">Code: <?php echo esc_html($comp->competition_code); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($comp->supervisor_certficates_folder)): ?>
                                        <a href="<?php echo esc_url($comp->supervisor_certficates_folder); ?>" 
                                           target="_blank" 
                                           class="certificate-link">
                                            <span class="dashicons dashicons-media-document"></span>
                                            <?php _e('View Certificate', 'wevoi-competition-manager'); ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if (!$certs_from_registrations): ?>
                <p class="no-items"><?php _e('No certificates found.', 'wevoi-competition-manager'); ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
/* Genel stil */
.supervisor-details-wrapper {
    max-width: 100%;
    padding: 0;
}

/* Bölüm stilleri */
.section {
    background: #fff;
    border: 1px solid #e2e4e7;
    border-radius: 6px;
    margin-bottom: 25px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.section h3 {
    margin: 0;
    padding: 15px 20px;
    background: #f0f0f1;
    border-bottom: 1px solid #e2e4e7;
    border-radius: 6px 6px 0 0;
    font-size: 14px;
    font-weight: 600;
    color: #1d2327;
}

/* Tablo stilleri */
.supervisor-details-wrapper table {
    border-spacing: 0;
    width: 100%;
    border: none;
    margin: 0;
}

.supervisor-details-wrapper th,
.supervisor-details-wrapper td {
    padding: 12px 20px;
    border-bottom: 1px solid #f0f0f1;
}

/* Badge stilleri */
.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 500;
    text-align: center;
}

/* Status badge renkleri */
.status-pending_review {
    background: #fff3e0;
    color: #e65100;
}

.status-application_accepted {
    background: #e8f5e9;
    color: #1b5e20;
}

.status-video_inaccessible, 
.status-irrelevant_content, 
.status-application_rejected {
    background: #ffebee;
    color: #c62828;
}

/* Madalya badge stilleri */
.medal-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 600;
}

.medal-badge .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.medal-gold {
    background: linear-gradient(135deg, #fff4d3, #ffd700);
    color: #856404;
    border: 1px solid #ffd700;
}

.medal-silver {
    background: linear-gradient(135deg, #f8f9fa, #c0c0c0);
    color: #4a4a4a;
    border: 1px solid #c0c0c0;
}

.medal-bronze {
    background: linear-gradient(135deg, #fff0e8, #cd7f32);
    color: #7a4012;
    border: 1px solid #cd7f32;
}

.medal-success {
    background: linear-gradient(135deg, #e8f5e9, #81c784);
    color: #1b5e20;
    border: 1px solid #81c784;
}

/* Link stilleri */
.certificate-link {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
    padding: 6px 12px;
    border-radius: 4px;
    color: #2271b1;
    background: #f0f6fc;
    border: 1px solid #c5d9ed;
    transition: all 0.2s ease;
    margin: 0 5px;
    font-size: 12px;
}

.certificate-link:hover {
    background: #2271b1;
    color: #fff;
    border-color: #2271b1;
}

.certificate-link .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
    margin-right: 5px;
}

/* Diğer stiller */
.row-actions {
    color: #666;
    font-size: 12px;
    margin-top: 4px;
}

.no-items {
    padding: 15px 20px;
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 4px;
    color: #666;
    margin: 15px 20px;
}

.result-details {
    margin-top: 5px;
    font-size: 12px;
    color: #666;
}

/* Öğrenci e-posta stilleri */
.student-email {
    font-size: 12px;
    margin-top: 2px;
}
</style>