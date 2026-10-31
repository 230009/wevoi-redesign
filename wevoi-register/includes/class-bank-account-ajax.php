<?php
/**
 * Bank Account AJAX Handler
 *
 * @package WeVoi_Register
 */

class WeVOi_Bank_Account_Ajax {

    public function __construct() {
        add_action('wp_ajax_get_bank_account_details', array($this, 'get_bank_account_details'));
    }

    /**
     * Get bank account details for admin modal (by account_id).
     * Requests with registration_id are handled by wevoi-competition-manager.
     */
    public function get_bank_account_details() {
        // Registrations list uses registration_id — let competition-manager handle that.
        if (!empty($_POST['registration_id']) && empty($_POST['account_id'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'get_bank_account_details')) {
            wp_send_json_error('Invalid nonce');
        }

        $account_id = isset($_POST['account_id']) ? intval($_POST['account_id']) : 0;
        if (!$account_id) {
            wp_send_json_error('Invalid account ID');
        }

        global $wpdb;

        $account = $wpdb->get_row($wpdb->prepare("
            SELECT ba.*,
                   u.display_name as student_name,
                   u.user_email as student_email,
                   c.competition_name,
                   r.claimed_prize_amount,
                   co.countryname,
                   cu.currency_code,
                   cu.currency_name,
                   cu.symbol
            FROM {$wpdb->prefix}wevoi_bank_accounts ba
            JOIN {$wpdb->prefix}users u ON ba.user_id = u.ID
            JOIN {$wpdb->prefix}wevoi_competitions c ON ba.competition_code = c.competition_code
            JOIN {$wpdb->prefix}wevoi_registrations r ON ba.registration_id = r.register_id
            LEFT JOIN {$wpdb->prefix}wevoi_countries co ON ba.countryid = co.countryid
            LEFT JOIN {$wpdb->prefix}wevoi_currencies cu ON ba.currencyid = cu.currencyid
            WHERE ba.id = %d
        ", $account_id));

        if ($account) {
            wp_send_json_success($account);
        }

        wp_send_json_error('Account not found');
    }
}

// Initialize the class
new WeVOi_Bank_Account_Ajax();
