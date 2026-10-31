jQuery(document).ready(function($) {
    'use strict';

    // Animate progress bars on load
    function animateProgressBars() {
        $('.progress-bar').each(function() {
            var $bar = $(this);
            var width = $bar.css('width');
            $bar.css('width', '0');
            setTimeout(function() {
                $bar.css('width', width);
            }, 100);
        });
    }

    // Animate stat cards on load
    function animateStatCards() {
        $('.stat-card').each(function(index) {
            var $card = $(this);
            $card.css('opacity', '0');
            $card.css('transform', 'translateY(20px)');
            setTimeout(function() {
                $card.css('transition', 'all 0.5s ease');
                $card.css('opacity', '1');
                $card.css('transform', 'translateY(0)');
            }, index * 100);
        });
    }

    var bankModalSubmitLabel = 'Submit Bank Details';
    var bankModalUpdateLabel = 'Update Bank Details';
    var bankModalSubmitTitle = 'Submit Bank Account Details';
    var bankModalEditTitle = 'Edit Bank details';

    function setBankModalMode(isEdit) {
        $('#bank-modal-title').text(isEdit ? bankModalEditTitle : bankModalSubmitTitle);
        $('#bank-modal-submit-btn').prop('disabled', false).text(isEdit ? bankModalUpdateLabel : bankModalSubmitLabel);
        $('#bank-account-form').data('is-edit', isEdit ? 1 : 0);
    }

    function fillBankAccountForm(account) {
        if (!account) {
            return;
        }
        $('#modal_account_holder_name').val(account.account_holder_name || '');
        $('#modal_bank_name').val(account.bank_name || '');
        $('#modal_account_number').val(account.account_number || '');
        $('#modal_sort_code').val(account.sort_code || '');
        $('#modal_iban').val(account.iban || '');
        $('#modal_swift_code').val(account.swift_code || '');
        $('#modal_countryid').val(account.countryid || '');
        $('#modal_city').val(account.city || '');
        if (account.currencyid) {
            $('#modal_currencyid').val(account.currencyid);
        }
        $('#modal_general_info').val(account.general_info || '');
    }

    // Bank Account Modal Functions
    function openBankAccountModal(registrationId, competitionName, prizeAmount, isEdit) {
        $('#modal_registration_id').val(registrationId);
        $('#modal-competition-name').html('<strong>Competition:</strong> ' + competitionName);
        var formattedAmount = parseFloat(prizeAmount).toLocaleString('en-GB', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        $('#modal-prize-amount').html('<strong>Prize Amount:</strong> £' + formattedAmount);
        $('#modal-message').hide().removeClass('success error').html('');
        setBankModalMode(isEdit);

        if (isEdit) {
            $('#bank-modal-submit-btn').prop('disabled', true).text('Loading...');
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wevoi_get_student_bank_account',
                    nonce: wevoiStudentNonce,
                    registration_id: registrationId
                },
                success: function(response) {
                    if (response.success) {
                        fillBankAccountForm(response.data);
                        setBankModalMode(true);
                    } else {
                        $('#modal-message').addClass('error').html(response.data || 'Unable to load bank details').fadeIn();
                        setBankModalMode(true);
                    }
                },
                error: function() {
                    $('#modal-message').addClass('error').html('Unable to load bank details. Please try again.').fadeIn();
                    setBankModalMode(true);
                }
            });
        } else {
            $('#bank-account-form')[0].reset();
            $('#modal_registration_id').val(registrationId);
            setBankModalMode(false);
        }

        $('#bank-account-modal').fadeIn(300);
        $('body').css('overflow', 'hidden');
    }

    function closeBankAccountModal() {
        $('#bank-account-modal').fadeOut(300);
        $('body').css('overflow', 'auto');
        $('#bank-account-form')[0].reset();
        $('#modal-message').hide().removeClass('success error').html('');
        setBankModalMode(false);
    }

    // Open modal when button is clicked
    $(document).on('click', '.btn-submit-bank-details', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var registrationId = $btn.data('registration-id');
        var competitionName = $btn.data('competition-name');
        var prizeAmount = $btn.data('prize-amount') || $btn.closest('.prize-section').find('.result-text').text().replace('£', '').trim();
        var isEdit = $btn.data('mode') === 'edit';
        openBankAccountModal(registrationId, competitionName, prizeAmount, isEdit);
    });

    // Close modal
    $(document).on('click', '.modal-close, .modal-overlay', function(e) {
        e.preventDefault();
        closeBankAccountModal();
    });

    // Submit bank account form
    $('#bank-account-form').on('submit', function(e) {
        e.preventDefault();

        var formData = $(this).serialize();
        formData += '&action=wevoi_submit_bank_account&nonce=' + wevoiStudentNonce;
        var isEdit = $(this).data('is-edit') === 1;
        var busyLabel = isEdit ? 'Updating...' : 'Submitting...';
        var idleLabel = isEdit ? bankModalUpdateLabel : bankModalSubmitLabel;

        $('#modal-message').hide().removeClass('success error').html('');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            beforeSend: function() {
                $('#bank-modal-submit-btn').prop('disabled', true).text(busyLabel);
            },
            success: function(response) {
                if (response.success) {
                    $('#modal-message').addClass('success').html(response.data.message).fadeIn();
                    setTimeout(function() {
                        closeBankAccountModal();
                        location.reload();
                    }, 2000);
                } else {
                    $('#modal-message').addClass('error').html(response.data || 'An error occurred').fadeIn();
                    $('#bank-modal-submit-btn').prop('disabled', false).text(idleLabel);
                }
            },
            error: function() {
                $('#modal-message').addClass('error').html('An error occurred. Please try again.').fadeIn();
                $('#bank-modal-submit-btn').prop('disabled', false).text(idleLabel);
            }
        });
    });

    // Run animations on page load
    animateProgressBars();
    animateStatCards();
});
