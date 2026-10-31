<?php

class Wevoi_Competitions {
    private $wpdb;
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'wevoi_competitions';

        // Form işlemleri için hook'ları ekle
        add_action('admin_post_add_competition', array($this, 'handle_add_competition'));
        add_action('admin_post_edit_competition', array($this, 'handle_edit_competition'));
        add_action('admin_post_delete_competition', array($this, 'handle_delete_competition'));
    }


    public function handle_add_competition() {
        // Nonce kontrolü
        if (!isset($_POST['competition_nonce']) ||
            !wp_verify_nonce($_POST['competition_nonce'], 'save_competition')) {
            wp_die(__('Security check failed', 'wevoi-competition-manager'));
        }

        // Yetki kontrolü
        if (!current_user_can('create_competitions')) {
            wp_die(__('You do not have permission to create competitions', 'wevoi-competition-manager'));
        }

        // Form verilerini al ve sanitize et
        $competition_data = $this->sanitize_competition_data($_POST);

        // Competition code benzersizlik kontrolü
        if ($this->competition_code_exists($competition_data['competition_code'])) {
            $this->redirect_with_error('add', __('Competition code already exists', 'wevoi-competition-manager'));
            return;
        }

        // Prepare format array based on competition type
        $formats = array(
            '%d', // competition_code
            '%s', // certificate_id_prefix
            '%s', // competition_name
            '%s', // competition_date
            '%s', // competition_status
            '%d', // season_id
            '%f', // competition_price
            '%s', // competition_type
            '%d', // award_ceremony_completed
            '%s', // award_ceremony_zoom_link
            '%s', // award_ceremony_date
        );

        // Add Forum pricing formats if Forum type
        if (isset($competition_data['competition_type']) && $competition_data['competition_type'] === 'Forum') {
            $formats[] = '%f'; // price_supervisor
            $formats[] = '%f'; // price_parent
            $formats[] = '%s'; // event_venue
            $formats[] = '%d'; // event_duration
        }

        $formats[] = '%s'; // forum_payment_phase

        // Veritabanına ekle
        $result = $this->wpdb->insert(
            $this->table_name,
            $competition_data,
            $formats
        );

        if ($result === false) {
            $this->redirect_with_error('add');
            return;
        }

        // If status is COMPLETED, run commission calculation (Spark/Reload/Summit)
        if (!empty($competition_data['competition_status']) && $competition_data['competition_status'] === 'COMPLETED') {
            $competition_code = (int) $competition_data['competition_code'];
            if ($competition_code > 0) {
                if (!class_exists('Wevoi_Commission')) {
                    require_once dirname(__FILE__) . '/class-commission.php';
                }
                $commission = new Wevoi_Commission();
                $commission->run_for_competition($competition_code);
            }
        }

        // Başarılı ekleme sonrası yönlendirme
        wp_redirect(add_query_arg(
            array(
                'page' => 'wevoi-competitions',
                'message' => 1
            ),
            admin_url('admin.php')
        ));
        exit;
    }


    public function handle_edit_competition() {
    error_log('*** EDIT COMPETITION HANDLER BAŞLADI ***');

    // Nonce kontrolü
    if (!isset($_POST['competition_nonce']) ||
        !wp_verify_nonce($_POST['competition_nonce'], 'save_competition')) {
        error_log('Nonce kontrolü başarısız');
        wp_die(__('Security check failed', 'wevoi-competition-manager'));
    }

    // Yetki kontrolü
    if (!current_user_can('edit_competitions')) {
        error_log('Yetki kontrolü başarısız');
        wp_die(__('You do not have permission to edit competitions', 'wevoi-competition-manager'));
    }

    // ID kontrolü
    if (!isset($_POST['competition_id'])) {
        error_log('ID kontrolü başarısız');
        wp_die(__('Competition ID is required', 'wevoi-competition-manager'));
    }

    $id = intval($_POST['competition_id']);
    error_log('Güncellenecek yarışma ID: ' . $id);

    // Form verilerini al ve sanitize et
    $competition_data = $this->sanitize_competition_data($_POST);
    error_log('Sanitize edilmiş veri: ' . print_r($competition_data, true));

    // Tablo yapısını kontrol et
    $table_columns = $this->wpdb->get_results("SHOW COLUMNS FROM {$this->table_name}");
    $column_names = array_column($table_columns, 'Field');
    error_log('Tablo sütunları: ' . print_r($column_names, true));

    // Mevcut verileri kontrol et
    $current_data = $this->wpdb->get_row(
        $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id),
        ARRAY_A
    );
    error_log('Mevcut veri: ' . print_r($current_data, true));

    // Prepare format array based on competition type
    $formats = array(
        '%d', // competition_code
        '%s', // certificate_id_prefix
        '%s', // competition_name
        '%s', // competition_date
        '%s', // competition_status
        '%d', // season_id
        '%f', // competition_price
        '%s', // competition_type
        '%d', // award_ceremony_completed
        '%s', // award_ceremony_zoom_link
        '%s', // award_ceremony_date
    );

    // Add Forum pricing formats if Forum type
    if (isset($competition_data['competition_type']) && $competition_data['competition_type'] === 'Forum') {
        $formats[] = '%f'; // price_supervisor
        $formats[] = '%f'; // price_parent
        $formats[] = '%s'; // event_venue
        $formats[] = '%d'; // event_duration
    }

    $formats[] = '%s'; // forum_payment_phase

    // Veritabanında güncelle
    $result = $this->wpdb->update(
        $this->table_name,
        $competition_data,
        array('id' => $id),
        $formats,
        array('%d') // where id
    );

    error_log('Son SQL sorgusu: ' . $this->wpdb->last_query);
    error_log('WPDB hata: ' . $this->wpdb->last_error);
    error_log('Güncelleme sonucu: ' . ($result !== false ? 'Başarılı (' . $result . ')' : 'Başarısız'));

    if ($result === false) {
        error_log('Güncelleme başarısız, hata sayfasına yönlendiriliyor');
        $this->redirect_with_error('edit', __('Error updating competition', 'wevoi-competition-manager'));
        return;
    }

    // If status changed to COMPLETED, run commission calculation for Spark/Reload/Summit
    if (!empty($competition_data['competition_status']) && $competition_data['competition_status'] === 'COMPLETED') {
        $competition_code = isset($competition_data['competition_code']) ? (int) $competition_data['competition_code'] : 0;
        if ($competition_code > 0) {
            if (!class_exists('Wevoi_Commission')) {
                require_once dirname(__FILE__) . '/class-commission.php';
            }
            $commission = new Wevoi_Commission();
            $commission->run_for_competition($competition_code);
        }
    }

    // Güncelleme sonrası verileri kontrol et
    $updated_data = $this->wpdb->get_row(
        $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id),
        ARRAY_A
    );
    error_log('Güncellenmiş veri: ' . print_r($updated_data, true));

    // Başarılı güncelleme sonrası yönlendirme
    error_log('Güncelleme başarılı, liste sayfasına yönlendiriliyor');
    wp_redirect(add_query_arg(
        array(
            'page' => 'wevoi-competitions',
            'message' => 2
        ),
        admin_url('admin.php')
    ));
    exit;
}


    public function handle_delete_competition() {
        // Nonce kontrolü
        if (!isset($_POST['competition_nonce']) ||
            !wp_verify_nonce($_POST['competition_nonce'], 'delete_competition')) {
            wp_die(__('Security check failed', 'wevoi-competition-manager'));
        }

        // Yetki kontrolü
        if (!current_user_can('delete_competitions')) {
            wp_die(__('You do not have permission to delete competitions', 'wevoi-competition-manager'));
        }

        // ID kontrolü
        $ids = isset($_POST['competitions']) ? (array) $_POST['competitions'] : array();
        if (empty($ids)) {
            $this->redirect_with_error('list', __('No competitions selected', 'wevoi-competition-manager'));
            return;
        }

        // Veritabanından sil
        $ids = array_map('intval', $ids);
        $format = array_fill(0, count($ids), '%d');
        $query = "DELETE FROM {$this->table_name} WHERE id IN (" . implode(',', $format) . ")";
        $result = $this->wpdb->query($this->wpdb->prepare($query, $ids));

        if ($result === false) {
            $this->redirect_with_error('list', __('Error deleting competitions', 'wevoi-competition-manager'));
            return;
        }

        // Başarılı silme sonrası yönlendirme
        wp_redirect(add_query_arg(
            array(
                'page' => 'wevoi-competitions',
                'message' => 3
            ),
            admin_url('admin.php')
        ));
        exit;
    }


    private function sanitize_competition_data($post_data) {
        $data = array(
            'competition_code' => intval($post_data['competition_code']),
            'certificate_id_prefix' => isset($post_data['certificate_id_prefix']) && $post_data['certificate_id_prefix'] !== ''
                ? sanitize_text_field($post_data['certificate_id_prefix'])
                : null,
            'competition_name' => sanitize_text_field($post_data['competition_name']),
            'competition_date' => sanitize_text_field($post_data['competition_date']),
            'competition_status' => sanitize_text_field($post_data['competition_status']),
            'season_id' => isset($post_data['season_id']) && $post_data['season_id'] !== '' ?
                         intval($post_data['season_id']) : null,
            'competition_price' => isset($post_data['competition_price']) ?
                                 floatval($post_data['competition_price']) :
                                 0.00,
            'competition_type' => isset($post_data['competition_type']) ?
                                sanitize_text_field($post_data['competition_type']) :
                                'Qualification',
            'award_ceremony_completed' => isset($post_data['award_ceremony_completed']) ?
                                       1 : 0,
            'award_ceremony_zoom_link' => isset($post_data['award_ceremony_zoom_link']) && !empty($post_data['award_ceremony_zoom_link'])
                                       ? esc_url_raw(trim($post_data['award_ceremony_zoom_link']))
                                       : null,
            'award_ceremony_date'      => isset($post_data['award_ceremony_date']) && !empty($post_data['award_ceremony_date'])
                                       ? date('Y-m-d H:i:s', strtotime(sanitize_text_field($post_data['award_ceremony_date'])))
                                       : null,
        );

        // Add Forum-specific pricing fields
        if (isset($post_data['competition_type']) && $post_data['competition_type'] === 'Forum') {
            $data['price_supervisor'] = isset($post_data['price_supervisor']) && $post_data['price_supervisor'] !== '' ?
                                       floatval($post_data['price_supervisor']) :
                                       null;
            $data['price_parent'] = isset($post_data['price_parent']) && $post_data['price_parent'] !== '' ?
                                   floatval($post_data['price_parent']) :
                                   null;
            // Add Forum-specific event fields
            $data['event_venue'] = isset($post_data['event_venue']) && $post_data['event_venue'] !== '' ?
                                   sanitize_text_field($post_data['event_venue']) :
                                   null;
            $data['event_duration'] = isset($post_data['event_duration']) && $post_data['event_duration'] !== '' ?
                                      intval($post_data['event_duration']) :
                                      null;
        } else {
            // For non-Forum competitions, set to null
            $data['price_supervisor'] = null;
            $data['price_parent'] = null;
            $data['event_venue'] = null;
            $data['event_duration'] = null;
        }

        if (isset($post_data['competition_type']) && $post_data['competition_type'] === 'Forum') {
            $phase = isset($post_data['forum_payment_phase']) ? sanitize_text_field($post_data['forum_payment_phase']) : 'pre_registration';
            if (!in_array($phase, array('pre_registration', 'payment_required'), true)) {
                $phase = 'pre_registration';
            }
            $data['forum_payment_phase'] = $phase;
        } else {
            $data['forum_payment_phase'] = null;
        }

        return $data;
    }


    private function competition_code_exists($code, $exclude_id = null) {
        $query = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE competition_code = %d",
            $code
        );

        if ($exclude_id !== null) {
            $query .= $this->wpdb->prepare(" AND id != %d", $exclude_id);
        }

        return (bool) $this->wpdb->get_var($query);
    }

    private function redirect_with_error($action, $error_message = '') {
        $redirect_args = array(
            'page' => 'wevoi-competitions',
            'message' => 4
        );

        if ($action === 'add' || $action === 'edit') {
            $redirect_args['action'] = $action;
            if (isset($_POST['competition_id'])) {
                $redirect_args['id'] = intval($_POST['competition_id']);
            }
        }

        if (!empty($error_message)) {
            $redirect_args['error'] = urlencode($error_message);
        }

        wp_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }


    public function get_competition($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );
    }


    public function get_competitions($args = array()) {
        $defaults = array(
            'orderby' => 'competition_date',
            'order' => 'DESC',
            'status' => '',
            'search' => '',
            'limit' => 20,
            'offset' => 0
        );

        $args = wp_parse_args($args, $defaults);

        $where = "WHERE 1=1";
        $values = array();

        if (!empty($args['status'])) {
            $where .= " AND competition_status = %s";
            $values[] = $args['status'];
        }

        if (!empty($args['search'])) {
            $where .= " AND (competition_name LIKE %s OR competition_code LIKE %s)";
            $values[] = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $values[] = '%' . $this->wpdb->esc_like($args['search']) . '%';
        }

        $query = "SELECT * FROM {$this->table_name} {$where}
                 ORDER BY {$args['orderby']} {$args['order']}
                 LIMIT %d OFFSET %d";

        $values[] = $args['limit'];
        $values[] = $args['offset'];

        return $this->wpdb->get_results(
            $this->wpdb->prepare($query, $values),
            ARRAY_A
        );
    }


    public function display_students_page() {
    // Get current action
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';

    // Add navigation tabs
    ?>
    <div class="nav-tab-wrapper">
        <a href="?page=wevoi-competitions&tab=students"
           class="nav-tab <?php echo $action === 'list' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Students List', 'wevoi-competition-manager'); ?>
        </a>
        <a href="?page=wevoi-competitions&tab=students&action=statistics"
           class="nav-tab <?php echo $action === 'statistics' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Statistics', 'wevoi-competition-manager'); ?>
        </a>
    </div>
    <?php

    // Display appropriate page
    switch($action) {
        case 'statistics':
            require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/students/statistics.php';
            break;
        case 'list':
        default:
            require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/students/list.php';
            break;
    }
}

