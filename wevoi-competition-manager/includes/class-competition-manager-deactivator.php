<?php
/**
 * Plugin deaktivasyon işlemleri
 */
class Wevoi_Competition_Manager_Deactivator {

    /**
     * Plugin deaktif edildiğinde çalışacak fonksiyon
     */
    public static function deactivate() {
        // Yetkileri kaldır
        self::remove_capabilities();
        
        // Rewrite kurallarını temizle
        flush_rewrite_rules();
    }

    /**
     * Admin yetkilerini kaldır
     */
    private static function remove_capabilities() {
        $admin = get_role('administrator');
        
        // Competitions yetkileri
        $admin->remove_cap('manage_competitions');
        $admin->remove_cap('create_competitions');
        $admin->remove_cap('edit_competitions');
        $admin->remove_cap('delete_competitions');
        
        // Coupons yetkileri
        $admin->remove_cap('manage_coupons');
        $admin->remove_cap('create_coupons');
        $admin->remove_cap('edit_coupons');
        $admin->remove_cap('delete_coupons');
    }
}