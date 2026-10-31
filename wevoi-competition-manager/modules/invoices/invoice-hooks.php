<?php
/**
 * Invoice Hooks
 * 
 * @package WeVoi_Competition_Manager
 * @subpackage Invoice
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function wevoi_add_invoice_button_to_coupon_actions($actions, $item) {
    error_log('Add Invoice Button - Coupon Item: ' . print_r($item, true));

    // Aktif bir competition_code olup olmadığını kontrol et
    if (!empty($item->competition_code)) {
        $invoice_url = add_query_arg(
            array(
                'page'      => 'wevoi-competitions',
                'tab'       => 'invoices',
                'action'    => 'create',
                'coupon_id' => $item->id
            ),
            admin_url('admin.php')
        );

        error_log('Invoice Button URL: ' . $invoice_url);

        $actions['invoice'] = sprintf(
            '<a href="%s" class="button button-small">%s</a>',
            $invoice_url,
            __('Invoice', 'wevoi-competition-manager')
        );
    } else {
        error_log('No competition_code for this coupon');
    }

    return $actions;
}
add_filter('wevoi_coupon_actions', 'wevoi_add_invoice_button_to_coupon_actions', 10, 2);


/**
 * Add tab to WeVoi Competition Manager
 */
function wevoi_add_invoice_tab($tabs) {
    $tabs['invoices'] = __('Invoices', 'wevoi-competition-manager');
    return $tabs;
}
add_filter('wevoi_competition_tabs', 'wevoi_add_invoice_tab');

/**
 * Handle invoice tab content
 */
function wevoi_handle_invoice_tab_content() {
    if (isset($_GET['tab']) && $_GET['tab'] === 'invoices') {
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        
        switch ($action) {
            case 'create':
                require_once WEVOI_INVOICE_PATH . 'views/invoice-form.php';
                break;
                
            case 'edit':
                require_once WEVOI_INVOICE_PATH . 'views/invoice-form.php';
                break;
                
            case 'view':
                require_once WEVOI_INVOICE_PATH . 'views/invoice-detail.php';
                break;
                
            case 'list':
            default:
                require_once WEVOI_INVOICE_PATH . 'views/invoice-list.php';
                break;
        }
    }
}