/**
 * WeVoi Summit Videos JavaScript
 */

(function($) {
    'use strict';

    let swiperInstance = null;
    let youtubeAPILoaded = false;
    let youtubePlayer = null;

    // Initialize when document is ready
    $(document).ready(function() {
        initializeCarousel();
        setupVideoModal();
        setupInPlaceVideoPlayback();
    });

    // YouTube API ready callback
    window.onYouTubeIframeAPIReady = function() {
        youtubeAPILoaded = true;
    };

    /**
     * Initialize Swiper carousel
     */
    function initializeCarousel() {
        const container = $('.wevoi-summit-videos-container');

        if (container.length === 0) {
            return;
        }

        const autoplay = container.data('autoplay') === 'true';
        const speed = parseInt(container.data('speed')) || 3000;
        const slidesPerView = parseInt(container.data('slides-per-view')) || 3;
        const spaceBetween = parseInt(container.data('space-between')) || 30;

        // Responsive slides per view
        const responsiveSlidesPerView = {
            320: 1,
            768: Math.min(2, slidesPerView),
            1024: Math.min(3, slidesPerView),
            1200: slidesPerView
        };

        swiperInstance = new Swiper('.wevoi-summit-videos-carousel', {
            slidesPerView: 1,
            spaceBetween: spaceBetween,
            loop: true,
            autoplay: autoplay ? {
                delay: speed,
                disableOnInteraction: false,
                pauseOnMouseEnter: true
            } : false,
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
                dynamicBullets: true
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev'
            },
            breakpoints: {
                320: {
                    slidesPerView: responsiveSlidesPerView[320],
                    spaceBetween: 20
                },
                768: {
                    slidesPerView: responsiveSlidesPerView[768],
                    spaceBetween: spaceBetween
                },
                1024: {
                    slidesPerView: responsiveSlidesPerView[1024],
                    spaceBetween: spaceBetween
                },
                1200: {
                    slidesPerView: responsiveSlidesPerView[1200],
                    spaceBetween: spaceBetween
                }
            },
            on: {
                init: function() {
                    // Add fade-in animation to slides
                    $('.swiper-slide').addClass('animate-in');
                },
                slideChange: function() {
                    // Pause any playing videos when slide changes
                    pauseAllVideos();
                }
            }
        });

        // Add hover pause for autoplay
        container.on('mouseenter', function() {
            if (swiperInstance && swiperInstance.autoplay) {
                swiperInstance.autoplay.pause();
            }
        }).on('mouseleave', function() {
            if (swiperInstance && swiperInstance.autoplay) {
                swiperInstance.autoplay.resume();
            }
        });
    }

    /**
     * Setup video modal functionality
     */
    function setupVideoModal() {
        const $videoModal = $('#wevoi-video-modal');
        const playerContainer = document.getElementById('wevoi-youtube-player');

        // Create modal if it doesn't exist
        if ($videoModal.length === 0) {
            $('body').append(`
                <div class="wevoi-video-modal" id="wevoi-video-modal">
                    <div class="wevoi-modal-content">
                        <span class="wevoi-modal-close">&times;</span>
                        <div class="wevoi-modal-video-container">
                            <div id="wevoi-youtube-player"></div>
                        </div>
                    </div>
                </div>
            `);
        }

        // Video button click handler
        $(document).on('click', '.wevoi-video-play-button', function(e) {
            e.preventDefault();

            const videoId = $(this).data('video-id');
            const videoUrl = $(this).data('video-url');

            if (!videoId) {
                console.error('No video ID found');
                return;
            }

            // Show modal
            $('#wevoi-video-modal').fadeIn(300);

            // Load video
            loadYouTubeVideo(videoId);

            // Track video view (optional analytics)
            trackVideoView(videoId, videoUrl);
        });

        // Close modal handlers
        $(document).on('click', '.wevoi-modal-close, .wevoi-video-modal', function(e) {
            if (e.target === this) {
                closeVideoModal();
            }
        });

        // Close modal on Escape key
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27 && $('#wevoi-video-modal').is(':visible')) {
                closeVideoModal();
            }
        });
    }

    /**
     * Setup in-place video playback for smaller screens
     */
    function setupInPlaceVideoPlayback() {
        $(document).on('click', '.wevoi-video-play-button', function() {
            const container = $(this).closest('.wevoi-video-thumbnail');
            const videoId = $(this).data('video-id');

            if (!videoId) {
                return;
            }

            // Replace thumbnail with iframe for mobile
            if (window.innerWidth <= 768) {
                const iframe = `
                    <iframe
                        src="https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0"
                        frameborder="0"
                        allow="autoplay; encrypted-media"
                        allowfullscreen
                        style="width: 100%; height: 100%;">
                    </iframe>
                `;

                container.html(iframe);
            }
        });
    }

    /**
     * Load YouTube video in modal
     */
    function loadYouTubeVideo(videoId) {
        const playerContainer = document.getElementById('wevoi-youtube-player');

        if (!playerContainer) {
            console.error('YouTube player container not found');
            return;
        }

        if (youtubeAPILoaded) {
            // Use YouTube API if available
            if (youtubePlayer) {
                youtubePlayer.loadVideoById(videoId);
            } else {
                youtubePlayer = new YT.Player('wevoi-youtube-player', {
                    videoId: videoId,
                    playerVars: {
                        autoplay: 1,
                        rel: 0,
                        modestbranding: 1,
                        fs: 1
                    },
                    events: {
                        onReady: function(event) {
                            event.target.playVideo();
                        }
                    }
                });
            }
        } else {
            // Fallback to iframe
            playerContainer.innerHTML = `
                <iframe
                    src="https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0&modestbranding=1&fs=1"
                    frameborder="0"
                    allow="autoplay; encrypted-media"
                    allowfullscreen>
                </iframe>
            `;
        }
    }

    /**
     * Close video modal
     */
    function closeVideoModal() {
        const $modal = $('#wevoi-video-modal');

        $modal.fadeOut(300, function() {
            // Stop video playback
            if (youtubePlayer && youtubePlayer.stopVideo) {
                youtubePlayer.stopVideo();
            }

            // Clear iframe content
            const playerContainer = document.getElementById('wevoi-youtube-player');
            if (playerContainer) {
                playerContainer.innerHTML = '';
            }
        });
    }

    /**
     * Pause all playing videos
     */
    function pauseAllVideos() {
        if (youtubePlayer && youtubePlayer.pauseVideo) {
            youtubePlayer.pauseVideo();
        }

        // Pause any iframe videos
        $('iframe[src*="youtube.com/embed"]').each(function() {
            this.contentWindow.postMessage('{"event":"command","func":"pauseVideo","args":""}', '*');
        });
    }

    /**
     * Track video view for analytics (optional)
     */
    function trackVideoView(videoId, videoUrl) {
        // This is where you can add analytics tracking
        // Example: Google Analytics, Facebook Pixel, etc.

        if (typeof gtag !== 'undefined') {
            gtag('event', 'video_view', {
                'event_category': 'wevoi_summit_videos',
                'event_label': videoId,
                'value': 1
            });
        }

        // Custom event for other tracking systems
        $(document).trigger('wevoi_video_view', {
            videoId: videoId,
            videoUrl: videoUrl,
            timestamp: new Date().toISOString()
        });
    }

    /**
     * Handle window resize
     */
    $(window).on('resize', function() {
        if (swiperInstance) {
            swiperInstance.update();
        }
    });

    /**
     * Lazy loading for images
     */
    function setupLazyLoading() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src || img.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    }

    // Initialize lazy loading
    setupLazyLoading();

    /**
     * Add loading states and error handling
     */
    function setupErrorHandling() {
        // Handle image load errors
        $(document).on('error', '.wevoi-video-thumbnail img', function() {
            $(this).closest('.wevoi-video-thumbnail').addClass('error');
            $(this).attr('src', 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzIwIiBoZWlnaHQ9IjE4MCIgdmlld0JveD0iMCAwIDMyMCAxODAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIzMjAiIGhlaWdodD0iMTgwIiBmaWxsPSIjRjhGOUZBIi8+CjxwYXRoIGQ9Ik0xNjAgOTBDMTYwIDkwIDE2MCA5MCAxNjAgOTBMMTYwIDkwWiIgZmlsbD0iI0Q0RDRENCIvPgo8L3N2Zz4K');
        });

        // Handle API errors
        window.onYouTubeIframeAPIError = function() {
            console.error('YouTube API failed to load');
            youtubeAPILoaded = false;
        };
    }

    // Initialize error handling
    setupErrorHandling();

})(jQuery);
