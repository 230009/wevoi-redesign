<?php
/**
 * Voucher Service class for business logic
 */

if (!defined('ABSPATH')) {
    exit;
}

class WeVoi_Voucher_Service {
    private static $instance = null;
    private $repository;
    private $stripe_handler;
    private $logger;
    private $db;
    
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
        $this->repository = WeVoi_Voucher_Repository::instance();
        $this->stripe_handler = WeVoi_Register_Stripe_Handler::instance();
        $this->logger = WeVoi_Register_Logger::instance();
    }
    
    /**
     * Process voucher purchase
     */
    public function process_voucher_payment($data) {
        try {
            // Validate data
            $this->validate_voucher_data($data);
            
// Create payment intent with Stripe
$payment_intent = $this->stripe_handler->create_payment_intent($data['price'], [
    'competition_code' => $data['competition_code'],
    'customer_email' => $data['payer_email'],
    'receipt_email' => $data['payer_email'],
    'is_voucher' => true,
    'voucher_type' => $data['voucher_type']
]);
            
            if (is_wp_error($payment_intent)) {
                throw new Exception($payment_intent->get_error_message());
            }
            
            // Create customer in Stripe
            $customer_id = $this->stripe_handler->create_customer([
                'name' => $data['payer_name'],
                'email' => $data['payer_email'],
                'country_id' => $data['payer_countryid'],
                'country_code' => $data['country_code']
            ]);
            
            if (is_wp_error($customer_id)) {
                throw new Exception($customer_id->get_error_message());
            }
            
            // Add payment intent ID to data
            $data['payment_intent_id'] = $payment_intent->id;
            
            // Store voucher in database
            $voucher_id = $this->repository->create_voucher($data);
            
            return [
                'success' => true,
                'voucher_id' => $voucher_id,
                'client_secret' => $payment_intent->client_secret,
                'customer_id' => $customer_id
            ];
            
        } catch (Exception $e) {
            $this->logger->log_error('Voucher payment processing failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate voucher data
     */
    private function validate_voucher_data($data) {
        $required_fields = ['competition_code', 'payer_email', 'payer_name', 'payer_countryid', 'price', 'voucher_type'];
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
        
        // Validate email
        if (!is_email($data['payer_email'])) {
            throw new Exception('Invalid email address');
        }
        
        // Validate voucher type
        if (!in_array($data['voucher_type'], ['VOUCHER', 'AMOUNT'])) {
            throw new Exception('Invalid voucher type');
        }
        
        // Validate quantity for voucher type
        if ($data['voucher_type'] === 'VOUCHER' && (empty($data['quantity']) || $data['quantity'] <= 0)) {
            throw new Exception('Quantity must be greater than 0 for voucher type');
        }
        
        // Validate price
        if ($data['price'] <= 0) {
            throw new Exception('Price must be greater than 0');
        }
    }
    
/**
 * Complete voucher payment (called from webhook)
 */
public function complete_voucher_payment($payment_intent_id, $customer_id) {
    $transaction_started = false;

    try {
        $voucher = $this->repository->get_voucher_by_payment_intent($payment_intent_id);
        
        if (!$voucher) {
            throw new Exception('Voucher not found');
        }

        $existing_coupon = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->db->prefix}wevoi_coupons 
             WHERE coupon_comments LIKE %s OR coupon_code = %s",
            "%voucher id: {$voucher->id}%",
            $voucher->voucher_code ?? ''
        ));

        if ($existing_coupon && $voucher->paid_status === 'PAID') {
            return [
                'success' => true,
                'voucher' => $voucher,
                'message' => 'Payment completed, coupon already exists'
            ];
        }

        $this->db->query('START TRANSACTION');
        $transaction_started = true;

        $data = ['paid_status' => 'PAID'];
        $format = ['%s'];
        if ($customer_id) {
            $data['customer_code'] = $customer_id;
            $format[] = '%s';
        }

        $updated = $this->db->update(
            $this->db->prefix . 'wevoi_vouchers',
            $data,
            ['id' => $voucher->id],
            $format,
            ['%d']
        );

        if ($updated === false) {
            throw new Exception('Failed to update voucher payment status');
        }
        
        if ($voucher->voucher_type === 'VOUCHER') {
            $voucher_code = $voucher->voucher_code;

            if (empty($voucher_code)) {
                $voucher_code = $this->repository->generate_unique_voucher_code();

                $code_updated = $this->db->update(
                    $this->db->prefix . 'wevoi_vouchers',
                    ['voucher_code' => $voucher_code],
                    ['id' => $voucher->id],
                    ['%s'],
                    ['%d']
                );

                if ($code_updated === false) {
                    throw new Exception('Failed to update voucher code');
                }
            }
            
            $existing_voucher_code = $this->db->get_var($this->db->prepare(
                "SELECT COUNT(*) FROM {$this->db->prefix}wevoi_coupons 
                 WHERE coupon_code = %s",
                $voucher_code
            ));
            
            if (empty($existing_voucher_code)) {
                $inserted = $this->db->insert(
                    $this->db->prefix . 'wevoi_coupons',
                    [
                        'coupon_code' => $voucher_code,
                        'coupon_limit' => $voucher->quantity ?? 1,
                        'coupon_discount_percentage' => 100,
                        'coupon_discount_amount' => NULL,
                        'competition_code' => $voucher->competition_code,
                        'coupon_used' => 0,
                        'coupon_comments' => "Created from voucher id: {$voucher->id}"
                    ],
                    ['%s', '%d', '%d', '%s', '%d', '%d', '%s']
                );
                
                if (!$inserted) {
                    throw new Exception("Coupon insertion failed: " . $this->db->last_error);
                }
            }
        }
        
        $this->db->query('COMMIT');
        $transaction_started = false;

        // Send confirmation email from webhook path too
        try {
            $email_service = WeVoi_Voucher_Email_Service::instance();
            $email_service->send_voucher_confirmation_email($voucher->id);
        } catch (Exception $e) {
            $this->logger->log_error('Webhook: Failed to send confirmation email', [
                'error' => $e->getMessage(),
                'voucher_id' => $voucher->id
            ]);
        }

        try {
            $email_service = WeVoi_Voucher_Email_Service::instance();
            $email_service->send_admin_notification($voucher->id);
        } catch (Exception $e) {
            $this->logger->log_error('Webhook: Failed to send admin notification', [
                'error' => $e->getMessage(),
                'voucher_id' => $voucher->id
            ]);
        }
        
        return [
            'success' => true,
            'voucher' => $this->repository->get_voucher($voucher->id)
        ];
        
    } catch (Exception $e) {
        if ($transaction_started) {
            $this->db->query('ROLLBACK');
        }
        
        $this->logger->log_error('Voucher payment completion failed', [
            'error' => $e->getMessage(),
            'payment_intent_id' => $payment_intent_id
        ]);
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
}