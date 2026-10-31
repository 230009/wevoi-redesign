<?php
/**
 * WeVoi Summit (Final): students need an accepted Spark or Reload qualification in the same season.
 *
 * @package WeVoi_Register
 */

if (!defined('ABSPATH')) {
    exit;
}

class WeVoi_Summit_Eligibility {

    /**
     * Non–Final competitions are always allowed.
     * Final without season_id: no check (legacy / misconfigured row).
     */
    public static function user_may_register_for_competition($wpdb, $user_id, $competition) {
        if (!$competition || empty($competition->competition_type) || $competition->competition_type !== 'Final') {
            return true;
        }
        $season_id = isset($competition->season_id) ? (int) $competition->season_id : 0;
        if ($season_id <= 0) {
            return true;
        }
        return self::user_qualifies_for_summit_season($wpdb, (int) $user_id, $season_id);
    }

    /**
     * Same season: at least one accepted Qualification whose name matches Spark or Reload.
     */
    public static function user_qualifies_for_summit_season($wpdb, $user_id, $season_id) {
        return self::has_accepted_qualification_named($wpdb, $user_id, $season_id, 'spark')
            || self::has_accepted_qualification_named($wpdb, $user_id, $season_id, 'reload');
    }

    /**
     * @param string $needle lowercase substring matched against competition_name (e.g. spark, reload).
     */
    private static function has_accepted_qualification_named($wpdb, $user_id, $season_id, $needle) {
        $like = '%' . $wpdb->esc_like($needle) . '%';
        $one = $wpdb->get_var($wpdb->prepare(
            "SELECT 1
             FROM {$wpdb->prefix}wevoi_registrations r
             INNER JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
             WHERE r.user_id = %d
             AND c.season_id = %d
             AND c.competition_type = %s
             AND LOWER(c.competition_name) LIKE %s
             AND r.application_status = %s
             AND r.paid_status IN ('PAID', 'FREE')
             LIMIT 1",
            $user_id,
            $season_id,
            'Qualification',
            $like,
            'APPLICATION_ACCEPTED'
        ));
        return !empty($one);
    }

    /**
     * User-facing error when blocking Summit registration.
     */
    public static function get_denial_message() {
        return 'WeVoi Summit is only open to students who took part in WeVoi Spark or WeVoi Reload in the same season with an accepted video application.';
    }

    /**
     * Shown on /register/ when Summit (Final) is ON AIR but the student fails qualification and no other video competition is available.
     */
    public static function get_unqualified_registration_notice_html() {
        return '<div class="wevoi-summit-unqualified-notice" style="max-width: 720px; margin: 1.25em auto; padding: 18px 22px; background: #fff; border: 1px solid #e0e0e0; border-radius: 6px; line-height: 1.65; color: #444;">'
            . '<p style="margin: 0 0 14px;"><strong>WeVoi Summit</strong> registration is open, but you are <strong>not eligible</strong> this season. Summit is only for students who took part in <strong>WeVoi Spark</strong> or <strong>WeVoi Reload</strong> in the same season with an accepted video application.</p>'
            . '<p style="margin: 0;">We invite you to take part in <strong>WeVoi Spark</strong> or <strong>WeVoi Reload</strong> next season so you can qualify for WeVoi Summit.</p>'
            . '</div>';
    }

    /**
     * True when every row is a Final competition (e.g. only Summit is ON AIR).
     *
     * @param array $competitions Objects with competition_type.
     */
    public static function active_list_is_only_final_competitions(array $competitions) {
        if (empty($competitions)) {
            return false;
        }
        foreach ($competitions as $c) {
            if (empty($c->competition_type) || $c->competition_type !== 'Final') {
                return false;
            }
        }
        return true;
    }
}
