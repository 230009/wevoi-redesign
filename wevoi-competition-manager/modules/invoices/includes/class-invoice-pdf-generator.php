<?php
/**
 * Invoice PDF Generator Class
 *
 * @package WeVoi_Competition_Manager
 * @subpackage Invoice
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Invoice PDF Generator class.
 */
class Wevoi_Invoice_PDF_Generator {
    /**
     * Invoice instance.
     *
     * @var Wevoi_Invoice
     */
    private $invoice;
    
    /**
     * Whether TCPDF is available.
     *
     * @var bool
     */
    private $tcpdf_available = false;

    /**
     * Initialize the class.
     */
    public function __construct() {
        $this->invoice = new Wevoi_Invoice();
        
        // Try to include TCPDF library if not already included
        if (!class_exists('TCPDF')) {
            $plugins_dir = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : plugin_dir_path(dirname(dirname(dirname(__FILE__))));
            $plugin_root = plugin_dir_path(dirname(dirname(dirname(__FILE__))));
            $tcpdf_paths = array(
                // Shared vendor: wp-content/plugins/vendor
                $plugins_dir . '/vendor/tecnickcom/tcpdf/tcpdf.php',
                // Plugin's own vendor
                $plugin_root . 'vendor/tecnickcom/tcpdf/tcpdf.php',
                ABSPATH . 'wp-includes/tcpdf/tcpdf.php',
                WEVOI_INVOICE_PATH . 'lib/tcpdf/tcpdf.php',
                $plugins_dir . '/tcpdf/tcpdf.php'
            );
            
            foreach ($tcpdf_paths as $path) {
                if (file_exists($path)) {
                    require_once($path);
                    $this->tcpdf_available = true;
                    break;
                }
            }
            
            // If TCPDF still not available, create directory structure to download it later
            if (!$this->tcpdf_available) {
                $this->create_tcpdf_directory();
                
                // Only log error if we're in debug mode to avoid spam
                // Commented out to prevent log spam
                // if (defined('WP_DEBUG') && WP_DEBUG) {
                //     error_log('TCPDF library not found. PDF generation is unavailable. Please install TCPDF in ' . WEVOI_INVOICE_PATH . 'lib/tcpdf/');
                // }
            }
        } else {
            $this->tcpdf_available = true;
        }
    }
    
    /**
     * Create directory structure for TCPDF
     */
    private function create_tcpdf_directory() {
        $tcpdf_dir = WEVOI_INVOICE_PATH . 'lib/tcpdf';
        
        if (!file_exists($tcpdf_dir)) {
            wp_mkdir_p($tcpdf_dir);
            
            // Create a placeholder readme file with instructions
            $readme_content = "TCPDF Library Missing\n\n";
            $readme_content .= "To enable PDF functionality, please download and install TCPDF:\n\n";
            $readme_content .= "1. Download TCPDF from https://github.com/tecnickcom/TCPDF/releases\n";
            $readme_content .= "2. Extract the contents to this directory\n";
            $readme_content .= "3. Ensure that tcpdf.php is directly in this directory\n";
            
            file_put_contents($tcpdf_dir . '/README.txt', $readme_content);
        }
    }

