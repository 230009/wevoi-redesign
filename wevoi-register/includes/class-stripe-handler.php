<?php
/**
 * Stripe payment handling for WeVoi Register
 */

if (!defined('ABSPATH')) {
    exit;
}

use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\Exception\ApiErrorException;

class WeVoi_Register_Stripe_Handler {
    private static $instance = null;
    private $encryption_handler;
    private $publishable_key;
    private $secret_key;
    private $webhook_secret;
    private $is_test_mode;
    private $db;
    private $logger;

    public static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->encryption_handler = WeVoi_Register_Encryption_Handler::instance();
        $this->logger = WeVoi_Register_Logger::instance();
        $this->init_stripe();
    }

    private function init_stripe() {
        $this->is_test_mode = get_option('wevoi_register_stripe_mode', 'test') === 'test';

        $key_prefix = $this->is_test_mode ? 'test' : 'live';
        $encrypted_publishable_key = get_option("wevoi_register_{$key_prefix}_publishable_key");
        $encrypted_secret_key = get_option("wevoi_register_{$key_prefix}_secret_key");
        $encrypted_webhook_secret = get_option("wevoi_register_{$key_prefix}_webhook_secret");

        // Decrypt keys only if they exist
        if (!empty($encrypted_publishable_key)) {
            $this->publishable_key = $this->encryption_handler->decrypt($encrypted_publishable_key);
        }

        if (!empty($encrypted_secret_key)) {
            $this->secret_key = $this->encryption_handler->decrypt($encrypted_secret_key);
        }

        if (!empty($encrypted_webhook_secret)) {
            $this->webhook_secret = $this->encryption_handler->decrypt($encrypted_webhook_secret);
        }

        // Set Stripe API key if available
        if (!empty($this->secret_key)) {
            try {
                Stripe::setApiKey($this->secret_key);
            } catch (Exception $e) {
                $this->logger->log_error('Failed to set Stripe API key', [
                    'error' => $e->getMessage(),
                    'mode' => $this->is_test_mode ? 'test' : 'live'
                ]);
            }
        }
    }

    public function get_publishable_key() {
        return $this->publishable_key;
    }

    public function get_webhook_secret() {
        if (empty($this->webhook_secret)) {
            throw new Exception('Webhook secret is not configured');
        }
        return $this->webhook_secret;
    }

    public function create_payment_intent($amount, $metadata = []) {
    try {
        // Validate amount
        if ($amount <= 0) {
            throw new Exception('Payment amount must be greater than zero');
        }

        // Determine the description based on metadata
        $description = 'WeVoi Competition Registration';

        if (isset($metadata['is_voucher']) && $metadata['is_voucher'] === true) {
            $description = 'WeVoi Competition Voucher';
        } else if (isset($metadata['is_bulk']) && $metadata['is_bulk'] === true) {
            $description = 'WeVoi Competition Bulk Registration';
        } else if (isset($metadata['competition_name'])) {
            $description = $metadata['competition_name'];
        }

        // Debug logging
        error_log('WeVoi Stripe: Description set to: ' . $description);
        error_log('WeVoi Stripe: Metadata: ' . print_r($metadata, true));

        $payment_intent = PaymentIntent::create([
            'amount' => round($amount * 100), // Convert to cents
            'currency' => 'gbp',
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            'metadata' => $metadata,
            'description' => $description,
            'statement_descriptor' => 'WEVOI Competition',
            'receipt_email' => $metadata['customer_email'] ?? null
        ]);

        $this->logger->log_info('Payment intent created', [
            'payment_intent_id' => $payment_intent->id,
            'amount' => $amount,
            'metadata' => $metadata
        ]);

        return $payment_intent;
    } catch (\Exception $e) {
        $this->logger->log_error('Payment intent creation failed', [
            'error' => $e->getMessage(),
            'amount' => $amount,
            'metadata' => $metadata
        ]);
        return new \WP_Error('stripe_error', $e->getMessage());
    }
}

    public function create_customer($customer_data) {
        try {
            // Validate required fields
            if (empty($customer_data['email']) || empty($customer_data['name'])) {
                throw new Exception('Customer email and name are required');
            }

            $customer = Customer::create([
                'name' => $customer_data['name'],
                'email' => $customer_data['email'],
                'metadata' => [
                    'country_id' => $customer_data['country_id'],
                    'country_code' => $customer_data['country_code'] ?? ''
                ]
            ]);

            $this->logger->log_info('Customer created successfully', [
                'customer_id' => $customer->id,
                'email' => $customer_data['email']
            ]);

            return $customer->id;
        } catch (ApiErrorException $e) {
            $this->logger->log_error('Customer creation failed', [
                'error' => $e->getMessage(),
                'customer_data' => $customer_data
            ]);
            return new WP_Error('stripe_error', $e->getMessage());
        } catch (Exception $e) {
            $this->logger->log_error('Customer creation failed', [
                'error' => $e->getMessage()
            ]);
            return new WP_Error('stripe_error', $e->getMessage());
        }
    }

