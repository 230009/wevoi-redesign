<?php
// Direct access control
if (!defined('ABSPATH')) {
    exit;
}

// Check judge ID parameter
if (!isset($judge_id)) {
    wp_die(__('Judge ID is required.', 'wevoi-competition-manager'));
}

// Get judge details
global $wpdb;

// Judge details
$judge = $wpdb->get_row($wpdb->prepare(
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
    $judge_id
));

if (!$judge) {
    wp_die(__('Judge not found.', 'wevoi-competition-manager'));
}

// Get assignments and evaluations
// Only show assignments from EVALUATING competitions
// Include student information and proper status
// Only show evaluations that belong to the same EVALUATING competition assignment
$assignments = $wpdb->get_results($wpdb->prepare(
    "SELECT ja.*,
            c.competition_name,
            c.competition_status,
            je.evaluation_id,
            je.total_score,
            je.evaluation_date,
            r.register_id,
            u.ID as student_id,
            u.display_name as student_display_name,
            u.user_email as student_email,
            um_first.meta_value as student_first_name,
            um_last.meta_value as student_last_name
     FROM {$wpdb->prefix}judge_assignments ja
     INNER JOIN {$wpdb->prefix}wevoi_competitions c ON ja.competition_code = c.competition_code AND c.competition_status = 'EVALUATING'
     LEFT JOIN {$wpdb->prefix}wevoi_registrations r ON ja.register_id = r.register_id AND r.competition_code = c.competition_code
     LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
     LEFT JOIN {$wpdb->usermeta} um_first ON u.ID = um_first.user_id AND um_first.meta_key = 'first_name'
     LEFT JOIN {$wpdb->usermeta} um_last ON u.ID = um_last.user_id AND um_last.meta_key = 'last_name'
     LEFT JOIN {$wpdb->prefix}judge_evaluations je ON ja.assignment_id = je.assignment_id AND je.judge_id = ja.judge_id
     WHERE ja.judge_id = %d
     ORDER BY ja.assignment_date DESC",
    $judge_id
));

// Get certificates
$certificates = $wpdb->get_results($wpdb->prepare(
    "SELECT jc.*, c.competition_name
     FROM {$wpdb->prefix}judge_certificates jc
     LEFT JOIN {$wpdb->prefix}wevoi_competitions c ON jc.competition_code = c.competition_code
     WHERE jc.judge_id = %d
     ORDER BY jc.issue_date DESC",
    $judge_id
));
?>

