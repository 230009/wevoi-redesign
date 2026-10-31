<?php
/**
 * Simple Test Shortcode for Bank Account Form
 */

class WeVoi_Bank_Account_Test {
    
    public function __construct() {
        add_shortcode('wevoi_bank_account_test', array($this, 'test_shortcode'));
    }
    
    public function test_shortcode($atts) {
        return '<div style="background: #f0f0f0; padding: 20px; border: 1px solid #ccc;">
            <h3>Bank Account Form Test</h3>
            <p>Shortcode is working!</p>
            <p>Registration ID: ' . (isset($_GET['registration_id']) ? intval($_GET['registration_id']) : 'Not set') . '</p>
            <p>User ID: ' . (is_user_logged_in() ? get_current_user_id() : 'Not logged in') . '</p>
        </div>';
    }
}

// Initialize
new WeVoi_Bank_Account_Test(); 