public function handle_successful_payment($payment_intent) {
    try {
        $this->db->query('START TRANSACTION');

        // Aynı payment_intent_id'ye sahip tüm kayıtları çek
        $registrations = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->db->prefix}wevoi_registrations
            WHERE payment_intent_id = %s",
            $payment_intent->id
        ));

        if (empty($registrations)) {
            $this->db->query('ROLLBACK');
            return false;
        }

        // Her bir kaydı güncelle
        foreach ($registrations as $registration) {
            $updated = $this->db->update(
                $this->db->prefix . 'wevoi_registrations',
                [
                    'paid_status' => 'PAID',
                    'customer_code' => $payment_intent->customer
                ],
                ['register_id' => $registration->register_id],
                ['%s', '%s'],
                ['%d']
            );

            if ($updated === false) {
                $this->logger->log_error('Failed to update registration', [
                    'register_id' => $registration->register_id,
                    'payment_intent_id' => $payment_intent->id
                ]);
                throw new Exception('Failed to update registration status');
            }
        }

        // Stripe event'i logla
        $this->db->insert(
            $this->db->prefix . 'wevoi_stripe_events',
            [
                'event_id' => $payment_intent->id,
                'processed_at' => current_time('mysql'),
                'last_error' => null
            ],
            ['%s', '%s', '%s']
        );

        // Ödemeyi logla
        $this->logger->log_payment([
            'payment_id' => $payment_intent->id,
            'registration_count' => count($registrations), // Kaç kayıt güncellendiğini log'a ekleyelim
            'amount' => $payment_intent->amount / 100,
            'status' => 'success'
        ]);

        $this->db->query('COMMIT');
        return true;

    } catch (Exception $e) {
        $this->db->query('ROLLBACK');
        $this->logger->log_error('Payment processing failed', [
            'error' => $e->getMessage(),
            'payment_intent_id' => $payment_intent->id
        ]);
        return false;
    }
}



    public function handle_failed_payment($payment_intent) {
        try {
            $this->db->query('START TRANSACTION');

            $registration_id = $payment_intent->metadata->registration_id ?? null;
            if (!$registration_id) {
                throw new Exception('Registration ID not found in payment metadata');
            }

            // Update registration status
            $updated = $this->db->update(
                $this->db->prefix . 'wevoi_registrations',
                [
                    'paid_status' => 'FAILED',
                    'payment_intent_id' => $payment_intent->id
                ],
                ['register_id' => $registration_id],
                ['%s', '%s'],
                ['%d']
            );

            if ($updated === false) {
                throw new Exception('Failed to update registration status');
            }

            // Log the failed event
            $this->db->insert(
                $this->db->prefix . 'wevoi_stripe_events',
                [
                    'event_id' => $payment_intent->id,
                    'processed_at' => current_time('mysql'),
                    'last_error' => $payment_intent->last_payment_error ?
                        $payment_intent->last_payment_error->message :
                        'Payment failed'
                ],
                ['%s', '%s', '%s']
            );

            $this->logger->log_payment([
                'payment_id' => $payment_intent->id,
                'registration_id' => $registration_id,
                'amount' => $payment_intent->amount / 100,
                'status' => 'failed',
                'error' => $payment_intent->last_payment_error ?
                    $payment_intent->last_payment_error->message :
                    'Unknown error'
            ]);

            $this->db->query('COMMIT');
            return true;

        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
            $this->logger->log_error('Failed payment processing failed', [
                'error' => $e->getMessage(),
                'payment_intent_id' => $payment_intent->id
            ]);
            throw $e;
        }
    }

    public function handle_refund($charge) {
        try {
            $this->db->query('START TRANSACTION');

            // Find all registrations with this payment intent
            $registrations = $this->db->get_results($this->db->prepare(
                "SELECT * FROM {$this->db->prefix}wevoi_registrations
                WHERE payment_intent_id = %s",
                $charge->payment_intent
            ));

            if (!empty($registrations)) {
                // Determine refund status based on amounts
                $refund_status = ($charge->amount_refunded >= $charge->amount) ? 'FULLY_REFUNDED' : 'PARTIAL_REFUNDED';

                // Update all related registrations
                foreach ($registrations as $registration) {
                    $updated = $this->db->update(
                        $this->db->prefix . 'wevoi_registrations',
                        [
                            'paid_status' => $refund_status,
                            'refund_amount' => $charge->amount_refunded / 100,
                            'refund_date' => current_time('mysql')
                        ],
                        ['register_id' => $registration->register_id],
                        ['%s', '%f', '%s'],
                        ['%d']
                    );

                    if ($updated === false) {
                        throw new Exception('Failed to update registration refund status for ID: ' . $registration->register_id);
                    }
                }

                // Log the refund event
                $this->logger->log_info('Refund processed', [
                    'charge_id' => $charge->id,
                    'payment_intent_id' => $charge->payment_intent,
                    'refund_amount' => $charge->amount_refunded / 100,
                    'refund_status' => $refund_status,
                    'registration_count' => count($registrations)
                ]);
            }

            // Insert into stripe events table
            $this->db->insert(
                $this->db->prefix . 'wevoi_stripe_events',
                [
                    'event_id' => $charge->id,
                    'processed_at' => current_time('mysql'),
                    'last_error' => null
                ],
                ['%s', '%s', '%s']
            );

            $this->db->query('COMMIT');
            return true;

        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
            $this->logger->log_error('Refund processing failed', [
                'error' => $e->getMessage(),
                'charge_id' => $charge->id,
                'payment_intent_id' => $charge->payment_intent ?? null
            ]);
            throw $e;
        }
    }

    public function is_stripe_ready() {
        return !empty($this->publishable_key) && !empty($this->secret_key) && !empty($this->webhook_secret);
    }
}
