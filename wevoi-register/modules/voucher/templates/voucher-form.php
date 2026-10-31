<?php
/**
 * Voucher Form Template - Enhanced Version
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="wevoi-voucher-form-container" class="wevoi-form-container">
    <!-- Notification Area -->
    <div id="wevoi-notification" class="wevoi-notification" style="display: none;"></div>

    <form id="wevoi-voucher-form">
        <!-- Main Card -->
        <div class="wevoi-card primary-card">
<h3>Purchasing Vouchers or Paying an Amount</h3>
            <!-- Options Container -->
            <div class="wevoi-options-container">
                <!-- Purchase Voucher Option -->
                <div class="wevoi-option-tab">
                    <input type="radio" id="voucher-type-voucher" name="voucher_type" value="VOUCHER" checked>
                    <label for="voucher-type-voucher" class="wevoi-tab-label">🏷️️️Purchase Voucher</label>

                    <div class="wevoi-tab-content">
                        <div class="wevoi-flex-row">
                            <div class="wevoi-form-group flex-grow-2">
                                <label for="voucher-competition">Competition</label>
                                <select id="voucher-competition" name="competition_code" required>
                                    <option value="">Select Competition</option>
                                    <?php foreach ($competitions as $competition): ?>
                                        <option value="<?php echo esc_attr($competition->competition_code); ?>"
                                                data-price="<?php echo esc_attr($competition->competition_price); ?>">
                                            <?php echo esc_html($competition->competition_name); ?> -
                                            <?php echo esc_html(date('d M Y', strtotime($competition->competition_date))); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="wevoi-form-group">
                                <label for="voucher-quantity">Quantity</label>
                                <select id="voucher-quantity" name="quantity">
                                    <option value="1">1 Voucher</option>
                                    <option value="2">2 Vouchers</option>
                                    <option value="3">3 Vouchers</option>
                                    <option value="4">4 Vouchers</option>
                                    <option value="5">5 Vouchers</option>
                                    <option value="6">6 Vouchers</option>
                                    <option value="7">7 Vouchers</option>
                                    <option value="8">8 Vouchers</option>
                                    <option value="9">9 Vouchers</option>
                                    <option value="10">10 Vouchers</option>
                                    <option value="15">15 Vouchers</option>
                                    <option value="20">20 Vouchers</option>
                                    <option value="25">25 Vouchers</option>
                                    <option value="30">30 Vouchers</option>
                                    <option value="35">35 Vouchers</option>
                                    <option value="40">40 Vouchers</option>
                                    <option value="45">45 Vouchers</option>
                                    <option value="50">50 Vouchers</option>

                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pay an Amount Option -->
<div class="wevoi-option-tab">
    <input type="radio" id="voucher-type-amount" name="voucher_type" value="AMOUNT">
    <label for="voucher-type-amount" class="wevoi-tab-label">💵Pay an Amount</label>

    <div class="wevoi-tab-content">
        <div class="wevoi-flex-row">
            <div class="wevoi-form-group flex-grow-2">
                <label for="amount-competition">Competition</label>
                <select id="amount-competition" name="amount_competition_code" disabled>
                    <option value="">Select Competition</option>
                    <?php foreach ($competitions as $competition): ?>
                        <option value="<?php echo esc_attr($competition->competition_code); ?>">
                            <?php echo esc_html($competition->competition_name); ?> -
                            <?php echo esc_html(date('d M Y', strtotime($competition->competition_date))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="wevoi-form-group">
                <label for="voucher-amount">Amount</label>
                <div class="wevoi-amount-input">
                    <span class="currency-symbol">£</span>
                    <input type="number" id="voucher-amount" name="amount" min="1" step="0.01" placeholder="Amount" disabled>
                </div>
            </div>
        </div>
        <div class="wevoi-form-group">
            <label for="payment-purpose">Payment Purpose</label>
            <input type="text" id="payment-purpose" name="payment_purpose" placeholder="Why are you making this payment?" disabled>
        </div>
    </div>
</div>
            </div>

            <!-- Total Amount Display -->
            <div class="wevoi-total-section">
                <div class="wevoi-total-label">Your Total:</div>
                <div class="wevoi-total-amount">£<span id="wevoi-price">0.00</span></div>
            </div>
        </div>

        <!-- Contact Information Card -->
        <div class="wevoi-card">
            <h3>Payer Information</h3>

            <div class="wevoi-form-group">
                <label for="payer-email">Email Address</label>
                <input type="email" id="payer-email" name="payer_email" placeholder="your@email.com" required>
            </div>

            <div class="wevoi-flex-row">
                <div class="wevoi-form-group">
                    <label for="payer-name">Full Name</label>
                    <input type="text" id="payer-name" name="payer_name" placeholder="Your Name" required>
                </div>
                <div class="wevoi-form-group">
                    <label for="payer-country">Country</label>
                    <select id="payer-country" name="payer_countryid" required>
                        <option value="">Select Country</option>
                        <?php
                        if (!empty($countries)) {
                            foreach ($countries as $country):
                        ?>
                            <option value="<?php echo esc_attr($country->countryid); ?>">
                                <?php echo esc_html($country->countryname); ?>
                            </option>
                        <?php
                            endforeach;
                        }
                        ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Payment Details Card -->
        <div class="wevoi-card">
            <h3>Payment Details</h3>

            <div class="wevoi-form-group">
                <label for="card-element">Credit or Debit Card</label>
                <div id="card-element" class="wevoi-stripe-element"></div>
                <div id="card-errors" role="alert"></div>
            </div>

            <div class="wevoi-consent-container">
                <label class="wevoi-consent-label">
                    <input type="checkbox" id="consent-checkbox" required>
                    I confirm that the information provided is accurate and complete. I agree to the
                    <a href="https://www.wevoi.org/terms-conditions/" target="_blank">terms & conditions</a>.
                </label>
            </div>

            <button type="submit" id="submit-button" class="wevoi-button wevoi-button-primary">COMPLETE PAYMENT</button>
        </div>
    </form>
</div>
