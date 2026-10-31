<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Coupons_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'coupon',
            'plural'   => 'coupons',
            'ajax'     => false
        ]);
    }

    /**
     * Tablo sütunlarını tanımla
     */
    public function get_columns() {
        return [
            'cb'                => '<input type="checkbox" />',
            'id'                => __('ID', 'wevoi-competition-manager'),
            'coupon_code'       => __('Coupon Code', 'wevoi-competition-manager'),
            'competition_name'  => __('Competition', 'wevoi-competition-manager'),
            'coupon_limit'      => __('Limit', 'wevoi-competition-manager'),
            'coupon_limit_expiration_date' => __('Expiration Date', 'wevoi-competition-manager'),
            'coupon_discount'   => __('Discount', 'wevoi-competition-manager'),
            'coupon_used'       => __('Used', 'wevoi-competition-manager'),
            'coupon_comments'   => __('Comments', 'wevoi-competition-manager'),
            'status'           => __('Status', 'wevoi-competition-manager'),
            'actions'          => __('Actions', 'wevoi-competition-manager')
        ];
    }

    /**
     * Sıralanabilir sütunları tanımla
     */
    public function get_sortable_columns() {
        return [
            'id' => ['id', false], // ID sıralanabilir (oluşturulma tarihine göre)
            'coupon_code' => ['coupon_code', true],
            'competition_name' => ['competition_name', false],
            'coupon_used' => ['coupon_used', false],
            'coupon_limit_expiration_date' => ['coupon_limit_expiration_date', false]
        ];
    }

    /**
     * Bulk actions
     */
    public function get_bulk_actions() {
        return [
            'delete' => __('Delete', 'wevoi-competition-manager')
        ];
    }



    /**
     * Prepare items
     */
    public function prepare_items() {
        global $wpdb;

        // Sayfa başına gösterilecek öğe sayısı
        $per_page = 20;

        // Sütunları ayarla
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        // SQL sorgusunu oluştur
        $sql_select = "SELECT c.*, comp.competition_name";

        $sql_from = " FROM {$wpdb->prefix}wevoi_coupons c
                      LEFT JOIN {$wpdb->prefix}wevoi_competitions comp
                      ON c.competition_code = comp.competition_code";

        $sql_where = " WHERE 1=1";

        // Status filtresi
        if (isset($_REQUEST['status_filter']) && !empty($_REQUEST['status_filter'])) {
            switch ($_REQUEST['status_filter']) {
                case 'valid':
                    $sql_where .= " AND (
                        (c.coupon_limit IS NULL OR c.coupon_used < c.coupon_limit)
                        AND (c.coupon_limit_expiration_date IS NULL OR c.coupon_limit_expiration_date >= CURDATE())
                    )";
                    break;
                case 'expired':
                    $sql_where .= " AND c.coupon_limit_expiration_date < CURDATE()";
                    break;
                case 'limit_reached':
                    $sql_where .= " AND c.coupon_limit IS NOT NULL AND c.coupon_used >= c.coupon_limit";
                    break;
            }
        }

        // Competition filtresi
        if (isset($_REQUEST['competition_filter']) && !empty($_REQUEST['competition_filter'])) {
            $sql_where .= $wpdb->prepare(" AND c.competition_code = %d", intval($_REQUEST['competition_filter']));
        }

        // Arama filtresi
        if (isset($_REQUEST['s']) && !empty($_REQUEST['s'])) {
            $search = '%' . $wpdb->esc_like($_REQUEST['s']) . '%';
            $sql_where .= $wpdb->prepare(
                " AND (c.coupon_code LIKE %s OR comp.competition_name LIKE %s OR c.coupon_comments LIKE %s)",
                $search,
                $search,
                $search
            );
        }

        // Toplam kayıt sayısını al
        $total_items = $wpdb->get_var("SELECT COUNT(*) " . $sql_from . $sql_where);

        // Sayfalama
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        // Sıralama - Varsayılan olarak id DESC (en yeni en üstte - id AUTO_INCREMENT olduğu için oluşturulma sırasını temsil eder)
        $orderby = isset($_REQUEST['orderby']) ? trim($_REQUEST['orderby']) : 'id';
        $order = isset($_REQUEST['order']) ? trim($_REQUEST['order']) : 'DESC';

        // Güvenli sıralama sütunları
        $allowed_orderby_columns = ['id', 'coupon_code', 'competition_code', 'coupon_used'];
        if (!in_array($orderby, $allowed_orderby_columns)) {
            $orderby = 'id';
            $order = 'DESC'; // Varsayılan olarak en yeni en üstte
        }
        $order = (strtoupper($order) === 'ASC') ? 'ASC' : 'DESC';

        // Eğer orderby belirtilmemişse, varsayılan olarak id DESC
        if (empty($_REQUEST['orderby'])) {
            $orderby = 'id';
            $order = 'DESC';
        }

        $sql_orderby = " ORDER BY c." . esc_sql($orderby) . " " . esc_sql($order);

        // Final sorguyu oluştur
        $final_sql = $sql_select . $sql_from . $sql_where . $sql_orderby . " LIMIT $per_page OFFSET $offset";

        // Verileri al - hata kontrolü ile
        $this->items = $wpdb->get_results($final_sql);

        // Eğer sorgu hatası varsa, hata mesajını logla ve boş array döndür
        if ($wpdb->last_error) {
            error_log('Coupon list query error: ' . $wpdb->last_error);
            error_log('Query: ' . $final_sql);
            $this->items = [];
        }

        // Sayfalama ayarlarını yap
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'   => $per_page,
            'total_pages'=> ceil($total_items / $per_page)
        ]);
    }

    /**
     * Checkbox sütunu
     */
    protected function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="coupons[]" value="%s" />',
            $item->id
        );
    }

    /**
     * ID sütunu
     */
    protected function column_id($item) {
        return sprintf(
            '<strong>%d</strong>',
            intval($item->id)
        );
    }

