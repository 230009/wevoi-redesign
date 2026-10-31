<?php
/**
 * Student Dashboard Template
 *
 * @package WeVoi_Student_Dashboard
 */

// Direct access prevention
if (!defined('ABSPATH')) {
    exit;
}

// Get the dashboard instance
$dashboard = new Wevoi_Student_Dashboard();

// Get student data
$student_id = get_current_user_id();
$student = $dashboard->get_student_data($student_id);

// Get registrations
$registrations = $dashboard->get_registrations($student_id);

// Get results
$results = $dashboard->get_results($student_id);

// Get certificates
$certificates = $dashboard->get_certificates($student_id);

// Merge all data by competition code
$competitions_data = array();
foreach ($registrations as $reg) {
    $comp_code = $reg->competition_code;
    $competitions_data[$comp_code] = array(
        'registration' => $reg,
        'result' => null,
        'certificate' => null
    );
}

// Add results
foreach ($results as $result) {
    $comp_code = $result->competition_code;
    if (isset($competitions_data[$comp_code])) {
        $competitions_data[$comp_code]['result'] = $result;
    }
}

// Add certificates
foreach ($certificates as $cert) {
    $comp_code = isset($cert->competition_code) ? $cert->competition_code : '';
    if (empty($comp_code) && isset($cert->competition_name)) {
        // Try to find by competition name
        foreach ($competitions_data as $code => $data) {
            if ($data['registration']->competition_name === $cert->competition_name) {
                $comp_code = $code;
                break;
            }
        }
    }
    if (!empty($comp_code) && isset($competitions_data[$comp_code])) {
        $competitions_data[$comp_code]['certificate'] = $cert;
    }
}

// Get statistics
$statistics = $dashboard->get_statistics($student_id);

// Get upcoming competitions
$upcoming_competitions = $dashboard->get_upcoming_competitions($student_id);

// Check Summit qualification
$summit_qualification = $dashboard->check_summit_qualification($student_id);

// Open competitions in the active season the student has not submitted a video for
$open_competitions_awaiting_video = $dashboard->get_open_competitions_awaiting_video($student_id);

// Medal stickers: static PNGs under assets/images/ named {competition_code}-{medal}-{age}.png
$medal_sticker_items = $dashboard->get_medal_sticker_display_items($student_id);

// Set nonce for AJAX calls
$nonce = wp_create_nonce('wevoi_student_nonce');

// Get student first and last name
$first_name = get_user_meta($student_id, 'first_name', true);
$last_name = get_user_meta($student_id, 'last_name', true);
$full_name = trim($first_name . ' ' . $last_name);
if (empty($full_name)) {
    $full_name = $student->display_name;
}
// Capitalize first letter of each word
$full_name = ucwords(strtolower($full_name));

/**
 * Get user-friendly status message
 */
function get_user_friendly_status($status, $competition_type = null) {
    // Forum kayıtları için APPLICATION_ACCEPTED durumunda mesaj gösterme
    if ($status === 'APPLICATION_ACCEPTED' && $competition_type === 'Forum') {
        return ''; // Forum kayıtları için mesaj gösterme
    }

    $status_messages = array(
        'APPLICATION_ACCEPTED' => __('Your video has been accepted', 'wevoi-student-dashboard'),
        'PENDING_REVIEW' => __('Your application is under review', 'wevoi-student-dashboard'),
        'APPLICATION_REJECTED' => __('Your application has been rejected', 'wevoi-student-dashboard'),
        'VIDEO_UNLISTED' => __('Your YouTube video must be set to Public', 'wevoi-student-dashboard'),
        'VIDEO_INACCESSIBLE' => __('Your video link is inaccessible', 'wevoi-student-dashboard'),
        'IRRELEVANT_CONTENT' => __('Your video content is irrelevant', 'wevoi-student-dashboard'),
    );

    return isset($status_messages[$status]) ? $status_messages[$status] : $status;
}
?>

