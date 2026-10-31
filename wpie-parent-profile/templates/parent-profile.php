<?php
/**
 * Parent Profile Template
 *
 * @package WPIE_Parent_Profile
 */

// Direct access prevention
if (!defined('ABSPATH')) {
    exit;
}

// Initialize profile class
$profile = new WPIE_Parent_Profile();
$parent_id = get_current_user_id();

// Get parent data
$parent = $profile->get_parent_data($parent_id);

// Check if parent data is valid
if (is_wp_error($parent)) {
    ?>
    <div class="wpie-parent-profile-error">
        <p><?php echo esc_html($parent->get_error_message()); ?></p>
    </div>
    <?php
    return;
}

// Get children (students)
$students = $profile->get_students($parent_id);

// Get student IDs
$student_ids = !empty($students) ? wp_list_pluck($students, 'student_id') : array();

// Get student registrations
$registrations = $profile->get_student_registrations($student_ids);

// Get parent certificates
$certificates = $profile->get_certificates($parent_id);

// Get parent's own Forum registrations
$own_forum_registrations = $profile->get_own_forum_registrations($parent_id);

// Get statistics
$stats = $profile->get_statistics($parent_id);

// Create a flat list of all registrations with student info
$all_student_registrations = array();
if (!empty($registrations)) {
    // Create student lookup array
    $student_lookup = array();
    foreach ($students as $student) {
        $student_first_name = !empty($student->first_name) ? mb_convert_case($student->first_name, MB_CASE_TITLE, 'UTF-8') : '';
        $student_last_name = !empty($student->last_name) ? mb_convert_case($student->last_name, MB_CASE_TITLE, 'UTF-8') : '';
        $student_full_name = trim($student_first_name . ' ' . $student_last_name);
        if (empty($student_full_name)) {
            $student_full_name = mb_convert_case($student->display_name, MB_CASE_TITLE, 'UTF-8');
        }

        $student_lookup[$student->student_id] = array(
            'name' => $student_full_name,
            'email' => $student->user_email
        );
    }

    // Add student info to each registration
    foreach ($registrations as $reg) {
        if (isset($student_lookup[$reg->user_id])) {
            $reg->student_name = $student_lookup[$reg->user_id]['name'];
            $reg->student_email = $student_lookup[$reg->user_id]['email'];
            // Age category comes from registration table, not from student lookup
            $reg->student_age_category = !empty($reg->age_category) ? $reg->age_category : '';
            $all_student_registrations[] = $reg;
        }
    }
}
?>

