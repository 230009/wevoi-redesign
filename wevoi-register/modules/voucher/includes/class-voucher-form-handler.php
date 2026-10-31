<?php
/**
 * Voucher Form handling for WeVoi Register
 */

if (!defined('ABSPATH')) {
    exit;
}

class WeVoi_Voucher_Form_Handler {
    private static $instance = null;
    private $db;
    private $repository;
    private $service;
    private $email_service;
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
        $this->repository = WeVoi_Voucher_Repository::instance();
        $this->service = WeVoi_Voucher_Service::instance();
        $this->email_service = WeVoi_Voucher_Email_Service::instance();
        $this->logger = WeVoi_Register_Logger::instance();

        // Register shortcode
        add_shortcode('wevoi_voucher_form', array($this, 'render_form'));

        // Register AJAX actions
        add_action('wp_ajax_wevoi_process_voucher', array($this, 'process_voucher_form'));
        add_action('wp_ajax_nopriv_wevoi_process_voucher', array($this, 'process_voucher_form'));
        add_action('wp_ajax_wevoi_update_voucher_payment', array($this, 'update_voucher_payment'));
        add_action('wp_ajax_nopriv_wevoi_update_voucher_payment', array($this, 'update_voucher_payment'));
    }

    /**
     * Render voucher form shortcode
     */
    public function render_form() {
        $competitions = $this->get_active_competitions();
        if (empty($competitions)) {
            return '<p>No active competitions available.</p>';
        }

        // Get countries for the dropdown
        $countries = $this->db->get_results(
            "SELECT countryid, countryname
            FROM {$this->db->prefix}wevoi_countries
            ORDER BY countryname"
        );

        ob_start();
        include WEVOI_VOUCHER_DIR . 'templates/voucher-form.php';
        $form_html = ob_get_clean();

        $this->enqueue_assets();

        return $form_html;
    }

    /**
     * Get active competitions
     */
    private function get_active_competitions() {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT competition_code, competition_name, competition_date, competition_price
                FROM {$this->db->prefix}wevoi_competitions
                WHERE competition_status = %s
                ORDER BY competition_date",
                'ON AIR'
            )
        );
    }

public function process_voucher_form() {
    try {
        // Validate nonce
        check_ajax_referer('wevoi_voucher_nonce', 'nonce');

        // Get form data
        $voucher_type = isset($_POST['voucher_type']) ? sanitize_text_field($_POST['voucher_type']) : '';

        // Get competition code based on voucher type
        if ($voucher_type === 'VOUCHER') {
            $competition_code = isset($_POST['competition_code']) ? intval($_POST['competition_code']) : 0;
        } else {
            $competition_code = isset($_POST['amount_competition_code']) ? intval($_POST['amount_competition_code']) : 0;
        }

        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $payer_email = isset($_POST['payer_email']) ? sanitize_email($_POST['payer_email']) : '';
        $payer_name = isset($_POST['payer_name']) ? sanitize_text_field($_POST['payer_name']) : '';
        $payer_countryid = isset($_POST['payer_countryid']) ? intval($_POST['payer_countryid']) : 0;
        $payment_purpose = isset($_POST['payment_purpose']) ? sanitize_text_field($_POST['payment_purpose']) : '';

        // Validate competition
        if (empty($competition_code)) {
            throw new Exception('Please select a competition');
        }

        // Validate payer info
        if (empty($payer_email) || !is_email($payer_email)) {
            throw new Exception('Please enter a valid email address');
        }

        if (empty($payer_name)) {
            throw new Exception('Please enter your full name');
        }

        if (empty($payer_countryid)) {
            throw new Exception('Please select your country');
        }

        // Get country code
        $country = $this->db->get_row($this->db->prepare(
            "SELECT iso FROM {$this->db->prefix}wevoi_countries WHERE countryid = %d",
            $payer_countryid
        ));

        if (!$country) {
            throw new Exception('Invalid country selected');
        }

        // Calculate price based on voucher type
        $price = 0;

        if ($voucher_type === 'VOUCHER') {
            if (empty($quantity) || $quantity < 1) {
                throw new Exception('Please select a valid quantity');
            }

            // Get competition price
            $competition = $this->db->get_row($this->db->prepare(
                "SELECT competition_price FROM {$this->db->prefix}wevoi_competitions
                WHERE competition_code = %d AND competition_status = 'ON AIR'",
                $competition_code
            ));

            if (!$competition) {
                throw new Exception('Selected competition is not available');
            }

            $price = $competition->competition_price * $quantity;
        } else if ($voucher_type === 'AMOUNT') {
            if (empty($amount) || $amount <= 0) {
                throw new Exception('Please enter a valid amount');
            }

            if (empty($payment_purpose)) {
                throw new Exception('Please enter a payment purpose');
            }

            $price = $amount;
        } else {
            throw new Exception('Invalid voucher type');
        }

        // Process payment
        $result = $this->service->process_voucher_payment([
            'competition_code' => $competition_code,
            'voucher_type' => $voucher_type,
            'quantity' => $quantity,
            'price' => $price,
            'payer_email' => $payer_email,
            'payer_name' => $payer_name,
            'payer_countryid' => $payer_countryid,
            'country_code' => $country->iso,
            'payment_purpose' => $payment_purpose
        ]);

        if (!$result['success']) {
            throw new Exception($result['message']);
        }

        wp_send_json_success($result);

    } catch (Exception $e) {
        $this->logger->log_error('Voucher form processing failed', [
            'error' => $e->getMessage(),
            'post_data' => $_POST
        ]);

        wp_send_json_error($e->getMessage());
    }
}

