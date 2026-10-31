<?php
/**
 * Parent Dashboard Template
 *
 * @package WeVoi_Competition_Manager
 * @subpackage Parent
 */

// Direct access prevention
if (!defined('ABSPATH')) {
    exit;
}

// Get the dashboard instance
$dashboard = new Wevoi_Parent_Dashboard();

// Get parent data
$parent_id = get_current_user_id();
$parent = $dashboard->get_parent_data($parent_id);

// Get students
$students = $dashboard->get_students($parent_id);

// Prepare student IDs
$student_ids = array();
if (!empty($students)) {
    $student_ids = wp_list_pluck($students, 'student_id');
}

// Get registrations
$registrations = $dashboard->get_student_registrations($student_ids);

// Get parent's own Forum registrations
$own_forum_registrations = $dashboard->get_own_forum_registrations($parent_id);

// Get certificates
$certificates = $dashboard->get_certificates($parent_id);

// Set nonce for AJAX calls
$nonce = wp_create_nonce('wevoi_parent_nonce');
?>

<div class="wevoi-supervisor-dashboard">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <h2><?php _e('Parent Dashboard', 'wevoi-competition-manager'); ?></h2>
        <div class="supervisor-info">
            <p><?php _e('Welcome', 'wevoi-competition-manager'); ?>, <strong><?php echo esc_html($parent->display_name); ?></strong></p>
            <?php if (!empty($parent->schoolname)): ?>
                <p>
                    <?php echo esc_html($parent->schoolname); ?>
                    <?php if (!empty($parent->countryname)): ?>
                        (<?php echo esc_html($parent->countryname); ?>)
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Dashboard Navigation -->
    <div class="dashboard-navigation">
        <ul class="dashboard-tabs">
            <li class="active" data-tab="students"><?php _e('My Children', 'wevoi-competition-manager'); ?></li>
            <li data-tab="my-forum"><?php _e('My Forum Registrations', 'wevoi-competition-manager'); ?></li>
            <li data-tab="results"><?php _e('Competition Results', 'wevoi-competition-manager'); ?></li>
            <li data-tab="certificates"><?php _e('My Certificates', 'wevoi-competition-manager'); ?></li>
        </ul>
    </div>

    <!-- Dashboard Content -->
    <div class="dashboard-content">
        <!-- Students Tab -->
        <div class="dashboard-tab-content active" id="tab-students">
            <div class="students-list">
                <?php if (!empty($students)): ?>
                    <h3><?php _e('Your Children', 'wevoi-competition-manager'); ?></h3>

                    <?php
                    // Organize students by competition
                    $students_by_competition = array();
                    $student_map = array();

                    // First, create a map of students for quick access
                    foreach ($students as $student) {
                        $student_map[$student->student_id] = $student;
                    }

                    // Group by competition
                    if (!empty($registrations)) {
                        foreach ($registrations as $reg) {
                            $competition_name = $reg->competition_name;

                            if (!isset($students_by_competition[$competition_name])) {
                                $students_by_competition[$competition_name] = array(
                                    'code' => $reg->competition_code,
                                    'students' => array()
                                );
                            }

                            // Add student to this competition if not already added
                            if (isset($student_map[$reg->user_id]) && !in_array($reg->user_id, array_column($students_by_competition[$competition_name]['students'], 'student_id'))) {
                                $students_by_competition[$competition_name]['students'][] = array(
                                    'student' => $student_map[$reg->user_id],
                                    'status' => $reg->application_status
                                );
                            }
                        }
                    }

                    // Check for students without competitions
                    $students_without_competition = array();
                    foreach ($students as $student) {
                        $has_competition = false;
                        foreach ($students_by_competition as $competition) {
                            foreach ($competition['students'] as $comp_student) {
                                if ($comp_student['student']->student_id == $student->student_id) {
                                    $has_competition = true;
                                    break 2;
                                }
                            }
                        }

                        if (!$has_competition) {
                            $students_without_competition[] = array(
                                'student' => $student,
                                'status' => ''
                            );
                        }
                    }

                    // Add "No competition" group if there are students without competitions
                    if (!empty($students_without_competition)) {
                        $students_by_competition[__('No Competition Participation', 'wevoi-competition-manager')] = array(
                            'code' => 'no-competition',
                            'students' => $students_without_competition
                        );
                    }

                    // Sort competitions alphabetically
                    ksort($students_by_competition);

                    // Display each competition group
                    foreach ($students_by_competition as $competition_name => $competition):
                    ?>
                        <div class="student-group">
                            <div class="accordion-header">
                                <?php echo esc_html($competition_name); ?> (<?php echo count($competition['students']); ?> <?php _e('students', 'wevoi-competition-manager'); ?>)
                            </div>
                            <div class="accordion-content">
                                <table class="wevoi-table students-table">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Name', 'wevoi-competition-manager'); ?></th>
                                            <th><?php _e('Email', 'wevoi-competition-manager'); ?></th>
                                            <th><?php _e('Registration Date', 'wevoi-competition-manager'); ?></th>
                                            <th><?php _e('Status', 'wevoi-competition-manager'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($competition['students'] as $student_data):
                                            $student = $student_data['student'];
                                            $status = $student_data['status'];

                                            // Get student first and last name
                                            $first_name = get_user_meta($student->student_id, 'first_name', true);
                                            $last_name = get_user_meta($student->student_id, 'last_name', true);
                                            $full_name = trim($first_name . ' ' . $last_name);

                                            if (empty($full_name)) {
                                                $full_name = $student->display_name;
                                            }
                                        ?>
                                            <tr>
                                                <td data-label="<?php _e('Name', 'wevoi-competition-manager'); ?>"><?php echo esc_html($full_name); ?></td>
                                                <td data-label="<?php _e('Email', 'wevoi-competition-manager'); ?>"><a href="mailto:<?php echo esc_attr($student->user_email); ?>"><?php echo esc_html($student->user_email); ?></a></td>
                                                <td data-label="<?php _e('Registration Date', 'wevoi-competition-manager'); ?>"><?php echo date_i18n(get_option('date_format'), strtotime($student->user_registered)); ?></td>
                                                <td data-label="<?php _e('Status', 'wevoi-competition-manager'); ?>">
                                                    <?php if (!empty($status)): ?>
                                                        <span class="status-badge status-<?php echo sanitize_html_class(strtolower($status)); ?>">
                                                            <?php echo esc_html($status); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-items-message">
                        <p><?php _e('You don\'t have any children registered yet.', 'wevoi-competition-manager'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Results Tab -->
        <div class="dashboard-tab-content" id="tab-results">
            <?php if (!empty($registrations)): ?>
                <div class="competition-results">
                    <h3><?php _e('Competition Results', 'wevoi-competition-manager'); ?></h3>
                    <?php
                    // Organize registrations by competition
                    $registrations_by_competition = array();
                    foreach ($registrations as $reg) {
                        if (!isset($registrations_by_competition[$reg->competition_code])) {
                            $registrations_by_competition[$reg->competition_code] = array(
                                'name' => $reg->competition_name,
                                'registrations' => array()
                            );
                        }
                        $registrations_by_competition[$reg->competition_code]['registrations'][] = $reg;
                    }
                    ?>

                    <?php foreach ($registrations_by_competition as $competition_code => $competition): ?>
                        <div class="competition-container">
                            <h4><?php echo esc_html($competition['name']); ?></h4>
                            <table class="wevoi-table results-table">
                                <thead>
                                    <tr>
                                        <th><?php _e('Student', 'wevoi-competition-manager'); ?></th>
                                        <th><?php _e('Status', 'wevoi-competition-manager'); ?></th>
                                        <th><?php _e('Video', 'wevoi-competition-manager'); ?></th>
                                        <th><?php _e('Result', 'wevoi-competition-manager'); ?></th>
                                        <th><?php _e('Certificate', 'wevoi-competition-manager'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($competition['registrations'] as $reg): ?>
                                        <tr>
                                            <td data-label="<?php _e('Student', 'wevoi-competition-manager'); ?>">
                                                <div><?php echo esc_html($reg->student_name); ?></div>
                                                <div class="student-email"><a href="mailto:<?php echo esc_attr($reg->student_email); ?>"><?php echo esc_html($reg->student_email); ?></a></div>
                                            </td>
                                            <td data-label="<?php _e('Status', 'wevoi-competition-manager'); ?>">
                                                <span class="status-badge status-<?php echo sanitize_html_class(strtolower($reg->application_status)); ?>">
                                                    <?php echo esc_html($reg->application_status); ?>
                                                </span>
                                            </td>
                                            <td data-label="<?php _e('Video', 'wevoi-competition-manager'); ?>">
                                                <?php if (!empty($reg->youtube_link)): ?>
                                                    <a href="<?php echo esc_url($reg->youtube_link); ?>" target="_blank" class="youtube-link">
                                                        <?php _e('Watch Video', 'wevoi-competition-manager'); ?>
                                                    </a>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="<?php _e('Result', 'wevoi-competition-manager'); ?>">
                                                <?php if (!empty($reg->student_medal)): ?>
                                                    <span class="medal-badge medal-<?php echo sanitize_html_class(strtolower($reg->student_medal)); ?>">
                                                        <?php echo esc_html($reg->student_medal); ?>
                                                    </span>
                                                    <?php if (!empty($reg->student_point) || !empty($reg->student_rank)): ?>
                                                        <div class="result-details">
                                                            <?php if (!empty($reg->student_point)): ?>
                                                                <div><?php printf(__('Points: %s', 'wevoi-competition-manager'), number_format((float)$reg->student_point, 2)); ?></div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($reg->student_rank)): ?>
                                                                <div><?php printf(__('Rank: #%d', 'wevoi-competition-manager'), intval($reg->student_rank)); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Summit Kupon Kodu varsa göster -->
                                                    <?php if (!empty($reg->claimed_summit_coupon_code)): ?>
                                                        <div class="coupon-code">
                                                            <?php printf(__('Summit Coupon: %s', 'wevoi-competition-manager'), '<strong>' . esc_html($reg->claimed_summit_coupon_code) . '</strong>'); ?>
                                                        </div>
                                                    <?php endif; ?>

                                                <?php elseif (!empty($reg->claimed_summit_coupon_code)): ?>
                                                    <!-- Madalya yoksa ama kupon kodu varsa sadece kuponu göster -->
                                                    <div class="coupon-code">
                                                        <?php printf(__('Summit Coupon: %s', 'wevoi-competition-manager'), '<strong>' . esc_html($reg->claimed_summit_coupon_code) . '</strong>'); ?>
                                                    </div>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="<?php _e('Certificate', 'wevoi-competition-manager'); ?>">
                                                <?php if (!empty($reg->certificate_link)): ?>
                                                    <a href="<?php echo esc_url($reg->certificate_link); ?>" target="_blank" class="certificate-link">
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
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-items-message">
                    <p><?php _e('No competition results found for your children.', 'wevoi-competition-manager'); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- My Forum Registrations Tab -->
        <div class="dashboard-tab-content" id="tab-my-forum">
            <?php if (!empty($own_forum_registrations)): ?>
                <div class="forum-registrations-list">
                    <h3><?php _e('My Forum Registrations', 'wevoi-competition-manager'); ?></h3>
                    <div class="forum-registrations-grid">
                        <?php foreach ($own_forum_registrations as $reg): ?>
                            <div class="forum-card" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <div class="forum-card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; border-radius: 8px 8px 0 0; margin: -20px -20px 20px -20px;">
                                    <h4 style="margin: 0; color: white;"><?php echo esc_html($reg->competition_name); ?></h4>
                                </div>

                                <div class="forum-card-content">
                                    <div class="forum-info-section" style="margin-bottom: 15px;">
                                        <div class="info-item" style="margin-bottom: 10px;">
                                            <span class="info-label" style="font-weight: 600; display: inline-block; width: 150px;"><?php _e('Registration Date', 'wevoi-competition-manager'); ?>:</span>
                                            <span class="info-value">
                                                <?php
                                                if (!empty($reg->registration_date)) {
                                                    echo date_i18n(get_option('date_format'), strtotime($reg->registration_date));
                                                } else {
                                                    echo '-';
                                                }
                                                ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Forum Cards Container -->
                                    <div class="forum-cards-container">
                                        <!-- Forum Event Details -->
                                        <div class="forum-event-details">
                                            <label class="forum-card-title"><?php _e('Event Information', 'wevoi-competition-manager'); ?></label>
                                            <?php if (!empty($reg->event_venue)): ?>
                                                <div class="info-item" style="margin-bottom: 10px;">
                                                    <span class="info-label" style="font-weight: 600;"><?php _e('Event Venue', 'wevoi-competition-manager'); ?>:</span>
                                                    <span class="info-value"><?php echo esc_html($reg->event_venue); ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($reg->competition_date)): ?>
                                                <div class="info-item" style="margin-bottom: 10px;">
                                                    <span class="info-label" style="font-weight: 600;"><?php _e('Event Date', 'wevoi-competition-manager'); ?>:</span>
                                                    <span class="info-value"><?php echo date_i18n(get_option('date_format'), strtotime($reg->competition_date)); ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($reg->event_duration)): ?>
                                                <div class="info-item" style="margin-bottom: 10px;">
                                                    <span class="info-label" style="font-weight: 600;"><?php _e('Duration', 'wevoi-competition-manager'); ?>:</span>
                                                    <span class="info-value"><?php echo esc_html($reg->event_duration); ?> <?php echo ($reg->event_duration == 1) ? __('day', 'wevoi-competition-manager') : __('days', 'wevoi-competition-manager'); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Payment Information -->
                                        <div class="forum-payment-info">
                                            <label class="forum-card-title"><?php _e('Payment Information', 'wevoi-competition-manager'); ?></label>
                                        <div class="info-item" style="margin-bottom: 8px;">
                                            <span class="info-label"><?php _e('Status', 'wevoi-competition-manager'); ?>:</span>
                                            <span class="info-value">
                                                <span class="status-badge status-<?php echo sanitize_html_class(strtolower($reg->paid_status ?? 'PENDING')); ?>">
                                                    <?php echo esc_html($reg->paid_status ?? 'PENDING'); ?>
                                                </span>
                                            </span>
                                        </div>
                                        <?php if (!empty($reg->price)): ?>
                                            <div class="info-item" style="margin-bottom: 8px;">
                                                <span class="info-label"><?php _e('Price', 'wevoi-competition-manager'); ?>:</span>
                                                <span class="info-value">£<?php echo number_format((float)$reg->price, 2); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($reg->coupon_code)): ?>
                                            <div class="info-item" style="margin-bottom: 8px;">
                                                <span class="info-label"><?php _e('Coupon Code', 'wevoi-competition-manager'); ?>:</span>
                                                <span class="info-value"><?php echo esc_html($reg->coupon_code); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        </div>

                                        <!-- Visa Status -->
                                        <div class="forum-visa-status">
                                            <label class="forum-card-title"><?php _e('Visa Status', 'wevoi-competition-manager'); ?></label>
                                            <div class="visa-status-radio-wrapper">
                                                <div class="visa-radio-group">
                                                    <label class="visa-radio-label">
                                                        <input type="radio"
                                                               name="visa_status_<?php echo esc_attr($reg->register_id); ?>"
                                                               class="visa-status-radio"
                                                               data-registration-id="<?php echo esc_attr($reg->register_id); ?>"
                                                               value="I currently hold a valid visa"
                                                               <?php checked($reg->visa_status ?? '', 'I currently hold a valid visa'); ?>>
                                                        <span><?php _e('I currently hold a valid visa', 'wevoi-competition-manager'); ?></span>
                                                    </label>
                                                    <label class="visa-radio-label">
                                                        <input type="radio"
                                                               name="visa_status_<?php echo esc_attr($reg->register_id); ?>"
                                                               class="visa-status-radio"
                                                               data-registration-id="<?php echo esc_attr($reg->register_id); ?>"
                                                               value="I have not yet submitted a visa application"
                                                               <?php checked($reg->visa_status ?? '', 'I have not yet submitted a visa application'); ?>>
                                                        <span><?php _e('I have not yet submitted a visa application', 'wevoi-competition-manager'); ?></span>
                                                    </label>
                                                    <label class="visa-radio-label">
                                                        <input type="radio"
                                                               name="visa_status_<?php echo esc_attr($reg->register_id); ?>"
                                                               class="visa-status-radio"
                                                               data-registration-id="<?php echo esc_attr($reg->register_id); ?>"
                                                               value="My visa application is under evaluation"
                                                               <?php checked($reg->visa_status ?? '', 'My visa application is under evaluation'); ?>>
                                                        <span><?php _e('My visa application is under evaluation', 'wevoi-competition-manager'); ?></span>
                                                    </label>
                                                    <label class="visa-radio-label">
                                                        <input type="radio"
                                                               name="visa_status_<?php echo esc_attr($reg->register_id); ?>"
                                                               class="visa-status-radio"
                                                               data-registration-id="<?php echo esc_attr($reg->register_id); ?>"
                                                               value="My visa application has been rejected"
                                                               <?php checked($reg->visa_status ?? '', 'My visa application has been rejected'); ?>>
                                                        <span><?php _e('My visa application has been rejected', 'wevoi-competition-manager'); ?></span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="no-items-message">
                    <p><?php _e('You don\'t have any Forum registrations yet.', 'wevoi-competition-manager'); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Certificates Tab -->
        <div class="dashboard-tab-content" id="tab-certificates">
            <?php if (!empty($certificates)): ?>
                <div class="certificates-list">
                    <h3><?php _e('My Certificates', 'wevoi-competition-manager'); ?></h3>
                    <table class="wevoi-table certificates-table">
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
                                    <td data-label="<?php _e('Competition', 'wevoi-competition-manager'); ?>"><?php echo esc_html($cert->competition_name ?? '-'); ?></td>
                                    <td data-label="<?php _e('Issue Date', 'wevoi-competition-manager'); ?>">
                                        <?php
                                        if (isset($cert->issue_date)) {
                                            echo date_i18n(get_option('date_format'), strtotime($cert->issue_date));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td data-label="<?php _e('Certificate', 'wevoi-competition-manager'); ?>">
                                        <?php
                                        $cert_link = isset($cert->certificate_link) ? $cert->certificate_link : '';

                                        if (!empty($cert_link)):
                                        ?>
                                            <a href="<?php echo esc_url($cert_link); ?>" target="_blank" class="certificate-link">
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
                </div>
            <?php else: ?>
                <div class="no-items-message">
                    <p><?php _e('No certificates found.', 'wevoi-competition-manager'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Hidden field for student IDs -->
<input type="hidden" id="student-ids" value="<?php echo esc_attr(implode(',', $student_ids)); ?>" />

<script type="text/javascript">
    // Set up nonce for AJAX calls
    var wevoiParentNonce = '<?php echo esc_js($nonce); ?>';

    // Define ajaxurl if not already defined
    if (typeof ajaxurl === 'undefined') {
        var ajaxurl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
    }

    // Handle visa status update
    jQuery(document).ready(function($) {
        $('.visa-status-radio').on('change', function() {
            var $radio = $(this);
            var registrationId = $radio.data('registration-id');
            var visaStatus = $radio.val();
            var $radioGroup = $radio.closest('.visa-radio-group');
            var $originalRadio = $radioGroup.find('input[data-original-value="' + visaStatus + '"]');

            // Disable all radios in this group while updating
            $radioGroup.find('input').prop('disabled', true);
            var $originalChecked = $radioGroup.find('input:checked').not($radio);
            var originalValue = $originalChecked.length > 0 ? $originalChecked.val() : '';

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wevoi_update_visa_status',
                    nonce: wevoiParentNonce,
                    registration_id: registrationId,
                    visa_status: visaStatus
                },
                success: function(response) {
                    if (response.success) {
                        // Update original value for all radios in group
                        $radioGroup.find('input').each(function() {
                            $(this).data('original-value', $(this).is(':checked') ? $(this).val() : '');
                        });
                        // Show success message
                        var $message = $('<div class="visa-status-message" style="margin-top: 5px; padding: 5px; background: #d4edda; color: #155724; border-radius: 4px; font-size: 12px; text-align: center;"><?php _e('Visa status updated successfully', 'wevoi-competition-manager'); ?></div>');
                        $radioGroup.after($message);
                        setTimeout(function() {
                            $message.fadeOut(function() {
                                $(this).remove();
                            });
                        }, 3000);
                    } else {
                        // Revert to original selection
                        if (originalValue) {
                            $radioGroup.find('input[value="' + originalValue + '"]').prop('checked', true);
                        } else {
                            $radio.prop('checked', false);
                        }
                        alert(response.data || '<?php _e('Failed to update visa status', 'wevoi-competition-manager'); ?>');
                    }
                },
                error: function() {
                    // Revert to original selection
                    if (originalValue) {
                        $radioGroup.find('input[value="' + originalValue + '"]').prop('checked', true);
                    } else {
                        $radio.prop('checked', false);
                    }
                    alert('<?php _e('Error updating visa status', 'wevoi-competition-manager'); ?>');
                },
                complete: function() {
                    // Re-enable all radios
                    $radioGroup.find('input').prop('disabled', false);
                }
            });
        });

        // Store original values on page load
        $('.visa-status-radio').each(function() {
            if ($(this).is(':checked')) {
                $(this).data('original-value', $(this).val());
            }
        });
    });
</script>
