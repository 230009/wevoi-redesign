(function($) {
    'use strict';

    const stripe = Stripe(wevoiRegister.stripeKey);
    let elements;
    let card;
    let currentPrice = 0;
    let selectedCompetition = null;
    let appliedCoupon = null;

    const form = $('#wevoi-register-form');
    const submitButton = $('#submit-button');
    const applyButton = $('#apply-coupon');
    const changeButton = $('#change-coupon');
    const couponInput = $('#coupon-code');
    const couponMessage = $('#coupon-message');
    const priceDisplay = $('#wevoi-price');
    const payerInfoSection = $('#payer-info-section');
    const cardErrors = $('#card-errors');


$(document).ready(function() {
    // Initialize Stripe Elements on page load
    initStripeElements().catch(error => {
        console.error('Stripe Elements initialization error:', error);
        showError('Failed to initialize payment form. Please refresh the page.');
    });

    // Supervisor/Agency lookup by email (optional)
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
                url: wevoiRegister.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wevoi_lookup_supervisor_by_email',
                    nonce: wevoiRegister.nonce,
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

    $(document).on('change', 'input[name="competition"]', function() {
        selectedCompetition = $(this).val();
        currentPrice = parseFloat($(this).data('price'));
        console.log('Selected competition:', selectedCompetition, 'Price:', currentPrice);
        updatePrice();
        resetCoupon();
        updateVideoTitleInstructions($(this).attr('data-name') || '', '');
        loadTopics(selectedCompetition);
    });






        $('.wevoi-application-status-select').on('change', function() {

            const $select = $(this);
            const registrationId = $select.data('registration-id');
            const newStatus = $select.val();

            $.ajax({
                url: wevoiRegister.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wevoi_update_application_status',
                    nonce: wevoiRegister.nonce,
                    registration_id: registrationId,
                    status: newStatus
                },
                success: function(response) {
                    if (response.success) {
                        const notification = $('<div class="wevoi-notification success">Status updated successfully</div>')
                            .hide()
                            .insertAfter($select)
                            .fadeIn();

                        setTimeout(() => {
                            notification.fadeOut(() => notification.remove());
                        }, 3000);
                    } else {
                        showNotification('Failed to update status: ' + response.data, 'error');
                    }
                },
                error: function() {
                    showNotification('Error updating status', 'error');
                }
            });
        });
    });




async function initStripeElements() {
    try {
        // Check if card element container exists in DOM
        const cardElement = document.getElementById('card-element');
        if (!cardElement) {
            console.warn('Card element container not found in DOM');
            return;
        }

        elements = stripe.elements();

        const elementOptions = {
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
            hidePostalCode: true,
            disabled: false,
            ariaLabel: 'Credit or debit card'
        };

        card = elements.create('card', elementOptions);
        await card.mount('#card-element');

        card.on('change', function(event) {
            if (event.error) {
                showError(event.error.message);
            } else {
                showError('');
            }
        });

        console.log('Stripe Elements initialized successfully');

    } catch (error) {
        console.error('Stripe Elements initialization error:', error);
        showError('Failed to initialize payment form. Please refresh the page.');
    }
}

    applyButton.on('click', function() {
        if (!selectedCompetition) {
            showNotification('Please select a competition first', 'error');
            return;
        }
        const couponCode = couponInput.val().trim();
        if (!couponCode) {
            showNotification('Please enter a coupon code', 'error');
            return;
        }
        validateCoupon(couponCode);
    });

    changeButton.on('click', function() {
        resetCoupon();
    });


function validateCoupon(couponCode) {
    // Önce yarışma seçili mi kontrol et
    if (!selectedCompetition) {
        showCouponError('Please select a competition first');
        return;
    }

    // Debug: Log the values being sent
    console.log('Validating coupon:', {
        couponCode: couponCode.trim(),
        competitionCode: parseInt(selectedCompetition),
        selectedCompetition: selectedCompetition
    });

    // Kullanıcıya kuponun kontrol edildiğini bildir
    couponMessage.html('<span style="color: blue;">Validating coupon...</span>');

    // AJAX ile sunucuya kupon kontrolü için istek gönder
    $.ajax({
        url: wevoiRegister.ajaxUrl,
        type: 'POST',
        data: {
            action: 'wevoi_validate_coupon',    // WordPress'te çağrılacak AJAX action
            nonce: wevoiRegister.nonce,         // Güvenlik için nonce
            competition_code: parseInt(selectedCompetition),  // Seçili yarışma kodu
            coupon_code: couponCode.trim()      // Boşlukları temizlenmiş kupon kodu
        },
        dataType: 'json',
        success: function(response) {
            console.log('Coupon validation response:', response);
            // Sunucudan başarılı yanıt gelirse
            if (response.success) {
                // Kuponu global değişkende sakla
                appliedCoupon = {
                    code: couponCode,
                    // Sabit miktar indirimi
                    discountAmount: response.data.discount_amount,
                    // Yüzdelik indirim
                    discountPercentage: response.data.discount_percentage
                };

                // Fiyatı güncelle
                updatePrice();

                // Başarı mesajını göster
                showCouponSuccess(response.data.message);

                // Kupon butonlarını güncelle (Apply -> Change)
                toggleCouponButtons(true);
            } else {
                // Hata mesajını göster
                showCouponError(response.data);
            }
        },
        error: function(xhr, status, error) {
            // AJAX hatası durumunda
            console.error('Coupon validation error:', error);
            showCouponError('An error occurred while validating the coupon');
        }
    });
}

function updatePrice() {
    // Başlangıç fiyatını currentPrice'dan alıyoruz
    let finalPrice = currentPrice;

    // Eğer bir kupon uygulanmışsa işlemlere başla
    if (appliedCoupon) {
        // İndirim miktarını tutacak değişkeni sıfır olarak başlat
        let discountAmount = 0;

        // Sabit miktar indirimi kontrolü
        // Örnek: £10 indirim
        if (appliedCoupon.discountAmount > 0) {
            discountAmount = parseFloat(appliedCoupon.discountAmount);
        }
        // Yüzdelik indirim kontrolü
        // Örnek: %25 indirim
        else if (appliedCoupon.discountPercentage > 0) {
            // Yüzdelik indirimi hesapla
            // Örnek: 100£ ürün, %25 indirim = 25£ indirim
            discountAmount = (currentPrice * parseFloat(appliedCoupon.discountPercentage)) / 100;
        }

        // İndirim tutarı ürün fiyatına eşit veya daha büyükse
        if (discountAmount >= currentPrice) {
            // Fiyatı sıfıra çek
            finalPrice = 0;
            // Ödeme bilgileri bölümünü gizle
            payerInfoSection.hide();
            // Buton metnini "REGISTER NOW" olarak değiştir
            submitButton.text('REGISTER NOW');
            // Ödeyici adı ve ülke alanlarını zorunlu olmaktan çıkar
            $('#payer-name, #payer-country').prop('required', false);
        }
        // İndirim tutarı ürün fiyatından küçükse
        else {
            // İndirimli fiyatı hesapla
            finalPrice = currentPrice - discountAmount;
            // Ödeme bilgileri bölümünü göster
            payerInfoSection.show();
            // Buton metnini "PAY NOW" olarak değiştir
            submitButton.text('PAY NOW');
            // Ödeyici adı ve ülke alanlarını zorunlu yap
            $('#payer-name, #payer-country').prop('required', true);
        }
    }

    // Son fiyatı ekranda göster (2 ondalık basamakla)
    priceDisplay.text(finalPrice.toFixed(2));
}
    function resetCoupon() {
        appliedCoupon = null;
        couponInput.val('').prop('disabled', false);
        applyButton.show();
        changeButton.hide();
        couponMessage.empty();
        updatePrice();

        payerInfoSection.show();
        submitButton.text('PAY NOW');
        $('#payer-name, #payer-country').prop('required', true);
    }

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

form.on('submit', async function(e) {
    e.preventDefault();

    if (!selectedCompetition) {
        showNotification('Please select a competition', 'error');
        return false;
    }

    const youtubeLink = $('#youtube-link').val().trim();
    if (!youtubeLink) {
        showNotification('Please enter your YouTube link', 'error');
        return false;
    }

    if (!isValidYouTubeLink(youtubeLink)) {
        showNotification('Invalid YouTube link format. Please enter a valid YouTube video link', 'error');
        return false;
    }

    // Supervisor/Agency: if email is entered, it must be valid (supervisor_id set). Otherwise user must leave field blank.
    const supervisorEmail = $('#supervisor_agency_email').val().trim();
    const supervisorId = $('#supervisor_id').val().trim();
    if (supervisorEmail && !supervisorId) {
        showNotification('No WeVoi account found with this email. Please enter a valid Supervisor/Agency email if you know one, or leave the field blank. Registration cannot continue with an email that is not in the system.', 'error');
        $('#supervisor-agency-name').addClass('error').text('No WeVoi account found with this email. Enter a valid Supervisor/Agency email or leave blank—registration cannot proceed otherwise.');
        return false;
    }

    // Calculate final price to determine if it's free
    let finalPrice = currentPrice;
    if (appliedCoupon) {
        if (appliedCoupon.discountAmount > 0) {
            finalPrice = Math.max(0, currentPrice - appliedCoupon.discountAmount);
        } else if (appliedCoupon.discountPercentage > 0) {
            const discountAmount = (currentPrice * appliedCoupon.discountPercentage) / 100;
            finalPrice = Math.max(0, currentPrice - discountAmount);
        }
    }

    // If final price is 0, process as free registration
    if (finalPrice === 0) {
        processFreeRegistration();
    } else {
        await processPayment();
    }
});

function isValidYouTubeLink(url) {
    // Handle empty or non-string input
    if (!url || typeof url !== 'string') {
        return false;
    }

    // Clean the URL
    url = url.trim();

    // Extract video ID from URL for various formats
    const patterns = {
        standard: /^(?:https?:\/\/)?(?:www\.|m\.)?youtube\.com\/watch\?(?:.*&)?v=([a-zA-Z0-9_-]{11})(?:\S*)?$/,
        shortened: /^(?:https?:\/\/)?(?:www\.|m\.)?youtu\.be\/([a-zA-Z0-9_-]{11})(?:\S*)?$/,
        shorts: /^(?:https?:\/\/)?(?:www\.|m\.)?youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})(?:\S*)?$/,
        embed: /^(?:https?:\/\/)?(?:www\.|m\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]{11})(?:\S*)?$/
    };

    // Test each pattern and extract video ID
    let videoId = null;
    for (const [format, pattern] of Object.entries(patterns)) {
        const match = url.match(pattern);
        if (match && match[1]) {
            videoId = match[1];
            break;
        }
    }

    // If no valid video ID found, show error message
    if (!videoId) {
        let errorMessage = "Please enter a valid YouTube link. Example formats:";
        errorMessage += "\n- youtube.com/watch?v=xxxxx";
        errorMessage += "\n- youtu.be/xxxxx";
        errorMessage += "\n- youtube.com/shorts/xxxxx";
        showNotification(errorMessage, 'error');
        return false;
    }

    return true;
}

