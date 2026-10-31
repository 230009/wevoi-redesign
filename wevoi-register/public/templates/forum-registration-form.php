<?php
/**
 * Forum registration form template
 */

if (!defined('ABSPATH')) {
    exit;
}

$form_mode = isset($form_mode) ? $form_mode : 'new';
$forum_payment_phase = isset($forum_payment_phase) ? $forum_payment_phase : 'pre_registration';
$is_payment_completion = ($form_mode === 'payment_completion');
$is_blocked = in_array($form_mode, array('blocked_complete', 'blocked_pre_registration', 'blocked_payment_pending'), true);

$user = wp_get_current_user();
$user_roles = (array) $user->roles;
$current_role = 'student';
if (in_array('supervisor', $user_roles)) {
    $current_role = 'supervisor';
} elseif (in_array('parent', $user_roles)) {
    $current_role = 'parent';
} elseif (in_array('agency', $user_roles)) {
    $current_role = 'agency';
}
?>

<div id="wevoi-forum-form" class="wevoi-form-container">
    <div id="wevoi-notification" class="wevoi-notification" style="display: none;"></div>

    <?php if ($form_mode === 'blocked_complete'): ?>
        <div class="wevoi-card" style="background-color: #fff3cd; border: 2px solid #ffc107; margin-bottom: 20px;">
            <div style="text-align: center; padding: 20px;">
                <h3 style="color: #856404; margin-bottom: 10px;">Already Registered</h3>
                <p style="color: #856404; font-size: 16px; margin: 0;">
                    You have already completed registration for this Forum. You cannot register again.
                </p>
            </div>
        </div>
    <?php elseif ($form_mode === 'blocked_pre_registration'): ?>
        <div class="wevoi-card" style="background-color: #d1ecf1; border: 2px solid #17a2b8; margin-bottom: 20px;">
            <div style="text-align: center; padding: 20px;">
                <h3 style="color: #0c5460; margin-bottom: 10px;">Pre-Registration Received</h3>
                <p style="color: #0c5460; font-size: 16px; margin: 0;">
                    Your pre-registration is on file. No payment is required at this stage. When payment opens, you will complete checkout from your dashboard or this page.
                </p>
            </div>
        </div>
    <?php elseif ($form_mode === 'blocked_payment_pending'): ?>
        <div class="wevoi-card" style="background-color: #fff3cd; border: 2px solid #ffc107; margin-bottom: 20px;">
            <div style="text-align: center; padding: 20px;">
                <h3 style="color: #856404; margin-bottom: 10px;">Payment In Progress</h3>
                <p style="color: #856404; font-size: 16px; margin: 0;">
                    A payment has already been started for this registration. If you need help, please contact WeVoi support.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$is_blocked): ?>
    <form id="forum-registration-form">
        <?php if ($is_payment_completion && !empty($existing_registration)): ?>
            <div class="wevoi-card" style="background-color: #e7f3ff; border: 1px solid #0066cc; margin-bottom: 20px;">
                <h3 style="margin-top: 0;">Complete Forum payment</h3>
                <p style="margin-bottom: 0;">Pre-registration is complete. Enter payer details and pay the amount below to confirm your place.</p>
            </div>
            <input type="radio" name="competition" class="wevoi-forum-competition-hidden"
                   value="<?php echo esc_attr($existing_registration->competition_code); ?>"
                   data-price="<?php echo esc_attr($price); ?>"
                   checked style="position:absolute;opacity:0;width:0;height:0;pointer-events:none;">
        <?php endif; ?>

        <div class="wevoi-card wevoi-forum-competition-section" <?php echo $is_payment_completion ? 'style="display:none;"' : ''; ?>>
            <h3>Forum Competition</h3>
            <div class="wevoi-competition-list">
                <?php if (!empty($competitions)): ?>
                    <?php foreach ($competitions as $competition): ?>
                        <div class="wevoi-competition-item">
                            <label>
                                <input type="radio" name="competition" value="<?php echo esc_attr($competition->competition_code); ?>"
                                       data-price="<?php echo esc_attr($price); ?>"
                                       <?php echo ($competition === $selected_competition) ? 'checked' : ''; ?>>
                                <div class="wevoi-competition-info">
                                    <div class="wevoi-competition-name-date">
                                        <?php echo esc_html($competition->competition_name); ?> -
                                        <?php echo esc_html(date('d/m/Y', strtotime($competition->competition_date))); ?>
                                    </div>
                                    <?php if (!empty($competition->event_venue) || !empty($competition->event_duration)): ?>
                                        <div class="wevoi-competition-details">
                                            <?php if (!empty($competition->event_venue)): ?>
                                                <span class="wevoi-event-venue"><?php echo esc_html($competition->event_venue); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($competition->event_duration)): ?>
                                                <span class="wevoi-event-duration"><?php echo esc_html($competition->event_duration); ?> <?php echo ($competition->event_duration == 1) ? 'day' : 'days'; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No active Forum competitions available.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$is_payment_completion && $forum_payment_phase === 'pre_registration'): ?>
        <div class="wevoi-card" style="background-color: #e7f3ff; border: 1px solid #0066cc; margin-bottom: 16px;">
            <p style="margin: 0;"><strong>Pre-registration:</strong> you are signing up without payment. Any applicable fee will be due later when WeVoi opens payment. You may apply a coupon now; the discounted amount will be used when you pay.</p>
        </div>
        <?php endif; ?>

        <div class="wevoi-card wevoi-forum-user-section" <?php echo $is_payment_completion ? 'style="display:none;"' : ''; ?>>
            <h3>Your Information</h3>

            <div class="wevoi-user-name-display">
                <?php
                $first_name = trim($user_info['first_name'] ?? '');
                $last_name = trim($user_info['last_name'] ?? '');

                if (!empty($first_name) || !empty($last_name)) {
                    $full_name = trim($first_name . ' ' . $last_name);
                } else {
                    $full_name = $user->display_name ?? $user->user_login;
                }
                echo esc_html($full_name);
                ?>
            </div>

            <?php if ($current_role === 'student'): ?>
                <div class="wevoi-inline-row">
                    <div class="wevoi-form-row">
                        <label for="whatsapp_number">WhatsApp Number <span class="required">*</span></label>
                        <input type="text" id="whatsapp_number" name="whatsapp_number"
                               value="<?php echo esc_attr($user_info['whatsapp'] ?? ''); ?>" <?php echo $is_payment_completion ? '' : 'required'; ?>>
                    </div>
                    <div class="wevoi-form-row">
                        <label for="passport_number">Passport Number <span class="required">*</span></label>
                        <input type="text" id="passport_number" name="passport_number" <?php echo $is_payment_completion ? '' : 'required'; ?>>
                    </div>
                </div>

                <div class="wevoi-card">
                    <h3>Supervisor/Agency (Optional)</h3>
                    <div class="wevoi-form-row">
                        <label for="supervisor_agency_email">Email</label>
                        <input type="email" id="supervisor_agency_email" name="supervisor_agency_email" placeholder="supervisor@example.com" autocomplete="off">
                        <div id="supervisor-agency-name" class="wevoi-supervisor-name-display" style="margin-top: 6px; font-weight: bold; color: #333;"></div>
                        <input type="hidden" id="supervisor_id" name="supervisor_id" value="">
                    </div>
                </div>

                <div class="wevoi-card">
                    <h3>Parent Information (Optional)</h3>
                    <div class="wevoi-form-row">
                        <label for="parent_email">Parent Email</label>
                        <input type="email" id="parent_email" name="parent_email"
                               value="<?php echo isset($parent_info) && !empty($parent_info['email']) ? esc_attr($parent_info['email']) : ''; ?>">
                    </div>
                    <div class="wevoi-inline-row">
                        <div class="wevoi-form-row">
                            <label for="parent_first_name">Parent First Name</label>
                            <input type="text" id="parent_first_name" name="parent_first_name"
                                   value="<?php echo isset($parent_info) && !empty($parent_info['first_name']) ? esc_attr($parent_info['first_name']) : ''; ?>">
                        </div>
                        <div class="wevoi-form-row">
                            <label for="parent_last_name">Parent Last Name</label>
                            <input type="text" id="parent_last_name" name="parent_last_name"
                                   value="<?php echo isset($parent_info) && !empty($parent_info['last_name']) ? esc_attr($parent_info['last_name']) : ''; ?>">
                        </div>
                    </div>
                    <div class="wevoi-form-row">
                        <label for="parent_whatsapp">Parent WhatsApp Number</label>
                        <input type="text" id="parent_whatsapp" name="parent_whatsapp"
                               value="<?php echo isset($parent_info) && !empty($parent_info['whatsapp']) ? esc_attr($parent_info['whatsapp']) : ''; ?>">
                    </div>
                </div>

            <?php elseif ($current_role === 'supervisor' || $current_role === 'parent' || $current_role === 'agency'): ?>
                <div class="wevoi-inline-row">
                    <div class="wevoi-form-row">
                        <label for="gender">Gender <span class="required">*</span></label>
                        <select id="gender" name="gender" <?php echo $is_payment_completion ? '' : 'required'; ?>>
                            <option value="">Select Gender</option>
                            <option value="Male" <?php selected($user_info['gender'] ?? '', 'Male'); ?>>Male</option>
                            <option value="Female" <?php selected($user_info['gender'] ?? '', 'Female'); ?>>Female</option>
                        </select>
                    </div>

                    <div class="wevoi-form-row">
                        <label for="birth_date">Date of Birth <span class="required">*</span></label>
                        <input type="date" id="birth_date" name="birth_date"
                               value="<?php echo esc_attr($user_info['birth_date'] ?? ''); ?>"
                               min="<?php echo date('Y-m-d', strtotime('-100 years')); ?>" <?php echo $is_payment_completion ? '' : 'required'; ?>>
                    </div>

                    <div class="wevoi-form-row">
                        <label for="whatsapp_number">WhatsApp Number <span class="required">*</span></label>
                        <input type="text" id="whatsapp_number" name="whatsapp_number"
                               value="<?php echo esc_attr($user_info['whatsapp'] ?? ''); ?>" <?php echo $is_payment_completion ? '' : 'required'; ?>>
                    </div>

                    <div class="wevoi-form-row">
                        <label for="passport_number">Passport Number <span class="required">*</span></label>
                        <input type="text" id="passport_number" name="passport_number" <?php echo $is_payment_completion ? '' : 'required'; ?>>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div id="payer-info-section" class="wevoi-card">
            <h3>Payer Information</h3>
            <div class="wevoi-inline-row">
                <div class="wevoi-form-row">
                    <label for="payer-name">Full Name <span class="required">*</span></label>
                    <input type="text" id="payer-name" name="payer_name" required>
                </div>
                <div class="wevoi-form-row">
                    <label for="payer-country">Country <span class="required">*</span></label>
                    <select id="payer-country" name="payer_countryid" required>
                        <option value="">Select Country</option>
                        <?php
                        global $wpdb;
                        $countries = $wpdb->get_results("SELECT countryid, countryname FROM {$wpdb->prefix}wevoi_countries ORDER BY countryname");
                        foreach ($countries as $country):
                        ?>
                            <option value="<?php echo esc_attr($country->countryid); ?>">
                                <?php echo esc_html($country->countryname); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <h3 class="wevoi-section-subtitle">Payment Information</h3>
            <div class="wevoi-form-row">
                <label for="card-element">Credit Card <span class="required">*</span></label>
                <div id="card-element" class="wevoi-stripe-element"></div>
                <div id="card-errors" role="alert" class="wevoi-error"></div>
            </div>
        </div>

        <div class="wevoi-amount-coupon-row wevoi-forum-pricing-row" <?php echo $is_payment_completion ? 'style="display:none;"' : ''; ?>>
            <div class="wevoi-card">
                <h3>Total Amount</h3>
                <div class="wevoi-total-amount">
                    <div class="wevoi-price-info">
                        <span class="wevoi-price-label">Price for <?php echo ucfirst($current_role); ?>:</span>
                        <span class="wevoi-price-value">£<span id="wevoi-price"><?php echo number_format($price, 2); ?></span></span>
                    </div>
                </div>
            </div>

            <div class="wevoi-card">
                <h3>Use Discount Coupon</h3>
                <div class="wevoi-form-row wevoi-coupon-container">
                    <input type="text" id="coupon-code" name="coupon_code" placeholder="Enter coupon code">
                    <button type="button" id="apply-coupon" class="wevoi-button">Apply</button>
                    <button type="button" id="change-coupon" class="wevoi-button" style="display: none;">Change</button>
                </div>
                <div id="coupon-message" class="wevoi-message"></div>
            </div>
        </div>

        <?php if ($is_payment_completion): ?>
        <div class="wevoi-card wevoi-forum-completion-amount">
            <h3>Amount due</h3>
            <div class="wevoi-total-amount">
                <div class="wevoi-price-info">
                    <span class="wevoi-price-label">Total to pay:</span>
                    <span class="wevoi-price-value">£<span id="wevoi-price"><?php echo number_format($price, 2); ?></span></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="wevoi-card">
            <div class="wevoi-consent-container">
                <label class="wevoi-consent-label">
                    <input type="checkbox" id="consent-checkbox" required>
                    I confirm that the information provided is accurate and complete. I agree to the <a href="https://www.wevoi.org/terms-conditions/" target="_blank">terms & conditions.</a>
                </label>
            </div>
            <button type="submit" id="submit-button" class="wevoi-button wevoi-button-primary">PAY NOW</button>
        </div>
    </form>
    <?php endif; ?>
</div>
