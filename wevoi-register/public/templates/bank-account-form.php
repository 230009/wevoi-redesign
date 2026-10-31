<?php
// Banka hesap bilgileri form sayfası
// Bu sayfa sadece para ödülü kazanan öğrenciler tarafından erişilebilir

// WordPress gerekli dosyalarını dahil et
if (!defined('ABSPATH')) {
    require_once('../../../wp-load.php');
}

// Güvenlik kontrolü
if (!is_user_logged_in()) {
    echo '<div class="wevoi-error">Please log in to access this page.</div>';
    return;
}

$user_id = get_current_user_id();
$registration_id = isset($_GET['registration_id']) ? intval($_GET['registration_id']) : 0;

if (!$registration_id) {
    echo '<div class="wevoi-error">You need to log in to access this page.</div>';
    return;
}

// Kayıt bilgilerini kontrol et
global $wpdb;
$registration = $wpdb->get_row($wpdb->prepare(
    "SELECT r.*, c.competition_name, c.competition_type 
     FROM {$wpdb->prefix}wevoi_registrations r
     JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
     WHERE r.register_id = %d AND r.user_id = %d",
    $registration_id, $user_id
));

if (!$registration) {
    echo '<div class="wevoi-error">Registration not found or access denied.</div>';
    return;
}

// Para ödülü kazanmış mı kontrol et
if (empty($registration->claimed_prize_amount) || $registration->claimed_prize_amount <= 0) {
    echo '<div class="wevoi-error">No cash prize found for this registration.</div>';
    return;
}

// Zaten banka bilgisi girilmiş mi kontrol et
$existing_account = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}wevoi_bank_accounts WHERE registration_id = %d",
    $registration_id
));

// Form gönderildi mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_bank_details'])) {
    if (wp_verify_nonce($_POST['bank_details_nonce'], 'submit_bank_details')) {
        
        // Validate required fields
        $required_fields = array('account_holder_name', 'bank_name', 'account_number', 'countryid', 'currencyid', 'city');
        $missing_fields = array();
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            echo '<div class="wevoi-error">Please fill in all required fields: ' . implode(', ', $missing_fields) . '</div>';
        } else {
                    $account_data = array(
            'user_id' => $user_id,
            'competition_code' => $registration->competition_code,
            'registration_id' => $registration_id,
            'account_holder_name' => sanitize_text_field($_POST['account_holder_name']),
            'bank_name' => sanitize_text_field($_POST['bank_name']),
            'account_number' => sanitize_text_field($_POST['account_number']),
            'sort_code' => sanitize_text_field($_POST['sort_code']),
            'iban' => sanitize_text_field($_POST['iban']),
            'swift_code' => sanitize_text_field($_POST['swift_code']),
            'countryid' => intval($_POST['countryid']),
            'city' => sanitize_text_field(isset($_POST['city']) ? $_POST['city'] : ''),
            'currencyid' => intval($_POST['currencyid']),
            'general_info' => sanitize_textarea_field(isset($_POST['general_info']) ? $_POST['general_info'] : ''),
            'status' => 'PENDING'
        );
        
        if ($existing_account) {
            // Güncelle
            $result = $wpdb->update(
                $wpdb->prefix . 'wevoi_bank_accounts',
                $account_data,
                array('registration_id' => $registration_id),
                array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s'),
                array('%d')
            );
            $message = 'Bank account details updated successfully!';
        } else {
            // Yeni kayıt
            $result = $wpdb->insert(
                $wpdb->prefix . 'wevoi_bank_accounts',
                $account_data,
                array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s')
            );
            $message = 'Bank account details submitted successfully!';
        }
        
            if ($result !== false) {
                echo '<div class="wevoi-success">' . esc_html($message) . '</div>';
                $existing_account = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}wevoi_bank_accounts WHERE registration_id = %d",
                    $registration_id
                ));
            } else {
                echo '<div class="wevoi-error">Error saving bank account details. Please try again.</div>';
                // Log the error for debugging
                error_log('WeVoi Bank Account Error: ' . $wpdb->last_error);
                error_log('WeVoi Bank Account Data: ' . print_r($account_data, true));
            }
        }
    }
}
?>

