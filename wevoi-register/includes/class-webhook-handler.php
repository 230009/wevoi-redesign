<?php
/**
 * Webhook handling for WeVoi Register
 */

if (!defined('ABSPATH')) {
    exit;
}

class WeVoi_Register_Webhook_Handler {
    private static $instance = null;
    private $stripe_handler;
    private $logger;
    private $email_handler;
    private $db;




    public static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }




    private function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->stripe_handler = WeVoi_Register_Stripe_Handler::instance();
        $this->logger = WeVoi_Register_Logger::instance();
        $this->email_handler = WeVoi_Register_Email_Handler::instance();

        add_action('rest_api_init', array($this, 'register_webhook_endpoint'));
    }





    public function register_webhook_endpoint() {
        register_rest_route('wevoi-register/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true'
        ));
    }

  public function handle_webhook($request) {
    try {
        $payload = $request->get_body();
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        if (empty($sig_header)) {
            $this->logger->log_error('Missing signature header', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return new WP_Error('invalid_webhook', 'Missing Stripe signature header', ['status' => 400]);
        }

        if (empty($payload)) {
            $this->logger->log_error('Empty webhook payload');
            return new WP_Error('invalid_webhook', 'Empty payload', ['status' => 400]);
        }

        $this->db->query('START TRANSACTION');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                $this->stripe_handler->get_webhook_secret()
            );
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            $this->logger->log_error('Webhook signature verification failed', [
                'error' => $e->getMessage()
            ]);
            return new WP_Error('invalid_signature', $e->getMessage(), ['status' => 400]);
        }

        // WeVoi ödemesi veya voucher ödemesi mi kontrol et
        $isWeVoiPayment = ($event->data->object->statement_descriptor === 'WEVOI Competition');
        $isVoucherPayment = isset($event->data->object->metadata->is_voucher) &&
                          $event->data->object->metadata->is_voucher === 'true';

        if (!$isWeVoiPayment && !$isVoucherPayment) {
            $this->db->query('COMMIT');
            return new WP_REST_Response([
                'status' => 'skipped',
                'message' => 'Not a WeVoi payment',
                'event_id' => $event->id
            ], 200);
        }

        if ($this->isEventProcessed($event->id)) {
            $this->db->query('COMMIT');
            return new WP_REST_Response([
                'status' => 'skipped',
                'message' => 'Event already processed',
                'event_id' => $event->id
            ], 200);
        }

        // Payment intent ID ile kayıt kontrolü
        $payment_intent_id = $event->data->object->id;

        // Önce normal kayıtlarda kontrol et
        $registration = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->db->prefix}wevoi_registrations
            WHERE payment_intent_id = %s",
            $payment_intent_id
        ));

        // Normal kayıt bulunamadıysa ve voucher ödeme ise voucher tablosunda kontrol et
        if (!$registration && $isVoucherPayment) {
            $voucher = $this->db->get_row($this->db->prepare(
                "SELECT * FROM {$this->db->prefix}wevoi_vouchers
                WHERE payment_intent_id = %s",
                $payment_intent_id
            ));

            // Voucher ödemesi ise ve voucher modülü yüklü ise
            if ($voucher && class_exists('WeVoi_Voucher_Service')) {
                // Voucher servisini kullanarak ödemeyi tamamla
                $voucher_service = WeVoi_Voucher_Service::instance();
                $result = $voucher_service->complete_voucher_payment($payment_intent_id, $event->data->object->customer);

                $this->db->insert(
                    $this->db->prefix . 'wevoi_stripe_events',
                    [
                        'event_id' => $event->id,
                        'processed_at' => current_time('mysql')
                    ],
                    ['%s', '%s']
                );

                $this->db->query('COMMIT');
                return new WP_REST_Response([
                    'status' => 'success',
                    'message' => 'Voucher payment processed successfully',
                    'event_type' => $event->type,
                    'event_id' => $event->id
                ], 200);
            }
        }

        // Eğer hiçbir kayıt bulunamadıysa
        if (!$registration && !($isVoucherPayment && isset($voucher))) {
            $this->db->query('COMMIT');
            return new WP_REST_Response([
                'status' => 'skipped',
                'message' => 'Registration or voucher not found',
                'event_id' => $event->id
            ], 200);
        }

        // Normal kayıtlar için mevcut işlemi devam ettir
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->stripe_handler->handle_successful_payment($event->data->object);
                break;

            case 'payment_intent.payment_failed':
                $this->stripe_handler->handle_failed_payment($event->data->object);
                break;

            case 'charge.refunded':
                $this->stripe_handler->handle_refund($event->data->object);
                break;

            default:
                $this->logger->log_webhook('unhandled', [
                    'type' => $event->type,
                    'id' => $event->id
                ]);
                break;
        }

        $this->db->insert(
            $this->db->prefix . 'wevoi_stripe_events',
            [
                'event_id' => $event->id,
                'processed_at' => current_time('mysql')
            ],
            ['%s', '%s']
        );

        $this->db->query('COMMIT');

        return new WP_REST_Response([
            'status' => 'success',
            'message' => 'Webhook processed successfully',
            'event_type' => $event->type,
            'event_id' => $event->id
        ], 200);

    } catch (Exception $e) {
        $this->db->query('ROLLBACK');

        $this->logger->log_error('Webhook processing failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return new WP_Error(
            'webhook_processing_error',
            $e->getMessage(),
            ['status' => 500]
        );
    }
}



    private function isEventProcessed($event_id) {
        return $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM {$this->db->prefix}wevoi_stripe_events
            WHERE event_id = %s AND last_error IS NULL",
            $event_id
        )) > 0;
    }

    public static function get_webhook_url() {
        $site_url = wp_parse_url(get_site_url());
        $path = '/wp-json/wevoi-register/v1/webhook';
        $scheme = is_ssl() ? 'https' : 'http';
        return $scheme . '://' . $site_url['host'] . $path;
    }


    /**
     * Verify webhook signature
     */
    private function verify_webhook($payload, $sig_header) {
        try {
            return \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                $this->stripe_handler->get_webhook_secret()
            );
        } catch (Exception $e) {
            $this->logger->log_error('Webhook verification failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Process webhook event
     */
private function process_webhook_event($event) {
    $this->logger->log_webhook($event->type, $event->data->object);

    switch ($event->type) {
        case 'payment_intent.succeeded':
            $this->handle_successful_payment($event->data->object);
            break;

        case 'payment_intent.payment_failed':
            $this->handle_failed_payment($event->data->object);
            break;

        case 'charge.refunded':  // Yeni event tipi
            $this->handle_refund($event->data->object);
            break;

        default:
            // Log unhandled event type
            $this->logger->log_webhook('unhandled', [
                'type' => $event->type,
                'id' => $event->id
            ]);
            break;
    }
}


    /**
     * Handle successful payment
     */
    private function handle_successful_payment($payment_intent) {
    try {
        $this->db->query('START TRANSACTION');

        // Tüm kayıtları bul
        $registrations = $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->db->prefix}wevoi_registrations
            WHERE payment_intent_id = %s",
            $payment_intent->id
        ));

        if (empty($registrations)) {
            throw new Exception('No registrations found');
        }

        // Tüm kayıtları güncelle
        foreach ($registrations as $registration) {
            // Check if this is a Forum registration
            $competition = $this->db->get_row($this->db->prepare(
                "SELECT competition_type FROM {$this->db->prefix}wevoi_competitions
                 WHERE competition_code = %d",
                $registration->competition_code
            ));

            $is_forum = ($competition && $competition->competition_type === 'Forum');

            // Forum kayıtları için application_status direkt APPLICATION_ACCEPTED olarak ayarlanır
            $update_data = [
                'paid_status' => 'PAID',
                'customer_code' => $payment_intent->customer
            ];

            if ($is_forum) {
                $update_data['application_status'] = 'APPLICATION_ACCEPTED';
            }

            $updated = $this->db->update(
                $this->db->prefix . 'wevoi_registrations',
                $update_data,
                ['register_id' => $registration->register_id],
                $is_forum ? ['%s', '%s', '%s'] : ['%s', '%s'],
                ['%d']
            );

            if ($updated === false) {
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

        // Ödemeleri logla
        $this->logger->log_payment([
            'payment_id' => $payment_intent->id,
            'registration_count' => count($registrations),
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

    /**
     * Handle failed payment
     */
    private function handle_failed_payment($payment_intent) {
        try {
            $this->db->query('START TRANSACTION');

            // Get registration from metadata
            $registration_id = $payment_intent->metadata->registration_id ?? null;
            if (!$registration_id) {
                throw new Exception('Registration ID not found in payment metadata');
            }

            // Update registration status
            $this->db->update(
                $this->db->prefix . 'wevoi_registrations',
                ['paid_status' => 'FAILED'],
                ['register_id' => $registration_id],
                ['%s'],
                ['%d']
            );

            // Log the failed event
            $this->db->insert(
                $this->db->prefix . 'wevoi_stripe_events',
                [
                    'event_id' => $payment_intent->id,
                    'processed_at' => current_time('mysql'),
                    'last_error' => $payment_intent->last_payment_error ?
                        $payment_intent->last_payment_error->message :
                        'Payment failed'
                ]
            );

            $this->db->query('COMMIT');

            // Log failure
            $this->logger->log_payment([
                'payment_id' => $payment_intent->id,
                'registration_id' => $registration_id,
                'amount' => $payment_intent->amount / 100,
                'status' => 'failed',
                'error' => $payment_intent->last_payment_error ?
                    $payment_intent->last_payment_error->message :
                    'Unknown error'
            ]);

        } catch (Exception $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Handle refund
     */
// class-webhook-handler.php içinde refund işlemini handle edelim

private function handle_refund($charge) {
    try {
        $this->db->query('START TRANSACTION');

        // Payment intent'e göre registration'ı bul
        $registration = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->db->prefix}wevoi_registrations
            WHERE payment_intent_id = %s",
            $charge->payment_intent
        ));

        if ($registration) {
            // Registration statusunu ve refund bilgilerini güncelle
            $this->db->update(
                $this->db->prefix . 'wevoi_registrations',
                [
                    'paid_status' => 'REFUNDED',
                    'refund_amount' => $charge->amount_refunded / 100, // Stripe kuruş olarak tutuyor
                    'refund_date' => current_time('mysql')
                ],
                ['register_id' => $registration->register_id],
                ['%s', '%f', '%s'],
                ['%d']
            );
        }

        // Stripe event'i logla
        $this->db->insert(
            $this->db->prefix . 'wevoi_stripe_events',
            [
                'event_id' => $charge->id,
                'processed_at' => current_time('mysql'),
                'last_error' => json_encode([
                    'type' => 'refund',
                    'amount' => $charge->amount_refunded / 100,
                    'registration_id' => $registration ? $registration->register_id : null
                ])
            ]
        );

        $this->db->query('COMMIT');

    } catch (Exception $e) {
        $this->db->query('ROLLBACK');
        throw $e;
    }
}



}
