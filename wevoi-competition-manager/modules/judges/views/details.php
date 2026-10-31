<?php
/**
 * Judge Details View
 *
 * @package Wevoi_Competition_Manager
 * @subpackage Judges
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get judge data
$judge = isset($data['judge']) ? $data['judge'] : null;
$assignments = isset($data['assignments']) ? $data['assignments'] : array();
$certificates = isset($data['certificates']) ? $data['certificates'] : array();

if (!$judge) {
    echo '<div class="notice notice-error"><p>' . __('Judge not found.', 'wevoi-competition-manager') . '</p></div>';
    return;
}
?>

<div class="judge-details-wrapper">
    <!-- Judge Information -->
    <div class="judge-info section">
        <h3><?php _e('Judge Information', 'wevoi-competition-manager'); ?></h3>
        <table class="widefat striped">
            <tr>
                <th><?php _e('Name:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo esc_html($judge->get_name()); ?></td>
            </tr>
            <tr>
                <th><?php _e('Email:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo esc_html($judge->get_email()); ?></td>
            </tr>
            <tr>
                <th><?php _e('Registration Date:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo esc_html($judge->get_registration_date()); ?></td>
            </tr>
            <tr>
                <th><?php _e('Country:', 'wevoi-competition-manager'); ?></th>
                <td>
                    <?php
                    if ($judge->country_name) {
                        printf('%s (ID: %d)', esc_html($judge->country_name), $judge->country_id);
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
                    if ($judge->school_name) {
                        printf('%s (ID: %d)', esc_html($judge->school_name), $judge->school_id);
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
        <?php if (!empty($assignments)): ?>
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

                        // Get student name
                        $student_name = '';
                        if (!empty($assignment->student_first_name) || !empty($assignment->student_last_name)) {
                            $student_name = trim($assignment->student_first_name . ' ' . $assignment->student_last_name);
                        }
                        if (empty($student_name) && !empty($assignment->student_display_name)) {
                            $student_name = $assignment->student_display_name;
                        }
                        if (empty($student_name)) {
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
        <?php if (!empty($certificates)): ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Competition', 'wevoi-competition-manager'); ?></th>
                        <th><?php _e('Issue Date', 'wevoi-competition-manager'); ?></th>
                        <th><?php _e('Certificate', 'wevoi-competition-manager'); ?></th>
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
                                <?php echo date_i18n(get_option('date_format'), strtotime($cert->issue_date)); ?>
                            </td>
                            <td>
                                <?php if ($cert->certificate_link): ?>
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
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-items"><?php _e('No certificates found.', 'wevoi-competition-manager'); ?></p>
        <?php endif; ?>
    </div>
</div>
