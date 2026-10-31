<?php
/**
 * Voucher Module main class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WeVoi_Voucher_Module {
    private static $instance = null;
    
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
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }
    
    /**
     * Define module constants
     */
    private function define_constants() {
        define('WEVOI_VOUCHER_DIR', WEVOI_REGISTER_PLUGIN_DIR . 'modules/voucher/');
        define('WEVOI_VOUCHER_URL', WEVOI_REGISTER_PLUGIN_URL . 'modules/voucher/');
        define('WEVOI_VOUCHER_VERSION', WEVOI_REGISTER_VERSION);
    }
    
    /**
     * Include required files
     */
private function includes() {
    // Database schemas
    require_once WEVOI_VOUCHER_DIR . 'db/schema.php';
    
    // Include core files
    require_once WEVOI_VOUCHER_DIR . 'includes/class-voucher-repository.php';
    require_once WEVOI_VOUCHER_DIR . 'includes/class-voucher-service.php';
    require_once WEVOI_VOUCHER_DIR . 'includes/class-voucher-email-service.php';
    require_once WEVOI_VOUCHER_DIR . 'includes/class-voucher-form-handler.php';
    require_once WEVOI_VOUCHER_DIR . 'includes/class-voucher-list-handler.php'; // Yeni eklenen satır
}
    
    /**
     * Initialize hooks
     */
private function init_hooks() {
    // Check if tables need to be created/updated
    register_activation_hook(WEVOI_REGISTER_PLUGIN_DIR . 'wevoi-register.php', 'wevoi_voucher_create_tables');
    
    // Initialize handlers
    add_action('init', function() {
        WeVoi_Voucher_Form_Handler::instance();
        WeVoi_Voucher_List_Handler::instance(); // Yeni eklenen satır
    });
}
}