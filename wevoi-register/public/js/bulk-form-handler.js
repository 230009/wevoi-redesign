(function($) {
    'use strict';

    const stripe = Stripe(wevoiBulkRegister.stripeKey);
    let elements;
    let card;
    let currentPrice = 0;
    let selectedCompetition = null;
    let appliedCoupon = null;
    let selectedStudents = new Set();

    // Form elements
    const form = $('#wevoi_bulk_register_form form');
    const submitButton = $('#submit-button');
    const applyButton = $('#apply-coupon');
    const changeButton = $('#change-coupon');
    const couponInput = $('#coupon-code');
    const couponMessage = $('#coupon-message');
    const priceDisplay = $('#wevoi-price');
    const studentCountDisplay = $('#student-count');
    const payerInfoSection = $('#payer-info-section');
    const cardErrors = $('#card-errors');

    // Initialize on document ready
    $(document).ready(function() {
        initStripeElements().catch(console.error);
        setupEventListeners();
        // Pre-select first competition so price updates when students are selected
        const $firstCompetition = $('input[name="competition"]:first');
        if ($firstCompetition.length) {
            selectedCompetition = $firstCompetition.val();
            currentPrice = parseFloat($firstCompetition.data('price')) || 0;
            $firstCompetition.prop('checked', true);
            $firstCompetition.closest('.wevoi-competition-item').addClass('selected');
            updateAllStudentVideoTitles($firstCompetition.attr('data-name') || '');
            loadCompetitionTopics(selectedCompetition);
        }
        updatePrice();
        updateStudentCount();
    });

    // Initialize Stripe Elements
    async function initStripeElements() {
        try {
            elements = stripe.elements();

            card = elements.create('card', {
                style: {
                    base: {
                        fontSize: '16px',
                        color: '#32325d',
                        '::placeholder': {
                            color: '#aab7c4'
                        },
                        backgroundColor: '#ffffff'
                    },
                    invalid: {
                        color: '#dc3545',
                        iconColor: '#dc3545'
                    }
                },
                hidePostalCode: true
            });

            await card.mount('#card-element');

            card.on('change', function(event) {
                if (event.error) {
                    showError(event.error.message);
                } else {
                    showError('');
                }
            });

        } catch (error) {
            console.error('Stripe Elements initialization error:', error);
            showError('Failed to initialize payment form. Please refresh the page.');
        }
    }

    // Setup event listeners
    function setupEventListeners() {
        // Competition selection
        $(document).on('change', 'input[name="competition"]', function() {
            selectedCompetition = $(this).val();
            currentPrice = parseFloat($(this).data('price'));
            $('.wevoi-competition-item').removeClass('selected');
            $(this).closest('.wevoi-competition-item').addClass('selected');
            updatePrice();
            resetCoupon();
            updateAllStudentVideoTitles($(this).attr('data-name') || '');
            loadCompetitionTopics(selectedCompetition);
        });

// Student selection (delegated so dynamically added rows work)
        $(document).on('change', '.wevoi-student-select input[type="checkbox"]', function() {
            const studentId = $(this).val();
            const $studentItem = $(this).closest('.wevoi-student-item');
            const youtubeInput = $studentItem.find('.youtube-link-input');

            if (this.checked) {
                selectedStudents.add(studentId);
                youtubeInput.prop('disabled', false).prop('required', true);
                $studentItem.addClass('selected');
            } else {
                selectedStudents.delete(studentId);
                youtubeInput.prop('disabled', true).prop('required', false);
                $studentItem.removeClass('selected');
            }

            updatePrice();
            updateStudentCount();
        });

        // Coupon buttons
        applyButton.on('click', validateCoupon);
        changeButton.on('click', resetCoupon);

        // Add student by email
        $('#wevoi-add-student-btn').on('click', handleAddStudentByEmail);

        // Form submission
        form.on('submit', handleFormSubmit);
    }

    function handleAddStudentByEmail() {
        const emailInput = $('#wevoi-add-student-email');
        const msgEl = $('#wevoi-add-student-msg');
        const email = emailInput.val().trim();
        msgEl.removeClass('success error').text('');

        if (!email) {
            msgEl.addClass('error').text('Please enter an email address.');
            return;
        }

        const competitionCode = selectedCompetition || $('input[name="competition"]:first').val();
        if (!competitionCode) {
            msgEl.addClass('error').text('Please select a competition first.');
            return;
        }

        $('#wevoi-add-student-btn').prop('disabled', true);

        $.ajax({
            url: wevoiBulkRegister.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wevoi_bulk_lookup_student_by_email',
                nonce: wevoiBulkRegister.nonce,
                email: email,
                competition_code: competitionCode
            },
            dataType: 'json'
        }).done(function(res) {
            if (res.success && res.data) {
                const d = res.data;
                if ($('.wevoi-student-item[data-student-id="' + d.ID + '"]').length) {
                    msgEl.addClass('error').text('This student is already in the list.');
                    return;
                }
                const topicHtml = (d.topic) ? '<div class="student-topic">Topic: ' + escapeHtml(d.topic) + '</div>' : '';
                const ageHtml = d.age_category ? '<span class="age-category wevoi-age-category-value">' + escapeHtml(d.age_category) + '</span>' : '';
                const row = '<div class="wevoi-student-item" data-student-id="' + d.ID + '" data-student-name="' + escapeHtml(d.student_name || '') + '" data-student-country="' + escapeHtml(d.countryname || '') + '" data-age-category="' + escapeHtml(d.age_category || '') + '" data-topic="' + escapeHtml(d.topic || '') + '">' +
                    '<div class="wevoi-student-header">' +
                    '<label class="wevoi-student-select">' +
                    '<input type="checkbox" name="selected_students[]" value="' + d.ID + '"> ' +
                    escapeHtml(d.student_name) + ' <a href="mailto:' + escapeHtml(d.email) + '" class="student-email-link">' + escapeHtml(d.email) + '</a>' +
                    '</label>' +
                    '<div class="wevoi-student-details">' +
                    '<div class="age-category-wrapper">' +
                    ageHtml + topicHtml +
                    '</div>' +
                    '<span class="school-name">' + escapeHtml(d.schoolname || '') + '</span>' +
                    '</div></div>' +
                    '<div class="wevoi-student-video-title"><span class="wevoi-student-video-title-label">Video Title</span><p class="wevoi-student-video-title-text"></p></div>' +
                    '<div class="wevoi-student-youtube">' +
                    '<input type="url" name="youtube_link_' + d.ID + '" class="youtube-link-input" placeholder="Enter YouTube Link" disabled>' +
                    '</div></div>';
                $('.wevoi-student-list').append(row);
                $('#wevoi-no-students-msg').hide();
                updateAllStudentVideoTitles();
                msgEl.addClass('success').text('Student added. Select the checkbox and enter the YouTube link.');
                emailInput.val('');
            } else {
                msgEl.addClass('error').text(res.data || 'Could not add student.');
            }
        }).fail(function(xhr, status, err) {
            const msg = (xhr.responseJSON && xhr.responseJSON.data) ? xhr.responseJSON.data : (err || 'Request failed.');
            msgEl.addClass('error').text(msg);
        }).always(function() {
            $('#wevoi-add-student-btn').prop('disabled', false);
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function getSelectedCompetitionName() {
        return $('input[name="competition"]:checked').attr('data-name') || '';
    }

    function buildStudentVideoTitle($item, competitionName) {
        const parts = [
            $.trim($item.attr('data-student-name') || ''),
            $.trim($item.attr('data-student-country') || ''),
            competitionName || getSelectedCompetitionName(),
            $.trim($item.attr('data-topic') || '')
        ].filter(function(part) {
            return part !== '';
        });
        return parts.join(' - ');
    }

    function updateAllStudentVideoTitles(competitionName) {
        const name = competitionName || getSelectedCompetitionName();
        $('.wevoi-student-item').each(function() {
            const title = buildStudentVideoTitle($(this), name);
            $(this).find('.wevoi-student-video-title-text').text(title || 'Select a competition to see this student\'s video title.');
        });
    }

    function loadCompetitionTopics(competitionCode) {
        if (!competitionCode) {
            updateAllStudentVideoTitles();
            return;
        }

        $.ajax({
            url: wevoiBulkRegister.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wevoi_get_competition_topics',
                nonce: wevoiBulkRegister.nonce,
                competition_code: competitionCode
            },
            dataType: 'json'
        }).done(function(res) {
            if (res.success && res.data && res.data.topics) {
                const topics = res.data.topics;
                $('.wevoi-student-item').each(function() {
                    const age = $(this).attr('data-age-category') || '';
                    if (age && topics[age]) {
                        $(this).attr('data-topic', topics[age]);
                        let $topic = $(this).find('.student-topic');
                        if ($topic.length) {
                            $topic.text('Topic: ' + topics[age]);
                        } else {
                            $(this).find('.age-category-wrapper').append('<div class="student-topic">Topic: ' + escapeHtml(topics[age]) + '</div>');
                        }
                    }
                });
            }
            updateAllStudentVideoTitles();
        }).fail(function() {
            updateAllStudentVideoTitles();
        });
    }

    // Update total price
    function updatePrice() {
        if (!selectedCompetition || selectedStudents.size === 0) {
            priceDisplay.text('0.00');
            return;
        }

        let finalPrice = currentPrice * selectedStudents.size;

        if (appliedCoupon) {
            if (appliedCoupon.discountAmount > 0) {
                // Discount amount is applied once to the total, not per student
                const discountAmount = parseFloat(appliedCoupon.discountAmount);
                finalPrice = Math.max(0, finalPrice - discountAmount);
            } else if (appliedCoupon.discountPercentage > 0) {
                const discount = (finalPrice * parseFloat(appliedCoupon.discountPercentage)) / 100;
                finalPrice = Math.max(0, finalPrice - discount);
            }
        }

        if (finalPrice === 0) {
            payerInfoSection.hide();
            submitButton.text('REGISTER NOW');
            $('#payer-name, #payer-country').prop('required', false);
        } else {
            payerInfoSection.show();
            submitButton.text('PAY NOW');
            $('#payer-name, #payer-country').prop('required', true);
        }

        priceDisplay.text(finalPrice.toFixed(2));
    }

    // Update student count display
    function updateStudentCount() {
        studentCountDisplay.text(selectedStudents.size);
    }

    // Validate coupon
    async function validateCoupon() {
        if (!selectedCompetition) {
            showNotification('Please select a competition first', 'error');
            return;
        }

        const couponCode = couponInput.val()?.trim();
        if (!couponCode) {
            showNotification('Please enter a coupon code', 'error');
            return;
        }

        try {
            const response = await $.ajax({
                url: wevoiBulkRegister.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wevoi_validate_coupon',
                    nonce: wevoiBulkRegister.nonce,
                    competition_code: selectedCompetition,
                    coupon_code: couponCode
                }
            });

            if (response.success) {
                appliedCoupon = {
                    code: couponCode,
                    discountAmount: response.data.discount_amount,
                    discountPercentage: response.data.discount_percentage
                };
                updatePrice();
                showCouponSuccess(response.data.message);
                toggleCouponButtons(true);
            } else {
                showCouponError(response.data);
            }
        } catch (error) {
            console.error('Coupon validation error:', error);
            showCouponError('Failed to validate coupon');
        }
    }

    // Reset coupon
    function resetCoupon() {
        appliedCoupon = null;
        couponInput.val('').prop('disabled', false);
        applyButton.show();
        changeButton.hide();
        couponMessage.empty();
        updatePrice();
    }

    // Toggle coupon buttons
    function toggleCouponButtons(couponApplied) {
        if (couponApplied) {
            couponInput.prop('disabled', true);
            applyButton.hide();
            changeButton.show();
        } else {
            couponInput.prop('disabled', false);
            applyButton.show();
            changeButton.hide();
        }
    }

    // Form submission handler
    async function handleFormSubmit(e) {
        e.preventDefault();

        try {
            // Basic validations
            if (!selectedCompetition) {
                throw new Error('Please select a competition');
            }

            if (selectedStudents.size === 0) {
                throw new Error('Please select at least one student');
            }

            // Collect student data
            const studentData = [];
            let hasInvalidLinks = false;

            selectedStudents.forEach(studentId => {
                const youtubeLink = $(`.youtube-link-input[name="youtube_link_${studentId}"]`).val()?.trim();

                if (!youtubeLink || !isValidYouTubeLink(youtubeLink)) {
                    hasInvalidLinks = true;
                    return;
                }

                studentData.push({
                    id: studentId,
                    youtube_link: youtubeLink
                });
            });

            if (hasInvalidLinks) {
                throw new Error('Please enter valid YouTube links for all selected students');
            }

            // Check terms acceptance
            if (!$('#consent-checkbox').is(':checked')) {
                throw new Error('Please accept the terms and conditions');
            }

            submitButton.prop('disabled', true);
            const finalPrice = parseFloat(priceDisplay.text());

            // Process as free or paid registration
            if (finalPrice === 0) {
                await processFreeRegistration(studentData);
            } else {
                await processPayment(studentData);
            }

        } catch (error) {
            console.error('Form submission error:', error);
            showNotification(error.message, 'error');
            submitButton.prop('disabled', false);
        }
    }

    // Process free registration
    async function processFreeRegistration(studentData) {
        submitButton.text('Processing...');

        const formData = {
            action: 'wevoi_process_bulk_registration',
            nonce: wevoiBulkRegister.nonce,
            competition_code: selectedCompetition,
            students: JSON.stringify(studentData),
            coupon_code: appliedCoupon?.code || ''
        };

        const response = await $.ajax({
            url: wevoiBulkRegister.ajaxUrl,
            type: 'POST',
            data: formData,
            dataType: 'json'
        });

        if (!response.success) {
            throw new Error(response.data || 'Registration failed');
        }

        submitButton.text('Registration Complete!');
        showNotification('Registration successful! Redirecting...', 'success');
    }

    async function processPayment(studentData) {
    try {
        submitButton.prop('disabled', true).text('Processing Payment...');

        const formData = {
            action: 'wevoi_process_bulk_registration',
            nonce: wevoiBulkRegister.nonce,
            competition_code: selectedCompetition,
            students: JSON.stringify(studentData),
            payer_name: $('#payer-name').val().trim(),
            payer_countryid: $('#payer-country').val(),
            coupon_code: appliedCoupon?.code || ''
        };

        // Log form data
        console.log('Form data being sent:', formData);

        const registrationResponse = await $.ajax({
            url: wevoiBulkRegister.ajaxUrl,
            type: 'POST',
            data: formData,
            dataType: 'json'
        });

        // Log registration response
        console.log('Registration response:', registrationResponse);

        if (!registrationResponse.success) {
            throw new Error(registrationResponse.data || 'Registration failed');
        }

        const result = await stripe.confirmCardPayment(registrationResponse.data.client_secret, {
            payment_method: {
                card: card,
                billing_details: {
                    name: formData.payer_name,
                    address: {
                        country: registrationResponse.data.country_code
                    }
                }
            }
        });

        // Stripe sonucunu loglayalım
        console.log('Stripe payment result:', result);

        if (result.error) {
            throw result.error;
        }

        if (result.paymentIntent.status === 'succeeded') {
            // Ödeme başarılı olduktan sonra buton durumunu güncelle
            submitButton.text('Payment Successful!');

            const updateResponse = await $.ajax({
                url: wevoiBulkRegister.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wevoi_update_bulk_payment_status',
                    nonce: wevoiBulkRegister.nonce,
                    payment_intent_id: result.paymentIntent.id,
                    customer_id: registrationResponse.data.customer_id,
                    registration_ids: registrationResponse.data.registration_ids
                },
                dataType: 'json'
            });

            // Log update response
            console.log('Update payment status response:', updateResponse);

            if (!updateResponse.success) {
                throw new Error('Payment status update failed');
            }

            showNotification('Payment successful! Redirecting...', 'success');
            setTimeout(() => {
                window.location.replace('https://www.wevoi.org/submissions/');
            }, 2000);
        }
    } catch (error) {
        console.error('Payment processing error:', error);
        showNotification(error.message || 'Payment failed', 'error');
        submitButton.prop('disabled', false).text('PAY NOW');
    }
}

    // Validate YouTube link
    function isValidYouTubeLink(url) {
        if (!url || typeof url !== 'string') {
            return false;
        }

        url = url.trim();

        const patterns = {
            standard: /^(?:https?:\/\/)?(?:www\.|m\.)?youtube\.com\/watch\?(?:.*&)?v=([a-zA-Z0-9_-]{11})(?:\S*)?$/,
            shortened: /^(?:https?:\/\/)?(?:www\.|m\.)?youtu\.be\/([a-zA-Z0-9_-]{11})(?:\S*)?$/,
            shorts: /^(?:https?:\/\/)?(?:www\.|m\.)?youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})(?:\S*)?$/,
            embed: /^(?:https?:\/\/)?(?:www\.|m\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]{11})(?:\S*)?$/
        };

        return Object.values(patterns).some(pattern => pattern.test(url));
    }

    // Show notification
    function showNotification(message, type) {
        const notification = $('#wevoi-notification');
        notification
            .removeClass('success error')
            .addClass(type)
            .text(message)
            .fadeIn();

        // Başarılı durumlarda 2 saniye sonra yönlendirme yap
        if (type === 'success' && message.includes('successful')) {
            setTimeout(() => {
                notification.fadeOut(() => {
                    window.location.replace('https://www.wevoi.org/submissions/');
                });
            }, 2000);
        } else {
            // Başarısız durumlarda 5 saniye sonra mesajı gizle
            setTimeout(() => {
                notification.fadeOut();
            }, 5000);
        }
    }

    // Show error
    function showError(message) {
        cardErrors.text(message);
    }

    // Show coupon success
    function showCouponSuccess(message) {
        couponMessage
            .removeClass('error')
            .addClass('success')
            .html(`<span style="color: green;">${message}</span>`);
    }

    // Show coupon error
    function showCouponError(message) {
        couponMessage
            .removeClass('success')
            .addClass('error')
            .html(`<span style="color: red;">${message}</span>`);
    }

})(jQuery);
