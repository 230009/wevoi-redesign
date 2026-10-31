<?php
/**
 * Judge Repository
 *
 * @package Wevoi_Competition_Manager
 * @subpackage Judges
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Judge repository class.
 */
class Wevoi_Judge_Repository {
    /**
     * Get judges list.
     *
     * @param array $args Query arguments.
     * @return array
     */
    public function get_judges($args = array()) {
        global $wpdb;

        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'user_registered',
            'order' => 'DESC',
            'search' => '',
            'country' => 0,
            'school' => 0,
            'evaluation_status' => ''
        );

        $args = wp_parse_args($args, $defaults);

        // Check if filtering is applied
        $is_filtered = !empty($args['search']) || !empty($args['country']) || !empty($args['school']) || !empty($args['evaluation_status']);

        // Items per page - use all items if per_page is -1
        $per_page = ($args['per_page'] === -1) ? 9999 : ($is_filtered ? 9999 : $args['per_page']);

        // Page number
        $page = absint($args['page']);
        if ($page < 1) {
            $page = 1;
        }

        // Calculate offset
        $offset = ($page - 1) * $per_page;

        // Build SQL query
        // Only show evaluations from competitions with EVALUATING status
        $sql_select = "SELECT DISTINCT u.*,
                   um_country.meta_value as countryid,
                   c.countryname,
                   um_school.meta_value as schoolid,
                   s.schoolname,
                   COUNT(DISTINCT CASE WHEN comp.competition_code IS NOT NULL THEN ja.assignment_id END) as assignment_count,
                   COUNT(DISTINCT CASE WHEN je.evaluation_id IS NOT NULL AND comp.competition_code IS NOT NULL THEN je.evaluation_id END) as evaluated_count";

        $sql_from = " FROM {$wpdb->users} u
                  LEFT JOIN {$wpdb->usermeta} um_role ON u.ID = um_role.user_id AND um_role.meta_key = '{$wpdb->prefix}capabilities'
                  LEFT JOIN {$wpdb->usermeta} um_country ON u.ID = um_country.user_id AND um_country.meta_key = 'countryid'
                  LEFT JOIN {$wpdb->usermeta} um_school ON u.ID = um_school.user_id AND um_school.meta_key = 'schoolid'
                  LEFT JOIN {$wpdb->prefix}wevoi_countries c ON c.countryid = um_country.meta_value
                  LEFT JOIN {$wpdb->prefix}wevoi_schools s ON s.schoolid = um_school.meta_value
                  LEFT JOIN {$wpdb->prefix}judge_assignments ja ON u.ID = ja.judge_id
                  LEFT JOIN {$wpdb->prefix}wevoi_competitions comp ON ja.competition_code = comp.competition_code AND comp.competition_status = 'EVALUATING'
                  LEFT JOIN {$wpdb->prefix}judge_evaluations je ON ja.assignment_id = je.assignment_id AND je.judge_id = ja.judge_id";

        $sql_where = " WHERE um_role.meta_value LIKE '%\"judge\"%'";

        // Add search condition
        if (!empty($args['search'])) {
            $sql_where .= $wpdb->prepare(
                " AND (u.display_name LIKE %s OR u.user_email LIKE %s)",
                '%' . $wpdb->esc_like($args['search']) . '%',
                '%' . $wpdb->esc_like($args['search']) . '%'
            );
        }

        // Add country filter
        if (!empty($args['country'])) {
            $sql_where .= $wpdb->prepare(" AND um_country.meta_value = %d", $args['country']);
        }

        // Add school filter
        if (!empty($args['school'])) {
            $sql_where .= $wpdb->prepare(" AND um_school.meta_value = %d", $args['school']);
        }

        // Group by user ID
        $sql_group = " GROUP BY u.ID";

