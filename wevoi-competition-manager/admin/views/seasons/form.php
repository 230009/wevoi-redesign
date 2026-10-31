<?php
if (!defined('ABSPATH')) {
    exit;
}

$season = null;
$is_edit = isset($_GET['action']) && $_GET['action'] === 'edit' && !empty($_GET['id']);

if ($is_edit) {
    global $wpdb;
    $season = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}wevoi_seasons WHERE season_id = %d",
        (int) $_GET['id']
    ), ARRAY_A);
    if (!$season) {
        wp_die(__('Season not found.', 'wevoi-competition-manager'));
    }
}
?>

<div class="wrap">
    <h1><?php echo $is_edit ? __('Edit Season', 'wevoi-competition-manager') : __('Add Season', 'wevoi-competition-manager'); ?></h1>
    <form method="post" action="">
        <?php wp_nonce_field($is_edit ? 'update_season_' . (int) $season['season_id'] : 'add_season', 'season_nonce'); ?>
        <input type="hidden" name="season_action" value="<?php echo $is_edit ? 'update' : 'add'; ?>">
        <?php if ($is_edit): ?>
            <input type="hidden" name="season_id" value="<?php echo (int) $season['season_id']; ?>">
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th><label for="season_name"><?php _e('Season Name', 'wevoi-competition-manager'); ?> <span class="required">*</span></label></th>
                <td>
                    <input type="text" name="season_name" id="season_name" class="regular-text" required
                           value="<?php echo $is_edit ? esc_attr($season['season_name']) : ''; ?>"
                           placeholder="e.g. 2025-2026">
                </td>
            </tr>
            <tr>
                <th><label for="season_abv"><?php _e('Season Abbreviation', 'wevoi-competition-manager'); ?> <span class="required">*</span></label></th>
                <td>
                    <input type="text" name="season_abv" id="season_abv" class="regular-text" required
                           value="<?php echo $is_edit ? esc_attr($season['season_abv']) : ''; ?>"
                           placeholder="e.g. 25-26">
                    <p class="description"><?php _e('Short form, e.g. 25-26 for 2025-2026', 'wevoi-competition-manager'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="min_students_commission"><?php _e('Min. students for commission', 'wevoi-competition-manager'); ?></label></th>
                <td>
                    <input type="number" name="min_students_commission" id="min_students_commission" class="small-text" min="0" step="1"
                           value="<?php echo $is_edit ? esc_attr(isset($season['min_students_commission']) ? $season['min_students_commission'] : '10') : '10'; ?>">
                    <p class="description"><?php _e('Supervisor/Agency must have at least this many PAID students in the season total to receive any commission. 0 = no minimum.', 'wevoi-competition-manager'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="is_active"><?php _e('Active', 'wevoi-competition-manager'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="is_active" id="is_active" value="1" <?php checked($is_edit ? $season['is_active'] : 0, 1); ?>>
                        <?php _e('This season is active', 'wevoi-competition-manager'); ?>
                    </label>
                </td>
            </tr>
        </table>
        <p class="submit">
            <?php submit_button($is_edit ? __('Update Season', 'wevoi-competition-manager') : __('Add Season', 'wevoi-competition-manager'), 'primary', 'submit', false); ?>
            <a href="?page=wevoi-competitions&tab=seasons" class="button"><?php _e('Cancel', 'wevoi-competition-manager'); ?></a>
        </p>
    </form>
</div>

<style>.required{color:#d63638;}</style>
