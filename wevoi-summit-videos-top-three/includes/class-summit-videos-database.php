<?php
/**
 * Database operations for WeVoi Summit Videos
 */

if (!defined('ABSPATH')) {
    exit;
}

class WeVoi_Summit_Videos_Database {
    private static $instance = null;
    private $db;

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
        global $wpdb;
        $this->db = $wpdb;
    }

    /**
     * Get the most recent completed WeVoi Summit
     */
    public function get_latest_completed_summit() {
        $query = "SELECT * FROM {$this->db->prefix}wevoi_competitions
                 WHERE competition_status = 'COMPLETED'
                 AND award_ceremony_completed = 1
                 ORDER BY competition_date DESC
                 LIMIT 1";

        return $this->db->get_row($query);
    }

    /**
     * Get top three videos from each age category for the most recent completed summit
     */
    public function get_top_three_videos_by_category($competition_code = null) {
        // If no competition code provided, get the latest completed summit
        if (!$competition_code) {
            $latest_summit = $this->get_latest_completed_summit();
            if (!$latest_summit) {
                return array();
            }
            $competition_code = $latest_summit->competition_code;
        }

        // Get all age categories for this competition
        $age_categories = $this->get_age_categories($competition_code);

        if (empty($age_categories)) {
            return array();
        }

        $results = array();

        // For each age category, get top 3 results
        foreach ($age_categories as $age_category) {
            $query = "SELECT r.*,
                      u.display_name,
                      u.user_email,
                      um_first.meta_value as first_name,
                      um_last.meta_value as last_name,
                      ac.age_category,
                      co.countryname,
                      co.countryid,
                      s.schoolname
                      FROM {$this->db->prefix}wevoi_registrations r
                      JOIN {$this->db->prefix}users u ON r.user_id = u.ID
                      LEFT JOIN {$this->db->prefix}usermeta um_first ON r.user_id = um_first.user_id AND um_first.meta_key = 'first_name'
                      LEFT JOIN {$this->db->prefix}usermeta um_last ON r.user_id = um_last.user_id AND um_last.meta_key = 'last_name'
                      LEFT JOIN {$this->db->prefix}wevoi_age_categories ac ON r.age_category_id = ac.id
                      LEFT JOIN {$this->db->prefix}usermeta um_country ON r.user_id = um_country.user_id AND um_country.meta_key = 'countryid'
                      LEFT JOIN {$this->db->prefix}wevoi_countries co ON um_country.meta_value = co.countryid
                      LEFT JOIN {$this->db->prefix}usermeta um_school ON r.user_id = um_school.user_id AND um_school.meta_key = 'schoolid'
                      LEFT JOIN {$this->db->prefix}wevoi_schools s ON um_school.meta_value = s.schoolid
                      WHERE r.competition_code = %d
                      AND r.application_status = 'APPLICATION_ACCEPTED'
                      AND r.student_medal IS NOT NULL
                      AND r.youtube_link IS NOT NULL
                      AND r.youtube_link != ''
                      AND ac.age_category = %s
                      ORDER BY r.student_rank ASC, r.student_point DESC
                      LIMIT 3";

            $category_results = $this->db->get_results(
                $this->db->prepare($query,
                    array(
                        $competition_code,
                        $age_category,
                        3
                    )
                )
            );

            if (!empty($category_results)) {
                // Store results by age category
                $results[$age_category] = $category_results;
            }
        }

        return $results;
    }

    /**
     * Get all age categories for a competition
     */
    public function get_age_categories($competition_code) {
        return $this->db->get_col($this->db->prepare(
            "SELECT DISTINCT ac.age_category
            FROM {$this->db->prefix}wevoi_registrations r
            JOIN {$this->db->prefix}wevoi_age_categories ac ON r.age_category_id = ac.id
            WHERE r.competition_code = %d
            AND r.student_medal IS NOT NULL
            AND r.youtube_link IS NOT NULL
            AND r.youtube_link != ''
            ORDER BY ac.age_category",
            $competition_code
        ));
    }

    /**
     * Get competition details by competition code
     */
    public function get_competition_by_code($competition_code) {
        return $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->db->prefix}wevoi_competitions
            WHERE competition_code = %d
            AND award_ceremony_completed = 1",
            $competition_code
        ));
    }

    /**
     * Extract YouTube ID from URL
     */
    public function extract_youtube_id($url) {
        // Check if it's a YouTube Shorts URL
        if (strpos($url, 'youtube.com/shorts/') !== false) {
            $pattern = '/(?:youtube\.com\/shorts\/)([a-zA-Z0-9_-]{11})/';
        } else {
            // Standard YouTube URL
            $pattern = '/(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
        }

        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        return false;
    }

    /**
     * Get all videos for carousel (flattened from all categories)
     */
    public function get_all_top_three_videos($competition_code = null) {
        $results_by_category = $this->get_top_three_videos_by_category($competition_code);
        $all_videos = array();

        foreach ($results_by_category as $category => $videos) {
            foreach ($videos as $video) {
                $video->age_category_name = $category;
                $video->youtube_id = $this->extract_youtube_id($video->youtube_link);
                $all_videos[] = $video;
            }
        }

        // Sort by rank within each category, then by points
        usort($all_videos, function($a, $b) {
            if ($a->student_rank == $b->student_rank) {
                return $b->student_point <=> $a->student_point;
            }
            return $a->student_rank <=> $b->student_rank;
        });

        return $all_videos;
    }
}
