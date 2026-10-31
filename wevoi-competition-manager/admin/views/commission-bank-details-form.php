<?php
/**
 * Commission bank details form – for supervisor/agency profile pages.
 * Expects: $user_id (current user). Saves to usermeta wevoi_commission_bank_details (JSON).
 */
if (!defined('ABSPATH')) {
    return;
}
$user_id = isset($user_id) ? (int) $user_id : get_current_user_id();
$json = get_user_meta($user_id, 'wevoi_commission_bank_details', true);
$bank = is_string($json) ? json_decode($json, true) : array();
if (!is_array($bank)) {
    $bank = array();
}
$account_holder = isset($bank['account_holder_name']) ? $bank['account_holder_name'] : '';
$bank_name = isset($bank['bank_name']) ? $bank['bank_name'] : '';
$iban = isset($bank['iban']) ? $bank['iban'] : '';
$swift_bic = isset($bank['swift_bic']) ? $bank['swift_bic'] : '';
$sort_code = isset($bank['sort_code']) ? $bank['sort_code'] : '';
$branch = isset($bank['branch']) ? $bank['branch'] : '';
$currency = isset($bank['currency']) ? $bank['currency'] : '';
$text_domain = 'wevoi-competition-manager';
?>

<section class="wevoi-bank-details-section">
    <div class="wevoi-bank-details-card">
        <div class="wevoi-bank-details-header">
            <span class="wevoi-bank-details-icon" aria-hidden="true">&#128179;</span>
            <h2 class="wevoi-bank-details-title"><?php _e('Bank details for commission payments', $text_domain); ?></h2>
        </div>

        <form id="wevoi-commission-bank-form" class="wevoi-bank-details-form">
            <?php wp_nonce_field('wevoi_commission_bank_details', 'wevoi_commission_bank_nonce'); ?>

            <div class="wevoi-bank-details-fields-grid">
            <div class="wevoi-bank-field-group wevoi-bank-field-full">
                <label for="wevoi_bank_account_holder" class="wevoi-bank-label"><?php _e('Account holder name', $text_domain); ?></label>
                <input type="text" name="account_holder_name" id="wevoi_bank_account_holder" value="<?php echo esc_attr($account_holder); ?>" class="wevoi-bank-input" placeholder="<?php esc_attr_e('Full name as on account', $text_domain); ?>" aria-describedby="wevoi_bank_account_holder_hint" autocomplete="name">
                <p id="wevoi_bank_account_holder_hint" class="wevoi-bank-field-hint"><?php _e('Please use only standard English letters (A-Z). Spaces between first and last name are allowed.', $text_domain); ?></p>
            </div>

            <div class="wevoi-bank-field-group wevoi-bank-group-title">
                <span class="wevoi-bank-group-label"><?php _e('Bank information', $text_domain); ?></span>
            </div>
            <div class="wevoi-bank-field-group">
                <label for="wevoi_bank_name" class="wevoi-bank-label"><?php _e('Bank name', $text_domain); ?></label>
                <input type="text" name="bank_name" id="wevoi_bank_name" value="<?php echo esc_attr($bank_name); ?>" class="wevoi-bank-input" placeholder="<?php esc_attr_e('Name of your bank', $text_domain); ?>">
            </div>
            <div class="wevoi-bank-field-group">
                <label for="wevoi_bank_branch" class="wevoi-bank-label"><?php _e('Branch', $text_domain); ?></label>
                <input type="text" name="branch" id="wevoi_bank_branch" value="<?php echo esc_attr($branch); ?>" class="wevoi-bank-input" placeholder="<?php esc_attr_e('Branch name or code', $text_domain); ?>">
            </div>

            <div class="wevoi-bank-field-group wevoi-bank-group-title">
                <span class="wevoi-bank-group-label"><?php _e('Account identifiers', $text_domain); ?></span>
            </div>
            <div class="wevoi-bank-field-group">
                <label for="wevoi_bank_iban" class="wevoi-bank-label"><?php _e('IBAN', $text_domain); ?></label>
                <input type="text" name="iban" id="wevoi_bank_iban" value="<?php echo esc_attr($iban); ?>" class="wevoi-bank-input wevoi-bank-input-mono" placeholder="e.g. GB82 WEST 1234 5698 7654 32">
            </div>
            <div class="wevoi-bank-field-group">
                <label for="wevoi_bank_swift" class="wevoi-bank-label"><?php _e('SWIFT / BIC', $text_domain); ?></label>
                <input type="text" name="swift_bic" id="wevoi_bank_swift" value="<?php echo esc_attr($swift_bic); ?>" class="wevoi-bank-input wevoi-bank-input-mono" placeholder="e.g. HBUKGB4B">
            </div>
            <div class="wevoi-bank-field-group">
                <label for="wevoi_bank_sort_code" class="wevoi-bank-label"><?php _e('Sort code', $text_domain); ?></label>
                <input type="text" name="sort_code" id="wevoi_bank_sort_code" value="<?php echo esc_attr($sort_code); ?>" class="wevoi-bank-input wevoi-bank-input-mono" placeholder="e.g. 12-34-56">
            </div>
            <div class="wevoi-bank-field-group">
                <label for="wevoi_bank_currency" class="wevoi-bank-label"><?php _e('Currency', $text_domain); ?></label>
                <input type="text" name="currency" id="wevoi_bank_currency" value="<?php echo esc_attr($currency); ?>" class="wevoi-bank-input wevoi-bank-input-small" placeholder="e.g. GBP" maxlength="6">
            </div>
            </div>

            <div class="wevoi-bank-details-actions">
                <button type="submit" class="wevoi-bank-save-btn">
                    <span class="wevoi-bank-save-text"><?php _e('Save bank details', $text_domain); ?></span>
                </button>
                <span id="wevoi-commission-bank-message" class="wevoi-bank-message"></span>
            </div>
        </form>
    </div>
