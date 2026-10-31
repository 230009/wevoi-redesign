<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$p = $wpdb->prefix;
$seasons = $wpdb->get_results("SELECT * FROM {$p}wevoi_seasons ORDER BY season_id DESC", ARRAY_A);
$message = isset($_GET['message']) ? intval($_GET['message']) : 0;
$error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';

if ($message === 1) {
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Season added successfully.', 'wevoi-competition-manager') . '</p></div>';
} elseif ($message === 2) {
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Season updated successfully.', 'wevoi-competition-manager') . '</p></div>';
} elseif ($message === 3) {
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Season deleted successfully.', 'wevoi-competition-manager') . '</p></div>';
} elseif ($message === 4) {
    if ($error === 'season_in_use') {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('This season cannot be deleted because it is used in commission rates or commission payments.', 'wevoi-competition-manager') . '</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('An error occurred.', 'wevoi-competition-manager') . '</p></div>';
    }
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Seasons', 'wevoi-competition-manager'); ?></h1>
    <a href="?page=wevoi-competitions&tab=seasons&action=add" class="page-title-action"><?php _e('Add Season', 'wevoi-competition-manager'); ?></a>
    <hr class="wp-header-end">

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="column-season_id"><?php _e('ID', 'wevoi-competition-manager'); ?></th>
                <th scope="col" class="column-season_name"><?php _e('Season Name', 'wevoi-competition-manager'); ?></th>
                <th scope="col" class="column-season_abv"><?php _e('Season (e.g. 25-26)', 'wevoi-competition-manager'); ?></th>
                <th scope="col" class="column-min_students"><?php _e('Min. students (commission)', 'wevoi-competition-manager'); ?></th>
                <th scope="col" class="column-is_active"><?php _e('Active', 'wevoi-competition-manager'); ?></th>
                <th scope="col" class="column-actions"><?php _e('Actions', 'wevoi-competition-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($seasons)): ?>
                <?php foreach ($seasons as $s): ?>
                    <tr>
                        <td><?php echo esc_html($s['season_id']); ?></td>
                        <td><strong><?php echo esc_html($s['season_name']); ?></strong></td>
                        <td><?php echo esc_html($s['season_abv']); ?></td>
                        <td><?php echo isset($s['min_students_commission']) && $s['min_students_commission'] !== '' && $s['min_students_commission'] !== null ? esc_html((int) $s['min_students_commission']) : '—'; ?></td>
                        <td><?php echo !empty($s['is_active']) ? '✓' : '—'; ?></td>
                        <td>
                            <a href="?page=wevoi-competitions&tab=seasons&action=edit&id=<?php echo (int) $s['season_id']; ?>" class="button button-small"><?php _e('Edit', 'wevoi-competition-manager'); ?></a>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=wevoi-competitions&tab=seasons&action=delete&id=' . (int) $s['season_id']), 'delete_season_' . (int) $s['season_id'])); ?>"
                               class="button button-small button-link-delete"
                               onclick="return confirm('<?php echo esc_attr(__('Delete this season?', 'wevoi-competition-manager')); ?>');"><?php _e('Delete', 'wevoi-competition-manager'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6"><?php _e('No seasons found. Add one to use with competitions and commission rates.', 'wevoi-competition-manager'); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
