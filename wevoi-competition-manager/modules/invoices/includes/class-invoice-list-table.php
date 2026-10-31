<?php
/**
 * Invoice List Table Class
 *
 * @package WeVoi_Competition_Manager
 * @subpackage Invoice
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Load WP_List_Table if not loaded
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Invoice List Table class.
 */
class Wevoi_Invoice_List_Table extends WP_List_Table {
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
        parent::__construct(array(
            'singular' => 'invoice',
            'plural'   => 'invoices',
            'ajax'     => false
        ));
        
        $this->invoice = new Wevoi_Invoice();
    }

    /**
     * Define table columns.
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'cb'             => '<input type="checkbox" />',
            'invoice_number' => __('Invoice Number', 'wevoi-competition-manager'),
            'recipient'      => __('Recipient', 'wevoi-competition-manager'),
            'coupon_code'    => __('Coupon Code', 'wevoi-competition-manager'),
            'competition'    => __('Competition', 'wevoi-competition-manager'),
            'amount'         => __('Amount', 'wevoi-competition-manager'),
            'status'         => __('Status', 'wevoi-competition-manager'),
            'created_date'   => __('Created Date', 'wevoi-competition-manager'),
            'actions'        => __('Actions', 'wevoi-competition-manager')
        );
    }

    /**
     * Define sortable columns.
     *
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'invoice_number' => array('invoice_number', true),
            'coupon_code'    => array('coupon_code', false),
            'created_date'   => array('created_date', true),
            'status'         => array('status', false),
            'amount'         => array('total_amount', false)
        );
    }

    /**
     * Get bulk actions.
     *
     * @return array
     */
    public function get_bulk_actions() {
        return array(
            'delete' => __('Delete', 'wevoi-competition-manager')
        );
    }

    /**
     * Process bulk actions.
     */
    public function process_bulk_action() {
        // Security check
        if (isset($_POST['_wpnonce']) && !empty($_POST['_wpnonce'])) {
            $nonce = filter_input(INPUT_POST, '_wpnonce', FILTER_SANITIZE_STRING);
            
            if (!wp_verify_nonce($nonce, 'bulk-' . $this->_args['plural'])) {
                wp_die(__('Security check failed', 'wevoi-competition-manager'));
            }
        }
        
        $action = $this->current_action();
        
        if ($action === 'delete') {
            $invoice_ids = isset($_POST['invoice']) ? array_map('intval', $_POST['invoice']) : array();
            
            if (!empty($invoice_ids)) {
                foreach ($invoice_ids as $id) {
                    $this->invoice->delete($id);
                }
                
                // Add admin notice
                $redirect = add_query_arg(
                    array(
                        'page'    => 'wevoi-competitions',
                        'tab'     => 'invoices',
                        'message' => 'deleted'
                    ),
                    admin_url('admin.php')
                );
                
                wp_redirect($redirect);
                exit;
            }
        }
    }

    /**
     * Prepare items for display.
     */
    public function prepare_items() {
        $this->process_bulk_action();
        
        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $status = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : '';
        
        $args = array(
            'number'  => $per_page,
            'offset'  => ($current_page - 1) * $per_page,
            'search'  => $search,
            'status'  => $status,
            'orderby' => isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'created_date',
            'order'   => isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'DESC'
        );
        
        $result = $this->invoice->get_invoices($args);
        
        $this->items = $result['items'];
        
        $this->set_pagination_args(array(
            'total_items' => $result['total'],
            'per_page'    => $per_page,
            'total_pages' => $result['pages']
        ));
    }

    /**
     * Checkbox column.
     *
     * @param object $item
     * @return string
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="invoice[]" value="%s" />',
            $item->id
        );
    }

    /**
     * Invoice number column.
     *
     * @param object $item
     * @return string
     */
    public function column_invoice_number($item) {
        $actions = array(
            'view' => sprintf(
                '<a href="%s">%s</a>',
                add_query_arg(
                    array(
                        'page'   => 'wevoi-competitions',
                        'tab'    => 'invoices',
                        'action' => 'view',
                        'id'     => $item->id
                    ),
                    admin_url('admin.php')
                ),
                __('View', 'wevoi-competition-manager')
            ),
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                add_query_arg(
                    array(
                        'page'   => 'wevoi-competitions',
                        'tab'    => 'invoices',
                        'action' => 'edit',
                        'id'     => $item->id
                    ),
                    admin_url('admin.php')
                ),
                __('Edit', 'wevoi-competition-manager')
            ),
            'delete' => sprintf(
                '<a href="%s" class="delete-invoice" data-id="%s">%s</a>',
                add_query_arg(
                    array(
                        'page'   => 'wevoi-competitions',
                        'tab'    => 'invoices',
                        'action' => 'delete',
                        'id'     => $item->id,
                        '_wpnonce' => wp_create_nonce('delete-invoice-' . $item->id)
                    ),
                    admin_url('admin.php')
                ),
                $item->id,
                __('Delete', 'wevoi-competition-manager')
            )
        );
        
        return sprintf(
            '<strong><a href="%s">%s</a></strong> %s',
            add_query_arg(
                array(
                    'page'   => 'wevoi-competitions',
                    'tab'    => 'invoices',
                    'action' => 'view',
                    'id'     => $item->id
                ),
                admin_url('admin.php')
            ),
            $item->invoice_number,
            $this->row_actions($actions)
        );
    }

    /**
     * Recipient column.
     *
     * @param object $item
     * @return string
     */
    public function column_recipient($item) {
        return sprintf(
            '<strong>%s</strong><br><a href="mailto:%s">%s</a>',
            esc_html($item->recipient_name),
            esc_attr($item->recipient_email),
            esc_html($item->recipient_email)
        );
    }

    /**
     * Amount column.
     *
     * @param object $item
     * @return string
     */
    public function column_amount($item) {
        return sprintf(
            '£%s<br><small>(%d x £%s)</small>',
            number_format($item->total_amount, 2),
            $item->quantity,
            number_format($item->unit_price, 2)
        );
    }

    /**
     * Status column.
     *
     * @param object $item
     * @return string
     */
    public function column_status($item) {
        $status_classes = array(
            'PENDING'   => 'status-pending',
            'SENT'      => 'status-sent',
            'PAID'      => 'status-paid',
            'CANCELLED' => 'status-cancelled'
        );
        
        $class = isset($status_classes[$item->status]) ? $status_classes[$item->status] : '';
        
        return sprintf(
            '<span class="invoice-status %s">%s</span>',
            esc_attr($class),
            esc_html($item->status)
        );
    }

    /**
     * Created date column.
     *
     * @param object $item
     * @return string
     */
    public function column_created_date($item) {
        return date_i18n(get_option('date_format'), strtotime($item->created_date));
    }

    /**
     * Actions column.
     *
     * @param object $item
     * @return string
     */
    public function column_actions($item) {
        $actions = array();
        
        // View button
        $actions[] = sprintf(
            '<a href="%s" class="button button-small">%s</a>',
            add_query_arg(
                array(
                    'page'   => 'wevoi-competitions',
                    'tab'    => 'invoices',
                    'action' => 'view',
                    'id'     => $item->id
                ),
                admin_url('admin.php')
            ),
            __('View', 'wevoi-competition-manager')
        );
        
        // Edit button
        $actions[] = sprintf(
            '<a href="%s" class="button button-small">%s</a>',
            add_query_arg(
                array(
                    'page'   => 'wevoi-competitions',
                    'tab'    => 'invoices',
                    'action' => 'edit',
                    'id'     => $item->id
                ),
                admin_url('admin.php')
            ),
            __('Edit', 'wevoi-competition-manager')
        );
        
        // Status-specific actions
        if ($item->status === 'PENDING') {
            // Send button
            $actions[] = sprintf(
                '<a href="#" class="button button-small send-invoice" data-id="%d">%s</a>',
                $item->id,
                __('Send', 'wevoi-competition-manager')
            );
        }
        
        if ($item->status === 'SENT') {
            // Mark as paid button
            $actions[] = sprintf(
                '<a href="#" class="button button-small mark-paid" data-id="%d">%s</a>',
                $item->id,
                __('Mark as Paid', 'wevoi-competition-manager')
            );
        }
        
        return implode(' ', $actions);
    }

    /**
     * Default column output.
     *
     * @param object $item
     * @param string $column_name
     * @return string
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'coupon_code':
                return esc_html($item->coupon_code);
            case 'competition':
                return esc_html($item->competition_name);
            default:
                return isset($item->$column_name) ? esc_html($item->$column_name) : '';
        }
    }

    /**
     * Display table when no items.
     */
    public function no_items() {
        _e('No invoices found.', 'wevoi-competition-manager');
    }

    /**
     * Get table classes.
     *
     * @return array
     */
    protected function get_table_classes() {
        return array('widefat', 'fixed', 'striped', 'invoices-table');
    }
}