<?php
/**
 * Database Updater Class
 * 
 * @package WeVoi_Register
 */

class WeVoi_Database_Updater {
    
    public function __construct() {
        add_action('admin_init', array($this, 'check_and_update_database'));
    }
    
    /**
     * Check and update database if needed
     */
    public function check_and_update_database() {
        // Always run: version bumps are skipped once db_version matches, but ENUM / schema fixes must still apply.
        $this->ensure_paid_status_accepts_pre_registered();
        $this->ensure_application_status_includes_video_unlisted();
        $this->ensure_bank_accounts_city_column();
        $this->ensure_bank_accounts_general_info_column();

        $current_version = get_option('wevoi_register_db_version', '0.0.0');
        $plugin_version = WEVOI_REGISTER_VERSION;
        
        if (version_compare($current_version, $plugin_version, '<')) {
            $this->update_database($current_version, $plugin_version);
            update_option('wevoi_register_db_version', $plugin_version);
        }
    }
    
    /**
     * Update database to current version
     */
    private function update_database($from_version, $to_version) {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();
        
        // Add claimed_prize_amount field to registrations table if it doesn't exist
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}wevoi_registrations LIKE 'claimed_prize_amount'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}wevoi_registrations ADD COLUMN `claimed_prize_amount` decimal(10,2) DEFAULT NULL AFTER `application_status`");
        }

        // paid_status ENUM is updated on every admin_init in check_and_update_database().

        // Forum: pre-registration vs payment-required phase (manual switch in competition admin)
        $competitions_table = $wpdb->prefix . 'wevoi_competitions';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$competitions_table}'") === $competitions_table) {
            $forum_phase_col = $wpdb->get_results("SHOW COLUMNS FROM {$competitions_table} LIKE 'forum_payment_phase'");
            if (empty($forum_phase_col)) {
                $wpdb->query("ALTER TABLE {$competitions_table} ADD COLUMN `forum_payment_phase` varchar(32) NULL DEFAULT NULL");
                $wpdb->query("UPDATE {$competitions_table} SET forum_payment_phase = 'pre_registration' WHERE UPPER(TRIM(competition_type)) = 'FORUM' AND (forum_payment_phase IS NULL OR forum_payment_phase = '')");
            }
        }

        // Forum registrations with NULL/empty paid_status (legacy imports or early bugs): backfill
        $registrations_table = $wpdb->prefix . 'wevoi_registrations';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$registrations_table}'") === $registrations_table
            && $wpdb->get_var("SHOW TABLES LIKE '{$competitions_table}'") === $competitions_table
        ) {
            $wpdb->query(
                "UPDATE {$registrations_table} r
                INNER JOIN {$competitions_table} c ON r.competition_code = c.competition_code
                SET r.paid_status = 'FREE'
                WHERE (r.paid_status IS NULL OR r.paid_status = '')
                AND UPPER(TRIM(IFNULL(c.competition_type,''))) = 'FORUM'
                AND IFNULL(r.price, 0) <= 0"
            );
            // Paid forum, no Stripe intent yet: treat as pre-registration / payment-not-started (fixes NULL paid_status rows).
            $wpdb->query(
                "UPDATE {$registrations_table} r
                INNER JOIN {$competitions_table} c ON r.competition_code = c.competition_code
                SET r.paid_status = 'PRE_REGISTERED'
                WHERE (r.paid_status IS NULL OR r.paid_status = '')
                AND UPPER(TRIM(IFNULL(c.competition_type,''))) = 'FORUM'
                AND IFNULL(r.price, 0) > 0
                AND (r.payment_intent_id IS NULL OR r.payment_intent_id = '')
                AND r.application_status IN ('APPLICATION_ACCEPTED', 'PENDING_REVIEW')"
            );
        }
        
        // Create bank accounts table if it doesn't exist
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
        }

        $this->ensure_bank_accounts_city_column();
        $this->ensure_bank_accounts_general_info_column();
        
        // Create currencies table if it doesn't exist
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
            dbDelta($sql);
            
            // Insert default currencies
            $this->insert_default_currencies();
        }
        
        // Log the update
        error_log("WeVoi Register: Database updated from version {$from_version} to {$to_version}");
    }

    /**
     * Add city column to bank accounts table for prize payments.
     */
    private function ensure_bank_accounts_city_column() {
        global $wpdb;

        $table = $wpdb->prefix . 'wevoi_bank_accounts';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return;
        }

        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'city'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN `city` varchar(255) DEFAULT NULL AFTER `countryid`");
        }
    }

    /**
     * Add optional general_info column to bank accounts table.
     */
    private function ensure_bank_accounts_general_info_column() {
        global $wpdb;

        $table = $wpdb->prefix . 'wevoi_bank_accounts';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return;
        }

        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'general_info'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN `general_info` text DEFAULT NULL AFTER `currencyid`");
        }
    }

    /**
     * Add VIDEO_UNLISTED to application_status when the column is MySQL ENUM.
     */
    private function ensure_application_status_includes_video_unlisted() {
        global $wpdb;

        $table = $wpdb->prefix . 'wevoi_registrations';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return;
        }

        $col = $wpdb->get_row("SHOW COLUMNS FROM `{$table}` LIKE 'application_status'");
        if (!$col) {
            return;
        }

        $raw_type = isset($col->Type) ? $col->Type : (isset($col->type) ? $col->type : '');
        if ($raw_type === '' || stripos($raw_type, 'enum(') !== 0) {
            return;
        }
        if (stripos($raw_type, 'video_unlisted') !== false) {
            return;
        }

        if (!preg_match('/^enum\s*\((.+)\)\s*$/is', $raw_type, $m)) {
            error_log('WeVoi Register: application_status ENUM parse failed for type: ' . $raw_type);
            return;
        }

        $parts = explode(',', $m[1]);
        $values = array();
        foreach ($parts as $p) {
            $values[] = trim($p, " \t\n\r\0\x0B'\"");
        }
        $values = array_values(array_filter($values, 'strlen'));
        if (empty($values)) {
            return;
        }

        if (!in_array('VIDEO_UNLISTED', $values, true)) {
            $idx = array_search('APPLICATION_ACCEPTED', $values, true);
            if ($idx !== false) {
                array_splice($values, $idx + 1, 0, array('VIDEO_UNLISTED'));
            } else {
                $values[] = 'VIDEO_UNLISTED';
            }
        }

        $quoted = array();
        foreach ($values as $v) {
            $quoted[] = "'" . esc_sql($v) . "'";
        }
        $enum_sql = implode(',', $quoted);

        $col_null = isset($col->Null) ? $col->Null : (isset($col->null) ? $col->null : 'NO');
        $col_default = isset($col->Default) ? $col->Default : (isset($col->default) ? $col->default : null);

        $null_sql = ($col_null === 'YES') ? 'NULL' : 'NOT NULL';
        $default_sql = '';
        if ($col_default !== null && $col_default !== '') {
            $default_sql = " DEFAULT '" . esc_sql($col_default) . "'";
        } elseif ($col_null === 'NO') {
            $default_sql = " DEFAULT 'PENDING_REVIEW'";
        }

        $alter = "ALTER TABLE `{$table}` MODIFY COLUMN `application_status` ENUM({$enum_sql}) {$null_sql}{$default_sql}";
        $wpdb->query($alter);
        if ($wpdb->last_error) {
            error_log('WeVoi Register: failed to extend application_status ENUM: ' . $wpdb->last_error);
        } else {
            error_log('WeVoi Register: application_status ENUM updated to include VIDEO_UNLISTED on table ' . $table);
        }
    }

    /**
     * Add PRE_REGISTERED to paid_status when the column is MySQL ENUM (otherwise INSERT/UPDATE can fail or store wrong value).
     */
    private function ensure_paid_status_accepts_pre_registered() {
        global $wpdb;

        $table = $wpdb->prefix . 'wevoi_registrations';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return;
        }

        $col = $wpdb->get_row("SHOW COLUMNS FROM `{$table}` LIKE 'paid_status'");
        if (!$col) {
            return;
        }
        $raw_type = isset($col->Type) ? $col->Type : (isset($col->type) ? $col->type : '');
        if ($raw_type === '') {
            return;
        }
        $type_lc = strtolower($raw_type);
        if (strpos($type_lc, 'enum(') !== 0) {
            return;
        }
        if (stripos($raw_type, 'pre_registered') !== false) {
            return;
        }

        if (!preg_match('/^enum\s*\((.+)\)\s*$/is', $raw_type, $m)) {
            error_log('WeVoi Register: paid_status ENUM parse failed for type: ' . $raw_type);
            return;
        }

        $parts = explode(',', $m[1]);
        $values = array();
        foreach ($parts as $p) {
            $values[] = trim($p, " \t\n\r\0\x0B'\"");
        }
        $values = array_values(array_filter($values, 'strlen'));
        if (empty($values)) {
            return;
        }

        if (!in_array('PRE_REGISTERED', $values, true)) {
            $idx = array_search('PENDING', $values, true);
            if ($idx !== false) {
                array_splice($values, $idx + 1, 0, array('PRE_REGISTERED'));
            } else {
                $values[] = 'PRE_REGISTERED';
            }
        }

        $quoted = array();
        foreach ($values as $v) {
            $quoted[] = "'" . esc_sql($v) . "'";
        }
        $enum_sql = implode(',', $quoted);

        $col_null = isset($col->Null) ? $col->Null : (isset($col->null) ? $col->null : 'NO');
        $col_default = isset($col->Default) ? $col->Default : (isset($col->default) ? $col->default : null);

        $null_sql = ($col_null === 'YES') ? 'NULL' : 'NOT NULL';
        $default_sql = '';
        if ($col_default !== null && $col_default !== '') {
            $default_sql = " DEFAULT '" . esc_sql($col_default) . "'";
        } elseif ($col_null === 'NO') {
            $default_sql = " DEFAULT 'PENDING'";
        }

        $alter = "ALTER TABLE `{$table}` MODIFY COLUMN `paid_status` ENUM({$enum_sql}) {$null_sql}{$default_sql}";
        $wpdb->query($alter);
        if ($wpdb->last_error) {
            error_log('WeVoi Register: failed to extend paid_status ENUM: ' . $wpdb->last_error);
        } else {
            error_log('WeVoi Register: paid_status ENUM updated to include PRE_REGISTERED on table ' . $table);
        }
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

// Initialize the database updater
new WeVoi_Database_Updater(); 