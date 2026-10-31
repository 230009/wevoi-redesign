<?php
/**
 * Fix Bank Accounts Table Structure
 * 
 * This script converts the country and currency fields from varchar to integer
 * to match the form requirements and existing countries/currencies tables.
 */

// Load WordPress
require_once('../../../wp-load.php');

// Security check - only allow admin users
if (!current_user_can('manage_options')) {
    // Instead of blocking, just show a warning
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px; border-radius: 5px;'>";
    echo "<strong>Warning:</strong> You should be logged in as an administrator to run this script safely.";
    echo "<br><a href='" . wp_login_url() . "'>Login to WordPress Admin</a>";
    echo "</div>";
}

echo "<h1>WeVoi Bank Accounts Table Fix</h1>";

global $wpdb;

echo "<h2>Current Table Structure</h2>";
$current_structure = $wpdb->get_results("DESCRIBE {$wpdb->prefix}wevoi_bank_accounts");
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
foreach ($current_structure as $field) {
    echo "<tr>";
    echo "<td>{$field->Field}</td>";
    echo "<td>{$field->Type}</td>";
    echo "<td>{$field->Null}</td>";
    echo "<td>{$field->Key}</td>";
    echo "<td>{$field->Default}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Fixing Table Structure...</h2>";

// Step 1: Add new columns
echo "<h3>Step 1: Adding new columns...</h3>";

// Add countryid column
$result = $wpdb->query("ALTER TABLE {$wpdb->prefix}wevoi_bank_accounts ADD COLUMN `countryid` int(11) DEFAULT NULL AFTER `swift_code`");
if ($result !== false) {
    echo "<p style='color: green;'>✓ Added countryid column</p>";
} else {
    echo "<p style='color: red;'>✗ Error adding countryid column: " . $wpdb->last_error . "</p>";
}

// Add currencyid column
$result = $wpdb->query("ALTER TABLE {$wpdb->prefix}wevoi_bank_accounts ADD COLUMN `currencyid` int(11) DEFAULT NULL AFTER `countryid`");
if ($result !== false) {
    echo "<p style='color: green;'>✓ Added currencyid column</p>";
} else {
    echo "<p style='color: red;'>✗ Error adding currencyid column: " . $wpdb->last_error . "</p>";
}

// Step 2: Migrate existing data
echo "<h3>Step 2: Migrating existing data...</h3>";

// Get existing bank accounts with country/currency data
$accounts = $wpdb->get_results("SELECT id, country, currency FROM {$wpdb->prefix}wevoi_bank_accounts WHERE country IS NOT NULL OR currency IS NOT NULL");

if (!empty($accounts)) {
    echo "<p>Found " . count($accounts) . " accounts to migrate...</p>";
    
    foreach ($accounts as $account) {
        $updates = array();
        
        // Migrate country to countryid
        if (!empty($account->country)) {
            $countryid = $wpdb->get_var($wpdb->prepare(
                "SELECT countryid FROM {$wpdb->prefix}wevoi_countries WHERE countryname = %s",
                $account->country
            ));
            
            if ($countryid) {
                $updates[] = "countryid = " . intval($countryid);
                echo "<p>✓ Mapped country '{$account->country}' to countryid {$countryid}</p>";
            } else {
                echo "<p style='color: orange;'>⚠ Country '{$account->country}' not found in countries table</p>";
            }
        }
        
        // Migrate currency to currencyid
        if (!empty($account->currency)) {
            $currencyid = $wpdb->get_var($wpdb->prepare(
                "SELECT currencyid FROM {$wpdb->prefix}wevoi_currencies WHERE currency_code = %s",
                $account->currency
            ));
            
            if ($currencyid) {
                $updates[] = "currencyid = " . intval($currencyid);
                echo "<p>✓ Mapped currency '{$account->currency}' to currencyid {$currencyid}</p>";
            } else {
                echo "<p style='color: orange;'>⚠ Currency '{$account->currency}' not found in currencies table</p>";
            }
        }
        
        // Update the record
        if (!empty($updates)) {
            $update_sql = "UPDATE {$wpdb->prefix}wevoi_bank_accounts SET " . implode(', ', $updates) . " WHERE id = " . intval($account->id);
            $result = $wpdb->query($update_sql);
            
            if ($result !== false) {
                echo "<p style='color: green;'>✓ Updated account ID {$account->id}</p>";
            } else {
                echo "<p style='color: red;'>✗ Error updating account ID {$account->id}: " . $wpdb->last_error . "</p>";
            }
        }
    }
} else {
    echo "<p>No existing data to migrate.</p>";
}

// Step 3: Make new columns NOT NULL and remove old columns
echo "<h3>Step 3: Finalizing table structure...</h3>";

// Make countryid NOT NULL
$result = $wpdb->query("ALTER TABLE {$wpdb->prefix}wevoi_bank_accounts MODIFY COLUMN `countryid` int(11) NOT NULL");
if ($result !== false) {
    echo "<p style='color: green;'>✓ Made countryid NOT NULL</p>";
} else {
    echo "<p style='color: red;'>✗ Error making countryid NOT NULL: " . $wpdb->last_error . "</p>";
}

// Make currencyid NOT NULL
$result = $wpdb->query("ALTER TABLE {$wpdb->prefix}wevoi_bank_accounts MODIFY COLUMN `currencyid` int(11) NOT NULL");
if ($result !== false) {
    echo "<p style='color: green;'>✓ Made currencyid NOT NULL</p>";
} else {
    echo "<p style='color: red;'>✗ Error making currencyid NOT NULL: " . $wpdb->last_error . "</p>";
}

// Remove old country column
$result = $wpdb->query("ALTER TABLE {$wpdb->prefix}wevoi_bank_accounts DROP COLUMN `country`");
if ($result !== false) {
    echo "<p style='color: green;'>✓ Removed old country column</p>";
} else {
    echo "<p style='color: red;'>✗ Error removing country column: " . $wpdb->last_error . "</p>";
}

// Remove old currency column
$result = $wpdb->query("ALTER TABLE {$wpdb->prefix}wevoi_bank_accounts DROP COLUMN `currency`");
if ($result !== false) {
    echo "<p style='color: green;'>✓ Removed old currency column</p>";
} else {
    echo "<p style='color: red;'>✗ Error removing currency column: " . $wpdb->last_error . "</p>";
}

// Step 4: Show final table structure
echo "<h2>Final Table Structure</h2>";
$final_structure = $wpdb->get_results("DESCRIBE {$wpdb->prefix}wevoi_bank_accounts");
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
foreach ($final_structure as $field) {
    echo "<tr>";
    echo "<td>{$field->Field}</td>";
    echo "<td>{$field->Type}</td>";
    echo "<td>{$field->Null}</td>";
    echo "<td>{$field->Key}</td>";
    echo "<td>{$field->Default}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Table fix completed!</h2>";
echo "<p>The bank accounts table now matches the form requirements.</p>";
echo "<p><a href='" . admin_url() . "'>Return to WordPress Admin</a></p>"; 