<?php
/**
 * Supervisor Profile Template
 *
 * @package WPIE_Supervisor_Profile
 */

// Direct access prevention
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Initialize profile class
$profile = new WPIE_Supervisor_Profile();
$supervisor_id = get_current_user_id();

// Get supervisor data
$supervisor = $profile->get_supervisor_data($supervisor_id);

// Check if supervisor data is valid
if (is_wp_error($supervisor)) {
    ?>
    <div class="wpie-supervisor-profile-error">
        <p><?php echo esc_html($supervisor->get_error_message()); ?></p>
    </div>
    <?php
    return;
}

// Get students
$students = $profile->get_students($supervisor_id);

// Get student IDs
$student_ids = !empty($students) ? wp_list_pluck($students, 'student_id') : array();

// Get student registrations
$registrations = $profile->get_student_registrations($student_ids);

// Get supervisor certificates
$certificates = $profile->get_certificates($supervisor_id);

// Get supervisor's own Forum registrations
$own_forum_registrations = $profile->get_own_forum_registrations($supervisor_id);

// Get statistics
$stats = $profile->get_statistics($supervisor_id);

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

// Unique competition names for My Students filter (dropdown order: newest competition date first).
$student_competition_filter_names = array();
if (!empty($all_student_registrations)) {
    $competition_newest_ts = array();
    foreach ($all_student_registrations as $reg) {
        $cname = isset($reg->competition_name) ? trim((string) $reg->competition_name) : '';
        if ($cname === '') {
            continue;
        }
        $ts = !empty($reg->competition_date) ? strtotime($reg->competition_date) : 0;
        if (!isset($competition_newest_ts[$cname]) || $ts > $competition_newest_ts[$cname]) {
            $competition_newest_ts[$cname] = $ts;
        }
    }
    arsort($competition_newest_ts, SORT_NUMERIC);
    $student_competition_filter_names = array_keys($competition_newest_ts);
}
?>

