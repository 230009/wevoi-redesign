<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get coupon ID from URL or form data
$coupon_id = isset($_GET['coupon_id']) ? intval($_GET['coupon_id']) : 0;
$invoice_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get coupon data
global $wpdb;
$coupon = null;

if ($coupon_id) {
    $coupon = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT c.*, comp.competition_name, comp.competition_code FROM {$wpdb->prefix}wevoi_coupons c
            LEFT JOIN {$wpdb->prefix}wevoi_competitions comp ON c.competition_code = comp.competition_code
            WHERE c.id = %d",
            $coupon_id
        )
    );
}

// Get invoice data if editing
$invoice = null;
if ($invoice_id) {
    require_once WEVOI_INVOICE_PATH . 'includes/class-invoice.php';
    $invoice_obj = new Wevoi_Invoice();
    $invoice = $invoice_obj->get($invoice_id);
    
    // Get coupon data if not already set
    if (!$coupon_id && $invoice) {
        $coupon_id = $invoice->coupon_id;
        $coupon = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT c.*, comp.competition_name, comp.competition_code FROM {$wpdb->prefix}wevoi_coupons c
                LEFT JOIN {$wpdb->prefix}wevoi_competitions comp ON c.competition_code = comp.competition_code
                WHERE c.id = %d",
                $coupon_id
            )
        );
    }
}

error_log('Invoice Form - Coupon ID: ' . $coupon_id);
error_log('Invoice Form - Invoice ID: ' . $invoice_id);
error_log('Invoice Form - Coupon Data: ' . print_r($coupon, true));
error_log('Invoice Form - Invoice Data: ' . print_r($invoice, true));

