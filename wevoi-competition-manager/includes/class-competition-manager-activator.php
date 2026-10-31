<?php
class Wevoi_Competition_Manager_Activator {
    public static function activate() {
        self::create_tables();
        self::add_capabilities();
        self::add_default_options();
        flush_rewrite_rules();
    }

    private static function add_default_options() {
        if (get_option('wevoi_stripe_fee_percent') === false) {
            add_option('wevoi_stripe_fee_percent', '2.9');
        }
        if (get_option('wevoi_stripe_fee_fixed') === false) {
            add_option('wevoi_stripe_fee_fixed', '0.20');
        }
    }

    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Email Snippets table
        $table_name = $wpdb->prefix . 'wevoi_email_snippets';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `snippet_name` varchar(255) NOT NULL,
            `target_group` enum('Student','Supervisor','Judge') NOT NULL DEFAULT 'Student',
            `snippet_text` text NOT NULL,
            `date_created` datetime DEFAULT CURRENT_TIMESTAMP,
            `last_modified` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Forum Discounts table
        $table_name = $wpdb->prefix . 'wevoi_forum_discounts';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `competition_code` int(11) NOT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `gold_discount` decimal(5,2) NOT NULL DEFAULT 10.00,
            `silver_discount` decimal(5,2) NOT NULL DEFAULT 6.00,
            `bronze_discount` decimal(5,2) NOT NULL DEFAULT 6.00,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_competition_code_active` (`competition_code`, `is_active`),
            KEY `idx_competition_code` (`competition_code`),
            KEY `idx_is_active` (`is_active`)
        ) $charset_collate;";
        dbDelta($sql);

        // Seasons table (for commission and competition grouping)
        $table_name = $wpdb->prefix . 'wevoi_seasons';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            `season_id` int(11) NOT NULL AUTO_INCREMENT,
            `season_name` varchar(50) NOT NULL,
            `season_abv` varchar(20) NOT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 0,
            `min_students_commission` int(11) NULL DEFAULT 10,
            PRIMARY KEY (`season_id`),
            UNIQUE KEY `idx_season_name` (`season_name`),
            UNIQUE KEY `idx_season_abv` (`season_abv`),
            KEY `idx_is_active` (`is_active`)
        ) $charset_collate;";
        dbDelta($sql);

        // Ensure min_students_commission exists on existing installs
        $col = $wpdb->get_results("SHOW COLUMNS FROM {$table_name} LIKE 'min_students_commission'");
        if (empty($col)) {
            $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN `min_students_commission` int(11) NULL DEFAULT 10 AFTER `is_active`");
        }

        // Ensure award_ceremony_zoom_link and award_ceremony_date exist on wevoi_competitions
        $competitions_table = $wpdb->prefix . 'wevoi_competitions';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$competitions_table}'") === $competitions_table) {
            $zoom_col = $wpdb->get_results("SHOW COLUMNS FROM {$competitions_table} LIKE 'award_ceremony_zoom_link'");
            if (empty($zoom_col)) {
                $wpdb->query("ALTER TABLE {$competitions_table} ADD COLUMN `award_ceremony_zoom_link` varchar(2048) NULL DEFAULT NULL AFTER `award_ceremony_completed`");
            }
            $date_col = $wpdb->get_results("SHOW COLUMNS FROM {$competitions_table} LIKE 'award_ceremony_date'");
            if (empty($date_col)) {
                $wpdb->query("ALTER TABLE {$competitions_table} ADD COLUMN `award_ceremony_date` datetime NULL DEFAULT NULL AFTER `award_ceremony_zoom_link`");
            }
        }

        // Commission rates per season and role (supervisor / agency)
        $table_name = $wpdb->prefix . 'wevoi_commission_rates';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `season_id` int(11) NOT NULL,
            `role` varchar(20) NOT NULL,
            `commission_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_season_role` (`season_id`, `role`),
            KEY `idx_season_id` (`season_id`)
        ) $charset_collate;";
        dbDelta($sql);

        // Commission payments: one row per (user_id, competition_code, season_id) with total
        $table_name = $wpdb->prefix . 'wevoi_commission_payments';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL,
            `competition_code` int(11) NOT NULL,
            `season_id` int(11) NOT NULL,
            `role` varchar(20) NOT NULL,
            `total_commission_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
            `status` varchar(20) NOT NULL DEFAULT 'PENDING',
            `paid_at` datetime DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_user_competition_season` (`user_id`, `competition_code`, `season_id`),
            KEY `idx_user_id` (`user_id`),
            KEY `idx_competition_code` (`competition_code`),
            KEY `idx_season_id` (`season_id`),
            KEY `idx_status` (`status`)
        ) $charset_collate;";
        dbDelta($sql);

        // Commission payment details: per-registration breakdown for modal
        $table_name = $wpdb->prefix . 'wevoi_commission_payment_details';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `commission_payment_id` int(11) NOT NULL,
            `register_id` bigint(20) unsigned NOT NULL,
            `registration_price` decimal(10,2) NOT NULL DEFAULT 0.00,
            `stripe_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
            `net_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
            `commission_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
            `commission_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_payment_register` (`commission_payment_id`, `register_id`),
            KEY `idx_commission_payment_id` (`commission_payment_id`)
        ) $charset_collate;";
        dbDelta($sql);
    }

    private static function add_capabilities() {
        $admin = get_role('administrator');

        // Competitions capabilities
        $admin->add_cap('manage_competitions');
        $admin->add_cap('create_competitions');
        $admin->add_cap('edit_competitions');
        $admin->add_cap('delete_competitions');

        // Coupons capabilities
        $admin->add_cap('manage_coupons');
        $admin->add_cap('create_coupons');
        $admin->add_cap('edit_coupons');
        $admin->add_cap('delete_coupons');

        // Email snippets capabilities
        $admin->add_cap('manage_email_snippets');
        $admin->add_cap('create_email_snippets');
        $admin->add_cap('edit_email_snippets');
        $admin->add_cap('delete_email_snippets');
    }
}