// Benzer şekilde diğer tablar için de fonksiyonlar ekleyin
public function display_supervisors_page() {
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
    ?>
    <div class="nav-tab-wrapper">
        <a href="?page=wevoi-competitions&tab=supervisors"
           class="nav-tab <?php echo $action === 'list' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Supervisors List', 'wevoi-competition-manager'); ?>
        </a>
        <a href="?page=wevoi-competitions&tab=supervisors&action=statistics"
           class="nav-tab <?php echo $action === 'statistics' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Statistics', 'wevoi-competition-manager'); ?>
        </a>
    </div>
    <?php

    switch($action) {
        case 'statistics':
            require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/supervisors/statistics.php';
            break;
        case 'list':
        default:
            require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/supervisors/list.php';
            break;
    }
}

public function display_judges_page() {
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
    ?>
    <div class="nav-tab-wrapper">
        <a href="?page=wevoi-competitions&tab=judges"
           class="nav-tab <?php echo $action === 'list' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Judges List', 'wevoi-competition-manager'); ?>
        </a>
        <a href="?page=wevoi-competitions&tab=judges&action=statistics"
           class="nav-tab <?php echo $action === 'statistics' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Statistics', 'wevoi-competition-manager'); ?>
        </a>
    </div>
    <?php

    switch($action) {
        case 'statistics':
            require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/judges/statistics.php';
            break;
        case 'list':
        default:
            require_once WEVOI_CM_PLUGIN_DIR . 'admin/views/judges/list.php';
            break;
    }
}


}