<div class="wevoi-student-dashboard">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="dashboard-header-left">
            <h2><?php _e('Welcome', 'wevoi-student-dashboard'); ?>, <strong><?php echo esc_html($full_name); ?></strong></h2>
        </div>
        <div class="dashboard-header-right">
            <?php if (!empty($student->schoolname) || !empty($student->supervisorname)): ?>
                <div class="student-additional-info">
                    <?php if (!empty($student->schoolname)): ?>
                        <p>
                            <?php echo esc_html(ucwords(strtolower($student->schoolname))); ?>
                            <?php if (!empty($student->countryname)): ?>
                                (<?php echo esc_html(ucwords(strtolower($student->countryname))); ?>)
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($student->supervisorname)): ?>
                        <p>
                            <?php _e('Supervisor', 'wevoi-student-dashboard'); ?>: <strong><?php echo esc_html(ucwords(strtolower($student->supervisorname))); ?></strong>
                            <?php if (!empty($student->supervisoremail)): ?>
                                (<a href="mailto:<?php echo esc_attr($student->supervisoremail); ?>"><?php echo esc_html($student->supervisoremail); ?></a>)
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($medal_sticker_items)) : ?>
    <section class="wevoi-medal-stickers-section" aria-label="<?php echo esc_attr(__('My Medal Stickers', 'wevoi-student-dashboard')); ?>">
        <h3 class="wevoi-medal-stickers-heading"><?php esc_html_e('My Medal Stickers', 'wevoi-student-dashboard'); ?></h3>
        <div class="wevoi-medal-stickers-grid">
            <?php foreach ($medal_sticker_items as $sticker_item) : ?>
                <a class="wevoi-medal-sticker-link"
                   href="<?php echo esc_url($sticker_item->download_url); ?>"
                   download="<?php echo esc_attr($sticker_item->download_filename); ?>"
                   title="<?php esc_attr_e('Download sticker as PNG', 'wevoi-student-dashboard'); ?>">
                    <img class="wevoi-medal-sticker-image"
                         src="<?php echo esc_url($sticker_item->preview_url); ?>"
                         alt="<?php echo esc_attr(sprintf(__('Medal sticker: %s', 'wevoi-student-dashboard'), $sticker_item->competition_name)); ?>"
                         loading="lazy"
                         decoding="async" />
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Award Ceremony Banner (shown when competition status = RANKINGS_FINALIZED and zoom link is set) -->
    <?php
    $award_ceremony_comp = null;
    foreach ($competitions_data as $comp_data) {
        $reg = $comp_data['registration'];
        if (
            isset($reg->competition_status) &&
            $reg->competition_status === 'RANKINGS_FINALIZED' &&
            !empty($reg->award_ceremony_zoom_link)
        ) {
            $award_ceremony_comp = $reg;
            break;
        }
    }
    if ($award_ceremony_comp):
        $ceremony_date_str = '';
        if (!empty($award_ceremony_comp->award_ceremony_date)) {
            $ts = strtotime($award_ceremony_comp->award_ceremony_date);
            $ceremony_date_str = date_i18n('l, F j, Y', $ts) . ' at ' . date('H:i', $ts) . ' (UK Time)';
        }
    ?>
    <div class="award-ceremony-banner">
        <div class="award-ceremony-banner-content">
            <div class="award-ceremony-icon">🎉</div>
            <div class="award-ceremony-message">
                <h3>You're Invited to the <?php echo esc_html($award_ceremony_comp->competition_name); ?> Award Ceremony!</h3>
                <?php if ($ceremony_date_str): ?>
                <p class="award-ceremony-datetime">
                    📅 <strong><?php echo esc_html($ceremony_date_str); ?></strong>
                </p>
                <?php endif; ?>
                <p>
                    Evaluation of all submitted videos has been completed. We are delighted to invite you to our
                    <strong>Award Ceremony</strong> where the results and winners will be announced live.
                    Join us on Zoom and celebrate this special moment with participants from around the world!
                </p>
                <div class="award-ceremony-action">
                    <a href="<?php echo esc_url($award_ceremony_comp->award_ceremony_zoom_link); ?>"
                       target="_blank" rel="noopener noreferrer"
                       class="award-ceremony-zoom-button">
                        🎥 Join Award Ceremony on Zoom
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Top Banners Container (Forum + Encouragement) -->
    <?php
    // Check if student is registered for active Forum
    $is_registered_for_active_forum = $dashboard->is_registered_for_active_forum($student_id);

    // Forum promo cards only when competition status is ON AIR
    $active_forum = $dashboard->get_active_forum_competition();

    $has_forum_banner = $active_forum && !$is_registered_for_active_forum;

    if ($has_forum_banner):
    ?>
    <div class="top-banners-container has-one-banner">
        <!-- WeVoi Forum 2026 Message -->
        <?php if ($has_forum_banner):
            // Get Forum coupon information
            $forum_coupon_info = $dashboard->get_forum_coupon_info($student_id);

            // Determine if we have both cards (main message + discount) or just one
            $has_discount = !empty($forum_coupon_info);
        ?>
        <div class="forum-banners-container <?php echo $has_discount ? 'has-two-cards' : 'has-one-card'; ?>">
            <!-- Main Forum Message Card -->
            <div class="forum-2026-banner">
                <div class="forum-banner-content">
                    <div class="forum-icon">🌍</div>
                    <div class="forum-message">
                        <h3><?php _e('WeVoi Forum 2026: Global Experience Awaits You!', 'wevoi-student-dashboard'); ?></h3>
                        <p>
                            <?php _e('WeVoi Forum is your chance to join a global learning experience with students from around the world. Through workshops, city explorations, and cultural exchange, you\'ll build confidence, improve communication, and make lifelong friendships.', 'wevoi-student-dashboard'); ?>
                        </p>
                        <?php if ($active_forum): ?>
                        <div class="forum-event-info" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255, 255, 255, 0.3);">
                            <?php if (!empty($active_forum->event_venue)): ?>
                            <div style="margin-bottom: 8px;">
                                <strong><?php _e('Event Venue:', 'wevoi-student-dashboard'); ?></strong>
                                <span><?php echo esc_html($active_forum->event_venue); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($active_forum->competition_date)): ?>
                            <div style="margin-bottom: 8px;">
                                <strong><?php _e('Start Date:', 'wevoi-student-dashboard'); ?></strong>
                                <span><?php echo date_i18n(get_option('date_format'), strtotime($active_forum->competition_date)); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($active_forum->event_duration)): ?>
                            <div style="margin-bottom: 8px;">
                                <strong><?php _e('Duration:', 'wevoi-student-dashboard'); ?></strong>
                                <span><?php echo esc_html($active_forum->event_duration); ?> <?php echo ($active_forum->event_duration == 1) ? __('day', 'wevoi-student-dashboard') : __('days', 'wevoi-student-dashboard'); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <div class="forum-action">
                            <a href="https://wevoi.org/register-forum" target="_blank" class="forum-register-button">
                                <?php _e('Register Now', 'wevoi-student-dashboard'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Discount Coupon Card -->
            <?php if ($forum_coupon_info): ?>
                <div class="forum-discount-banner">
                    <div class="forum-banner-content">
                        <div class="forum-icon">🎟️</div>
                        <div class="forum-message">
                            <h3><?php _e('Special Discount for You!', 'wevoi-student-dashboard'); ?></h3>
                            <p>
                                <?php _e('You\'ve earned a discount code to register for the WeVoi Forum 2026! Use the code at checkout for a reduced rate and be part of this unique global experience.', 'wevoi-student-dashboard'); ?>
                            </p>
                            <div class="forum-coupon-details">
                                <div class="forum-coupon-code">
                                    <strong><?php _e('Your Discount Code:', 'wevoi-student-dashboard'); ?></strong>
                                    <span class="coupon-code-value"><?php echo esc_html($forum_coupon_info['coupon_code']); ?></span>
                                </div>
                                <?php if ($forum_coupon_info['discount_type'] && $forum_coupon_info['discount_value']): ?>
                                    <div class="forum-discount-amount">
                                        <?php if ($forum_coupon_info['discount_type'] === 'percentage'): ?>
                                            <strong><?php _e('Discount:', 'wevoi-student-dashboard'); ?></strong>
                                            <span><?php echo esc_html($forum_coupon_info['discount_value']); ?>%</span>
                                        <?php elseif ($forum_coupon_info['discount_type'] === 'amount'): ?>
                                            <strong><?php _e('Discount:', 'wevoi-student-dashboard'); ?></strong>
                                            <span>£<?php echo number_format($forum_coupon_info['discount_value'], 2); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Summit Qualification Banner -->
    <?php if ($summit_qualification['qualified']): ?>
        <div class="summit-qualification-banner <?php echo $summit_qualification['has_free_coupon'] ? 'summit-free-coupon' : 'summit-qualified'; ?>">
            <div class="summit-banner-content">
                <div class="summit-icon">🏆</div>
                <div class="summit-message">
                    <?php if ($summit_qualification['has_free_coupon']): ?>
                        <h3><?php _e('Congratulations! You Qualified for WeVoi Summit', 'wevoi-student-dashboard'); ?></h3>
                        <p>
                                <?php
                                // For top 5 students: Show rank, age category, and coupon code in one sentence
                                if (!empty($summit_qualification['free_coupon_competitions'])):
                                    $comp = $summit_qualification['free_coupon_competitions'][0]; // Get first competition
                                    $age_category_text = !empty($comp['age_category']) ? ' in the ' . esc_html(ucwords(strtolower($comp['age_category']))) . ' age category' : '';
                                    $comp_name = ucwords(strtolower($comp['competition_name']));
                                    printf(
                                        __('You ranked #%d%s in %s and earned a free entry to WeVoi Summit with coupon code %s.', 'wevoi-student-dashboard'),
                                        intval($comp['rank']),
                                        $age_category_text,
                                        '<strong>' . esc_html($comp_name) . '</strong>',
                                        '<strong>' . esc_html($comp['coupon_code']) . '</strong>'
                                    );
                                else:
                                    _e('You ranked in the top 5 in your age category and earned a free entry to WeVoi Summit!', 'wevoi-student-dashboard');
                                endif;
                                ?>
                        </p>
                        <p class="summit-action-text">
                            <?php _e('You can now start preparing your video for WeVoi Summit! Each age category has a free topic available.', 'wevoi-student-dashboard'); ?>
                        </p>
                    <?php else: ?>
                        <h3><?php _e('You Qualified for WeVoi Summit!', 'wevoi-student-dashboard'); ?></h3>
                        <p>
                            <?php _e('Your video has been accepted in a qualification competition. You can now start preparing your video for WeVoi Summit! Each age category has a free topic available.', 'wevoi-student-dashboard'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="statistics-cards">
        <div class="stat-card stat-card-blue">
            <div class="stat-icon">🏆</div>
            <div class="stat-content">
                <div class="stat-value"><?php echo esc_html($statistics['total_competitions']); ?></div>
                <div class="stat-label"><?php _e('Total Competitions', 'wevoi-student-dashboard'); ?></div>
            </div>
        </div>

        <div class="stat-card stat-card-green">
            <div class="stat-icon">✅</div>
            <div class="stat-content">
                <div class="stat-value"><?php echo esc_html($statistics['completed_competitions']); ?></div>
                <div class="stat-label"><?php _e('Completed', 'wevoi-student-dashboard'); ?></div>
            </div>
        </div>

        <div class="stat-card stat-card-gold">
            <div class="stat-icon">🥇</div>
            <div class="stat-content">
                <div class="stat-value"><?php echo esc_html($statistics['total_medals']); ?></div>
                <div class="stat-label"><?php _e('Total Medals', 'wevoi-student-dashboard'); ?></div>
                <?php if ($statistics['total_medals'] > 0): ?>
                    <div class="stat-detail">
                        🥇 <?php echo esc_html($statistics['gold_medals']); ?>
                        🥈 <?php echo esc_html($statistics['silver_medals']); ?>
                        🥉 <?php echo esc_html($statistics['bronze_medals']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="stat-card stat-card-purple">
            <div class="stat-icon">⭐</div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($statistics['total_points'], 1); ?></div>
                <div class="stat-label"><?php _e('Total Points', 'wevoi-student-dashboard'); ?></div>
            </div>
        </div>

        <div class="stat-card stat-card-orange">
            <div class="stat-icon">📜</div>
            <div class="stat-content">
                <div class="stat-value"><?php echo esc_html($statistics['total_certificates']); ?></div>
                <div class="stat-label"><?php _e('Certificates', 'wevoi-student-dashboard'); ?></div>
            </div>
        </div>
    </div>

    <!-- Dashboard Content -->
    <div class="dashboard-content">

        <!-- Competitions List -->
        <div class="competitions-list-section">
            <h3><?php _e('My Competitions', 'wevoi-student-dashboard'); ?></h3>

            <?php if (!empty($open_competitions_awaiting_video)): ?>
                <div class="open-competition-prompts">
                    <?php foreach ($open_competitions_awaiting_video as $open_comp):
                        $open_name = ucwords(strtolower($open_comp->competition_name));
                    ?>
                        <div class="open-competition-prompt">
                            <div class="open-competition-prompt-icon" aria-hidden="true">🎥</div>
                            <div class="open-competition-prompt-body">
                                <h4><?php printf(__('Submit your video for %s', 'wevoi-student-dashboard'), esc_html($open_name)); ?></h4>
                                <p>
                                    <?php printf(
                                        __('%s is open this season. Record your video and register to compete with students from around the world.', 'wevoi-student-dashboard'),
                                        esc_html($open_name)
                                    ); ?>
                                </p>
                                <a href="<?php echo esc_url(home_url('/register/')); ?>" class="open-competition-prompt-button">
                                    <?php printf(__('Register for %s', 'wevoi-student-dashboard'), esc_html($open_name)); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($competitions_data)): ?>
                <div class="competitions-grid">
                    <?php
                    // DEBUG: Show active year info (remove after debugging)
                    if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                        <div style="background: #fff3cd; padding: 15px; margin-bottom: 20px; border: 2px solid #ffc107; border-radius: 8px;">
                            <h4 style="margin-top: 0;">🔍 DEBUG INFO</h4>
                            <p><strong>Active Year Check:</strong> Using active_year field from database (1 = active, 0 = not active)</p>
                        </div>
                    <?php endif;

                    foreach ($competitions_data as $comp_code => $data):
                        $reg = $data['registration'];
                        $result = $data['result'];
                        $cert = $data['certificate'];

                        // Check if bank account exists for this registration
                        global $wpdb;
                        $has_bank_account = false;
                        $registration_id = null;
                        if ($result) {
                            // register_id is the primary key in wevoi_registrations table
                            $registration_id = !empty($result->register_id) ? intval($result->register_id) : null;
                            if ($registration_id) {
                                $bank_account = $wpdb->get_var($wpdb->prepare(
                                    "SELECT id FROM {$wpdb->prefix}wevoi_bank_accounts WHERE registration_id = %d",
                                    $registration_id
                                ));
                                $has_bank_account = !empty($bank_account);
                            }
                        }

                        // Check if this is an active year qualification competition
                        $is_active_year = (!empty($reg->active_year) && intval($reg->active_year) === 1);
                        $is_qualification_comp = false;
                        $is_top_5_with_coupon = false;
                        $comp_year = null;

                        if (!empty($reg->competition_date)) {
                            $comp_year = date('Y', strtotime($reg->competition_date));
                        }

                        // Check if it's Spark or Reload or Qualification type
                        if (stripos($reg->competition_name, 'Spark') !== false ||
                            stripos($reg->competition_name, 'Reload') !== false ||
                            (!empty($reg->competition_type) && strtoupper($reg->competition_type) === 'QUALIFICATION')) {
                            $is_qualification_comp = true;
                        }

                        // Check if top 5 with coupon (check both result and registration)
                        $student_rank = null;
                        $coupon_code = null;

                        if ($result) {
                            $student_rank = !empty($result->student_rank) ? intval($result->student_rank) : null;
                            $coupon_code = !empty($result->claimed_summit_coupon_code) ? $result->claimed_summit_coupon_code : null;
                        }

                        // Also check registration table for rank and coupon
                        if (empty($student_rank) && !empty($reg->student_rank)) {
                            $student_rank = intval($reg->student_rank);
                        }
                        if (empty($coupon_code) && !empty($reg->claimed_summit_coupon_code)) {
                            $coupon_code = $reg->claimed_summit_coupon_code;
                        }

                        if ($student_rank !== null && $student_rank <= 5 && !empty($coupon_code)) {
                            $is_top_5_with_coupon = true;
                        }

                        // Extract video ID
                        $video_id = '';
                        if (!empty($reg->youtube_link)) {
                            $patterns = array(
                                '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/',
                                '/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/'
                            );
                            foreach ($patterns as $pattern) {
                                if (preg_match($pattern, $reg->youtube_link, $match)) {
                                    $video_id = $match[1];
                                    break;
                                }
                            }
                        }

                        // Check if this is a Forum competition
                        $is_forum = (!empty($reg->competition_type) && strtoupper($reg->competition_type) === 'FORUM');

                        // Get flight information for Forum registrations
                        $flight_info = null;
                        $airports = array();
                        if ($is_forum) {
                            $flight_info = $dashboard->get_flight_info($reg->register_id);
                            $airports = $dashboard->get_competition_airports($reg->competition_code);
                        }
                    ?>
                        <div class="competition-card-full">
                            <!-- DEBUG: Show competition info (remove after debugging) -->
                            <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                                <div style="background: #e7f3ff; padding: 10px; margin-bottom: 10px; border-left: 4px solid #2196F3; font-size: 12px;">
                                    <strong>Competition:</strong> <?php echo esc_html(ucwords(strtolower($reg->competition_name))); ?><br>
                                    <strong>Competition Date:</strong> <?php echo !empty($reg->competition_date) ? esc_html($reg->competition_date) : 'N/A'; ?><br>
                                    <strong>Competition Year:</strong> <?php echo $comp_year !== null ? $comp_year : 'N/A'; ?><br>
                                    <strong>Active Year:</strong> <?php echo !empty($reg->active_year) ? intval($reg->active_year) : '0'; ?> (<?php echo $is_active_year ? 'YES ✅' : 'NO ❌'; ?>)<br>
                                    <strong>Competition Type:</strong> <?php echo !empty($reg->competition_type) ? esc_html($reg->competition_type) : 'N/A'; ?><br>
                                    <strong>Application Status:</strong> <?php echo !empty($reg->application_status) ? esc_html($reg->application_status) : 'N/A'; ?><br>
                                    <strong>Is Qualification Comp:</strong> <?php echo $is_qualification_comp ? 'YES' : 'NO'; ?><br>
                                </div>
                            <?php endif; ?>

                            <div class="competition-card-header <?php echo $is_active_year ? 'header-active' : 'header-inactive'; ?>">
                                <h4><?php echo esc_html(ucwords(strtolower($reg->competition_name))); ?></h4>
                                <?php if (!empty($reg->application_status)):
                                    $status_message = get_user_friendly_status($reg->application_status, $reg->competition_type ?? null);
                                    if (!empty($status_message)): ?>
                                        <span class="status-badge status-<?php echo sanitize_html_class(strtolower($reg->application_status)); ?>">
                                            <?php echo esc_html($status_message); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <div class="competition-card-content <?php echo $is_forum ? 'forum-card-content' : ''; ?>">
                                <!-- Left Side - Information -->
                                <div class="competition-info-column <?php echo $is_forum ? 'forum-info-column' : ''; ?>">
                                    <?php if ($is_forum): ?>
                                        <!-- Forum-specific information -->
                                        <div class="competition-info-row">
                                            <div class="info-item">
                                                <span class="info-label"><?php _e('Registration Date', 'wevoi-student-dashboard'); ?>:</span>
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
                                                <label class="forum-card-title"><?php _e('Event Information', 'wevoi-student-dashboard'); ?></label>
                                                <?php if (!empty($reg->event_venue)): ?>
                                                <div class="info-item" style="margin-bottom: 10px;">
                                                    <span class="info-label" style="font-weight: 600;"><?php _e('Event Venue', 'wevoi-student-dashboard'); ?>:</span>
                                                    <span class="info-value"><?php echo esc_html($reg->event_venue); ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($reg->competition_date)): ?>
                                                <div class="info-item" style="margin-bottom: 10px;">
                                                    <span class="info-label" style="font-weight: 600;"><?php _e('Event Date', 'wevoi-student-dashboard'); ?>:</span>
                                                    <span class="info-value"><?php echo date_i18n(get_option('date_format'), strtotime($reg->competition_date)); ?></span>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($reg->event_duration)): ?>
                                                <div class="info-item" style="margin-bottom: 10px;">
                                                    <span class="info-label" style="font-weight: 600;"><?php _e('Duration', 'wevoi-student-dashboard'); ?>:</span>
                                                    <span class="info-value"><?php echo esc_html($reg->event_duration); ?> <?php echo ($reg->event_duration == 1) ? __('day', 'wevoi-student-dashboard') : __('days', 'wevoi-student-dashboard'); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            </div>

                                            <!-- Payment Information -->
                                            <div class="forum-payment-info">
                                                <label class="forum-card-title"><?php _e('Payment Information', 'wevoi-student-dashboard'); ?></label>
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
                                                    $forum_status_label = __('Pre-registered', 'wevoi-student-dashboard');
                                                } else {
                                                    $forum_status_label = $forum_paid_raw !== '' ? $forum_paid_raw : __('Pending', 'wevoi-student-dashboard');
                                                }
                                                $forum_paid_class = sanitize_html_class(strtolower($forum_reg_ps));
                                                ?>
                                                <div class="info-item" style="margin-bottom: 8px;">
                                                    <span class="info-label"><?php _e('Status', 'wevoi-student-dashboard'); ?>:</span>
                                                    <span class="info-value">
                                                        <span class="status-badge status-<?php echo esc_attr($forum_paid_class); ?>">
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
                                                    <div class="info-item" style="margin-bottom: 8px;">
                                                        <span class="info-label"><?php _e('Price', 'wevoi-student-dashboard'); ?>:</span>
                                                        <span class="info-value">£<?php echo number_format((float)$reg->price, 2); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($reg->coupon_code)): ?>
                                                    <div class="info-item" style="margin-bottom: 8px;">
                                                        <span class="info-label"><?php _e('Coupon Code', 'wevoi-student-dashboard'); ?>:</span>
                                                        <span class="info-value"><?php echo esc_html($reg->coupon_code); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                <?php
                                                $forum_pay_open = ($forum_reg_phase === 'payment_required');
                                                if ($forum_pay_open && $forum_reg_ps === 'PRE_REGISTERED' && floatval($reg->price) > 0) :
                                                    $forum_pay_url = trailingslashit(home_url('/register-forum/'));
 ?>
                                                    <div class="info-item" style="margin-top: 12px;">
                                                        <a href="<?php echo esc_url($forum_pay_url); ?>" class="button button-primary" style="display: inline-block; text-decoration: none;">
                                                            Complete Forum payment
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <?php
                                            $forum_show_travel = in_array($forum_reg_ps, array('PAID', 'FREE'), true);
                                            ?>
                                            <?php if ($forum_show_travel) : ?>
                                            <!-- Visa Status -->
                                            <div class="forum-visa-status">
                                                <label class="forum-card-title"><?php _e('Visa Status', 'wevoi-student-dashboard'); ?></label>
                                                <div class="visa-status-radio-wrapper">
                                                    <div class="visa-radio-group">
                                                        <label class="visa-radio-label">
                                                            <input type="radio"
                                                                   name="visa_status_<?php echo esc_attr($reg->register_id); ?>"
                                                                   class="visa-status-radio"
                                                                   data-registration-id="<?php echo esc_attr($reg->register_id); ?>"
                                                                   value="I do not need a visa"
                                                                   <?php checked($reg->visa_status ?? '', 'I do not need a visa'); ?>>
                                                            <span><?php _e('I do not need a visa', 'wevoi-student-dashboard'); ?></span>
                                                        </label>
                                                        <label class="visa-radio-label">
                                                            <input type="radio"
                                                                   name="visa_status_<?php echo esc_attr($reg->register_id); ?>"
                                                                   class="visa-status-radio"
                                                                   data-registration-id="<?php echo esc_attr($reg->register_id); ?>"
                                                                   value="I currently hold a valid visa"
                                                                   <?php checked($reg->visa_status ?? '', 'I currently hold a valid visa'); ?>>
                                                            <span><?php _e('I currently hold a valid visa', 'wevoi-student-dashboard'); ?></span>
                                                        </label>
                                                        <label class="visa-radio-label">
                                                            <input type="radio"
                                                                   name="visa_status_<?php echo esc_attr($reg->register_id); ?>"
                                                                   class="visa-status-radio"
                                                                   data-registration-id="<?php echo esc_attr($reg->register_id); ?>"
                                                                   value="I have not yet submitted a visa application"
                                                                   <?php checked($reg->visa_status ?? '', 'I have not yet submitted a visa application'); ?>>
                                                            <span><?php _e('I have not yet submitted a visa application', 'wevoi-student-dashboard'); ?></span>
                                                        </label>
                                                        <label class="visa-radio-label">
                                                            <input type="radio"
                                                                   name="visa_status_<?php echo esc_attr($reg->register_id); ?>"
                                                                   class="visa-status-radio"
                                                                   data-registration-id="<?php echo esc_attr($reg->register_id); ?>"
                                                                   value="My visa application is under evaluation"
                                                                   <?php checked($reg->visa_status ?? '', 'My visa application is under evaluation'); ?>>
                                                            <span><?php _e('My visa application is under evaluation', 'wevoi-student-dashboard'); ?></span>
                                                        </label>
                                                        <label class="visa-radio-label">
                                                            <input type="radio"
                                                                   name="visa_status_<?php echo esc_attr($reg->register_id); ?>"
                                                                   class="visa-status-radio"
                                                                   data-registration-id="<?php echo esc_attr($reg->register_id); ?>"
                                                                   value="My visa application has been rejected"
                                                                   <?php checked($reg->visa_status ?? '', 'My visa application has been rejected'); ?>>
                                                            <span><?php _e('My visa application has been rejected', 'wevoi-student-dashboard'); ?></span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Flight Information -->
                                            <div class="forum-flight-info">
                                                <label class="forum-card-title"><?php _e('Flight Information', 'wevoi-student-dashboard'); ?></label>
                                                <form class="flight-info-form" data-registration-id="<?php echo esc_attr($reg->register_id); ?>">
                                                    <!-- Arrival Flight Section -->
                                                    <div class="flight-section">
                                                        <h5 class="flight-section-title"><?php _e('Arrival Flight (To Competition)', 'wevoi-student-dashboard'); ?></h5>
                                                        <div class="flight-form-row">
                                                            <div class="flight-form-group">
                                                                <label><?php _e('Flight Number', 'wevoi-student-dashboard'); ?></label>
                                                                <input type="text"
                                                                       name="arrival_flight_number"
                                                                       class="flight-input"
                                                                       value="<?php echo esc_attr($flight_info->arrival_flight_number ?? ''); ?>"
                                                                       placeholder="<?php _e('e.g., TK123', 'wevoi-student-dashboard'); ?>">
                                                            </div>
                                                            <div class="flight-form-group">
                                                                <label><?php _e('Airline', 'wevoi-student-dashboard'); ?></label>
                                                                <input type="text"
                                                                       name="arrival_airline"
                                                                       class="flight-input"
                                                                       value="<?php echo esc_attr($flight_info->arrival_airline ?? ''); ?>"
                                                                       placeholder="<?php _e('e.g., Air Astana', 'wevoi-student-dashboard'); ?>">
                                                            </div>
                                                        </div>
                                                        <div class="flight-form-row">
                                                            <div class="flight-form-group">
                                                                <label><?php _e('From Airport', 'wevoi-student-dashboard'); ?></label>
                                                                <input type="text"
                                                                       name="arrival_airport_from"
                                                                       class="flight-input"
                                                                       value="<?php echo esc_attr($flight_info->arrival_airport_from ?? ''); ?>"
                                                                       placeholder="<?php _e('e.g., Almaty International Airport', 'wevoi-student-dashboard'); ?>">
                                                            </div>
                                                            <div class="flight-form-group">
                                                                <label><?php _e('To Airport', 'wevoi-student-dashboard'); ?></label>
                                                                <select name="arrival_airport_to" class="flight-input">
                                                                    <option value=""><?php _e('Select Airport', 'wevoi-student-dashboard'); ?></option>
                                                                    <?php foreach ($airports as $airport): ?>
                                                                        <option value="<?php echo esc_attr($airport->airport_name); ?>"
                                                                                <?php selected($flight_info->arrival_airport_to ?? '', $airport->airport_name); ?>>
                                                                            <?php echo esc_html($airport->airport_name); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="flight-form-row">
                                                            <div class="flight-form-group">
                                                                <label><?php _e('Arrival Date', 'wevoi-student-dashboard'); ?></label>
                                                                <input type="date"
                                                                       name="arrival_date"
                                                                       class="flight-input"
                                                                       value="<?php echo esc_attr($flight_info->arrival_date ?? ''); ?>">
                                                            </div>
                                                            <div class="flight-form-group">
                                                                <label><?php _e('Arrival Time', 'wevoi-student-dashboard'); ?></label>
                                                                <input type="time"
                                                                       name="arrival_time"
                                                                       class="flight-input"
                                                                       value="<?php echo esc_attr($flight_info->arrival_time ?? ''); ?>">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Return Flight Section -->
                                                    <div class="flight-section">
                                                        <h5 class="flight-section-title"><?php _e('Return Flight (From Competition)', 'wevoi-student-dashboard'); ?></h5>
                                                        <div class="flight-form-row">
                                                            <div class="flight-form-group">
                                                                <label><?php _e('Flight Number', 'wevoi-student-dashboard'); ?></label>
                                                                <input type="text"
                                                                       name="return_flight_number"
                                                                       class="flight-input"
                                                                       value="<?php echo esc_attr($flight_info->return_flight_number ?? ''); ?>"
                                                                       placeholder="<?php _e('e.g., TK456', 'wevoi-student-dashboard'); ?>">
                                                            </div>
                                                            <div class="flight-form-group">
                                                                <label><?php _e('Airline', 'wevoi-student-dashboard'); ?></label>
                                                                <input type="text"
                                                                       name="return_airline"
                                                                       class="flight-input"
                                                                       value="<?php echo esc_attr($flight_info->return_airline ?? ''); ?>"
                                                                       placeholder="<?php _e('e.g., Air Astana', 'wevoi-student-dashboard'); ?>">
                                                            </div>
                                                        </div>
                                                        <div class="flight-form-row">
                                                            <div class="flight-form-group">
                                                                <label><?php _e('From Airport', 'wevoi-student-dashboard'); ?></label>
                                                                <select name="return_airport_from" class="flight-input">
                                                                    <option value=""><?php _e('Select Airport', 'wevoi-student-dashboard'); ?></option>
                                                                    <?php foreach ($airports as $airport): ?>
                                                                        <option value="<?php echo esc_attr($airport->airport_name); ?>"
                                                                                <?php selected($flight_info->return_airport_from ?? '', $airport->airport_name); ?>>
                                                                            <?php echo esc_html($airport->airport_name); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="flight-form-group">
                                                                <label><?php _e('To Airport', 'wevoi-student-dashboard'); ?></label>
                                                                <input type="text"
                                                                       name="return_airport_to"
                                                                       class="flight-input"
                                                                       value="<?php echo esc_attr($flight_info->return_airport_to ?? ''); ?>"
                                                                       placeholder="<?php _e('e.g., Almaty International Airport', 'wevoi-student-dashboard'); ?>">
                                                            </div>
                                                        </div>
                                                        <div class="flight-form-row">
                                                            <div class="flight-form-group">
                                                                <label><?php _e('Departure Date', 'wevoi-student-dashboard'); ?></label>
                                                                <input type="date"
                                                                       name="return_departure_date"
                                                                       class="flight-input"
                                                                       value="<?php echo esc_attr($flight_info->return_departure_date ?? ''); ?>">
                                                            </div>
                                                            <div class="flight-form-group">
                                                                <label><?php _e('Departure Time', 'wevoi-student-dashboard'); ?></label>
                                                                <input type="time"
                                                                       name="return_departure_time"
                                                                       class="flight-input"
                                                                       value="<?php echo esc_attr($flight_info->return_departure_time ?? ''); ?>">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="flight-form-actions">
                                                        <button type="submit" class="flight-save-btn">
                                                            <?php _e('Save Flight Information', 'wevoi-student-dashboard'); ?>
                                                        </button>
                                                        <span class="flight-save-message" style="display: none;"></span>
                                                    </div>
                                                </form>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <!-- Regular competition information -->
                                        <div class="competition-info-row">
                                            <div class="info-item">
                                                <span class="info-label"><?php _e('Registration Date', 'wevoi-student-dashboard'); ?>:</span>
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

                                            <?php if (!empty($reg->age_category)): ?>
                                            <div class="info-item">
                                                <span class="info-label"><?php _e('Age Category', 'wevoi-student-dashboard'); ?>:</span>
                                                <span class="age-category-badge"><?php echo esc_html($reg->age_category); ?></span>
                                            </div>
                                            <?php endif; ?>

                                            <?php if (!empty($reg->supervisor_id) && (!empty(trim($reg->supervisor_name ?? '')) || !empty($reg->supervisor_email))): ?>
                                            <?php
                                            $sup_role_label = __('Supervisor/Agency', 'wevoi-student-dashboard');
                                            $sup_user = get_userdata($reg->supervisor_id);
                                            if ($sup_user && !empty($sup_user->roles)) {
                                                if (in_array('agency', $sup_user->roles, true)) {
                                                    $sup_role_label = __('Agency', 'wevoi-student-dashboard');
                                                } elseif (in_array('supervisor', $sup_user->roles, true)) {
                                                    $sup_role_label = __('Supervisor', 'wevoi-student-dashboard');
                                                }
                                            }
                                            ?>
                                            <div class="info-item">
                                                <span class="info-label"><?php echo esc_html($sup_role_label); ?>:</span>
                                                <span class="info-value">
                                                    <?php
                                                    $sup_name = trim($reg->supervisor_name ?? '');
                                                    if (empty($sup_name)) {
                                                        $sup_name = !empty($reg->supervisor_email) ? $reg->supervisor_email : '—';
                                                    } else {
                                                        $sup_name = function_exists('wevoi_capitalize_name') ? wevoi_capitalize_name($sup_name) : $sup_name;
                                                    }
                                                    if (!empty($reg->supervisor_email)) {
                                                        echo '<a href="mailto:' . esc_attr($reg->supervisor_email) . '" class="wevoi-dashboard-supervisor-link">' . esc_html($sup_name) . '</a>';
                                                    } else {
                                                        echo esc_html($sup_name);
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($result): ?>
                                    <div class="competition-result-section">
                                        <h5><?php _e('Results', 'wevoi-student-dashboard'); ?></h5>

                                        <?php if (!empty($result->student_medal) || !empty($result->student_point) || !empty($result->student_rank)): ?>
                                            <div class="result-items-row">
                                                <?php if (!empty($result->student_medal)): ?>
                                                    <div class="result-item">
                                                        <div class="result-icon-large medal-icon-<?php echo sanitize_html_class(strtolower($result->student_medal)); ?>">
                                                            <?php
                                                            $medal_icons = array(
                                                                'GOLD' => '🥇',
                                                                'SILVER' => '🥈',
                                                                'BRONZE' => '🥉'
                                                            );
                                                            echo isset($medal_icons[strtoupper($result->student_medal)]) ? $medal_icons[strtoupper($result->student_medal)] : '🏅';
                                                            ?>
                                                        </div>
                                                        <div class="result-value">
                                                            <span class="result-label"><?php _e('Medal', 'wevoi-student-dashboard'); ?></span>
                                                            <span class="result-text medal-<?php echo sanitize_html_class(strtolower($result->student_medal)); ?>"><?php echo esc_html($result->student_medal); ?></span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($result->student_point)): ?>
                                                    <div class="result-item">
                                                        <div class="result-icon-large points-icon">⭐</div>
                                                        <div class="result-value">
                                                            <span class="result-label"><?php _e('Points', 'wevoi-student-dashboard'); ?></span>
                                                            <span class="result-text"><?php echo number_format((float)$result->student_point, 2); ?></span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($result->student_rank)): ?>
                                                    <div class="result-item">
                                                        <div class="result-icon-large rank-icon">🎯</div>
                                                        <div class="result-value">
                                                            <span class="result-label"><?php _e('Rank', 'wevoi-student-dashboard'); ?></span>
                                                            <span class="result-text">#<?php echo intval($result->student_rank); ?></span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <?php if (!empty($result->claimed_summit_coupon_code)): ?>
                                                <div class="coupon-code" style="margin-top: 15px;">
                                                    <?php printf(__('Summit Coupon: %s', 'wevoi-student-dashboard'), '<strong>' . esc_html($result->claimed_summit_coupon_code) . '</strong>'); ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php
                                            // Prize information for WeVoi Summit (Final type) - Top 3 only
                                            if (!empty($reg->competition_type) && strtoupper($reg->competition_type) === 'FINAL' &&
                                                !empty($result->student_rank) && intval($result->student_rank) <= 3 &&
                                                !empty($result->claimed_prize_amount) && floatval($result->claimed_prize_amount) > 0):
                                            ?>
                                                <div class="prize-section" style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 8px; border: 2px solid #ffc107;">
                                                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                                                        <div>
                                                            <div style="font-size: 24px; font-weight: 700; color: #ff6b35; margin-bottom: 5px;">
                                                                £<?php echo number_format((float)$result->claimed_prize_amount, 2); ?>
                                                            </div>
                                                            <div style="font-size: 14px; color: #666;">
                                                                <?php _e('Cash Prize', 'wevoi-student-dashboard'); ?>
                                                            </div>
                                                        </div>
                                                        <div style="text-align: right;">
                                                            <?php if (!empty($result->claimed_prize_paid_status) && strtoupper($result->claimed_prize_paid_status) === 'PAID'): ?>
                                                                <span style="display: inline-block; padding: 5px 12px; background: #28a745; color: #fff; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                                                    <?php _e('Paid', 'wevoi-student-dashboard'); ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span style="display: inline-block; padding: 5px 12px; background: #ffc107; color: #333; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                                                    <?php _e('Not Paid Yet', 'wevoi-student-dashboard'); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <?php if (empty($result->claimed_prize_paid_status) || strtoupper($result->claimed_prize_paid_status) !== 'PAID'): ?>
                                                        <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #e2e4e7;">
                                                            <?php if ($has_bank_account): ?>
                                                                <div class="bank-details-submitted-row">
                                                                    <div style="display: flex; align-items: center; gap: 8px; color: #28a745; font-size: 14px;">
                                                                        <span>✓</span>
                                                                        <span><?php _e('Bank account details submitted', 'wevoi-student-dashboard'); ?></span>
                                                                    </div>
                                                                    <button type="button" class="btn-submit-bank-details btn-edit-bank-details"
                                                                            data-mode="edit"
                                                                            data-registration-id="<?php echo esc_attr($registration_id); ?>"
                                                                            data-competition-name="<?php echo esc_attr($reg->competition_name); ?>"
                                                                            data-prize-amount="<?php echo esc_attr($result->claimed_prize_amount); ?>">
                                                                        <?php _e('Edit Bank details', 'wevoi-student-dashboard'); ?>
                                                                    </button>
                                                                </div>
                                                            <?php else: ?>
                                                                <button type="button" class="btn-submit-bank-details"
                                                                        data-mode="submit"
                                                                        data-registration-id="<?php echo esc_attr($registration_id); ?>"
                                                                        data-competition-name="<?php echo esc_attr($reg->competition_name); ?>"
                                                                        data-prize-amount="<?php echo esc_attr($result->claimed_prize_amount); ?>"
                                                                        style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; background: #0073aa; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600;">
                                                                    <span>🏦</span>
                                                                    <span><?php _e('Submit Bank Account Details', 'wevoi-student-dashboard'); ?></span>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php elseif (!empty($result->claimed_summit_coupon_code)): ?>
                                            <div class="coupon-code">
                                                <?php printf(__('Summit Coupon: %s', 'wevoi-student-dashboard'), '<strong>' . esc_html($result->claimed_summit_coupon_code) . '</strong>'); ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="no-result"><?php _e('Results pending', 'wevoi-student-dashboard'); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>

                                    <?php
                                    $cert_link = null;
                                    $is_competition_completed = !empty($reg->competition_status)
                                        && strtoupper((string) $reg->competition_status) === 'COMPLETED';
                                    if ($is_competition_completed) {
                                        if ($cert) {
                                            $cert_link = isset($cert->certificate_link) ? $cert->certificate_link : '';
                                        } elseif ($result && !empty($result->certificate_link)) {
                                            $cert_link = $result->certificate_link;
                                        }
                                    }

                                    if (!empty($cert_link)):
                                    ?>
                                    <div class="competition-certificate-section">
                                        <a href="<?php echo esc_url($cert_link); ?>" target="_blank" class="action-button certificate-button">
                                            <span class="button-icon">📜</span>
                                            <?php _e('View Certificate', 'wevoi-student-dashboard'); ?>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Right Side - Video (only for non-Forum competitions) -->
                                <?php if (!$is_forum): ?>
                                    <?php if (!empty($reg->youtube_link) && $video_id): ?>
                                    <div class="competition-video-column">
                                        <div class="video-container">
                                            <iframe
                                                width="100%"
                                                height="100%"
                                                src="https://www.youtube.com/embed/<?php echo esc_attr($video_id); ?>"
                                                frameborder="0"
                                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                                allowfullscreen>
                                            </iframe>
                                        </div>
                                    </div>
                                    <?php elseif (!empty($reg->youtube_link)): ?>
                                    <div class="competition-video-column">
                                        <div class="video-container">
                                            <a href="<?php echo esc_url($reg->youtube_link); ?>" target="_blank" class="youtube-link">
                                                <?php _e('View Video on YouTube', 'wevoi-student-dashboard'); ?>
                                            </a>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif (empty($open_competitions_awaiting_video)): ?>
                <div class="no-items-message">
                    <p><?php _e('You don\'t have any competition registrations yet.', 'wevoi-student-dashboard'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bank Account Details Modal -->
<div id="bank-account-modal" class="bank-account-modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="bank-modal-title"><?php _e('Submit Bank Account Details', 'wevoi-student-dashboard'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-header-info">
                <p id="modal-competition-name"></p>
                <p id="modal-prize-amount"></p>
            </div>
            <form id="bank-account-form" class="bank-details-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="modal_account_holder_name"><?php _e('Account Holder Name', 'wevoi-student-dashboard'); ?> *</label>
                        <input type="text" id="modal_account_holder_name" name="account_holder_name" required>
                    </div>
                    <div class="form-group">
                        <label for="modal_bank_name"><?php _e('Bank Name', 'wevoi-student-dashboard'); ?> *</label>
                        <input type="text" id="modal_bank_name" name="bank_name" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="modal_account_number"><?php _e('Account Number', 'wevoi-student-dashboard'); ?> *</label>
                        <input type="text" id="modal_account_number" name="account_number" required>
                    </div>
                    <div class="form-group">
                        <label for="modal_sort_code"><?php _e('Sort Code (UK Banks)', 'wevoi-student-dashboard'); ?></label>
                        <input type="text" id="modal_sort_code" name="sort_code" placeholder="12-34-56">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="modal_iban"><?php _e('IBAN (International)', 'wevoi-student-dashboard'); ?></label>
                        <input type="text" id="modal_iban" name="iban" placeholder="GB29 NWBK 6016 1331 9268 19">
                    </div>
                    <div class="form-group">
                        <label for="modal_swift_code"><?php _e('SWIFT/BIC Code', 'wevoi-student-dashboard'); ?></label>
                        <input type="text" id="modal_swift_code" name="swift_code" placeholder="NWBKGB2L">
                    </div>
                </div>
                <div class="form-row form-row-three">
                    <div class="form-group">
                        <label for="modal_countryid"><?php _e('Country', 'wevoi-student-dashboard'); ?> *</label>
                        <select id="modal_countryid" name="countryid" required>
                            <option value=""><?php _e('Select Country', 'wevoi-student-dashboard'); ?></option>
                            <?php
                            global $wpdb;
                            $countries = $wpdb->get_results("SELECT countryid, countryname FROM {$wpdb->prefix}wevoi_countries ORDER BY countryname");
                            foreach ($countries as $country) {
                                echo '<option value="' . $country->countryid . '">' . esc_html($country->countryname) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="modal_city"><?php _e('City', 'wevoi-student-dashboard'); ?> *</label>
                        <input type="text" id="modal_city" name="city" required>
                    </div>
                    <div class="form-group">
                        <label for="modal_currencyid"><?php _e('Currency', 'wevoi-student-dashboard'); ?> *</label>
                        <select id="modal_currencyid" name="currencyid" required>
                            <?php
                            $currencies = $wpdb->get_results("SELECT currencyid, currency_code, currency_name, symbol FROM {$wpdb->prefix}wevoi_currencies WHERE is_active = 1 ORDER BY currency_code");
                            foreach ($currencies as $currency) {
                                $selected = ($currency->currency_code == 'GBP') ? 'selected' : '';
                                echo '<option value="' . $currency->currencyid . '" ' . $selected . '>' .
                                     esc_html($currency->currency_code . ' (' . $currency->symbol . ') - ' . $currency->currency_name) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="modal_general_info"><?php _e('General Information', 'wevoi-student-dashboard'); ?></label>
                        <textarea id="modal_general_info" name="general_info" rows="3" placeholder="<?php echo esc_attr__('Any additional notes for the payment (optional)', 'wevoi-student-dashboard'); ?>"></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="submit-btn" id="bank-modal-submit-btn"><?php _e('Submit Bank Details', 'wevoi-student-dashboard'); ?></button>
                    <button type="button" class="cancel-btn modal-close"><?php _e('Cancel', 'wevoi-student-dashboard'); ?></button>
                </div>
                <input type="hidden" id="modal_registration_id" name="registration_id" value="">
            </form>
            <div id="modal-message" class="modal-message" style="display: none;"></div>
        </div>
    </div>
</div>

<script type="text/javascript">
    // Set up nonce for AJAX calls
    var wevoiStudentNonce = '<?php echo esc_js($nonce); ?>';

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
                    nonce: wevoiStudentNonce,
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
                        var $message = $('<div class="visa-status-message" style="margin-top: 5px; padding: 5px; background: #d4edda; color: #155724; border-radius: 4px; font-size: 12px; text-align: center;"><?php _e('Visa status updated successfully', 'wevoi-student-dashboard'); ?></div>');
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
                        alert(response.data || '<?php _e('Failed to update visa status', 'wevoi-student-dashboard'); ?>');
                    }
                },
                error: function() {
                    // Revert to original selection
                    if (originalValue) {
                        $radioGroup.find('input[value="' + originalValue + '"]').prop('checked', true);
                    } else {
                        $radio.prop('checked', false);
                    }
                    alert('<?php _e('Error updating visa status', 'wevoi-student-dashboard'); ?>');
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

        // Auto-select if only one option in dropdown (excluding "Select Airport")
        function autoSelectSingleOption() {
            $('.flight-info-form select.flight-input').each(function() {
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
            $('.flight-info-form .flight-input').each(function() {
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
        $('.flight-info-form .flight-input').on('input change', function() {
            checkFilledFields();
        });

        // Handle flight information save
        $('.flight-info-form').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            var $saveBtn = $form.find('.flight-save-btn');
            var $message = $form.find('.flight-save-message');
            var registrationId = $form.data('registration-id');

            // Disable save button
            $saveBtn.prop('disabled', true).text('<?php _e('Saving...', 'wevoi-student-dashboard'); ?>');
            $message.hide().removeClass('success error');

            // Collect form data
            var formData = {
                action: 'wevoi_save_flight_info',
                nonce: wevoiStudentNonce,
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
                        $message.text('<?php _e('Flight information saved successfully', 'wevoi-student-dashboard'); ?>')
                                .addClass('success')
                                .show();
                        setTimeout(function() {
                            $message.fadeOut();
                        }, 3000);
                    } else {
                        $message.text(response.data || '<?php _e('Failed to save flight information', 'wevoi-student-dashboard'); ?>')
                                .addClass('error')
                                .show();
                    }
                },
                error: function() {
                    $message.text('<?php _e('Error saving flight information. Please try again.', 'wevoi-student-dashboard'); ?>')
                            .addClass('error')
                            .show();
                },
                complete: function() {
                    $saveBtn.prop('disabled', false).text('<?php _e('Save Flight Information', 'wevoi-student-dashboard'); ?>');
                }
            });
        });
    });
</script>

