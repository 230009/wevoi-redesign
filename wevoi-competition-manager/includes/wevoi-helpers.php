<?php
/**
 * Helper functions for WeVoi Competition Manager.
 * UTF-8 Turkish-aware name formatting (i → İ, I → ı).
 *
 * @package Wevoi_Competition_Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Format a string as proper/title case for display, with Turkish rules (UTF-8).
 * - Lowercase "i" becomes "İ" when capitalized (not "I").
 * - Uppercase "I" becomes "ı" when lowercased (not "i").
 *
 * @param string $str Name or word(s).
 * @return string Formatted string.
 */
function wevoi_proper_name($str) {
    if (!is_string($str) || trim($str) === '') {
        return $str;
    }
    $str = trim($str);
    if (!function_exists('mb_substr') || !function_exists('mb_strtoupper') || !function_exists('mb_strtolower')) {
        return $str;
    }
    $words = preg_split('/(\s+)/u', $str, -1, PREG_SPLIT_DELIM_CAPTURE);
    $out = array();
    foreach ($words as $i => $w) {
        if ($i % 2 === 0) {
            if ($w === '') {
                $out[] = $w;
                continue;
            }
            // Turkish lowercase: I → ı, İ → i (before mb_strtolower)
            $w = str_replace(array('I', 'İ'), array('ı', 'i'), $w);
            $w = mb_strtolower($w, 'UTF-8');
            $first = mb_substr($w, 0, 1, 'UTF-8');
            $rest = mb_substr($w, 1, null, 'UTF-8');
            if ($first === 'i') {
                $first = 'İ';
            } elseif ($first === 'ı') {
                $first = 'I';
            } else {
                $first = mb_strtoupper($first, 'UTF-8');
            }
            $out[] = $first . $rest;
        } else {
            $out[] = $w;
        }
    }
    return implode('', $out);
}

/**
 * Format full name for display: first + last in proper case, or display_name fallback.
 *
 * @param string $first_name  First name (optional).
 * @param string $last_name   Last name (optional).
 * @param string $display_name Display name fallback (optional).
 * @return string Formatted full name.
 */
function wevoi_format_display_name($first_name = '', $last_name = '', $display_name = '') {
    $first = is_string($first_name) ? trim($first_name) : '';
    $last = is_string($last_name) ? trim($last_name) : '';
    $full = trim($first . ' ' . $last);
    if ($full !== '') {
        return wevoi_proper_name($full);
    }
    $display = is_string($display_name) ? trim($display_name) : '';
    if ($display !== '') {
        return wevoi_proper_name($display);
    }
    return '—';
}

/**
 * Age category names from wpie_wevoi_age_categories, ordered by starting age.
 *
 * @return string[]
 */
function wevoi_get_age_categories() {
    static $categories = null;

    if ($categories !== null) {
        return $categories;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'wevoi_age_categories';
    $rows = $wpdb->get_col("SELECT age_category FROM {$table} ORDER BY starting_age ASC, id ASC");
    $categories = is_array($rows) ? array_values(array_filter($rows)) : array();

    return $categories;
}
