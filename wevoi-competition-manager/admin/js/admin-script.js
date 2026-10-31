/**
 * WeVoi Competition Manager Admin Panel JavaScript
 * 
 * Handles all admin panel interactions including:
 * - Modal management
 * - Form validations
 * - AJAX calls
 * - UI interactions
 */
jQuery(document).ready(function($) {
    // Config and Constants
    const WEVOI = {
        debug: true,
        modals: {},
        nonce: $('#wevoi_nonce').val(),
        modalSelectors: {
            student: '#student-details-modal',
            judge: '#judge-details-modal',
            supervisor: '#supervisor-details-modal',
            agency: '#agency-details-modal',
            registration: '#registration-details-modal',
            email: '#email-modal'
        },
        messages: {
            confirmDelete: 'Are you sure you want to delete this item?',
            confirmBulkDelete: 'Are you sure you want to delete these items?',
            confirmLeave: 'You have unsaved changes. Are you sure you want to leave?',
            errorLoading: 'Error loading data. Please try again.',
            errorSaving: 'Error saving data. Please try again.',
            errorValidation: 'Please fill all required fields correctly.',
            errorPermission: 'You do not have permission to perform this action.'
        }
    };

    /**
     * Utility Functions
     */
    const Utils = {
        // Logging with debug flag control
        log: function(message, data) {
            if (WEVOI.debug && window.console && console.log) {
                if (data) {
                    console.log(message, data);
                } else {
                    console.log(message);
                }
            }
        },
        
        // Error logging
        error: function(message, data) {
            if (window.console && console.error) {
                console.error(message, data || '');
            }
        },
        
        // Display user error messages
        showError: function(message) {
            alert(message);
        },
        
        // Validate form inputs
        validateForm: function($form) {
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
                
                if (email && !Utils.isValidEmail(email)) {
                    isValid = false;
                    $field.addClass('error');
                    $field.after('<span class="error-message">Please enter a valid email address</span>');
                }
            });
            
            return isValid;
        },
        
        // Email validation regex
        isValidEmail: function(email) {
            const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(String(email).toLowerCase());
        },
        
        // Try-catch wrapper for AJAX calls
        safeAjax: function(options) {
            try {
                // Always add the security nonce
                if (options.data && !options.data.nonce && !options.data.security) {
                    options.data.nonce = WEVOI.nonce;
                }
                
                // Add default error handling
                const originalErrorHandler = options.error;
                options.error = function(xhr, status, error) {
                    Utils.error('AJAX Error:', { xhr, status, error });
                    
                    if (typeof originalErrorHandler === 'function') {
                        originalErrorHandler(xhr, status, error);
                    } else {
                        Utils.showError(WEVOI.messages.errorLoading);
                    }
                };
                
                // Send the AJAX request
                return $.ajax(options);
            } catch (e) {
                Utils.error('Exception in AJAX request:', e);
                Utils.showError('An unexpected error occurred. Please try again.');
                return false;
            }
        }
    };

    /**
     * Modal Management
     */
    const ModalManager = {
        // Open a modal by type and optionally load content via AJAX
        open: function(modalType, itemId, callback) {
            if (!WEVOI.modalSelectors[modalType]) {
                Utils.error('Invalid modal type:', modalType);
                return;
            }
            
            const $modal = $(WEVOI.modalSelectors[modalType]);
            
            if (!$modal.length) {
                Utils.error('Modal not found:', WEVOI.modalSelectors[modalType]);
                return;
            }
            
            // If item ID is provided, load content
            if (itemId !== undefined && itemId !== '' && !isNaN(Number(itemId))) {
                const numId = Number(itemId);
                this.loadContent(modalType, numId, function(response) {
                    if (response.success) {
                        $modal.find('.modal-body').html(response.data);
                        $modal.show();
                        
                        if (typeof callback === 'function') {
                            callback($modal);
                        }
                    } else {
                        Utils.showError(response.data || WEVOI.messages.errorLoading);
                    }
                });
            } else {
                // Just show the modal
                $modal.show();
                
                if (typeof callback === 'function') {
                    callback($modal);
                }
            }
        },
        
        // Close a modal
        close: function(modalType) {
            if (modalType && WEVOI.modalSelectors[modalType]) {
                $(WEVOI.modalSelectors[modalType]).hide();
            } else {
                // Close all modals if type not specified
                $('.modal').hide();
            }
        },
        
        // Load modal content via AJAX
        loadContent: function(modalType, itemId, callback) {
            const actions = {
                student: 'get_student_details',
                judge: 'get_judge_details',
                supervisor: 'get_supervisor_details',
                agency: 'get_agency_details',
                registration: 'get_registration_details',
                email: 'get_email_template'
            };
            
            if (!actions[modalType]) {
                Utils.error('No AJAX action defined for modal type:', modalType);
                return;
            }
            
            const params = {
                action: actions[modalType],
                nonce: WEVOI.nonce
            };
            
            // Add the appropriate ID parameter
            params[modalType + '_id'] = itemId;
            
            Utils.safeAjax({
                url: ajaxurl,
                type: 'POST',
                data: params,
                success: function(response) {
                    if (typeof callback === 'function') {
                        callback(response);
                    }
                }
            });
        }
    };

    /**
     * Form Management
     */
    const FormManager = {
        // Initialize a form with validation and submission handling
        init: function($form, options) {
            if (!$form || !$form.length) {
                return;
            }
            
            const defaultOptions = {
                onValidate: Utils.validateForm,
                onBeforeSubmit: function() { return true; },
                onSuccess: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        Utils.showError(response.data || WEVOI.messages.errorSaving);
                    }
                },
                onError: function(xhr, status, error) {
                    Utils.showError(WEVOI.messages.errorSaving);
                }
            };
            
            const settings = $.extend({}, defaultOptions, options);
            
            // Store initial form state for change detection
            const initialState = $form.serialize();
            
            // Form submission handler
            $form.off('submit').on('submit', function(e) {
                e.preventDefault();
                
                // Validate form
                if (typeof settings.onValidate === 'function') {
                    if (!settings.onValidate($form)) {
                        return false;
                    }
                }
                
                // Before submit callback
                if (typeof settings.onBeforeSubmit === 'function') {
                    if (settings.onBeforeSubmit($form) === false) {
                        return false;
                    }
                }
                
                // Disable submit button and show spinner
                const $submitBtn = $form.find('[type="submit"]');
                $submitBtn.prop('disabled', true);
                $form.find('.spinner').addClass('is-active');
                
                // Prepare form data
                let formData;
                if (options.useFormData) {
                    formData = new FormData($form[0]);
                } else {
                    formData = $form.serialize();
                }
                
                // Add security nonce if not present
                if (options.useFormData) {
                    if (!formData.has('nonce') && !formData.has('security')) {
                        formData.append('nonce', WEVOI.nonce);
                    }
                }
                
                // Send form data
                Utils.safeAjax({
                    url: $form.attr('action') || ajaxurl,
                    type: $form.attr('method') || 'POST',
                    data: formData,
                    processData: options.useFormData ? false : true,
                    contentType: options.useFormData ? false : 'application/x-www-form-urlencoded; charset=UTF-8',
                    success: function(response) {
                        if (typeof settings.onSuccess === 'function') {
                            settings.onSuccess(response);
                        }
                    },
                    error: function(xhr, status, error) {
                        if (typeof settings.onError === 'function') {
                            settings.onError(xhr, status, error);
                        }
                    },
                    complete: function() {
                        $submitBtn.prop('disabled', false);
                        $form.find('.spinner').removeClass('is-active');
                    }
                });
                
                return false;
            });
            
            // Warn before leaving with unsaved changes
            $('.button-secondary', $form).on('click', function(e) {
                if ($form.serialize() !== initialState) {
                    if (!confirm(WEVOI.messages.confirmLeave)) {
                        e.preventDefault();
                        return false;
                    }
                }
            });
            
            // Error field handlers - remove error on input
            $(document).on('input', '.error', function() {
                const $field = $(this);
                if ($field.val().trim()) {
                    $field.removeClass('error');
                    $field.next('.error-message').remove();
                }
            });
            
            return $form;
        },
        
        // Handle edit form loading and setup
        initEditForm: function(type, itemId) {
            const modalId = `${type}-edit-modal`;
            const $modal = $(`#${modalId}`);

            if (!$modal.length) {
                Utils.error(`Edit modal not found: ${modalId}`);
                return;
            }

            const numId = (itemId !== undefined && itemId !== '') ? Number(itemId) : 0;
            if (!numId || isNaN(numId)) {
                Utils.error('Invalid item ID for edit form:', itemId);
                return;
            }

            Utils.log('Opening edit modal:', { modalId, type, itemId: numId });

            Utils.safeAjax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: `get_${type}_data`,
                    id: numId,
                    nonce: WEVOI.nonce
                },
                beforeSend: function() {
                    Utils.log('Sending edit data request');
                    $modal.find('.modal-body').html('<div class="spinner is-active"></div>');
                },
                success: function(response) {
                    Utils.log('Edit data response:', response);
                    if (response.success) {
                        $modal.find('.modal-body').html(response.data);
                        
                        // Initialize form fields
                        const $form = $modal.find('form');
                        if ($form.length) {
                            FormManager.init($form, {
                                useFormData: true,
                                onSuccess: function(response) {
                                    if (response.success) {
                                        location.reload();
                                    } else {
                                        Utils.showError(response.data || WEVOI.messages.errorSaving);
                                    }
                                }
                            });
                            
                            // Initialize select2 if available (skip native cascades, e.g. supervisor country/school)
                            if ($.fn.select2) {
                                $form.find('select').not('.wevoi-no-select2').select2({
                                    width: '100%',
                                    dropdownParent: $modal
                                });
                            }
                            
                            // Initialize datepicker if available
                            if ($.fn.datepicker) {
                                $form.find('input[type="date"]').datepicker({
                                    dateFormat: 'yy-mm-dd'
                                });
                            }
                        }
                        
                        $modal.show();
                    } else {
                        Utils.showError(response.data || WEVOI.messages.errorLoading);
                    }
                }
            });
        }
    };

    /**
     * Email Management
     */
    const EmailManager = {
        init: function() {
            // Only initialize email functionality if the email modal exists
            if ($(WEVOI.modalSelectors.email).length === 0) {
                return;
            }
            
            // Remove any existing event handlers to prevent duplicates
            $(document).off('click', '.email-compose, .email-trigger');
            
            // Email trigger handler
            $(document).on('click', '.email-compose, .email-trigger', function(e) {
                e.preventDefault();
                
                // Get recipient email
                let emailTo;
                if ($(this).data('email')) {
                    emailTo = $(this).data('email');
                } else if ($(this).attr('href') && $(this).attr('href').indexOf('mailto:') === 0) {
                    emailTo = $(this).attr('href').replace('mailto:', '');
                }
                
                if (!emailTo) {
                    Utils.log('No email address found');
                    return;
                }
                
                Utils.log('Sending email to:', emailTo);
                
                // Reset form fields
                const $form = $('#send-email-form');
                $form[0].reset();
                $('#email-to').val(emailTo);
                
                // Reset snippet dropdown if exists
                const $snippetDropdown = $('#email-snippet');
                if ($snippetDropdown.length) {
                    $snippetDropdown.val('').trigger('change');
                }
                
                // Add signature if available
                if (typeof wevoiEmailData !== 'undefined' && wevoiEmailData.signature) {
                    $('#email-message').val(wevoiEmailData.signature);
                }
                
                // Show modal
                ModalManager.open('email');
            });
            
            // Email snippet selection handler
            $('#email-snippet').on('change', function() {
                const snippetId = $(this).val();
                
                if (!snippetId) {
                    // Reset fields if no snippet selected
                    $('#email-subject').val('');
                    
                    // Add signature back if available
                    if (typeof wevoiEmailData !== 'undefined' && wevoiEmailData.signature) {
                        $('#email-message').val(wevoiEmailData.signature);
                    } else {
                        $('#email-message').val('');
                    }
                    return;
                }
                
                Utils.safeAjax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_snippet_content',
                        snippet_id: snippetId,
                        nonce: wevoiEmailData.nonce
                    },
                    beforeSend: function() {
                        Utils.log('Requesting snippet content for ID:', snippetId);
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            // Look for subject line in text
                            const text = response.data.text;
                            const subjectMatch = text.match(/^Subject:\s*(.+)$/m);
                            
                            if (subjectMatch) {
                                $('#email-subject').val(subjectMatch[1]);
                                // Remove subject line from message
                                $('#email-message').val(text.replace(/^Subject:\s*.+\n/m, ''));
                            } else {
                                // If no subject in text, use snippet name as subject
                                $('#email-subject').val(response.data.name || '');
                                $('#email-message').val(text);
                            }
                            
                            // Append signature if needed
                            if (typeof wevoiEmailData !== 'undefined' && 
                                wevoiEmailData.signature && 
                                $('#email-message').val().indexOf(wevoiEmailData.signature) === -1) {
                                $('#email-message').val($('#email-message').val() + wevoiEmailData.signature);
                            }
                        } else {
                            Utils.error('Error getting snippet:', response);
                            if (response.data) {
                                Utils.showError('Error loading template: ' + response.data);
                            }
                        }
                    }
                });
            });
            
            // Cancel button handler
            $(document).on('click', '.cancel-email', function() {
                ModalManager.close('email');
            });
            
            // Email form submission
            $('#send-email-form').off('submit').on('submit', function(e) {
                e.preventDefault();
                
                const $form = $(this);
                const $modal = $('#email-modal');
                
                // Validate required fields
                const subject = $('#email-subject').val().trim();
                const message = $('#email-message').val().trim();
                
                if (!subject || !message) {
                    Utils.showError('Please fill in all required fields.');
                    return;
                }
                
                Utils.log('Sending email', {
                    to: $('#email-to').val(),
                    subject: subject
                });
                
                // Show loading state
                $modal.addClass('sending');
                
                Utils.safeAjax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'send_user_email',
                        nonce: $('#email_nonce').val(),
                        to: $('#email-to').val(),
                        subject: subject,
                        message: message
                    },
                    success: function(response) {
                        $modal.removeClass('sending');
                        
                        if (response.success) {
                            alert('Email sent successfully!');
                            ModalManager.close('email');
                            $form[0].reset();
                        } else {
                            Utils.showError('Error sending email: ' + (response.data || 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        $modal.removeClass('sending');
                        Utils.showError('Error sending email. Please try again.');
                    }
                });
            });
        }
    };

    /**
     * Filter and Table Management
     */
    const FilterManager = {
        init: function() {
            // Country-School filter relationship handling
            $('#filter-by-country').on('change', function() {
                const countryId = $(this).val();
                const schoolSelect = $('#filter-by-school');
                
                // Reset school selection
                schoolSelect.html('<option value="">' + wevoiAdmin.messages.all_schools + '</option>');
                
                if (countryId) {
                    Utils.safeAjax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'get_schools_by_country',
                            country_id: countryId,
                            nonce: WEVOI.nonce
                        },
                        success: function(response) {
                            if (response.success && response.data) {
                                response.data.forEach(function(school) {
                                    schoolSelect.append(
                                        $('<option></option>')
                                            .val(school.schoolid)
                                            .text(school.schoolname)
                                    );
                                });
                            }
                        }
                    });
                }
            });
            
            // Bulk action confirmation
            $('form').on('submit', function(e) {
                const action = $(this).find('select[name="action"]').val() || 
                               $(this).find('select[name="action2"]').val();
                               
                if (action === 'delete') {
                    if (!confirm(WEVOI.messages.confirmBulkDelete)) {
                        e.preventDefault();
                    }
                }
            });
            
            // Status badges styling
            $('.status-badge').each(function() {
                const status = $(this).text().trim().toLowerCase();
                $(this).addClass('status-' + status.replace(/\s+/g, '-'));
            });
        }
    };

    /**
     * Initialize Application
     */
    function initApp() {
        Utils.log('Initializing admin script...');
        
        // Set up modal close handlers (global)
        $('.modal-close, .modal').on('click', function(e) {
            if (e.target === this || $(e.target).hasClass('modal-close')) {
                $(this).closest('.modal').hide();
            }
        });
        
        // Initialize view toggles
        $('.view-toggle').on('click', function() {
            $('.view-toggle').removeClass('active');
            $(this).addClass('active');
            
            const view = $(this).data('view');
            $('.students-view, .judges-view, .supervisors-view').removeClass('active');
            $(`#${view}-view`).addClass('active');
        });
        
        // Initialize edit handlers
        $(document).on('click', '.edit-details', function(e) {
            e.preventDefault();

            const $button = $(this);
            const itemId = $button.data('student-id') || $button.data('studentId') ||
                         $button.data('supervisor-id') || $button.data('supervisorId') ||
                         $button.data('agency-id') || $button.data('agencyId') ||
                         $button.data('judge-id') || $button.data('judgeId') ||
                         $button.data('registration-id') || $button.data('registrationId');

            const type = ($button.data('student-id') || $button.data('studentId')) ? 'student' :
                       ($button.data('supervisor-id') || $button.data('supervisorId')) ? 'supervisor' :
                       ($button.data('agency-id') || $button.data('agencyId')) ? 'agency' :
                       ($button.data('judge-id') || $button.data('judgeId')) ? 'judge' :
                       'registration';

            FormManager.initEditForm(type, itemId);
        });

        // Initialize view details handlers
        $(document).on('click', '.view-details', function() {
            const $button = $(this);
            const itemId = $button.data('student-id') || $button.data('studentId') ||
                         $button.data('supervisor-id') || $button.data('supervisorId') ||
                         $button.data('agency-id') || $button.data('agencyId') ||
                         $button.data('judge-id') || $button.data('judgeId') ||
                         $button.data('registration-id') || $button.data('registrationId');

            const type = ($button.data('student-id') || $button.data('studentId')) ? 'student' :
                       ($button.data('supervisor-id') || $button.data('supervisorId')) ? 'supervisor' :
                       ($button.data('agency-id') || $button.data('agencyId')) ? 'agency' :
                       ($button.data('judge-id') || $button.data('judgeId')) ? 'judge' :
                       'registration';

            ModalManager.open(type, itemId);
        });
        
        // Initialize filters
        FilterManager.init();
        
        // Supervisor edit: country → schools (AJAX)
        $(document).on('change', '#supervisor-edit-country', function() {
            var $form = $('#supervisor-edit-form');
            if (!$form.length) {
                return;
            }
            var nonce = $form.attr('data-schools-nonce');
            var selectSchool = $form.attr('data-select-school') || '';
            var cid = $(this).val();
            var $sch = $('#supervisor-edit-school');
            if (!cid) {
                $sch.prop('disabled', true).html('<option value="">' + $('<div/>').text(selectSchool).html() + '</option>');
                return;
            }
            $sch.prop('disabled', true);
            $.post(typeof ajaxurl !== 'undefined' ? ajaxurl : (wevoiAdmin && wevoiAdmin.ajaxurl) || '', {
                action: 'get_schools_by_country',
                country_id: cid,
                nonce: nonce
            }, function(res) {
                var html = '<option value="">' + $('<div/>').text(selectSchool).html() + '</option>';
                if (res.success && res.data && res.data.length) {
                    res.data.forEach(function(s) {
                        html += '<option value="' + String(s.schoolid) + '">' + $('<div/>').text(s.schoolname).html() + '</option>';
                    });
                }
                $sch.html(html).prop('disabled', false);
            });
        });

        // Initialize email functionality
        EmailManager.init();
        
        // Initialize Select2 if available
        if ($.fn.select2) {
            $('.select2-enabled').select2();
        }
        
        Utils.log('Admin script initialized successfully');
    }

    // Start application
    initApp();
    
    // Set up global error handler for AJAX requests
    $(document).ajaxError(function(event, jqXHR, settings, error) {
        Utils.error('Global AJAX error:', {
            event: event,
            status: jqXHR.status,
            response: jqXHR.responseText,
            error: error,
            url: settings.url,
            data: settings.data
        });
    });
});