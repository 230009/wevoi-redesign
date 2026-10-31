<?php
/**
 * Database schema for Voucher module
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create voucher tables if they don't exist
 */
function wevoi_voucher_create_tables() {
    global $wpdb;
    $wpdb->show_errors();

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $charset_collate = $wpdb->get_charset_collate();

    // Vouchers table
    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wevoi_vouchers (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `competition_code` int(11) NOT NULL,
        `payer_email` varchar(255) NOT NULL,
        `payer_name` varchar(100) NOT NULL,
        `payer_countryid` int(11) NOT NULL,
        `payment_intent_id` varchar(40) DEFAULT NULL,
        `paid_status` enum('PENDING','PAID','FAILED','REFUNDED') NOT NULL DEFAULT 'PENDING',
        `price` decimal(10,2) NOT NULL,
        `voucher_code` varchar(6) DEFAULT NULL,
        `payment_date` datetime NOT NULL,
        `voucher_type` enum('VOUCHER','AMOUNT') NOT NULL,
        `quantity` int(11) DEFAULT NULL,
        `payment_purpose` text DEFAULT NULL,
        `customer_code` varchar(100) DEFAULT NULL,
        `refund_amount` decimal(10,2) DEFAULT NULL,
        `refund_date` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_payment_intent_id` (`payment_intent_id`),
        KEY `idx_voucher_code` (`voucher_code`),
        KEY `idx_payer_email` (`payer_email`),
        KEY `idx_paid_status` (`paid_status`)
    ) $charset_collate;";

    dbDelta($sql);

    // Add payment_purpose column if it doesn't exist (for existing installations)
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}wevoi_vouchers LIKE 'payment_purpose'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}wevoi_vouchers ADD COLUMN `payment_purpose` text DEFAULT NULL AFTER `quantity`");
    }
}