/**
 * Coupon Code sütunu
 */
protected function column_coupon_code($item) {
    return sprintf(
        '<strong>%s</strong>',
        esc_html($item->coupon_code)
    );
}

    /**
     * Competition Name sütunu
     */
    protected function column_competition_name($item) {
        return sprintf(
            '<strong>%s</strong><div class="row-actions"><span class="code">Code: %s</span></div>',
            $item->competition_name,
            $item->competition_code
        );
    }

    /**
     * Limit sütunu
     */
    protected function column_coupon_limit($item) {
        if (!empty($item->coupon_limit)) {
            return sprintf(
                '%d / %d',
                $item->coupon_used,
                $item->coupon_limit
            );
        }
        return '-';
    }

    /**
     * Discount sütunu
     */
    protected function column_coupon_discount($item) {
        if (!empty($item->coupon_discount_amount)) {
            return '£' . number_format($item->coupon_discount_amount, 2);
        } elseif (!empty($item->coupon_discount_percentage)) {
            return $item->coupon_discount_percentage . '%';
        }
        return '-';
    }

    /**
     * Status sütunu
     */
    protected function column_status($item) {
        $status = '';
        $class = '';

        if (!empty($item->coupon_limit_expiration_date)) {
            $expiration_date = strtotime($item->coupon_limit_expiration_date);
            if ($expiration_date < time()) {
                $status = 'EXPIRED';
                $class = 'status-expired';
            }
        }

        if (!empty($item->coupon_limit) && $item->coupon_used >= $item->coupon_limit) {
            $status = 'LIMIT REACHED';
            $class = 'status-limit-reached';
        }

        if (empty($status)) {
            $status = 'VALID';
            $class = 'status-valid';
        }

        return sprintf(
            '<span class="status-badge %s">%s</span>',
            esc_attr($class),
            esc_html($status)
        );
    }

protected function column_actions($item) {
    $actions = [
        'edit' => sprintf(
            '<a href="?page=%s&tab=coupons&action=edit&id=%s" class="button button-small">%s</a>',
            $_REQUEST['page'],
            $item->id,
            __('Edit', 'wevoi-competition-manager')
        )
    ];

    // Add delete action
    $actions['delete'] = sprintf(
        '<a href="%s" class="button button-small button-link-delete delete-coupon" onclick="return confirm(\'%s\');">%s</a>',
        wp_nonce_url(
            sprintf(
                'admin.php?page=wevoi-competitions&tab=coupons&action=delete&id=%d',
                $item->id
            ),
            'delete_coupon_' . $item->id
        ),
        esc_js(__('Are you sure you want to delete this coupon?', 'wevoi-competition-manager')),
        __('Delete', 'wevoi-competition-manager')
    );

    // Add invoice button directly instead of trying to use non-existent class
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

        $actions['invoice'] = sprintf(
            '<a href="%s" class="button button-small">%s</a>',
            $invoice_url,
            __('Invoice', 'wevoi-competition-manager')
        );
    }

    return implode(' ', $actions);
}

    /**
     * Varsayılan sütun görünümü
     */
    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'coupon_limit_expiration_date':
                return !empty($item->$column_name) ? date('Y-m-d', strtotime($item->$column_name)) : '-';
            case 'coupon_comments':
                return !empty($item->$column_name) ? esc_html($item->$column_name) : '-';
            default:
                return isset($item->$column_name) ? esc_html($item->$column_name) : '';
        }
    }
}
