<?php
// WordPress'in dışından direkt erişimi engelle
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Options'ları temizle
delete_option('wevoi_competition_manager_db_version');