    /**
     * Generate preview from form data.
     *
     * @param array $data Form data
     * @return string|WP_Error URL to preview file or error object
     */
    public function generate_preview($data) {
        // Check if TCPDF is available
        if (!$this->tcpdf_available) {
            return new WP_Error('tcpdf_missing', __('PDF generation is not available. TCPDF library is missing.', 'wevoi-competition-manager'));
        }
        
        // Generate preview PDF
        $pdf = $this->create_pdf($data);
        
        // Save to temporary file
        $upload_dir = wp_upload_dir();
        $filename = 'invoice-preview-' . time() . '.pdf';
        $filepath = $upload_dir['basedir'] . '/wevoi-invoices/' . $filename;
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir['basedir'] . '/wevoi-invoices/')) {
            wp_mkdir_p($upload_dir['basedir'] . '/wevoi-invoices/');
        }
        
        // Save file
        $pdf->Output($filepath, 'F');
        
        // Return URL to file
        return $upload_dir['baseurl'] . '/wevoi-invoices/' . $filename;
    }

    /**
     * Generate and send invoice.
     *
     * @param int $invoice_id
     * @return bool|WP_Error
     */
    public function generate_and_send($invoice_id) {
        // Check if TCPDF is available
        if (!$this->tcpdf_available) {
            return new WP_Error('tcpdf_missing', __('PDF generation is not available. TCPDF library is missing.', 'wevoi-competition-manager'));
        }
        
        // Get invoice data
        $invoice_data = $this->invoice->get($invoice_id);
        
        if (!$invoice_data) {
            return new WP_Error('not_found', __('Invoice not found', 'wevoi-competition-manager'));
        }
        
        // Generate PDF
        $pdf = $this->create_pdf_from_invoice($invoice_data);
        
        // Save to file
        $upload_dir = wp_upload_dir();
        $filename = 'invoice-' . $invoice_data->invoice_number . '.pdf';
        $filepath = $upload_dir['basedir'] . '/wevoi-invoices/' . $filename;
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir['basedir'] . '/wevoi-invoices/')) {
            wp_mkdir_p($upload_dir['basedir'] . '/wevoi-invoices/');
        }
        
        // Save file
        $pdf->Output($filepath, 'F');
        
        // Send email with attachment
        $result = $this->send_invoice_email($invoice_data, $filepath);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Update status to SENT
        $this->invoice->update_status($invoice_id, 'SENT');
        
        return true;
    }

    /**
     * Create PDF from form data.
     *
     * @param array $data Form data
     * @return TCPDF
     */
    private function create_pdf($data) {
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('WeVoi Competition Manager');
        $pdf->SetAuthor('WeVoi');
        $pdf->SetTitle('Invoice');
        $pdf->SetSubject('WeVoi Invoice');
        
        // Remove header and footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        
        // Set margins
        $pdf->SetMargins(15, 15, 15);
        
        // Set auto page breaks
        $pdf->SetAutoPageBreak(true, 15);
        
        // Set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        
        // Add a page
        $pdf->AddPage();
        
        // Get logo path
        $logo_path = WEVOI_INVOICE_PATH . 'assets/images/logo.png';
        
        // Calculate values
        $quantity = isset($data['quantity']) ? intval($data['quantity']) : 0;
        $unit_price = isset($data['unit_price']) ? floatval($data['unit_price']) : 0;
        $total = $quantity * $unit_price;
        
        // Get current date in Y-m-d format
        $invoice_date = date('Y-m-d');
        
        // HTML content
        $html = '
        <table border="0" cellpadding="0" cellspacing="0" style="width: 100%;">
            <tr>
                <td style="width: 60%;">
                    <img src="' . $logo_path . '" alt="WeVoi Logo" style="width: 150px;">
                    <br><br>
                    <b>WeVoi</b><br>
                    124 City Road, London, EC1V 2NX<br>
                    United Kingdom<br>
                    Email: info@wevoi.com<br>
                    Website: www.wevoi.com
                </td>
                <td style="width: 40%; text-align: right;">
                    <h1>INVOICE</h1>
                    <br>
                    <table border="0" cellpadding="2" cellspacing="0">
                        <tr>
                            <td><b>Invoice Number:</b></td>
                            <td>INV-' . date('Ymd') . '-PREVIEW</td>
                        </tr>
                        <tr>
                            <td><b>Date:</b></td>
                            <td>' . $invoice_date . '</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <br><br>
        <table border="0" cellpadding="5" cellspacing="0" style="width: 100%;">
            <tr>
                <td style="width: 50%; background-color: #f5f5f5; padding: 10px;">
                    <b>Bill To:</b><br>
                    ' . (isset($data['recipient_name']) ? $data['recipient_name'] : 'Recipient Name') . '<br>
                    ' . (isset($data['recipient_email']) ? $data['recipient_email'] : 'recipient@example.com') . '
                </td>
                <td style="width: 50%; background-color: #f5f5f5; padding: 10px;">
                    <b>Payment Details:</b><br>
                    Account Name: WeVoi Ltd<br>
                    Bank: Example Bank<br>
                    Account Number: 12345678<br>
                    Sort Code: 12-34-56<br>
                    IBAN: GB00EXMP12345678
                </td>
            </tr>
        </table>
        <br><br>
        <table border="0" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
            <tr style="background-color: #333; color: white;">
                <th style="text-align: left; border: 1px solid #ddd;">Description</th>
                <th style="text-align: center; border: 1px solid #ddd;">Quantity</th>
                <th style="text-align: right; border: 1px solid #ddd;">Unit Price</th>
                <th style="text-align: right; border: 1px solid #ddd;">Total</th>
            </tr>
            <tr>
                <td style="border: 1px solid #ddd;">' . (isset($data['competition_name']) ? $data['competition_name'] : 'Competition') . ' Coupon (' . (isset($data['coupon_code']) ? $data['coupon_code'] : 'COUPON') . ')</td>
                <td style="text-align: center; border: 1px solid #ddd;">' . $quantity . '</td>
                <td style="text-align: right; border: 1px solid #ddd;">£' . number_format($unit_price, 2) . '</td>
                <td style="text-align: right; border: 1px solid #ddd;">£' . number_format($total, 2) . '</td>
            </tr>
            <tr>
                <td colspan="2" style="border: 1px solid #ddd;"></td>
                <td style="text-align: right; border: 1px solid #ddd;"><b>Total:</b></td>
                <td style="text-align: right; border: 1px solid #ddd;">£' . number_format($total, 2) . '</td>
            </tr>
        </table>
        <br><br>
        <div style="background-color: #f5f5f5; padding: 10px;">
            <b>Notes:</b><br>
            ' . (isset($data['notes']) ? nl2br($data['notes']) : 'Thank you for your business.') . '
        </div>
        <br><br>
        <div style="text-align: center; font-size: 12px; color: #777;">
            This is a computer-generated invoice and does not require a signature.
        </div>
        ';
        
        // Output HTML content
        $pdf->writeHTML($html, true, false, true, false, '');
        
        return $pdf;
    }

    /**
     * Create PDF from invoice data.
     *
     * @param object $invoice_data
     * @return TCPDF
     */
    private function create_pdf_from_invoice($invoice_data) {
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('WeVoi Competition Manager');
        $pdf->SetAuthor('WeVoi');
        $pdf->SetTitle('Invoice ' . $invoice_data->invoice_number);
        $pdf->SetSubject('WeVoi Invoice');
        
        // Remove header and footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        
        // Set margins
        $pdf->SetMargins(15, 15, 15);
        
        // Set auto page breaks
        $pdf->SetAutoPageBreak(true, 15);
        
        // Set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        
        // Add a page
        $pdf->AddPage();
        
        // Get logo path
        $logo_path = WEVOI_INVOICE_PATH . 'assets/images/logo.png';
        
        // Get invoice date
        $invoice_date = date('Y-m-d', strtotime($invoice_data->created_date));
        
        // HTML content
        $html = '
        <table border="0" cellpadding="0" cellspacing="0" style="width: 100%;">
            <tr>
                <td style="width: 60%;">
                    <img src="' . $logo_path . '" alt="WeVoi Logo" style="width: 150px;">
                    <br><br>
                    <b>WeVoi</b><br>
                    124 City Road, London, EC1V 2NX<br>
                    United Kingdom<br>
                    Email: info@wevoi.com<br>
                    Website: www.wevoi.com
                </td>
                <td style="width: 40%; text-align: right;">
                    <h1>INVOICE</h1>
                    <br>
                    <table border="0" cellpadding="2" cellspacing="0">
                        <tr>
                            <td><b>Invoice Number:</b></td>
                            <td>' . $invoice_data->invoice_number . '</td>
                        </tr>
                        <tr>
                            <td><b>Date:</b></td>
                            <td>' . $invoice_date . '</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <br><br>
        <table border="0" cellpadding="5" cellspacing="0" style="width: 100%;">
            <tr>
                <td style="width: 50%; background-color: #f5f5f5; padding: 10px;">
                    <b>Bill To:</b><br>
                    ' . $invoice_data->recipient_name . '<br>
                    ' . $invoice_data->recipient_email . '
                </td>
                <td style="width: 50%; background-color: #f5f5f5; padding: 10px;">
                    <b>Payment Details:</b><br>
                    Account Name: WeVoi Ltd<br>
                    Bank: Example Bank<br>
                    Account Number: 12345678<br>
                    Sort Code: 12-34-56<br>
                    IBAN: GB00EXMP12345678
                </td>
            </tr>
        </table>
        <br><br>
        <table border="0" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse;">
            <tr style="background-color: #333; color: white;">
                <th style="text-align: left; border: 1px solid #ddd;">Description</th>
                <th style="text-align: center; border: 1px solid #ddd;">Quantity</th>
                <th style="text-align: right; border: 1px solid #ddd;">Unit Price</th>
                <th style="text-align: right; border: 1px solid #ddd;">Total</th>
            </tr>
            <tr>
                <td style="border: 1px solid #ddd;">' . $invoice_data->competition_name . ' Coupon (' . $invoice_data->coupon_code . ')</td>
                <td style="text-align: center; border: 1px solid #ddd;">' . $invoice_data->quantity . '</td>
                <td style="text-align: right; border: 1px solid #ddd;">£' . number_format($invoice_data->unit_price, 2) . '</td>
                <td style="text-align: right; border: 1px solid #ddd;">£' . number_format($invoice_data->total_amount, 2) . '</td>
            </tr>
            <tr>
                <td colspan="2" style="border: 1px solid #ddd;"></td>
                <td style="text-align: right; border: 1px solid #ddd;"><b>Total:</b></td>
                <td style="text-align: right; border: 1px solid #ddd;">£' . number_format($invoice_data->total_amount, 2) . '</td>
            </tr>
        </table>
        <br><br>
        <div style="background-color: #f5f5f5; padding: 10px;">
            <b>Notes:</b><br>
            ' . nl2br($invoice_data->notes ? $invoice_data->notes : 'Thank you for your business.') . '
        </div>
        <br><br>
        <div style="text-align: center; font-size: 12px; color: #777;">
            This is a computer-generated invoice and does not require a signature.
        </div>
        ';
        
        // Output HTML content
        $pdf->writeHTML($html, true, false, true, false, '');
        
        return $pdf;
    }

    /**
     * Send invoice email with attachment.
     *
     * @param object $invoice_data
     * @param string $attachment_path
     * @return bool|WP_Error
     */
    private function send_invoice_email($invoice_data, $attachment_path) {
        $to = $invoice_data->recipient_email;
        $subject = sprintf(__('Invoice %s from WeVoi', 'wevoi-competition-manager'), $invoice_data->invoice_number);
        
        // Get email template
        ob_start();
        include(WEVOI_INVOICE_PATH . 'templates/email-template.php');
        $message = ob_get_clean();
        
        // Replace variables in template
        $placeholders = array(
            '{recipient_name}' => $invoice_data->recipient_name,
            '{invoice_number}' => $invoice_data->invoice_number,
            '{invoice_date}' => date('Y-m-d', strtotime($invoice_data->created_date)),
            '{total_amount}' => '£' . number_format($invoice_data->total_amount, 2),
            '{competition_name}' => $invoice_data->competition_name,
            '{coupon_code}' => $invoice_data->coupon_code
        );
        
        $message = str_replace(array_keys($placeholders), array_values($placeholders), $message);
        
        // Headers
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        // Attachments
        $attachments = array($attachment_path);
        
        // Send email
        $sent = wp_mail($to, $subject, $message, $headers, $attachments);
        
        if (!$sent) {
            return new WP_Error('email_failed', __('Failed to send email', 'wevoi-competition-manager'));
        }
        
        return true;
    }
}