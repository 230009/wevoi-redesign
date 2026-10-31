<?php
/**
 * Shortcode handler for WeVoi Summit Videos
 */

if (!defined('ABSPATH')) {
    exit;
}

class WeVoi_Summit_Videos_Shortcode {
    private static $instance = null;
    private $database;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton
     */
    private function __construct() {
        $this->database = WeVoi_Summit_Videos_Database::instance();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_shortcode('wevoi_summit_videos', array($this, 'render_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Enqueue scripts and styles when shortcode is used
     */
    public function enqueue_scripts() {
        global $post;

        // Only enqueue if shortcode is present on current page
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'wevoi_summit_videos')) {
            wp_enqueue_style('wevoi-summit-videos-style');
            wp_enqueue_style('swiper-css');
            wp_enqueue_script('swiper-js');
            wp_enqueue_script('wevoi-summit-videos-script');
            wp_enqueue_script('youtube-api');
        }
    }

    /**
     * Render the shortcode
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'competition_code' => null,
            'show_title' => 'true',
            'show_date' => 'true',
            'autoplay' => 'true',
            'speed' => '3000',
            'slides_per_view' => '3',
            'space_between' => '30'
        ), $atts);

        // Get videos data
        $videos = $this->database->get_all_top_three_videos($atts['competition_code']);

        if (empty($videos)) {
            return '<div class="wevoi-summit-videos-no-data">' .
                   __('No summit videos available at the moment.', 'wevoi-summit-videos-top-three') .
                   '</div>';
        }

        // Get competition details
        $competition = null;
        if ($atts['competition_code']) {
            $competition = $this->database->get_competition_by_code($atts['competition_code']);
        } else {
            $competition = $this->database->get_latest_completed_summit();
        }

        ob_start();
        ?>
        <div class="wevoi-summit-videos-container"
             data-autoplay="<?php echo esc_attr($atts['autoplay']); ?>"
             data-speed="<?php echo esc_attr($atts['speed']); ?>"
             data-slides-per-view="<?php echo esc_attr($atts['slides_per_view']); ?>"
             data-space-between="<?php echo esc_attr($atts['space_between']); ?>">

            <?php if ($atts['show_title'] === 'true' && $competition): ?>
                <div class="wevoi-summit-videos-header">
                    <h2 class="wevoi-summit-videos-title">
                        <?php echo esc_html($competition->competition_name); ?>
                    </h2>
                    <?php if ($atts['show_date'] === 'true'): ?>
                        <p class="wevoi-summit-videos-date">
                            <?php echo esc_html(date('j F Y', strtotime($competition->competition_date))); ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="wevoi-summit-videos-carousel swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($videos as $video): ?>
                        <div class="swiper-slide">
                            <div class="wevoi-video-card">
                                <div class="wevoi-video-thumbnail">
                                    <?php if ($video->youtube_id): ?>
                                        <img src="https://img.youtube.com/vi/<?php echo esc_attr($video->youtube_id); ?>/mqdefault.jpg"
                                             alt="<?php echo esc_attr($video->first_name . ' ' . $video->last_name); ?>">
                                        <div class="wevoi-video-play-button"
                                             data-video-id="<?php echo esc_attr($video->youtube_id); ?>"
                                             data-video-url="<?php echo esc_url($video->youtube_link); ?>">
                                            <svg width="68" height="48" viewBox="0 0 68 48">
                                                <path d="M66.52,7.74c-0.78-2.93-2.49-5.41-5.42-6.19C55.79,.13,34,0,34,0S12.21,.13,6.9,1.55 C3.97,2.33,2.27,4.81,1.48,7.74C0.06,13.05,0,24,0,24s0.06,10.95,1.48,16.26c0.78,2.93,2.49,5.41,5.42,6.19 C12.21,47.87,34,48,34,48s21.79-0.13,27.1-1.55c2.93-0.78,4.64-3.26,5.42-6.19C67.94,34.95,68,24,68,24S67.94,13.05,66.52,7.74z" fill="#f00"></path>
                                                <path d="M 45,24 27,14 27,34" fill="#fff"></path>
                                            </svg>
                                        </div>
                                    <?php else: ?>
                                        <div class="wevoi-video-no-thumbnail">
                                            <span><?php _e('Video not available', 'wevoi-summit-videos-top-three'); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="wevoi-video-info">
                                    <h3 class="wevoi-video-participant">
                                        <?php echo esc_html($video->first_name . ' ' . $video->last_name); ?>
                                    </h3>
                                    <div class="wevoi-video-details">
                                        <p class="wevoi-video-country">
                                            <span class="flag-icon">
                                                <img src="<?php echo WEVOI_SUMMIT_VIDEOS_PLUGIN_URL; ?>assets/flags/<?php echo esc_attr($video->countryid); ?>.png"
                                                     alt="<?php echo esc_attr($video->countryname); ?>"
                                                     onerror="this.style.display='none'">
                                            </span>
                                            <?php echo esc_html($video->countryname); ?>
                                        </p>
                                    </div>

                                    <div class="wevoi-video-stats">
                                        <span class="wevoi-video-rank">
                                            <?php echo esc_html($video->student_rank); ?><?php echo $this->get_rank_suffix($video->student_rank); ?>
                                        </span>
                                        <span class="wevoi-video-category">
                                            <?php echo esc_html($video->age_category_name); ?>
                                        </span>
                                        <span class="wevoi-video-medal wevoi-medal-<?php echo strtolower($video->student_medal); ?>">
                                            <?php echo esc_html($video->student_medal); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Navigation buttons -->
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>

                <!-- Pagination -->
                <div class="swiper-pagination"></div>
            </div>
        </div>

        <!-- Video Modal -->
        <div class="wevoi-video-modal" id="wevoi-video-modal">
            <div class="wevoi-modal-content">
                <span class="wevoi-modal-close">&times;</span>
                <div class="wevoi-modal-video-container">
                    <div id="wevoi-youtube-player"></div>
                </div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Get rank suffix (st, nd, rd, th)
     */
    private function get_rank_suffix($rank) {
        $suffixes = array(
            1 => 'st',
            2 => 'nd',
            3 => 'rd'
        );

        $last_digit = $rank % 10;
        $last_two_digits = $rank % 100;

        // Handle special cases for 11th, 12th, 13th
        if ($last_two_digits >= 11 && $last_two_digits <= 13) {
            return 'th';
        }

        return isset($suffixes[$last_digit]) ? $suffixes[$last_digit] : 'th';
    }
}
