<?php
// Direct access kontrolü
if (!defined('ABSPATH')) {
    exit;
}

// Edit için veri yükleme
$competition = null;
$is_edit = isset($_GET['action']) && $_GET['action'] === 'edit';

if ($is_edit && isset($_GET['id'])) {
    global $wpdb;
    $competition = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wevoi_competitions WHERE id = %d",
            intval($_GET['id'])
        ),
        ARRAY_A
    );

    if (!$competition) {
        wp_die(__('Competition not found.', 'wevoi-competition-manager'));
    }
}

// Sezon listesi (Season dropdown için)
global $wpdb;
$seasons = [];
$seasons_table = $wpdb->prefix . 'wevoi_seasons';
if ($wpdb->get_var("SHOW TABLES LIKE '$seasons_table'") === $seasons_table) {
    $seasons = $wpdb->get_results("SELECT season_id, season_name, season_abv FROM {$seasons_table} ORDER BY season_abv ASC", ARRAY_A);
}

// Status seçenekleri
$status_options = [
    'NOT YET',
    'CONFIRMED',
    'ON AIR',
    'IN THE QUE',
    'CLOSED',
    'CANCELLED',
    'POSTPONED',
    'EVALUATING',
    'RANKINGS_FINALIZED',
    'COMPLETED'
];
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo $is_edit ? __('Edit Competition', 'wevoi-competition-manager') : __('Add New Competition', 'wevoi-competition-manager'); ?>
    </h1>
    <hr class="wp-header-end">

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="competition-form" class="validate">
        <?php wp_nonce_field('save_competition', 'competition_nonce'); ?>
        <input type="hidden" name="action" value="<?php echo $is_edit ? 'edit_competition' : 'add_competition'; ?>">
        <?php if ($is_edit): ?>
            <input type="hidden" name="competition_id" value="<?php echo esc_attr($competition['id']); ?>">
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="competition_code"><?php _e('Competition Code', 'wevoi-competition-manager'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input name="competition_code" type="number" id="competition_code"
                           value="<?php echo $is_edit ? esc_attr($competition['competition_code']) : ''; ?>"
                           class="regular-text" required <?php echo $is_edit ? 'readonly' : ''; ?>>
                    <p class="description"><?php _e('Unique competition code', 'wevoi-competition-manager'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="certificate_id_prefix"><?php _e('Certificate ID Prefix', 'wevoi-competition-manager'); ?></label>
                </th>
                <td>
                    <input name="certificate_id_prefix" type="text" id="certificate_id_prefix"
                           value="<?php echo $is_edit ? esc_attr($competition['certificate_id_prefix'] ?? '') : ''; ?>"
                           class="regular-text" maxlength="64" placeholder="<?php echo esc_attr__('e.g. WSR15FEB25', 'wevoi-competition-manager'); ?>">
                    <p class="description"><?php _e('Used on Export/Import certificate CSVs. Enter the base only (e.g. WRR23MAR26): student IDs use it as-is; judge exports append J; supervisor exports append S before the number.', 'wevoi-competition-manager'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="competition_name"><?php _e('Competition Name', 'wevoi-competition-manager'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input name="competition_name" type="text" id="competition_name"
                           value="<?php echo $is_edit ? esc_attr($competition['competition_name']) : ''; ?>"
                           class="regular-text" required>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="competition_date"><?php _e('Competition Date', 'wevoi-competition-manager'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <input name="competition_date" type="date" id="competition_date"
                           value="<?php echo $is_edit ? esc_attr($competition['competition_date']) : ''; ?>"
                           class="regular-text" required>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="competition_status"><?php _e('Status', 'wevoi-competition-manager'); ?></label>
                </th>
                <td>
                    <select name="competition_status" id="competition_status">
                        <?php foreach ($status_options as $status): ?>
                            <option value="<?php echo esc_attr($status); ?>"
                                <?php selected($is_edit ? $competition['competition_status'] : 'NOT YET', $status); ?>>
                                <?php echo esc_html($status); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="season_id"><?php _e('Season', 'wevoi-competition-manager'); ?></label>
                </th>
                <td>
                    <select name="season_id" id="season_id">
                        <option value=""><?php _e('— None —', 'wevoi-competition-manager'); ?></option>
                        <?php foreach ($seasons as $s): ?>
                            <option value="<?php echo esc_attr($s['season_id']); ?>"
                                <?php selected($is_edit ? (isset($competition['season_id']) ? $competition['season_id'] : '') : '', $s['season_id']); ?>>
                                <?php echo esc_html($s['season_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Used for commission rates and payments.', 'wevoi-competition-manager'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="competition_price"><?php _e('Price (£)', 'wevoi-competition-manager'); ?></label>
                </th>
                <td>
                    <input name="competition_price" type="number" id="competition_price"
                           value="<?php echo $is_edit ? esc_attr($competition['competition_price']) : ''; ?>"
                           class="regular-text" step="0.01" min="0">
                    <p class="description"><?php _e('Competition price in pounds (£)', 'wevoi-competition-manager'); ?></p>
                    <p class="description" id="price-description"><?php _e('For Qualification and Final competitions, this is the standard price. For Forum competitions, this is the Student price.', 'wevoi-competition-manager'); ?></p>
                </td>
            </tr>

            <!-- Forum-specific pricing fields -->
            <tr id="forum-pricing-row" style="display: none;">
                <th scope="row">
                    <label><?php _e('Forum Role-Based Pricing (£)', 'wevoi-competition-manager'); ?></label>
                </th>
                <td>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <div>
                            <label for="price_supervisor" style="display: inline-block; width: 150px; font-weight: normal;">
                                <?php _e('Supervisor Price:', 'wevoi-competition-manager'); ?>
                            </label>
                            <input name="price_supervisor" type="number" id="price_supervisor"
                                   value="<?php echo $is_edit ? esc_attr($competition['price_supervisor'] ?? '') : ''; ?>"
                                   class="regular-text" step="0.01" min="0" style="width: 200px;">
                        </div>
                        <div>
                            <label for="price_parent" style="display: inline-block; width: 150px; font-weight: normal;">
                                <?php _e('Parent Price:', 'wevoi-competition-manager'); ?>
                            </label>
                            <input name="price_parent" type="number" id="price_parent"
                                   value="<?php echo $is_edit ? esc_attr($competition['price_parent'] ?? '') : ''; ?>"
                                   class="regular-text" step="0.01" min="0" style="width: 200px;">
                        </div>
                        <p class="description"><?php _e('Student price is set in the Price field above.', 'wevoi-competition-manager'); ?></p>
                    </div>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="competition_type"><?php _e('Competition Type', 'wevoi-competition-manager'); ?></label>
                </th>
                <td>
                    <select name="competition_type" id="competition_type">
                        <option value="Qualification" <?php selected($is_edit ? $competition['competition_type'] : 'Qualification', 'Qualification'); ?>>
                            <?php _e('Qualification (WeVoi Reload/Spark)', 'wevoi-competition-manager'); ?>
                        </option>
                        <option value="Final" <?php selected($is_edit ? $competition['competition_type'] : 'Qualification', 'Final'); ?>>
                            <?php _e('Final (WeVoi Summit)', 'wevoi-competition-manager'); ?>
                        </option>
                        <option value="Forum" <?php selected($is_edit ? $competition['competition_type'] : 'Qualification', 'Forum'); ?>>
                            <?php _e('Forum', 'wevoi-competition-manager'); ?>
                        </option>
                    </select>
                    <p class="description"><?php _e('Qualification competitions give free codes to top 5, Final competitions give cash prizes to top 3, Forum competitions are discussion-based events', 'wevoi-competition-manager'); ?></p>
                </td>
            </tr>

            <!-- Forum-specific event fields -->
            <tr id="forum-event-row" style="display: none;">
                <th scope="row">
                    <label><?php _e('Forum Event Details', 'wevoi-competition-manager'); ?></label>
                </th>
                <td>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <div>
                            <label for="event_venue" style="display: inline-block; width: 150px; font-weight: normal;">
                                <?php _e('Event Venue:', 'wevoi-competition-manager'); ?>
                            </label>
                            <input name="event_venue" type="text" id="event_venue"
                                   value="<?php echo $is_edit ? esc_attr($competition['event_venue'] ?? '') : ''; ?>"
                                   class="regular-text" maxlength="255" style="width: 400px;">
                            <p class="description" style="margin-left: 150px; margin-top: 5px;"><?php _e('Location or venue where the Forum event will take place', 'wevoi-competition-manager'); ?></p>
                        </div>
                        <div>
                            <label for="event_duration" style="display: inline-block; width: 150px; font-weight: normal;">
                                <?php _e('Event Duration (days):', 'wevoi-competition-manager'); ?>
                            </label>
                            <input name="event_duration" type="number" id="event_duration"
                                   value="<?php echo $is_edit ? esc_attr($competition['event_duration'] ?? '') : ''; ?>"
                                   class="regular-text" min="0" max="255" style="width: 200px;">
                            <p class="description" style="margin-left: 150px; margin-top: 5px;"><?php _e('Duration of the Forum event in days', 'wevoi-competition-manager'); ?></p>
                        </div>
                    </div>
                </td>
            </tr>

            <tr id="forum-payment-phase-row" style="display: none;">
                <th scope="row">
                    <label for="forum_payment_phase"><?php _e('Forum registration &amp; payment', 'wevoi-competition-manager'); ?></label>
                </th>
                <td>
                    <select name="forum_payment_phase" id="forum_payment_phase">
                        <option value="pre_registration" <?php selected($is_edit ? ($competition['forum_payment_phase'] ?? 'pre_registration') : 'pre_registration', 'pre_registration'); ?>>
                            <?php _e('Pre-registration only (no online payment)', 'wevoi-competition-manager'); ?>
                        </option>
                        <option value="payment_required" <?php selected($is_edit ? ($competition['forum_payment_phase'] ?? '') : '', 'payment_required'); ?>>
                            <?php _e('Payment required at registration (Stripe)', 'wevoi-competition-manager'); ?>
                        </option>
                    </select>
                    <p class="description"><?php _e('Use Pre-registration while collecting interest without charging. When you are ready, switch to Payment required: existing pre-registrations will be asked to pay from their dashboard; new users pay immediately.', 'wevoi-competition-manager'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="award_ceremony_date"><?php _e('Award Ceremony Date &amp; Time', 'wevoi-competition-manager'); ?></label>
                </th>
                <td>
                    <input name="award_ceremony_date" type="datetime-local" id="award_ceremony_date"
                           value="<?php
                               $ceremony_dt = $is_edit ? ($competition['award_ceremony_date'] ?? '') : '';
                               // datetime-local input expects "YYYY-MM-DDTHH:MM"
                               echo $ceremony_dt ? esc_attr(date('Y-m-d\TH:i', strtotime($ceremony_dt))) : '';
                           ?>"
                           class="regular-text">
                    <p class="description"><?php _e('Date and time of the Award Ceremony (displayed in the invitation banner alongside the Zoom link).', 'wevoi-competition-manager'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="award_ceremony_zoom_link"><?php _e('Award Ceremony Zoom Link', 'wevoi-competition-manager'); ?></label>
                </th>
                <td>
                    <input name="award_ceremony_zoom_link" type="url" id="award_ceremony_zoom_link"
                           value="<?php echo $is_edit ? esc_attr($competition['award_ceremony_zoom_link'] ?? '') : ''; ?>"
                           class="regular-text" style="width: 480px;"
                           placeholder="https://us02web.zoom.us/j/...">
                    <p class="description"><?php _e('Zoom meeting link for the Award Ceremony. When competition status is RANKINGS_FINALIZED, this link will be displayed to students, supervisors, and judges as an invitation.', 'wevoi-competition-manager'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="award_ceremony_completed"><?php _e('Award Ceremony Status', 'wevoi-competition-manager'); ?></label>
                </th>
                <td>
                    <label>
                        <input name="award_ceremony_completed" type="checkbox" id="award_ceremony_completed"
                               value="1" <?php checked($is_edit ? $competition['award_ceremony_completed'] : false, true); ?>>
                        <?php _e('Award Ceremony Completed', 'wevoi-competition-manager'); ?>
                    </label>
                    <p class="description"><?php _e('Check this when the award ceremony is completed. Results will only be visible to users after this is checked.', 'wevoi-competition-manager'); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <?php
            submit_button(
                $is_edit ? __('Update Competition', 'wevoi-competition-manager') : __('Add Competition', 'wevoi-competition-manager'),
                'primary',
                'submit',
                false
            );
            ?>
            <a href="?page=<?php echo $_REQUEST['page']; ?>" class="button button-secondary">
                <?php _e('Cancel', 'wevoi-competition-manager'); ?>
            </a>
        </p>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Form validation
    $('#competition-form').on('submit', function(e) {
        // Competition code validation
        var code = $('#competition_code').val();
        if (code <= 0) {
            alert('<?php _e('Competition code must be a positive number.', 'wevoi-competition-manager'); ?>');
            e.preventDefault();
            return false;
        }

        // Price validation
        var price = $('#competition_price').val();
        if (price && parseFloat(price) < 0) {
            alert('<?php _e('Price cannot be negative.', 'wevoi-competition-manager'); ?>');
            e.preventDefault();
            return false;
        }

        // Date validation
        var date = $('#competition_date').val();
        if (!date) {
            alert('<?php _e('Please select a competition date.', 'wevoi-competition-manager'); ?>');
            e.preventDefault();
            return false;
        }

        return true;
    });

    // Back to list butonu için onay
    $('.button-secondary').on('click', function(e) {
        if ($('#competition-form').serialize() !== $('#competition-form').data('serialized')) {
            if (!confirm('<?php _e('You have unsaved changes. Are you sure you want to leave?', 'wevoi-competition-manager'); ?>')) {
                e.preventDefault();
                return false;
            }
        }
    });

    // Form değişikliklerini takip et
    $('#competition-form').data('serialized', $('#competition-form').serialize());

    // Show/hide Forum pricing fields based on competition type
    function toggleForumPricing() {
        var competitionType = $('#competition_type').val();
        if (competitionType === 'Forum') {
            $('#forum-pricing-row').show();
            $('#forum-event-row').show();
            $('#forum-payment-phase-row').show();
            $('#price-description').text('<?php _e('Student price for Forum competitions', 'wevoi-competition-manager'); ?>');
        } else {
            $('#forum-pricing-row').hide();
            $('#forum-event-row').hide();
            $('#forum-payment-phase-row').hide();
            $('#price-description').text('<?php _e('For Qualification and Final competitions, this is the standard price. For Forum competitions, this is the Student price.', 'wevoi-competition-manager'); ?>');
        }
    }

    // Initial check
    toggleForumPricing();

    // On type change
    $('#competition_type').on('change', function() {
        toggleForumPricing();
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
