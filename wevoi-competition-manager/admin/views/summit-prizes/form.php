<?php
// Direct access control
if (!defined('ABSPATH')) {
    exit;
}

$prize = null;
$is_edit = isset($_GET['action']) && $_GET['action'] === 'edit';

if ($is_edit && isset($_GET['id'])) {
    global $wpdb;
    $prize = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}wevoi_summit_prizes WHERE id = %d",
        intval($_GET['id'])
    ), ARRAY_A);

    if (!$prize) {
        wp_die(__('Summit prize not found.', 'wevoi-competition-manager'));
    }
}

global $wpdb;
$competitions = $wpdb->get_results(
    "SELECT competition_code, competition_name
     FROM {$wpdb->prefix}wevoi_competitions
     WHERE competition_type = 'Final'
     ORDER BY competition_code DESC",
    ARRAY_A
);

// Fallback if no Final competitions exist
if (empty($competitions)) {
    $competitions = $wpdb->get_results(
        "SELECT competition_code, competition_name
         FROM {$wpdb->prefix}wevoi_competitions
         ORDER BY competition_code DESC",
        ARRAY_A
    );
}

$age_categories = wevoi_get_age_categories();
$ranks = [1, 2, 3];

$error = isset($_GET['error']) ? sanitize_text_field(wp_unslash($_GET['error'])) : '';
if ($error) {
    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(urldecode($error)) . '</p></div>';
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo $is_edit ? esc_html__('Edit Summit Prize', 'wevoi-competition-manager') : esc_html__('Add New Summit Prize', 'wevoi-competition-manager'); ?>
    </h1>
    <hr class="wp-header-end">

    <form method="post" action="" id="summit-prize-form" class="validate">
        <?php wp_nonce_field($is_edit ? 'update_summit_prize_' . $prize['id'] : 'add_new_summit_prize', 'prize_nonce'); ?>
        <input type="hidden" name="prize_action" value="<?php echo $is_edit ? 'update' : 'add'; ?>">
        <?php if ($is_edit): ?>
            <input type="hidden" name="prize_id" value="<?php echo esc_attr($prize['id']); ?>">
        <?php endif; ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="competition_code"><?php _e('Competition', 'wevoi-competition-manager'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <select name="competition_code" id="competition_code" class="regular-text" required>
                        <option value=""><?php _e('Select Competition', 'wevoi-competition-manager'); ?></option>
                        <?php foreach ($competitions as $competition): ?>
                            <option value="<?php echo esc_attr($competition['competition_code']); ?>"
                                <?php selected($is_edit ? $prize['competition_code'] : '', $competition['competition_code']); ?>>
                                <?php echo esc_html($competition['competition_code'] . ' - ' . $competition['competition_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Select the Final (Summit) competition for this prize.', 'wevoi-competition-manager'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wevoi_age_category"><?php _e('Age Category', 'wevoi-competition-manager'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <select name="wevoi_age_category" id="wevoi_age_category" class="regular-text" required>
                        <option value=""><?php _e('Select Age Category', 'wevoi-competition-manager'); ?></option>
                        <?php foreach ($age_categories as $category): ?>
                            <option value="<?php echo esc_attr($category); ?>"
                                <?php selected($is_edit ? $prize['wevoi_age_category'] : '', $category); ?>>
                                <?php echo esc_html($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="student_rank"><?php _e('Rank', 'wevoi-competition-manager'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <select name="student_rank" id="student_rank" class="regular-text" required>
                        <option value=""><?php _e('Select Rank', 'wevoi-competition-manager'); ?></option>
                        <?php foreach ($ranks as $rank): ?>
                            <option value="<?php echo esc_attr($rank); ?>"
                                <?php selected($is_edit ? (int) $prize['student_rank'] : '', $rank); ?>>
                                <?php echo esc_html($rank); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Only ranks 1–3 receive cash prizes.', 'wevoi-competition-manager'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="prize"><?php _e('Prize Amount (£)', 'wevoi-competition-manager'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input type="number" name="prize" id="prize" class="regular-text" min="0.01" step="0.01" required
                           value="<?php echo $is_edit ? esc_attr($prize['prize']) : ''; ?>">
                    <p class="description"><?php _e('Cash prize amount in GBP.', 'wevoi-competition-manager'); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <?php
            submit_button(
                $is_edit ? __('Update Summit Prize', 'wevoi-competition-manager') : __('Add Summit Prize', 'wevoi-competition-manager'),
                'primary',
                'submit',
                false
            );
            ?>
            <a href="?page=<?php echo esc_attr($_REQUEST['page']); ?>&tab=summit-prizes" class="button button-secondary">
                <?php _e('Cancel', 'wevoi-competition-manager'); ?>
            </a>
        </p>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#summit-prize-form').on('submit', function(e) {
        if (!$('#competition_code').val() || !$('#wevoi_age_category').val() || !$('#student_rank').val() || !$('#prize').val()) {
            alert('<?php echo esc_js(__('Please fill in all required fields.', 'wevoi-competition-manager')); ?>');
            e.preventDefault();
            return false;
        }

        if (parseFloat($('#prize').val()) <= 0) {
            alert('<?php echo esc_js(__('Prize amount must be greater than zero.', 'wevoi-competition-manager')); ?>');
            e.preventDefault();
            return false;
        }
    });
});
</script>