<div class="wpie-parent-profile-wrapper">
    <div class="wpie-parent-profile-header">
        <h1 class="wpie-profile-title"><?php echo __('My Dashboard', 'wpie-parent-profile'); ?></h1>
    </div>

    <!-- WeVoi Forum 2026 Message -->
    <?php
    // Check if parent is registered for active Forum
    $is_registered_for_active_forum = $profile->is_registered_for_active_forum($parent_id);

    // Get ON AIR Forum competition details
    $active_forum = $profile->get_active_forum_competition();

    // Only show card when Forum is ON AIR and parent is not registered
    if ($active_forum && !$is_registered_for_active_forum):
    ?>
    <div class="forum-2026-banner">
        <div class="forum-banner-content">
            <div class="forum-icon">🌍</div>
            <div class="forum-message">
                <h3><?php _e('WeVoi Forum 2026: Global Experience Awaits You!', 'wpie-parent-profile'); ?></h3>
                <p>
                    <?php _e('WeVoi Forum is your opportunity to join a global learning experience with families and participants from around the world. Through workshops, city explorations, and cultural exchange, you\'ll connect with other parents, support your child\'s educational journey, and be part of an enriching international community.', 'wpie-parent-profile'); ?>
                </p>
                <?php if ($active_forum): ?>
                <div class="forum-event-info" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255, 255, 255, 0.3);">
                    <?php if (!empty($active_forum->event_venue)): ?>
                    <div style="margin-bottom: 8px;">
                        <strong><?php _e('Event Venue:', 'wpie-parent-profile'); ?></strong>
                        <span><?php echo esc_html($active_forum->event_venue); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($active_forum->competition_date)): ?>
                    <div style="margin-bottom: 8px;">
                        <strong><?php _e('Start Date:', 'wpie-parent-profile'); ?></strong>
                        <span><?php echo date_i18n(get_option('date_format'), strtotime($active_forum->competition_date)); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($active_forum->event_duration)): ?>
                    <div style="margin-bottom: 8px;">
                        <strong><?php _e('Duration:', 'wpie-parent-profile'); ?></strong>
                        <span><?php echo esc_html($active_forum->event_duration); ?> <?php echo ($active_forum->event_duration == 1) ? __('day', 'wpie-parent-profile') : __('days', 'wpie-parent-profile'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="forum-action">
                    <a href="https://wevoi.org/register-forum" target="_blank" class="forum-register-button">
                        <?php _e('Register Now', 'wpie-parent-profile'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; // End check for active Forum registration ?>

    <!-- Parent Personal Information -->
    <section class="wpie-profile-section wpie-personal-info">
        <h2 class="wpie-section-title"><?php echo __('Personal Information', 'wpie-parent-profile'); ?></h2>
        <div class="wpie-info-grid">
            <!-- Left Column: Name and Email -->
            <div class="wpie-info-column">
                <div class="wpie-info-item">
                    <span class="wpie-info-label"><?php echo __('Name:', 'wpie-parent-profile'); ?></span>
                    <span class="wpie-info-value">
                        <?php
                        $parent_first_name = get_user_meta($parent_id, 'first_name', true);
                        $parent_last_name = get_user_meta($parent_id, 'last_name', true);
                        $parent_first_name = !empty($parent_first_name) ? mb_convert_case($parent_first_name, MB_CASE_TITLE, 'UTF-8') : '';
                        $parent_last_name = !empty($parent_last_name) ? mb_convert_case($parent_last_name, MB_CASE_TITLE, 'UTF-8') : '';
                        $parent_full_name = trim($parent_first_name . ' ' . $parent_last_name);
                        if (empty($parent_full_name)) {
                            $parent_full_name = mb_convert_case($parent->display_name, MB_CASE_TITLE, 'UTF-8');
                        }
                        echo esc_html($parent_full_name);
                        ?>
                    </span>
                </div>
                <div class="wpie-info-item">
                    <span class="wpie-info-label"><?php echo __('Email:', 'wpie-parent-profile'); ?></span>
                    <span class="wpie-info-value wpie-email-value"><?php echo esc_html($parent->user_email); ?></span>
                </div>
            </div>
            <!-- Right Column: Country and School -->
            <div class="wpie-info-column">
                <?php if (!empty($parent->countryname)): ?>
                <div class="wpie-info-item">
                    <span class="wpie-info-label"><?php echo __('Country:', 'wpie-parent-profile'); ?></span>
                    <span class="wpie-info-value"><?php echo esc_html($parent->countryname); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($parent->schoolname)): ?>
                <div class="wpie-info-item">
                    <span class="wpie-info-label"><?php echo __('School:', 'wpie-parent-profile'); ?></span>
                    <span class="wpie-info-value"><?php echo esc_html($parent->schoolname); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Statistics Overview -->
    <section class="wpie-profile-section wpie-statistics">
        <h2 class="wpie-section-title"><?php echo __('Statistics Overview', 'wpie-parent-profile'); ?></h2>
        <div class="wpie-stats-grid">
            <div class="wpie-stat-card">
                <div class="wpie-stat-icon">👥</div>
                <div class="wpie-stat-content">
                    <div class="wpie-stat-value"><?php echo number_format($stats['total_students']); ?></div>
                    <div class="wpie-stat-label"><?php echo __('Total Children', 'wpie-parent-profile'); ?></div>
                </div>
            </div>
            <div class="wpie-stat-card">
                <div class="wpie-stat-icon">📝</div>
                <div class="wpie-stat-content">
                    <div class="wpie-stat-value"><?php echo number_format($stats['total_registrations']); ?></div>
                    <div class="wpie-stat-label"><?php echo __('Total Registrations', 'wpie-parent-profile'); ?></div>
                </div>
            </div>
            <div class="wpie-stat-card">
                <div class="wpie-stat-icon">🎥</div>
                <div class="wpie-stat-content">
                    <div class="wpie-stat-value"><?php echo number_format($stats['video_submissions']); ?></div>
                    <div class="wpie-stat-label"><?php echo __('Video Submissions', 'wpie-parent-profile'); ?></div>
                </div>
            </div>
            <div class="wpie-stat-card">
                <div class="wpie-stat-icon">🏆</div>
                <div class="wpie-stat-content">
                    <div class="wpie-stat-value"><?php echo number_format($stats['total_certificates']); ?></div>
                    <div class="wpie-stat-label"><?php echo __('Total Certificates', 'wpie-parent-profile'); ?></div>
                </div>
            </div>
            <?php if ($stats['active_registrations'] > 0): ?>
            <div class="wpie-stat-card">
                <div class="wpie-stat-icon">⭐</div>
                <div class="wpie-stat-content">
                    <div class="wpie-stat-value"><?php echo number_format($stats['average_point'], 2); ?></div>
                    <div class="wpie-stat-label"><?php echo __('Average Point', 'wpie-parent-profile'); ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Medal Statistics -->
        <?php if ($stats['gold_medals'] > 0 || $stats['silver_medals'] > 0 || $stats['bronze_medals'] > 0 || $stats['success_certificates'] > 0): ?>
        <div class="wpie-medal-stats">
            <h3 class="wpie-subsection-title"><?php echo __('Medal Statistics', 'wpie-parent-profile'); ?></h3>
            <div class="wpie-medal-grid">
                <?php if ($stats['gold_medals'] > 0): ?>
                <div class="wpie-medal-item wpie-medal-gold">
                    <span class="wpie-medal-icon">🥇</span>
                    <span class="wpie-medal-count"><?php echo number_format($stats['gold_medals']); ?></span>
                    <span class="wpie-medal-label"><?php echo __('Gold', 'wpie-parent-profile'); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($stats['silver_medals'] > 0): ?>
                <div class="wpie-medal-item wpie-medal-silver">
                    <span class="wpie-medal-icon">🥈</span>
                    <span class="wpie-medal-count"><?php echo number_format($stats['silver_medals']); ?></span>
                    <span class="wpie-medal-label"><?php echo __('Silver', 'wpie-parent-profile'); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($stats['bronze_medals'] > 0): ?>
                <div class="wpie-medal-item wpie-medal-bronze">
                    <span class="wpie-medal-icon">🥉</span>
                    <span class="wpie-medal-count"><?php echo number_format($stats['bronze_medals']); ?></span>
                    <span class="wpie-medal-label"><?php echo __('Bronze', 'wpie-parent-profile'); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($stats['success_certificates'] > 0): ?>
                <div class="wpie-medal-item wpie-medal-success">
                    <span class="wpie-medal-icon">✓</span>
                    <span class="wpie-medal-count"><?php echo number_format($stats['success_certificates']); ?></span>
                    <span class="wpie-medal-label"><?php echo __('Success', 'wpie-parent-profile'); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Qualification, Final - Dynamic Grid -->
        <?php
        $has_qualification = $stats['competition_type_stats']['qualification'] > 0;
        $has_final = $stats['competition_type_stats']['final'] > 0;
        $has_qualification_or_final = $has_qualification || $has_final;
        ?>
        <?php if ($has_qualification_or_final): ?>
        <div class="wpie-main-stats-grid">
            <?php if ($has_qualification): ?>
            <div class="wpie-stat-item wpie-comp-type-item">
                <span class="wpie-comp-type-label"><?php echo __('Qualification:', 'wpie-parent-profile'); ?></span>
                <span class="wpie-comp-type-value"><?php echo number_format($stats['competition_type_stats']['qualification']); ?></span>
            </div>
            <?php endif; ?>

            <?php if ($has_final): ?>
            <div class="wpie-stat-item wpie-comp-type-item">
                <span class="wpie-comp-type-label"><?php echo __('Final:', 'wpie-parent-profile'); ?></span>
                <span class="wpie-comp-type-value"><?php echo number_format($stats['competition_type_stats']['final']); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Accepted and Forum Stats Cards - Side by Side on Desktop -->
        <?php
        $has_accepted = $stats['application_status_stats']['application_accepted'] > 0;
        $has_forum = $stats['competition_type_stats']['forum'] > 0;
        ?>
        <?php if ($has_accepted || $has_forum): ?>
        <div class="wpie-accepted-forum-cards-wrapper" style="margin-top: 30px;">
            <?php if ($has_accepted): ?>
            <div class="wpie-accepted-forum-card wpie-status-accepted-card">
                <div class="wpie-accepted-forum-card-content">
                    <span class="wpie-accepted-forum-label"><?php echo __('Accepted:', 'wpie-parent-profile'); ?></span>
                    <span class="wpie-accepted-forum-value"><?php echo number_format($stats['application_status_stats']['application_accepted']); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($has_forum): ?>
            <div class="wpie-accepted-forum-card wpie-forum-stat-card">
                <div class="wpie-accepted-forum-card-content">
                    <span class="wpie-accepted-forum-label"><?php echo __('Forum:', 'wpie-parent-profile'); ?></span>
                    <span class="wpie-accepted-forum-value"><?php echo number_format($stats['competition_type_stats']['forum']); ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Other Application Status Statistics -->
        <?php
        $other_status_items = array();
        if ($stats['application_status_stats']['pending_review'] > 0) {
            $other_status_items[] = array('key' => 'pending_review', 'label' => __('Pending Review:', 'wpie-parent-profile'), 'value' => $stats['application_status_stats']['pending_review'], 'class' => 'wpie-status-pending');
        }
        if ($stats['application_status_stats']['video_inaccessible'] > 0) {
            $other_status_items[] = array('key' => 'video_inaccessible', 'label' => __('Video Inaccessible:', 'wpie-parent-profile'), 'value' => $stats['application_status_stats']['video_inaccessible'], 'class' => 'wpie-status-inaccessible');
        }
        if ($stats['application_status_stats']['irrelevant_content'] > 0) {
            $other_status_items[] = array('key' => 'irrelevant_content', 'label' => __('Irrelevant Content:', 'wpie-parent-profile'), 'value' => $stats['application_status_stats']['irrelevant_content'], 'class' => 'wpie-status-irrelevant');
        }
        if ($stats['application_status_stats']['application_rejected'] > 0) {
            $other_status_items[] = array('key' => 'application_rejected', 'label' => __('Rejected:', 'wpie-parent-profile'), 'value' => $stats['application_status_stats']['application_rejected'], 'class' => 'wpie-status-rejected');
        }
        ?>
        <?php if (!empty($other_status_items)): ?>
        <div class="wpie-other-stats-section" style="margin-top: 30px;">
            <h3 class="wpie-subsection-title"><?php echo __('Application Status', 'wpie-parent-profile'); ?></h3>
            <div class="wpie-app-status-grid">
                <?php foreach ($other_status_items as $item): ?>
                <div class="wpie-app-status-item <?php echo esc_attr($item['class']); ?>">
                    <span class="wpie-status-label"><?php echo esc_html($item['label']); ?></span>
                    <span class="wpie-status-value"><?php echo number_format($item['value']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <!-- Parent Certificates -->
    <?php if (!empty($certificates)): ?>
    <section class="wpie-profile-section wpie-certificates">
        <h2 class="wpie-section-title"><?php echo __('My Certificates', 'wpie-parent-profile'); ?></h2>
        <div class="wpie-certificates-list">
            <table class="wpie-certificates-table">
                <thead>
                    <tr>
                        <th><?php echo __('Competition', 'wpie-parent-profile'); ?></th>
                        <th><?php echo __('Competition Date', 'wpie-parent-profile'); ?></th>
                        <th><?php echo __('Issue Date', 'wpie-parent-profile'); ?></th>
                        <th><?php echo __('Certificate', 'wpie-parent-profile'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($certificates as $cert): ?>
                    <tr>
                        <td><?php echo esc_html($cert->competition_name); ?></td>
                        <td><?php echo $cert->competition_date ? date_i18n(get_option('date_format'), strtotime($cert->competition_date)) : '-'; ?></td>
                        <td><?php echo $cert->issue_date ? date_i18n(get_option('date_format'), strtotime($cert->issue_date)) : '-'; ?></td>
                        <td>
                            <?php if (!empty($cert->certificate_link)): ?>
                            <a href="<?php echo esc_url($cert->certificate_link); ?>" target="_blank" class="wpie-cert-link">
                                <?php echo __('View Certificate', 'wpie-parent-profile'); ?>
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
    </section>
    <?php endif; ?>

    <!-- Parent's Own Forum Registrations -->
    <?php if (!empty($own_forum_registrations)): ?>
    <section class="wpie-profile-section wpie-forum-registrations">
        <h2 class="wpie-section-title"><?php echo __('My Forum Registrations', 'wpie-parent-profile'); ?></h2>
        <div class="wpie-forum-registrations-grid">
            <?php foreach ($own_forum_registrations as $reg):
                // Get flight information
                $flight_info = $profile->get_flight_info($reg->register_id);
                // Get airports for this competition
                $airports = $profile->get_competition_airports($reg->competition_code);
            ?>
                <div class="wpie-forum-card">
                    <div class="wpie-forum-card-header">
                        <h3><?php echo esc_html($reg->competition_name); ?></h3>
                    </div>
                    <div class="wpie-forum-card-content">
                        <div class="wpie-forum-info-section">
                            <div class="wpie-info-item">
                                <span class="wpie-info-label"><?php echo __('Registration Date', 'wpie-parent-profile'); ?>:</span>
                                <span class="wpie-info-value">
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
                        <div class="wpie-forum-cards-container">
                            <!-- Forum Event Details -->
                            <div class="wpie-forum-event-details">
                                <label class="wpie-forum-card-title"><?php echo __('Event Information', 'wpie-parent-profile'); ?></label>
                                <?php if (!empty($reg->event_venue)): ?>
                                    <div class="wpie-info-item" style="margin-bottom: 10px;">
                                        <span class="wpie-info-label" style="font-weight: 600;"><?php echo __('Event Venue', 'wpie-parent-profile'); ?>:</span>
                                        <span class="wpie-info-value"><?php echo esc_html($reg->event_venue); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($reg->competition_date)): ?>
                                    <div class="wpie-info-item" style="margin-bottom: 10px;">
                                        <span class="wpie-info-label" style="font-weight: 600;"><?php echo __('Event Date', 'wpie-parent-profile'); ?>:</span>
                                        <span class="wpie-info-value"><?php echo date_i18n(get_option('date_format'), strtotime($reg->competition_date)); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($reg->event_duration)): ?>
                                    <div class="wpie-info-item" style="margin-bottom: 10px;">
                                        <span class="wpie-info-label" style="font-weight: 600;"><?php echo __('Duration', 'wpie-parent-profile'); ?>:</span>
                                        <span class="wpie-info-value"><?php echo esc_html($reg->event_duration); ?> <?php echo ($reg->event_duration == 1) ? __('day', 'wpie-parent-profile') : __('days', 'wpie-parent-profile'); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Payment Information -->
                            <div class="wpie-forum-payment-info">
                                <label class="wpie-forum-card-title"><?php echo __('Payment Information', 'wpie-parent-profile'); ?></label>
                                <?php
                                $forum_paid_raw = isset($reg->paid_status) ? trim((string) $reg->paid_status) : '';
                                $forum_reg_ps = strtoupper(preg_replace('/[\s\-]+/', '_', $forum_paid_raw));
                                if ($forum_reg_ps === 'PREREGISTERED') {
                                    $forum_reg_ps = 'PRE_REGISTERED';
                                }
                                if ($forum_reg_ps === '') {
                                    $forum_reg_ps = 'PENDING';
                                }
                                $forum_reg_phase = isset($reg->forum_payment_phase) ? trim((string) $reg->forum_payment_phase) : '';
                                if ($forum_reg_ps === 'PRE_REGISTERED') {
                                    $forum_status_label = __('Pre-registered', 'wpie-parent-profile');
                                } else {
                                    $forum_status_label = $forum_paid_raw !== '' ? $forum_paid_raw : __('Pending', 'wpie-parent-profile');
                                }
                                $forum_paid_class = sanitize_html_class(strtolower($forum_reg_ps));
                                ?>
                                <div class="wpie-info-item" style="margin-bottom: 8px;">
                                    <span class="wpie-info-label"><?php echo __('Status', 'wpie-parent-profile'); ?>:</span>
                                    <span class="wpie-info-value">
                                        <span class="wpie-status-badge wpie-status-<?php echo esc_attr($forum_paid_class); ?>">
                                            <?php echo esc_html($forum_status_label); ?>
                                        </span>
                                    </span>
                                </div>
                                <?php if ($forum_reg_ps === 'PRE_REGISTERED') : ?>
                                    <?php if ($forum_reg_phase === 'payment_required' && floatval($reg->price) > 0) : ?>
                                        <p class="forum-pre-reg-notice" style="margin: 0 0 12px; font-size: 14px; line-height: 1.5; color: #1e3a5f;">
                                            You have completed <strong>pre-registration</strong>. Payment is now open—please use the button below when you are ready to pay and confirm your place.
                                        </p>
                                    <?php else : ?>
                                        <p class="forum-pre-reg-notice" style="margin: 0 0 12px; font-size: 14px; line-height: 1.5; color: #1e3a5f;">
                                            You have completed <strong>pre-registration</strong>. No payment has been taken yet. <strong>We will notify you by email</strong> when it is time to complete payment. Your fee below will apply when payment opens (including any coupon you used at pre-registration).
                                        </p>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (!empty($reg->price)): ?>
                                    <div class="wpie-info-item" style="margin-bottom: 8px;">
                                        <span class="wpie-info-label"><?php echo __('Price', 'wpie-parent-profile'); ?>:</span>
                                        <span class="wpie-info-value">£<?php echo number_format((float)$reg->price, 2); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($reg->coupon_code)): ?>
                                    <div class="wpie-info-item" style="margin-bottom: 8px;">
                                        <span class="wpie-info-label"><?php echo __('Coupon Code', 'wpie-parent-profile'); ?>:</span>
                                        <span class="wpie-info-value"><?php echo esc_html($reg->coupon_code); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php
                                $forum_pay_open = ($forum_reg_phase === 'payment_required');
                                if ($forum_pay_open && $forum_reg_ps === 'PRE_REGISTERED' && floatval($reg->price) > 0) :
                                    $forum_pay_url = trailingslashit(home_url('/register-forum/'));
                                ?>
                                    <div class="wpie-info-item" style="margin-top: 12px;">
                                        <a href="<?php echo esc_url($forum_pay_url); ?>" class="button button-primary" style="display: inline-block; text-decoration: none;">Complete Forum payment</a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php
                            $forum_show_travel = in_array($forum_reg_ps, array('PAID', 'FREE'), true);
                            ?>
                            <?php if ($forum_show_travel) : ?>
                            <!-- Visa Status -->
                            <div class="wpie-forum-visa-status">
                                <label class="wpie-forum-card-title"><?php echo __('Visa Status', 'wpie-parent-profile'); ?></label>
                                <div class="wpie-visa-status-radio-wrapper">
                                    <div class="wpie-visa-radio-group">
                                        <label class="wpie-visa-radio-label">
                                            <input type="radio"
                                                   name="visa_status_<?php echo esc_attr($reg->register_id); ?>"
                                                   class="wpie-visa-status-radio"
                                                   data-registration-id="<?php echo esc_attr($reg->register_id); ?>"
                                                   value="I do not need a visa"
                                                   <?php checked($reg->visa_status ?? '', 'I do not need a visa'); ?>>
                                            <span><?php echo __('I do not need a visa', 'wpie-parent-profile'); ?></span>
                                        </label>
                                        <label class="wpie-visa-radio-label">
                                            <input type="radio"
                                                   name="visa_status_<?php echo esc_attr($reg->register_id); ?>"
                                                   class="wpie-visa-status-radio"
                                                   data-registration-id="<?php echo esc_attr($reg->register_id); ?>"
                                                   value="I currently hold a valid visa"
                                                   <?php checked($reg->visa_status ?? '', 'I currently hold a valid visa'); ?>>
                                            <span><?php echo __('I currently hold a valid visa', 'wpie-parent-profile'); ?></span>
                                        </label>
                                        <label class="wpie-visa-radio-label">
                                            <input type="radio"
                                                   name="visa_status_<?php echo esc_attr($reg->register_id); ?>"
                                                   class="wpie-visa-status-radio"
                                                   data-registration-id="<?php echo esc_attr($reg->register_id); ?>"
                                                   value="I have not yet submitted a visa application"
                                                   <?php checked($reg->visa_status ?? '', 'I have not yet submitted a visa application'); ?>>
                                            <span><?php echo __('I have not yet submitted a visa application', 'wpie-parent-profile'); ?></span>
                                        </label>
                                        <label class="wpie-visa-radio-label">
                                            <input type="radio"
                                                   name="visa_status_<?php echo esc_attr($reg->register_id); ?>"
                                                   class="wpie-visa-status-radio"
                                                   data-registration-id="<?php echo esc_attr($reg->register_id); ?>"
                                                   value="My visa application is under evaluation"
                                                   <?php checked($reg->visa_status ?? '', 'My visa application is under evaluation'); ?>>
                                            <span><?php echo __('My visa application is under evaluation', 'wpie-parent-profile'); ?></span>
                                        </label>
                                        <label class="wpie-visa-radio-label">
                                            <input type="radio"
                                                   name="visa_status_<?php echo esc_attr($reg->register_id); ?>"
                                                   class="wpie-visa-status-radio"
                                                   data-registration-id="<?php echo esc_attr($reg->register_id); ?>"
                                                   value="My visa application has been rejected"
                                                   <?php checked($reg->visa_status ?? '', 'My visa application has been rejected'); ?>>
                                            <span><?php echo __('My visa application has been rejected', 'wpie-parent-profile'); ?></span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Flight Information -->
                            <div class="wpie-forum-flight-info">
                                <label class="wpie-forum-card-title"><?php echo __('Flight Information', 'wpie-parent-profile'); ?></label>
                                <form class="wpie-flight-info-form" data-registration-id="<?php echo esc_attr($reg->register_id); ?>">
                                    <!-- Arrival Flight Section -->
                                    <div class="wpie-flight-section">
                                        <h5 class="wpie-flight-section-title"><?php echo __('Arrival Flight (To Competition)', 'wpie-parent-profile'); ?></h5>
                                        <div class="wpie-flight-form-row">
                                            <div class="wpie-flight-form-group">
                                                <label><?php echo __('Flight Number', 'wpie-parent-profile'); ?></label>
                                                <input type="text"
                                                       name="arrival_flight_number"
                                                       class="wpie-flight-input"
                                                       value="<?php echo esc_attr($flight_info->arrival_flight_number ?? ''); ?>"
                                                       placeholder="<?php echo __('e.g., TK123', 'wpie-parent-profile'); ?>">
                                            </div>
                                            <div class="wpie-flight-form-group">
                                                <label><?php echo __('Airline', 'wpie-parent-profile'); ?></label>
                                                <input type="text"
                                                       name="arrival_airline"
                                                       class="wpie-flight-input"
                                                       value="<?php echo esc_attr($flight_info->arrival_airline ?? ''); ?>"
                                                       placeholder="<?php echo __('e.g., Air Astana', 'wpie-parent-profile'); ?>">
                                            </div>
                                        </div>
                                        <div class="wpie-flight-form-row">
                                            <div class="wpie-flight-form-group">
                                                <label><?php echo __('From Airport', 'wpie-parent-profile'); ?></label>
                                                <input type="text"
                                                       name="arrival_airport_from"
                                                       class="wpie-flight-input"
                                                       value="<?php echo esc_attr($flight_info->arrival_airport_from ?? ''); ?>"
                                                       placeholder="<?php echo __('e.g., Almaty International Airport', 'wpie-parent-profile'); ?>">
                                            </div>
                                            <div class="wpie-flight-form-group">
                                                <label><?php echo __('To Airport', 'wpie-parent-profile'); ?></label>
                                                <select name="arrival_airport_to" class="wpie-flight-input">
                                                    <option value=""><?php echo __('Select Airport', 'wpie-parent-profile'); ?></option>
                                                    <?php foreach ($airports as $airport): ?>
                                                        <option value="<?php echo esc_attr($airport->airport_name); ?>"
                                                                <?php selected($flight_info->arrival_airport_to ?? '', $airport->airport_name); ?>>
                                                            <?php echo esc_html($airport->airport_name); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="wpie-flight-form-row">
                                            <div class="wpie-flight-form-group">
                                                <label><?php echo __('Arrival Date', 'wpie-parent-profile'); ?></label>
                                                <input type="date"
                                                       name="arrival_date"
                                                       class="wpie-flight-input"
                                                       value="<?php echo esc_attr($flight_info->arrival_date ?? ''); ?>">
                                            </div>
                                            <div class="wpie-flight-form-group">
                                                <label><?php echo __('Arrival Time', 'wpie-parent-profile'); ?></label>
                                                <input type="time"
                                                       name="arrival_time"
                                                       class="wpie-flight-input"
                                                       value="<?php echo esc_attr($flight_info->arrival_time ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Return Flight Section -->
                                    <div class="wpie-flight-section">
                                        <h5 class="wpie-flight-section-title"><?php echo __('Return Flight (From Competition)', 'wpie-parent-profile'); ?></h5>
                                        <div class="wpie-flight-form-row">
                                            <div class="wpie-flight-form-group">
                                                <label><?php echo __('Flight Number', 'wpie-parent-profile'); ?></label>
                                                <input type="text"
                                                       name="return_flight_number"
                                                       class="wpie-flight-input"
                                                       value="<?php echo esc_attr($flight_info->return_flight_number ?? ''); ?>"
                                                       placeholder="<?php echo __('e.g., TK456', 'wpie-parent-profile'); ?>">
                                            </div>
                                            <div class="wpie-flight-form-group">
                                                <label><?php echo __('Airline', 'wpie-parent-profile'); ?></label>
                                                <input type="text"
                                                       name="return_airline"
                                                       class="wpie-flight-input"
                                                       value="<?php echo esc_attr($flight_info->return_airline ?? ''); ?>"
                                                       placeholder="<?php echo __('e.g., Air Astana', 'wpie-parent-profile'); ?>">
                                            </div>
                                        </div>
                                        <div class="wpie-flight-form-row">
                                            <div class="wpie-flight-form-group">
                                                <label><?php echo __('From Airport', 'wpie-parent-profile'); ?></label>
                                                <select name="return_airport_from" class="wpie-flight-input">
                                                    <option value=""><?php echo __('Select Airport', 'wpie-parent-profile'); ?></option>
                                                    <?php foreach ($airports as $airport): ?>
                                                        <option value="<?php echo esc_attr($airport->airport_name); ?>"
                                                                <?php selected($flight_info->return_airport_from ?? '', $airport->airport_name); ?>>
                                                            <?php echo esc_html($airport->airport_name); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="wpie-flight-form-group">
                                                <label><?php echo __('To Airport', 'wpie-parent-profile'); ?></label>
                                                <input type="text"
                                                       name="return_airport_to"
                                                       class="wpie-flight-input"
                                                       value="<?php echo esc_attr($flight_info->return_airport_to ?? ''); ?>"
                                                       placeholder="<?php echo __('e.g., Almaty International Airport', 'wpie-parent-profile'); ?>">
                                            </div>
                                        </div>
                                        <div class="wpie-flight-form-row">
                                            <div class="wpie-flight-form-group">
                                                <label><?php echo __('Departure Date', 'wpie-parent-profile'); ?></label>
                                                <input type="date"
                                                       name="return_departure_date"
                                                       class="wpie-flight-input"
                                                       value="<?php echo esc_attr($flight_info->return_departure_date ?? ''); ?>">
                                            </div>
                                            <div class="wpie-flight-form-group">
                                                <label><?php echo __('Departure Time', 'wpie-parent-profile'); ?></label>
                                                <input type="time"
                                                       name="return_departure_time"
                                                       class="wpie-flight-input"
                                                       value="<?php echo esc_attr($flight_info->return_departure_time ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="wpie-flight-form-actions">
                                        <button type="submit" class="wpie-flight-save-btn">
                                            <?php echo __('Save Flight Information', 'wpie-parent-profile'); ?>
                                        </button>
                                        <span class="wpie-flight-save-message" style="display: none;"></span>
                                    </div>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Students Section -->
    <?php if (!empty($all_student_registrations)): ?>
    <section class="wpie-profile-section wpie-students">
        <h2 class="wpie-section-title"><?php echo __('My Children', 'wpie-parent-profile'); ?></h2>

        <!-- Desktop Table View -->
        <div class="wpie-students-table-view">
            <table class="wpie-students-table">
                <thead>
                    <tr>
                        <th class="wpie-col-number"><?php echo __('No', 'wpie-parent-profile'); ?></th>
                        <th><?php echo __('Competition', 'wpie-parent-profile'); ?></th>
                        <th><?php echo __('Name', 'wpie-parent-profile'); ?></th>
                        <th><?php echo __('Age Category', 'wpie-parent-profile'); ?></th>
                        <th><?php echo __('Date', 'wpie-parent-profile'); ?></th>
                        <th><?php echo __('Point', 'wpie-parent-profile'); ?></th>
                        <th><?php echo __('Rank', 'wpie-parent-profile'); ?></th>
                        <th><?php echo __('Video', 'wpie-parent-profile'); ?></th>
                        <th><?php echo __('Medal', 'wpie-parent-profile'); ?></th>
                        <th><?php echo __('Certificate', 'wpie-parent-profile'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $row_number = 1; foreach ($all_student_registrations as $reg): ?>
                    <tr>
                        <td class="wpie-col-number"><?php echo $row_number++; ?></td>
                        <td>
                            <div class="wpie-competition-cell">
                                <strong><?php echo esc_html($reg->competition_name); ?></strong>
                                <?php if ($reg->competition_date): ?>
                                <span class="wpie-competition-date"><?php echo date_i18n(get_option('date_format'), strtotime($reg->competition_date)); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="wpie-student-name-cell">
                                <strong><?php echo esc_html($reg->student_name); ?></strong>
                                <span class="wpie-student-email-cell"><?php echo esc_html($reg->student_email); ?></span>
                            </div>
                        </td>
                        <td><?php echo !empty($reg->student_age_category) ? esc_html($reg->student_age_category) : '-'; ?></td>
                        <td><?php echo !empty($reg->registration_date) ? date_i18n(get_option('date_format'), strtotime($reg->registration_date)) : '-'; ?></td>
                        <td><?php echo !empty($reg->student_point) ? number_format($reg->student_point, 2) : '-'; ?></td>
                        <td><?php echo !empty($reg->student_rank) ? '#' . $reg->student_rank : '-'; ?></td>
                        <td class="wpie-video-cell">
                            <?php if (!empty($reg->youtube_link)): ?>
                            <a href="<?php echo esc_url($reg->youtube_link); ?>" target="_blank" class="wpie-video-icon" title="<?php echo __('View Video', 'wpie-parent-profile'); ?>">
                                🎥
                            </a>
                            <?php else: ?>
                            <span class="wpie-no-video">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($reg->student_medal)):
                                $medal_class = 'wpie-medal-' . strtolower($reg->student_medal);
                            ?>
                            <div class="wpie-medal-container">
                                <span class="wpie-medal-badge <?php echo esc_attr($medal_class); ?>">
                                    <?php echo esc_html($reg->student_medal); ?>
                                </span>
                                <?php
                                // Prize information for WeVoi Summit top 3
                                $is_summit = (stripos($reg->competition_name, 'WeVoi Summit') !== false);
                                $is_top_3 = (!empty($reg->student_rank) && in_array(intval($reg->student_rank), [1, 2, 3]));
                                if ($is_summit && $is_top_3 && !empty($reg->claimed_prize_amount) && $reg->claimed_prize_amount > 0): ?>
                                    <div class="wpie-prize-info">
                                        <div class="wpie-prize-amount">
                                            <?php printf(__('Prize: £%s', 'wpie-parent-profile'), number_format((float)$reg->claimed_prize_amount, 2)); ?>
                                        </div>
                                        <?php if (!empty($reg->claimed_prize_paid_status)): ?>
                                            <div class="wpie-prize-payment-status">
                                                <span class="wpie-payment-status-badge wpie-status-<?php echo sanitize_html_class(strtolower(str_replace(' ', '-', $reg->claimed_prize_paid_status))); ?>">
                                                    <?php echo esc_html($reg->claimed_prize_paid_status); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($reg->claimed_summit_coupon_code)): ?>
                                <div class="wpie-summit-coupon-info">
                                    <strong><?php echo __('Global Summit Coupon:', 'wpie-parent-profile'); ?></strong>
                                    <?php echo esc_html($reg->claimed_summit_coupon_code); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <?php if (!empty($reg->claimed_summit_coupon_code)): ?>
                            <div class="wpie-summit-coupon-info">
                                <strong><?php echo __('Global Summit Coupon:', 'wpie-parent-profile'); ?></strong>
                                <?php echo esc_html($reg->claimed_summit_coupon_code); ?>
                            </div>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($reg->certificate_link)): ?>
                            <a href="<?php echo esc_url($reg->certificate_link); ?>" target="_blank" class="wpie-cert-link">
                                <?php echo __('View', 'wpie-parent-profile'); ?>
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

        <!-- Mobile Card View -->
        <div class="wpie-students-card-view">
            <?php $card_number = 1; foreach ($all_student_registrations as $reg): ?>
            <div class="wpie-student-registration-card">
                <div class="wpie-card-number">#<?php echo $card_number++; ?></div>
                <div class="wpie-card-header">
                    <div class="wpie-card-student-info">
                        <strong class="wpie-card-student-name"><?php echo esc_html($reg->student_name); ?></strong>
                        <span class="wpie-card-student-email"><?php echo esc_html($reg->student_email); ?></span>
                        <?php if (!empty($reg->student_age_category)): ?>
                        <span class="wpie-card-age-category"><?php echo esc_html($reg->student_age_category); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="wpie-card-body">
                    <div class="wpie-card-row">
                        <span class="wpie-card-label"><?php echo __('Competition:', 'wpie-parent-profile'); ?></span>
                        <span class="wpie-card-value">
                            <div class="wpie-card-competition-info">
                                <strong><?php echo esc_html($reg->competition_name); ?></strong>
                                <?php if ($reg->competition_date): ?>
                                <span class="wpie-card-competition-date"><?php echo date_i18n(get_option('date_format'), strtotime($reg->competition_date)); ?></span>
                                <?php endif; ?>
                            </div>
                        </span>
                    </div>
                    <div class="wpie-card-row">
                        <span class="wpie-card-label"><?php echo __('Date:', 'wpie-parent-profile'); ?></span>
                        <span class="wpie-card-value"><?php echo !empty($reg->registration_date) ? date_i18n(get_option('date_format'), strtotime($reg->registration_date)) : '-'; ?></span>
                    </div>
                    <div class="wpie-card-row">
                        <span class="wpie-card-label"><?php echo __('Point:', 'wpie-parent-profile'); ?></span>
                        <span class="wpie-card-value"><?php echo !empty($reg->student_point) ? number_format($reg->student_point, 2) : '-'; ?></span>
                    </div>
                    <div class="wpie-card-row">
                        <span class="wpie-card-label"><?php echo __('Rank:', 'wpie-parent-profile'); ?></span>
                        <span class="wpie-card-value"><?php echo !empty($reg->student_rank) ? '#' . $reg->student_rank : '-'; ?></span>
                    </div>
                    <div class="wpie-card-row">
                        <span class="wpie-card-label"><?php echo __('Video:', 'wpie-parent-profile'); ?></span>
                        <span class="wpie-card-value">
                            <?php if (!empty($reg->youtube_link)): ?>
                            <a href="<?php echo esc_url($reg->youtube_link); ?>" target="_blank" class="wpie-video-icon" title="<?php echo __('View Video', 'wpie-parent-profile'); ?>">
                                🎥
                            </a>
                            <?php else: ?>
                            <span class="wpie-no-video">-</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="wpie-card-row">
                        <span class="wpie-card-label"><?php echo __('Medal:', 'wpie-parent-profile'); ?></span>
                        <span class="wpie-card-value">
                            <?php if (!empty($reg->student_medal)):
                                $medal_class = 'wpie-medal-' . strtolower($reg->student_medal);
                            ?>
                            <div class="wpie-card-medal-container">
                                <span class="wpie-medal-badge <?php echo esc_attr($medal_class); ?>">
                                    <?php echo esc_html($reg->student_medal); ?>
                                </span>
                                <?php
                                // Prize information for WeVoi Summit top 3
                                $is_summit = (stripos($reg->competition_name, 'WeVoi Summit') !== false);
                                $is_top_3 = (!empty($reg->student_rank) && in_array(intval($reg->student_rank), [1, 2, 3]));
                                if ($is_summit && $is_top_3 && !empty($reg->claimed_prize_amount) && $reg->claimed_prize_amount > 0): ?>
                                    <div class="wpie-prize-info">
                                        <div class="wpie-prize-amount">
                                            <?php printf(__('Prize: £%s', 'wpie-parent-profile'), number_format((float)$reg->claimed_prize_amount, 2)); ?>
                                        </div>
                                        <?php if (!empty($reg->claimed_prize_paid_status)): ?>
                                            <div class="wpie-prize-payment-status">
                                                <span class="wpie-payment-status-badge wpie-status-<?php echo sanitize_html_class(strtolower(str_replace(' ', '-', $reg->claimed_prize_paid_status))); ?>">
                                                    <?php echo esc_html($reg->claimed_prize_paid_status); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($reg->claimed_summit_coupon_code)): ?>
                                <div class="wpie-summit-coupon-info">
                                    <strong><?php echo __('Global Summit Coupon:', 'wpie-parent-profile'); ?></strong>
                                    <?php echo esc_html($reg->claimed_summit_coupon_code); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <?php if (!empty($reg->claimed_summit_coupon_code)): ?>
                            <div class="wpie-summit-coupon-info">
                                <strong><?php echo __('Global Summit Coupon:', 'wpie-parent-profile'); ?></strong>
                                <?php echo esc_html($reg->claimed_summit_coupon_code); ?>
                            </div>
                            <?php else: ?>
                            -
                            <?php endif; ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if (!empty($reg->certificate_link)): ?>
                    <div class="wpie-card-row">
                        <span class="wpie-card-label"><?php echo __('Certificate:', 'wpie-parent-profile'); ?></span>
                        <span class="wpie-card-value">
                            <a href="<?php echo esc_url($reg->certificate_link); ?>" target="_blank" class="wpie-cert-link">
                                <?php echo __('View', 'wpie-parent-profile'); ?>
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php elseif (!empty($students)): ?>
    <section class="wpie-profile-section wpie-students">
        <h2 class="wpie-section-title"><?php echo __('My Children', 'wpie-parent-profile'); ?></h2>
        <p class="wpie-no-data"><?php echo __('No competition registrations found for your children.', 'wpie-parent-profile'); ?></p>
    </section>
    <?php endif; ?>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Define ajaxurl if not already defined
    if (typeof ajaxurl === 'undefined') {
        var ajaxurl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
    }

    // Get nonce from PHP
    var parentNonce = '<?php echo wp_create_nonce('wevoi_parent_nonce'); ?>';

    // Handle visa status update
    $('.wpie-visa-status-radio').on('change', function() {
        var $radio = $(this);
        var registrationId = $radio.data('registration-id');
        var visaStatus = $radio.val();
        var $radioGroup = $radio.closest('.wpie-visa-radio-group');

        console.log('Visa status change:', {
            registrationId: registrationId,
            visaStatus: visaStatus,
            nonce: parentNonce
        });

        // Disable all radios in this group while updating
        $radioGroup.find('input').prop('disabled', true);
        var $originalChecked = $radioGroup.find('input:checked').not($radio);
        var originalValue = $originalChecked.length > 0 ? $originalChecked.val() : '';

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wevoi_update_visa_status_parent',
                nonce: parentNonce,
                registration_id: registrationId,
                visa_status: visaStatus
            },
            success: function(response) {
                console.log('AJAX Success Response:', response);
                if (response.success) {
                    // Update original value for all radios in group
                    $radioGroup.find('input').each(function() {
                        $(this).data('original-value', $(this).is(':checked') ? $(this).val() : '');
                    });
                    // Show success message
                    var $message = $('<div class="wpie-visa-status-message" style="margin-top: 5px; padding: 5px; background: #d4edda; color: #155724; border-radius: 4px; font-size: 12px; text-align: center;"><?php echo esc_js(__('Visa status updated successfully', 'wpie-parent-profile')); ?></div>');
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
                    var errorMsg = (typeof response.data === 'string') ? response.data : (response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Failed to update visa status', 'wpie-parent-profile')); ?>');
                    console.error('AJAX Error:', response);
                    alert(errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Request Error:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
                // Revert to original selection on error
                if (originalValue) {
                    $radioGroup.find('input[value="' + originalValue + '"]').prop('checked', true);
                } else {
                    $radio.prop('checked', false);
                }
                alert('<?php echo esc_js(__('Error updating visa status. Please try again.', 'wpie-parent-profile')); ?>');
            },
            complete: function() {
                // Re-enable all radios
                $radioGroup.find('input').prop('disabled', false);
            }
        });
    });

    // Store original values on page load
    $('.wpie-visa-status-radio').each(function() {
        if ($(this).is(':checked')) {
            $(this).data('original-value', $(this).val());
        }
    });

    // Auto-select if only one option in dropdown (excluding "Select Airport")
    function autoSelectSingleOption() {
        $('.wpie-flight-info-form select.wpie-flight-input').each(function() {
            var $select = $(this);
            var $options = $select.find('option:not([value=""])');

            // If only one option exists (besides "Select Airport"), select it
            if ($options.length === 1 && !$select.val()) {
                $select.val($options.first().val()).trigger('change');
            }
        });
    }

    // Check and mark filled flight input fields
    function checkFilledFields() {
        $('.wpie-flight-info-form .wpie-flight-input').each(function() {
            var $input = $(this);
            var value = $input.val();

            // For select elements, check if value is not empty and not "Select Airport"
            if ($input.is('select')) {
                if (value && value !== '' && value !== '0') {
                    $input.addClass('filled');
                } else {
                    $input.removeClass('filled');
                }
            } else {
                // For text inputs, check if value is not empty
                if (value && value.trim() !== '') {
                    $input.addClass('filled');
                } else {
                    $input.removeClass('filled');
                }
            }
        });
    }

    // Auto-select single options on page load
    autoSelectSingleOption();

    // Check on page load
    checkFilledFields();

    // Check on input change
    $('.wpie-flight-info-form .wpie-flight-input').on('input change', function() {
        checkFilledFields();
    });

    // Handle flight information save
    $('.wpie-flight-info-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $saveBtn = $form.find('.wpie-flight-save-btn');
        var $message = $form.find('.wpie-flight-save-message');
        var registrationId = $form.data('registration-id');

        // Disable save button
        $saveBtn.prop('disabled', true).text('<?php echo esc_js(__('Saving...', 'wpie-parent-profile')); ?>');
        $message.hide().removeClass('success error');

        // Collect form data
        var formData = {
            action: 'wevoi_save_flight_info_parent',
            nonce: parentNonce,
            registration_id: registrationId
        };

        // Add all form fields
        $form.find('input').each(function() {
            var name = $(this).attr('name');
            var value = $(this).val();
            if (name) {
                formData[name] = value;
            }
        });

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $message.text('<?php echo esc_js(__('Flight information saved successfully', 'wpie-parent-profile')); ?>')
                            .addClass('success')
                            .show();
                    setTimeout(function() {
                        $message.fadeOut();
                    }, 3000);
                } else {
                    $message.text(response.data || '<?php echo esc_js(__('Failed to save flight information', 'wpie-parent-profile')); ?>')
                            .addClass('error')
                            .show();
                }
            },
            error: function() {
                $message.text('<?php echo esc_js(__('Error saving flight information. Please try again.', 'wpie-parent-profile')); ?>')
                        .addClass('error')
                        .show();
            },
            complete: function() {
                $saveBtn.prop('disabled', false).text('<?php echo esc_js(__('Save Flight Information', 'wpie-parent-profile')); ?>');
            }
        });
    });
});
</script>
