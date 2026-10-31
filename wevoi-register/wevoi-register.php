<?php
/**
 * Plugin Name: WeVoi Register
 * Plugin URI: https://wevoi.org
 * Description: Competition registration and payment handling system for WeVoi
 * Version: 1.0.8
 * Author: WeVoi
 * Text Domain: wevoi-register
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('WEVOI_REGISTER_VERSION', '1.0.8');
define('WEVOI_REGISTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WEVOI_REGISTER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Composer autoload: prefer wp-content/plugins/vendor (shared), then plugin's own vendor
$shared_vendor = (defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : dirname(WEVOI_REGISTER_PLUGIN_DIR)) . '/vendor/autoload.php';
$own_vendor    = WEVOI_REGISTER_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($shared_vendor)) {
    require_once $shared_vendor;
} elseif (file_exists($own_vendor)) {
    require_once $own_vendor;
} else {
    wp_die('WeVoi Register: Composer dependencies missing. Run <code>composer install</code> in <code>wp-content/plugins</code> (or in the plugin directory).');
}

// Include core files
require_once WEVOI_REGISTER_PLUGIN_DIR . 'includes/class-wevoi-summit-eligibility.php';
require_once WEVOI_REGISTER_PLUGIN_DIR . 'includes/class-encryption-handler.php';
require_once WEVOI_REGISTER_PLUGIN_DIR . 'includes/class-stripe-handler.php';
require_once WEVOI_REGISTER_PLUGIN_DIR . 'includes/class-ai-video-evaluation.php';
require_once WEVOI_REGISTER_PLUGIN_DIR . 'includes/class-form-handler.php';
require_once WEVOI_REGISTER_PLUGIN_DIR . 'includes/class-email-handler.php';
require_once WEVOI_REGISTER_PLUGIN_DIR . 'includes/class-logger.php';
require_once WEVOI_REGISTER_PLUGIN_DIR . 'includes/class-webhook-handler.php';
require_once WEVOI_REGISTER_PLUGIN_DIR . 'includes/class-bulk-form-handler.php';
require_once WEVOI_REGISTER_PLUGIN_DIR . 'includes/class-forum-form-handler.php';
require_once WEVOI_REGISTER_PLUGIN_DIR . 'includes/class-bank-account-page.php';
require_once WEVOI_REGISTER_PLUGIN_DIR . 'includes/class-bank-account-ajax.php';
require_once WEVOI_REGISTER_PLUGIN_DIR . 'includes/class-bank-account-test.php';
require_once WEVOI_REGISTER_PLUGIN_DIR . 'includes/class-database-updater.php';


// Initialize plugin
class WeVoi_Register {
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
        // Plugin initialization
        add_action('plugins_loaded', array($this, 'init'));

        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));

        // Register deactivation hook
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Initialize plugin components
     */
    public function init() {
        // Run database migrations (check and add missing columns)
        $this->run_migrations();

        // Load text domain for translations
        load_plugin_textdomain('wevoi-register', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Initialize admin if in admin area
        if (is_admin()) {
            require_once WEVOI_REGISTER_PLUGIN_DIR . 'admin/class-admin-settings.php';
            new WeVoi_Register_Admin_Settings();
        }

        // Initialize all handlers
        WeVoi_Register_Encryption_Handler::instance();
        WeVoi_Register_Stripe_Handler::instance();
        WeVoi_Register_Form_Handler::instance();
        WeVoi_Register_Email_Handler::instance();
        WeVoi_Register_Logger::instance();
        WeVoi_Register_Webhook_Handler::instance();
        WeVoi_Register_Bulk_Form_Handler::instance();
        WeVoi_Forum_Form_Handler::instance();

        // Initialize Voucher Module
        $this->init_voucher_module();
    }

    /**
     * Initialize Voucher Module
     */
    private function init_voucher_module() {
        $voucher_module_file = WEVOI_REGISTER_PLUGIN_DIR . 'modules/voucher/class-voucher-module.php';

        if (file_exists($voucher_module_file)) {
            require_once $voucher_module_file;

            if (class_exists('WeVoi_Voucher_Module')) {
                WeVoi_Voucher_Module::instance();
            } else {
                error_log('WeVoi Voucher Module class not found.');
            }
        } else {
            error_log('WeVoi Voucher Module file not found: ' . $voucher_module_file);
        }
    }

    /**
     * Plugin activation tasks
     */
    public function activate() {
        // Create or update database tables
        $this->create_tables();

        // Set default options
        $this->set_default_options();

        // Clear permalinks
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation tasks
     */
    public function deactivate() {
        // Clear permalinks
        flush_rewrite_rules();
    }

    /**
     * Create necessary database tables
     */
    private function create_tables() {
        global $wpdb;
        $wpdb->show_errors();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        // Competitions table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wevoi_competitions (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `competition_code` int(11) NOT NULL,
            `competition_date` date NOT NULL,
            `competition_name` varchar(255) NOT NULL,
            `competition_status` varchar(30) DEFAULT NULL,
            `competition_price` decimal(5,2) DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `competition_code` (`competition_code`),
            KEY `idx_status_date` (`competition_status`,`competition_date`),
            KEY `idx_competition_code` (`competition_code`)
        ) $charset_collate;";
        dbDelta($sql);

        // Countries table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wevoi_countries (
            `countryid` int(11) NOT NULL AUTO_INCREMENT,
            `countryname` varchar(255) NOT NULL,
            `continent` varchar(50) DEFAULT NULL,
            `region` varchar(50) DEFAULT NULL,
            `iso` varchar(3) DEFAULT NULL,
            PRIMARY KEY (`countryid`)
        ) $charset_collate;";
        dbDelta($sql);

        // Coupons table - matching existing database structure
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wevoi_coupons (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `coupon_code` varchar(50) NOT NULL,
            `coupon_limit_expiration_date` date DEFAULT NULL,
            `coupon_limit` int(11) DEFAULT NULL,
            `coupon_discount_amount` decimal(5,2) DEFAULT NULL,
            `coupon_discount_percentage` tinyint(4) DEFAULT NULL,
            `competition_code` int(11) NOT NULL,
            `coupon_used` int(11) DEFAULT 0,
            `coupon_comments` varchar(255) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `competition_code` (`competition_code`)
        ) $charset_collate;";
        dbDelta($sql);

        // Add coupon_discount_percentage column if it doesn't exist (for existing installations)
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}wevoi_coupons LIKE 'coupon_discount_percentage'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}wevoi_coupons ADD COLUMN `coupon_discount_percentage` tinyint(4) DEFAULT NULL AFTER `coupon_discount_amount`");
        }

        // Modify coupon_discount_amount to allow NULL and match existing type (for existing installations)
        $column_info = $wpdb->get_row("SHOW COLUMNS FROM {$wpdb->prefix}wevoi_coupons WHERE Field = 'coupon_discount_amount'");
        if ($column_info) {
            // Check if type is different or NULL is not allowed
            if ($column_info->Type !== 'decimal(5,2)' || $column_info->Null === 'NO') {
                $wpdb->query("ALTER TABLE {$wpdb->prefix}wevoi_coupons MODIFY COLUMN `coupon_discount_amount` decimal(5,2) DEFAULT NULL");
            }
        }

        // Modify coupon_discount_percentage to match existing type if it exists
        $column_info = $wpdb->get_row("SHOW COLUMNS FROM {$wpdb->prefix}wevoi_coupons WHERE Field = 'coupon_discount_percentage'");
        if ($column_info && $column_info->Type !== 'tinyint(4)') {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}wevoi_coupons MODIFY COLUMN `coupon_discount_percentage` tinyint(4) DEFAULT NULL");
        }

        // Add coupon_limit_expiration_date column if it doesn't exist
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}wevoi_coupons LIKE 'coupon_limit_expiration_date'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}wevoi_coupons ADD COLUMN `coupon_limit_expiration_date` date DEFAULT NULL AFTER `coupon_code`");
        }

        // Modify coupon_limit to allow NULL if it doesn't
        $column_info = $wpdb->get_row("SHOW COLUMNS FROM {$wpdb->prefix}wevoi_coupons WHERE Field = 'coupon_limit'");
        if ($column_info && $column_info->Null === 'NO') {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}wevoi_coupons MODIFY COLUMN `coupon_limit` int(11) DEFAULT NULL");
        }

        // Add coupon_comments column if it doesn't exist, or modify if type is different
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}wevoi_coupons LIKE 'coupon_comments'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}wevoi_coupons ADD COLUMN `coupon_comments` varchar(255) DEFAULT NULL AFTER `coupon_used`");
        } else {
            // Check if type is text instead of varchar(255)
            $column_info = $wpdb->get_row("SHOW COLUMNS FROM {$wpdb->prefix}wevoi_coupons WHERE Field = 'coupon_comments'");
            if ($column_info && strpos($column_info->Type, 'text') !== false) {
                $wpdb->query("ALTER TABLE {$wpdb->prefix}wevoi_coupons MODIFY COLUMN `coupon_comments` varchar(255) DEFAULT NULL");
            }
        }

        // Add role column for Forum coupons (NULL = all roles, 'student'/'supervisor'/'parent' = specific role)
        $this->add_role_column_to_coupons();

        // Registrations table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wevoi_registrations (
            `register_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL,
            `competition_code` int(11) NOT NULL,
            `youtube_link` varchar(255) NOT NULL,
            `payer_name` varchar(100) NOT NULL,
            `payer_countryid` int(11) NOT NULL,
            `paid_status` varchar(50) NOT NULL,
            `price` decimal(10,2) NOT NULL,
            `coupon_code` varchar(50) DEFAULT NULL,
            `customer_code` varchar(100) DEFAULT NULL,
            `registration_date` datetime NOT NULL,
            `payment_intent_id` varchar(40) DEFAULT NULL,
            `refund_amount` decimal(10,2) DEFAULT NULL,
            `refund_date` datetime DEFAULT NULL,
            `application_status` enum('PENDING_REVIEW','APPLICATION_ACCEPTED','VIDEO_UNLISTED','VIDEO_INACCESSIBLE','IRRELEVANT_CONTENT','APPLICATION_REJECTED') NOT NULL DEFAULT 'PENDING_REVIEW',
            `claimed_prize_amount` decimal(10,2) DEFAULT NULL,
            PRIMARY KEY (`register_id`),
            KEY `user_id` (`user_id`),
            KEY `competition_code` (`competition_code`),
            KEY `idx_user_competition` (`user_id`,`competition_code`),
            KEY `idx_registration_date` (`registration_date`),
            KEY `idx_paid_status` (`paid_status`)
        ) $charset_collate;";
        dbDelta($sql);

        // Stripe events table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wevoi_stripe_events (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `event_id` varchar(255) NOT NULL,
            `processed_at` datetime NOT NULL,
            `retry_count` int(11) DEFAULT 0,
            `last_error` text DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_event_id` (`event_id`)
        ) $charset_collate;";
        dbDelta($sql);

        // Bank accounts table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wevoi_bank_accounts (
            `id` bigint(20) NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL,
            `competition_code` int(11) NOT NULL,
            `registration_id` bigint(20) unsigned NOT NULL,
            `account_holder_name` varchar(255) NOT NULL,
            `bank_name` varchar(255) NOT NULL,
            `account_number` varchar(50) NOT NULL,
            `sort_code` varchar(20) DEFAULT NULL,
            `iban` varchar(50) DEFAULT NULL,
            `swift_code` varchar(20) DEFAULT NULL,
            `countryid` int(11) NOT NULL,
            `city` varchar(255) DEFAULT NULL,
            `currencyid` int(11) NOT NULL,
            `general_info` text DEFAULT NULL,
            `status` enum('PENDING','VERIFIED','REJECTED') NOT NULL DEFAULT 'PENDING',
            `admin_notes` text DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_registration_id` (`registration_id`),
            KEY `user_id` (`user_id`),
            KEY `competition_code` (`competition_code`),
            KEY `status` (`status`)
        ) $charset_collate;";
        dbDelta($sql);

        // Currencies table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wevoi_currencies (
            `currencyid` int(11) NOT NULL AUTO_INCREMENT,
            `currency_code` varchar(3) NOT NULL,
            `currency_name` varchar(100) NOT NULL,
            `symbol` varchar(10) NOT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (`currencyid`),
            UNIQUE KEY `currency_code` (`currency_code`)
        ) $charset_collate;";
        dbDelta($sql);

        // AI video evaluation table (Gemini scoring for accepted video submissions; in-plugin).
        $this->create_ai_evaluation_table();
    }

    /**
     * Set default plugin options
     */
    /**
     * Run database migrations (check and add missing columns)
     */
    private function run_migrations() {
        global $wpdb;

        // Add role column to coupons table if it doesn't exist
        $this->add_role_column_to_coupons();

        // Certificate CSV export: prefix per competition (set in Competition Manager)
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}wevoi_competitions LIKE 'certificate_id_prefix'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}wevoi_competitions ADD COLUMN `certificate_id_prefix` varchar(64) DEFAULT NULL AFTER `competition_code`");
        }

        // Bank accounts: city for prize payments
        $bank_table = $wpdb->prefix . 'wevoi_bank_accounts';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$bank_table}'") === $bank_table) {
            $city_column = $wpdb->get_results("SHOW COLUMNS FROM {$bank_table} LIKE 'city'");
            if (empty($city_column)) {
                $wpdb->query("ALTER TABLE {$bank_table} ADD COLUMN `city` varchar(255) DEFAULT NULL AFTER `countryid`");
            }
        }

        // Ensure AI evaluation table exists on plugin updates.
        $this->create_ai_evaluation_table();
    }

    /**
     * Add role column to coupons table for Forum coupons
     */
    private function add_role_column_to_coupons() {
        global $wpdb;

        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}wevoi_coupons LIKE 'role'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}wevoi_coupons ADD COLUMN `role` varchar(20) DEFAULT NULL AFTER `competition_code`");
        }
    }

    /**
     * Create AI video evaluation table (Gemini results).
     */
    private function create_ai_evaluation_table() {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wevoi_ai_evaluation (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `season_id` int(11) NOT NULL,
            `competition_code` int(11) NOT NULL,
            `user_id` bigint(20) unsigned NOT NULL,
            `evaluated_video_url` varchar(2048) DEFAULT NULL,
            `score_content` decimal(5,2) NOT NULL DEFAULT 0.00,
            `score_accuracy` decimal(5,2) NOT NULL DEFAULT 0.00,
            `score_delivery` decimal(5,2) NOT NULL DEFAULT 0.00,
            `score_organization` decimal(5,2) NOT NULL DEFAULT 0.00,
            `score_total` decimal(5,2) NOT NULL DEFAULT 0.00,
            `ai_model` varchar(120) DEFAULT NULL,
            `ai_version` varchar(120) DEFAULT NULL,
            `evaluation_json` longtext DEFAULT NULL,
            `gemini_elapsed_ms` int(11) DEFAULT NULL,
            `suspicious_fast_response` tinyint(1) NOT NULL DEFAULT 0,
            `likely_not_watched` tinyint(1) NOT NULL DEFAULT 0,
            `video_observed_reliably` tinyint(1) NOT NULL DEFAULT 1,
            `flags_json` text DEFAULT NULL,
            `confidence` decimal(5,4) DEFAULT NULL,
            `attempt_count` int(11) NOT NULL DEFAULT 1,
            `status` varchar(30) NOT NULL DEFAULT 'completed',
            `last_error` text DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_season_competition_user` (`season_id`,`competition_code`,`user_id`),
            KEY `idx_competition_code` (`competition_code`),
            KEY `idx_user_id` (`user_id`),
            KEY `idx_status` (`status`)
        ) $charset_collate;";

        dbDelta($sql);

        // Backward-compatible column adds for existing installs.
        $table = $wpdb->prefix . 'wevoi_ai_evaluation';
        $columns = array(
            'evaluated_video_url' => "ALTER TABLE {$table} ADD COLUMN `evaluated_video_url` varchar(2048) DEFAULT NULL AFTER `user_id`",
            'gemini_elapsed_ms' => "ALTER TABLE {$table} ADD COLUMN `gemini_elapsed_ms` int(11) DEFAULT NULL AFTER `evaluation_json`",
            'suspicious_fast_response' => "ALTER TABLE {$table} ADD COLUMN `suspicious_fast_response` tinyint(1) NOT NULL DEFAULT 0 AFTER `gemini_elapsed_ms`",
            'likely_not_watched' => "ALTER TABLE {$table} ADD COLUMN `likely_not_watched` tinyint(1) NOT NULL DEFAULT 0 AFTER `suspicious_fast_response`",
            'video_observed_reliably' => "ALTER TABLE {$table} ADD COLUMN `video_observed_reliably` tinyint(1) NOT NULL DEFAULT 1 AFTER `likely_not_watched`",
            'flags_json' => "ALTER TABLE {$table} ADD COLUMN `flags_json` text DEFAULT NULL AFTER `video_observed_reliably`",
            'confidence' => "ALTER TABLE {$table} ADD COLUMN `confidence` decimal(5,4) DEFAULT NULL AFTER `flags_json`"
        );

        foreach ($columns as $column_name => $alter_sql) {
            $exists = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE '{$column_name}'");
            if (empty($exists)) {
                $wpdb->query($alter_sql);
            }
        }
    }

    private function set_default_options() {
        $defaults = array(
            'stripe_mode' => 'test',
            'test_publishable_key' => '',
            'test_secret_key' => '',
            'test_webhook_secret' => '',
            'live_publishable_key' => '',
            'live_secret_key' => '',
            'live_webhook_secret' => '',
            'gemini_api_key' => '',
            'gemini_model' => 'gemini-2.5-flash',
        );

        foreach ($defaults as $key => $value) {
            if (get_option('wevoi_register_' . $key) === false) {
                add_option('wevoi_register_' . $key, $value);
            }
        }

        // Insert default currencies
        $this->insert_default_currencies();
    }

    /**
     * Insert default currencies
     */
    private function insert_default_currencies() {
        global $wpdb;

        $default_currencies = array(
            array('GBP', 'British Pound', '£'),
            array('USD', 'US Dollar', '$'),
            array('EUR', 'Euro', '€'),
            array('TRY', 'Turkish Lira', '₺'),
            array('CAD', 'Canadian Dollar', 'C$'),
            array('AUD', 'Australian Dollar', 'A$')
        );

        foreach ($default_currencies as $currency) {
            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$wpdb->prefix}wevoi_currencies (currency_code, currency_name, symbol) VALUES (%s, %s, %s)",
                $currency[0], $currency[1], $currency[2]
            ));
        }
    }
}

// Initialize the plugin
function wevoi_register() {
    return WeVoi_Register::instance();
}

// Start the plugin
wevoi_register();
