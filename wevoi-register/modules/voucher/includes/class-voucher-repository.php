<?php
/**
 * Voucher Repository for database operations
 */

if (!defined('ABSPATH')) {
    exit;
}

class WeVoi_Voucher_Repository {
    private static $instance = null;
    private $db;
    private $logger;
    
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
        global $wpdb;
        $this->db = $wpdb;
        $this->logger = WeVoi_Register_Logger::instance();
    }
    
    /**
     * Create a new voucher record
     */
    /**
 * Create a new voucher record
 */
public function create_voucher($data) {
    try {
        $this->db->query('START TRANSACTION');
        
        $inserted = $this->db->insert(
            $this->db->prefix . 'wevoi_vouchers',
            [
                'competition_code' => $data['competition_code'],
                'payer_email' => $data['payer_email'],
                'payer_name' => $data['payer_name'],
                'payer_countryid' => $data['payer_countryid'],
                'payment_intent_id' => $data['payment_intent_id'],
                'paid_status' => 'PENDING',
                'price' => $data['price'],
                'payment_date' => current_time('mysql'),
                'voucher_type' => $data['voucher_type'],
                'quantity' => $data['quantity'] ?? null,
                'payment_purpose' => isset($data['payment_purpose']) ? $data['payment_purpose'] : null // Yeni eklenen alan
            ],
            [
                '%d', '%s', '%s', '%d', '%s', '%s', '%f', '%s', '%s', '%d', '%s' // %s eklendi
            ]
        );
        
        if (!$inserted) {
            throw new Exception('Failed to insert voucher record');
        }
        
        $voucher_id = $this->db->insert_id;
        $this->db->query('COMMIT');
        return $voucher_id;
        
    } catch (Exception $e) {
        $this->db->query('ROLLBACK');
        $this->logger->log_error('Voucher creation failed', [
            'error' => $e->getMessage(),
            'data' => $data
        ]);
        throw $e;
    }
}
    
    /**
     * Update voucher payment status
     */
    public function update_payment_status($voucher_id, $status, $customer_id = null) {
        try {
            $this->db->query('START TRANSACTION');
            
            $data = [
                'paid_status' => $status
            ];
            
            $format = ['%s'];
            
            if ($customer_id) {
                $data['customer_code'] = $customer_id;
                $format[] = '%s';
            }
            
            $updated = $this->db->update(
                $this->db->prefix . 'wevoi_vouchers',
                $data,
                ['id' => $voucher_id],
                $format,
                ['%d']
            );
            
            if ($updated === false) {
                throw new Exception('Failed to update voucher payment status');
            }
            
            $this->db->query('COMMIT');
            return true;
            
        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
            $this->logger->log_error('Voucher payment status update failed', [
                'error' => $e->getMessage(),
                'voucher_id' => $voucher_id,
                'status' => $status
            ]);
            throw $e;
        }
    }
    
    /**
     * Update voucher with generated code
     */
    public function update_voucher_code($voucher_id, $voucher_code) {
        try {
            $this->db->query('START TRANSACTION');
            
            $updated = $this->db->update(
                $this->db->prefix . 'wevoi_vouchers',
                ['voucher_code' => $voucher_code],
                ['id' => $voucher_id],
                ['%s'],
                ['%d']
            );
            
            if ($updated === false) {
                throw new Exception('Failed to update voucher code');
            }
            
            $this->db->query('COMMIT');
            return true;
            
        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
            $this->logger->log_error('Voucher code update failed', [
                'error' => $e->getMessage(),
                'voucher_id' => $voucher_id,
                'voucher_code' => $voucher_code
            ]);
            throw $e;
        }
    }
    
    /**
     * Get voucher by ID
     */
    public function get_voucher($voucher_id) {
        return $this->db->get_row($this->db->prepare(
            "SELECT v.*, c.competition_name, co.countryname 
            FROM {$this->db->prefix}wevoi_vouchers v
            JOIN {$this->db->prefix}wevoi_competitions c ON v.competition_code = c.competition_code
            JOIN {$this->db->prefix}wevoi_countries co ON v.payer_countryid = co.countryid
            WHERE v.id = %d",
            $voucher_id
        ));
    }
    
    /**
     * Get voucher by payment intent ID
     */
    public function get_voucher_by_payment_intent($payment_intent_id) {
        return $this->db->get_row($this->db->prepare(
            "SELECT v.*, c.competition_name, co.countryname 
            FROM {$this->db->prefix}wevoi_vouchers v
            JOIN {$this->db->prefix}wevoi_competitions c ON v.competition_code = c.competition_code
            JOIN {$this->db->prefix}wevoi_countries co ON v.payer_countryid = co.countryid
            WHERE v.payment_intent_id = %s",
            $payment_intent_id
        ));
    }
    
public function create_coupon_for_voucher($competition_code, $voucher_code, $quantity = 1) {
    try {
        $this->db->query('START TRANSACTION');
        
        // Log coupon creation attempt
        error_log("Creating coupon for competition: $competition_code, code: $voucher_code, quantity: $quantity");
        
        // Get competition price
        $price = $this->db->get_var($this->db->prepare(
            "SELECT competition_price FROM {$this->db->prefix}wevoi_competitions 
            WHERE competition_code = %d",
            $competition_code
        ));
        
        if (!$price) {
            throw new Exception('Competition not found or price not set');
        }
        
        // Check if coupon already exists
        $existing_coupon = $this->db->get_var($this->db->prepare(
            "SELECT id FROM {$this->db->prefix}wevoi_coupons 
            WHERE coupon_code = %s",
            $voucher_code
        ));
        
        if ($existing_coupon) {
            // Coupon already exists, just return success
            $this->db->query('COMMIT');
            return true;
        }
        
        // Create coupon with 100% discount
        $inserted = $this->db->insert(
            $this->db->prefix . 'wevoi_coupons',
            [
                'coupon_code' => $voucher_code,
                'coupon_limit' => $quantity,
                'coupon_discount_percentage' => 100, // 100% discount
                'coupon_discount_amount' => null,
                'competition_code' => $competition_code,
                'coupon_used' => 0,
                'coupon_comments' => 'Created from voucher'
            ],
            [
                '%s', '%d', '%d', '%s', '%d', '%d', '%s'
            ]
        );
        
        if (!$inserted) {
            throw new Exception('Failed to insert coupon record: ' . $this->db->last_error);
        }
        
        $this->db->query('COMMIT');
        return true;
        
    } catch (Exception $e) {
        $this->db->query('ROLLBACK');
        error_log('Coupon creation for voucher failed: ' . $e->getMessage());
        throw $e;
    }
}
    
    /**
     * Generate unique voucher code
     */
    public function generate_unique_voucher_code() {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // Removed similar looking characters I, O
        $numbers = '23456789'; // Removed similar looking numbers 0, 1
        
        do {
            $code = '';
            // Generate 3 letters
            for ($i = 0; $i < 3; $i++) {
                $code .= $characters[rand(0, strlen($characters) - 1)];
            }
            // Generate 3 numbers
            for ($i = 0; $i < 3; $i++) {
                $code .= $numbers[rand(0, strlen($numbers) - 1)];
            }
            
            // Check if code already exists
            $exists = $this->db->get_var($this->db->prepare(
                "SELECT COUNT(*) FROM {$this->db->prefix}wevoi_coupons WHERE coupon_code = %s",
                $code
            ));
            
        } while ($exists > 0);
        
        return $code;
    }
}