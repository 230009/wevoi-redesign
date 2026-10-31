<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Topics_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'topic',
            'plural'   => 'topics',
            'ajax'     => false
        ]);
    }

    /**
     * Define table columns
     */
    public function get_columns() {
        return [
            'cb'                    => '<input type="checkbox" />',
            'id'                    => __('ID', 'wevoi-competition-manager'),
            'competition_code'      => __('Competition Code', 'wevoi-competition-manager'),
            'competition_name'      => __('Competition Name', 'wevoi-competition-manager'),
            'wevoi_age_category'    => __('Age Category', 'wevoi-competition-manager'),
            'topic'                 => __('Topic', 'wevoi-competition-manager'),
            'actions'               => __('Actions', 'wevoi-competition-manager')
        ];
    }

    /**
     * Define sortable columns
     */
    public function get_sortable_columns() {
        return [
            'id'                    => ['id', true],
            'competition_code'      => ['competition_code', false],
            'wevoi_age_category'    => ['wevoi_age_category', false],
            'topic'                 => ['topic', false]
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
     * Prepare items for display
     */
    public function prepare_items() {
        global $wpdb;

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        // Search and filter parameters
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $competition_filter = isset($_REQUEST['competition_filter']) ? sanitize_text_field($_REQUEST['competition_filter']) : '';
        $age_category_filter = isset($_REQUEST['age_category_filter']) ? sanitize_text_field($_REQUEST['age_category_filter']) : '';

        // Order by
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'id';
        $order = isset($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'DESC';

        // Build WHERE clause for filtering
        $where_conditions = [];
        $where_values = [];

        if (!empty($search)) {
            $where_conditions[] = "topic LIKE %s";
            $where_values[] = '%' . $wpdb->esc_like($search) . '%';
        }

        if (!empty($competition_filter)) {
            $where_conditions[] = "competition_code = %d";
            $where_values[] = intval($competition_filter);
        }

        if (!empty($age_category_filter)) {
            $where_conditions[] = "wevoi_age_category = %s";
            $where_values[] = $age_category_filter;
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Build the main query with filters
        $query = "SELECT * FROM {$wpdb->prefix}wevoi_topics {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

        if (!empty($where_values)) {
            $query_values = array_merge($where_values, [$per_page, $offset]);
            $this->items = $wpdb->get_results($wpdb->prepare($query, $query_values), ARRAY_A);
        } else {
            $this->items = $wpdb->get_results($wpdb->prepare($query, $per_page, $offset), ARRAY_A);
        }

        // If we have items, try to get competition names separately
        if (!empty($this->items)) {
            foreach ($this->items as &$item) {
                $competition = $wpdb->get_row($wpdb->prepare(
                    "SELECT competition_name FROM {$wpdb->prefix}wevoi_competitions WHERE competition_code = %d",
                    $item['competition_code']
                ));
                $item['competition_name'] = $competition ? $competition->competition_name : 'N/A';
            }
        }

        // Get total count with filters
        $count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}wevoi_topics {$where_clause}";

        if (!empty($where_values)) {
            // Remove the LIMIT values from where_values for count query
            $count_values = array_slice($where_values, 0, count($where_values));
            $total_items = $wpdb->get_var($wpdb->prepare($count_query, $count_values));
        } else {
            $total_items = $wpdb->get_var($count_query);
        }

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }

    /**
     * Display column content
     */
    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return $item['id'];
            case 'competition_code':
                return $item['competition_code'];
            case 'competition_name':
                return $item['competition_name'] ? $item['competition_name'] : 'N/A';
            case 'wevoi_age_category':
                return $item['wevoi_age_category'];
            case 'topic':
                return wp_trim_words($item['topic'], 10);
            default:
                return print_r($item, true);
        }
    }

    /**
     * Checkbox column
     */
    protected function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="topic[]" value="%s" />',
            $item['id']
        );
    }

    /**
     * Actions column
     */
    protected function column_actions($item) {
        $actions = [
            'edit' => sprintf(
                '<a href="?page=%s&tab=topics&action=edit&id=%s" class="button button-small">%s</a>',
                $_REQUEST['page'],
                $item['id'],
                __('Edit', 'wevoi-competition-manager')
            )
        ];

        // Add delete action
        $actions['delete'] = sprintf(
            '<a href="%s" class="button button-small button-link-delete delete-topic" onclick="return confirm(\'%s\');">%s</a>',
            wp_nonce_url(
                sprintf(
                    'admin.php?page=wevoi-competitions&tab=topics&action=delete&id=%d',
                    $item['id']
                ),
                'delete_topic_' . $item['id']
            ),
            esc_js(__('Are you sure you want to delete this topic?', 'wevoi-competition-manager')),
            __('Delete', 'wevoi-competition-manager')
        );

        return implode(' ', $actions);
    }

    /**
     * No items message
     */
    public function no_items() {
        _e('No topics found.', 'wevoi-competition-manager');
    }

    /**
     * Override display method to ensure proper rendering
     */
    public function display() {
        $this->display_tablenav('top');
        ?>
        <table class="wp-list-table <?php echo implode(' ', $this->get_table_classes()); ?>">
            <thead>
                <tr>
                    <?php $this->print_column_headers(); ?>
                </tr>
            </thead>
            <tbody id="the-list"<?php echo $this->row_attributes; ?>>
                <?php $this->display_rows_or_placeholder(); ?>
            </tbody>
            <tfoot>
                <tr>
                    <?php $this->print_column_headers(false); ?>
                </tr>
            </tfoot>
        </table>
        <?php
        $this->display_tablenav('bottom');
    }
}
