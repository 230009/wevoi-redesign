(function($) {
    'use strict';

    const stripe = Stripe(wevoiVoucher.stripeKey);
    let elements;
    let card;
    let currentPrice = 0;
    let selectedCompetition = null;
    let selectedType = 'VOUCHER';
    let quantity = 1;
    let amount = 0;

    // Form elements
    const form = $('#wevoi-voucher-form');
    const submitButton = $('#submit-button');
    const priceDisplay = $('#wevoi-price');
    const voucherCompetitionSelect = $('#voucher-competition');
    const amountCompetitionSelect = $('#amount-competition');
    const voucherQuantitySelect = $('#voucher-quantity');
    const amountInput = $('#voucher-amount');
    const cardErrors = $('#card-errors');

    // Initialize on document ready
    $(document).ready(async function() {
        try {
            await initStripeElements();
            $('#voucher-type-' + selectedType).closest('.wevoi-option-tab').addClass('selected');
            setupEventListeners();
            updateFormState(); // Initial form state setup
        } catch (error) {
            console.error('Initialization error:', error);
            showNotification('Failed to initialize payment form. Please refresh the page.', 'error');
        }
    });

    // Initialization of Stripe Elements
    const initStripeElements = async () => {
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
    };

    // Setup event listeners
    const setupEventListeners = () => {
        // Voucher type radio change
        $('input[name="voucher_type"]').on('change', function() {
        selectedType = $(this).val();

            // Tüm sekmelerdeki "selected" sınıfını kaldır
    $('.wevoi-option-tab').removeClass('selected');

    // Seçilen sekmeye "selected" sınıfını ekle
    $(this).closest('.wevoi-option-tab').addClass('selected');

            updateFormState();
        });


$('#voucher-type-voucher').closest('.wevoi-option-tab').addClass('selected');











// Competition selection change for voucher
voucherCompetitionSelect.on('change', function() {
    if (selectedType === 'VOUCHER') { // Only update if this type is active
        selectedCompetition = $(this).val();

        if (selectedCompetition) {
            currentPrice = parseFloat($(this).find('option:selected').data('price'));
        } else {
            currentPrice = 0;
        }

        updatePrice();
    }
});

// Competition selection change for amount
amountCompetitionSelect.on('change', function() {
    if (selectedType === 'AMOUNT') { // Only update if this type is active
        selectedCompetition = $(this).val();
        updatePrice();
    }
});

        // Quantity change
        voucherQuantitySelect.on('change', function() {
            quantity = parseInt($(this).val());
            updatePrice();
        });

        // Amount change
        amountInput.on('input', function() {
            amount = parseFloat($(this).val()) || 0;
            updatePrice();
        });

        // Form submission
        form.on('submit', handleFormSubmit);

        // İlk yükleme için form durumunu belirle
        updateFormState();
    };

// In voucher-form-handler.js, the updateFormState function needs to be modified:

const updateFormState = () => {
    if (selectedType === 'VOUCHER') {
        // Enable voucher fields
        voucherCompetitionSelect
            .prop('disabled', false)
            .prop('required', true)
            .closest('.wevoi-form-group')
            .removeClass('disabled');
        voucherQuantitySelect
            .prop('disabled', false)
            .closest('.wevoi-form-group')
            .removeClass('disabled');

        // Disable and clear amount fields
        amountCompetitionSelect
            .prop('disabled', true)
            .prop('required', false)
            .val('') // Clear selection
            .closest('.wevoi-form-group')
            .addClass('disabled');
        amountInput
            .prop('disabled', true)
            .prop('required', false)
            .val('') // Clear input
            .closest('.wevoi-form-group')
            .addClass('disabled');
        $('#payment-purpose')
            .prop('disabled', true)
            .prop('required', false)
            .val('') // Clear input
            .closest('.wevoi-form-group')
            .addClass('disabled');

        // Reset competition selection for form submission
        selectedCompetition = voucherCompetitionSelect.val();
    } else {
        // Disable and clear voucher fields
        voucherCompetitionSelect
            .prop('disabled', true)
            .prop('required', false)
            .val('') // Clear selection
            .closest('.wevoi-form-group')
            .addClass('disabled');
        voucherQuantitySelect
            .prop('disabled', true)
            .val('1') // Reset to default
            .closest('.wevoi-form-group')
            .addClass('disabled');

        // Enable amount fields
        amountCompetitionSelect
            .prop('disabled', false)
            .prop('required', true)
            .closest('.wevoi-form-group')
            .removeClass('disabled');
        amountInput
            .prop('disabled', false)
            .prop('required', true)
            .closest('.wevoi-form-group')
            .removeClass('disabled');
        $('#payment-purpose')
            .prop('disabled', false)
            .prop('required', true)
            .closest('.wevoi-form-group')
            .removeClass('disabled');

        // Reset competition selection for form submission
        selectedCompetition = amountCompetitionSelect.val();
    }

    // Reset price calculation
    updatePrice();
}

    // Update price calculation
    const updatePrice = () => {
        let finalPrice = 0;

        if (selectedType === 'VOUCHER' && selectedCompetition && quantity) {
            finalPrice = currentPrice * quantity;
        } else if (selectedType === 'AMOUNT' && selectedCompetition && amount) {
            finalPrice = amount;
        }

        priceDisplay.text(finalPrice.toFixed(2));
    };

    // Handle form submission
    const handleFormSubmit = async (e) => {
        e.preventDefault();

        try {
            // Basic validation
            if (!selectedCompetition) {
                throw new Error('Please select a competition');
            }

            if (selectedType === 'VOUCHER' && (!quantity || quantity <= 0)) {
                throw new Error('Please select a valid quantity');
            }

            if (selectedType === 'AMOUNT' && (!amount || amount <= 0)) {
                throw new Error('Please enter a valid amount');
            }

            if (selectedType === 'AMOUNT' && !$('#payment-purpose').val().trim()) {
                throw new Error('Please enter a payment purpose');
            }

            if (!$('#payer-email').val()) {
                throw new Error('Please enter your email address');
            }

            if (!$('#payer-name').val()) {
                throw new Error('Please enter your full name');
            }

            if (!$('#payer-country').val()) {
                throw new Error('Please select your country');
            }

            // Check terms acceptance
            if (!$('#consent-checkbox').is(':checked')) {
                throw new Error('Please accept the terms and conditions');
            }

            submitButton.prop('disabled', true).addClass('loading');

            // Calculate price
            let price = 0;
            if (selectedType === 'VOUCHER') {
                price = currentPrice * quantity;
            } else {
                price = amount;
            }

            // Process payment
            await processPayment(price);

        } catch (error) {
            console.error('Form submission error:', error);
            showNotification(error.message, 'error');
            submitButton.prop('disabled', false).removeClass('loading');
        }
    };

    const processPayment = async (price) => {
        try {
const formData = {
    action: 'wevoi_process_voucher',
    nonce: wevoiVoucher.nonce,
    competition_code: selectedType === 'VOUCHER' ? $('#voucher-competition').val() : $('#amount-competition').val(),
    amount_competition_code: selectedType === 'AMOUNT' ? $('#amount-competition').val() : '',
    voucher_type: selectedType,
    quantity: selectedType === 'VOUCHER' ? quantity : null,
    amount: selectedType === 'AMOUNT' ? price : null,
    payment_purpose: selectedType === 'AMOUNT' ? $('#payment-purpose').val().trim() : '',
    payer_email: $('#payer-email').val(),
    payer_name: $('#payer-name').val(),
    payer_countryid: $('#payer-country').val()
};

            console.log('Sending form data:', formData);

            const response = await $.ajax({
                url: wevoiVoucher.ajaxUrl,
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
                throw new Error(response.data || 'Initial payment processing failed');
            }

            const result = await stripe.confirmCardPayment(response.data.client_secret, {
                payment_method: {
                    card: card,
                    billing_details: {
                        name: formData.payer_name,
                        email: formData.payer_email
                    }
                }
            });

            console.log('Stripe confirmation result:', result);

            if (result.error) {
                console.error('Stripe payment error:', result.error);
                throw new Error(result.error.message || 'Stripe payment failed');
            }

            // Detaylı Stripe Intent durumu kontrolü
            if (!result.paymentIntent) {
                throw new Error('No payment intent found in the result');
            }

            const paymentIntentStatus = result.paymentIntent.status;
            console.log('Payment Intent Status:', paymentIntentStatus);

            // Başarılı durumlar için daha geniş kontrol
            const successStatuses = ['succeeded', 'requires_capture', 'processing'];
            if (successStatuses.includes(paymentIntentStatus)) {
                // Ödeme başarılı olduktan sonra buton durumunu güncelle
                submitButton.text('Payment Processing...');

                const updateResult = await updatePaymentStatus(
                    result.paymentIntent.id,
                    response.data.customer_id,
                    response.data.voucher_id
                );

                return updateResult;
            } else {
                throw new Error(`Unexpected payment status: ${paymentIntentStatus}`);
            }

        } catch (error) {
            console.error('Payment processing error:', error);
            showNotification(error.message || 'Payment failed', 'error');
            throw error;
        }
    };

// Update payment status after successful payment
const updatePaymentStatus = async (paymentIntentId, customerId, voucherId) => {
    try {
        submitButton.text('Finalizing payment...');

        console.log('Sending update request with:', {
            payment_intent_id: paymentIntentId,
            customer_id: customerId,
            voucher_id: voucherId
        });

        const updateResponse = await $.ajax({
            url: wevoiVoucher.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wevoi_update_voucher_payment',
                nonce: wevoiVoucher.nonce,
                payment_intent_id: paymentIntentId,
                customer_id: customerId,
                voucher_id: voucherId
            },
            dataType: 'json'
        });

        console.log('Update payment status response:', updateResponse);

        if (!updateResponse.success) {
            throw new Error(updateResponse.data?.message || 'Payment status update failed');
        }

        submitButton.text('Payment Successful!');
        showNotification('Payment successful! You will receive a confirmation email shortly.', 'success');

        // Reset form after 3 seconds and redirect to confirmation page
        setTimeout(() => {
            window.location.href = 'https://www.wevoi.org/list-vouchers/';
        }, 3000);

        return updateResponse;

    } catch (error) {
        console.error('Payment status update error:', error);
        submitButton.prop('disabled', false).removeClass('loading').text('COMPLETE PAYMENT');
        showNotification('Payment was processed but confirmation failed. Please contact support.', 'error');
    }
};

    // Show notification
    const showNotification = (message, type) => {
        const notification = $('#wevoi-notification');
        notification
            .removeClass('success error')
            .addClass(type)
            .text(message)
            .fadeIn();

        setTimeout(() => {
            notification.fadeOut();
        }, 5000);
    };

    // Show error
    const showError = (message) => {
        cardErrors.text(message);
    };
})(jQuery);
