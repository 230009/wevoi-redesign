<?php
/**
 * Invoice Class
 *
 * @package WeVoi_Competition_Manager
 * @subpackage Invoice
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Invoice class to handle invoice operations.
 */
class Wevoi_Invoice {
    /**
     * Invoice table name.
     *
     * @var string
     */
    private $table_name;

    /**
     * Initialize the class.
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wevoi_invoices';
    }

    /**
     * Get invoice by ID.
     *
     * @param int $invoice_id
     * @return object|false
     */
    public function get($invoice_id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $invoice_id)
        );
    }

    /**
     * Get invoices.
     *
     * @param array $args
     * @return array
     */
    public function get_invoices($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'number'      => 20,
            'offset'      => 0,
            'orderby'     => 'id',
            'order'       => 'DESC',
            'status'      => '',
            'coupon_id'   => 0,
            'search'      => '',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $values = array();
        
        // Filter by status
        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        
        // Filter by coupon_id
        if (!empty($args['coupon_id'])) {
            $where[] = 'coupon_id = %d';
            $values[] = $args['coupon_id'];
        }
        
        // Search
        if (!empty($args['search'])) {
            $where[] = '(invoice_number LIKE %s OR recipient_name LIKE %s OR recipient_email LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
            $values[] = $search_term;
        }
        
        // Build query
        $where_clause = implode(' AND ', $where);
        
        // Count query
        $count_query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";
        
        // Items query
        $query = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$args['orderby']} {$args['order']} LIMIT %d, %d";
        
        // Add limit parameters
        $values[] = $args['offset'];
        $values[] = $args['number'];
        
        // Prepare queries
        $count_query = !empty($values) ? $wpdb->prepare($count_query, $values) : $count_query;
        
        // Remove last two values (limit params) for count query
        array_pop($values);
        array_pop($values);
        
        // Add limit params back for items query
        $values[] = $args['offset'];
        $values[] = $args['number'];
        
        $query = $wpdb->prepare($query, $values);
        
        // Execute queries
        $total = $wpdb->get_var($count_query);
        $items = $wpdb->get_results($query);
        
        return array(
            'items' => $items,
            'total' => $total,
            'pages' => ceil($total / $args['number']),
        );
    }

    /**
     * Save invoice data.
     *
     * @param array $data
     * @return int|WP_Error
     */
    public function save($data) {
        global $wpdb;
        
        // Validate required fields
        $required_fields = array('coupon_id', 'coupon_code', 'competition_id', 'competition_name', 
                                'recipient_name', 'recipient_email', 'unit_price', 'quantity');
        
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', sprintf(__('Field %s is required', 'wevoi-competition-manager'), $field));
            }
        }
        
        // Sanitize data
        $coupon_id = intval($data['coupon_id']);
        $competition_id = intval($data['competition_id']);
        $unit_price = floatval($data['unit_price']);
        $quantity = intval($data['quantity']);
        $total_amount = $unit_price * $quantity;
        
        // Generate invoice number
        $invoice_number = $this->generate_invoice_number();
        
        // Prepare invoice data
        $invoice_data = array(
            'invoice_number'   => $invoice_number,
            'coupon_id'        => $coupon_id,
            'coupon_code'      => sanitize_text_field($data['coupon_code']),
            'competition_id'   => $competition_id,
            'competition_name' => sanitize_text_field($data['competition_name']),
            'recipient_name'   => sanitize_text_field($data['recipient_name']),
            'recipient_email'  => sanitize_email($data['recipient_email']),
            'unit_price'       => $unit_price,
            'quantity'         => $quantity,
            'total_amount'     => $total_amount,
            'status'           => 'PENDING',
            'created_date'     => current_time('mysql'),
            'notes'            => isset($data['notes']) ? sanitize_textarea_field($data['notes']) : '',
        );
        
        // Insert or update
        if (!empty($data['invoice_id'])) {
            // Update existing invoice
            $result = $wpdb->update(
                $this->table_name,
                $invoice_data,
                array('id' => intval($data['invoice_id'])),
                array(
                    '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%f', '%d', '%f', '%s', '%s', '%s'
                ),
                array('%d')
            );
            
            if ($result === false) {
                return new WP_Error('db_error', __('Could not update invoice', 'wevoi-competition-manager'));
            }
            
            return intval($data['invoice_id']);
        } else {
            // Insert new invoice
            $result = $wpdb->insert(
                $this->table_name,
                $invoice_data,
                array(
                    '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%f', '%d', '%f', '%s', '%s', '%s'
                )
            );
            
            if ($result === false) {
                return new WP_Error('db_error', __('Could not create invoice', 'wevoi-competition-manager'));
            }
            
            return $wpdb->insert_id;
        }
    }

    /**
     * Update invoice status.
     *
     * @param int $invoice_id
     * @param string $status
     * @param array $data Additional data
     * @return bool|WP_Error
     */
    public function update_status($invoice_id, $status, $data = array()) {
        global $wpdb;
        
        $allowed_statuses = array('PENDING', 'SENT', 'PAID', 'CANCELLED');
        
        if (!in_array($status, $allowed_statuses)) {
            return new WP_Error('invalid_status', __('Invalid status', 'wevoi-competition-manager'));
        }
        
        $update_data = array(
            'status' => $status
        );
        
        // Additional fields based on status
        if ($status === 'SENT') {
            $update_data['sent_date'] = current_time('mysql');
        } elseif ($status === 'PAID') {
            $update_data['payment_method'] = isset($data['payment_method']) ? sanitize_text_field($data['payment_method']) : '';
            $update_data['payment_reference'] = isset($data['payment_reference']) ? sanitize_text_field($data['payment_reference']) : '';
            $update_data['payment_date'] = isset($data['payment_date']) ? sanitize_text_field($data['payment_date']) : current_time('mysql');
        }
        
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            array('id' => $invoice_id),
            null,
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Could not update invoice status', 'wevoi-competition-manager'));
        }
        
        return true;
    }

    /**
     * Delete invoice.
     *
     * @param int $invoice_id
     * @return bool|WP_Error
     */
    public function delete($invoice_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $invoice_id),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Could not delete invoice', 'wevoi-competition-manager'));
        }
        
        return true;
    }

    /**
     * Generate unique invoice number.
     *
     * @return string
     */
    private function generate_invoice_number() {
        $prefix = 'INV-';
        $year = date('Y');
        $month = date('m');
        
        // Get the last invoice number
        global $wpdb;
        $last_number = $wpdb->get_var("
            SELECT invoice_number FROM {$this->table_name}
            WHERE invoice_number LIKE '{$prefix}{$year}{$month}%'
            ORDER BY id DESC LIMIT 1
        ");
        
        if ($last_number) {
            // Extract the sequential number
            $sequential = intval(substr($last_number, -4));
            $sequential++;
        } else {
            $sequential = 1;
        }
        
        // Format the new invoice number
        return sprintf('%s%s%s%04d', $prefix, $year, $month, $sequential);
    }
}