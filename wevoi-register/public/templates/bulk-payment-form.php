<?php
/**
 * Bulk / supervisor video registration form.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="wevoi_bulk_register_form" class="wevoi-form-container wevoi-register-ui">
    <div id="wevoi-notification" class="wevoi-notification" style="display: none;"></div>

    <form id="wevoi_bulk_register_form_inner">
        <div class="wevoi-card">
            <h3>Available Competitions</h3>
            <div class="wevoi-competition-list">
                <?php foreach ($competitions as $competition): ?>
                    <div class="wevoi-competition-item">
                        <label>
                            <input type="radio" name="competition"
                                   value="<?php echo esc_attr($competition->competition_code); ?>"
                                   data-price="<?php echo esc_attr($competition->competition_price); ?>"
                                   data-name="<?php echo esc_attr($competition->competition_name); ?>">
                            <?php echo esc_html($competition->competition_name); ?> -
                            <?php echo esc_html(date('d/m/Y', strtotime($competition->competition_date))); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="wevoi-card">
            <h3>Select students to submit their videos</h3>
            <p class="wevoi-bulk-help">
                Students appear below if they are linked to you (from a past registration or by having your email as Supervisor/Agency in their profile). You can also <strong>add any student by email</strong> if they have already signed up on the platform.
            </p>
            <div class="wevoi-add-by-email">
                <label for="wevoi-add-student-email">Add student by email</label>
                <div class="wevoi-add-by-email-row">
                    <input type="email" id="wevoi-add-student-email" placeholder="student@example.com" autocomplete="off">
                    <button type="button" id="wevoi-add-student-btn" class="wevoi-button">Add</button>
                </div>
                <div id="wevoi-add-student-msg" class="wevoi-message"></div>
            </div>
            <div class="wevoi-student-list">
                <?php if (empty($eligible_students)): ?>
                <p id="wevoi-no-students-msg" class="wevoi-no-students-msg">No students in the list yet. Select a competition above, then add students by email or ask students to link you in their profile (Supervisor/Agency email).</p>
                <?php endif; ?>
                <?php foreach ($eligible_students as $student): ?>
                    <?php
                    $student_name = trim((string) $student->student_name);
                    $student_country = !empty($student->countryname) ? $student->countryname : '';
                    $student_topic = !empty($student->topic) ? $student->topic : '';
                    $student_age = !empty($student->age_category) ? $student->age_category : '';
                    ?>
                    <div class="wevoi-student-item"
                         data-student-id="<?php echo esc_attr($student->ID); ?>"
                         data-student-name="<?php echo esc_attr($student_name); ?>"
                         data-student-country="<?php echo esc_attr($student_country); ?>"
                         data-age-category="<?php echo esc_attr($student_age); ?>"
                         data-topic="<?php echo esc_attr($student_topic); ?>">
                        <div class="wevoi-student-header">
                            <label class="wevoi-student-select">
                                <input type="checkbox" name="selected_students[]"
                                       value="<?php echo esc_attr($student->ID); ?>">
                                <?php echo esc_html($student_name); ?>
                                <a href="mailto:<?php echo esc_attr($student->email); ?>"
                                   class="student-email-link">
                                    <?php echo esc_html($student->email); ?>
                                </a>
                            </label>
                            <div class="wevoi-student-details">
                                <div class="age-category-wrapper">
                                    <?php if ($student_age !== ''): ?>
                                        <span class="age-category wevoi-age-category-value"><?php echo esc_html($student_age); ?></span>
                                    <?php endif; ?>
                                    <?php if ($student_topic !== ''): ?>
                                        <div class="student-topic">Topic: <?php echo esc_html($student_topic); ?></div>
                                    <?php endif; ?>
                                </div>
                                <span class="school-name"><?php echo esc_html($student->schoolname); ?></span>
                            </div>
                        </div>
                        <div class="wevoi-student-video-title">
                            <span class="wevoi-student-video-title-label">Video Title</span>
                            <p class="wevoi-student-video-title-text"></p>
                        </div>
                        <div class="wevoi-student-youtube">
                            <input type="url" name="youtube_link_<?php echo esc_attr($student->ID); ?>"
                                   class="youtube-link-input" placeholder="Enter YouTube Link" disabled>
                        </div>
                    </div>
                <?php endforeach; ?>
                <p class="wevoi-bulk-note">
                    <strong>Please note: </strong>You can only submit videos for students who already have an account. Use &quot;Add student by email&quot; to add any student who has signed up; they do not need to be pre-linked to you.
                </p>
                <p class="wevoi-bulk-warning">
                    <strong>Important: </strong>Please ensure that the videos you submit match the topic shown for each student's age category. Videos that do not match the assigned topic may be rejected during the review process.
                </p>
            </div>
        </div>

        <div id="payer-info-section" class="wevoi-card">
            <h3>Payer Information</h3>
            <div class="wevoi-inline-row">
                <div class="wevoi-form-row">
                    <label for="payer-name">Full Name:</label>
                    <input type="text" id="payer-name" name="payer_name" required>
                </div>
                <div class="wevoi-form-row">
                    <label for="payer-country">Country:</label>
                    <select id="payer-country" name="payer_countryid" required>
                        <option value="">Select Country</option>
                        <?php
                        $countries = $this->db->get_results(
                            "SELECT countryid, countryname
                            FROM {$this->db->prefix}wevoi_countries
                            ORDER BY countryname"
                        );
                        foreach ($countries as $country):
                        ?>
                            <option value="<?php echo esc_attr($country->countryid); ?>">
                                <?php echo esc_html($country->countryname); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="wevoi-form-row">
                    <label for="card-element">Credit Card:</label>
                    <div id="card-element" class="wevoi-stripe-element"></div>
                    <div id="card-errors" role="alert"></div>
            </div>
        </div>

        <div class="wevoi-amount-coupon-row">
            <div class="wevoi-card">
                <h3>Total Amount you will pay</h3>
                <div class="wevoi-total-amount">
                    £<span id="wevoi-price">0.00</span>
                </div>
                <div class="wevoi-student-count">
                    Selected Students: <span id="student-count">0</span>
                </div>
            </div>

            <div class="wevoi-card">
                <h3>Use Discount Coupon</h3>
                <div class="wevoi-form-row wevoi-coupon-container">
                    <input type="text" id="coupon-code" name="coupon_code"
                           placeholder="Enter coupon code">
                    <button type="button" id="apply-coupon" class="wevoi-button">Apply</button>
                    <button type="button" id="change-coupon" class="wevoi-button"
                            style="display: none;">Change</button>
                </div>
                <div id="coupon-message" class="wevoi-message"></div>
            </div>
        </div>

        <div class="wevoi-card">
            <div class="wevoi-consent-container">
                <label class="wevoi-consent-label">
                    <input type="checkbox" id="consent-checkbox" required>
                    I confirm that the information provided is accurate and complete.
                    I agree to the <a href="https://www.wevoi.org/terms-conditions/"
                    target="_blank">terms &amp; conditions</a>.
                </label>
            </div>
            <button type="submit" id="submit-button"
                    class="wevoi-button wevoi-button-primary">PAY NOW</button>
        </div>
    </form>

    <div id="notification-container"></div>
</div>
