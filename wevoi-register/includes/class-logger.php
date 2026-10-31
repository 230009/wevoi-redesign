<?php
/**
 * Logging functionality for WeVoi Register
 */

if (!defined('ABSPATH')) {
    exit;
}

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class WeVoi_Register_Logger {
    private static $instance = null;
    private $logger;
    private $db;

    const LOG_DIRECTORY = 'logs';
    const MAX_FILES = 30; // Keep logs for 30 days

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
        $this->init_logger();
    }

    /**
     * Initialize logger
     */
    private function init_logger() {
        try {
            // Create logs directory if it doesn't exist
            $log_dir = $this->get_log_directory();
            if (!file_exists($log_dir)) {
                mkdir($log_dir, 0755, true);
            }

            // Create logger instance
            $this->logger = new Logger('wevoi_register');

            // Custom format for logs
            $output_format = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
            $date_format = "Y-m-d H:i:s";
            $formatter = new LineFormatter($output_format, $date_format);

            // Add rotating file handler
            $rotating_handler = new RotatingFileHandler(
                $log_dir . '/wevoi-register.log',
                self::MAX_FILES,
                Logger::DEBUG
            );
            $rotating_handler->setFormatter($formatter);
            $this->logger->pushHandler($rotating_handler);

            // Add stream handler for immediate output
            $stream_handler = new StreamHandler('php://stderr', Logger::DEBUG);
            $stream_handler->setFormatter($formatter);
            $this->logger->pushHandler($stream_handler);

        } catch (Exception $e) {
            error_log('WeVoi Register Logger initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Get log directory path
     */
    private function get_log_directory() {
        return WEVOI_REGISTER_PLUGIN_DIR . self::LOG_DIRECTORY;
    }

    /**
     * Log payment event
     */
    public function log_payment($data) {
        try {
            $context = [
                'payment_id' => $data['payment_id'] ?? '',
                'amount' => $data['amount'] ?? 0,
                'status' => $data['status'] ?? '',
                'registration_id' => $data['registration_id'] ?? '',
                'user_id' => $data['user_id'] ?? '',
                'is_bulk' => isset($data['registration_ids']), // Yeni eklenen
                'registration_count' => isset($data['registration_ids']) ?
                count($data['registration_ids']) : 1,  // Yeni eklenen
            ];

            $this->logger->info('Payment processed', $context);
            $this->save_to_db('payment', $context);

        } catch (Exception $e) {
            error_log('WeVoi Register payment logging failed: ' . $e->getMessage());
        }
    }


// Yeni log fonksiyonu eklenecek:
public function log_bulk_payment($data) {
    try {
        $context = [
            'payment_id' => $data['payment_id'] ?? '',
            'amount' => $data['amount'] ?? 0,
            'status' => $data['status'] ?? '',
            'registration_ids' => $data['registration_ids'] ?? [],
            'supervisor_id' => $data['supervisor_id'] ?? '',
            'registration_count' => count($data['registration_ids'] ?? [])
        ];

        $this->logger->info('Bulk payment processed', $context);
        $this->save_to_db('bulk_payment', $context);

    } catch (Exception $e) {
        error_log('WeVoi Register bulk payment logging failed: ' . $e->getMessage());
    }
}


    /**
     * Log registration event
     */
    public function log_registration($data) {
        try {
            $context = [
                'registration_id' => $data['registration_id'] ?? '',
                'user_id' => $data['user_id'] ?? '',
                'competition_code' => $data['competition_code'] ?? '',
                'status' => $data['status'] ?? '',
                'coupon_code' => $data['coupon_code'] ?? ''
            ];

            $this->logger->info('Registration processed', $context);
            $this->save_to_db('registration', $context);

        } catch (Exception $e) {
            error_log('WeVoi Register registration logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Log info event
     */
    public function log_info($message, $context = []) {
        try {
            $this->logger->info($message, $context);
            $this->save_to_db('info', ['message' => $message, 'context' => json_encode($context)]);

        } catch (Exception $e) {
            error_log('WeVoi Register info logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Log error event
     */
    public function log_error($message, $context = []) {
        try {
            $this->logger->error($message, $context);
            $this->save_to_db('error', ['message' => $message, 'context' => json_encode($context)]);

        } catch (Exception $e) {
            error_log('WeVoi Register error logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Log webhook event
     */
    public function log_webhook($event_type, $data) {
        try {
            $context = [
                'event_type' => $event_type,
                'event_id' => $data['id'] ?? '',
                'status' => $data['status'] ?? '',
                'customer_id' => $data['customer'] ?? ''
            ];

            $this->logger->info('Webhook received', $context);
            $this->save_to_db('webhook', $context);

        } catch (Exception $e) {
            error_log('WeVoi Register webhook logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Save log to database
     */
    private function save_to_db($type, $data) {
        try {
            $this->db->insert(
                $this->db->prefix . 'wevoi_stripe_events',
                [
                    'event_id' => $data['event_id'] ?? uniqid('log_'),
                    'processed_at' => current_time('mysql'),
                    'last_error' => json_encode([
                        'type' => $type,
                        'data' => $data
                    ])
                ],
                ['%s', '%s', '%s']
            );
        } catch (Exception $e) {
            error_log('WeVoi Register database logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Get logs for admin panel
     */
    public function get_logs($limit = 100, $offset = 0, $type = null) {
        $query = "SELECT * FROM {$this->db->prefix}wevoi_stripe_events";

        if ($type) {
            $query .= $this->db->prepare(" WHERE last_error LIKE %s", '%"type":"' . $type . '"%');
        }

        $query .= " ORDER BY processed_at DESC LIMIT %d OFFSET %d";

        return $this->db->get_results(
            $this->db->prepare($query, $limit, $offset)
        );
    }

    /**
     * Clear old logs
     */
    public function clear_old_logs($days = 30) {
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $this->db->query(
            $this->db->prepare(
                "DELETE FROM {$this->db->prefix}wevoi_stripe_events WHERE processed_at < %s",
                $date
            )
        );
    }
}
