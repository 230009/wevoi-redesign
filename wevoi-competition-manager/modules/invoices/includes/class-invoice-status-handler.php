<?php
/**
 * Invoice Status Handler Class
 *
 * @package WeVoi_Competition_Manager
 * @subpackage Invoice
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Invoice Status Handler class.
 */
class Wevoi_Invoice_Status_Handler {
    /**
     * Invoice instance.
     *
     * @var Wevoi_Invoice
     */
    private $invoice;

    /**
     * Initialize the class.
     */
    public function __construct() {
        $this->invoice = new Wevoi_Invoice();
    }

    /**
     * Update invoice status.
     *
     * @param int $invoice_id
     * @param string $status
     * @param array $payment_data
     * @return bool|WP_Error
     */
    public function update_status($invoice_id, $status, $payment_data = array()) {
        // Get current invoice
        $invoice_data = $this->invoice->get($invoice_id);
        
        if (!$invoice_data) {
            return new WP_Error('not_found', __('Invoice not found', 'wevoi-competition-manager'));
        }
        
        // Validate status transition
        $valid_transition = $this->validate_status_transition($invoice_data->status, $status);
        
        if (is_wp_error($valid_transition)) {
            return $valid_transition;
        }
        
        // Update invoice status
        $result = $this->invoice->update_status($invoice_id, $status, $payment_data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Additional actions based on new status
        if ($status === 'SENT') {
            $this->handle_sent_status($invoice_id);
        } elseif ($status === 'PAID') {
            $this->handle_paid_status($invoice_id, $payment_data);
        }
        
        return true;
    }

    /**
     * Validate status transition.
     *
     * @param string $current_status
     * @param string $new_status
     * @return bool|WP_Error
     */
    private function validate_status_transition($current_status, $new_status) {
        // Define allowed transitions
        $allowed_transitions = array(
            'PENDING'   => array('SENT', 'CANCELLED'),
            'SENT'      => array('PAID', 'CANCELLED'),
            'PAID'      => array('CANCELLED'),
            'CANCELLED' => array()
        );
        
        // Check if transition is allowed
        if (!isset($allowed_transitions[$current_status]) || !in_array($new_status, $allowed_transitions[$current_status])) {
            return new WP_Error(
                'invalid_transition',
                sprintf(
                    __('Cannot change status from %s to %s', 'wevoi-competition-manager'),
                    $current_status,
                    $new_status
                )
            );
        }
        
        return true;
    }

    /**
     * Handle sent status actions.
     *
     * @param int $invoice_id
     */
    private function handle_sent_status($invoice_id) {
        // Send invoice email logic can be added here
        // This will be handled by the PDF generator class
    }

    /**
     * Handle paid status actions.
     *
     * @param int $invoice_id
     * @param array $payment_data
     */
    private function handle_paid_status($invoice_id, $payment_data) {
        // Get invoice data
        $invoice_data = $this->invoice->get($invoice_id);
        
        if (!$invoice_data) {
            return;
        }
        
        // You can add additional logic here, like:
        // - Send confirmation email
        // - Update coupon stats
        // - Log payment in transactions
        
        // Example: Send confirmation email
        $this->send_payment_confirmation($invoice_data, $payment_data);
    }

    /**
     * Send payment confirmation email.
     *
     * @param object $invoice
     * @param array $payment_data
     */
    private function send_payment_confirmation($invoice, $payment_data) {
        $to = $invoice->recipient_email;
        $subject = sprintf(__('Payment Confirmation for Invoice %s', 'wevoi-competition-manager'), $invoice->invoice_number);
        
        // Email body
        $message = sprintf(
            __('Dear %s,

Thank you for your payment of £%s for invoice number %s.

Payment Details:
- Method: %s
- Reference: %s
- Date: %s

This is an automated message. Please do not reply to this email.

Best regards,
WeVoi Team', 'wevoi-competition-manager'),
            $invoice->recipient_name,
            number_format($invoice->total_amount, 2),
            $invoice->invoice_number,
            isset($payment_data['payment_method']) ? $payment_data['payment_method'] : __('Not specified', 'wevoi-competition-manager'),
            isset($payment_data['payment_reference']) ? $payment_data['payment_reference'] : __('Not specified', 'wevoi-competition-manager'),
            isset($payment_data['payment_date']) ? date_i18n(get_option('date_format'), strtotime($payment_data['payment_date'])) : date_i18n(get_option('date_format'))
        );
        
        // Send email
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($to, $subject, nl2br($message), $headers);
    }
}