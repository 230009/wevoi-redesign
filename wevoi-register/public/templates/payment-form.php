<?php
/**
 * Payment form template for WeVoi Register: payment-form.php
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="wevoi-register-form" class="wevoi-form-container wevoi-register-ui">
    <!-- Notification Area -->
    <div id="wevoi-notification" class="wevoi-notification" style="display: none;"></div>

    <form id="competition-registration-form">
        <!-- Competition Selection -->
        <div class="wevoi-card">
            <h3>Available Competitions</h3>
            <div class="wevoi-competition-list">
                <?php foreach ($competitions as $competition): ?>
                    <div class="wevoi-competition-item">
                        <label>
                            <input type="radio" name="competition" value="<?php echo esc_attr($competition->competition_code); ?>"
                                   data-price="<?php echo esc_attr($competition->competition_price); ?>"
                                   data-name="<?php echo esc_attr($competition->competition_name); ?>">
                            <?php echo esc_html($competition->competition_name); ?> -
                            <?php echo esc_html(date('d/m/Y', strtotime($competition->competition_date))); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Topics Section -->
        <div id="topics-section" class="wevoi-card" style="display: none;">
            <h3>Competition Topics</h3>
            <div id="topics-content"></div>
        </div>

        <!-- Competition Details -->
        <div class="wevoi-card">
            <?php if ($age_category_name): ?>
            <div class="wevoi-age-category-row">
                <span class="wevoi-age-category-label">Your Age Category:</span>
                <span class="wevoi-age-category-value"><?php echo esc_html($age_category_name); ?></span>
            </div>
            <?php endif; ?>

            <div class="wevoi-upload-guide">
                <h4 class="wevoi-upload-guide-title">Uploading your video to YouTube</h4>

                <div class="wevoi-privacy-notice">
                    Please choose <em>UNLISTED</em> in the <em>PRIVACY</em> settings when uploading to your YouTube account.
                </div>

                <div class="wevoi-upload-columns">
                    <div id="wevoi-video-title-box"
                         data-student-name="<?php echo esc_attr($student_name); ?>"
                         data-student-country="<?php echo esc_attr($student_country); ?>">
                        <h5>Your Video Title</h5>
                        <div class="wevoi-video-title-value">
                            <p id="wevoi-video-title-text">Select a competition to see your video title.</p>
                        </div>
                    </div>

                    <div class="wevoi-important-points">
                        <h5>Important Points</h5>
                        <ul>
                            <li>The description should contain <em>"<span class="wevoi-instruction-competition-name">the selected competition</span>"</em></li>
                            <li>Tags should contain <em>"<span class="wevoi-instruction-competition-name">the selected competition</span>"</em></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="wevoi-form-row">
                <label for="youtube-link">YouTube Link:</label>
                <input type="url" id="youtube-link" name="youtube_link" placeholder="https://www.youtube.com/" required>
            </div>

            <div class="wevoi-form-row">
                <label for="supervisor_agency_email">Supervisor/Agency (Optional)</label>
                <input type="email" id="supervisor_agency_email" name="supervisor_agency_email" placeholder="Enter email if your Supervisor/Agency has a WeVoi account" autocomplete="off">
                <div id="supervisor-agency-name" class="wevoi-supervisor-name-display"></div>
                <input type="hidden" id="supervisor_id" name="supervisor_id" value="">
            </div>
        </div>

        <!-- Payer Information -->
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
                        $countries = $this->db->get_results("SELECT countryid, countryname FROM {$this->db->prefix}wevoi_countries ORDER BY countryname");
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

        <div class="wevoi-card">
            <div class="wevoi-consent-container">
                <label class="wevoi-consent-label">
                    <input type="checkbox" id="consent-checkbox" required>
                    I confirm that the information provided is accurate and complete. I agree to the <a href="https://www.wevoi.org/terms-conditions/" target="_blank">terms &amp; conditions.</a>
                </label>
            </div>
            <button type="submit" id="submit-button" class="wevoi-button wevoi-button-primary">PAY NOW</button>
        </div>
    </form>
</div>
