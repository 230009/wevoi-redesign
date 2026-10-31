<?php
/**
 * Invoice AJAX Handlers
 *
 * @package WeVoi_Competition_Manager
 * @subpackage Invoice
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Invoice AJAX handler class.
 */
class Wevoi_Invoice_Ajax {
    /**
     * PDF Generator instance
     * 
     * @var Wevoi_Invoice_PDF_Generator
     */
    private $pdf_generator;
    
    /**
     * Invoice instance
     * 
     * @var Wevoi_Invoice
     */
    private $invoice;

    /**
     * Initialize the class.
     */
    public function __construct() {
        try {
            // Initialize PDF Generator if possible
            if (class_exists('Wevoi_Invoice_PDF_Generator')) {
                $this->pdf_generator = new Wevoi_Invoice_PDF_Generator();
            } else {
                error_log('WeVoi Invoice PDF Generator class not found');
            }
            
            // Initialize Invoice instance
            if (class_exists('Wevoi_Invoice')) {
                $this->invoice = new Wevoi_Invoice();
            } else {
                error_log('WeVoi Invoice class not found');
            }
            
            // Register AJAX handlers
            add_action('wp_ajax_preview_invoice', array($this, 'handle_preview_invoice'));
            add_action('wp_ajax_send_invoice', array($this, 'handle_send_invoice'));
            add_action('wp_ajax_update_invoice_status', array($this, 'handle_update_invoice_status'));
        } catch (Exception $e) {
            error_log('WeVoi Invoice AJAX initialization error: ' . $e->getMessage());
        }
    }

    /**
     * Handle preview invoice AJAX request
     */
    public function handle_preview_invoice() {
        // Verify nonce
        check_ajax_referer('wevoi_invoice_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'wevoi-competition-manager'));
            return;
        }
        
        try {
            // Check if PDF generator is available
            if (!isset($this->pdf_generator) || !is_object($this->pdf_generator)) {
                wp_send_json_error(__('PDF generation is not available. TCPDF library is missing.', 'wevoi-competition-manager'));
                return;
            }
            
            // Get form data
            $data = $_POST;
            
            // Generate preview
            $preview_result = $this->pdf_generator->generate_preview($data);
            
            // Check if result is an error
            if (is_wp_error($preview_result)) {
                wp_send_json_error($preview_result->get_error_message());
                return;
            }
            
            // Return success
            wp_send_json_success(array(
                'preview_url' => $preview_result
            ));
        } catch (Exception $e) {
            // Return error
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle send invoice AJAX request
     */
    public function handle_send_invoice() {
        // Verify nonce
        check_ajax_referer('wevoi_invoice_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'wevoi-competition-manager'));
            return;
        }
        
        try {
            // Check if Invoice and PDF generator are available
            if (!isset($this->invoice) || !is_object($this->invoice)) {
                wp_send_json_error(__('Invoice functionality is not available.', 'wevoi-competition-manager'));
                return;
            }
            
            // Get form data
            $data = $_POST;
            
            // Save invoice
            $invoice_id = $this->invoice->save($data);
            
            if (is_wp_error($invoice_id)) {
                throw new Exception($invoice_id->get_error_message());
            }
            
            // Check if PDF generator is available
            if (!isset($this->pdf_generator) || !is_object($this->pdf_generator)) {
                // Return a warning but don't fail completely
                wp_send_json_success(array(
                    'message' => __('Invoice saved but PDF generation is not available. TCPDF library is missing.', 'wevoi-competition-manager'),
                    'warning' => true
                ));
                return;
            }
            
            // Send invoice
            $status_handler = new Wevoi_Invoice_Status_Handler();
            
            // Check if PDF generation is available
            $pdf_result = $this->pdf_generator->generate_and_send($invoice_id);
            
            if (is_wp_error($pdf_result)) {
                // Update invoice status to PENDING instead of SENT
                $status_handler->update_status($invoice_id, 'PENDING');
                
                // Return a warning with PDF error but don't fail completely
                wp_send_json_success(array(
                    'message' => __('Invoice saved but could not generate PDF: ', 'wevoi-competition-manager') . $pdf_result->get_error_message(),
                    'warning' => true
                ));
                return;
            }
            
            // Update status to SENT
            $result = $status_handler->update_status($invoice_id, 'SENT');
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            // Return success
            wp_send_json_success(array(
                'message' => __('Invoice sent successfully', 'wevoi-competition-manager')
            ));
        } catch (Exception $e) {
            // Return error
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle update invoice status AJAX request
     */
    public function handle_update_invoice_status() {
        // Verify nonce
        check_ajax_referer('wevoi_invoice_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'wevoi-competition-manager'));
            return;
        }
        
        try {
            // Get data
            $invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
            $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
            $payment_data = isset($_POST['payment_data']) ? $_POST['payment_data'] : array();
            
            if (empty($invoice_id) || empty($status)) {
                throw new Exception(__('Invalid request data', 'wevoi-competition-manager'));
            }
            
            // Update status
            $status_handler = new Wevoi_Invoice_Status_Handler();
            $result = $status_handler->update_status($invoice_id, $status, $payment_data);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            // Return success
            wp_send_json_success(array(
                'message' => __('Invoice status updated successfully', 'wevoi-competition-manager')
            ));
        } catch (Exception $e) {
            // Return error
            wp_send_json_error($e->getMessage());
        }
    }
} 