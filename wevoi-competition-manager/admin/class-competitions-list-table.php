<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Competitions_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'competition',
            'plural'   => 'competitions',
            'ajax'     => false
        ]);
    }

    /**
     * Tablo sütunlarını tanımla
     */
    public function get_columns() {
        return [
            'cb'                => '<input type="checkbox" />',
            'season_id'         => __('Season', 'wevoi-competition-manager'),
            'competition_code'  => __('Code', 'wevoi-competition-manager'),
            'competition_name'  => __('Name', 'wevoi-competition-manager'),
            'competition_date'  => __('Date', 'wevoi-competition-manager'),
            'competition_status'=> __('Status', 'wevoi-competition-manager'),
            'competition_price' => __('Price (£)', 'wevoi-competition-manager'),
            'coupons'          => __('Coupons', 'wevoi-competition-manager'),
            'actions'          => __('Actions', 'wevoi-competition-manager')
        ];
    }

    /**
     * Sıralanabilir sütunları tanımla
     */
    public function get_sortable_columns() {
        return [
            'competition_code'  => ['competition_code', true],
            'competition_name'  => ['competition_name', false],
            'competition_date'  => ['competition_date', false],
            'competition_status'=> ['competition_status', false],
            'season_id'         => ['season_id', false],
            'competition_price' => ['competition_price', false]
        ];
    }

    /**
     * Toplu işlemler
     */
    public function get_bulk_actions() {
        return [
            'delete' => __('Delete', 'wevoi-competition-manager')
        ];
    }

    /**
     * Verileri hazırla
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

        // Sıralama parametrelerini al
        $orderby = isset($_REQUEST['orderby']) ? trim($_REQUEST['orderby']) : 'competition_date';
        $order = isset($_REQUEST['order']) ? trim($_REQUEST['order']) : 'DESC';

        // SQL sorgusunu oluştur (season için wevoi_seasons LEFT JOIN)
        $sql = "SELECT c.*, COUNT(cp.id) as coupon_count,
                MAX(s.season_name) as season_name
                FROM {$wpdb->prefix}wevoi_competitions c
                LEFT JOIN {$wpdb->prefix}wevoi_coupons cp ON c.competition_code = cp.competition_code
                LEFT JOIN {$wpdb->prefix}wevoi_seasons s ON c.season_id = s.season_id
                GROUP BY c.id";

        if (!empty($orderby) && !empty($order)) {
            $orderby = in_array($orderby, ['competition_code','competition_name','competition_date','competition_status','competition_price','season_id']) ? $orderby : 'competition_date';
            $sql .= " ORDER BY c.$orderby $order";
        }

        // Toplam kayıt sayısını al
        $total_items = $wpdb->query($sql);

        // Sayfalama
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $sql .= " LIMIT $per_page OFFSET $offset";

        // Verileri al
        $this->items = $wpdb->get_results($sql, ARRAY_A);

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
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="competitions[]" value="%s" />',
            $item['id']
        );
    }

    /**
     * Status sütunu özel görünümü
     */
    protected function column_competition_status($item) {
        $status = $item['competition_status'];
        $status_classes = [
            'NOT YET'    => 'status-not-yet',
            'CONFIRMED'  => 'status-confirmed',
            'ON AIR'     => 'status-on-air',
            'IN THE QUE' => 'status-in-que',
            'CLOSED'     => 'status-closed',
            'CANCELLED'  => 'status-cancelled',
            'POSTPONED'  => 'status-postponed',
            'EVALUATING' => 'status-evaluating',
            'RANKINGS_FINALIZED' => 'status-rankings-finalized',
            'COMPLETED'  => 'status-completed'
        ];

        $class = isset($status_classes[$status]) ? $status_classes[$status] : '';

        return sprintf(
            '<span class="status-badge %s">%s</span>',
            esc_attr($class),
            esc_html($status)
        );
    }

    /**
     * Actions sütunu
     */
    protected function column_actions($item) {
        $actions = [
            'edit' => sprintf(
                '<a href="?page=%s&action=edit&id=%s" class="button button-small">%s</a>',
                $_REQUEST['page'],
                $item['id'],
                __('Edit', 'wevoi-competition-manager')
            ),
            'add_coupon' => sprintf(
                '<a href="?page=%s&tab=coupons&action=add&competition_code=%s" class="button button-small">%s</a>',
                $_REQUEST['page'],
                $item['competition_code'],
                __('Add Coupon', 'wevoi-competition-manager')
            )
        ];

        return implode(' ', $actions);
    }

    /**
     * Varsayılan sütun görünümü
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'competition_price':
                return '£' . number_format($item[$column_name], 2);
            case 'season_id':
                if (!empty($item['season_name'])) {
                    return esc_html($item['season_name']);
                }
                return '—';
            case 'coupons':
                return $item['coupon_count'];
            default:
                return isset($item[$column_name]) ? $item[$column_name] : '';
        }
    }
}
