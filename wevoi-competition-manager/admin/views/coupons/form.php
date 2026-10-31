<?php
// Direct access kontrolü
if (!defined('ABSPATH')) {
    exit;
}

// Global wpdb değişkenini ekle
global $wpdb;

// Edit için veri yükleme
$coupon = null;
$is_edit = isset($_GET['action']) && $_GET['action'] === 'edit';
$competition_code = isset($_GET['competition_code']) ? intval($_GET['competition_code']) : 0;

if ($is_edit && isset($_GET['id'])) {
    $coupon = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT c.*, comp.competition_name
             FROM {$wpdb->prefix}wevoi_coupons c
             LEFT JOIN {$wpdb->prefix}wevoi_competitions comp ON c.competition_code = comp.competition_code
             WHERE c.id = %d",
            intval($_GET['id'])
        ),
        ARRAY_A
    );

    if (!$coupon) {
        wp_die(__('Coupon not found.', 'wevoi-competition-manager'));
    }
    $competition_code = $coupon['competition_code'];
}

// Yarışmaları getir
$competitions = $wpdb->get_results(
    "SELECT competition_code, competition_name, competition_type
     FROM {$wpdb->prefix}wevoi_competitions
     WHERE competition_status = 'ON AIR'
     ORDER BY competition_name"
);

