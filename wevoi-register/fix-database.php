<?php
/**
 * Database Fix Script for WeVoi Register Plugin
 * 
 * This script can be run manually to fix missing database tables and fields.
 * Run this script once to update your database.
 */

// Load WordPress
require_once('../../../wp-load.php');

// Security check - only allow admin users
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access');
}

echo "<h1>WeVoi Register Database Fix</h1>";

global $wpdb;
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

$charset_collate = $wpdb->get_charset_collate();

// Check and add claimed_prize_amount field to registrations table
echo "<h2>Checking registrations table...</h2>";
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}wevoi_registrations LIKE 'claimed_prize_amount'");
if (empty($column_exists)) {
    $result = $wpdb->query("ALTER TABLE {$wpdb->prefix}wevoi_registrations ADD COLUMN `claimed_prize_amount` decimal(10,2) DEFAULT NULL AFTER `application_status`");
    if ($result !== false) {
        echo "<p style='color: green;'>✓ Added claimed_prize_amount field to registrations table</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding claimed_prize_amount field: " . $wpdb->last_error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ claimed_prize_amount field already exists</p>";
}

// Check and create bank accounts table
echo "<h2>Checking bank accounts table...</h2>";
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wevoi_bank_accounts'");
if (!$table_exists) {
    $sql = "CREATE TABLE {$wpdb->prefix}wevoi_bank_accounts (
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
        `currencyid` int(11) NOT NULL,
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
    
    $result = dbDelta($sql);
    if (empty($wpdb->last_error)) {
        echo "<p style='color: green;'>✓ Created bank accounts table</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating bank accounts table: " . $wpdb->last_error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ Bank accounts table already exists</p>";
}

// Check and create currencies table
echo "<h2>Checking currencies table...</h2>";
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wevoi_currencies'");
if (!$table_exists) {
    $sql = "CREATE TABLE {$wpdb->prefix}wevoi_currencies (
        `currencyid` int(11) NOT NULL AUTO_INCREMENT,
        `currency_code` varchar(3) NOT NULL,
        `currency_name` varchar(100) NOT NULL,
        `symbol` varchar(10) NOT NULL,
        `is_active` tinyint(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`currencyid`),
        UNIQUE KEY `currency_code` (`currency_code`)
    ) $charset_collate;";
    
    $result = dbDelta($sql);
    if (empty($wpdb->last_error)) {
        echo "<p style='color: green;'>✓ Created currencies table</p>";
        
        // Insert default currencies
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
        echo "<p style='color: green;'>✓ Inserted default currencies</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating currencies table: " . $wpdb->last_error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ Currencies table already exists</p>";
}

// Update database version
update_option('wevoi_register_db_version', '1.0.0');

echo "<h2>Database fix completed!</h2>";
echo "<p>The bank account functionality should now work properly.</p>";
echo "<p><a href='" . admin_url() . "'>Return to WordPress Admin</a></p>"; 