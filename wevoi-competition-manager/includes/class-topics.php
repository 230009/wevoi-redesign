<?php

class Wevoi_Topics {
    private $wpdb;
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'wevoi_topics';

        // Form handlers
        add_action('admin_post_add_topic', array($this, 'handle_add_topic'));
        add_action('admin_post_edit_topic', array($this, 'handle_edit_topic'));
        add_action('admin_post_delete_topic', array($this, 'handle_delete_topic'));
        add_action('admin_post_bulk_delete_topics', array($this, 'handle_bulk_delete_topics'));
    }

    /**
     * Handle add topic form submission
     */
    public function handle_add_topic() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['topic_nonce'], 'save_topic')) {
            wp_die(__('Security check failed.', 'wevoi-competition-manager'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'wevoi-competition-manager'));
        }

        // Sanitize and validate input
        $topic_data = [
            'competition_code' => intval($_POST['competition_code']),
            'wevoi_age_category' => sanitize_text_field($_POST['wevoi_age_category']),
            'topic' => sanitize_textarea_field($_POST['topic'])
        ];

        // Validation
        if (empty($topic_data['competition_code']) || empty($topic_data['wevoi_age_category']) || empty($topic_data['topic'])) {
            $this->redirect_with_error('add', __('All fields are required.', 'wevoi-competition-manager'));
            return;
        }

        // Check if topic already exists for this competition and age category
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE competition_code = %d AND wevoi_age_category = %s",
            $topic_data['competition_code'],
            $topic_data['wevoi_age_category']
        ));

        if ($existing) {
            $this->redirect_with_error('add', __('A topic already exists for this competition and age category.', 'wevoi-competition-manager'));
            return;
        }

        // Insert topic
        $result = $this->wpdb->insert(
            $this->table_name,
            $topic_data,
            ['%d', '%s', '%s']
        );

        if ($result === false) {
            $this->redirect_with_error('add', __('Failed to add topic.', 'wevoi-competition-manager'));
            return;
        }

        // Success redirect
        wp_redirect(add_query_arg([
            'page' => 'wevoi-competitions',
            'tab' => 'topics',
            'message' => 1
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Handle edit topic form submission
     */
    public function handle_edit_topic() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['topic_nonce'], 'save_topic')) {
            wp_die(__('Security check failed.', 'wevoi-competition-manager'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'wevoi-competition-manager'));
        }

        $topic_id = intval($_POST['topic_id']);
        if (!$topic_id) {
            $this->redirect_with_error('edit', __('Invalid topic ID.', 'wevoi-competition-manager'));
            return;
        }

        // Sanitize and validate input
        $topic_data = [
            'competition_code' => intval($_POST['competition_code']),
            'wevoi_age_category' => sanitize_text_field($_POST['wevoi_age_category']),
            'topic' => sanitize_textarea_field($_POST['topic'])
        ];

        // Validation
        if (empty($topic_data['competition_code']) || empty($topic_data['wevoi_age_category']) || empty($topic_data['topic'])) {
            $this->redirect_with_error('edit', __('All fields are required.', 'wevoi-competition-manager'));
            return;
        }

        // Check if topic already exists for this competition and age category (excluding current topic)
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE competition_code = %d AND wevoi_age_category = %s AND id != %d",
            $topic_data['competition_code'],
            $topic_data['wevoi_age_category'],
            $topic_id
        ));

        if ($existing) {
            $this->redirect_with_error('edit', __('A topic already exists for this competition and age category.', 'wevoi-competition-manager'));
            return;
        }

        // Update topic
        $result = $this->wpdb->update(
            $this->table_name,
            $topic_data,
            ['id' => $topic_id],
            ['%d', '%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            $this->redirect_with_error('edit', __('Failed to update topic.', 'wevoi-competition-manager'));
            return;
        }

        // Success redirect
        wp_redirect(add_query_arg([
            'page' => 'wevoi-competitions',
            'tab' => 'topics',
            'message' => 2
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Handle delete topic
     */
    public function handle_delete_topic() {
        $topic_id = intval($_GET['id']);

        if (!$topic_id) {
            wp_die(__('Invalid topic ID.', 'wevoi-competition-manager'));
        }

        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_topic_' . $topic_id)) {
            wp_die(__('Security check failed.', 'wevoi-competition-manager'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'wevoi-competition-manager'));
        }

        // Delete topic
        $result = $this->wpdb->delete(
            $this->table_name,
            ['id' => $topic_id],
            ['%d']
        );

        // Redirect
        wp_redirect(add_query_arg([
            'page' => 'wevoi-competitions',
            'tab' => 'topics',
            'message' => ($result !== false) ? 3 : 4
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Handle bulk delete topics
     */
    public function handle_bulk_delete_topics() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'bulk-topics')) {
            wp_die(__('Security check failed.', 'wevoi-competition-manager'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'wevoi-competition-manager'));
        }

        if (!isset($_POST['topic']) || !is_array($_POST['topic'])) {
            wp_redirect(add_query_arg([
                'page' => 'wevoi-competitions',
                'tab' => 'topics',
                'message' => 4
            ], admin_url('admin.php')));
            exit;
        }

        $topic_ids = array_map('intval', $_POST['topic']);
        $deleted = 0;

        foreach ($topic_ids as $topic_id) {
            $result = $this->wpdb->delete(
                $this->table_name,
                ['id' => $topic_id],
                ['%d']
            );

            if ($result !== false) {
                $deleted++;
            }
        }

        // Redirect
        wp_redirect(add_query_arg([
            'page' => 'wevoi-competitions',
            'tab' => 'topics',
            'message' => ($deleted > 0) ? 3 : 4,
            'deleted' => $deleted
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Get topic by ID
     */
    public function get_topic($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ), ARRAY_A);
    }

    /**
     * Get all competitions for dropdown
     */
    public function get_competitions() {
        return $this->wpdb->get_results(
            "SELECT competition_code, competition_name FROM {$this->wpdb->prefix}wevoi_competitions ORDER BY competition_code DESC",
            ARRAY_A
        );
    }

    /**
     * Get age categories from wpie_wevoi_age_categories.
     */
    public function get_age_categories() {
        return wevoi_get_age_categories();
    }

    /**
     * Redirect with error
     */
    private function redirect_with_error($action, $error_message = '') {
        $redirect_args = [
            'page' => 'wevoi-competitions',
            'tab' => 'topics',
            'message' => 4
        ];

        if ($action === 'add' || $action === 'edit') {
            $redirect_args['action'] = $action;
        }

        if (!empty($error_message)) {
            $redirect_args['error'] = urlencode($error_message);
        }

        wp_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }
}
