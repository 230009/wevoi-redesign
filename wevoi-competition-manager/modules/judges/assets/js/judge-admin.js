jQuery(document).ready(function($) {
    // Debug flag
    const DEBUG = true;
    
    function log(message, data) {
        if (DEBUG) {
            console.log(message, data || '');
        }
    }
    
    log('Judge admin script loaded');
    
    // Modal işlemleri
    function initModals() {
        // Detay butonları
        $('.view-details').on('click', function() {
            const judgeId = $(this).data('judge-id');
            const modal = $('#judge-details-modal');
            
            // AJAX ile detayları getir
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_judge_details',
                    judge_id: judgeId,
                    nonce: wevoiJudges.nonce
                },
                beforeSend: function() {
                    modal.find('.modal-body').html('<p class="loading">Loading...</p>');
                    modal.show();
                },
                success: function(response) {
                    if (response.success) {
                        modal.find('.modal-body').html(response.data);
                    } else {
                        modal.find('.modal-body').html('<p class="error">' + (response.data || 'Error loading judge details') + '</p>');
                    }
                },
                error: function(xhr, status, error) {
                    log('AJAX Error:', error);
                    log('Status:', status);
                    log('Response:', xhr.responseText);
                    modal.find('.modal-body').html('<p class="error">Error loading judge details. Please try again.</p>');
                }
            });
        });
        
        // Edit butonları
        $('.edit-details').on('click', function() {
            const judgeId = $(this).data('judge-id');
            const modal = $('#judge-edit-modal');
            
            // AJAX ile düzenleme formunu getir
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
action: 'get_judge_data',
                   id: judgeId,
                   security: wevoiJudges.nonce
               },
               beforeSend: function() {
                   modal.find('.modal-body').html('<p class="loading">Loading...</p>');
                   modal.show();
               },
               success: function(response) {
                   if (response.success) {
                       modal.find('.modal-body').html(response.data);
                       initializeFormFields(modal);
                   } else {
                       modal.find('.modal-body').html('<p class="error">' + (response.data || 'Error loading edit form') + '</p>');
                   }
               },
               error: function(xhr, status, error) {
                   log('AJAX Error:', error);
                   log('Status:', status);
                   log('Response:', xhr.responseText);
                   modal.find('.modal-body').html('<p class="error">Error loading edit form. Please try again.</p>');
               }
           });
       });
       
       // Modal kapama işlemleri
       $('.modal-close, .modal').on('click', function(e) {
           if (e.target === this || $(e.target).hasClass('modal-close')) {
               $('.modal').hide();
           }
       });
       
       // Escape tuşu ile modalı kapat
       $(document).on('keydown', function(e) {
           if (e.key === 'Escape') {
               $('.modal').hide();
           }
       });
   }
   
   // Form initialization
   function initializeFormFields($modal) {
       // Form validasyonu
       $modal.find('form').off('submit').on('submit', function(e) {
           e.preventDefault();
           if (validateForm($(this))) {
               submitEditForm($(this));
           }
       });
   }
   
   // Form validation
   function validateForm($form) {
       let isValid = true;
       
       // Clear previous errors
       $form.find('.error').removeClass('error');
       $form.find('.error-message').remove();
       
       // Check required fields
       $form.find('[required]').each(function() {
           const $field = $(this);
           if (!$field.val().trim()) {
               isValid = false;
               $field.addClass('error');
               $field.after('<span class="error-message">This field is required</span>');
           }
       });
       
       // Email validation
       const $email = $form.find('input[type="email"]');
       if ($email.length && $email.val().trim() && !isValidEmail($email.val())) {
           isValid = false;
           $email.addClass('error');
           $email.after('<span class="error-message">Please enter a valid email address</span>');
       }
       
       return isValid;
   }
   
   // Email validation helper
   function isValidEmail(email) {
       const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
       return re.test(email);
   }
   
   // Submit edit form
   function submitEditForm($form) {
       const formData = new FormData($form[0]);
       
       $.ajax({
           url: ajaxurl,
           type: 'POST',
           data: formData,
           processData: false,
           contentType: false,
           beforeSend: function() {
               $form.find(':submit').prop('disabled', true);
               $form.find('.spinner').addClass('is-active');
           },
           success: function(response) {
               if (response.success) {
                   alert(wevoiJudges.messages.updateSuccess);
                   location.reload();
               } else {
                   alert(response.data || wevoiJudges.messages.updateError);
               }
           },
           error: function(xhr, status, error) {
               log('Submit Error:', error);
               log('Status:', status);
               log('Response:', xhr.responseText);
               alert(wevoiJudges.messages.updateError);
           },
           complete: function() {
               $form.find(':submit').prop('disabled', false);
               $form.find('.spinner').removeClass('is-active');
           }
       });
   }
   
   // Country-School Filter Handler
   function initCountrySchoolFilter() {
       $('#filter-by-country').on('change', function() {
           const countryId = $(this).val();
           const schoolSelect = $('#filter-by-school');
           
           // Reset school selection
           schoolSelect.html('<option value="">' + wevoiJudges.messages.allSchools + '</option>');
           
           if (countryId) {
               $.ajax({
                   url: ajaxurl,
                   type: 'POST',
                   data: {
                       action: 'get_schools_by_country',
                       country_id: countryId,
                       nonce: wevoiJudges.nonce
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
                   },
                   error: function(xhr, status, error) {
                       log('AJAX Error:', error);
                       log('Status:', status);
                       log('Response:', xhr.responseText);
                   }
               });
           }
       });
   }
   
   // Initialize all functionality
   function initialize() {
       initModals();
       initCountrySchoolFilter();
       
       // Delete confirmation
       $('.button-link-delete').on('click', function() {
           return confirm(wevoiJudges.messages.confirmDelete);
       });
   }
   
   // Start initialization
   initialize();
});