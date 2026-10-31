<?php
/**
 * Coupons CRUD işlemleri
 */
class Wevoi_Coupons {
    private $wpdb;
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'wevoi_coupons';


        // Form işlemleri için hook'ları ekle
        add_action('admin_post_add_coupon', array($this, 'handle_add_coupon'));
        add_action('admin_post_edit_coupon', array($this, 'handle_edit_coupon'));
        add_action('admin_post_delete_coupon', array($this, 'handle_delete_coupon'));
    }

public function get_coupon_actions($item) {
    $actions = array();

    // Diğer mevcut action'ları ekleyin
    $actions[] = sprintf(
        '<a href="%s" class="button button-small">%s</a>',
        add_query_arg(
            array(
                'page'      => 'wevoi-competitions',
                'tab'       => 'invoices',
                'action'    => 'create',
                'coupon_id' => $item->id
            ),
            admin_url('admin.php')
        ),
        __('Invoice', 'wevoi-competition-manager')
    );

    return $actions;
}


    /**
     * Yeni kupon ekleme
     */
    public function handle_add_coupon() {
        // Nonce kontrolü
        if (!isset($_POST['coupon_nonce']) ||
            !wp_verify_nonce($_POST['coupon_nonce'], 'save_coupon')) {
            wp_die(__('Security check failed', 'wevoi-competition-manager'));
        }

        // Yetki kontrolü
        if (!current_user_can('create_coupons')) {
            wp_die(__('You do not have permission to create coupons', 'wevoi-competition-manager'));
        }

        // Form verilerini sanitize et
        $coupon_data = $this->sanitize_coupon_data($_POST);

        // Kupon kodu benzersizlik kontrolü
        if ($this->coupon_code_exists($coupon_data['coupon_code'])) {
            $this->redirect_with_error('add', __('Coupon code already exists', 'wevoi-competition-manager'));
            return;
        }

        // Veritabanına ekle
        $result = $this->wpdb->insert(
            $this->table_name,
            $coupon_data,
            $this->get_data_format()
        );

        if ($result === false) {
            $this->redirect_with_error('add');
            return;
        }

        // Başarılı ekleme sonrası yönlendirme
        wp_redirect(add_query_arg(
            array(
                'page' => 'wevoi-competitions',
                'tab' => 'coupons',
                'message' => 1,
                'competition_code' => $coupon_data['competition_code']
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * Kupon düzenleme
     */
    public function handle_edit_coupon() {
        // Nonce kontrolü
        if (!isset($_POST['coupon_nonce']) ||
            !wp_verify_nonce($_POST['coupon_nonce'], 'save_coupon')) {
            wp_die(__('Security check failed', 'wevoi-competition-manager'));
        }

        // Yetki kontrolü
        if (!current_user_can('edit_coupons')) {
            wp_die(__('You do not have permission to edit coupons', 'wevoi-competition-manager'));
        }

        // ID kontrolü
        if (!isset($_POST['coupon_id'])) {
            wp_die(__('Coupon ID is required', 'wevoi-competition-manager'));
        }

        $id = intval($_POST['coupon_id']);

        // Form verilerini sanitize et
        $coupon_data = $this->sanitize_coupon_data($_POST);

        // Kupon kodu benzersizlik kontrolü (kendi ID'si hariç)
        if ($this->coupon_code_exists($coupon_data['coupon_code'], $id)) {
            $this->redirect_with_error('edit', __('Coupon code already exists', 'wevoi-competition-manager'));
            return;
        }

        // Veritabanında güncelle
        $result = $this->wpdb->update(
            $this->table_name,
            $coupon_data,
            array('id' => $id),
            $this->get_data_format(),
            array('%d')
        );

        if ($result === false) {
            $this->redirect_with_error('edit');
            return;
        }

        // Başarılı güncelleme sonrası yönlendirme
        wp_redirect(add_query_arg(
            array(
                'page' => 'wevoi-competitions',
                'tab' => 'coupons',
                'message' => 2,
                'competition_code' => $coupon_data['competition_code']
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    public function handle_delete_coupon() {
        error_log('Delete handler başladı'); // Debug log

        // Tekli silme
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
            error_log('Delete işlemi için veriler: ' . print_r($_GET, true)); // Debug log

            // Nonce kontrolü
            check_admin_referer('delete_coupon_' . $_GET['id']);

            // Yetki kontrolü
            if (!current_user_can('manage_options')) { // delete_coupons yerine manage_options kullanıyoruz
                wp_die(__('You do not have permission to delete coupons.', 'wevoi-competition-manager'));
            }

            $coupon_id = intval($_GET['id']);

            // Kullanılmış kupon kontrolü
            $coupon = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT coupon_used FROM {$this->table_name} WHERE id = %d",
                $coupon_id
            ));

            if ($coupon && $coupon->coupon_used > 0) {
                wp_redirect(add_query_arg(
                    array(
                        'page' => 'wevoi-competitions',
                        'tab' => 'coupons',
                        'message' => 'error',
                        'error' => 'cannot_delete_used_coupon'
                    ),
                    admin_url('admin.php')
                ));
                exit;
            }

            error_log('Silme sorgusu başlıyor. ID: ' . $coupon_id); // Debug log

            // Kuponu sil
            $result = $this->wpdb->delete(
                $this->table_name,
                array('id' => $coupon_id),
                array('%d')
            );

            error_log('Silme sonucu: ' . ($result !== false ? 'Başarılı' : 'Başarısız')); // Debug log

            // Redirect
            wp_redirect(add_query_arg(
                array(
                    'page' => 'wevoi-competitions',
                    'tab' => 'coupons',
                    'message' => ($result !== false) ? 3 : 4
                ),
                admin_url('admin.php')
            ));




            exit;
        }

        // Toplu silme
        if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['coupons'])) {
            // Nonce kontrolü
            check_admin_referer('bulk-coupons');

            // Yetki kontrolü
            if (!current_user_can('delete_coupons')) {
                wp_die(__('You do not have permission to delete coupons.', 'wevoi-competition-manager'));
            }

            $coupon_ids = array_map('intval', $_POST['coupons']);

            // Kullanılmamış kuponları filtrele
            $unused_coupons = $this->wpdb->get_col($this->wpdb->prepare(
                "SELECT id FROM {$this->table_name}
                 WHERE id IN (" . implode(',', array_fill(0, count($coupon_ids), '%d')) . ")
                 AND coupon_used = 0",
                $coupon_ids
            ));

            if (!empty($unused_coupons)) {
                // Kullanılmamış kuponları sil
                $result = $this->wpdb->query(
                    "DELETE FROM {$this->table_name}
                     WHERE id IN (" . implode(',', array_map('intval', $unused_coupons)) . ")"
                );
            }

            // Redirect
            wp_redirect(add_query_arg(
                array(
                    'page' => 'wevoi-competitions',
                    'tab' => 'coupons',
                    'message' => (!empty($unused_coupons) && $result !== false) ? 3 : 4,
                    'deleted' => count($unused_coupons)
                ),
                admin_url('admin.php')
            ));
            exit;

    }


   }


    /**
     * Form verilerini sanitize et
     */
private function sanitize_coupon_data($post_data) {
    // İlk veri dizisini oluştur
    $data = array(
        'coupon_code' => sanitize_text_field($post_data['coupon_code']),
        'competition_code' => intval($post_data['competition_code'])
    );

    // Role field (NULL = all roles, 'student'/'supervisor'/'parent' = specific role)
    if (isset($post_data['coupon_role']) && !empty($post_data['coupon_role'])) {
        $role = sanitize_text_field($post_data['coupon_role']);
        if (in_array($role, ['student', 'supervisor', 'parent'])) {
            $data['role'] = $role;
        } else {
            $data['role'] = null;
        }
    } else {
        $data['role'] = null;
    }

    // Limit türüne göre veri ekle
    if ($post_data['limit_type'] === 'usage') {
        $data['coupon_limit'] = intval($post_data['coupon_limit']);
        $data['coupon_limit_expiration_date'] = null;
    } else {
        $data['coupon_limit'] = null;
        $data['coupon_limit_expiration_date'] = sanitize_text_field($post_data['coupon_limit_expiration_date']);
    }

    // İndirim türüne göre veri ekle
    if ($post_data['discount_type'] === 'amount') {
        $data['coupon_discount_amount'] = floatval($post_data['coupon_discount_amount']);
        $data['coupon_discount_percentage'] = null;
    } else {
        $data['coupon_discount_amount'] = null;
        $data['coupon_discount_percentage'] = intval($post_data['coupon_discount_percentage']);
    }

    // Comments alanını en son ekle
    $data['coupon_comments'] = sanitize_textarea_field($post_data['coupon_comments']);

    return $data;
}

    /**
     * Veri formatlarını al
     */
    private function get_data_format() {
        return array(
            '%s', // coupon_code
            '%d', // competition_code
            '%s', // role (NULL or 'student'/'supervisor'/'parent')
            '%d', // coupon_limit
            '%s', // coupon_limit_expiration_date
            '%f', // coupon_discount_amount
            '%d',  // coupon_discount_percentage
            '%s'  // coupon_comments
        );
    }

    /**
     * Kupon kodu kontrolü
     */
    private function coupon_code_exists($code, $exclude_id = null) {
        $query = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE coupon_code = %s",
            $code
        );

        if ($exclude_id !== null) {
            $query .= $this->wpdb->prepare(" AND id != %d", $exclude_id);
        }

        return (bool) $this->wpdb->get_var($query);
    }

    /**
     * Hata durumunda yönlendirme
     */
    private function redirect_with_error($action, $error_message = '') {
        $redirect_args = array(
            'page' => 'wevoi-competitions',
            'tab' => 'coupons',
            'message' => 4
        );

        if ($action === 'add' || $action === 'edit') {
            $redirect_args['action'] = $action;
            if (isset($_POST['coupon_id'])) {
                $redirect_args['id'] = intval($_POST['coupon_id']);
            }
        }

        if (!empty($error_message)) {
            $redirect_args['error'] = urlencode($error_message);
        }

        wp_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;



    }
}
