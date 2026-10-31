jQuery(document).ready(function($) {
    let stripe, cardElement;
    let selectedCompetition = null;
    let appliedCoupon = null;
    let basePrice = 0;
    const submitButton = $('#submit-button');
    const formMode = (typeof wevoiForum !== 'undefined' && wevoiForum.formMode) ? wevoiForum.formMode : 'standard';
    const forumPaymentPhase = (typeof wevoiForum !== 'undefined' && wevoiForum.forumPaymentPhase) ? wevoiForum.forumPaymentPhase : 'payment_required';

    // Initialize Stripe
    if (typeof wevoiForum !== 'undefined' && wevoiForum.publishableKey) {
        stripe = Stripe(wevoiForum.publishableKey);
        setTimeout(function() {
            if (forumPaymentPhase !== 'pre_registration' || formMode === 'payment_completion') {
                initStripeElements();
            }
        }, 100);
    } else {
        console.error('Stripe publishable key not found');
    }

    // Initialize Stripe Elements
    function initStripeElements() {
        try {
            const cardElementContainer = document.getElementById('card-element');
            if (!cardElementContainer) {
                console.error('Card element container not found');
                return;
            }
            if (forumPaymentPhase === 'pre_registration' && formMode === 'standard') {
                return;
            }

            const elements = stripe.elements();

            const elementOptions = {
                style: {
                    base: {
                        fontSize: '16px',
                        color: '#32325d',
                        fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                        '::placeholder': {
                            color: '#aab7c4'
                        }
                    },
                    invalid: {
                        color: '#fa755a',
                        iconColor: '#fa755a'
                    }
                },
                hidePostalCode: true
            };

            cardElement = elements.create('card', elementOptions);

            cardElement.mount('#card-element');

            cardElement.on('change', function(event) {
                const displayError = document.getElementById('card-errors');
                if (event.error) {
                    displayError.textContent = event.error.message;
                    displayError.style.display = 'block';
                } else {
                    displayError.textContent = '';
                    displayError.style.display = 'none';
                }
            });

            console.log('Stripe Elements initialized successfully');
        } catch (error) {
            console.error('Stripe Elements initialization error:', error);
            const displayError = document.getElementById('card-errors');
            if (displayError) {
                displayError.textContent = 'Failed to initialize payment form. Please refresh the page.';
                displayError.style.display = 'block';
            }
        }
    }

    // Get selected competition
    $('input[name="competition"]').on('change', function() {
        selectedCompetition = $(this).val();
        basePrice = parseFloat($(this).data('price')) || 0;
        updatePrice();
    });

    // Set initial competition
    const initialCompetition = $('input[name="competition"]:checked');
    if (initialCompetition.length) {
        selectedCompetition = initialCompetition.val();
        if (formMode === 'payment_completion' && typeof wevoiForum.completionAmount !== 'undefined') {
            basePrice = parseFloat(wevoiForum.completionAmount) || 0;
        } else {
            basePrice = parseFloat(initialCompetition.data('price')) || 0;
        }
        updatePrice();
    }

    if (formMode === 'payment_completion') {
        submitButton.text('COMPLETE PAYMENT');
    } else if (forumPaymentPhase === 'pre_registration') {
        submitButton.text('SUBMIT PRE-REGISTRATION');
    }

    // Supervisor/Agency lookup by email (optional field)
    const $supervisorEmail = $('#supervisor_agency_email');
    const $supervisorId = $('#supervisor_id');
    const $supervisorNameDisplay = $('#supervisor-agency-name');
    if ($supervisorEmail.length) {
        let lookupTimeout;
        function doSupervisorLookup() {
            const email = $supervisorEmail.val().trim();
            if (!email) {
                $supervisorId.val('');
                $supervisorNameDisplay.text('').removeClass('error');
                return;
            }
            $.ajax({
                url: wevoiForum.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wevoi_lookup_supervisor_by_email',
                    nonce: typeof wevoiForum !== 'undefined' && wevoiForum.registerNonce ? wevoiForum.registerNonce : wevoiForum.nonce,
                    email: email
                },
                dataType: 'json',
                success: function(res) {
                    if (res.success && res.data && res.data.id) {
                        $supervisorId.val(res.data.id);
                        $supervisorNameDisplay.text(res.data.display_name || (res.data.first_name + ' ' + res.data.last_name).trim() || 'Found').removeClass('error');
                    } else {
                        $supervisorId.val('');
                        $supervisorNameDisplay.text(res.data && res.data.message ? res.data.message : 'No WeVoi account found with this email. Enter a valid Supervisor/Agency email or leave blank—registration cannot proceed otherwise.').addClass('error');
                    }
                },
                error: function(xhr) {
                    let msg = 'Could not verify email.';
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) msg = xhr.responseJSON.data.message;
                    $supervisorId.val('');
                    $supervisorNameDisplay.text(msg).addClass('error');
                }
            });
        }
        $supervisorEmail.on('blur', doSupervisorLookup);
        $supervisorEmail.on('input', function() {
            clearTimeout(lookupTimeout);
            $supervisorId.val('');
            $supervisorNameDisplay.text('').removeClass('error');
            if ($(this).val().trim().length >= 3) {
                lookupTimeout = setTimeout(doSupervisorLookup, 400);
            }
        });
    }

    // Update price display
    function updatePrice() {
        let finalPrice = basePrice;
        if (appliedCoupon) {
            if (appliedCoupon.discount_type === 'percentage') {
                const discount = (basePrice * appliedCoupon.discount_value) / 100;
                const maxDiscount = appliedCoupon.max_discount || 0;
                finalPrice = basePrice - (maxDiscount > 0 && discount > maxDiscount ? maxDiscount : discount);
            } else if (appliedCoupon.discount_type === 'fixed') {
                finalPrice = basePrice - appliedCoupon.discount_value;
            } else if (appliedCoupon.discount_type === 'both') {
                const percentageDiscount = (basePrice * appliedCoupon.discount_value) / 100;
                const fixedDiscount = appliedCoupon.discount_value_fixed || 0;
                let totalDiscount = percentageDiscount + fixedDiscount;
                if (appliedCoupon.max_discount > 0 && totalDiscount > appliedCoupon.max_discount) {
                    totalDiscount = appliedCoupon.max_discount;
                }
                finalPrice = basePrice - totalDiscount;
            }
            if (finalPrice < 0) finalPrice = 0;
        }
        $('#wevoi-price').text(finalPrice.toFixed(2));

        const payerInfoSection = $('#payer-info-section');

        if (forumPaymentPhase === 'pre_registration' && formMode === 'standard') {
            payerInfoSection.hide();
            $('#payer-name, #payer-country').prop('required', false);
            $('#card-element').closest('.wevoi-form-row').find('label .required').hide();
            submitButton.text('SUBMIT PRE-REGISTRATION');
            return;
        }

        if (finalPrice === 0) {
            payerInfoSection.hide();
            $('#payer-name, #payer-country').prop('required', false);
            submitButton.text(formMode === 'payment_completion' ? 'COMPLETE PAYMENT' : 'REGISTER FREE');
        } else {
            payerInfoSection.show();
            $('#payer-name, #payer-country').prop('required', true);
            $('#payer-name').closest('.wevoi-form-row').find('label .required').show();
            $('#payer-country').closest('.wevoi-form-row').find('label .required').show();
            $('#card-element').closest('.wevoi-form-row').find('label .required').show();
            submitButton.text(formMode === 'payment_completion' ? 'COMPLETE PAYMENT' : 'PAY NOW');
        }
    }

    // Coupon handling
    $('#apply-coupon').on('click', function() {
        const couponCode = $('#coupon-code').val().trim();
        if (!couponCode) {
            showCouponMessage('Please enter a coupon code', 'error');
            return;
        }

        if (!selectedCompetition) {
            showCouponMessage('Please select a competition first', 'error');
            return;
        }

        $.ajax({
            url: wevoiForum.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wevoi_validate_forum_coupon',
                nonce: wevoiForum.nonce,
                competition_code: selectedCompetition,
                coupon_code: couponCode
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    appliedCoupon = {
                        code: couponCode,
                        discount_type: response.data.discount_type,
                        discount_value: response.data.discount_value,
                        discount_value_fixed: response.data.discount_value_fixed || 0,
                        max_discount: response.data.max_discount || 0
                    };
                    $('#coupon-code').prop('disabled', true);
                    $('#apply-coupon').hide();
                    $('#change-coupon').show();
                    showCouponMessage('Coupon applied successfully!', 'success');
                    updatePrice();
                } else {
                    showCouponMessage(response.data || 'Invalid coupon code', 'error');
                    appliedCoupon = null;
                }
            },
            error: function() {
                showCouponMessage('Error validating coupon', 'error');
                appliedCoupon = null;
            }
        });
    });

    $('#change-coupon').on('click', function() {
        appliedCoupon = null;
        $('#coupon-code').val('').prop('disabled', false);
        $('#apply-coupon').show();
        $('#change-coupon').hide();
        $('#coupon-message').hide();
        updatePrice();
    });

    function showCouponMessage(message, type) {
        const messageDiv = $('#coupon-message');
        messageDiv.removeClass('success error').addClass(type).text(message).show();
        if (type === 'success') {
            setTimeout(() => messageDiv.fadeOut(), 3000);
        }
    }

    // Form submission
    $('#forum-registration-form').on('submit', async function(e) {
        e.preventDefault();

        console.log('Form submitted');
        console.log('Selected competition:', selectedCompetition);
        console.log('Base price:', basePrice);
        console.log('Applied coupon:', appliedCoupon);

        if (!selectedCompetition) {
            showNotification('Please select a competition', 'error');
            return;
        }

        if (!$('#consent-checkbox').is(':checked')) {
            showNotification('Please confirm the terms and conditions', 'error');
            return;
        }

        // Supervisor/Agency (student form only): if email is entered, it must be valid (supervisor_id set).
        const supervisorEmail = ($('#supervisor_agency_email').val() || '').trim();
        const supervisorId = ($('#supervisor_id').val() || '').trim();
        if (supervisorEmail && !supervisorId) {
            showNotification('No WeVoi account found with this email. Please enter a valid Supervisor/Agency email if you know one, or leave the field blank. Registration cannot continue with an email that is not in the system.', 'error');
            $('#supervisor-agency-name').addClass('error').text('No WeVoi account found with this email. Enter a valid Supervisor/Agency email or leave blank—registration cannot proceed otherwise.');
            return;
        }

        // Calculate final price to determine if it's free
        let finalPrice = basePrice;
        if (appliedCoupon) {
            if (appliedCoupon.discount_type === 'percentage') {
                const discount = (basePrice * appliedCoupon.discount_value) / 100;
                const maxDiscount = appliedCoupon.max_discount || 0;
                finalPrice = basePrice - (maxDiscount > 0 && discount > maxDiscount ? maxDiscount : discount);
            } else if (appliedCoupon.discount_type === 'fixed') {
                finalPrice = basePrice - appliedCoupon.discount_value;
            } else if (appliedCoupon.discount_type === 'both') {
                const percentageDiscount = (basePrice * appliedCoupon.discount_value) / 100;
                const fixedDiscount = appliedCoupon.discount_value_fixed || 0;
                let totalDiscount = percentageDiscount + fixedDiscount;
                if (appliedCoupon.max_discount > 0 && totalDiscount > appliedCoupon.max_discount) {
                    totalDiscount = appliedCoupon.max_discount;
                }
                finalPrice = basePrice - totalDiscount;
            }
            if (finalPrice < 0) finalPrice = 0;
        }

        if (formMode === 'payment_completion') {
            try {
                await processPaymentCompletion();
            } catch (error) {
                console.error('Payment completion error:', error);
                showNotification(error.message || 'Error processing payment', 'error');
                submitButton.prop('disabled', false).text('COMPLETE PAYMENT');
            }
            return;
        }

        if (forumPaymentPhase === 'pre_registration') {
            processFreeRegistration();
            return;
        }

        if (finalPrice === 0) {
            processFreeRegistration();
        } else {
            try {
                await processPayment();
            } catch (error) {
                console.error('Form submission error:', error);
                showNotification('Error processing registration', 'error');
                submitButton.prop('disabled', false).text('PAY NOW');
            }
        }
    });

    // Process free registration
    function processFreeRegistration() {
        console.log('Processing free registration...');
        console.log('Selected competition:', selectedCompetition);
        console.log('Applied coupon:', appliedCoupon);

        const formData = {
            action: 'wevoi_process_forum_registration',
            nonce: wevoiForum.nonce,
            competition_code: selectedCompetition,
            coupon_code: appliedCoupon ? appliedCoupon.code : ''
        };

        // Add role-specific fields
        const whatsappNumber = $('#whatsapp_number').val();
        if (whatsappNumber && whatsappNumber.trim) {
            const trimmed = whatsappNumber.trim();
            if (trimmed) {
                formData.whatsapp_number = trimmed;
            }
        }

        const passportNumber = $('#passport_number').val();
        if (passportNumber && passportNumber.trim) {
            const trimmed = passportNumber.trim();
            if (trimmed) {
                formData.passport_number = trimmed;
            }
        }

        // Student-specific optional: Supervisor/Agency (user ID from email lookup)
        const supervisorIdEl = $('#supervisor_id');
        if (supervisorIdEl.length) {
            const sid = supervisorIdEl.val();
            if (sid && sid.trim()) {
                formData.supervisor_id = sid.trim();
            }
        }

        const parentEmailEl = $('#parent_email');
        if (parentEmailEl.length) {
            const parentEmail = parentEmailEl.val();
            if (parentEmail && parentEmail.trim) {
                const trimmed = parentEmail.trim();
                if (trimmed) {
                    formData.parent_email = trimmed;
                    const parentFirstName = $('#parent_first_name').val();
                    if (parentFirstName && parentFirstName.trim) {
                        formData.parent_first_name = parentFirstName.trim();
                    }
                    const parentLastName = $('#parent_last_name').val();
                    if (parentLastName && parentLastName.trim) {
                        formData.parent_last_name = parentLastName.trim();
                    }
                    const parentWhatsapp = $('#parent_whatsapp').val();
                    if (parentWhatsapp && parentWhatsapp.trim) {
                        formData.parent_whatsapp = parentWhatsapp.trim();
                    }
                }
            }
        }

        // Supervisor/Parent-specific fields
        const genderEl = $('#gender');
        if (genderEl.length) {
            const gender = genderEl.val();
            if (gender) {
                formData.gender = gender;
            }
        }

        const birthDateEl = $('#birth_date');
        if (birthDateEl.length) {
            const birthDate = birthDateEl.val();
            if (birthDate) {
                formData.birth_date = birthDate;
            }
        }

        // Optional payer info for free registration
        const payerNameEl = $('#payer-name');
        if (payerNameEl.length) {
            const payerName = payerNameEl.val();
            if (payerName && payerName.trim) {
                const trimmed = payerName.trim();
                if (trimmed) {
                    formData.payer_name = trimmed;
                }
            }
        }
        const payerCountryEl = $('#payer-country');
        if (payerCountryEl.length) {
            const payerCountry = payerCountryEl.val();
            if (payerCountry) {
                formData.payer_countryid = payerCountry;
            }
        }

        submitButton.prop('disabled', true).text('Processing...');

        $.ajax({
            url: wevoiForum.ajaxUrl,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const msg = response.data && response.data.is_pre_registration
                        ? 'Pre-registration successful! Redirecting...'
                        : 'Registration successful! Redirecting...';
                    showNotification(msg, 'success');
                    setTimeout(() => {
                        const redirectUrl = typeof wevoiForum !== 'undefined' && wevoiForum.redirectUrl
                            ? wevoiForum.redirectUrl
                            : 'https://www.wevoi.org/submissions/';
                        window.location.href = redirectUrl;
                    }, 2000);
                } else {
                    console.error('Registration failed:', response);
                    const errorMessage = response.data || 'Registration failed. Please check all required fields are filled.';
                    showNotification(errorMessage, 'error');
                    // Restore button text based on final price
                    const finalPrice = parseFloat($('#wevoi-price').text()) || 0;
                    submitButton.prop('disabled', false).text(finalPrice === 0 ? 'REGISTER FREE' : 'PAY NOW');
                }
            },
            error: function(xhr, status, error) {
                console.error('Registration error:', error);
                console.error('XHR response:', xhr.responseText);

                // Try to parse error message from response
                let errorMessage = 'Error processing registration';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.data) {
                        errorMessage = response.data;
                    }
                } catch (e) {
                    // If response is not JSON, use default message
                    if (xhr.responseText) {
                        errorMessage = xhr.responseText;
                    }
                }

                showNotification(errorMessage, 'error');
                // Restore button text based on final price
                const finalPrice = parseFloat($('#wevoi-price').text()) || 0;
                submitButton.prop('disabled', false).text(finalPrice === 0 ? 'REGISTER FREE' : 'PAY NOW');
            }
        });
    }

    async function processPaymentCompletion() {
        const payerName = $('#payer-name').length ? ($('#payer-name').val() || '').trim() : '';
        const payerCountry = $('#payer-country').length ? $('#payer-country').val() : '';
        if (!payerName || !payerCountry) {
            showNotification('Please enter payer name and country.', 'error');
            return;
        }
        if (!stripe || !cardElement) {
            showNotification('Payment form is not ready. Please refresh the page.', 'error');
            return;
        }
        submitButton.prop('disabled', true).text('Processing...');

        const startRes = await $.ajax({
            url: wevoiForum.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wevoi_start_forum_payment',
                nonce: wevoiForum.nonce,
                registration_id: wevoiForum.registrationId,
                payer_name: payerName,
                payer_countryid: payerCountry
            },
            dataType: 'json'
        });

        if (!startRes.success) {
            throw new Error(startRes.data || 'Could not start payment');
        }

        const result = await stripe.confirmCardPayment(startRes.data.client_secret, {
            payment_method: {
                card: cardElement,
                billing_details: {
                    name: payerName,
                    address: { country: startRes.data.country_code }
                }
            }
        });

        if (result.error) {
            throw result.error;
        }

        if (result.paymentIntent.status !== 'succeeded') {
            throw new Error('Payment was not completed successfully');
        }

        submitButton.text('Payment Successful!');

        try {
            await $.ajax({
                url: wevoiForum.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wevoi_update_payment_status',
                    nonce: wevoiForum.nonce,
                    payment_intent_id: result.paymentIntent.id,
                    customer_id: startRes.data.customer_id,
                    registration_id: startRes.data.registration_id
                },
                dataType: 'json'
            });
        } catch (e) {
            console.warn('Payment status update failed; webhook may still process.', e);
        }

        showNotification('Payment successful! Redirecting...', 'success');
        setTimeout(() => {
            window.location.href = wevoiForum.redirectUrl || 'https://www.wevoi.org/submissions/';
        }, 2000);
    }

    // Process payment
    async function processPayment() {
        try {
            // Collect form data based on role
            const formData = {
                action: 'wevoi_process_forum_registration',
                nonce: wevoiForum.nonce,
                competition_code: selectedCompetition,
                payer_name: $('#payer-name').length ? ($('#payer-name').val() || '').trim() : '',
                payer_countryid: $('#payer-country').length ? $('#payer-country').val() : '',
                coupon_code: appliedCoupon ? appliedCoupon.code : ''
            };

            // Add role-specific fields
            const whatsappNumber = $('#whatsapp_number').val();
            if (whatsappNumber && whatsappNumber.trim) {
                const trimmed = whatsappNumber.trim();
                if (trimmed) {
                    formData.whatsapp_number = trimmed;
                }
            }

            const passportNumber = $('#passport_number').val();
            if (passportNumber && passportNumber.trim) {
                const trimmed = passportNumber.trim();
                if (trimmed) {
                    formData.passport_number = trimmed;
                }
            }

            // Student-specific optional: Supervisor/Agency (user ID from email lookup)
            const supervisorIdEl = $('#supervisor_id');
            if (supervisorIdEl.length) {
                const sid = supervisorIdEl.val();
                if (sid && sid.trim()) {
                    formData.supervisor_id = sid.trim();
                }
            }

            const parentEmailEl = $('#parent_email');
            if (parentEmailEl.length) {
                const parentEmail = parentEmailEl.val();
                if (parentEmail && parentEmail.trim) {
                    const trimmed = parentEmail.trim();
                    if (trimmed) {
                        formData.parent_email = trimmed;
                        const parentFirstName = $('#parent_first_name').val();
                        if (parentFirstName && parentFirstName.trim) {
                            formData.parent_first_name = parentFirstName.trim();
                        }
                        const parentLastName = $('#parent_last_name').val();
                        if (parentLastName && parentLastName.trim) {
                            formData.parent_last_name = parentLastName.trim();
                        }
                        const parentWhatsapp = $('#parent_whatsapp').val();
                        if (parentWhatsapp && parentWhatsapp.trim) {
                            formData.parent_whatsapp = parentWhatsapp.trim();
                        }
                    }
                }
            }

            // Supervisor/Parent-specific fields
            const genderEl = $('#gender');
            if (genderEl.length) {
                const gender = genderEl.val();
                if (gender) {
                    formData.gender = gender;
                }
            }

            const birthDateEl = $('#birth_date');
            if (birthDateEl.length) {
                const birthDate = birthDateEl.val();
                if (birthDate) {
                    formData.birth_date = birthDate;
                }
            }

            console.log('Sending form data:', formData);

            submitButton.prop('disabled', true).text('Processing...');

            const response = await $.ajax({
                url: wevoiForum.ajaxUrl,
                type: 'POST',
                data: formData,
                dataType: 'json',
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX Error:', {
                        status: jqXHR.status,
                        statusText: jqXHR.statusText,
                        responseText: jqXHR.responseText,
                        textStatus: textStatus,
                        errorThrown: errorThrown
                    });
                }
            });

            console.log('Server response:', response);

            if (!response.success) {
                console.error('Server returned error:', response);
                throw new Error(response.data || 'Registration failed');
            }

            console.log('Stripe payment data:', {
                clientSecret: response.data.client_secret,
                customerDetails: {
                    name: formData.payer_name,
                    country: response.data.country_code
                }
            });

            const result = await stripe.confirmCardPayment(response.data.client_secret, {
                payment_method: {
                    card: cardElement,
                    billing_details: {
                        name: formData.payer_name,
                        address: {
                            country: response.data.country_code
                        }
                    }
                }
            });

            console.log('Stripe confirmation result:', result);

            if (result.error) {
                console.error('Stripe error:', result.error);
                throw result.error;
            }

            if (result.paymentIntent.status === 'succeeded') {
                submitButton.text('Payment Successful!');

                try {
                    const updateResponse = await $.ajax({
                        url: wevoiForum.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'wevoi_update_payment_status',
                            nonce: wevoiForum.nonce,
                            payment_intent_id: result.paymentIntent.id,
                            customer_id: response.data.customer_id,
                            registration_id: response.data.registration_id
                        },
                        dataType: 'json',
                        error: function(jqXHR, textStatus, errorThrown) {
                            console.warn('Payment status update AJAX error (payment succeeded, webhook will handle):', {
                                status: jqXHR.status,
                                statusText: jqXHR.statusText,
                                responseText: jqXHR.responseText,
                                textStatus: textStatus,
                                errorThrown: errorThrown
                            });
                        }
                    });

                    console.log('Payment status update response:', updateResponse);

                    if (updateResponse && !updateResponse.success) {
                        console.warn('Payment status update returned error, but payment succeeded. Webhook will handle it.');
                        // Payment succeeded in Stripe, so continue with success flow
                        // Webhook will update the status in the background
                    }
                } catch (updateError) {
                    console.warn('Payment status update failed, but payment succeeded. Webhook will handle it.', updateError);
                    // Payment succeeded in Stripe, so continue with success flow
                    // Webhook will update the status in the background
                }

                showNotification('Payment successful! Redirecting...', 'success');

                setTimeout(() => {
                    const redirectUrl = typeof wevoiForum !== 'undefined' && wevoiForum.redirectUrl
                        ? wevoiForum.redirectUrl
                        : 'https://www.wevoi.org/submissions/';
                    window.location.href = redirectUrl;
                }, 2000);
            } else {
                // Payment intent status is not succeeded
                throw new Error('Payment was not completed successfully');
            }
        } catch (error) {
            console.error('Full payment error details:', {
                error: error,
                message: error.message,
                stack: error.stack
            });
            submitButton.prop('disabled', false).text('PAY NOW');
            showNotification(error.message || 'Payment processing failed', 'error');
        }
    }

    // Show notification
    function showNotification(message, type) {
        const notification = $('#wevoi-notification');
        notification.removeClass('success error').addClass(type).text(message).show();

        if (type === 'success') {
            setTimeout(() => {
                notification.fadeOut();
            }, 5000);
        }
    }

    // Supervisor email blur event - check and prefill
    $('#supervisor_email').on('blur', function() {
        const email = $(this).val().trim();
        if (!email) {
            return;
        }

        // Check if email is valid
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            return;
        }

        $.ajax({
            url: wevoiForum.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wevoi_get_supervisor_parent_info',
                nonce: wevoiForum.nonce,
                email: email,
                type: 'supervisor'
            },
            success: function(response) {
                if (response.success && response.data) {
                    const data = response.data;
                    // Only prefill if we got data from user_table or student_meta
                    if (data.source !== 'none') {
                        if (data.first_name) {
                            $('#supervisor_first_name').val(data.first_name);
                        }
                        if (data.last_name) {
                            $('#supervisor_last_name').val(data.last_name);
                        }
                        if (data.whatsapp) {
                            $('#supervisor_whatsapp').val(data.whatsapp);
                        }
                    }
                }
            },
            error: function() {
                // Silently fail - user can still enter manually
            }
        });
    });

    // Parent email blur event - check and prefill
    $('#parent_email').on('blur', function() {
        const email = $(this).val().trim();
        if (!email) {
            return;
        }

        // Check if email is valid
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            return;
        }

        $.ajax({
            url: wevoiForum.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wevoi_get_supervisor_parent_info',
                nonce: wevoiForum.nonce,
                email: email,
                type: 'parent'
            },
            success: function(response) {
                if (response.success && response.data) {
                    const data = response.data;
                    // Only prefill if we got data from user_table or student_meta
                    if (data.source !== 'none') {
                        if (data.first_name) {
                            $('#parent_first_name').val(data.first_name);
                        }
                        if (data.last_name) {
                            $('#parent_last_name').val(data.last_name);
                        }
                        if (data.whatsapp) {
                            $('#parent_whatsapp').val(data.whatsapp);
                        }
                    }
                }
            },
            error: function() {
                // Silently fail - user can still enter manually
            }
        });
    });
});

