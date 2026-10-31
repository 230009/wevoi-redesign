<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get invoice ID from URL
$invoice_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$invoice_id) {
    echo '<div class="notice notice-error"><p>' . __('Invoice ID is required.', 'wevoi-competition-manager') . '</p></div>';
    return;
}

// Get invoice data
$invoice_obj = new Wevoi_Invoice();
$invoice = $invoice_obj->get($invoice_id);

if (!$invoice) {
    echo '<div class="notice notice-error"><p>' . __('Invoice not found.', 'wevoi-competition-manager') . '</p></div>';
    return;
}

// Format dates
$created_date = date_i18n(get_option('date_format'), strtotime($invoice->created_date));
$sent_date = $invoice->sent_date ? date_i18n(get_option('date_format'), strtotime($invoice->sent_date)) : '-';
$payment_date = $invoice->payment_date ? date_i18n(get_option('date_format'), strtotime($invoice->payment_date)) : '-';

// Get status class
$status_classes = array(
    'PENDING'   => 'status-pending',
    'SENT'      => 'status-sent',
    'PAID'      => 'status-paid',
    'CANCELLED' => 'status-cancelled'
);
$status_class = isset($status_classes[$invoice->status]) ? $status_classes[$invoice->status] : '';
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html(sprintf(__('Invoice %s', 'wevoi-competition-manager'), $invoice->invoice_number)); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=wevoi-competitions&tab=invoices')); ?>" class="page-title-action"><?php _e('Back to Invoices', 'wevoi-competition-manager'); ?></a>
    <hr class="wp-header-end">
    
    <div class="invoice-detail-container">
        <div class="invoice-detail-header">
            <h2><?php _e('Invoice Information', 'wevoi-competition-manager'); ?></h2>
            
            <div class="invoice-status-container">
                <span class="invoice-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($invoice->status); ?></span>
                
                <?php if ($invoice->status === 'PENDING'): ?>
                    <button type="button" id="detail-send-invoice" class="button button-primary" data-id="<?php echo esc_attr($invoice_id); ?>"><?php _e('Send Invoice', 'wevoi-competition-manager'); ?></button>
                <?php endif; ?>
                
                <?php if ($invoice->status === 'SENT'): ?>
                    <button type="button" id="detail-mark-paid" class="button button-primary" data-id="<?php echo esc_attr($invoice_id); ?>"><?php _e('Mark as Paid', 'wevoi-competition-manager'); ?></button>
                <?php endif; ?>
                
                <a href="<?php echo esc_url(admin_url('admin.php?page=wevoi-competitions&tab=invoices&action=edit&id=' . $invoice_id)); ?>" class="button button-secondary"><?php _e('Edit Invoice', 'wevoi-competition-manager'); ?></a>
            </div>
        </div>
        
        <div class="invoice-detail-columns">
            <div class="invoice-detail-column">
                <div class="invoice-detail-section">
                    <h3><?php _e('General Information', 'wevoi-competition-manager'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Invoice Number', 'wevoi-competition-manager'); ?></th>
                            <td><?php echo esc_html($invoice->invoice_number); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Created Date', 'wevoi-competition-manager'); ?></th>
                            <td><?php echo esc_html($created_date); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Sent Date', 'wevoi-competition-manager'); ?></th>
                            <td><?php echo esc_html($sent_date); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Status', 'wevoi-competition-manager'); ?></th>
                            <td><span class="invoice-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($invoice->status); ?></span></td>
                        </tr>
                    </table>
                </div>
                
                <div class="invoice-detail-section">
                    <h3><?php _e('Recipient Information', 'wevoi-competition-manager'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Recipient Name', 'wevoi-competition-manager'); ?></th>
                            <td><?php echo esc_html($invoice->recipient_name); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Recipient Email', 'wevoi-competition-manager'); ?></th>
                            <td><a href="mailto:<?php echo esc_attr($invoice->recipient_email); ?>"><?php echo esc_html($invoice->recipient_email); ?></a></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="invoice-detail-column">
                <div class="invoice-detail-section">
                    <h3><?php _e('Coupon Information', 'wevoi-competition-manager'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Coupon Code', 'wevoi-competition-manager'); ?></th>
                            <td><?php echo esc_html($invoice->coupon_code); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Competition', 'wevoi-competition-manager'); ?></th>
                            <td><?php echo esc_html($invoice->competition_name); ?></td>
                        </tr>
                    </table>
                </div>
                
                <div class="invoice-detail-section">
                    <h3><?php _e('Payment Information', 'wevoi-competition-manager'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Unit Price', 'wevoi-competition-manager'); ?></th>
                            <td>£<?php echo esc_html(number_format($invoice->unit_price, 2)); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Quantity', 'wevoi-competition-manager'); ?></th>
                            <td><?php echo esc_html($invoice->quantity); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Total Amount', 'wevoi-competition-manager'); ?></th>
                            <td><strong>£<?php echo esc_html(number_format($invoice->total_amount, 2)); ?></strong></td>
                        </tr>
                        
                        <?php if ($invoice->status === 'PAID'): ?>
                        <tr>
                            <th scope="row"><?php _e('Payment Method', 'wevoi-competition-manager'); ?></th>
                            <td><?php echo esc_html($invoice->payment_method); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Payment Reference', 'wevoi-competition-manager'); ?></th>
                            <td><?php echo esc_html($invoice->payment_reference); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Payment Date', 'wevoi-competition-manager'); ?></th>
                            <td><?php echo esc_html($payment_date); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="invoice-detail-section">
            <h3><?php _e('Notes', 'wevoi-competition-manager'); ?></h3>
            <div class="invoice-notes">
                <?php echo !empty($invoice->notes) ? wpautop(esc_html($invoice->notes)) : __('No notes', 'wevoi-competition-manager'); ?>
            </div>
        </div>
        
        <div class="invoice-detail-actions">
            <?php if ($invoice->status === 'PENDING' || $invoice->status === 'SENT'): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wevoi-competitions&tab=invoices&action=edit&id=' . $invoice_id)); ?>" class="button button-secondary"><?php _e('Edit Invoice', 'wevoi-competition-manager'); ?></a>
            <?php endif; ?>
            
            <a href="<?php echo esc_url(admin_url('admin.php?page=wevoi-competitions&tab=invoices')); ?>" class="button button-secondary"><?php _e('Back to Invoices', 'wevoi-competition-manager'); ?></a>
        </div>
    </div>
    
    <!-- Payment Modal -->
    <div id="payment-modal" class="invoice-modal" style="display:none;">
        <div class="invoice-modal-content">
            <div class="invoice-modal-header">
                <h2><?php _e('Mark Invoice as Paid', 'wevoi-competition-manager'); ?></h2>
                <span class="invoice-modal-close">&times;</span>
            </div>
            <div class="invoice-modal-body">
                <form id="payment-form">
                    <input type="hidden" id="invoice_id" name="invoice_id" value="<?php echo esc_attr($invoice_id); ?>">
                    <?php wp_nonce_field('wevoi_invoice_nonce', 'payment_nonce'); ?>
                    
                    <div class="form-field">
                        <label for="payment_method"><?php _e('Payment Method', 'wevoi-competition-manager'); ?></label>
                        <select id="payment_method" name="payment_method" class="regular-text">
                            <option value="Bank Transfer"><?php _e('Bank Transfer', 'wevoi-competition-manager'); ?></option>
                            <option value="Credit Card"><?php _e('Credit Card', 'wevoi-competition-manager'); ?></option>
                            <option value="PayPal"><?php _e('PayPal', 'wevoi-competition-manager'); ?></option>
                            <option value="Other"><?php _e('Other', 'wevoi-competition-manager'); ?></option>
                        </select>
                    </div>
                    
                    <div class="form-field">
                        <label for="payment_reference"><?php _e('Payment Reference', 'wevoi-competition-manager'); ?></label>
                        <input type="text" id="payment_reference" name="payment_reference" class="regular-text">
                    </div>
                    
                    <div class="form-field">
                        <label for="payment_date"><?php _e('Payment Date', 'wevoi-competition-manager'); ?></label>
                        <input type="date" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" class="regular-text">
                    </div>
                </form>
            </div>
            <div class="invoice-modal-footer">
                <button type="button" id="confirm-payment" class="button button-primary"><?php _e('Confirm Payment', 'wevoi-competition-manager'); ?></button>
                <button type="button" class="button button-secondary invoice-modal-close"><?php _e('Cancel', 'wevoi-competition-manager'); ?></button>
            </div>
        </div>
    </div>
</div>