<?php
if (!defined('ABSPATH')) {
    exit;
}

$snippet = null;
$is_edit = isset($_GET['action']) && $_GET['action'] === 'edit';

if ($is_edit && isset($_GET['id'])) {
    global $wevoi_email_snippets;
    $snippet = $wevoi_email_snippets->get_snippet(intval($_GET['id']));
    if (!$snippet) {
        wp_die(__('Snippet not found.', 'wevoi-competition-manager'));
    }
}

$target_groups = array(
    'Student' => __('Student', 'wevoi-competition-manager'),
    'Supervisor' => __('Supervisor', 'wevoi-competition-manager'),
    'Judge' => __('Judge', 'wevoi-competition-manager')
);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo $is_edit ? __('Edit Snippet', 'wevoi-competition-manager') : __('Add New Snippet', 'wevoi-competition-manager'); ?>
    </h1>
    <hr class="wp-header-end">

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="snippet-form">
        <?php wp_nonce_field('save_email_snippet', 'snippet_nonce'); ?>
        <input type="hidden" name="action" value="<?php echo $is_edit ? 'edit_email_snippet' : 'add_email_snippet'; ?>">
        <?php if ($is_edit): ?>
            <input type="hidden" name="snippet_id" value="<?php echo esc_attr($snippet->id); ?>">
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="snippet_name"><?php _e('Internal Name', 'wevoi-competition-manager'); ?></label>
                </th>
                <td>
                    <input name="snippet_name" type="text" id="snippet_name" 
                           value="<?php echo $is_edit ? esc_attr($snippet->snippet_name) : ''; ?>"
                           class="regular-text" required>
                    <p class="description">
                        <?php _e('Give your snippet a descriptive name for easy identification.', 'wevoi-competition-manager'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="target_group"><?php _e('For Whom', 'wevoi-competition-manager'); ?></label>
                </th>
                <td>
                    <select name="target_group" id="target_group" required>
                        <option value=""><?php _e('Select Target Group', 'wevoi-competition-manager'); ?></option>
                        <?php foreach ($target_groups as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" 
                                <?php selected($is_edit ? $snippet->target_group : '', $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php _e('Select the group this email template is intended for.', 'wevoi-competition-manager'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="snippet_text"><?php _e('Snippet Text', 'wevoi-competition-manager'); ?></label>
                </th>
                <td>
                    <textarea name="snippet_text" id="snippet_text" rows="15" 
                              class="large-text code" required><?php 
                        echo $is_edit ? esc_textarea($snippet->snippet_text) : ''; 
                    ?></textarea>
                    <p class="description">
                        <?php _e('Enter your email template. Basic HTML formatting is supported.', 'wevoi-competition-manager'); ?>
                        <br>
                        <?php _e('You can use variables like: {name}, {email}, etc.', 'wevoi-competition-manager'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <?php 
            submit_button(
                $is_edit ? __('Update Snippet', 'wevoi-competition-manager') : __('Add Snippet', 'wevoi-competition-manager'),
                'primary',
                'submit',
                false
            ); 
            ?>
            <a href="?page=wevoi-competitions&tab=email-snippets" class="button button-secondary">
                <?php _e('Cancel', 'wevoi-competition-manager'); ?>
            </a>
        </p>
    </form>
</div>

<style>
.form-table textarea.code {
    font-family: monospace;
    line-height: 1.4;
    tab-size: 4;
    resize: vertical;
    min-height: 300px;
}

.form-table td {
    padding: 15px 10px;
}

.form-table th {
    padding: 20px 10px 20px 0;
}

.description {
    margin-top: 5px;
    color: #666;
}

.button-secondary {
    margin-left: 10px;
}

/* Form validation styles */
.form-table input:invalid,
.form-table select:invalid,
.form-table textarea:invalid {
    border-color: #dc3232;
}

.form-table input:invalid:focus,
.form-table select:invalid:focus,
.form-table textarea:invalid:focus {
    border-color: #dc3232;
    box-shadow: 0 0 2px rgba(220,50,50,.8);
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    const form = $('#snippet-form');
    const initialState = form.serialize();
    
    // Form değişiklik kontrolü
    $('.button-secondary').on('click', function(e) {
        if (form.serialize() !== initialState) {
            if (!confirm('<?php _e('You have unsaved changes. Are you sure you want to leave?', 'wevoi-competition-manager'); ?>')) {
                e.preventDefault();
            }
        }
    });

    // Form doğrulama
    form.on('submit', function(e) {
        const snippetName = $('#snippet_name').val().trim();
        const targetGroup = $('#target_group').val();
        const snippetText = $('#snippet_text').val().trim();

        if (!snippetName || !targetGroup || !snippetText) {
            e.preventDefault();
            alert('<?php _e('Please fill in all required fields.', 'wevoi-competition-manager'); ?>');
            return false;
        }

        return true;
    });

    // Tab tuşu desteği
    $('#snippet_text').on('keydown', function(e) {
        if (e.key === 'Tab') {
            e.preventDefault();
            const start = this.selectionStart;
            const end = this.selectionEnd;
            
            this.value = this.value.substring(0, start) + 
                        "    " + 
                        this.value.substring(end);
            
            this.selectionStart = this.selectionEnd = start + 4;
        }
    });
});
</script>