<div class="wevoi-bank-account-form">
    <div class="form-header">
        <h2>Bank Account Details for Prize Payment</h2>
        <p>Please provide your bank account details to receive your cash prize of <strong>£<?php echo number_format($registration->claimed_prize_amount, 2); ?></strong></p>
        <div class="competition-info">
            <strong>Competition:</strong> <?php echo esc_html($registration->competition_name); ?><br>
            <strong>Prize Amount:</strong> £<?php echo number_format($registration->claimed_prize_amount, 2); ?>
        </div>
    </div>

    <?php if ($existing_account): ?>
        <div class="status-info">
            <h3>Current Status: 
                <span class="status-<?php echo strtolower($existing_account->status); ?>">
                    <?php echo esc_html($existing_account->status); ?>
                </span>
            </h3>
            <?php if ($existing_account->admin_notes): ?>
                <div class="admin-notes">
                    <strong>Admin Notes:</strong> <?php echo esc_html($existing_account->admin_notes); ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="post" class="bank-details-form">
        <?php wp_nonce_field('submit_bank_details', 'bank_details_nonce'); ?>
        
        <div class="form-row">
            <div class="form-group">
                <label for="account_holder_name">Account Holder Name *</label>
                <input type="text" id="account_holder_name" name="account_holder_name" 
                       value="<?php echo esc_attr($existing_account ? $existing_account->account_holder_name : ''); ?>" 
                       required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="bank_name">Bank Name *</label>
                <input type="text" id="bank_name" name="bank_name" 
                       value="<?php echo esc_attr($existing_account ? $existing_account->bank_name : ''); ?>" 
                       required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="account_number">Account Number *</label>
                <input type="text" id="account_number" name="account_number" 
                       value="<?php echo esc_attr($existing_account ? $existing_account->account_number : ''); ?>" 
                       required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="sort_code">Sort Code (UK Banks)</label>
                <input type="text" id="sort_code" name="sort_code" 
                       value="<?php echo esc_attr($existing_account ? $existing_account->sort_code : ''); ?>" 
                       placeholder="12-34-56">
            </div>
            <div class="form-group">
                <label for="iban">IBAN (International)</label>
                <input type="text" id="iban" name="iban" 
                       value="<?php echo esc_attr($existing_account ? $existing_account->iban : ''); ?>" 
                       placeholder="GB29 NWBK 6016 1331 9268 19">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="swift_code">SWIFT/BIC Code</label>
                <input type="text" id="swift_code" name="swift_code" 
                       value="<?php echo esc_attr($existing_account ? $existing_account->swift_code : ''); ?>" 
                       placeholder="NWBKGB2L">
            </div>
            <div class="form-group">
                <label for="countryid">Country *</label>
                <select id="countryid" name="countryid" required>
                    <option value="">Select Country</option>
                    <?php
                    // Get countries from database
                    $countries = $wpdb->get_results("SELECT countryid, countryname FROM {$wpdb->prefix}wevoi_countries ORDER BY countryname");
                    foreach ($countries as $country) {
                        $selected = ($existing_account && $existing_account->countryid == $country->countryid) ? 'selected' : '';
                        echo '<option value="' . $country->countryid . '" ' . $selected . '>' . esc_html($country->countryname) . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="city">City *</label>
                <input type="text" id="city" name="city"
                       value="<?php echo esc_attr($existing_account && isset($existing_account->city) ? $existing_account->city : ''); ?>"
                       required>
            </div>
            <div class="form-group">
                <label for="currencyid">Currency *</label>
                <select id="currencyid" name="currencyid" required>
                    <?php
                    // Get currencies from database
                    $currencies = $wpdb->get_results("SELECT currencyid, currency_code, currency_name, symbol FROM {$wpdb->prefix}wevoi_currencies WHERE is_active = 1 ORDER BY currency_code");
                    foreach ($currencies as $currency) {
                        $selected = ($existing_account && $existing_account->currencyid == $currency->currencyid) ? 'selected' : ((!$existing_account && $currency->currency_code == 'GBP') ? 'selected' : '');
                        echo '<option value="' . $currency->currencyid . '" ' . $selected . '>' . 
                             esc_html($currency->currency_code . ' (' . $currency->symbol . ') - ' . $currency->currency_name) . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="general_info">General Information</label>
                <textarea id="general_info" name="general_info" rows="3" placeholder="Any additional notes for the payment (optional)"><?php echo esc_textarea($existing_account && !empty($existing_account->general_info) ? $existing_account->general_info : ''); ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" name="submit_bank_details" class="submit-btn">
                <?php echo $existing_account ? 'Update Bank Details' : 'Submit Bank Details'; ?>
            </button>
            <a href="<?php echo home_url('/submissions/'); ?>" class="cancel-btn">Back to Submissions</a>
        </div>
    </form>
</div>

<style>
.wevoi-bank-account-form {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.form-header {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #f0f0f0;
}

.form-header h2 {
    color: #333;
    margin-bottom: 10px;
}

.competition-info {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    margin-top: 15px;
}

.status-info {
    background: #e3f2fd;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.status-pending { color: #ff9800; font-weight: bold; }
.status-verified { color: #4caf50; font-weight: bold; }
.status-rejected { color: #f44336; font-weight: bold; }

.admin-notes {
    background: #fff3cd;
    padding: 10px;
    border-radius: 3px;
    margin-top: 10px;
    border-left: 4px solid #ffc107;
}

.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    flex: 1;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.form-group textarea {
    min-height: 80px;
    resize: vertical;
    font-family: inherit;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 2px rgba(0,124,186,0.2);
}

.form-actions {
    text-align: center;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #f0f0f0;
}

.submit-btn {
    background: #007cba;
    color: white;
    padding: 12px 30px;
    border: none;
    border-radius: 4px;
    font-size: 16px;
    cursor: pointer;
    margin-right: 15px;
}

.submit-btn:hover {
    background: #005a87;
}

.cancel-btn {
    background: #6c757d;
    color: white;
    padding: 12px 30px;
    text-decoration: none;
    border-radius: 4px;
    font-size: 16px;
    display: inline-block;
}

.cancel-btn:hover {
    background: #545b62;
    color: white;
}

.wevoi-success {
    background: #d4edda;
    color: #155724;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
    border: 1px solid #c3e6cb;
}

.wevoi-error {
    background: #f8d7da;
    color: #721c24;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
    border: 1px solid #f5c6cb;
}

@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 15px;
    }
    
    .wevoi-bank-account-form {
        padding: 15px;
    }
}
</style> 