// Handle YouTube link input with debounce
let validateTimer;
$('#youtube-link').on('input', function() {
    const input = $(this);
    const url = input.val().trim();

    // Clear previous timer
    clearTimeout(validateTimer);

    // Set new timer to validate after typing stops
    validateTimer = setTimeout(() => {
        if (url && !isValidYouTubeLink(url)) {
            input.addClass('invalid');
        } else {
            input.removeClass('invalid');
        }
    }, 500); // Wait 500ms after typing stops
});

// Handle YouTube link input
$('#youtube-link').on('input', function() {
    const input = $(this);
    const url = input.val().trim();

    if (url && !isValidYouTubeLink(url)) {
        input.addClass('invalid');
    } else {
        input.removeClass('invalid');
    }
});

// Show notification function (if not already defined)
function showNotification(message, type) {
    const notification = $('#wevoi-notification');
    notification
        .removeClass('success error')
        .addClass(type)
        .html(message.replace(/\n/g, '<br>'))
        .fadeIn();

    setTimeout(() => {
        notification.fadeOut();
    }, 7000); // Show for 7 seconds to give users time to read
}

// form-handler.js - processFreeRegistration fonksiyonu

function processFreeRegistration() {
    const formData = {
        action: 'wevoi_process_registration',
        nonce: wevoiRegister.nonce,
        competition_code: selectedCompetition,
        youtube_link: $('#youtube-link').val().trim(),
        coupon_code: appliedCoupon ? appliedCoupon.code : ''
    };
    const sid = $('#supervisor_id').val();
    if (sid && sid.trim()) formData.supervisor_id = sid.trim();

    submitButton.prop('disabled', true).text('Processing...');

    $.ajax({
        url: wevoiRegister.ajaxUrl,
        type: 'POST',
        data: formData,
        dataType: 'json',  // Explicitly set dataType to json
        success: function(response) {
            if (response.success) {
                showNotification('Registration successful!', 'success');
                setTimeout(() => {
                    window.location.href = 'https://www.wevoi.org/submissions/';
                }, 2000);
            } else {
                showNotification(response.data || 'Registration failed', 'error');
                submitButton.prop('disabled', false).text('REGISTER NOW');
            }
        },
        error: function(xhr, status, error) {
            console.error('Registration error:', error);
            showNotification('Error processing registration', 'error');
            submitButton.prop('disabled', false).text('REGISTER NOW');
        }
    });
}


 // form-handler.js - processPayment fonksiyonu


