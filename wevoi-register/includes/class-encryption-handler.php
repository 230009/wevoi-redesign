<?php
/**
 * Encryption handling for WeVoi Register
 */

if (!defined('ABSPATH')) {
    exit;
}

use Defuse\Crypto\Key;
use Defuse\Crypto\Crypto;

class WeVoi_Register_Encryption_Handler {
    private static $instance = null;
    private $encryption_key;

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
        $this->init_encryption_key();
    }

    /**
     * Initialize or retrieve encryption key
     */
private function init_encryption_key() {
    try {
        $key = get_option('wevoi_register_encryption_key');
        
        if (empty($key)) {
            // Generate a new encryption key
            $newKey = Key::createNewRandomKey();
            $saveKey = $newKey->saveToAsciiSafeString();
            update_option('wevoi_register_encryption_key', $saveKey);
            $this->encryption_key = $newKey;
        } else {
            // Load existing key
            $this->encryption_key = Key::loadFromAsciiSafeString($key);
        }
    } catch (Exception $e) {
        $this->encryption_key = null;
    }
}

    /**
     * Encrypt sensitive data
     * 
     * @param string $value The value to encrypt
     * @return string|bool Encrypted value or false on failure
     */
    public function encrypt($value) {
        if (empty($value)) {
            return '';
        }

        try {
            return \Defuse\Crypto\Crypto::encrypt($value, $this->encryption_key);
        } catch (\Defuse\Crypto\Exception\EnvironmentIsBrokenException $ex) {
            return false;
        }
    }

    /**
     * Decrypt sensitive data
     * 
     * @param string $encrypted_value The value to decrypt
     * @return string|bool Decrypted value or false on failure
     */
    public function decrypt($encrypted_value) {
        if (empty($encrypted_value)) {
            return '';
        }

        try {
            return \Defuse\Crypto\Crypto::decrypt($encrypted_value, $this->encryption_key);
        } catch (\Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
            return false;
        }
    }

    /**
     * Get current encryption key
     */
    public function get_key() {
        return $this->encryption_key;
    }

    /**
     * Check if encryption is properly initialized
     */
    public function is_encryption_ready() {
        return $this->encryption_key !== null;
    }
}