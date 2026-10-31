<?php
/**
 * Admin settings management for WeVoi Register
 */

if (!defined('ABSPATH')) {
    exit;
}

class WeVoi_Register_Admin_Settings {
    private $encryption_handler;
    private $stripe_handler; 

    public function __construct() {
        // Hook into the admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Add admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Initialize handlers
        $this->encryption_handler = WeVoi_Register_Encryption_Handler::instance();
        $this->stripe_handler = WeVoi_Register_Stripe_Handler::instance(); 
    }

    /**
     * Add menu item to WordPress admin
     */
        public function add_admin_menu() {
        add_menu_page(
            'WeVoi Stripe Settings',      // Page title
            'WeVoi Stripe',               // Menu title
            'manage_options',               // Capability
            'wevoi-register-settings',      // Menu slug
            array($this, 'display_settings_page'), // Callback
            'dashicons-cart',              // Icon
            25                             // Position
        );
    }

    /**
     * Enqueue admin scripts
     */
        public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_wevoi-register-settings' !== $hook) {
            return;
        }

        wp_enqueue_script(
            'wevoi-register-admin',
            WEVOI_REGISTER_PLUGIN_URL . 'admin/js/admin-scripts.js',
            array('jquery'),
            WEVOI_REGISTER_VERSION,
            true
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('wevoi_register_settings', 'wevoi_register_stripe_mode');
        register_setting('wevoi_register_settings', 'wevoi_register_test_publishable_key', 
            array($this, 'encrypt_field'));
        register_setting('wevoi_register_settings', 'wevoi_register_test_secret_key', 
            array($this, 'encrypt_field'));
        register_setting('wevoi_register_settings', 'wevoi_register_test_webhook_secret', 
            array($this, 'encrypt_field'));
        register_setting('wevoi_register_settings', 'wevoi_register_live_publishable_key', 
            array($this, 'encrypt_field'));
        register_setting('wevoi_register_settings', 'wevoi_register_live_secret_key', 
            array($this, 'encrypt_field'));
        register_setting('wevoi_register_settings', 'wevoi_register_live_webhook_secret', 
            array($this, 'encrypt_field'));
        register_setting('wevoi_register_settings', 'wevoi_register_gemini_api_key',
            array($this, 'encrypt_gemini_api_key_setting'));
        register_setting('wevoi_register_settings', 'wevoi_register_gemini_model',
            array('sanitize_callback' => 'sanitize_text_field'));
    }

    /**
     * Encrypt Gemini API key; empty POST keeps the previous value (password fields often submit blank).
     *
     * @param mixed $value Submitted value.
     * @return string Encrypted ciphertext or previous option.
     */
    public function encrypt_gemini_api_key_setting($value) {
        if (!$this->encryption_handler->is_encryption_ready()) {
            add_settings_error(
                'wevoi_register_messages',
                'encryption_not_ready',
                'Encryption system is not properly initialized.',
                'error'
            );
            return '';
        }
        $trimmed = is_string($value) ? trim($value) : '';
        if ($trimmed === '') {
            return (string) get_option('wevoi_register_gemini_api_key', '');
        }
        $encrypted = $this->encryption_handler->encrypt($trimmed);
        if ($encrypted === false || $encrypted === '') {
            return (string) get_option('wevoi_register_gemini_api_key', '');
        }
        return $encrypted;
    }

    /**
     * Encrypt sensitive field data
     */
    public function encrypt_field($value) {
        if (!$this->encryption_handler->is_encryption_ready()) {
            add_settings_error(
                'wevoi_register_messages',
                'encryption_not_ready',
                'Encryption system is not properly initialized.',
                'error'
            );
            return '';
        }
        return $this->encryption_handler->encrypt($value);
    }

    /**
     * Get decrypted field value
     */
    private function get_decrypted_value($option_name) {
        if (!$this->encryption_handler->is_encryption_ready()) {
            return '';
        }
        $encrypted_value = get_option($option_name);
        if (empty($encrypted_value)) {
            return '';
        }
        return $this->encryption_handler->decrypt($encrypted_value);
    }

    /**
     * Display the settings page
     */
    public function display_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if encryption is ready
        if (!$this->encryption_handler->is_encryption_ready()) {
            ?>
            <div class="notice notice-error">
                <p>Encryption system is not properly initialized. Please contact support.</p>
            </div>
            <?php
            return;
        }

        // Settings errors/updates
        settings_errors('wevoi_register_messages');
        ?>
        
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <!-- Webhook URL bilgisi -->
            <div class="notice notice-info inline">
                <p>
                    <strong>Webhook URL:</strong> 
                    <code id="webhook-url"><?php echo esc_url(WeVoi_Register_Webhook_Handler::get_webhook_url()); ?></code>
                    <button type="button" class="button button-small copy-webhook-url">
                        Copy to Clipboard
                    </button>
                </p>
                <p>
                    <strong>Current Webhook Secret:</strong>
                    <code><?php 
                        try {
                            if ($this->stripe_handler && method_exists($this->stripe_handler, 'get_webhook_secret')) {
                                $secret = $this->stripe_handler->get_webhook_secret();
                                echo $secret ? '***' . esc_html(substr($secret, -4)) : 'Not set';
                            } else {
                                echo 'Stripe handler not initialized properly';
                            }
                        } catch (Exception $e) {
                            echo 'Error getting webhook secret';
                        }
                    ?></code>
                </p>
                <p class="description">
                    Use this URL in your Stripe Dashboard to configure webhooks. This endpoint will handle all Stripe events automatically.
                </p>
            </div>

            <!-- Settings form -->
            <form action="options.php" method="post">
                <?php
                settings_fields('wevoi_register_settings');
                do_settings_sections('wevoi_register_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Stripe Mode</th>
                        <td>
                            <select name="wevoi_register_stripe_mode">
                                <option value="test" <?php selected(get_option('wevoi_register_stripe_mode'), 'test'); ?>>
                                    Test Mode
                                </option>
                                <option value="live" <?php selected(get_option('wevoi_register_stripe_mode'), 'live'); ?>>
                                    Live Mode
                                </option>
                            </select>
                        </td>
                    </tr>
                    
                    <!-- Test Mode Fields -->
                    <tr>
                        <th scope="row">Test Publishable Key</th>
                        <td>
                            <input type="text" name="wevoi_register_test_publishable_key" 
                                value="<?php echo esc_attr($this->get_decrypted_value('wevoi_register_test_publishable_key')); ?>" 
                                class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Test Secret Key</th>
                        <td>
                            <input type="password" name="wevoi_register_test_secret_key" 
                                value="<?php echo esc_attr($this->get_decrypted_value('wevoi_register_test_secret_key')); ?>" 
                                class="regular-text" />
                            <button type="button" class="button toggle-password">Show</button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Test Webhook Secret</th>
                        <td>
                            <input type="password" name="wevoi_register_test_webhook_secret" 
                                value="<?php echo esc_attr($this->get_decrypted_value('wevoi_register_test_webhook_secret')); ?>" 
                                class="regular-text" />
                            <button type="button" class="button toggle-password">Show</button>
                        </td>
                    </tr>

                    <!-- Live Mode Fields -->
                    <tr>
                        <th scope="row">Live Publishable Key</th>
                        <td>
                            <input type="text" name="wevoi_register_live_publishable_key" 
                                value="<?php echo esc_attr($this->get_decrypted_value('wevoi_register_live_publishable_key')); ?>" 
                                class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Live Secret Key</th>
                        <td>
                            <input type="password" name="wevoi_register_live_secret_key" 
                                value="<?php echo esc_attr($this->get_decrypted_value('wevoi_register_live_secret_key')); ?>" 
                                class="regular-text" />
                            <button type="button" class="button toggle-password">Show</button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Live Webhook Secret</th>
                        <td>
                            <input type="password" name="wevoi_register_live_webhook_secret" 
                                value="<?php echo esc_attr($this->get_decrypted_value('wevoi_register_live_webhook_secret')); ?>" 
                                class="regular-text" />
                            <button type="button" class="button toggle-password">Show</button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Gemini API key</th>
                        <td>
                            <input type="password" name="wevoi_register_gemini_api_key"
                                value="<?php echo esc_attr($this->get_decrypted_value('wevoi_register_gemini_api_key')); ?>"
                                class="regular-text" autocomplete="off" />
                            <button type="button" class="button toggle-password">Show</button>
                            <p class="description">Use a <strong>Google AI Studio</strong> API key for <code>generativelanguage.googleapis.com</code> (starts with <code>AIza</code>). Leave blank when saving other settings to keep the current key. Application restrictions must allow your server (or use “None” for testing).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Gemini model</th>
                        <td>
                            <?php
                            $gemini_model_current = trim((string) get_option('wevoi_register_gemini_model', 'gemini-2.5-flash'));
                            $gemini_presets       = array(
                                'gemini-2.5-flash' => 'Gemini 2.5 Flash — faster, lower cost',
                                'gemini-2.5-pro'   => 'Gemini 2.5 Pro — often higher quality, may be slower',
                            );
                            ?>
                            <select name="wevoi_register_gemini_model" id="wevoi_register_gemini_model" class="regular-text" style="max-width:28rem;">
                                <?php foreach ($gemini_presets as $model_id => $label) : ?>
                                    <option value="<?php echo esc_attr($model_id); ?>" <?php selected($gemini_model_current, $model_id); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if ($gemini_model_current !== '' && ! isset($gemini_presets[ $gemini_model_current ])) : ?>
                                    <option value="<?php echo esc_attr($gemini_model_current); ?>" selected>
                                        <?php echo esc_html($gemini_model_current); ?> (current custom ID)
                                    </option>
                                <?php endif; ?>
                            </select>
                            <p class="description">Used for <code>generativelanguage.googleapis.com/v1beta/models/{model}:generateContent</code>. Switch to <strong>2.5 Pro</strong> to compare scoring quality; billing and latency may increase.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    }
}