<div class="judge-details-wrapper">
    <!-- Judge Information -->
    <div class="judge-info section">
        <h3><?php _e('Judge Information', 'wevoi-competition-manager'); ?></h3>
        <table class="widefat striped">
            <tr>
                <th><?php _e('Name:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo esc_html(wevoi_proper_name($judge->display_name)); ?></td>
            </tr>
            <tr>
                <th><?php _e('Email:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo esc_html($judge->user_email); ?></td>
            </tr>
            <tr>
                <th><?php _e('Registration Date:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo date_i18n(get_option('date_format'), strtotime($judge->user_registered)); ?></td>
            </tr>
            <tr>
                <th><?php _e('Country:', 'wevoi-competition-manager'); ?></th>
                <td>
                    <?php
                    if ($judge->countryname) {
                        printf('%s (ID: %d)', esc_html($judge->countryname), intval($judge->countryid));
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
                    if ($judge->schoolname) {
                        printf('%s (ID: %d)', esc_html($judge->schoolname), intval($judge->schoolid));
                    } else {
                        echo '-';
                    }
                    ?>
                </td>
            </tr>
        </table>
    </div>

    <!-- Competition Assignments -->
    <div class="competition-assignments section">
        <h3><?php _e('Competition Assignments', 'wevoi-competition-manager'); ?></h3>
        <?php if ($assignments): ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Competition', 'wevoi-competition-manager'); ?></th>
                        <th><?php _e('Student', 'wevoi-competition-manager'); ?></th>
                        <th><?php _e('Assignment Date', 'wevoi-competition-manager'); ?></th>
                        <th><?php _e('Status', 'wevoi-competition-manager'); ?></th>
                        <th><?php _e('Evaluation', 'wevoi-competition-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignments as $assignment): ?>
                        <?php
                        // Determine status: if evaluation exists, show EVALUATED, otherwise show PENDING
                        $display_status = $assignment->evaluation_id ? 'EVALUATED' : 'PENDING';

                        $student_name = wevoi_format_display_name(
                            isset($assignment->student_first_name) ? $assignment->student_first_name : '',
                            isset($assignment->student_last_name) ? $assignment->student_last_name : '',
                            isset($assignment->student_display_name) ? $assignment->student_display_name : ''
                        );
                        if ($student_name === '—') {
                            $student_name = __('Unknown', 'wevoi-competition-manager');
                        }
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($assignment->competition_name); ?></strong>
                                <div class="row-actions">
                                    <span class="code">Code: <?php echo esc_html($assignment->competition_code); ?></span>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo esc_html($student_name); ?></strong>
                                <?php if (!empty($assignment->student_email)): ?>
                                    <div class="row-actions">
                                        <span class="email"><?php echo esc_html($assignment->student_email); ?></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo date_i18n(get_option('date_format'), strtotime($assignment->assignment_date)); ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($display_status); ?>">
                                    <?php echo esc_html($display_status); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($assignment->evaluation_id): ?>
                                    <div><strong><?php _e('Score:', 'wevoi-competition-manager'); ?></strong> <?php echo esc_html($assignment->total_score); ?></div>
                                    <div><strong><?php _e('Date:', 'wevoi-competition-manager'); ?></strong> <?php echo date_i18n(get_option('date_format'), strtotime($assignment->evaluation_date)); ?></div>
                                <?php else: ?>
                                    <?php _e('Not evaluated yet', 'wevoi-competition-manager'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-items"><?php _e('No competition assignments found.', 'wevoi-competition-manager'); ?></p>
        <?php endif; ?>
    </div>

<!-- Judge Certificates -->
<div class="judge-certificates section">
    <h3><?php _e('Judge Certificates', 'wevoi-competition-manager'); ?></h3>
    <?php
    // Certificates tablosundan sertifikaları çek
    $has_certificates_table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wevoi_certificates'") !== null;
    $certificates = array();

    if ($has_certificates_table) {
        $certificates = $wpdb->get_results($wpdb->prepare(
            "SELECT cert.*, c.competition_name
             FROM {$wpdb->prefix}wevoi_certificates cert
             LEFT JOIN {$wpdb->prefix}wevoi_competitions c ON cert.competition_code = c.competition_code
             WHERE cert.user_id = %d AND cert.role = 'judge'
             ORDER BY cert.issue_date DESC",
            $judge_id
        ));
    }

    if (!empty($certificates)): ?>
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
        <p class="no-items"><?php _e('No certificates found.', 'wevoi-competition-manager'); ?></p>
    <?php endif; ?>
</div>
</div>

<style>
/* Genel stil */
.judge-details-wrapper {
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
.judge-details-wrapper table {
    border-spacing: 0;
    width: 100%;
    border: none;
    margin: 0;
}

.judge-details-wrapper th,
.judge-details-wrapper td {
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
.status-pending {
    background: #fff3e0;
    color: #e65100;
}

.status-evaluated {
    background: #e8f5e9;
    color: #1b5e20;
}

.status-completed {
    background: #e8f5e9;
    color: #1b5e20;
}

.status-in-progress {
    background: #e3f2fd;
    color: #0d47a1;
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
    margin-left: 5px;
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

/* Değerlendirme özellikleri */
.competition-assignments td {
    vertical-align: middle;
}

/* Sertifika bilgileri için özel stil */
td div strong {
    color: #1d2327;
    margin-right: 5px;
}
</style>
