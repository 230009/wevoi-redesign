<?php
/**
 * Voucher Email Service class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WeVoi_Voucher_Email_Service {
    private static $instance = null;
    private $repository;
    
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
        $this->repository = WeVoi_Voucher_Repository::instance();
        
        // Set email content type to HTML
        add_filter('wp_mail_content_type', function() {
            return 'text/html';
        });
    }
    
    /**
     * Send voucher confirmation email
     */
    public function send_voucher_confirmation_email($voucher_id) {
        // Get voucher details
        $voucher = $this->repository->get_voucher($voucher_id);
        if (!$voucher) {
            return false;
        }
        
        $subject = 'WeVoi Payment Confirmation';
        
        if ($voucher->voucher_type === 'VOUCHER') {
            $subject = 'WeVoi Voucher Confirmation';
        }
        
        $message = $this->get_email_template($voucher);
        
        return wp_mail($voucher->payer_email, $subject, $message);
    }
    
    /**
     * Get email template
     */
    private function get_email_template($voucher) {
        ob_start();
        
        // Get logo URL
        $logo_url = 'https://www.wevoi.org/wp-content/uploads/2018/08/WeVoi_Logo_XS-e1533120407306.png';
        
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>WeVoi Payment Confirmation</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333333; }
                .container { max-width: 600px; margin: 0 auto; }
                .header { background-color: #EFE8E8; padding: 20px; text-align: center; }
                .header img { max-width: 200px; height: auto; }
                .content { padding: 30px; background-color: #ffffff; }
                .voucher-box { 
                    background-color: #f8f9fa; 
                    border: 2px dashed #6c757d; 
                    padding: 20px; 
                    text-align: center; 
                    margin: 20px 0; 
                }
                .voucher-code { 
                    font-size: 24px; 
                    font-weight: bold; 
                    letter-spacing: 3px; 
                    color: #0066cc; 
                    margin: 10px 0; 
                }
                .payment-details { 
                    background-color: #f8f9fa; 
                    padding: 15px; 
                    margin: 20px 0; 
                }
                .footer { 
                    background-color: #f8f9fa; 
                    padding: 20px; 
                    text-align: center; 
                    font-size: 12px; 
                    color: #666; 
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <img src="<?php echo esc_url($logo_url); ?>" alt="WeVoi Logo">
                </div>
                <div class="content">
                    <h2>Dear <?php echo esc_html($voucher->payer_name); ?>,</h2>
                    
                    <p>Thank you for your payment. Here are your details:</p>
                    
                    <?php if ($voucher->voucher_type === 'VOUCHER' && !empty($voucher->voucher_code)): ?>
                        <div class="voucher-box">
                            <h3>Your Voucher Code</h3>
                            <p class="voucher-code"><?php echo esc_html($voucher->voucher_code); ?></p>
                            <p>This voucher is valid for <?php echo intval($voucher->quantity); ?> registration(s) for the competition:</p>
                            <p><strong><?php echo esc_html($voucher->competition_name); ?></strong></p>
                        </div>
                        <p>You can use this voucher code when registering for the competition or share it with others.</p>
                    <?php else: ?>
                        <p>Your payment for <strong><?php echo esc_html($voucher->competition_name); ?></strong> has been successfully processed.</p>
                    <?php endif; ?>
                    
                    <div class="payment-details">
                        <p><strong>Competition:</strong> <?php echo esc_html($voucher->competition_name); ?></p>
                        <p><strong>Amount:</strong> £<?php echo number_format($voucher->price, 2); ?></p>
                        <p><strong>Payment Date:</strong> <?php echo esc_html(date('d/m/Y H:i', strtotime($voucher->payment_date))); ?></p>
                        <p><strong>Payment Status:</strong> <?php echo esc_html($voucher->paid_status); ?></p>
                    </div>

                    <p>If you have any questions, feel free to contact us.</p>
                    
                    <p>Best regards,<br>WeVoi Team</p>
                </div>
                <div class="footer">
                    <p>This is an automated email. Please do not reply to this message.</p>
                    <p>&copy; <?php echo date('Y'); ?> WeVoi. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Send admin notification
     */
    public function send_admin_notification($voucher_id) {
        // Get voucher details
        $voucher = $this->repository->get_voucher($voucher_id);
        if (!$voucher) {
            return false;
        }
        
        $subject = 'New ' . ($voucher->voucher_type === 'VOUCHER' ? 'Voucher' : 'Payment') . ' - ' . $voucher->competition_name;
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>New Payment Notification</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #0066cc;">New <?php echo $voucher->voucher_type === 'VOUCHER' ? 'Voucher Purchase' : 'Payment'; ?></h2>
                
                <div style="background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 4px;">
                    <p><strong>Competition:</strong> <?php echo esc_html($voucher->competition_name); ?></p>
                    <p><strong>Payer:</strong> <?php echo esc_html($voucher->payer_name); ?></p>
                    <p><strong>Email:</strong> <?php echo esc_html($voucher->payer_email); ?></p>
                    <p><strong>Country:</strong> <?php echo esc_html($voucher->countryname); ?></p>
                    <p><strong>Amount:</strong> £<?php echo number_format($voucher->price, 2); ?></p>
                    <p><strong>Payment Date:</strong> <?php echo esc_html(date('d/m/Y H:i', strtotime($voucher->payment_date))); ?></p>
                    <p><strong>Payment Status:</strong> <?php echo esc_html($voucher->paid_status); ?></p>
                    
                    <?php if ($voucher->voucher_type === 'VOUCHER'): ?>
                        <p><strong>Voucher Code:</strong> <?php echo esc_html($voucher->voucher_code); ?></p>
                        <p><strong>Quantity:</strong> <?php echo intval($voucher->quantity); ?></p>
                    <?php endif; ?>
                </div>

                <p>This is an automated notification. Please check the admin panel for more details.</p>
            </div>
        </body>
        </html>
        <?php
        $message = ob_get_clean();
        
        return wp_mail('admin@wevoi.org', $subject, $message);
    }
}