jQuery(document).ready(function($) {
    // Debug flag
    const DEBUG = true;
    
    function log(message, data) {
        if (DEBUG) {
            console.log(message, data || '');
        }
    }
    
    log("Email handler script loaded");
    
    // Email trigger click handler
    $(document).on('click', '.email-compose, .email-trigger', function(e) {
        e.preventDefault();
        log("Email trigger clicked", this);
        
        // Get recipient email
        var emailTo;
        if ($(this).data('email')) {
            emailTo = $(this).data('email');
        } else if ($(this).attr('href') && $(this).attr('href').indexOf('mailto:') === 0) {
            emailTo = $(this).attr('href').replace('mailto:', '');
        }
        
        if (!emailTo) {
            log("No email address found");
            return;
        }
        
        log("Email to:", emailTo);
        
        // Reset form fields
        const $form = $('#send-email-form');
        $form[0].reset();
        $('#email-to').val(emailTo);
        
        // Check snippet dropdown
        const $snippetDropdown = $('#email-snippet');
        log("Snippet dropdown:", $snippetDropdown.length ? "Found" : "Not found");
        log("Available snippets:", $snippetDropdown.find('option').length);
        
        // Reset snippet dropdown if exists
        if ($snippetDropdown.length) {
            $snippetDropdown.val('').trigger('change');
        }
        
        // Add signature if available
        if (typeof wevoiEmailData !== 'undefined' && wevoiEmailData.signature) {
            $('#email-message').val(wevoiEmailData.signature);
        }
        
        // Show modal
        $('#email-modal').show();
    });

    // Remaining code stays the same...
    // Modal close handlers
    $('.modal-close, .modal').on('click', function(e) {
        if (e.target === this) {
            $('#email-modal').hide();
        }
    });

    // Email snippet selection handler
    $('#email-snippet').on('change', function() {
        const snippetId = $(this).val();
        log("Snippet selected:", snippetId);
        
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

        // Get snippet content via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_snippet_content',
                snippet_id: snippetId,
                nonce: wevoiEmailData.nonce
            },
            beforeSend: function() {
                log("Requesting snippet content for ID:", snippetId);
            },
            success: function(response) {
                log("Snippet response:", response);
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
                    log("Error getting snippet:", response);
                    if (response.data) {
                        alert("Error loading template: " + response.data);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", status, error);
                console.error("Response:", xhr.responseText);
                alert("Error loading template. Please try again.");
            }
        });
    });
    
    
    // Cancel button handler
$(document).on('click', '.cancel-email', function() {
    $('#email-modal').hide();
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
            alert('Please fill in all required fields.');
            return;
        }
        
        log("Sending email", {
            to: $('#email-to').val(),
            subject: subject
        });
        
        // Show loading state
        $modal.addClass('sending');

        // Prepare form data
        const formData = {
            action: 'send_user_email',
            nonce: $('#email_nonce').val(),
            to: $('#email-to').val(),
            subject: subject,
            message: message
        };

        // Send via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            beforeSend: function() {
                log("Sending email request");
            },
            success: function(response) {
                log("Email send response:", response);
                $modal.removeClass('sending');
                
                if (response.success) {
                    alert('Email sent successfully!');
                    $modal.hide();
                    $form[0].reset();
                } else {
                    alert('Error sending email: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error("Email send error:", status, error);
                console.error("Response:", xhr.responseText);
                $modal.removeClass('sending');
                alert('Error sending email. Please try again.');
            }
        });
    });
    
    // Global ajaxError handler for debugging
    $(document).ajaxError(function(event, jqxhr, settings, error) {
        console.error("AJAX error in email handler:", error);
        console.error("Request:", settings.url, settings.data);
        console.error("Response:", jqxhr.responseText);
    });
});