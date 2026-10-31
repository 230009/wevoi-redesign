<div class="wevoi-my-competitions">
    <?php
    global $wpdb; // Add global $wpdb to access the database

    // Helper: title case (first letter of each word upper), UTF-8 safe. "i" → "I" not "İ".
    if (!function_exists('wevoi_capitalize_name')) {
        function wevoi_capitalize_name($string) {
            if ($string === null || $string === '') {
                return (string) $string;
            }
            $string = (string) $string;
            $enc = 'UTF-8';
            $words = preg_split('/(\s+)/u', $string, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            $out = array();
            foreach ($words as $w) {
                if (preg_match('/^\s+$/u', $w)) {
                    $out[] = $w;
                    continue;
                }
                $len = mb_strlen($w, $enc);
                if ($len === 0) {
                    $out[] = $w;
                    continue;
                }
                $firstCh = mb_substr($w, 0, 1, $enc);
                // Explicit "i" → "I" so Turkish locale never produces "İ"
                $first = ($firstCh === 'i') ? 'I' : mb_strtoupper($firstCh, $enc);
                $rest  = $len > 1 ? mb_strtolower(mb_substr($w, 1, $len - 1, $enc), $enc) : '';
                $out[] = $first . $rest;
            }
            return implode('', $out);
        }
    }

    $user = wp_get_current_user();
    $is_admin = in_array('administrator', (array) $user->roles);
    if (in_array('supervisor', (array) $user->roles)) {
        $page_heading = 'Video Submissions of My Students';
    } elseif (in_array('parent', (array) $user->roles)) {
        $page_heading = 'Video Submissions of My Children';
    } elseif (in_array('agency', (array) $user->roles)) {
        $page_heading = 'Video Submissions of My Students';
    } else {
        $page_heading = 'My Video Submissions';
    }
    ?>
    <h1 class="wevoi-submissions-page-title"><?php echo esc_html($page_heading); ?></h1>

    <?php
    // Award Ceremony Banner: shown once at the top if any registration's competition is RANKINGS_FINALIZED with a zoom link
    $ceremony_comp = null;
    foreach ($registrations as $reg_item) {
        if (
            isset($reg_item->competition_status) &&
            $reg_item->competition_status === 'RANKINGS_FINALIZED' &&
            !empty($reg_item->award_ceremony_zoom_link)
        ) {
            $ceremony_comp = $reg_item;
            break;
        }
    }
    if ($ceremony_comp):
        $ceremony_date_str = '';
        if (!empty($ceremony_comp->award_ceremony_date)) {
            $ts = strtotime($ceremony_comp->award_ceremony_date);
            $ceremony_date_str = date_i18n('l, F j, Y', $ts) . ' at ' . date('H:i', $ts) . ' (UK Time)';
        }
    ?>
    <div style="background: linear-gradient(135deg,#6a11cb 0%,#2575fc 100%); border-radius: 12px; padding: 28px 32px; margin-bottom: 28px; color: #fff; box-shadow: 0 4px 20px rgba(37,117,252,.35);">
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

    <?php foreach ($registrations as $registration): ?>
        <div class="wevoi-competition-card">
            <div class="wevoi-competition-header">
                <div class="wevoi-competition-title">
                    <h3><?php echo esc_html($registration->competition_name); ?></h3>
                    <?php if ($is_admin): ?>
                        <div class="wevoi-user-info">
                            <span class="user-name">
                                <?php
                                $first_name = !empty($registration->first_name) ? wevoi_capitalize_name($registration->first_name) : '';
                                $last_name = !empty($registration->last_name) ? wevoi_capitalize_name($registration->last_name) : '';
                                echo esc_html(trim($first_name . ' ' . $last_name));
                                ?>
                            </span>
                            <span class="user-email">
                                <?php echo esc_html($registration->user_email); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="wevoi-status-container">
                    <span class="wevoi-status <?php echo esc_attr(strtolower($registration->paid_status)); ?>">
                        <?php echo esc_html($registration->paid_status); ?>
                    </span>
                    <?php if ($is_admin): ?>
                        <select class="wevoi-application-status-select"
                                data-registration-id="<?php echo esc_attr($registration->register_id); ?>">
                            <?php
                            $status_options = [
                                'PENDING_REVIEW' => 'Pending Review',
                                'APPLICATION_ACCEPTED' => 'Accepted',
                                'VIDEO_INACCESSIBLE' => 'Video Inaccessible',
                                'IRRELEVANT_CONTENT' => 'Irrelevant Content',
                                'APPLICATION_REJECTED' => 'Rejected'
                            ];
                            foreach ($status_options as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>"
                                        <?php selected($registration->application_status, $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <span class="wevoi-application-status <?php echo esc_attr(strtolower($registration->application_status)); ?>">
                            <?php
                            $status_labels = [
                                'PENDING_REVIEW' => 'Pending Review',
                                'APPLICATION_ACCEPTED' => 'Accepted',
                                'VIDEO_UNLISTED' => 'Unlisted',
                                'VIDEO_INACCESSIBLE' => 'Video Inaccessible',
                                'IRRELEVANT_CONTENT' => 'Irrelevant Content',
                                'APPLICATION_REJECTED' => 'Rejected'
                            ];
                            echo esc_html($status_labels[$registration->application_status] ?? $registration->application_status);
                            ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="wevoi-competition-content">
                <div class="wevoi-competition-details">
                    <div class="wevoi-detail-row">
                        <span class="label">Student:</span>
                        <span class="value">
                            <?php
                            $first_name = !empty($registration->first_name) ? wevoi_capitalize_name($registration->first_name) : '';
                            $last_name = !empty($registration->last_name) ? wevoi_capitalize_name($registration->last_name) : '';
                            echo esc_html(trim($first_name . ' ' . $last_name));
                            ?>
                        </span>
                    </div>

                    <div class="wevoi-detail-row">
                        <span class="label">Country:</span>
                        <span class="value"><?php echo esc_html($registration->countryname); ?></span>
                    </div>

                    <?php if (!empty($registration->supervisor_id) && (!empty($registration->supervisor_name) || !empty($registration->supervisor_email))): ?>
                    <?php
                    $sup_role_label = __('Supervisor/Agency', 'wevoi-register');
                    $sup_user = get_userdata($registration->supervisor_id);
                    if ($sup_user && !empty($sup_user->roles)) {
                        if (in_array('agency', $sup_user->roles, true)) {
                            $sup_role_label = __('Agency', 'wevoi-register');
                        } elseif (in_array('supervisor', $sup_user->roles, true)) {
                            $sup_role_label = __('Supervisor', 'wevoi-register');
                        }
                    }
                    ?>
                    <div class="wevoi-detail-row">
                        <span class="label"><?php echo esc_html($sup_role_label); ?>:</span>
                        <span class="value">
                            <?php
                            $sup_name = trim($registration->supervisor_name ?? '');
                            if (empty($sup_name)) {
                                $sup_name = !empty($registration->supervisor_email) ? $registration->supervisor_email : '—';
                            } else {
                                $sup_name = wevoi_capitalize_name($sup_name);
                            }
                            if (!empty($registration->supervisor_email)) {
                                echo '<a href="mailto:' . esc_attr($registration->supervisor_email) . '" class="wevoi-supervisor-link">' . esc_html($sup_name) . '</a>';
                            } else {
                                echo esc_html($sup_name);
                            }
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <div class="wevoi-detail-row">
                        <span class="label">Competition Date:</span>
                        <span class="value">
                            <?php echo esc_html(date('d M Y', strtotime($registration->competition_date))); ?>
                        </span>
                    </div>

                    <div class="wevoi-detail-row">
                        <span class="label">Registration Date:</span>
                        <span class="value">
                            <?php echo esc_html(date('d M Y H:i', strtotime($registration->registration_date))); ?>
                        </span>
                    </div>

                    <div class="wevoi-detail-row">
                        <span class="label">Age Category:</span>
                        <span class="value"><?php echo !empty($registration->age_category) ? esc_html($registration->age_category) : 'N/A'; ?></span>
                    </div>

                    <div class="wevoi-detail-row">
                        <span class="label">Topic:</span>
                        <span class="value"><?php echo !empty($registration->topic) ? esc_html($registration->topic) : 'N/A'; ?></span>
                    </div>

                    <?php if ($registration->price > 0): ?>
                        <div class="wevoi-detail-row">
                            <span class="label">Payment Amount:</span>
                            <span class="value">£<?php echo number_format($registration->price, 2); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($registration->refund_amount) && !empty($registration->refund_date)): ?>
                        <div class="wevoi-detail-row refund">
                            <span class="label">Refunded Amount:</span>
                            <span class="value">£<?php echo number_format($registration->refund_amount, 2); ?></span>
                            <div class="refund-date">
                                Refunded on <?php echo esc_html(date('d M Y H:i', strtotime($registration->refund_date))); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Show specific warning messages based on application status
                    switch ($registration->application_status) {
                        case 'VIDEO_INACCESSIBLE':
                            echo '<div class="wevoi-detail-row warning-message">
                                    <span class="value" style="color: red;">
                                        Your video link is broken or inaccessible. Please update your video link.
                                    </span>
                                  </div>';
                            break;

                        case 'IRRELEVANT_CONTENT':
                            echo '<div class="wevoi-detail-row warning-message">
                                    <span class="value" style="color: red;">
                                        Your video content does not match with the competition topic. Please submit a relevant video.
                                    </span>
                                </div>';
                            break;

                        case 'APPLICATION_REJECTED':
                            echo '<div class="wevoi-detail-row warning-message">
                                    <span class="value" style="color: red;">
                                        Your application has been rejected. Please check the competition rules and update your video.
                                    </span>
                                  </div>';
                            break;

                        case 'PENDING_REVIEW':
                            echo '<div class="wevoi-detail-row warning-message">
                                    <span class="value" style="color: orange;">
                                        Your application is under review and will be finalized as soon as possible.
                                    </span>
                                  </div>';
                            break;
case 'APPLICATION_ACCEPTED':
    if ($registration->competition_status === 'ON AIR') {
        echo '<div class="wevoi-detail-row warning-message">
                <span class="value" style="color: green;">
                    Congratulations, your application has been accepted. After the deadline, your video will be evaluated by our esteemed judges, and the results will be announced.
                </span>
            </div>';
    }

// Yeni kod (sonuçları göstermek için)
if (!empty($registration->student_rank) || !empty($registration->student_medal) || !empty($registration->claimed_summit_coupon_code)) {
    // RANKINGS_FINALIZED durumunda sadece admin görebilir
    if ($registration->competition_status === 'RANKINGS_FINALIZED' && !current_user_can('manage_options')) {
        echo '<div style="text-align: center; padding-top: 20px;">
            <div style="display: inline-block; background-color: #F5F5F5; padding: 10px 20px; border-radius: 8px;">
                <span style="font-weight: 100; color: orange;">
                    Results are being prepared. They will be available after the Award Ceremony is completed.
                </span>
            </div>
        </div>';
    } elseif ($registration->competition_status === 'COMPLETED' && !$registration->award_ceremony_completed) {
        echo '<div style="text-align: center; padding-top: 20px;">
            <div style="display: inline-block; background-color: #F5F5F5; padding: 10px 20px; border-radius: 8px;">
                <span style="font-weight: 100; color: orange;">
                    Results will be available after the Award Ceremony is completed.
                </span>
            </div>
        </div>';
    } elseif ($registration->competition_status === 'COMPLETED' && $registration->award_ceremony_completed) {
    echo '<div style="text-align: center; padding-top: 20px;">
        <div style="display: inline-block; background-color: #F5F5F5; padding: 20px; border-radius: 8px; width: 100%;">
            <h4 style="margin-top: 0; margin-bottom: 15px; color: #333; font-size: 18px;">Competition Results</h4>
            <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 20px;">';

    // Rank (Sıralama) bilgisi
    if (!empty($registration->student_rank)) {
        echo '<div style="text-align: center; min-width: 100px;">
            <div style="font-weight: bold; font-size: 30px; color: #0066cc; margin-bottom: 5px;">' . esc_html($registration->student_rank) . '</div>
            <div style="font-size: 14px; color: #666;">Rank</div>
        </div>';
    }

    // Medal (Madalya) bilgisi
    if (!empty($registration->student_medal)) {
        $medal_color = '';
        $medal_text = '';

        switch ($registration->student_medal) {
            case 'GOLD':
                $medal_color = '#FFD700';
                $medal_text = 'Gold Medal';
                break;
            case 'SILVER':
                $medal_color = '#C0C0C0';
                $medal_text = 'Silver Medal';
                break;
            case 'BRONZE':
                $medal_color = '#CD7F32';
                $medal_text = 'Bronze Medal';
                break;
            case 'SUCCESS':
                $medal_color = '#28a745';
                $medal_text = 'Success Certificate';
                break;
        }

        echo '<div style="text-align: center; min-width: 100px;">
            <div style="font-weight: bold; font-size: 24px; color: ' . $medal_color . '; margin-bottom: 5px;">
                <span style="font-size: 30px;">🏅</span>
            </div>
            <div style="font-size: 14px; color: #666;">' . esc_html($medal_text) . '</div>
        </div>';
    }

    // Summit Coupon bilgisi
    if (!empty($registration->claimed_summit_coupon_code)) {
        echo '<div style="text-align: center; min-width: 150px;">
            <div style="font-weight: bold; font-size: 18px; background-color: #e9f5ff; padding: 8px; border-radius: 4px; color: #0066cc; margin-bottom: 5px;">' .
                esc_html($registration->claimed_summit_coupon_code) .
            '</div>
            <div style="font-size: 14px; color: #666;">WeVoi Summit Free Coupon</div>
        </div>';
    }

    // Points bilgisi
    if (!empty($registration->student_point)) {
        echo '<div style="text-align: center; min-width: 100px;">
            <div style="font-weight: bold; font-size: 30px; color: #28a745; margin-bottom: 5px;">' .
                number_format($registration->student_point, 2) .
            '</div>
            <div style="font-size: 14px; color: #666;">Points</div>
        </div>';
    }

    // Prize Amount bilgisi (WeVoi Summit para ödülü)
    if (!empty($registration->claimed_prize_amount) && $registration->claimed_prize_amount > 0) {
        echo '<div style="text-align: center; min-width: 120px;">
            <div style="font-weight: bold; font-size: 30px; color: #FF6B35; margin-bottom: 5px;">
                <span style="font-size: 24px;">£</span>' .
                number_format($registration->claimed_prize_amount, 2) .
            '</div>
            <div style="font-size: 14px; color: #666; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <span>Cash Prize</span>';

        // Prize payment status - aynı satırda
        if (!empty($registration->claimed_prize_paid_status)) {
            $status_color = ($registration->claimed_prize_paid_status === 'PAID') ? '#28a745' : '#ffc107';
            $status_text = ($registration->claimed_prize_paid_status === 'PAID') ? 'Prize Paid' : 'Prize Not Paid Yet';

            echo '<span style="font-size: 10px; color: ' . $status_color . '; font-weight: bold; padding: 1px 4px; border-radius: 3px; background-color: ' . $status_color . '20;">
                    ' . esc_html($status_text) . '
                </span>';
        }

        echo '</div>';

        // Bank account details link - sadece ödenmemiş ödüller için
        if (empty($registration->claimed_prize_paid_status) || $registration->claimed_prize_paid_status !== 'PAID') {
            echo '<div style="margin-top: 8px; text-align: center;">
                <a href="' . home_url('/bank-account-details/?registration_id=' . $registration->register_id) . '"
                   style="font-size: 12px; color: #007cba; text-decoration: none; padding: 4px 8px; border: 1px solid #007cba; border-radius: 3px; background-color: #f8f9fa;">
                    <span style="margin-right: 4px;">🏦</span>Add Bank Details
                </a>
            </div>';
        }

        echo '</div>';
    }

    echo '</div>';

    // Add certificate download link - START NEW CODE
    if (!empty($registration->student_medal)) {
        // Get certificate link from database
        $certificate_link = $wpdb->get_var($wpdb->prepare(
            "SELECT certificate_link
            FROM {$wpdb->prefix}wevoi_certificates
            WHERE user_id = %d
            AND competition_code = %d
            AND role = 'student'",
            $registration->user_id,
            $registration->competition_code
        ));

        if (!empty($certificate_link)) {
            echo '<div style="margin-top: 15px; text-align: center;">
                <a href="' . esc_url($certificate_link) . '" target="_blank"
                   style="display: inline-block; background-color: #4CAF50; color: white; padding: 10px 20px;
                          text-decoration: none; border-radius: 4px; font-weight: bold;">
                    <i class="fa fa-certificate" style="margin-right: 5px;"></i> Download Certificate
                </a>
            </div>';
        }
    }
    // Add certificate download link - END NEW CODE

    echo '</div></div>';
    }
} else {
    echo '<div style="text-align: center; padding-top: 20px;">
        <div style="display: inline-block; background-color: #F5F5F5; padding: 10px 20px; border-radius: 8px;">
            <span style="font-weight: 100; color: purple;">
                Results will be announced soon!
            </span>
        </div>
    </div>';
}

                            break;
                    }
                    ?>
                </div>

                <div class="wevoi-video-container">
                <?php
                // Extract video ID from various YouTube URL formats including Shorts
                    $video_id = '';
                $patterns = array(
                // Standard YouTube URLs
                    '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/',
                    // YouTube Shorts URLs
                    '/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/'
                );

foreach ($patterns as $pattern) {
    if (preg_match($pattern, $registration->youtube_link, $match)) {
        $video_id = $match[1];
        break;
    }
}
                    if ($video_id): ?>
                        <iframe
                            width="100%"
                            height="315"
                            src="https://www.youtube.com/embed/<?php echo esc_attr($video_id); ?>"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen>
                        </iframe>
                    <?php else: ?>
                        <a href="<?php echo esc_url($registration->youtube_link); ?>"
                           target="_blank"
                           class="youtube-link">
                            View Video on YouTube
                        </a>
                    <?php endif; ?>

<!-- Video güncelleme arayüzü -->
<?php if (!$is_admin || $registration->competition_status === 'ON AIR'): ?>
<?php
// ON AIR yarışmalarda video güncelleme izni ver
$can_update_video = ($registration->competition_status === 'ON AIR');
?>
<div class="wevoi-video-update-container"
     data-registration-id="<?php echo esc_attr($registration->register_id); ?>"
     data-competition-status="<?php echo esc_attr($registration->competition_status); ?>">
    <input type="text"
           class="wevoi-video-link-input"
           value="<?php echo esc_attr($registration->youtube_link); ?>"
           placeholder="Enter new YouTube link"
           data-registration-id="<?php echo esc_attr($registration->register_id); ?>"
           <?php echo !$can_update_video ? 'disabled' : ''; ?>
    >
    <button class="wevoi-update-video-button"
            data-registration-id="<?php echo esc_attr($registration->register_id); ?>"
            <?php echo !$can_update_video ? 'disabled' : ''; ?>>
        Update Video Link
    </button>
    <?php if (!$can_update_video): ?>
        <div class="video-update-disabled-message">Video updates are only allowed for competitions that are currently ON AIR</div>
    <?php endif; ?>
</div>
<?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
