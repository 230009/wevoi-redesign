<?php
// Direct access control
if (!defined('ABSPATH')) {
    exit;
}

// Edit data loading
$discount = null;
$is_edit = isset($_GET['action']) && $_GET['action'] === 'edit';

if ($is_edit && isset($_GET['id'])) {
    global $wpdb;
    $discount = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}wevoi_forum_discounts WHERE id = %d",
        intval($_GET['id'])
    ), ARRAY_A);

    if (!$discount) {
        wp_die(__('Forum discount not found.', 'wevoi-competition-manager'));
    }
}

// Get Forum competitions only
global $wpdb;
$forum_competitions = $wpdb->get_results(
    "SELECT competition_code, competition_name
     FROM {$wpdb->prefix}wevoi_competitions
     WHERE competition_type = 'Forum'
     ORDER BY competition_code DESC",
    ARRAY_A
);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo $is_edit ? __('Edit Forum Discount', 'wevoi-competition-manager') : __('Add New Forum Discount', 'wevoi-competition-manager'); ?>
    </h1>
    <hr class="wp-header-end">

    <form method="post" action="" id="forum-discount-form" class="validate">
        <?php wp_nonce_field($is_edit ? 'update_forum_discount_' . $discount['id'] : 'add_new_forum_discount', 'discount_nonce'); ?>
        <input type="hidden" name="discount_action" value="<?php echo $is_edit ? 'update' : 'add'; ?>">
        <?php if ($is_edit): ?>
            <input type="hidden" name="discount_id" value="<?php echo esc_attr($discount['id']); ?>">
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="competition_code"><?php _e('Forum Competition', 'wevoi-competition-manager'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <select name="competition_code" id="competition_code" class="regular-text" required>
                        <option value=""><?php _e('Select Forum Competition', 'wevoi-competition-manager'); ?></option>
                        <?php foreach ($forum_competitions as $competition): ?>
                            <option value="<?php echo esc_attr($competition['competition_code']); ?>"
                                <?php selected($is_edit ? $discount['competition_code'] : '', $competition['competition_code']); ?>>
                                <?php echo esc_html($competition['competition_code'] . ' - ' . $competition['competition_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Select the Forum competition for these discount percentages', 'wevoi-competition-manager'); ?></p>
                </td>
            </tr>

            <?php if ($is_edit): ?>
            <tr>
                <th scope="row">
                    <label for="is_active"><?php _e('Status', 'wevoi-competition-manager'); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="is_active" id="is_active" value="1"
                               <?php checked($discount['is_active'], 1); ?>>
                        <?php _e('Active', 'wevoi-competition-manager'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Only one discount can be active per Forum competition. Activating this will deactivate others.', 'wevoi-competition-manager'); ?>
                    </p>
                </td>
            </tr>
            <?php endif; ?>

            <tr>
                <th scope="row">
                    <label for="gold_discount"><?php _e('Gold Medal Discount (%)', 'wevoi-competition-manager'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="number"
                           name="gold_discount"
                           id="gold_discount"
                           class="regular-text"
                           step="0.01"
                           min="0"
                           max="100"
                           value="<?php echo $is_edit ? esc_attr($discount['gold_discount']) : '10.00'; ?>"
                           required>
                    <p class="description"><?php _e('Discount percentage for Gold medal winners (0-100)', 'wevoi-competition-manager'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="silver_discount"><?php _e('Silver Medal Discount (%)', 'wevoi-competition-manager'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="number"
                           name="silver_discount"
                           id="silver_discount"
                           class="regular-text"
                           step="0.01"
                           min="0"
                           max="100"
                           value="<?php echo $is_edit ? esc_attr($discount['silver_discount']) : '6.00'; ?>"
                           required>
                    <p class="description"><?php _e('Discount percentage for Silver medal winners (0-100)', 'wevoi-competition-manager'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="bronze_discount"><?php _e('Bronze Medal Discount (%)', 'wevoi-competition-manager'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="number"
                           name="bronze_discount"
                           id="bronze_discount"
                           class="regular-text"
                           step="0.01"
                           min="0"
                           max="100"
                           value="<?php echo $is_edit ? esc_attr($discount['bronze_discount']) : '6.00'; ?>"
                           required>
                    <p class="description"><?php _e('Discount percentage for Bronze medal winners (0-100)', 'wevoi-competition-manager'); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <?php
            submit_button(
                $is_edit ? __('Update Forum Discount', 'wevoi-competition-manager') : __('Add Forum Discount', 'wevoi-competition-manager'),
                'primary',
                'submit',
                false
            );
            ?>
            <a href="?page=<?php echo $_REQUEST['page']; ?>&tab=forum-discounts" class="button button-secondary">
                <?php _e('Cancel', 'wevoi-competition-manager'); ?>
            </a>
        </p>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Form validation
    $('#forum-discount-form').on('submit', function(e) {
        var competitionCode = $('#competition_code').val();
        var goldDiscount = parseFloat($('#gold_discount').val());
        var silverDiscount = parseFloat($('#silver_discount').val());
        var bronzeDiscount = parseFloat($('#bronze_discount').val());

        if (!competitionCode) {
            alert('<?php _e('Please select a Forum competition.', 'wevoi-competition-manager'); ?>');
            e.preventDefault();
            return false;
        }

        if (isNaN(goldDiscount) || goldDiscount < 0 || goldDiscount > 100) {
            alert('<?php _e('Gold discount must be a number between 0 and 100.', 'wevoi-competition-manager'); ?>');
            e.preventDefault();
            return false;
        }

        if (isNaN(silverDiscount) || silverDiscount < 0 || silverDiscount > 100) {
            alert('<?php _e('Silver discount must be a number between 0 and 100.', 'wevoi-competition-manager'); ?>');
            e.preventDefault();
            return false;
        }

        if (isNaN(bronzeDiscount) || bronzeDiscount < 0 || bronzeDiscount > 100) {
            alert('<?php _e('Bronze discount must be a number between 0 and 100.', 'wevoi-competition-manager'); ?>');
            e.preventDefault();
            return false;
        }

        return true;
    });
});
</script>

<style>
.required {
    color: #d63638;
}
.form-table th {
    width: 200px;
}
</style>