// Seçili competition'ın type'ını kontrol et (Forum için role seçimi göstermek için)
$selected_competition_type = null;
if ($competition_code) {
    $selected_comp = $wpdb->get_row($wpdb->prepare(
        "SELECT competition_type FROM {$wpdb->prefix}wevoi_competitions WHERE competition_code = %d",
        $competition_code
    ));
    if ($selected_comp) {
        $selected_competition_type = $selected_comp->competition_type;
    }
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo $is_edit ? __('Edit Coupon', 'wevoi-competition-manager') : __('Add New Coupon', 'wevoi-competition-manager'); ?>
    </h1>
    <hr class="wp-header-end">

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="coupon-form" class="validate">
        <?php wp_nonce_field('save_coupon', 'coupon_nonce'); ?>
        <input type="hidden" name="action" value="<?php echo $is_edit ? 'edit_coupon' : 'add_coupon'; ?>">
        <?php if ($is_edit): ?>
            <input type="hidden" name="coupon_id" value="<?php echo esc_attr($coupon['id']); ?>">
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="coupon_code"><?php _e('Coupon Code', 'wevoi-competition-manager'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input name="coupon_code" type="text" id="coupon_code"
                           value="<?php echo $is_edit ? esc_attr($coupon['coupon_code']) : ''; ?>"
                           class="regular-text" required>
                    <?php if (!$is_edit): ?>
                        <button type="button" id="generate_code" class="button button-secondary">
                            <?php _e('Generate Code', 'wevoi-competition-manager'); ?>
                        </button>
                    <?php endif; ?>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="competition_code"><?php _e('Competition', 'wevoi-competition-manager'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <select name="competition_code" id="competition_code" required <?php echo $is_edit ? 'disabled' : ''; ?>>
                        <option value=""><?php _e('Select Competition', 'wevoi-competition-manager'); ?></option>
                        <?php foreach ($competitions as $comp): ?>
                            <option value="<?php echo esc_attr($comp->competition_code); ?>"
                                data-type="<?php echo esc_attr($comp->competition_type ?? ''); ?>"
                                <?php selected($competition_code, $comp->competition_code); ?>>
                                <?php echo esc_html($comp->competition_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($is_edit): ?>
                        <input type="hidden" name="competition_code" value="<?php echo esc_attr($competition_code); ?>">
                    <?php endif; ?>
                </td>
            </tr>

            <tr id="role-row" class="forum-only" style="<?php echo ($selected_competition_type === 'Forum') ? '' : 'display: none;'; ?>">
                <th scope="row">
                    <label for="coupon_role"><?php _e('Role', 'wevoi-competition-manager'); ?></label>
                </th>
                <td>
                    <select name="coupon_role" id="coupon_role">
                        <option value=""><?php _e('All Roles', 'wevoi-competition-manager'); ?></option>
                        <option value="student" <?php selected($is_edit && isset($coupon['role']) ? $coupon['role'] : '', 'student'); ?>>
                            <?php _e('Student', 'wevoi-competition-manager'); ?>
                        </option>
                        <option value="supervisor" <?php selected($is_edit && isset($coupon['role']) ? $coupon['role'] : '', 'supervisor'); ?>>
                            <?php _e('Supervisor', 'wevoi-competition-manager'); ?>
                        </option>
                        <option value="parent" <?php selected($is_edit && isset($coupon['role']) ? $coupon['role'] : '', 'parent'); ?>>
                            <?php _e('Parent', 'wevoi-competition-manager'); ?>
                        </option>
                    </select>
                    <p class="description"><?php _e('Select a specific role for this coupon, or leave as "All Roles" to apply to all roles.', 'wevoi-competition-manager'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Coupon Limit Type', 'wevoi-competition-manager'); ?> <span class="required">*</span></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <?php _e('Coupon Limit Type', 'wevoi-competition-manager'); ?>
                        </legend>
                        <label>
                            <input type="radio" name="limit_type" value="usage"
                                <?php echo (!$is_edit || ($is_edit && !empty($coupon['coupon_limit']))) ? 'checked' : ''; ?>>
                            <?php _e('Usage Limit', 'wevoi-competition-manager'); ?>
                        </label>
                        <br>
                        <label>
                            <input type="radio" name="limit_type" value="date"
                                <?php echo ($is_edit && !empty($coupon['coupon_limit_expiration_date'])) ? 'checked' : ''; ?>>
                            <?php _e('Expiration Date', 'wevoi-competition-manager'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>

            <tr class="limit-type usage-limit">
                <th scope="row">
                    <label for="coupon_limit"><?php _e('Usage Limit', 'wevoi-competition-manager'); ?></label>
                </th>
                <td>
                    <input name="coupon_limit" type="number" id="coupon_limit"
                           value="<?php echo $is_edit ? esc_attr($coupon['coupon_limit']) : ''; ?>"
                           class="regular-text" min="1">
                </td>
            </tr>

            <tr class="limit-type date-limit">
                <th scope="row">
                    <label for="coupon_limit_expiration_date"><?php _e('Expiration Date', 'wevoi-competition-manager'); ?></label>
                </th>
                <td>
                    <input name="coupon_limit_expiration_date" type="date" id="coupon_limit_expiration_date"
                           value="<?php echo $is_edit && !empty($coupon['coupon_limit_expiration_date']) ?
                                 date('Y-m-d', strtotime($coupon['coupon_limit_expiration_date'])) : ''; ?>"
                           class="regular-text">
                </td>
            </tr>

            <tr>
                <th scope="row"><?php _e('Discount Type', 'wevoi-competition-manager'); ?> <span class="required">*</span></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <?php _e('Discount Type', 'wevoi-competition-manager'); ?>
                        </legend>
                        <label>
                            <input type="radio" name="discount_type" value="amount"
                                <?php echo (!$is_edit || ($is_edit && !empty($coupon['coupon_discount_amount']))) ? 'checked' : ''; ?>>
                            <?php _e('Fixed Amount (£)', 'wevoi-competition-manager'); ?>
                        </label>
                        <br>
                        <label>
                            <input type="radio" name="discount_type" value="percentage"
                                <?php echo ($is_edit && !empty($coupon['coupon_discount_percentage'])) ? 'checked' : ''; ?>>
                            <?php _e('Percentage (%)', 'wevoi-competition-manager'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>

            <tr class="discount-type amount-discount">
                <th scope="row">
                    <label for="coupon_discount_amount"><?php _e('Discount Amount (£)', 'wevoi-competition-manager'); ?></label>
                </th>
                <td>
                    <input name="coupon_discount_amount" type="number" id="coupon_discount_amount"
                           value="<?php echo $is_edit ? esc_attr($coupon['coupon_discount_amount']) : ''; ?>"
                           class="regular-text" step="0.01" min="0">
                </td>
            </tr>


            <tr>
                <th scope="row">
                    <label for="coupon_comments"><?php _e('Comments', 'wevoi-competition-manager'); ?></label>
                </th>
                <td>
                    <textarea name="coupon_comments" id="coupon_comments"
                              class="large-text" rows="3"><?php echo $is_edit ? esc_textarea($coupon['coupon_comments']) : ''; ?></textarea>
                    <p class="description"><?php _e('Add any notes or comments about this coupon.', 'wevoi-competition-manager'); ?></p>
                </td>
            </tr>


            <tr class="discount-type percentage-discount">
                <th scope="row">
                    <label for="coupon_discount_percentage"><?php _e('Discount Percentage (%)', 'wevoi-competition-manager'); ?></label>
                </th>
                <td>
                    <input name="coupon_discount_percentage" type="number" id="coupon_discount_percentage"
                           value="<?php echo $is_edit ? esc_attr($coupon['coupon_discount_percentage']) : ''; ?>"
                           class="regular-text" min="0" max="100">
                </td>
            </tr>

            <?php if ($is_edit): ?>
            <tr>
                <th scope="row"><?php _e('Usage Count', 'wevoi-competition-manager'); ?></th>
                <td>
                    <p class="description">
                        <?php printf(__('This coupon has been used %d times.', 'wevoi-competition-manager'),
                            intval($coupon['coupon_used'])); ?>
                    </p>
                </td>
            </tr>
            <?php endif; ?>
        </table>

        <p class="submit">
            <?php
            submit_button(
                $is_edit ? __('Update Coupon', 'wevoi-competition-manager') : __('Add Coupon', 'wevoi-competition-manager'),
                'primary',
                'submit',
                false
            );
            ?>
            <a href="?page=<?php echo $_REQUEST['page']; ?>&tab=coupons<?php echo $competition_code ? '&competition_code=' . $competition_code : ''; ?>"
               class="button button-secondary">
                <?php _e('Cancel', 'wevoi-competition-manager'); ?>
            </a>
        </p>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Limit type toggle
    function toggleLimitType() {
        var selectedType = $('input[name="limit_type"]:checked').val();
        $('.limit-type').hide();
        $('.' + selectedType + '-limit').show();

        if (selectedType === 'usage') {
            $('#coupon_limit_expiration_date').val('');
        } else {
            $('#coupon_limit').val('');
        }
    }

    // Discount type toggle
    function toggleDiscountType() {
        var selectedType = $('input[name="discount_type"]:checked').val();
        $('.discount-type').hide();
        $('.' + selectedType + '-discount').show();

        if (selectedType === 'amount') {
            $('#coupon_discount_percentage').val('');
        } else {
            $('#coupon_discount_amount').val('');
        }
    }

    // Initial state
    toggleLimitType();
    toggleDiscountType();

    // Event listeners
    $('input[name="limit_type"]').on('change', toggleLimitType);
    $('input[name="discount_type"]').on('change', toggleDiscountType);

    // Show/hide role field based on competition type
    function toggleRoleField() {
        var selectedOption = $('#competition_code option:selected');
        var competitionType = selectedOption.data('type');
        if (competitionType === 'Forum') {
            $('#role-row').show();
        } else {
            $('#role-row').hide();
            $('#coupon_role').val('');
        }
    }

    // Initial check
    toggleRoleField();

    // Listen for competition change
    $('#competition_code').on('change', toggleRoleField);

    // Generate random coupon code

// Generate random coupon code
$('#generate_code').on('click', function() {
    function generateCouponCode() {
        const letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // Karışıklık yaratabilecek I,O harfleri çıkarıldı
        const numbers = '0123456789';
        let code = '';

        // İlk 3 harf
        for (let i = 0; i < 3; i++) {
            code += letters.charAt(Math.floor(Math.random() * letters.length));
        }

        // Son 3 rakam
        for (let i = 0; i < 3; i++) {
            code += numbers.charAt(Math.floor(Math.random() * numbers.length));
        }

        return code;
    }

    $('#coupon_code').val(generateCouponCode());
        // Form durumunu güncelle
    initialFormState = $('#coupon-form').serialize();
});
    // Form validation
    $('#coupon-form').on('submit', function(e) {
        var limitType = $('input[name="limit_type"]:checked').val();
        var discountType = $('input[name="discount_type"]:checked').val();
        var hasError = false;
        var errorMessage = '';

        // Coupon code validation
        if ($('#coupon_code').val().length < 3) {
            errorMessage = '<?php _e('Coupon code must be at least 3 characters long.', 'wevoi-competition-manager'); ?>';
            hasError = true;
        }

        // Limit validation
        if (limitType === 'usage' && (!$('#coupon_limit').val() || $('#coupon_limit').val() < 1)) {
            errorMessage = '<?php _e('Please enter a valid usage limit.', 'wevoi-competition-manager'); ?>';
            hasError = true;
        }
        if (limitType === 'date' && !$('#coupon_limit_expiration_date').val()) {
            errorMessage = '<?php _e('Please select an expiration date.', 'wevoi-competition-manager'); ?>';
            hasError = true;
        }

        // Discount validation
        if (discountType === 'amount' && (!$('#coupon_discount_amount').val() || $('#coupon_discount_amount').val() <= 0)) {
            errorMessage = '<?php _e('Please enter a valid discount amount.', 'wevoi-competition-manager'); ?>';
            hasError = true;
        }
        if (discountType === 'percentage' && (!$('#coupon_discount_percentage').val() || $('#coupon_discount_percentage').val() <= 0 || $('#coupon_discount_percentage').val() > 100)) {
            errorMessage = '<?php _e('Please enter a valid discount percentage (1-100).', 'wevoi-competition-manager'); ?>';
            hasError = true;
        }

        if (hasError) {
            alert(errorMessage);
            e.preventDefault();
            return false;
        }

        return true;
    });

    // Değişiklik kontrolü
    var initialFormState = $('#coupon-form').serialize();
    $('.button-secondary').on('click', function(e) {
        if ($('#coupon-form').serialize() !== initialFormState) {
            if (!confirm('<?php _e('You have unsaved changes. Are you sure you want to leave?', 'wevoi-competition-manager'); ?>')) {
                e.preventDefault();
                return false;
            }
        }
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