</section>

<style>
.wevoi-bank-details-section { margin: 28px 0; }
.wevoi-bank-details-card {
    max-width: 520px;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    overflow: hidden;
}
.wevoi-bank-details-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px 24px 0;
    margin-bottom: 8px;
}
.wevoi-bank-details-icon {
    font-size: 28px;
    line-height: 1;
    opacity: 0.9;
}
.wevoi-bank-details-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: #1e293b;
}
.wevoi-bank-details-form {
    padding: 0 24px 24px;
}
.wevoi-bank-details-fields-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 24px;
}
.wevoi-bank-details-fields-grid .wevoi-bank-field-group.wevoi-bank-field-full,
.wevoi-bank-details-fields-grid .wevoi-bank-field-group.wevoi-bank-group-title {
    grid-column: 1 / -1;
}
.wevoi-bank-field-group {
    margin-bottom: 18px;
}
.wevoi-bank-field-group.wevoi-bank-group-title {
    margin-top: 22px;
    margin-bottom: 10px;
    padding-top: 18px;
    border-top: 1px solid #f1f5f9;
}
.wevoi-bank-field-group.wevoi-bank-group-title:first-of-type { border-top: none; padding-top: 0; margin-top: 0; }
.wevoi-bank-group-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #94a3b8;
}
.wevoi-bank-label {
    display: block;
    margin-bottom: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    color: #334155;
}
.wevoi-bank-input {
    width: 100%;
    max-width: 100%;
    padding: 10px 14px;
    font-size: 0.9375rem;
    line-height: 1.4;
    color: #1e293b;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    box-sizing: border-box;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.wevoi-bank-input:hover { border-color: #94a3b8; }
.wevoi-bank-input:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}
.wevoi-bank-input::placeholder { color: #94a3b8; }
.wevoi-bank-field-hint {
    margin: 8px 0 0;
    padding: 10px 12px;
    font-size: 0.8125rem;
    line-height: 1.45;
    color: #92400e;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 8px;
}
.wevoi-bank-input-mono { font-family: ui-monospace, 'Cascadia Code', 'SF Mono', Monaco, monospace; }
.wevoi-bank-input-small { max-width: 100px; }
.wevoi-bank-details-actions {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
.wevoi-bank-save-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 20px;
    font-size: 0.9375rem;
    font-weight: 600;
    color: #fff;
    background: #2563eb;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.2s ease, transform 0.05s ease;
}
.wevoi-bank-save-btn:hover { background: #1d4ed8; }
.wevoi-bank-save-btn:active { transform: scale(0.98); }
.wevoi-bank-save-btn:disabled { opacity: 0.7; cursor: not-allowed; }
.wevoi-bank-message {
    font-size: 0.875rem;
    font-weight: 500;
}
.wevoi-bank-message.success { color: #059669; }
.wevoi-bank-message.error { color: #dc2626; }

/* Button to open bank details modal */
.wevoi-bank-details-trigger-title {
    margin: 0 0 10px 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: #1e293b;
}
.wevoi-bank-details-trigger-desc {
    margin: 0 0 14px 0;
    font-size: 0.9375rem;
    line-height: 1.5;
    color: #64748b;
}
.wevoi-bank-details-open-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    font-size: 0.9375rem;
    font-weight: 600;
    color: #fff;
    background: #2563eb;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: background 0.2s ease;
}
.wevoi-bank-details-open-btn:hover { background: #1d4ed8; }

/* Modal */
.wevoi-bank-details-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 100000;
    align-items: center;
    justify-content: center;
    padding: 20px;
    box-sizing: border-box;
}
.wevoi-bank-details-modal[aria-hidden="false"] { display: flex; }
.wevoi-bank-details-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.5);
    cursor: pointer;
}
.wevoi-bank-details-modal-content {
    position: relative;
    max-width: 720px;
    width: 100%;
    max-height: calc(100vh - 40px);
    overflow: auto;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
}
.wevoi-bank-details-modal-close {
    position: absolute;
    top: 12px;
    right: 12px;
    z-index: 2;
    width: 36px;
    height: 36px;
    padding: 0;
    font-size: 24px;
    line-height: 1;
    color: #64748b;
    background: transparent;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: color 0.2s, background 0.2s;
}
.wevoi-bank-details-modal-close:hover {
    color: #1e293b;
    background: #f1f5f9;
}
.wevoi-bank-details-modal .wevoi-bank-details-section {
    margin: 0;
    border: none;
    box-shadow: none;
}
.wevoi-bank-details-modal .wevoi-bank-details-card {
    max-width: none;
    box-shadow: none;
}
</style>

<script>
jQuery(function($) {
    $('#wevoi-commission-bank-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $msg = $('#wevoi-commission-bank-message');
        $btn.prop('disabled', true);
        $msg.removeClass('success error').text('').hide();

        $.post(typeof ajaxurl !== 'undefined' ? ajaxurl : '<?php echo esc_js(admin_url('admin-ajax.php')); ?>', {
            action: 'wevoi_save_commission_bank_details',
            nonce: $form.find('input[name="wevoi_commission_bank_nonce"]').val(),
            account_holder_name: $form.find('#wevoi_bank_account_holder').val(),
            bank_name: $form.find('#wevoi_bank_name').val(),
            iban: $form.find('#wevoi_bank_iban').val(),
            swift_bic: $form.find('#wevoi_bank_swift').val(),
            sort_code: $form.find('#wevoi_bank_sort_code').val(),
            branch: $form.find('#wevoi_bank_branch').val(),
            currency: $form.find('#wevoi_bank_currency').val()
        }).done(function(res) {
            if (res.success) {
                $msg.addClass('success').text(res.data && res.data.message ? res.data.message : '<?php echo esc_js(__('Saved.', 'wevoi-competition-manager')); ?>').show();
                jQuery(document).trigger('wevoiBankDetailsSaved');
            } else {
                $msg.addClass('error').text(res.data && res.data.message ? res.data.message : '<?php echo esc_js(__('Error.', 'wevoi-competition-manager')); ?>').show();
            }
        }).fail(function() {
            $msg.addClass('error').text('<?php echo esc_js(__('Request failed.', 'wevoi-competition-manager')); ?>').show();
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });
});
</script>