async function processPayment() {
    try {
        const formData = {
            action: 'wevoi_process_registration',
            nonce: wevoiRegister.nonce,
            competition_code: selectedCompetition,
            youtube_link: $('#youtube-link').val().trim(),
            payer_name: $('#payer-name').val().trim(),
            payer_countryid: $('#payer-country').val(),
            coupon_code: appliedCoupon ? appliedCoupon.code : ''
        };
        const sid = $('#supervisor_id').val();
        if (sid && sid.trim()) formData.supervisor_id = sid.trim();

        // Log form data
        console.log('Sending form data:', formData);

        submitButton.prop('disabled', true).text('Processing...');

        const response = await $.ajax({
            url: wevoiRegister.ajaxUrl,
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

        // Log server response
        console.log('Server response:', response);

        if (!response.success) {
            console.error('Server returned error:', response);
            throw new Error(response.data || 'Registration failed');
        }

        // Log Stripe data before confirmation
        console.log('Stripe payment data:', {
            clientSecret: response.data.client_secret,
            customerDetails: {
                name: formData.payer_name,
                country: response.data.country_code
            }
        });

        const result = await stripe.confirmCardPayment(response.data.client_secret, {
            payment_method: {
                card: card,
                billing_details: {
                    name: formData.payer_name,
                    address: {
                        country: response.data.country_code
                    }
                }
            }
        });

        // Log Stripe result
        console.log('Stripe confirmation result:', result);

        if (result.error) {
            console.error('Stripe error:', result.error);
            throw result.error;
        }

        if (result.paymentIntent.status === 'succeeded') {
            // Ödeme başarılı olduktan sonra buton durumunu güncelle
            submitButton.text('Payment Successful!');

            const updateResponse = await $.ajax({
                url: wevoiRegister.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wevoi_update_payment_status',
                    nonce: wevoiRegister.nonce,
                    payment_intent_id: result.paymentIntent.id,
                    customer_id: response.data.customer_id,
                    registration_id: response.data.registration_id
                },
                dataType: 'json'
            });

            // Log update response
            console.log('Payment status update response:', updateResponse);

            showNotification('Payment successful! Redirecting...', 'success');

            // Redirect işlemi için zamanlayıcı
            setTimeout(() => {
                window.location.href = 'https://www.wevoi.org/submissions/';
            }, 2000);
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






    function showError(message) {
        cardErrors.text(message);
    }

    function showNotification(message, type) {
        const notification = $('#wevoi-notification');
        notification
            .removeClass('success error')
            .addClass(type)
            .text(message)
            .fadeIn();

        setTimeout(() => {
            notification.fadeOut();
        }, 5000);
    }

    function showCouponSuccess(message) {
        couponMessage
            .removeClass('error')
            .addClass('success')
            .html(`<span style="color: green;">${message}</span>`);
    }

    function showCouponError(message) {
        couponMessage
            .removeClass('success')
            .addClass('error')
            .html(`<span style="color: red;">${message}</span>`);
    }

    document.querySelectorAll('.wevoi-competition-item').forEach(item => {
        item.addEventListener('click', function() {
            document.querySelectorAll('.wevoi-competition-item').forEach(i =>
                i.classList.remove('selected'));
            this.classList.add('selected');
        });
    });

    function updateVideoTitleInstructions(competitionName, topic) {
        const $box = $('#wevoi-video-title-box');
        if (!$box.length) {
            return;
        }

        const studentName = $.trim($box.attr('data-student-name') || '');
        const studentCountry = $.trim($box.attr('data-student-country') || '');
        const parts = [studentName, studentCountry, competitionName, topic].filter(function(part) {
            return part && String(part).trim() !== '';
        });

        if (parts.length) {
            $('#wevoi-video-title-text').text(parts.join(' - '));
        } else {
            $('#wevoi-video-title-text').text('Select a competition to see your video title.');
        }

        if (competitionName) {
            $('.wevoi-instruction-competition-name').text(competitionName);
        }
    }

    // Load topics for selected competition
    function loadTopics(competitionCode) {
        if (!competitionCode) {
            $('#topics-section').hide();
            return;
        }

        const competitionName = $('input[name="competition"]:checked').attr('data-name') || '';

        $.ajax({
            url: wevoiRegister.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wevoi_get_topics',
                competition_code: competitionCode,
                nonce: wevoiRegister.nonce
            },
            success: function(response) {
                let topicText = '';
                if (response.success && response.data.topics && response.data.topics.length > 0) {
                    let topicsHtml = '<div class="wevoi-topics-list">';
                    topicsHtml += '<div class="wevoi-topic-age">';
                    topicsHtml += '<strong>Age Category:</strong> <span>' + response.data.age_category + '</span>';
                    topicsHtml += '</div>';

                    response.data.topics.forEach(function(topic) {
                        topicsHtml += '<div class="wevoi-topic-item">';
                        topicsHtml += '<strong>Topic:</strong> ' + topic.topic;
                        topicsHtml += '</div>';
                    });
                    topicsHtml += '</div>';

                    topicText = response.data.topics[0].topic || '';
                    $('#topics-content').html(topicsHtml);
                    $('#topics-section').show();
                } else {
                    $('#topics-section').hide();
                }
                updateVideoTitleInstructions(competitionName, topicText);
            },
            error: function() {
                $('#topics-section').hide();
                updateVideoTitleInstructions(competitionName, '');
            }
        });
    }

})(jQuery);
