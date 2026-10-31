<?php
/**
 * Voucher list display handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class WeVoi_Voucher_List_Handler {
    private static $instance = null;
    private $db;
    
    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor for singleton
     */
    private function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        
        // Register shortcode
        add_shortcode('wevoi_my_vouchers', array($this, 'render_vouchers_list'));
    }
    
    /**
     * Render vouchers list shortcode
     */
    public function render_vouchers_list() {
        if (!is_user_logged_in()) {
            return '<div style="text-align: center; margin-top: 20px;">
                    <p style="font-weight: bold;">Please <a href="https://www.wevoi.org/wp-login.php" 
                    style="text-decoration: none; color: blue;">log in</a> to view your vouchers.</p>
                    </div>';
        }
        
        // Get current user
        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;
        
        // Check if user is admin
        $is_admin = current_user_can('administrator');
        
        // Get vouchers based on user role
        if ($is_admin) {
            $vouchers = $this->get_all_vouchers();
        } else {
            $vouchers = $this->get_user_vouchers($user_email);
        }
        
        if (empty($vouchers)) {
            if ($is_admin) {
                return '<div class="wevoi-no-vouchers">
                        <p>There are no vouchers in the system yet.</p>
                        </div>';
            } else {
                return '<div class="wevoi-no-vouchers">
                        <p>You don\'t have any vouchers yet. <a href="https://www.wevoi.org/vouchers/">Purchase vouchers</a> to support our competitions.</p>
                        </div>';
            }
        }
        
        // Enqueue styles
        $this->enqueue_assets();
        
        // Render the template
        ob_start();
        include WEVOI_REGISTER_PLUGIN_DIR . 'modules/voucher/templates/my-vouchers.php';
        return ob_get_clean();
    }
    
    /**
     * Get vouchers for a specific user email
     */
private function get_user_vouchers($user_email) {
    return $this->db->get_results($this->db->prepare(
        "SELECT v.*, c.competition_name, co.countryname, 
                COALESCE(coup.coupon_limit, 0) as total_limit,
                COALESCE(coup.coupon_used, 0) as used_count,
                COALESCE(coup.coupon_limit - coup.coupon_used, v.quantity) as remaining_uses
         FROM {$this->db->prefix}wevoi_vouchers v
         JOIN {$this->db->prefix}wevoi_competitions c ON v.competition_code = c.competition_code
         LEFT JOIN {$this->db->prefix}wevoi_countries co ON v.payer_countryid = co.countryid
         LEFT JOIN {$this->db->prefix}wevoi_coupons coup ON coup.coupon_code = v.voucher_code
         WHERE v.payer_email = %s AND v.paid_status = 'PAID'
         ORDER BY v.payment_date DESC",
        $user_email
    ));
}
    
    /**
     * Get all vouchers for admin users
     */
private function get_all_vouchers() {
    return $this->db->get_results(
        "SELECT v.*, c.competition_name, co.countryname,
                COALESCE(coup.coupon_limit, 0) as total_limit,
                COALESCE(coup.coupon_used, 0) as used_count,
                COALESCE(coup.coupon_limit - coup.coupon_used, v.quantity) as remaining_uses
         FROM {$this->db->prefix}wevoi_vouchers v
         JOIN {$this->db->prefix}wevoi_competitions c ON v.competition_code = c.competition_code
         LEFT JOIN {$this->db->prefix}wevoi_countries co ON v.payer_countryid = co.countryid
         LEFT JOIN {$this->db->prefix}wevoi_coupons coup ON coup.coupon_code = v.voucher_code
         WHERE v.paid_status = 'PAID'
         ORDER BY v.payment_date DESC"
    );
}
    
    /**
     * Enqueue required assets
     */
    private function enqueue_assets() {
        wp_enqueue_style(
            'wevoi-my-vouchers-style',
            WEVOI_REGISTER_PLUGIN_URL . 'modules/voucher/assets/css/my-vouchers.css',
            array(),
            WEVOI_REGISTER_VERSION
        );
    }
}