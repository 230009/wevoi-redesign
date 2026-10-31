<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get snippets for current page
global $wevoi_email_snippets;
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : '';

// Map tab to target group
$target_group_map = [
    'students' => 'Student',
    'supervisors' => 'Supervisor',
    'agency' => 'Supervisor',
    'judges' => 'Judge',
    'registrations' => 'Student' // registrations are for students
];

// Debug - Log current values
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Email Modal - Current Tab: ' . $current_tab);
}

// Determine target group from current tab
$target_group = isset($target_group_map[$current_tab]) ? $target_group_map[$current_tab] : '';

// IMPORTANT FIX: Force target group for supervisor page
if ($current_tab === 'supervisors') {
    $target_group = 'Supervisor';
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Email Modal - Forced target group to Supervisor');
    }
}

if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Email Modal - Determined Target Group: ' . $target_group);
}

// Get snippets based on target group
$snippets = $target_group ? $wevoi_email_snippets->get_snippets($target_group) : [];

if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Email Modal - Found ' . count($snippets) . ' snippets for ' . $target_group);
    if (!empty($snippets)) {
        foreach ($snippets as $snippet) {
            error_log('Email Modal - Snippet: ' . $snippet->snippet_name . ', ID: ' . $snippet->id);
        }
    }
}
?>

<?php if (isset($type) && !empty($type)): ?>
<div id="<?php echo esc_attr($type); ?>-edit-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php printf(__('Edit %s', 'wevoi-competition-manager'), ucfirst($type)); ?></h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Will be populated via AJAX -->
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Email Modal -->
<div id="email-modal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2><?php _e('Send Email', 'wevoi-competition-manager'); ?></h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="send-email-form">
                <?php wp_nonce_field('send_email_nonce', 'email_nonce'); ?>
                
                <div class="form-field">
                    <label for="email-to">To:</label>
                    <input type="email" id="email-to" name="to" readonly class="regular-text">
                </div>

                <?php if (!empty($snippets)): ?>
                <div class="form-field">
                    <label for="email-snippet"><?php _e('Use Template:', 'wevoi-competition-manager'); ?></label>
                    <select id="email-snippet" class="regular-text">
                        <option value=""><?php _e('Select a template...', 'wevoi-competition-manager'); ?></option>
                        <?php foreach ($snippets as $snippet): ?>
                            <option value="<?php echo esc_attr($snippet->id); ?>">
                                <?php echo esc_html($snippet->snippet_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <div class="form-field">
                    <p class="description"><?php 
                        printf(
                            __('No email templates available for %s. You can create templates in Email Snippets tab.', 'wevoi-competition-manager'),
                            $target_group ? $target_group : 'this user type'
                        ); 
                    ?></p>
                </div>
                <?php endif; ?>

                <div class="form-field">
                    <label for="email-subject">Subject:</label>
                    <input type="text" id="email-subject" name="subject" required class="regular-text">
                </div>

                <div class="form-field">
                    <label for="email-message">Message:</label>
                    <textarea id="email-message" name="message" required rows="10" class="regular-text"></textarea>
                    <p class="description">
                        <?php _e('You can use these variables in your message:', 'wevoi-competition-manager'); ?>
                        <br><code>{first_name}</code>, <code>{last_name}</code>, <code>{full_name}</code>, <code>{email}</code>
                        <?php if ($target_group === 'Supervisor'): ?>
                        <br><code>{supervisor_name}</code>
                        <?php elseif ($target_group === 'Student'): ?>
                        <br><code>{student_name}</code>
                        <?php elseif ($target_group === 'Judge'): ?>
                        <br><code>{judge_name}</code>
                        <?php endif; ?>
                    </p>
                </div>

<div class="submit-wrapper">
    <button type="button" class="button button-secondary cancel-email">
        <?php _e('Cancel', 'wevoi-competition-manager'); ?>
    </button>
    <button type="submit" class="button button-primary">
        <span class="button-text"><?php _e('Send Email', 'wevoi-competition-manager'); ?></span>
        <span class="spinner"></span>
    </button>
</div>
            </form>
        </div>
    </div>
</div>

<style>
.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    z-index: 159900;
}

.modal-content {
    position: relative;
    background: #fff;
    margin: 30px auto;
    max-width: 800px;
    max-height: calc(100vh - 60px);
    overflow-y: auto;
    border-radius: 4px;
}

.modal-header {
    padding: 15px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    padding: 0;
    font-size: 1.3em;
}

.modal-body {
    padding: 20px;
}

.modal-close {
    cursor: pointer;
    background: none;
    border: none;
    font-size: 24px;
    color: #666;
}

.form-row {
    margin-bottom: 15px;
}

.form-row label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.form-row input,
.form-row select,
.form-row textarea {
    width: 100%;
    max-width: 100%;
}

/* Email Modal Styles */
#email-modal .form-field {
    margin-bottom: 15px;
}

#email-modal label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

#email-modal .regular-text {
    width: 100%;
}

#email-modal textarea.regular-text {
    min-height: 200px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    resize: vertical;
}

#email-modal .submit-wrapper {
    text-align: right;
    margin-top: 20px;
}

#email-modal .spinner {
    display: none;
    visibility: visible;
    vertical-align: middle;
    margin-left: 5px;
}

#email-modal.sending .spinner {
    display: inline-block;
}

#email-modal.sending .button-text {
    opacity: 0.7;
}

#email-modal.sending button {
    pointer-events: none;
}

/* Description styles */
.description {
    color: #666;
    font-size: 12px;
    margin-top: 5px;
}

.description code {
    background: #f0f0f1;
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 12px;
}
</style>