// Check if coupon exists when creating a new invoice
if (!$coupon && !$invoice_id) {
    // Kupon seçme formu gösterelim
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php _e('Select Coupon for Invoice', 'wevoi-competition-manager'); ?></h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=wevoi-competitions&tab=invoices')); ?>" class="page-title-action"><?php _e('Back to Invoices', 'wevoi-competition-manager'); ?></a>
        <hr class="wp-header-end">
        
        <div class="notice notice-info">
            <p><?php _e('Please select a coupon to create an invoice for:', 'wevoi-competition-manager'); ?></p>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Coupon Code', 'wevoi-competition-manager'); ?></th>
                    <th><?php _e('Competition', 'wevoi-competition-manager'); ?></th>
                    <th><?php _e('Used', 'wevoi-competition-manager'); ?></th>
                    <th><?php _e('Actions', 'wevoi-competition-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Kuponları listele
                $coupons = $wpdb->get_results(
                    "SELECT c.*, comp.competition_name 
                    FROM {$wpdb->prefix}wevoi_coupons c
                    LEFT JOIN {$wpdb->prefix}wevoi_competitions comp ON c.competition_code = comp.competition_code
                    ORDER BY c.id DESC"
                );
                
                if ($coupons) {
                    foreach ($coupons as $coupon_item) {
                        ?>
                        <tr>
                            <td><?php echo esc_html($coupon_item->coupon_code); ?></td>
                            <td><?php echo esc_html($coupon_item->competition_name); ?></td>
                            <td><?php echo esc_html($coupon_item->coupon_used); ?></td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg(array('action' => 'create', 'coupon_id' => $coupon_item->id), admin_url('admin.php?page=wevoi-competitions&tab=invoices'))); ?>" class="button button-small"><?php _e('Create Invoice', 'wevoi-competition-manager'); ?></a>
                            </td>
                        </tr>
                        <?php
                    }
                } else {
                    ?>
                    <tr>
                        <td colspan="4"><?php _e('No coupons found.', 'wevoi-competition-manager'); ?></td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
    return;
}

// Check if invoice exists when editing
if (!$invoice && $invoice_id) {
    echo '<div class="notice notice-error"><p>' . __('Invoice not found.', 'wevoi-competition-manager') . '</p></div>';
    return;
}

// Determine form values
$recipient_name = $invoice ? $invoice->recipient_name : '';
$recipient_email = $invoice ? $invoice->recipient_email : '';
$unit_price = $invoice ? $invoice->unit_price : '';
$quantity = $invoice ? $invoice->quantity : ($coupon ? $coupon->coupon_used : 0);
$notes = $invoice ? $invoice->notes : '';

// Calculate total if possible
$total = 0;
if ($unit_price && $quantity) {
    $total = $unit_price * $quantity;
}

// Get page title
$page_title = $invoice_id ? __('Edit Invoice', 'wevoi-competition-manager') : __('Create Invoice', 'wevoi-competition-manager');
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html($page_title); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=wevoi-competitions&tab=invoices')); ?>" class="page-title-action"><?php _e('Back to Invoices', 'wevoi-competition-manager'); ?></a>
    <hr class="wp-header-end">
    
    <div class="invoice-form-container">
        <form id="invoice-form" method="post">
            <?php wp_nonce_field('wevoi_invoice_nonce', 'invoice_nonce'); ?>
            
            <?php if ($invoice_id): ?>
                <input type="hidden" name="invoice_id" value="<?php echo esc_attr($invoice_id); ?>">
            <?php endif; ?>
            
            <input type="hidden" name="coupon_id" value="<?php echo esc_attr($coupon_id); ?>">
            <input type="hidden" name="coupon_code" value="<?php echo esc_attr($coupon ? $coupon->coupon_code : ($invoice ? $invoice->coupon_code : '')); ?>">
            <input type="hidden" name="competition_id" value="<?php echo esc_attr($coupon ? $coupon->competition_code : ($invoice ? $invoice->competition_id : '')); ?>">
            <input type="hidden" name="competition_name" value="<?php echo esc_attr($coupon ? $coupon->competition_name : ($invoice ? $invoice->competition_name : '')); ?>">
            
            <div class="invoice-form-section">
                <h2><?php _e('Coupon Information', 'wevoi-competition-manager'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Coupon Code', 'wevoi-competition-manager'); ?></th>
                        <td>
                            <strong><?php echo esc_html($coupon ? $coupon->coupon_code : ($invoice ? $invoice->coupon_code : '')); ?></strong>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Competition', 'wevoi-competition-manager'); ?></th>
                        <td>
                            <?php echo esc_html($coupon ? $coupon->competition_name : ($invoice ? $invoice->competition_name : '')); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Used', 'wevoi-competition-manager'); ?></th>
                        <td>
                            <?php echo esc_html($coupon ? $coupon->coupon_used : ($invoice ? $invoice->quantity : '0')); ?>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="invoice-form-section">
                <h2><?php _e('Recipient Information', 'wevoi-competition-manager'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="recipient_name"><?php _e('Recipient Name', 'wevoi-competition-manager'); ?> <span class="required">*</span></label></th>
                        <td>
                            <input type="text" id="recipient_name" name="recipient_name" value="<?php echo esc_attr($recipient_name); ?>" class="regular-text" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="recipient_email"><?php _e('Recipient Email', 'wevoi-competition-manager'); ?> <span class="required">*</span></label></th>
                        <td>
                            <input type="email" id="recipient_email" name="recipient_email" value="<?php echo esc_attr($recipient_email); ?>" class="regular-text" required>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="invoice-form-section">
                <h2><?php _e('Invoice Details', 'wevoi-competition-manager'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="unit_price"><?php _e('Unit Price (£)', 'wevoi-competition-manager'); ?> <span class="required">*</span></label></th>
                        <td>
                            <input type="number" id="unit_price" name="unit_price" value="<?php echo esc_attr($unit_price); ?>" class="regular-text" step="0.01" min="0" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="quantity"><?php _e('Quantity', 'wevoi-competition-manager'); ?> <span class="required">*</span></label></th>
                        <td>
                            <input type="number" id="quantity" name="quantity" value="<?php echo esc_attr($quantity); ?>" class="regular-text" min="1" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Total Amount (£)', 'wevoi-competition-manager'); ?></th>
                        <td>
                            <span id="total_amount" class="large-text"><?php echo esc_html(number_format($total, 2)); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="notes"><?php _e('Notes', 'wevoi-competition-manager'); ?></label></th>
                        <td>
                            <textarea id="notes" name="notes" class="large-text" rows="5"><?php echo esc_textarea($notes); ?></textarea>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="invoice-form-actions">
                <button type="button" id="preview-invoice" class="button button-secondary"><?php _e('Preview Invoice', 'wevoi-competition-manager'); ?></button>
                <button type="button" id="send-invoice" class="button button-primary"><?php _e('Send Invoice', 'wevoi-competition-manager'); ?></button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wevoi-competitions&tab=invoices')); ?>" class="button button-secondary"><?php _e('Cancel', 'wevoi-competition-manager'); ?></a>
                <span class="spinner"></span>
            </div>
        </form>
    </div>
    
    <!-- Preview Modal -->
    <div id="preview-modal" class="invoice-modal" style="display:none;">
        <div class="invoice-modal-content">
            <div class="invoice-modal-header">
                <h2><?php _e('Invoice Preview', 'wevoi-competition-manager'); ?></h2>
                <span class="invoice-modal-close">&times;</span>
            </div>
            <div class="invoice-modal-body">
                <iframe id="preview-frame" src="" width="100%" height="600px"></iframe>
            </div>
            <div class="invoice-modal-footer">
                <button type="button" id="confirm-send" class="button button-primary"><?php _e('Send Invoice', 'wevoi-competition-manager'); ?></button>
                <button type="button" class="button button-secondary invoice-modal-close"><?php _e('Close', 'wevoi-competition-manager'); ?></button>
            </div>
        </div>
    </div>
</div>