public function update_voucher_payment() {
    $transaction_started = false;

    try {
        check_ajax_referer('wevoi_voucher_nonce', 'nonce');

        $payment_intent_id = isset($_POST['payment_intent_id']) ? sanitize_text_field($_POST['payment_intent_id']) : '';
        $customer_id = isset($_POST['customer_id']) ? sanitize_text_field($_POST['customer_id']) : '';
        $voucher_id = isset($_POST['voucher_id']) ? intval($_POST['voucher_id']) : 0;

        $this->logger->log_error('Debug: update_voucher_payment request', [
            'payment_intent_id' => $payment_intent_id,
            'customer_id' => $customer_id,
            'voucher_id' => $voucher_id,
            'full_post' => $_POST
        ]);

        if (empty($payment_intent_id)) {
            throw new Exception('Missing payment intent ID');
        }

        if (empty($customer_id)) {
            throw new Exception('Missing customer ID');
        }

        if (empty($voucher_id)) {
            throw new Exception('Missing voucher ID');
        }

        $voucher = $this->repository->get_voucher($voucher_id);
        if (!$voucher) {
            $voucher = $this->repository->get_voucher_by_payment_intent($payment_intent_id);

            if (!$voucher) {
                $this->logger->log_error('Voucher not found', [
                    'voucher_id' => $voucher_id,
                    'payment_intent_id' => $payment_intent_id
                ]);
                throw new Exception('Voucher not found');
            }

            $voucher_id = $voucher->id;
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
            ['id' => $voucher_id],
            $format,
            ['%d']
        );

        if ($updated === false) {
            throw new Exception('Failed to update voucher payment status');
        }

        if ($voucher->voucher_type === 'VOUCHER') {
            $voucher_code = $this->repository->generate_unique_voucher_code();

            $code_updated = $this->db->update(
                $this->db->prefix . 'wevoi_vouchers',
                ['voucher_code' => $voucher_code],
                ['id' => $voucher_id],
                ['%s'],
                ['%d']
            );

            if ($code_updated === false) {
                throw new Exception('Failed to update voucher code');
            }

            $price = $this->db->get_var($this->db->prepare(
                "SELECT competition_price FROM {$this->db->prefix}wevoi_competitions 
                WHERE competition_code = %d",
                $voucher->competition_code
            ));

            if (!$price) {
                throw new Exception('Competition not found or price not set');
            }

            $existing_coupon = $this->db->get_var($this->db->prepare(
                "SELECT id FROM {$this->db->prefix}wevoi_coupons WHERE coupon_code = %s",
                $voucher_code
            ));

            if (!$existing_coupon) {
                $inserted = $this->db->insert(
                    $this->db->prefix . 'wevoi_coupons',
                    [
                        'coupon_code' => $voucher_code,
                        'coupon_limit' => $voucher->quantity ?? 1,
                        'coupon_discount_percentage' => 100,
                        'coupon_discount_amount' => null,
                        'competition_code' => $voucher->competition_code,
                        'coupon_used' => 0,
                        'coupon_comments' => "Created from voucher id: {$voucher_id}"
                    ],
                    ['%s', '%d', '%d', '%s', '%d', '%d', '%s']
                );

                if (!$inserted) {
                    throw new Exception('Failed to create coupon: ' . $this->db->last_error);
                }
            }
        }

        $this->db->query('COMMIT');
        $transaction_started = false;

        $email_sent = false;
        try {
            $email_sent = $this->email_service->send_voucher_confirmation_email($voucher_id);
        } catch (Exception $e) {
            $this->logger->log_error('Failed to send confirmation email', [
                'error' => $e->getMessage(),
                'voucher_id' => $voucher_id
            ]);
        }

        $admin_notified = false;
        try {
            $admin_notified = $this->email_service->send_admin_notification($voucher_id);
        } catch (Exception $e) {
            $this->logger->log_error('Failed to send admin notification', [
                'error' => $e->getMessage(),
                'voucher_id' => $voucher_id
            ]);
        }

        $updated_voucher = $this->repository->get_voucher($voucher_id);

        wp_send_json_success([
            'message' => 'Payment processed successfully',
            'voucher_id' => $voucher_id,
            'voucher_code' => $updated_voucher->voucher_code ?? null,
            'voucher_type' => $updated_voucher->voucher_type,
            'email_sent' => $email_sent,
            'admin_notified' => $admin_notified
        ]);

    } catch (Exception $e) {
        if ($transaction_started) {
            $this->db->query('ROLLBACK');
        }

        $this->logger->log_error('Voucher payment update failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'post_data' => $_POST
        ]);

        wp_send_json_error([
            'message' => $e->getMessage(),
            'details' => 'An error occurred while processing your payment confirmation.'
        ]);
    }
}

    /**
     * Enqueue necessary scripts and styles
     */
    private function enqueue_assets() {
        // Enqueue Stripe.js
        wp_enqueue_script(
            'stripe-js',
            'https://js.stripe.com/v3/',
            array(),
            null,
            true
        );

        // Enqueue custom styles
        wp_enqueue_style(
            'wevoi-voucher-style',
            WEVOI_VOUCHER_URL . 'assets/css/voucher-form-styles.css',
            array(),
            WEVOI_VOUCHER_VERSION
        );

        // Enqueue custom script
        wp_enqueue_script(
            'wevoi-voucher-script',
            WEVOI_VOUCHER_URL . 'assets/js/voucher-form-handler.js',
            array('jquery', 'stripe-js'),
            WEVOI_VOUCHER_VERSION,
            true
        );

        // Localize script
        wp_localize_script(
            'wevoi-voucher-script',
            'wevoiVoucher',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wevoi_voucher_nonce'),
                'stripeKey' => WeVoi_Register_Stripe_Handler::instance()->get_publishable_key()
            )
        );
    }
}