        // Add evaluation status filter
        $sql_having = "";
        if (!empty($args['evaluation_status'])) {
            switch ($args['evaluation_status']) {
                case 'not_started':
                    $sql_having = " HAVING assignment_count > 0 AND evaluated_count = 0";
                    break;
                case 'evaluating':
                    $sql_having = " HAVING evaluated_count > 0 AND evaluated_count < assignment_count";
                    break;
                case 'completed':
                    $sql_having = " HAVING assignment_count > 0 AND evaluated_count = assignment_count";
                    break;
            }
        }

        // Add order
        $allowed_orderby = array('user_registered', 'display_name', 'assignment_count', 'evaluated_count');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'user_registered';
        $order = ($args['order'] === 'ASC') ? 'ASC' : 'DESC';

        $sql_order = " ORDER BY $orderby $order";

        // Add limit
        $sql_limit = $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, $offset);

        // Get count of total items
        $count_sql = "SELECT COUNT(*) FROM (SELECT u.ID $sql_from $sql_where $sql_group $sql_having) AS filtered_judges";
        $total_items = $wpdb->get_var($count_sql);

        // Get items
        $items = $wpdb->get_results($sql_select . $sql_from . $sql_where . $sql_group . $sql_having . $sql_order . $sql_limit);

        // Convert to Judge objects
        $judges = array();
        foreach ($items as $item) {
            $judges[] = new Wevoi_Judge($item);
        }

        return array(
            'items' => $judges,
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page),
            'current_page' => $page
        );
    }

    /**
     * Get judge by ID.
     *
     * @param int $judge_id Judge ID.
     * @return Wevoi_Judge|false
     */
    public function get_judge($judge_id) {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT u.*,
                um_country.meta_value as countryid,
                c.countryname,
                um_school.meta_value as schoolid,
                s.schoolname
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um_country ON u.ID = um_country.user_id AND um_country.meta_key = 'countryid'
            LEFT JOIN {$wpdb->usermeta} um_school ON u.ID = um_school.user_id AND um_school.meta_key = 'schoolid'
            LEFT JOIN {$wpdb->prefix}wevoi_countries c ON c.countryid = um_country.meta_value
            LEFT JOIN {$wpdb->prefix}wevoi_schools s ON s.schoolid = um_school.meta_value
            WHERE u.ID = %d",
            $judge_id
        );

        $data = $wpdb->get_row($sql);

        if (!$data) {
            return false;
        }

        // Get assignments and evaluations
        $data->assignment_count = $this->get_judge_assignment_count($judge_id);
        $data->evaluated_count = $this->get_judge_evaluation_count($judge_id);

        return new Wevoi_Judge($data);
    }

    /**
     * Get judge assignments.
     * Only show assignments from EVALUATING competitions.
     * Include student information.
     *
     * @param int $judge_id Judge ID.
     * @return array
     */
    public function get_judge_assignments($judge_id) {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT ja.*,
                    c.competition_name,
                    c.competition_status,
                    je.evaluation_id,
                    je.total_score,
                    je.evaluation_date,
                    r.register_id,
                    u.ID as student_id,
                    u.display_name as student_display_name,
                    u.user_email as student_email,
                    um_first.meta_value as student_first_name,
                    um_last.meta_value as student_last_name
             FROM {$wpdb->prefix}judge_assignments ja
             INNER JOIN {$wpdb->prefix}wevoi_competitions c ON ja.competition_code = c.competition_code AND c.competition_status = 'EVALUATING'
             LEFT JOIN {$wpdb->prefix}wevoi_registrations r ON ja.register_id = r.register_id AND r.competition_code = c.competition_code
             LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
             LEFT JOIN {$wpdb->usermeta} um_first ON u.ID = um_first.user_id AND um_first.meta_key = 'first_name'
             LEFT JOIN {$wpdb->usermeta} um_last ON u.ID = um_last.user_id AND um_last.meta_key = 'last_name'
             LEFT JOIN {$wpdb->prefix}judge_evaluations je ON ja.assignment_id = je.assignment_id AND je.judge_id = ja.judge_id
             WHERE ja.judge_id = %d
             ORDER BY ja.assignment_date DESC",
            $judge_id
        );

        return $wpdb->get_results($sql);
    }

    /**
     * Get judge certificates.
     *
     * @param int $judge_id Judge ID.
     * @return array
     */
    public function get_judge_certificates($judge_id) {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT jc.*, c.competition_name
             FROM {$wpdb->prefix}judge_certificates jc
             LEFT JOIN {$wpdb->prefix}wevoi_competitions c ON jc.competition_code = c.competition_code
             WHERE jc.judge_id = %d
             ORDER BY jc.issue_date DESC",
            $judge_id
        );

        return $wpdb->get_results($sql);
    }

    /**
     * Get judge assignment count.
     * Only count assignments from competitions with EVALUATING status.
     *
     * @param int $judge_id Judge ID.
     * @return int
     */
    public function get_judge_assignment_count($judge_id) {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT COUNT(ja.assignment_id)
             FROM {$wpdb->prefix}judge_assignments ja
             JOIN {$wpdb->prefix}wevoi_competitions comp ON ja.competition_code = comp.competition_code
             WHERE ja.judge_id = %d
             AND comp.competition_status = 'EVALUATING'",
            $judge_id
        );

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Get judge evaluation count.
     * Only count evaluations from competitions with EVALUATING status.
     *
     * @param int $judge_id Judge ID.
     * @return int
     */
    public function get_judge_evaluation_count($judge_id) {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT COUNT(je.evaluation_id)
             FROM {$wpdb->prefix}judge_evaluations je
             JOIN {$wpdb->prefix}judge_assignments ja ON je.assignment_id = ja.assignment_id
             JOIN {$wpdb->prefix}wevoi_competitions comp ON ja.competition_code = comp.competition_code
             WHERE ja.judge_id = %d
             AND comp.competition_status = 'EVALUATING'",
            $judge_id
        );

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Update judge.
     *
     * @param int $judge_id Judge ID.
     * @param array $data Judge data.
     * @return bool
     */
    public function update_judge($judge_id, $data) {
        global $wpdb;

        $success = true;

        // Begin transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Update user data
            $user_data = array(
                'ID' => $judge_id
            );

            if (isset($data['email'])) {
                $user_data['user_email'] = sanitize_email($data['email']);
            }

            $result = wp_update_user($user_data);

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            // Update user meta
            if (isset($data['first_name'])) {
                update_user_meta($judge_id, 'first_name', sanitize_text_field($data['first_name']));
            }

            if (isset($data['last_name'])) {
                update_user_meta($judge_id, 'last_name', sanitize_text_field($data['last_name']));
            }

            // Commit transaction
            $wpdb->query('COMMIT');

        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            $success = false;
        }

        return $success;
    }

    /**
     * Delete judge.
     *
     * @param int $judge_id Judge ID.
     * @return bool
     */
    public function delete_judge($judge_id) {
        global $wpdb;

        $success = true;

        // Begin transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Delete judge assignments
            $wpdb->delete(
                $wpdb->prefix . 'judge_assignments',
                array('judge_id' => $judge_id),
                array('%d')
            );

            // Delete judge evaluations (through assignments)
            $wpdb->query($wpdb->prepare(
                "DELETE je
                 FROM {$wpdb->prefix}judge_evaluations je
                 JOIN {$wpdb->prefix}judge_assignments ja ON je.assignment_id = ja.assignment_id
                 WHERE ja.judge_id = %d",
                $judge_id
            ));

            // Delete judge certificates
            $wpdb->delete(
                $wpdb->prefix . 'judge_certificates',
                array('judge_id' => $judge_id),
                array('%d')
            );

            // Delete user
            $result = wp_delete_user($judge_id);

            if (!$result) {
                throw new Exception(__('Failed to delete user', 'wevoi-competition-manager'));
            }

            // Commit transaction
            $wpdb->query('COMMIT');

        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            $success = false;
        }

        return $success;
    }
}
