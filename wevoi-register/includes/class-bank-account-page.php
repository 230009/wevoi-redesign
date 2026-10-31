<?php
/**
 * Bank Account Details Page Handler
 * 
 * @package WeVoi_Register
 */

class WeVoi_Bank_Account_Page {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Bank account details page template
        add_filter('template_include', array($this, 'load_bank_account_template'));
        
        // Add rewrite rule for bank account details page
        add_action('init', array($this, 'add_rewrite_rules'));
        
        // Add shortcode for bank account form
        add_shortcode('wevoi_bank_account_form', array($this, 'bank_account_shortcode'));
        
        // Flush rewrite rules on plugin activation
        register_activation_hook(__FILE__, array($this, 'flush_rewrite_rules'));
    }
    
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^bank-account-details/?$',
            'index.php?pagename=bank-account-details',
            'top'
        );
    }
    
    public function flush_rewrite_rules() {
        $this->add_rewrite_rules();
        flush_rewrite_rules();
    }
    
    public function load_bank_account_template($template) {
        if (is_page('bank-account-details') || (isset($_GET['registration_id']) && strpos($_SERVER['REQUEST_URI'], 'bank-account-details') !== false)) {
            // Load our custom template
            $custom_template = plugin_dir_path(__FILE__) . '../public/templates/bank-account-form.php';
            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }
        return $template;
    }
    
    /**
     * Check if user has access to bank account details page
     */
    public static function user_has_access($user_id, $registration_id) {
        global $wpdb;
        
        // Check if user owns this registration and has cash prize
        $registration = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, c.competition_name 
             FROM {$wpdb->prefix}wevoi_registrations r
             JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
             WHERE r.register_id = %d AND r.user_id = %d",
            $registration_id, $user_id
        ));
        
        if (!$registration) {
            return false;
        }
        
        // Check if user has cash prize
        if (empty($registration->claimed_prize_amount) || $registration->claimed_prize_amount <= 0) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get bank account details for a registration
     */
    public static function get_bank_account_details($registration_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wevoi_bank_accounts 
             WHERE registration_id = %d",
            $registration_id
        ));
    }
    
    /**
     * Shortcode for bank account form
     */
    public function bank_account_shortcode($atts) {
        try {
            // Check if user is logged in
            if (!is_user_logged_in()) {
                return '<div class="wevoi-error">Please log in to access this page.</div>';
            }
            
            // Get registration_id from URL parameter
            $registration_id = isset($_GET['registration_id']) ? intval($_GET['registration_id']) : 0;
            
            if (!$registration_id) {
                return '<div class="wevoi-error">You need to log in to access this page.</div>';
            }
            
            // Include the form template
            ob_start();
            $template_path = plugin_dir_path(__FILE__) . '../public/templates/bank-account-form.php';
            
            if (file_exists($template_path)) {
                include $template_path;
            } else {
                return '<div class="wevoi-error">Template file not found.</div>';
            }
            
            return ob_get_clean();
            
        } catch (Exception $e) {
            return '<div class="wevoi-error">Error loading form: ' . esc_html($e->getMessage()) . '</div>';
        }
    }
    
    /**
     * Save bank account details
     */
    public static function save_bank_account_details($data) {
        global $wpdb;
        
        $account_data = array(
            'user_id' => intval($data['user_id']),
            'competition_code' => intval($data['competition_code']),
            'registration_id' => intval($data['registration_id']),
            'account_holder_name' => sanitize_text_field($data['account_holder_name']),
            'bank_name' => sanitize_text_field($data['bank_name']),
            'account_number' => sanitize_text_field($data['account_number']),
            'sort_code' => sanitize_text_field($data['sort_code']),
            'iban' => sanitize_text_field($data['iban']),
            'swift_code' => sanitize_text_field($data['swift_code']),
            'countryid' => intval($data['countryid']),
            'city' => sanitize_text_field(isset($data['city']) ? $data['city'] : ''),
            'currencyid' => intval($data['currencyid']),
            'general_info' => sanitize_textarea_field(isset($data['general_info']) ? $data['general_info'] : ''),
            'status' => 'PENDING'
        );
        
        // Check if record exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}wevoi_bank_accounts 
             WHERE registration_id = %d",
            $data['registration_id']
        ));
        
        if ($existing) {
            // Update existing record
            $result = $wpdb->update(
                $wpdb->prefix . 'wevoi_bank_accounts',
                $account_data,
                array('registration_id' => $data['registration_id']),
                array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s'),
                array('%d')
            );
        } else {
            // Insert new record
            $result = $wpdb->insert(
                $wpdb->prefix . 'wevoi_bank_accounts',
                $account_data,
                array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s')
            );
        }
        
        return $result !== false;
    }
}

// Initialize the class
new WeVoi_Bank_Account_Page(); 