<?php
class Wevoi_Email_Snippets {
    private $wpdb;
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'wevoi_email_snippets';

        add_action('admin_post_add_email_snippet', array($this, 'handle_add_snippet'));
        add_action('admin_post_edit_email_snippet', array($this, 'handle_edit_snippet'));
        add_action('admin_post_delete_email_snippet', array($this, 'handle_delete_snippet'));
        add_action('wp_ajax_get_snippet_content', array($this, 'get_snippet_content'));
        add_action('wp_ajax_delete_snippet_ajax', array($this, 'ajax_delete_snippet'));
        
    }

public function get_snippets($target_group = '') {
    $sql = "SELECT * FROM {$this->table_name}";
    if (!empty($target_group)) {
        $sql .= $this->wpdb->prepare(" WHERE target_group = %s", $target_group);
    }
    $sql .= " ORDER BY id ASC"; // Change from snippet_name to id ASC
    
    return $this->wpdb->get_results($sql);
}

    public function get_snippet($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $id
            )
        );
    }

    public function handle_add_snippet() {
        check_admin_referer('save_email_snippet', 'snippet_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'wevoi-competition-manager'));
        }

        $snippet_data = array(
            'snippet_name' => sanitize_text_field($_POST['snippet_name']),
            'target_group' => sanitize_text_field($_POST['target_group']),
            'snippet_text' => wp_kses_post($_POST['snippet_text'])
        );

        $result = $this->wpdb->insert(
            $this->table_name,
            $snippet_data,
            array('%s', '%s', '%s')
        );

        wp_redirect(add_query_arg(
            array(
                'page' => 'wevoi-competitions',
                'tab' => 'email-snippets',
                'message' => ($result !== false) ? 1 : 4
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    public function handle_edit_snippet() {
        check_admin_referer('save_email_snippet', 'snippet_nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'wevoi-competition-manager'));
        }

        $id = isset($_POST['snippet_id']) ? intval($_POST['snippet_id']) : 0;
        if (!$id) {
            wp_die(__('Invalid snippet ID', 'wevoi-competition-manager'));
        }

        $snippet_data = array(
            'snippet_name' => sanitize_text_field($_POST['snippet_name']),
            'target_group' => sanitize_text_field($_POST['target_group']),
            'snippet_text' => wp_kses_post($_POST['snippet_text'])
        );

        $result = $this->wpdb->update(
            $this->table_name,
            $snippet_data,
            array('id' => $id),
            array('%s', '%s', '%s'),
            array('%d')
        );

        wp_redirect(add_query_arg(
            array(
                'page' => 'wevoi-competitions',
                'tab' => 'email-snippets',
                'message' => ($result !== false) ? 2 : 4
            ),
            admin_url('admin.php')
        ));
        exit;
    }

public function handle_delete_snippet() {
    // admin-post.php üzerinden çağrılabilmesi için action parametresini kontrol et
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
    if ($action !== 'delete') {
        wp_die(__('Invalid action', 'wevoi-competition-manager'));
    }
    
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$id) {
        wp_die(__('Invalid snippet ID', 'wevoi-competition-manager'));
    }
    
    // Nonce kontrolü
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_snippet_' . $id)) {
        wp_die(__('Security check failed. The link has expired. Please go back and try again.', 'wevoi-competition-manager'));
    }
    
    if (!current_user_can('manage_options')) {
        wp_die(__('Permission denied', 'wevoi-competition-manager'));
    }

    $result = $this->wpdb->delete(
        $this->table_name,
        array('id' => $id),
        array('%d')
    );

    wp_redirect(add_query_arg(
        array(
            'page' => 'wevoi-competitions',
            'tab' => 'email-snippets',
            'message' => ($result !== false) ? 3 : 4
        ),
        admin_url('admin.php')
    ));
    exit;
}


/**
 * Handle AJAX snippet deletion
 */
public function ajax_delete_snippet() {
    // Nonce check
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'delete_snippet_' . $_POST['id'])) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    // Permission check
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
        return;
    }
    
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$id) {
        wp_send_json_error('Invalid snippet ID');
        return;
    }
    
    $result = $this->wpdb->delete(
        $this->table_name,
        array('id' => $id),
        array('%d')
    );
    
    if ($result !== false) {
        wp_send_json_success('Snippet deleted successfully');
    } else {
        wp_send_json_error('Failed to delete snippet');
    }
}

    public function get_snippet_content() {
    check_ajax_referer('get_snippet_content', 'nonce', false);
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
        return;
    }

    $snippet_id = isset($_POST['snippet_id']) ? intval($_POST['snippet_id']) : 0;
    if (!$snippet_id) {
        wp_send_json_error('Invalid snippet ID');
        return;
    }

    $snippet = $this->get_snippet($snippet_id);
    if ($snippet) {
        wp_send_json_success(array(
            'text' => $snippet->snippet_text,
            'name' => $snippet->snippet_name // Internal name değerini de gönder
        ));
    } else {
        wp_send_json_error('Snippet not found');
    }
}
}