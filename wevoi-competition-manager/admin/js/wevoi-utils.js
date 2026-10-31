/**
 * WeVoi Common Utilities Library
 * 
 * Provides helper functions for all WeVoi JavaScript components:
 * - Secure AJAX calls
 * - Logging and error handling
 * - Form processing
 * - Validations
 * - UI helpers
 */
var WevoiUtils = (function($) {
    // Configuration
    const DEBUG = true;
    
    // Store common data
    let config = {
        nonce: '',
        ajaxUrl: ''
    };
    
    /**
     * Initialize the utility library
     */
    function init(options) {
        config = $.extend({}, config, options);
        
        // Set up global AJAX error handler
        $(document).ajaxError(function(event, jqXHR, settings, error) {
            logError('Global AJAX error:', {
                event: event,
                status: jqXHR.status,
                response: jqXHR.responseText,
                error: error,
                url: settings.url
            });
        });
        
        log('WeVoi Utils initialized with config:', config);
    }
    
    /**
     * Logging Functions
     */
    function log(message, data) {
        if (DEBUG && window.console && console.log) {
            if (data !== undefined) {
                console.log(message, data);
            } else {
                console.log(message);
            }
        }
    }
    
    function logError(message, data) {
        if (window.console && console.error) {
            console.error(message, data || '');
        }
    }
    
    function showError(message, title) {
        title = title || 'Error';
        alert(message);
    }
    
    /**
     * AJAX Functions
     */
    function ajax(options) {
        try {
            // Always add the security nonce if available
            if (config.nonce && options.data && !options.data.nonce && !options.data.security) {
                options.data.nonce = config.nonce;
            }
            
            // Add default error handling
            const originalErrorHandler = options.error;
            options.error = function(xhr, status, error) {
                logError('AJAX Error:', { xhr, status, error });
                
                if (typeof originalErrorHandler === 'function') {
                    originalErrorHandler(xhr, status, error);
                } else {
                    showError('Error loading data. Please try again.');
                }
            };
            
            // Send the AJAX request
            return $.ajax(options);
        } catch (e) {
            logError('Exception in AJAX request:', e);
            showError('An unexpected error occurred.');
            return false;
        }
    }
    
    /**
     * Form Functions
     */
    function validateForm($form) {
        let isValid = true;
        
        // Clear previous errors
        $form.find('.error').removeClass('error');
        $form.find('.error-message').remove();
        
        // Validate required fields
        $form.find('[required]').each(function() {
            const $field = $(this);
            if (!$field.val().trim()) {
                isValid = false;
                $field.addClass('error');
                $field.after('<span class="error-message">This field is required</span>');
            }
        });
        
        // Validate email fields
        $form.find('input[type="email"]').each(function() {
            const $field = $(this);
            const email = $field.val().trim();
            
            if (email && !isValidEmail(email)) {
                isValid = false;
                $field.addClass('error');
                $field.after('<span class="error-message">Please enter a valid email address</span>');
            }
        });
        
        return isValid;
    }
    
    function isValidEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }
    
    function submitForm($form, options) {
        const defaultOptions = {
            validate: true,
            useFormData: false,
            resetForm: true,
            beforeSubmit: null,
            success: null,
            error: null
        };
        
        const settings = $.extend({}, defaultOptions, options);
        
        // Validate if requested
        if (settings.validate && !validateForm($form)) {
            return false;
        }
        
        // Call beforeSubmit if provided
        if (typeof settings.beforeSubmit === 'function') {
            const result = settings.beforeSubmit($form);
            if (result === false) {
                return false;
            }
        }
        
        // Disable submit button and show spinner
        const $submitBtn = $form.find('[type="submit"]');
        $submitBtn.prop('disabled', true);
        const $spinner = $form.find('.spinner');
        if ($spinner.length) {
            $spinner.addClass('is-active');
        }
        
        // Prepare form data
        let formData;
        if (settings.useFormData) {
            formData = new FormData($form[0]);
            // Add nonce if available
            if (config.nonce && !formData.has('nonce') && !formData.has('security')) {
                formData.append('nonce', config.nonce);
            }
        } else {
            formData = $form.serialize();
            // Add nonce if available
            if (config.nonce && formData.indexOf('nonce=') === -1 && formData.indexOf('security=') === -1) {
                formData += '&nonce=' + config.nonce;
            }
        }
        
        // Send form data
        ajax({
            url: $form.attr('action') || config.ajaxUrl,
            type: $form.attr('method') || 'POST',
            data: formData,
            processData: settings.useFormData ? false : true,
            contentType: settings.useFormData ? false : 'application/x-www-form-urlencoded; charset=UTF-8',
            success: function(response) {
                if (typeof settings.success === 'function') {
                    settings.success(response, $form);
                }
                
                if (settings.resetForm) {
                    $form[0].reset();
                }
            },
            error: function(xhr, status, error) {
                if (typeof settings.error === 'function') {
                    settings.error(xhr, status, error, $form);
                }
            },
            complete: function() {
                $submitBtn.prop('disabled', false);
                if ($spinner.length) {
                    $spinner.removeClass('is-active');
                }
            }
        });
        
        return true;
    }
    
    /**
     * DOM Helper Functions
     */
    function initializeSelect2($elements, options) {
        if (!$.fn.select2) {
            logError('Select2 library is not loaded');
            return;
        }
        
        $elements = $elements || $('.select2-enabled');
        options = options || {};
        
        $elements.each(function() {
            const $element = $(this);
            const defaultOptions = {
                width: '100%'
            };
            
            const elementOptions = $.extend({}, defaultOptions, options, $element.data());
            $element.select2(elementOptions);
        });
    }
    
    function initializeDatepicker($elements, options) {
        if (!$.fn.datepicker) {
            logError('Datepicker library is not loaded');
            return;
        }
        
        $elements = $elements || $('input.datepicker');
        options = options || {};
        
        $elements.each(function() {
            const $element = $(this);
            const defaultOptions = {
                dateFormat: 'yy-mm-dd'
            };
            
            const elementOptions = $.extend({}, defaultOptions, options, $element.data());
            $element.datepicker(elementOptions);
        });
    }
    
    // Return public API
    return {
        init: init,
        log: log,
        logError: logError,
        showError: showError,
        ajax: ajax,
        validateForm: validateForm,
        isValidEmail: isValidEmail,
        submitForm: submitForm,
        initializeSelect2: initializeSelect2,
        initializeDatepicker: initializeDatepicker
    };
})(jQuery);

// Initialize when document is ready
jQuery(function($) {
    WevoiUtils.init({
        nonce: typeof wevoiAdmin !== 'undefined' && wevoiAdmin.nonce ? wevoiAdmin.nonce : '',
        ajaxUrl: typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php'
    });
});