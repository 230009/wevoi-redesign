jQuery(document).ready(function ($) {
    // Tarayıcı bilgisini konsola yazdıralım
    console.log('Browser: ' + navigator.userAgent);

    // jQuery ve AJAX nesnelerinin varlığını kontrol edelim
    console.log('jQuery version: ' + $.fn.jquery);
    console.log('AJAX URL exists: ' + (typeof customRegisterAjax !== 'undefined' && customRegisterAjax.ajaxurl ? 'Yes' : 'No'));

    // Initial checks

    // Initial role check
    var initialRole = $('input[name="role"]:checked').val();
    if (initialRole === 'student') {
        $('#student-fields').show();
        $('#birth_date, #gender, #supervisorfullname, #supervisoremail, #supervisorwhatsapp').prop('required', true);
    } else {
        $('#birth_date, #gender, #supervisorfullname, #supervisoremail, #supervisorwhatsapp').prop('required', false);
    }
    if (initialRole === 'parent' || initialRole === 'agency') {
        $('#school-field-container').hide();
        $('#new-school-container').hide();
        $('#schoolid').prop('required', false);
    } else if (initialRole) {
        $('#school-field-container').show();
    }
    if (initialRole === 'agency') {
        $('#agency-fields').show();
        $('#company_name').prop('required', true);
    } else {
        $('#agency-fields').hide();
        $('#company_name').prop('required', false);
    }

    $('#registerform').prepend('<input type="hidden" name="timestamp" value="' + Math.floor(Date.now() / 1000) + '">');

    // Role change handler - student alanlarını göstermek için
$(document).on('change', 'input[name="role"]', function() {
    console.log('Role changed to: ' + $(this).val());
    var selectedRole = $(this).val();

    if (selectedRole === 'student') {
        $('#student-fields').slideDown();
        $('#school-field-container').slideDown();
        $('#birth_date, #gender, #supervisorfullname, #supervisoremail, #supervisorwhatsapp').prop('required', true);
        $('#schoolid').prop('required', true);
    } else {
        $('#student-fields').slideUp();
        $('#birth_date, #gender, #supervisorfullname, #supervisoremail, #supervisorwhatsapp').prop('required', false);

        // Clear student fields when role is not student
        $('#birth_date').val('');
        $('#gender').val('');
        $('#supervisorfullname').val('');
        $('#supervisoremail').val('');
        $('#supervisorwhatsapp').val('');
    }

    // Agency: show Company Name, hide School
    if (selectedRole === 'agency') {
        $('#agency-fields').slideDown();
        $('#company_name').prop('required', true);
        $('#school-field-container').hide();
        $('#schoolid').val('').prop('required', false);
        $('#new-school-container').hide();
        $('#new_school').val('');
    } else {
        $('#agency-fields').slideUp();
        $('#company_name').prop('required', false).val('');
    }

    // Hide school field for parent role
    if (selectedRole === 'parent') {
        $('#school-field-container').hide();
        $('#schoolid').val('').prop('required', false);
        $('#new-school-container').hide();
        $('#new_school').val('');
    } else if (selectedRole && selectedRole !== 'agency') {
        $('#school-field-container').show();
        $('#schoolid').prop('required', true);
    }
});

    // Ülke değişikliği için olay dinleyicisini geliştirelim ve daha fazla hata ayıklama bilgisi ekleyelim
    $(document).on('change', '#countryid', function() {
        let countryId = $(this).val();
        let schoolSelect = $('#schoolid');

        console.log('Country changed. ID: ' + countryId);

        // Okul seçim alanını temizleyelim
        schoolSelect.empty().append('<option value="">Select a school</option>');

        if (!countryId) {
            console.log('No country selected');
            schoolSelect.append('<option value="other" class="italic-option">My school is not in the list</option>');
            return;
        }

        // AJAX nesnesinin varlığını kontrol edelim
        if (typeof customRegisterAjax === 'undefined' || !customRegisterAjax.ajaxurl) {
            console.error('customRegisterAjax object is not properly defined');
            return;
        }

        console.log('Sending AJAX request to: ' + customRegisterAjax.ajaxurl);
        console.log('With nonce: ' + customRegisterAjax.nonce);

        // AJAX isteği yollayalım
        $.ajax({
            url: customRegisterAjax.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'get_schools_by_country',
                countryid: countryId,
                nonce: customRegisterAjax.nonce
            },
            beforeSend: function() {
                console.log('AJAX request started');
                // İstek başladığında bir yükleniyor göstergesi ekleyelim
                schoolSelect.prop('disabled', true);
                schoolSelect.after('<span class="loading-schools">Loading schools...</span>');
            },
            success: function(response) {
                console.log('AJAX success. Response:', response);
                $('.loading-schools').remove();
                schoolSelect.prop('disabled', false);

                // "Other" seçeneğini ekleyelim
                schoolSelect.append('<option value="other" class="italic-option">-- My school is not in the list --</option>');

                // Okul verilerini işleyelim
                if (response && response.success && Array.isArray(response.data)) {
                    console.log('Found ' + response.data.length + ' schools');
                    response.data.forEach(function(school) {
                        if (school.schoolid && school.schoolname) {
                            schoolSelect.append($('<option>', {
                                value: school.schoolid,
                                text: school.schoolname
                            }));
                        }
                    });
                } else {
                    console.log('No schools found or invalid response format');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response Text:', xhr.responseText);
                $('.loading-schools').remove();
                schoolSelect.prop('disabled', false);

                // Hata durumunda varsayılan seçenekleri ekleyelim
                schoolSelect.empty()
                    .append('<option value="">Select a school</option>')
                    .append('<option value="other" class="italic-option">-- My school is not in the list --</option>');
            },
            complete: function() {
                console.log('AJAX request completed');
            }
        });
    });



    $('#schoolid').on('click focus', function(e) {
        let countryId = $('#countryid').val();
        if (!countryId) {
            e.preventDefault();
            alert('Please select a country first before choosing a school.');
            $('#countryid').focus();
            return false;
        }
    });

    $('#schoolid').change(function () {
        if ($(this).val() === 'other') {
            $('#new-school-container').show();
        } else {
            $('#new-school-container').hide();
            $('#new_school').val('');
        }
    });
});
