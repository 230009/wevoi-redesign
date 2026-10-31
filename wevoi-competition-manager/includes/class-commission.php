<?php
/**
 * Commission calculation and helpers for supervisor/agency commissions.
 * Spark, Reload, Summit — PAID registrations only; aggregated per (user, competition, season).
 *
 * @package Wevoi_Competition_Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wevoi_Commission {

    /** @var wpdb */
    private $wpdb;

    /** @var string */
    private $payments_table;

    /** @var string */
    private $details_table;

    /** @var string */
    private $rates_table;

    /** @var string */
    private $registrations_table;

    /** @var string */
    private $competitions_table;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $p = $wpdb->prefix;
        $this->payments_table = $p . 'wevoi_commission_payments';
        $this->details_table = $p . 'wevoi_commission_payment_details';
        $this->rates_table = $p . 'wevoi_commission_rates';
        $this->registrations_table = $p . 'wevoi_registrations';
        $this->competitions_table = $p . 'wevoi_competitions';
    }

    /**
     * Check if competition name is Spark, Reload, or Summit.
     *
     * @param string $competition_name
     * @return bool
     */
    public static function is_commissionable_competition($competition_name) {
        if (!is_string($competition_name)) {
            return false;
        }
        $name = strtolower($competition_name);
        return (strpos($name, 'spark') !== false
            || strpos($name, 'reload') !== false
            || strpos($name, 'summit') !== false);
    }

    /**
     * Get Stripe fee for an amount (percent + fixed).
     *
     * @param float $amount Price paid (e.g. 20.00)
     * @return float Fee amount
     */
    public function get_stripe_fee($amount) {
        $percent = floatval(get_option('wevoi_stripe_fee_percent', 2.9));
        $fixed = floatval(get_option('wevoi_stripe_fee_fixed', 0.20));
        return round($amount * ($percent / 100) + $fixed, 2);
    }

    /**
     * Get commission rate for season and role (supervisor or agency).
     *
     * @param int    $season_id
     * @param string $role supervisor|agency
     * @return float|null Percent or null if not found
     */
    public function get_commission_rate($season_id, $role) {
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT commission_percent FROM {$this->rates_table} WHERE season_id = %d AND role = %s",
            $season_id,
            $role
        ));
        return $row ? floatval($row->commission_percent) : null;
    }

    /**
     * Get WordPress role for user (supervisor or agency). Returns first matching.
     *
     * @param int $user_id
     * @return string|null 'supervisor'|'agency' or null
     */
    public static function get_commission_role_for_user($user_id) {
        $user = get_userdata($user_id);
        if (!$user || empty($user->roles)) {
            return null;
        }
        $roles = array_map('strtolower', (array) $user->roles);
        if (in_array('agency', $roles, true)) {
            return 'agency';
        }
        if (in_array('supervisor', $roles, true)) {
            return 'supervisor';
        }
        return null;
    }

    /**
     * Run commission calculation for a competition. Call when competition becomes COMPLETED.
     * Only runs for Spark/Reload/Summit; only PAID registrations with supervisor_id; skips if already calculated.
     *
     * @param int $competition_code
     * @return array{ ok: bool, message: string, created_count: int }
     */
    public function run_for_competition($competition_code) {
        $competition_code = (int) $competition_code;
        $comp = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT competition_code, competition_name, competition_status FROM {$this->competitions_table} WHERE competition_code = %d",
            $competition_code
        ));

        if (!$comp) {
            return array('ok' => false, 'message' => 'Competition not found', 'created_count' => 0);
        }

        if ($comp->competition_status !== 'COMPLETED') {
            return array('ok' => true, 'message' => 'Status not COMPLETED, skip', 'created_count' => 0);
        }

        if (!self::is_commissionable_competition($comp->competition_name)) {
            return array('ok' => true, 'message' => 'Not Spark/Reload/Summit, skip', 'created_count' => 0);
        }

        // season_id: support both column and fallback from competition name year
        $season_id = null;
        $table_columns = $this->wpdb->get_col("SHOW COLUMNS FROM {$this->competitions_table}");
        if (in_array('season_id', $table_columns, true)) {
            $row = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT season_id FROM {$this->competitions_table} WHERE competition_code = %d",
                $competition_code
            ));
            $season_id = $row && !empty($row->season_id) ? (int) $row->season_id : null;
        }
        if ($season_id === null && preg_match('/\b(20\d{2})\b/', $comp->competition_name, $m)) {
            $year = (int) $m[1];
            $season_abv = ($year - 2000) . '-' . (($year - 1999) % 100);
            $season_row = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT season_id FROM {$this->wpdb->prefix}wevoi_seasons WHERE season_abv = %s LIMIT 1",
                $season_abv
            ));
            $season_id = $season_row ? (int) $season_row->season_id : null;
        }

        if (!$season_id) {
            return array('ok' => false, 'message' => 'Season not found for competition', 'created_count' => 0);
        }

        // Minimum students per season: supervisor/agency must have >= min_students_commission in season total to get any commission
        $seasons_table = $this->wpdb->prefix . 'wevoi_seasons';
        $min_students = 0;
        $has_season_col = $this->wpdb->get_col("SHOW COLUMNS FROM {$seasons_table} LIKE 'min_students_commission'");
        if (!empty($has_season_col)) {
            $row = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT min_students_commission FROM {$seasons_table} WHERE season_id = %d",
                $season_id
            ));
            $min_students = $row && isset($row->min_students_commission) && $row->min_students_commission !== null ? max(0, (int) $row->min_students_commission) : 0;
        }

        $competitions_has_season = in_array('season_id', $this->wpdb->get_col("SHOW COLUMNS FROM {$this->competitions_table}"), true);
        $season_student_counts = array();
        if ($min_students > 0 && $competitions_has_season) {
            $season_student_counts = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT r.supervisor_id, COUNT(DISTINCT r.user_id) as cnt
                 FROM {$this->registrations_table} r
                 INNER JOIN {$this->competitions_table} c ON c.competition_code = r.competition_code AND c.season_id = %d
                 WHERE r.paid_status = 'PAID' AND r.supervisor_id IS NOT NULL AND r.supervisor_id > 0
                 GROUP BY r.supervisor_id",
                $season_id
            ), OBJECT_K);
            if (!is_array($season_student_counts)) {
                $season_student_counts = array();
            }
        }

        // PAID only; supervisor_id not null; must have positive price (exclude null/zero)
        $regs = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT register_id, user_id, supervisor_id, price
             FROM {$this->registrations_table}
             WHERE competition_code = %d AND paid_status = 'PAID' AND supervisor_id IS NOT NULL AND supervisor_id > 0
             AND price IS NOT NULL AND price > 0",
            $competition_code
        ), ARRAY_A);

        if (empty($regs)) {
            return array('ok' => true, 'message' => 'No PAID registrations with supervisor', 'created_count' => 0);
        }

        $stripe_fee_pct = floatval(get_option('wevoi_stripe_fee_percent', 2.9));
        $stripe_fee_fixed = floatval(get_option('wevoi_stripe_fee_fixed', 0.20));

        // Group by (supervisor_id, competition_code, season_id) — already unique per competition
        $groups = array();
        foreach ($regs as $r) {
            $uid = (int) $r['supervisor_id'];

            if ($min_students > 0 && isset($season_student_counts[$uid])) {
                $total_in_season = (int) $season_student_counts[$uid]->cnt;
                if ($total_in_season < $min_students) {
                    continue;
                }
            } elseif ($min_students > 0) {
                continue;
            }

            $role = self::get_commission_role_for_user($uid);
            if (!$role) {
                continue;
            }
            $rate = $this->get_commission_rate($season_id, $role);
            if ($rate === null) {
                continue;
            }
            $price = floatval($r['price']);
            $fee = round($price * ($stripe_fee_pct / 100) + $stripe_fee_fixed, 2);
            $net = round($price - $fee, 2);
            $commission = round($net * ($rate / 100), 2);

            $key = $uid . '_' . $competition_code . '_' . $season_id;
            if (!isset($groups[$key])) {
                $groups[$key] = array(
                    'user_id' => $uid,
                    'competition_code' => $competition_code,
                    'season_id' => $season_id,
                    'role' => $role,
                    'total' => 0,
                    'details' => array(),
                );
            }
            $groups[$key]['total'] += $commission;
            $groups[$key]['details'][] = array(
                'register_id' => (int) $r['register_id'],
                'registration_price' => $price,
                'stripe_fee' => $fee,
                'net_amount' => $net,
                'commission_percent' => $rate,
                'commission_amount' => $commission,
            );
        }

        $created = 0;
        foreach ($groups as $group) {
            $exists = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM {$this->payments_table} WHERE user_id = %d AND competition_code = %d AND season_id = %d",
                $group['user_id'],
                $group['competition_code'],
                $group['season_id']
            ));
            if ($exists) {
                continue;
            }

            $this->wpdb->insert(
                $this->payments_table,
                array(
                    'user_id' => $group['user_id'],
                    'competition_code' => $group['competition_code'],
                    'season_id' => $group['season_id'],
                    'role' => $group['role'],
                    'total_commission_amount' => round($group['total'], 2),
                    'status' => 'PENDING',
                ),
                array('%d', '%d', '%d', '%s', '%f', '%s')
            );

            $payment_id = (int) $this->wpdb->insert_id;
            if ($payment_id <= 0) {
                continue;
            }

            foreach ($group['details'] as $d) {
                $this->wpdb->insert(
                    $this->details_table,
                    array(
                        'commission_payment_id' => $payment_id,
                        'register_id' => $d['register_id'],
                        'registration_price' => $d['registration_price'],
                        'stripe_fee' => $d['stripe_fee'],
                        'net_amount' => $d['net_amount'],
                        'commission_percent' => $d['commission_percent'],
                        'commission_amount' => $d['commission_amount'],
                    ),
                    array('%d', '%d', '%f', '%f', '%f', '%f', '%f')
                );
            }
            $created++;
        }

        return array('ok' => true, 'message' => 'Commissions calculated', 'created_count' => $created);
    }

    /**
     * Check if user has commission bank details (usermeta JSON).
     *
     * @param int $user_id
     * @return bool
     */
    public static function user_has_bank_details($user_id) {
        $json = get_user_meta($user_id, 'wevoi_commission_bank_details', true);
        if (!is_string($json) || $json === '') {
            return false;
        }
        $data = json_decode($json, true);
        return is_array($data) && !empty(array_filter($data));
    }
}
