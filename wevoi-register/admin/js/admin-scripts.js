(function($) {
    'use strict';

    $(document).ready(function() {
        // Webhook URL kopyalama butonu
        $('.copy-webhook-url').on('click', function() {
            // Webhook URL'ini al
            var webhookUrl = $('#webhook-url').text();

            // Clipboard API'yi kullan
            navigator.clipboard.writeText(webhookUrl).then(function() {
                // Başarılı kopyalama feedback'i
                var $button = $('.copy-webhook-url');
                var originalText = $button.text();
                
                $button.text('Copied!');
                $button.addClass('updated');
                
                // 2 saniye sonra butonu eski haline getir
                setTimeout(function() {
                    $button.text(originalText);
                    $button.removeClass('updated');
                }, 2000);
            }).catch(function(err) {
                // Hata durumunda
                console.error('Failed to copy webhook URL: ', err);
                alert('Failed to copy webhook URL. Please try manually selecting and copying.');
            });
        });

        // Test/Live mod değişikliğinde alanları toggle et
        $('select[name="wevoi_register_stripe_mode"]').on('change', function() {
            var mode = $(this).val();
            if (mode === 'test') {
                $('[name^="wevoi_register_test_"]').closest('tr').show();
                $('[name^="wevoi_register_live_"]').closest('tr').hide();
            } else {
                $('[name^="wevoi_register_test_"]').closest('tr').hide();
                $('[name^="wevoi_register_live_"]').closest('tr').show();
            }
        }).trigger('change'); // Sayfa yüklendiğinde de çalıştır

        // Secret key alanları için toggle password visibility
        $('.toggle-password').on('click', function() {
            var input = $(this).prev('input');
            if (input.attr('type') === 'password') {
                input.attr('type', 'text');
                $(this).text('Hide');
            } else {
                input.attr('type', 'password');
                $(this).text('Show');
            }
        });
    });

})(jQuery);