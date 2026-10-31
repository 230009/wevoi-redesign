<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get current type from parent scope
if (!isset($type)) {
    wp_die('Type variable is required for edit modal');
}
?>

<!-- Edit Modal Template -->
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

/* Form styles */
.edit-form {
    padding: 20px;
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

.error {
    border-color: #dc3232;
}

.error-message {
    color: #dc3232;
    font-size: 12px;
    margin-top: 5px;
}

.submit-row {
    margin-top: 20px;
    text-align: right;
}

.submit-row .spinner {
    float: none;
    margin-right: 4px;
}
</style>