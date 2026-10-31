<?php
// Direct access control
if (!defined('ABSPATH')) {
    exit;
}

// Topics class is already loaded in main plugin file

// Edit data loading
$topic = null;
$is_edit = isset($_GET['action']) && $_GET['action'] === 'edit';

if ($is_edit && isset($_GET['id'])) {
    global $wpdb;
    $topic = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}wevoi_topics WHERE id = %d",
        intval($_GET['id'])
    ), ARRAY_A);

    if (!$topic) {
        wp_die(__('Topic not found.', 'wevoi-competition-manager'));
    }
}

// Get competitions and age categories
global $wpdb;
$competitions = $wpdb->get_results(
    "SELECT competition_code, competition_name FROM {$wpdb->prefix}wevoi_competitions ORDER BY competition_code DESC",
    ARRAY_A
);
$age_categories = wevoi_get_age_categories();
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo $is_edit ? __('Edit Topic', 'wevoi-competition-manager') : __('Add New Topic', 'wevoi-competition-manager'); ?>
    </h1>
    <hr class="wp-header-end">

    <form method="post" action="" id="topic-form" class="validate">
        <?php wp_nonce_field($is_edit ? 'update_topic_' . $topic['id'] : 'add_new_topic', 'topic_nonce'); ?>
        <input type="hidden" name="topic_action" value="<?php echo $is_edit ? 'update' : 'add'; ?>">
        <?php if ($is_edit): ?>
            <input type="hidden" name="topic_id" value="<?php echo esc_attr($topic['id']); ?>">
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="competition_code"><?php _e('Competition', 'wevoi-competition-manager'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <select name="competition_code" id="competition_code" class="regular-text" required>
                        <option value=""><?php _e('Select Competition', 'wevoi-competition-manager'); ?></option>
                        <?php foreach ($competitions as $competition): ?>
                            <option value="<?php echo esc_attr($competition['competition_code']); ?>"
                                <?php selected($is_edit ? $topic['competition_code'] : '', $competition['competition_code']); ?>>
                                <?php echo esc_html($competition['competition_code'] . ' - ' . $competition['competition_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Select the competition for this topic', 'wevoi-competition-manager'); ?></p>
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
                                <?php selected($is_edit ? $topic['wevoi_age_category'] : '', $category); ?>>
                                <?php echo esc_html($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Select the age category for this topic', 'wevoi-competition-manager'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="topic"><?php _e('Topic', 'wevoi-competition-manager'); ?> <span class="required">*</span></label>
                </th>
                <td>
                    <textarea name="topic" id="topic" rows="5" cols="50" class="large-text" required><?php echo $is_edit ? esc_textarea($topic['topic']) : ''; ?></textarea>
                    <p class="description"><?php _e('Enter the topic text for this competition and age category', 'wevoi-competition-manager'); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <?php
            submit_button(
                $is_edit ? __('Update Topic', 'wevoi-competition-manager') : __('Add Topic', 'wevoi-competition-manager'),
                'primary',
                'submit',
                false
            );
            ?>
            <a href="?page=<?php echo $_REQUEST['page']; ?>&tab=topics" class="button button-secondary">
                <?php _e('Cancel', 'wevoi-competition-manager'); ?>
            </a>
        </p>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Form validation
    $('#topic-form').on('submit', function(e) {
        var competitionCode = $('#competition_code').val();
        var ageCategory = $('#wevoi_age_category').val();
        var topic = $('#topic').val().trim();

        if (!competitionCode) {
            alert('<?php _e('Please select a competition.', 'wevoi-competition-manager'); ?>');
            e.preventDefault();
            return false;
        }

        if (!ageCategory) {
            alert('<?php _e('Please select an age category.', 'wevoi-competition-manager'); ?>');
            e.preventDefault();
            return false;
        }

        if (!topic) {
            alert('<?php _e('Please enter a topic.', 'wevoi-competition-manager'); ?>');
            e.preventDefault();
            return false;
        }

        return true;
    });

    // Back to list button confirmation
    $('.button-secondary').on('click', function(e) {
        if ($('#topic-form').serialize() !== $('#topic-form').data('serialized')) {
            if (!confirm('<?php _e('You have unsaved changes. Are you sure you want to leave?', 'wevoi-competition-manager'); ?>')) {
                e.preventDefault();
                return false;
            }
        }
    });

    // Track form changes
    $('#topic-form').data('serialized', $('#topic-form').serialize());
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
