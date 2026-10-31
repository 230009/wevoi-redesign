<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Process messages
$message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
$messages = array(
    'created' => __('Invoice created successfully.', 'wevoi-competition-manager'),
    'updated' => __('Invoice updated successfully.', 'wevoi-competition-manager'),
    'deleted' => __('Invoice(s) deleted successfully.', 'wevoi-competition-manager'),
    'sent'    => __('Invoice sent successfully.', 'wevoi-competition-manager'),
    'error'   => __('An error occurred.', 'wevoi-competition-manager')
);

// Get the invoice list table
require_once WEVOI_INVOICE_PATH . 'includes/class-invoice-list-table.php';
$invoices_table = new Wevoi_Invoice_List_Table();
$invoices_table->prepare_items();
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Invoices', 'wevoi-competition-manager'); ?></h1>
    <a href="<?php echo esc_url(add_query_arg(array('action' => 'create'), admin_url('admin.php?page=wevoi-competitions&tab=invoices'))); ?>" class="page-title-action"><?php _e('Add New Invoice', 'wevoi-competition-manager'); ?></a>
    <hr class="wp-header-end">
    
    <?php if (isset($messages[$message])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($messages[$message]); ?></p>
        </div>
    <?php endif; ?>
    
    <form id="invoices-filter" method="get">
        <input type="hidden" name="page" value="wevoi-competitions">
        <input type="hidden" name="tab" value="invoices">
        
        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="status">
                    <option value=""<?php selected('', isset($_GET['status']) ? $_GET['status'] : ''); ?>><?php _e('All Statuses', 'wevoi-competition-manager'); ?></option>
                    <option value="PENDING"<?php selected('PENDING', isset($_GET['status']) ? $_GET['status'] : ''); ?>><?php _e('Pending', 'wevoi-competition-manager'); ?></option>
                    <option value="SENT"<?php selected('SENT', isset($_GET['status']) ? $_GET['status'] : ''); ?>><?php _e('Sent', 'wevoi-competition-manager'); ?></option>
                    <option value="PAID"<?php selected('PAID', isset($_GET['status']) ? $_GET['status'] : ''); ?>><?php _e('Paid', 'wevoi-competition-manager'); ?></option>
                    <option value="CANCELLED"<?php selected('CANCELLED', isset($_GET['status']) ? $_GET['status'] : ''); ?>><?php _e('Cancelled', 'wevoi-competition-manager'); ?></option>
                </select>
                
                <?php submit_button(__('Filter', 'wevoi-competition-manager'), 'action', false, false); ?>
            </div>
            
            <div class="alignright">
                <p class="search-box">
                    <label class="screen-reader-text" for="invoice-search-input"><?php _e('Search Invoices', 'wevoi-competition-manager'); ?>:</label>
                    <input type="search" id="invoice-search-input" name="s" value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>">
                    <?php submit_button(__('Search', 'wevoi-competition-manager'), 'action', false, false, array('id' => 'search-submit')); ?>
                </p>
            </div>
        </div>
        
        <?php $invoices_table->display(); ?>
    </form>

    <!-- Payment Modal -->
    <div id="payment-modal" class="invoice-modal" style="display:none;">
        <div class="invoice-modal-content">
            <div class="invoice-modal-header">
                <h2><?php _e('Mark Invoice as Paid', 'wevoi-competition-manager'); ?></h2>
                <span class="invoice-modal-close">&times;</span>
            </div>
            <div class="invoice-modal-body">
                <form id="payment-form">
                    <input type="hidden" id="invoice_id" name="invoice_id" value="">
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