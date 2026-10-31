<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!isset($agency_id)) {
    wp_die(__('Agency ID is required.', 'wevoi-competition-manager'));
}

global $wpdb;

$agency = $wpdb->get_row($wpdb->prepare(
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
    $agency_id
));

if (!$agency) {
    wp_die(__('Agency not found.', 'wevoi-competition-manager'));
}

$students = $wpdb->get_results($wpdb->prepare(
    "SELECT DISTINCT u.*, u.ID as student_id
     FROM {$wpdb->prefix}wevoi_registrations r
     JOIN {$wpdb->users} u ON r.user_id = u.ID
     JOIN {$wpdb->usermeta} um_role ON u.ID = um_role.user_id AND um_role.meta_key = '{$wpdb->prefix}capabilities'
     WHERE r.supervisor_id = %d
     AND um_role.meta_value LIKE '%\"student\"%'
     ORDER BY u.display_name",
    $agency_id
));

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
        WHERE r.user_id IN (" . implode(',', array_map('intval', $student_ids)) . ")
        ORDER BY c.competition_name, u.display_name
    ");
}

$has_certificates_table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wevoi_certificates'") !== null;
$certificates = array();
if ($has_certificates_table) {
    $certificates = $wpdb->get_results($wpdb->prepare(
        "SELECT cert.*, c.competition_name
         FROM {$wpdb->prefix}wevoi_certificates cert
         LEFT JOIN {$wpdb->prefix}wevoi_competitions c ON cert.competition_code = c.competition_code
         WHERE cert.user_id = %d AND cert.role = 'agency'
         ORDER BY cert.issue_date DESC",
        $agency_id
    ));
}
?>

<div class="agency-details-wrapper">
    <div class="agency-info section">
        <h3><?php _e('Agency Information', 'wevoi-competition-manager'); ?></h3>
        <table class="widefat striped">
            <tr>
                <th><?php _e('Name:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo esc_html(wevoi_proper_name($agency->display_name)); ?></td>
            </tr>
            <tr>
                <th><?php _e('Email:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo esc_html($agency->user_email); ?></td>
            </tr>
            <tr>
                <th><?php _e('Registration Date:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo date_i18n(get_option('date_format'), strtotime($agency->user_registered)); ?></td>
            </tr>
            <tr>
                <th><?php _e('Country:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo $agency->countryname ? sprintf('%s (ID: %d)', esc_html($agency->countryname), intval($agency->countryid)) : '-'; ?></td>
            </tr>
            <tr>
                <th><?php _e('School:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo $agency->schoolname ? sprintf('%s (ID: %d)', esc_html($agency->schoolname), intval($agency->schoolid)) : '-'; ?></td>
            </tr>
        </table>
    </div>

    <div class="agency-students section">
        <h3><?php _e('Students linked to this agency', 'wevoi-competition-manager'); ?></h3>
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
                            <td><strong><?php echo esc_html(wevoi_proper_name($student->display_name)); ?></strong></td>
                            <td><a href="mailto:<?php echo esc_attr($student->user_email); ?>"><?php echo esc_html($student->user_email); ?></a></td>
                            <td><?php echo date_i18n(get_option('date_format'), strtotime($student->user_registered)); ?></td>
                            <td>
                                <a href="?page=wevoi-competitions&tab=students&action=details&student_id=<?php echo esc_attr($student->student_id); ?>" class="button button-small"><?php _e('View Details', 'wevoi-competition-manager'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-items"><?php _e('No students found.', 'wevoi-competition-manager'); ?></p>
        <?php endif; ?>
    </div>

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
                            <td><strong><?php echo esc_html($reg->competition_name); ?></strong><div class="row-actions"><span class="code">Code: <?php echo esc_html($reg->competition_code); ?></span></div></td>
                            <td><div><?php echo esc_html(wevoi_proper_name($reg->student_name)); ?></div><div class="student-email"><a href="mailto:<?php echo esc_attr($reg->student_email); ?>"><?php echo esc_html($reg->student_email); ?></a></div></td>
                            <td><span class="status-badge status-<?php echo strtolower($reg->application_status); ?>"><?php echo esc_html($reg->application_status); ?></span></td>
                            <td>
                                <?php if (!empty($reg->student_medal)): ?>
                                    <span class="medal-badge medal-<?php echo strtolower($reg->student_medal); ?>"><?php echo esc_html($reg->student_medal); ?></span>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-items"><?php _e('No competition results found.', 'wevoi-competition-manager'); ?></p>
        <?php endif; ?>
    </div>

    <div class="agency-certificates section">
        <h3><?php _e('Agency Certificates', 'wevoi-competition-manager'); ?></h3>
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
                            <td><strong><?php echo esc_html($cert->competition_name); ?></strong></td>
                            <td><?php echo !empty($cert->certificate_link) ? '<a href="' . esc_url($cert->certificate_link) . '" target="_blank" class="certificate-link">' . __('View Certificate', 'wevoi-competition-manager') . '</a>' : '-'; ?></td>
                            <td><?php echo date_i18n(get_option('date_format'), strtotime($cert->issue_date)); ?></td>
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
.agency-details-wrapper .section { background:#fff; border:1px solid #e2e4e7; border-radius:6px; margin-bottom:25px; }
.agency-details-wrapper .section h3 { margin:0; padding:15px 20px; background:#f0f0f1; border-bottom:1px solid #e2e4e7; font-size:14px; }
.agency-details-wrapper table { width:100%; }
.agency-details-wrapper th, .agency-details-wrapper td { padding:12px 20px; border-bottom:1px solid #f0f0f1; }
.no-items { padding:15px 20px; color:#666; }
</style>