<div class="wpie-supervisor-profile-wrapper">
    <div class="wpie-supervisor-profile-header">
        <h1 class="wpie-profile-title"><?php echo __('My Dashboard', 'wpie-supervisor-profile'); ?></h1>
    </div>

    <!-- Award Ceremony Banner (RANKINGS_FINALIZED) -->
    <?php
    $ceremony_comp = $wpdb->get_row(
        "SELECT competition_name, award_ceremony_zoom_link, award_ceremony_date
         FROM {$wpdb->prefix}wevoi_competitions
         WHERE competition_status = 'RANKINGS_FINALIZED'
           AND award_ceremony_zoom_link IS NOT NULL
           AND award_ceremony_zoom_link != ''
         ORDER BY competition_code DESC
         LIMIT 1"
    );
    if ($ceremony_comp):
        $ceremony_date_str = '';
        if (!empty($ceremony_comp->award_ceremony_date)) {
            $ts = strtotime($ceremony_comp->award_ceremony_date);
            $ceremony_date_str = date_i18n('l, F j, Y', $ts) . ' at ' . date('H:i', $ts) . ' (UK Time)';
        }
    ?>
    <div style="background:linear-gradient(135deg,#6a11cb 0%,#2575fc 100%); border-radius:12px; padding:28px 32px; margin-bottom:28px; color:#fff; box-shadow:0 4px 20px rgba(37,117,252,.35);">
        <div style="display:flex; align-items:flex-start; gap:18px;">
            <div style="font-size:46px; line-height:1; flex-shrink:0;">🎉</div>
            <div>
                <h3 style="margin:0 0 10px; font-size:20px; font-weight:700; color:#fff;">
                    You're Invited to the <?php echo esc_html($ceremony_comp->competition_name); ?> Award Ceremony!
                </h3>
                <?php if ($ceremony_date_str): ?>
                <p style="display:inline-block; background:rgba(255,255,255,.18); border-radius:6px; padding:6px 14px; margin:0 0 14px; font-size:15px; color:#fff;">
                    📅 <strong><?php echo esc_html($ceremony_date_str); ?></strong>
                </p>
                <?php endif; ?>
                <p style="margin:0 0 18px; font-size:15px; line-height:1.6; color:rgba(255,255,255,.92);">
                    Evaluation of all submitted videos has been completed. We are delighted to invite you to our
                    <strong>Award Ceremony</strong> where the results and winners will be announced live.
                    Join us on Zoom and celebrate this special moment with participants from around the world!
                </p>
                <a href="<?php echo esc_url($ceremony_comp->award_ceremony_zoom_link); ?>"
                   target="_blank" rel="noopener noreferrer"
                   style="display:inline-block; background:#fff; color:#2575fc; font-weight:700; font-size:15px; padding:12px 24px; border-radius:8px; text-decoration:none; box-shadow:0 2px 8px rgba(0,0,0,.15);">
                    🎥 Join Award Ceremony on Zoom
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- WeVoi Forum 2026 Message -->
    <?php
    // Check if supervisor is registered for active Forum
    $is_registered_for_active_forum = $profile->is_registered_for_active_forum($supervisor_id);

    // Get ON AIR Forum competition details
    $active_forum = $profile->get_active_forum_competition();

    // Only show card when Forum is ON AIR and supervisor is not registered
    if ($active_forum && !$is_registered_for_active_forum):
    ?>
    <div class="forum-2026-banner">
        <div class="forum-banner-content">
            <div class="forum-icon">🌍</div>
            <div class="forum-message">
                <h3><?php _e('WeVoi Forum 2026: Global Experience Awaits You!', 'wpie-supervisor-profile'); ?></h3>
                <p>
                    <?php _e('WeVoi Forum is your opportunity to join a global learning experience with educators and participants from around the world. Through workshops, city explorations, and cultural exchange, you\'ll connect with fellow supervisors, expand your professional network, and be part of an enriching international community.', 'wpie-supervisor-profile'); ?>
                </p>
                <?php if ($active_forum): ?>
                <div class="forum-event-info" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255, 255, 255, 0.3);">
                    <?php if (!empty($active_forum->event_venue)): ?>
                    <div style="margin-bottom: 8px;">
                        <strong><?php _e('Event Venue:', 'wpie-supervisor-profile'); ?></strong>
                        <span><?php echo esc_html($active_forum->event_venue); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($active_forum->competition_date)): ?>
                    <div style="margin-bottom: 8px;">
                        <strong><?php _e('Start Date:', 'wpie-supervisor-profile'); ?></strong>
                        <span><?php echo date_i18n(get_option('date_format'), strtotime($active_forum->competition_date)); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($active_forum->event_duration)): ?>
                    <div style="margin-bottom: 8px;">
                        <strong><?php _e('Duration:', 'wpie-supervisor-profile'); ?></strong>
                        <span><?php echo esc_html($active_forum->event_duration); ?> <?php echo ($active_forum->event_duration == 1) ? __('day', 'wpie-supervisor-profile') : __('days', 'wpie-supervisor-profile'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="forum-action">
                    <a href="https://wevoi.org/register-forum" target="_blank" class="forum-register-button">
                        <?php _e('Register Now', 'wpie-supervisor-profile'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; // End check for active Forum registration ?>

    <!-- Supervisor Personal Information -->
    <section class="wpie-profile-section wpie-personal-info">
        <h2 class="wpie-section-title"><?php echo __('Personal Information', 'wpie-supervisor-profile'); ?></h2>
        <div class="wpie-info-grid">
            <!-- Left Column: Name and Email -->
            <div class="wpie-info-column">
                <div class="wpie-info-item">
                    <span class="wpie-info-label"><?php echo __('Name:', 'wpie-supervisor-profile'); ?></span>
                    <span class="wpie-info-value">
                        <?php
                        $supervisor_first_name = get_user_meta($supervisor_id, 'first_name', true);
                        $supervisor_last_name = get_user_meta($supervisor_id, 'last_name', true);
                        $supervisor_first_name = !empty($supervisor_first_name) ? mb_convert_case($supervisor_first_name, MB_CASE_TITLE, 'UTF-8') : '';
                        $supervisor_last_name = !empty($supervisor_last_name) ? mb_convert_case($supervisor_last_name, MB_CASE_TITLE, 'UTF-8') : '';
                        $supervisor_full_name = trim($supervisor_first_name . ' ' . $supervisor_last_name);
                        if (empty($supervisor_full_name)) {
                            $supervisor_full_name = mb_convert_case($supervisor->display_name, MB_CASE_TITLE, 'UTF-8');
                        }
                        echo esc_html($supervisor_full_name);
                        ?>
                    </span>
                </div>
                <div class="wpie-info-item">
                    <span class="wpie-info-label"><?php echo __('Email:', 'wpie-supervisor-profile'); ?></span>
                    <span class="wpie-info-value wpie-email-value"><?php echo esc_html($supervisor->user_email); ?></span>
                </div>
            </div>
            <!-- Right Column: Country and School -->
            <div class="wpie-info-column">
                <?php if (!empty($supervisor->countryname)): ?>
                <div class="wpie-info-item">
                    <span class="wpie-info-label"><?php echo __('Country:', 'wpie-supervisor-profile'); ?></span>
                    <span class="wpie-info-value"><?php echo esc_html($supervisor->countryname); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($supervisor->schoolname)): ?>
                <div class="wpie-info-item">
                    <span class="wpie-info-label"><?php echo __('School:', 'wpie-supervisor-profile'); ?></span>
                    <span class="wpie-info-value"><?php echo esc_html($supervisor->schoolname); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php
    if (defined('WEVOI_CM_PLUGIN_DIR') && file_exists(WEVOI_CM_PLUGIN_DIR . 'admin/views/commission-bank-details-form.php')) {
        $user_id = $supervisor_id;
        ?>
        <section class="wpie-profile-section wevoi-bank-details-trigger-section">
            <h2 class="wevoi-bank-details-trigger-title"><?php _e('Bank details for commission payments', 'wpie-supervisor-profile'); ?></h2>
            <p class="wevoi-bank-details-trigger-desc"><?php _e('If you receive commission from WeVoi Spark, Reload or Summit registrations, you can enter your bank details here so we can pay you. All fields are optional but help us process payments correctly.', 'wpie-supervisor-profile'); ?></p>
            <button type="button" class="wevoi-bank-details-open-btn" id="wevoi-bank-details-open-btn" aria-controls="wevoi-bank-details-modal" aria-expanded="false">
                <?php _e('Bank details', 'wpie-supervisor-profile'); ?>
            </button>
        </section>
        <div id="wevoi-bank-details-modal" class="wevoi-bank-details-modal" role="dialog" aria-labelledby="wevoi-bank-details-modal-title" aria-modal="true" aria-hidden="true">
            <div class="wevoi-bank-details-modal-backdrop" aria-hidden="true"></div>
            <div class="wevoi-bank-details-modal-content">
                <button type="button" class="wevoi-bank-details-modal-close" aria-label="<?php esc_attr_e('Close', 'wpie-supervisor-profile'); ?>">&times;</button>
                <?php include WEVOI_CM_PLUGIN_DIR . 'admin/views/commission-bank-details-form.php'; ?>
            </div>
        </div>
        <script type="text/javascript">
        jQuery(function($) {
            var $modal = $('#wevoi-bank-details-modal');
            var $btn = $('#wevoi-bank-details-open-btn');
            function openModal() { $modal.attr('aria-hidden', 'false'); $btn.attr('aria-expanded', 'true'); $(document).on('keydown.wevoiBankModal', function(e) { if (e.key === 'Escape') closeModal(); }); }
            function closeModal() { $modal.attr('aria-hidden', 'true'); $btn.attr('aria-expanded', 'false'); $(document).off('keydown.wevoiBankModal'); }
            $btn.on('click', openModal);
            $modal.find('.wevoi-bank-details-modal-close, .wevoi-bank-details-modal-backdrop').on('click', closeModal);
            $(document).on('wevoiBankDetailsSaved', closeModal);
        });
        </script>
        <?php
    }
    ?>

    <!-- Statistics Overview -->
    <section class="wpie-profile-section wpie-statistics">
        <h2 class="wpie-section-title"><?php echo __('Statistics Overview', 'wpie-supervisor-profile'); ?></h2>
        <div class="wpie-stats-grid">
            <div class="wpie-stat-card">
                <div class="wpie-stat-icon">👥</div>
                <div class="wpie-stat-content">
                    <div class="wpie-stat-value"><?php echo number_format($stats['total_students']); ?></div>
                    <div class="wpie-stat-label"><?php echo __('Total Students', 'wpie-supervisor-profile'); ?></div>
                </div>
            </div>
            <div class="wpie-stat-card">
                <div class="wpie-stat-icon">📝</div>
                <div class="wpie-stat-content">
                    <div class="wpie-stat-value"><?php echo number_format($stats['total_registrations']); ?></div>
                    <div class="wpie-stat-label"><?php echo __('Total Registrations', 'wpie-supervisor-profile'); ?></div>
                </div>
            </div>
            <div class="wpie-stat-card">
                <div class="wpie-stat-icon">🎥</div>
                <div class="wpie-stat-content">
                    <div class="wpie-stat-value"><?php echo number_format($stats['video_submissions']); ?></div>
                    <div class="wpie-stat-label"><?php echo __('Video Submissions', 'wpie-supervisor-profile'); ?></div>
                </div>
            </div>
            <div class="wpie-stat-card">
                <div class="wpie-stat-icon">🏆</div>
                <div class="wpie-stat-content">
                    <div class="wpie-stat-value"><?php echo number_format($stats['total_certificates']); ?></div>
                    <div class="wpie-stat-label"><?php echo __('Total Certificates', 'wpie-supervisor-profile'); ?></div>
                </div>
            </div>
            <?php if ($stats['active_registrations'] > 0): ?>
            <div class="wpie-stat-card">
                <div class="wpie-stat-icon">⭐</div>
                <div class="wpie-stat-content">
                    <div class="wpie-stat-value"><?php echo number_format($stats['average_point'], 2); ?></div>
                    <div class="wpie-stat-label"><?php echo __('Average Point', 'wpie-supervisor-profile'); ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Medal Statistics -->
        <?php if ($stats['gold_medals'] > 0 || $stats['silver_medals'] > 0 || $stats['bronze_medals'] > 0 || $stats['success_certificates'] > 0): ?>
        <div class="wpie-medal-stats">
            <h3 class="wpie-subsection-title"><?php echo __('Medal Statistics', 'wpie-supervisor-profile'); ?></h3>
            <div class="wpie-medal-grid">
                <?php if ($stats['gold_medals'] > 0): ?>
                <div class="wpie-medal-item wpie-medal-gold">
                    <span class="wpie-medal-icon">🥇</span>
                    <span class="wpie-medal-count"><?php echo number_format($stats['gold_medals']); ?></span>
                    <span class="wpie-medal-label"><?php echo __('Gold', 'wpie-supervisor-profile'); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($stats['silver_medals'] > 0): ?>
                <div class="wpie-medal-item wpie-medal-silver">
                    <span class="wpie-medal-icon">🥈</span>
                    <span class="wpie-medal-count"><?php echo number_format($stats['silver_medals']); ?></span>
                    <span class="wpie-medal-label"><?php echo __('Silver', 'wpie-supervisor-profile'); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($stats['bronze_medals'] > 0): ?>
                <div class="wpie-medal-item wpie-medal-bronze">
                    <span class="wpie-medal-icon">🥉</span>
                    <span class="wpie-medal-count"><?php echo number_format($stats['bronze_medals']); ?></span>
                    <span class="wpie-medal-label"><?php echo __('Bronze', 'wpie-supervisor-profile'); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($stats['success_certificates'] > 0): ?>
                <div class="wpie-medal-item wpie-medal-success">
                    <span class="wpie-medal-icon">✓</span>
                    <span class="wpie-medal-count"><?php echo number_format($stats['success_certificates']); ?></span>
                    <span class="wpie-medal-label"><?php echo __('Success', 'wpie-supervisor-profile'); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Qualification, Final, Accepted, Forum — single responsive row (e.g. Qualification + Accepted side by side) -->
        <?php
        $has_qualification = $stats['competition_type_stats']['qualification'] > 0;
        $has_final = $stats['competition_type_stats']['final'] > 0;
        $has_accepted = $stats['application_status_stats']['application_accepted'] > 0;
        $has_forum = $stats['competition_type_stats']['forum'] > 0;
        $has_secondary_stats = $has_qualification || $has_final || $has_accepted || $has_forum;
        ?>
        <?php if ($has_secondary_stats): ?>
        <div class="wpie-secondary-stats-grid">
            <?php if ($has_qualification): ?>
            <div class="wpie-stat-item wpie-comp-type-item">
                <span class="wpie-comp-type-label"><?php echo __('Qualification:', 'wpie-supervisor-profile'); ?></span>
                <span class="wpie-comp-type-value"><?php echo number_format($stats['competition_type_stats']['qualification']); ?></span>
            </div>
            <?php endif; ?>

            <?php if ($has_final): ?>
            <div class="wpie-stat-item wpie-comp-type-item">
                <span class="wpie-comp-type-label"><?php echo __('Final:', 'wpie-supervisor-profile'); ?></span>
                <span class="wpie-comp-type-value"><?php echo number_format($stats['competition_type_stats']['final']); ?></span>
            </div>
            <?php endif; ?>

            <?php if ($has_accepted): ?>
            <div class="wpie-accepted-forum-card wpie-status-accepted-card">
                <div class="wpie-accepted-forum-card-content">
                    <span class="wpie-accepted-forum-label"><?php echo __('Accepted:', 'wpie-supervisor-profile'); ?></span>
                    <span class="wpie-accepted-forum-value"><?php echo number_format($stats['application_status_stats']['application_accepted']); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($has_forum): ?>
            <div class="wpie-accepted-forum-card wpie-forum-stat-card">
                <div class="wpie-accepted-forum-card-content">
                    <span class="wpie-accepted-forum-label"><?php echo __('Forum:', 'wpie-supervisor-profile'); ?></span>
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
            $other_status_items[] = array('key' => 'pending_review', 'label' => __('Pending Review:', 'wpie-supervisor-profile'), 'value' => $stats['application_status_stats']['pending_review'], 'class' => 'wpie-status-pending');
        }
        if ($stats['application_status_stats']['video_inaccessible'] > 0) {
            $other_status_items[] = array('key' => 'video_inaccessible', 'label' => __('Video Inaccessible:', 'wpie-supervisor-profile'), 'value' => $stats['application_status_stats']['video_inaccessible'], 'class' => 'wpie-status-inaccessible');
        }
        if ($stats['application_status_stats']['irrelevant_content'] > 0) {
            $other_status_items[] = array('key' => 'irrelevant_content', 'label' => __('Irrelevant Content:', 'wpie-supervisor-profile'), 'value' => $stats['application_status_stats']['irrelevant_content'], 'class' => 'wpie-status-irrelevant');
        }
        if ($stats['application_status_stats']['application_rejected'] > 0) {
            $other_status_items[] = array('key' => 'application_rejected', 'label' => __('Rejected:', 'wpie-supervisor-profile'), 'value' => $stats['application_status_stats']['application_rejected'], 'class' => 'wpie-status-rejected');
        }
        $has_other_status = !empty($other_status_items);
        ?>
        <?php if ($has_other_status): ?>
        <div class="wpie-other-status-stats" style="margin-top: 30px;">
            <h3 class="wpie-subsection-title"><?php echo __('Application Status', 'wpie-supervisor-profile'); ?></h3>
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

    <!-- Supervisor Certificates -->
    <?php if (!empty($certificates)): ?>
    <section class="wpie-profile-section wpie-certificates">
        <h2 class="wpie-section-title"><?php echo __('My Certificates', 'wpie-supervisor-profile'); ?></h2>
        <div class="wpie-certificates-list">
            <table class="wpie-certificates-table">
                <thead>
                    <tr>
                        <th><?php echo __('Competition', 'wpie-supervisor-profile'); ?></th>
                        <th><?php echo __('Competition Date', 'wpie-supervisor-profile'); ?></th>
                        <th><?php echo __('Issue Date', 'wpie-supervisor-profile'); ?></th>
                        <th><?php echo __('Certificate', 'wpie-supervisor-profile'); ?></th>
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
                                <?php echo __('View Certificate', 'wpie-supervisor-profile'); ?>
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

    <!-- Supervisor's Own Forum Registrations -->
    <?php if (!empty($own_forum_registrations)): ?>
    <section class="wpie-profile-section wpie-forum-registrations">
        <h2 class="wpie-section-title"><?php echo __('My Forum Registrations', 'wpie-supervisor-profile'); ?></h2>
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
                                <span class="wpie-info-label"><?php echo __('Registration Date', 'wpie-supervisor-profile'); ?>:</span>
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
                                <label class="wpie-forum-card-title"><?php echo __('Event Information', 'wpie-supervisor-profile'); ?></label>
                                <?php if (!empty($reg->event_venue)): ?>
                                    <div class="wpie-info-item" style="margin-bottom: 10px;">
                                        <span class="wpie-info-label" style="font-weight: 600;"><?php echo __('Event Venue', 'wpie-supervisor-profile'); ?>:</span>
                                        <span class="wpie-info-value"><?php echo esc_html($reg->event_venue); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($reg->competition_date)): ?>
                                    <div class="wpie-info-item" style="margin-bottom: 10px;">
                                        <span class="wpie-info-label" style="font-weight: 600;"><?php echo __('Event Date', 'wpie-supervisor-profile'); ?>:</span>
                                        <span class="wpie-info-value"><?php echo date_i18n(get_option('date_format'), strtotime($reg->competition_date)); ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($reg->event_duration)): ?>
                                    <div class="wpie-info-item" style="margin-bottom: 10px;">
                                        <span class="wpie-info-label" style="font-weight: 600;"><?php echo __('Duration', 'wpie-supervisor-profile'); ?>:</span>
                                        <span class="wpie-info-value"><?php echo esc_html($reg->event_duration); ?> <?php echo ($reg->event_duration == 1) ? __('day', 'wpie-supervisor-profile') : __('days', 'wpie-supervisor-profile'); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Payment Information -->
                            <div class="wpie-forum-payment-info">
                                <label class="wpie-forum-card-title"><?php echo __('Payment Information', 'wpie-supervisor-profile'); ?></label>
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
                                    $forum_status_label = __('Pre-registered', 'wpie-supervisor-profile');
                                } else {
                                    $forum_status_label = $forum_paid_raw !== '' ? $forum_paid_raw : __('Pending', 'wpie-supervisor-profile');
                                }
                                $forum_paid_class = sanitize_html_class(strtolower($forum_reg_ps));
                                ?>
                                <div class="wpie-info-item" style="margin-bottom: 8px;">
                                    <span class="wpie-info-label"><?php echo __('Status', 'wpie-supervisor-profile'); ?>:</span>
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
                                        <span class="wpie-info-label"><?php echo __('Price', 'wpie-supervisor-profile'); ?>:</span>
                                        <span class="wpie-info-value">£<?php echo number_format((float)$reg->price, 2); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($reg->coupon_code)): ?>
                                    <div class="wpie-info-item" style="margin-bottom: 8px;">
                                        <span class="wpie-info-label"><?php echo __('Coupon Code', 'wpie-supervisor-profile'); ?>:</span>
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
                                <label class="wpie-forum-card-title"><?php echo __('Visa Status', 'wpie-supervisor-profile'); ?></label>
                                <div class="wpie-visa-status-radio-wrapper">
                                    <div class="wpie-visa-radio-group">
                                        <label class="wpie-visa-radio-label">
                                            <input type="radio"
                                                   name="visa_status_<?php echo esc_attr($reg->register_id); ?>"
                                                   class="wpie-visa-status-radio"
                                                   data-registration-id="<?php echo esc_attr($reg->register_id); ?>"
                                                   value="I do not need a visa"
                                                   <?php checked($reg->visa_status ?? '', 'I do not need a visa'); ?>>
                                            <span><?php echo __('I do not need a visa', 'wpie-supervisor-profile'); ?></span>
                                        </label>
                                        <label class="wpie-visa-radio-label">
                                            <input type="radio"
                                                   name="visa_status_<?php echo esc_attr($reg->register_id); ?>"
                                                   class="wpie-visa-status-radio"
                                                   data-registration-id="<?php echo esc_attr($reg->register_id); ?>"
                                                   value="I currently hold a valid visa"
                                                   <?php checked($reg->visa_status ?? '', 'I currently hold a valid visa'); ?>>
                                            <span><?php echo __('I currently hold a valid visa', 'wpie-supervisor-profile'); ?></span>
                                        </label>
                                        <label class="wpie-visa-radio-label">
                                            <input type="radio"
                                                   name="visa_status_<?php echo esc_attr($reg->register_id); ?>"
                                                   class="wpie-visa-status-radio"
                                                   data-registration-id="<?php echo esc_attr($reg->register_id); ?>"
                                                   value="I have not yet submitted a visa application"
                                                   <?php checked($reg->visa_status ?? '', 'I have not yet submitted a visa application'); ?>>
                                            <span><?php echo __('I have not yet submitted a visa application', 'wpie-supervisor-profile'); ?></span>
                                        </label>
                                        <label class="wpie-visa-radio-label">
                                            <input type="radio"
                                                   name="visa_status_<?php echo esc_attr($reg->register_id); ?>"
                                                   class="wpie-visa-status-radio"
                                                   data-registration-id="<?php echo esc_attr($reg->register_id); ?>"
                                                   value="My visa application is under evaluation"
                                                   <?php checked($reg->visa_status ?? '', 'My visa application is under evaluation'); ?>>
                                            <span><?php echo __('My visa application is under evaluation', 'wpie-supervisor-profile'); ?></span>
                                        </label>
                                        <label class="wpie-visa-radio-label">
                                            <input type="radio"
                                                   name="visa_status_<?php echo esc_attr($reg->register_id); ?>"
                                                   class="wpie-visa-status-radio"
                                                   data-registration-id="<?php echo esc_attr($reg->register_id); ?>"
                                                   value="My visa application has been rejected"
                                                   <?php checked($reg->visa_status ?? '', 'My visa application has been rejected'); ?>>
                                            <span><?php echo __('My visa application has been rejected', 'wpie-supervisor-profile'); ?></span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Flight Information -->
                            <div class="wpie-forum-flight-info">
                                <label class="wpie-forum-card-title"><?php echo __('Flight Information', 'wpie-supervisor-profile'); ?></label>
                                <form class="wpie-flight-info-form" data-registration-id="<?php echo esc_attr($reg->register_id); ?>">
                                    <!-- Arrival Flight Section -->
                                    <div class="wpie-flight-section">
                                        <h5 class="wpie-flight-section-title"><?php echo __('Arrival Flight (To Competition)', 'wpie-supervisor-profile'); ?></h5>
                                        <div class="wpie-flight-form-row">
                                            <div class="wpie-flight-form-group">
                                                <label><?php echo __('Flight Number', 'wpie-supervisor-profile'); ?></label>
                                                <input type="text"
                                                       name="arrival_flight_number"
                                                       class="wpie-flight-input"
                                                       value="<?php echo esc_attr($flight_info->arrival_flight_number ?? ''); ?>"
                                                       placeholder="<?php echo __('e.g., TK123', 'wpie-supervisor-profile'); ?>">
                                            </div>
                                            <div class="wpie-flight-form-group">
                                                <label><?php echo __('Airline', 'wpie-supervisor-profile'); ?></label>
                                                <input type="text"
                                                       name="arrival_airline"
                                                       class="wpie-flight-input"
                                                       value="<?php echo esc_attr($flight_info->arrival_airline ?? ''); ?>"
                                                       placeholder="<?php echo __('e.g., Air Astana', 'wpie-supervisor-profile'); ?>">
                                            </div>
                                        </div>
                                        <div class="wpie-flight-form-row">
                                            <div class="wpie-flight-form-group">
                                                <label><?php echo __('From Airport', 'wpie-supervisor-profile'); ?></label>
                                                <input type="text"
                                                       name="arrival_airport_from"
                                                       class="wpie-flight-input"
                                                       value="<?php echo esc_attr($flight_info->arrival_airport_from ?? ''); ?>"
                                                       placeholder="<?php echo __('e.g., Almaty International Airport', 'wpie-supervisor-profile'); ?>">
                                            </div>
                                            <div class="wpie-flight-form-group">
                                                <label><?php echo __('To Airport', 'wpie-supervisor-profile'); ?></label>
                                                <select name="arrival_airport_to" class="wpie-flight-input">
                                                    <option value=""><?php echo __('Select Airport', 'wpie-supervisor-profile'); ?></option>
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
                                                <label><?php echo __('Arrival Date', 'wpie-supervisor-profile'); ?></label>
                                                <input type="date"
                                                       name="arrival_date"
                                                       class="wpie-flight-input"
                                                       value="<?php echo esc_attr($flight_info->arrival_date ?? ''); ?>">
                                            </div>
                                            <div class="wpie-flight-form-group">
                                                <label><?php echo __('Arrival Time', 'wpie-supervisor-profile'); ?></label>
                                                <input type="time"
                                                       name="arrival_time"
                                                       class="wpie-flight-input"
                                                       value="<?php echo esc_attr($flight_info->arrival_time ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Return Flight Section -->
                                    <div class="wpie-flight-section">
                                        <h5 class="wpie-flight-section-title"><?php echo __('Return Flight (From Competition)', 'wpie-supervisor-profile'); ?></h5>
                                        <div class="wpie-flight-form-row">
                                            <div class="wpie-flight-form-group">
                                                <label><?php echo __('Flight Number', 'wpie-supervisor-profile'); ?></label>
                                                <input type="text"
                                                       name="return_flight_number"
                                                       class="wpie-flight-input"
                                                       value="<?php echo esc_attr($flight_info->return_flight_number ?? ''); ?>"
                                                       placeholder="<?php echo __('e.g., TK456', 'wpie-supervisor-profile'); ?>">
                                            </div>
                                            <div class="wpie-flight-form-group">
                                                <label><?php echo __('Airline', 'wpie-supervisor-profile'); ?></label>
                                                <input type="text"
                                                       name="return_airline"
                                                       class="wpie-flight-input"
                                                       value="<?php echo esc_attr($flight_info->return_airline ?? ''); ?>"
                                                       placeholder="<?php echo __('e.g., Air Astana', 'wpie-supervisor-profile'); ?>">
                                            </div>
                                        </div>
                                        <div class="wpie-flight-form-row">
                                            <div class="wpie-flight-form-group">
                                                <label><?php echo __('From Airport', 'wpie-supervisor-profile'); ?></label>
                                                <select name="return_airport_from" class="wpie-flight-input">
                                                    <option value=""><?php echo __('Select Airport', 'wpie-supervisor-profile'); ?></option>
                                                    <?php foreach ($airports as $airport): ?>
                                                        <option value="<?php echo esc_attr($airport->airport_name); ?>"
                                                                <?php selected($flight_info->return_airport_from ?? '', $airport->airport_name); ?>>
                                                            <?php echo esc_html($airport->airport_name); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="wpie-flight-form-group">
                                                <label><?php echo __('To Airport', 'wpie-supervisor-profile'); ?></label>
                                                <input type="text"
                                                       name="return_airport_to"
                                                       class="wpie-flight-input"
                                                       value="<?php echo esc_attr($flight_info->return_airport_to ?? ''); ?>"
                                                       placeholder="<?php echo __('e.g., Almaty International Airport', 'wpie-supervisor-profile'); ?>">
                                            </div>
                                        </div>
                                        <div class="wpie-flight-form-row">
                                            <div class="wpie-flight-form-group">
                                                <label><?php echo __('Departure Date', 'wpie-supervisor-profile'); ?></label>
                                                <input type="date"
                                                       name="return_departure_date"
                                                       class="wpie-flight-input"
                                                       value="<?php echo esc_attr($flight_info->return_departure_date ?? ''); ?>">
                                            </div>
                                            <div class="wpie-flight-form-group">
                                                <label><?php echo __('Departure Time', 'wpie-supervisor-profile'); ?></label>
                                                <input type="time"
                                                       name="return_departure_time"
                                                       class="wpie-flight-input"
                                                       value="<?php echo esc_attr($flight_info->return_departure_time ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="wpie-flight-form-actions">
                                        <button type="submit" class="wpie-flight-save-btn">
                                            <?php echo __('Save Flight Information', 'wpie-supervisor-profile'); ?>
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
        <h2 class="wpie-section-title"><?php echo __('My Students', 'wpie-supervisor-profile'); ?></h2>

        <?php if (!empty($student_competition_filter_names)) : ?>
        <div class="wpie-students-filter-bar">
            <label for="wpie-students-competition-filter" class="wpie-students-filter-label"><?php echo esc_html__('Filter by competition', 'wpie-supervisor-profile'); ?></label>
            <select id="wpie-students-competition-filter" class="wpie-students-competition-select" aria-label="<?php echo esc_attr__('Filter students by competition', 'wpie-supervisor-profile'); ?>">
                <option value=""><?php echo esc_html__('All competitions', 'wpie-supervisor-profile'); ?></option>
                <?php foreach ($student_competition_filter_names as $filter_comp_name) : ?>
                <option value="<?php echo esc_attr($filter_comp_name); ?>"><?php echo esc_html($filter_comp_name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <p class="wpie-students-filter-empty" hidden><?php echo esc_html__('No students match this competition.', 'wpie-supervisor-profile'); ?></p>
        <?php endif; ?>

        <!-- Desktop Table View -->
        <div class="wpie-students-table-view">
            <table class="wpie-students-table">
                <thead>
                    <tr>
                        <th class="wpie-col-number"><?php echo __('No', 'wpie-supervisor-profile'); ?></th>
                        <th><?php echo __('Competition', 'wpie-supervisor-profile'); ?></th>
                        <th><?php echo __('Name', 'wpie-supervisor-profile'); ?></th>
                        <th><?php echo __('Age Category', 'wpie-supervisor-profile'); ?></th>
                        <th><?php echo __('Date', 'wpie-supervisor-profile'); ?></th>
                        <th><?php echo __('Point', 'wpie-supervisor-profile'); ?></th>
                        <th><?php echo __('Rank', 'wpie-supervisor-profile'); ?></th>
                        <th><?php echo __('Video', 'wpie-supervisor-profile'); ?></th>
                        <th><?php echo __('Medal', 'wpie-supervisor-profile'); ?></th>
                        <th><?php echo __('Certificate', 'wpie-supervisor-profile'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $row_number = 1; foreach ($all_student_registrations as $reg): ?>
                    <tr data-competition-name="<?php echo esc_attr($reg->competition_name); ?>">
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
                            <a href="<?php echo esc_url($reg->youtube_link); ?>" target="_blank" class="wpie-video-icon" title="<?php echo __('View Video', 'wpie-supervisor-profile'); ?>">
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
                                            <?php printf(__('Prize: £%s', 'wpie-supervisor-profile'), number_format((float)$reg->claimed_prize_amount, 2)); ?>
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
                                    <strong><?php echo __('Global Summit Coupon:', 'wpie-supervisor-profile'); ?></strong>
                                    <?php echo esc_html($reg->claimed_summit_coupon_code); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <?php if (!empty($reg->claimed_summit_coupon_code)): ?>
                            <div class="wpie-summit-coupon-info">
                                <strong><?php echo __('Global Summit Coupon:', 'wpie-supervisor-profile'); ?></strong>
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
                                <?php echo __('View', 'wpie-supervisor-profile'); ?>
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
            <div class="wpie-student-registration-card" data-competition-name="<?php echo esc_attr($reg->competition_name); ?>">
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
                        <span class="wpie-card-label"><?php echo __('Competition:', 'wpie-supervisor-profile'); ?></span>
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
                        <span class="wpie-card-label"><?php echo __('Date:', 'wpie-supervisor-profile'); ?></span>
                        <span class="wpie-card-value"><?php echo !empty($reg->registration_date) ? date_i18n(get_option('date_format'), strtotime($reg->registration_date)) : '-'; ?></span>
                    </div>
                    <div class="wpie-card-row">
                        <span class="wpie-card-label"><?php echo __('Point:', 'wpie-supervisor-profile'); ?></span>
                        <span class="wpie-card-value"><?php echo !empty($reg->student_point) ? number_format($reg->student_point, 2) : '-'; ?></span>
                    </div>
                    <div class="wpie-card-row">
                        <span class="wpie-card-label"><?php echo __('Rank:', 'wpie-supervisor-profile'); ?></span>
                        <span class="wpie-card-value"><?php echo !empty($reg->student_rank) ? '#' . $reg->student_rank : '-'; ?></span>
                    </div>
                    <div class="wpie-card-row">
                        <span class="wpie-card-label"><?php echo __('Video:', 'wpie-supervisor-profile'); ?></span>
                        <span class="wpie-card-value">
                            <?php if (!empty($reg->youtube_link)): ?>
                            <a href="<?php echo esc_url($reg->youtube_link); ?>" target="_blank" class="wpie-video-icon" title="<?php echo __('View Video', 'wpie-supervisor-profile'); ?>">
                                🎥
                            </a>
                            <?php else: ?>
                            <span class="wpie-no-video">-</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="wpie-card-row">
                        <span class="wpie-card-label"><?php echo __('Medal:', 'wpie-supervisor-profile'); ?></span>
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
                                            <?php printf(__('Prize: £%s', 'wpie-supervisor-profile'), number_format((float)$reg->claimed_prize_amount, 2)); ?>
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
                                    <strong><?php echo __('Global Summit Coupon:', 'wpie-supervisor-profile'); ?></strong>
                                    <?php echo esc_html($reg->claimed_summit_coupon_code); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <?php if (!empty($reg->claimed_summit_coupon_code)): ?>
                            <div class="wpie-summit-coupon-info">
                                <strong><?php echo __('Global Summit Coupon:', 'wpie-supervisor-profile'); ?></strong>
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
                        <span class="wpie-card-label"><?php echo __('Certificate:', 'wpie-supervisor-profile'); ?></span>
                        <span class="wpie-card-value">
                            <a href="<?php echo esc_url($reg->certificate_link); ?>" target="_blank" class="wpie-cert-link">
                                <?php echo __('View', 'wpie-supervisor-profile'); ?>
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
        <h2 class="wpie-section-title"><?php echo __('My Students', 'wpie-supervisor-profile'); ?></h2>
        <p class="wpie-no-data"><?php echo __('No competition registrations found for your students.', 'wpie-supervisor-profile'); ?></p>
    </section>
    <?php endif; ?>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // My Students: filter rows by competition name
    var $compFilter = $('#wpie-students-competition-filter');
    if ($compFilter.length) {
        var $filterEmpty = $('.wpie-students-filter-empty');

        function wpieApplyStudentsCompetitionFilter() {
            var selected = $compFilter.val() || '';
            var visible = 0;
            $('.wpie-students-table tbody tr').each(function() {
                var $tr = $(this);
                var name = $tr.attr('data-competition-name') || '';
                var show = !selected || name === selected;
                $tr.toggle(show);
                if (show) {
                    $tr.find('.wpie-col-number').text(++visible);
                }
            });
            visible = 0;
            $('.wpie-students-card-view .wpie-student-registration-card').each(function() {
                var $card = $(this);
                var name = $card.attr('data-competition-name') || '';
                var show = !selected || name === selected;
                $card.toggle(show);
                if (show) {
                    $card.find('.wpie-card-number').text('#' + (++visible));
                }
            });
            var anyVisible = visible > 0;
            if ($filterEmpty.length) {
                $filterEmpty.prop('hidden', anyVisible);
            }
        }
        $compFilter.on('change', wpieApplyStudentsCompetitionFilter);
    }

    // Define ajaxurl if not already defined
    if (typeof ajaxurl === 'undefined') {
        var ajaxurl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
    }

    // Get nonce from PHP
    var supervisorNonce = '<?php echo wp_create_nonce('wevoi_supervisor_nonce'); ?>';

    // Handle visa status update
    $('.wpie-visa-status-radio').on('change', function() {
        var $radio = $(this);
        var registrationId = $radio.data('registration-id');
        var visaStatus = $radio.val();
        var $radioGroup = $radio.closest('.wpie-visa-radio-group');

        console.log('Visa status change:', {
            registrationId: registrationId,
            visaStatus: visaStatus,
            nonce: supervisorNonce
        });

        // Disable all radios in this group while updating
        $radioGroup.find('input').prop('disabled', true);
        var $originalChecked = $radioGroup.find('input:checked').not($radio);
        var originalValue = $originalChecked.length > 0 ? $originalChecked.val() : '';

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wevoi_update_visa_status_supervisor',
                nonce: supervisorNonce,
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
                    var $message = $('<div class="wpie-visa-status-message" style="margin-top: 5px; padding: 5px; background: #d4edda; color: #155724; border-radius: 4px; font-size: 12px; text-align: center;"><?php echo esc_js(__('Visa status updated successfully', 'wpie-supervisor-profile')); ?></div>');
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
                    var errorMsg = (typeof response.data === 'string') ? response.data : (response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Failed to update visa status', 'wpie-supervisor-profile')); ?>');
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
                alert('<?php echo esc_js(__('Error updating visa status. Please try again.', 'wpie-supervisor-profile')); ?>');
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
        $saveBtn.prop('disabled', true).text('<?php echo esc_js(__('Saving...', 'wpie-supervisor-profile')); ?>');
        $message.hide().removeClass('success error');

        // Collect form data
        var formData = {
            action: 'wevoi_save_flight_info_supervisor',
            nonce: supervisorNonce,
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
                    $message.text('<?php echo esc_js(__('Flight information saved successfully', 'wpie-supervisor-profile')); ?>')
                            .addClass('success')
                            .show();
                    setTimeout(function() {
                        $message.fadeOut();
                    }, 3000);
                } else {
                    $message.text(response.data || '<?php echo esc_js(__('Failed to save flight information', 'wpie-supervisor-profile')); ?>')
                            .addClass('error')
                            .show();
                }
            },
            error: function() {
                $message.text('<?php echo esc_js(__('Error saving flight information. Please try again.', 'wpie-supervisor-profile')); ?>')
                        .addClass('error')
                        .show();
            },
            complete: function() {
                $saveBtn.prop('disabled', false).text('<?php echo esc_js(__('Save Flight Information', 'wpie-supervisor-profile')); ?>');
            }
        });
    });
});
</script>
