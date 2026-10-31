jQuery(document).ready(function($) {
    // Application status güncelleme
    $('.wevoi-application-status-select').each(function() {
        $(this).data('previous-value', $(this).val());
    });

    $('.wevoi-application-status-select').on('focus', function() {
        $(this).data('previous-value', $(this).val());
    });

    $('.wevoi-application-status-select').on('change', function() {
        const $select = $(this);
        const registrationId = $select.data('registration-id');
        const newStatus = $select.val();

        $select.prop('disabled', true);
        $select.closest('.wevoi-status-container').find('.wevoi-status-update-timer').remove();

        const startTime = Date.now();
        let requestFinishedOk = false;

        function elapsedSeconds() {
            return Math.floor((Date.now() - startTime) / 1000);
        }

        const $timer = $('<span class="wevoi-status-update-timer status-updating">0s Updating...</span>').insertAfter($select);
        const tickMs = 250;
        const timerId = setInterval(function() {
            $timer.text(elapsedSeconds() + 's Updating...');
        }, tickMs);

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
                try {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;

                    if (data.success) {
                        requestFinishedOk = true;

                        const payload = data.data || {};
                        if (payload.status) {
                            $select.val(payload.status);
                        }
                        $select.data('previous-value', $select.val());

                        const noticeText = payload.message || 'Status updated successfully';
                        const $notification = jQuery(document.createElement('div'))
                            .addClass('wevoi-notification success')
                            .text(noticeText)
                            .hide()
                            .insertAfter($select)
                            .fadeIn();

                        // Check competition status - only allow video updates for ON AIR competitions
                        const $container = $(`.wevoi-video-update-container[data-registration-id="${registrationId}"]`);
                        if ($container.length) {
                            const competitionStatus = $container.data('competition-status');
                            const $input = $container.find('.wevoi-video-link-input');
                            const $button = $container.find('.wevoi-update-video-button');
                            const $existingMessage = $container.find('.video-update-disabled-message');

                            if (competitionStatus === 'ON AIR') {
                                // Enable video update if competition is ON AIR
                                $input.prop('disabled', false);
                                $button.prop('disabled', false);
                                $existingMessage.remove();
                            } else {
                                // Disable video update if competition is not ON AIR
                                $input.prop('disabled', true);
                                $button.prop('disabled', true);
                                if ($existingMessage.length === 0) {
                                    $container.append('<div class="video-update-disabled-message">Video updates are only allowed for competitions that are currently ON AIR</div>');
                                }
                            }
                        }

                        setTimeout(() => {
                            $notification.fadeOut(() => $notification.remove());
                        }, 3000);
                    } else {
                        requestFinishedOk = false;
                        console.error('Update failed:', data.data);
                        alert('Failed to update status: ' + data.data);
                        $select.val($select.data('previous-value'));
                    }
                } catch (error) {
                    requestFinishedOk = false;
                    console.error('Parse error:', error);
                    alert('Error updating status');
                    $select.val($select.data('previous-value'));
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                requestFinishedOk = false;
                console.error('AJAX error:', {jqXHR, textStatus, errorThrown});
                alert('Error updating status');
                $select.val($select.data('previous-value'));
            },
            complete: function() {
                clearInterval(timerId);
                const sec = elapsedSeconds();
                $select.prop('disabled', false);
                $timer.removeClass('status-updating');
                if (requestFinishedOk) {
                    $timer.text(sec + 's Updated').addClass('status-updating-done');
                } else {
                    $timer.text(sec + 's Failed').addClass('status-updating-error');
                }
            }
        });
    });

// Sayfa yüklendiğinde yarışma durumuna göre video güncelleme özelliğini ayarla
$('.wevoi-video-update-container').each(function() {
    const $container = $(this);
    const competitionStatus = $container.data('competition-status');
    const $input = $container.find('.wevoi-video-link-input');
    const $button = $container.find('.wevoi-update-video-button');

    // Sadece ON AIR yarışmalarda video güncelleme izni ver
    if (competitionStatus !== 'ON AIR') {
        $input.prop('disabled', true);
        $button.prop('disabled', true);

        // Mesajı sadece bir kez ekle
        if ($container.find('.video-update-disabled-message').length === 0) {
            $container.append('<div class="video-update-disabled-message">Video updates are only allowed for competitions that are currently ON AIR</div>');
        }
    }
});

// Status değişikliğinde yarışma durumuna göre video güncelleme özelliğini ayarla
// (Bu kod zaten yukarıda mevcut change handler içinde güncellendi)




// Video link güncelleme
$('.wevoi-update-video-button').on('click', function() {
    const $button = $(this);
    const registrationId = $button.data('registration-id');
    const $input = $(`.wevoi-video-link-input[data-registration-id="${registrationId}"]`);
    const newVideoLink = $input.val().trim();
    const currentVideoLink = $input.attr('value').trim(); // Mevcut linki alalım

    if (!newVideoLink) {
        alert('Please enter a valid YouTube link');
        return;
    }

       // Yeni link mevcut linkle aynıysa
    if (newVideoLink === currentVideoLink) {
        alert('The video link is the same as the current one');
        return;
    }

        const youtubePattern = /^(https?:\/\/)?(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/;
        if (!youtubePattern.test(newVideoLink)) {
            alert('Please enter a valid YouTube video link');
            return;
        }

        $button.prop('disabled', true).text('Updating...');

        $.ajax({
            url: wevoiRegister.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wevoi_update_video_link',
                nonce: wevoiRegister.nonce,
                registration_id: registrationId,
                video_link: newVideoLink
            },
            success: function(response) {
                if (response.success) {
                    const $notification = $('<div class="wevoi-notification success">Video link updated successfully</div>')
                        .hide()
                        .insertAfter($button)
                        .fadeIn();

                    setTimeout(() => {
                        $notification.fadeOut(() => $notification.remove());
                        location.reload();
                    }, 2000);
                } else {
                    alert('Failed to update video link: ' + response.data);
                }
            },
            error: function() {
                alert('Error updating video link');
            },
            complete: function() {
                $button.prop('disabled', false).text('Update Video Link');
            }
        });
    });
});
