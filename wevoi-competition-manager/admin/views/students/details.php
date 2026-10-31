<?php
// Direct access kontrolü
if (!defined('ABSPATH')) {
    exit;
}

// Öğrenci ID parametresini kontrol et
if (!isset($student_id)) {
    wp_die(__('Student ID is required.', 'wevoi-competition-manager'));
}


// Hata ayıklama için
error_log('Details.php çalıştırılıyor, student_id: ' . (isset($student_id) ? $student_id : 'tanımlanmamış'));

// Öğrenci ID kontrolü
if (!isset($student_id) || empty($student_id)) {
    error_log('Details.php Hata: student_id tanımlanmamış veya boş');
    wp_die(__('Student ID is required.', 'wevoi-competition-manager'));
}


// Öğrenci bilgilerini al
global $wpdb;

// Öğrenci detayları
$student = $wpdb->get_row($wpdb->prepare(
    "SELECT u.*, 
            um_country.meta_value as countryid,
            c.countryname,
            um_school.meta_value as schoolid,
            s.schoolname
     FROM {$wpdb->users} u
     LEFT JOIN {$wpdb->usermeta} um_country ON u.ID = um_country.user_id AND um_country.meta_key = 'countryid'
     LEFT JOIN {$wpdb->usermeta} um_school ON u.ID = um_school.user_id AND um_school.meta_key = 'schoolid'
     LEFT JOIN {$wpdb->prefix}wevoi_countries c ON c.countryid = um_country.meta_value
     LEFT JOIN {$wpdb->prefix}wevoi_schools s ON s.schoolid = um_school.meta_value
     WHERE u.ID = %d",
    $student_id
));

if (!$student) {
    wp_die(__('Student not found.', 'wevoi-competition-manager'));
}

// Yarışma kayıtlarını al
$registrations = $wpdb->get_results($wpdb->prepare(
    "SELECT r.*, 
            c.competition_name,
            c.competition_status,
            c.competition_date
     FROM {$wpdb->prefix}wevoi_registrations r
     LEFT JOIN {$wpdb->prefix}wevoi_competitions c ON r.competition_code = c.competition_code
     WHERE r.user_id = %d
     ORDER BY r.registration_date DESC",
    $student_id
));
?>

<div class="student-details-wrapper">
    <!-- Öğrenci Bilgileri -->
    <div class="student-info section">
        <h3><?php _e('Student Information', 'wevoi-competition-manager'); ?></h3>
        <table class="widefat striped">
            <tr>
                <th><?php _e('Name:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo esc_html(wevoi_proper_name($student->display_name)); ?></td>
            </tr>
            <tr>
                <th><?php _e('Email:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo esc_html($student->user_email); ?></td>
            </tr>
            <tr>
                <th><?php _e('Registration Date:', 'wevoi-competition-manager'); ?></th>
                <td><?php echo date_i18n(get_option('date_format'), strtotime($student->user_registered)); ?></td>
            </tr>
            <tr>
                <th><?php _e('Country:', 'wevoi-competition-manager'); ?></th>
                <td>
                    <?php 
                    if ($student->countryname) {
                        printf('%s (ID: %d)', esc_html($student->countryname), intval($student->countryid));
                    } else {
                        echo '-';
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th><?php _e('School:', 'wevoi-competition-manager'); ?></th>
                <td>
                    <?php 
                    if ($student->schoolname) {
                        printf('%s (ID: %d)', esc_html($student->schoolname), intval($student->schoolid));
                    } else {
                        echo '-';
                    }
                    ?>
                </td>
            </tr>
        </table>
    </div>

    <!-- Yarışma Kayıtları -->
    <div class="competition-registrations section">
        <h3><?php _e('Competition Registrations', 'wevoi-competition-manager'); ?></h3>
        <?php if ($registrations): ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Competition', 'wevoi-competition-manager'); ?></th>
                        <th><?php _e('Registration Date', 'wevoi-competition-manager'); ?></th>
                        <th><?php _e('Payment Status', 'wevoi-competition-manager'); ?></th>
                        <th><?php _e('Application Status', 'wevoi-competition-manager'); ?></th>
                        <th><?php _e('YouTube Link', 'wevoi-competition-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registrations as $reg): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($reg->competition_name); ?></strong>
                                <div class="row-actions">
                                    <span class="code">Code: <?php echo esc_html($reg->competition_code); ?></span>
                                </div>
                            </td>
                            <td>
                                <?php echo date_i18n(get_option('date_format'), strtotime($reg->registration_date)); ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($reg->paid_status); ?>">
                                    <?php echo esc_html($reg->paid_status); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($reg->application_status); ?>">
                                    <?php echo esc_html($reg->application_status); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($reg->youtube_link): ?>
                                    <a href="<?php echo esc_url($reg->youtube_link); ?>" 
                                       target="_blank" 
                                       class="youtube-link">
                                        <?php _e('Watch Video', 'wevoi-competition-manager'); ?>
                                        <span class="dashicons dashicons-external"></span>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-items"><?php _e('No competition registrations found.', 'wevoi-competition-manager'); ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Yarışma Sonuçları -->
<div class="competition-results section">
    <h3><?php _e('Competition Results', 'wevoi-competition-manager'); ?></h3>
    <?php if ($registrations): ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php _e('Competition', 'wevoi-competition-manager'); ?></th>
                    <th><?php _e('Point', 'wevoi-competition-manager'); ?></th>
                    <th><?php _e('Rank', 'wevoi-competition-manager'); ?></th>
                    <th><?php _e('Medal', 'wevoi-competition-manager'); ?></th>
                    <th><?php _e('Summit & Prize Info', 'wevoi-competition-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registrations as $reg): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($reg->competition_name); ?></strong>
                            <div class="row-actions">
                                <span class="code">Code: <?php echo esc_html($reg->competition_code); ?></span>
                            </div>
                        </td>
                        <td><?php echo $reg->student_point ? number_format($reg->student_point, 2) : '-'; ?></td>
                        <td><?php echo $reg->student_rank ? '#' . intval($reg->student_rank) : '-'; ?></td>
                        <td>
                            <?php if ($reg->student_medal): ?>
                                <span class="medal-badge medal-<?php echo strtolower($reg->student_medal); ?>">
                                    <?php 
                                    $medal_icon = '';
                                    switch ($reg->student_medal) {
                                        case 'GOLD':    $medal_icon = 'dashicons-awards'; break;
                                        case 'SILVER':  $medal_icon = 'dashicons-star-filled'; break;
                                        case 'BRONZE':  $medal_icon = 'dashicons-star-half'; break;
                                        case 'SUCCESS': $medal_icon = 'dashicons-yes-alt'; break;
                                    }
                                    ?>
                                    <span class="dashicons <?php echo $medal_icon; ?>"></span>
                                    <?php echo esc_html($reg->student_medal); ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $info_items = [];
                            
                            // Summit kupon kodu (ilk 5 için)
                            if ($reg->student_rank && $reg->student_rank <= 5 && $reg->claimed_summit_coupon_code) {
                                $info_items[] = sprintf(
                                    '<div><strong>Summit Coupon:</strong> %s</div>',
                                    esc_html($reg->claimed_summit_coupon_code)
                                );
                            }

                            // Ödül bilgisi (ilk 3 ve summit kuponu olanlar için)
                            if ($reg->student_rank && $reg->student_rank <= 3 && $reg->claimed_summit_coupon_code) {
                                if ($reg->claimed_prize_amount) {
                                    $info_items[] = sprintf(
                                        '<div><strong>Prize Amount:</strong> £%s</div>',
                                        number_format($reg->claimed_prize_amount, 2)
                                    );
                                    $info_items[] = sprintf(
                                        '<div><strong>Prize Payment Status:</strong> <span class="status-badge status-%s">%s</span></div>',
                                        strtolower($reg->claimed_prize_paid_status),
                                        esc_html($reg->claimed_prize_paid_status)
                                    );
                                }
                            }

                            echo !empty($info_items) ? implode('', $info_items) : '-';
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="no-items"><?php _e('No competition results found.', 'wevoi-competition-manager'); ?></p>
    <?php endif; ?>
</div>

<!-- Yarışma Sertifikaları -->
<!-- Yarışma Sertifikaları -->
<div class="competition-certificates section">
    <h3><?php _e('Competition Certificates', 'wevoi-competition-manager'); ?></h3>
    <?php
    // Yeni sorgu: wpie_wevoi_certificates tablosundan öğrencinin sertifikalarını çek
    $certificates = $wpdb->get_results($wpdb->prepare(
        "SELECT cert.*, c.competition_name 
         FROM {$wpdb->prefix}wevoi_certificates cert
         LEFT JOIN {$wpdb->prefix}wevoi_competitions c ON cert.competition_code = c.competition_code
         WHERE cert.user_id = %d AND cert.role = 'student'
         ORDER BY cert.issue_date DESC",
        $student_id
    ));

    if ($certificates): ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php _e('Competition', 'wevoi-competition-manager'); ?></th>
                    <th><?php _e('Certificate', 'wevoi-competition-manager'); ?></th>
                    <th><?php _e('Issue Date', 'wevoi-competition-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($certificates as $cert): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($cert->competition_name); ?></strong>
                            <div class="row-actions">
                                <span class="code">Code: <?php echo esc_html($cert->competition_code); ?></span>
                            </div>
                        </td>
                        <td>
                            <?php if ($cert->certificate_link): ?>
                                <a href="<?php echo esc_url($cert->certificate_link); ?>" 
                                   target="_blank" 
                                   class="certificate-link">
                                    <span class="dashicons dashicons-media-document"></span>
                                    <?php _e('View Certificate', 'wevoi-competition-manager'); ?>
                                </a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo date_i18n(get_option('date_format'), strtotime($cert->issue_date)); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="no-items"><?php _e('No certificates found.', 'wevoi-competition-manager'); ?></p>
    <?php endif; ?>
</div>
<style>
/* Genel stil */
.student-details-wrapper {
    max-width: 100%;
    padding: 0; /* Dış padding'i kaldır */
}

/* Bölüm stilleri */
.section {
    background: #fff;
    border: 1px solid #e2e4e7;
    border-radius: 6px;
    margin-bottom: 25px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.section h3 {
    margin: 0;
    padding: 15px 20px;
    background: #f0f0f1;
    border-bottom: 1px solid #e2e4e7;
    border-radius: 6px 6px 0 0;
    font-size: 14px;
    font-weight: 600;
    color: #1d2327;
}

/* Tablo stilleri */
.student-details-wrapper table {
    border-spacing: 0;
    width: 100%;
    border: none;
    margin: 0; /* Tablo margin'lerini kaldır */
}

.student-details-wrapper th,
.student-details-wrapper td {
    padding: 12px 20px;
    border-bottom: 1px solid #f0f0f1;
}

/* Badge stilleri */
.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 500;
    text-align: center;
}

/* Status badge renkleri */
.status-paid {
    background: #e8f5e9;
    color: #1b5e20;
}

.status-pending {
    background: #fff3e0;
    color: #e65100;
}

.status-failed {
    background: #ffebee;
    color: #c62828;
}

.status-free {
    background: #e3f2fd;
    color: #0d47a1;
}

/* Madalya badge stilleri */
.medal-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 600;
    text-align: center;
}

.medal-badge .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.medal-gold {
    background: linear-gradient(135deg, #fff4d3, #ffd700);
    color: #856404;
    border: 1px solid #ffd700;
}

.medal-gold .dashicons {
    color: #ffd700;
    text-shadow: 1px 1px 1px rgba(0,0,0,0.2);
}

.medal-silver {
    background: linear-gradient(135deg, #f8f9fa, #c0c0c0);
    color: #4a4a4a;
    border: 1px solid #c0c0c0;
}

.medal-silver .dashicons {
    color: #808080;
}

.medal-bronze {
    background: linear-gradient(135deg, #fff0e8, #cd7f32);
    color: #7a4012;
    border: 1px solid #cd7f32;
}

.medal-bronze .dashicons {
    color: #cd7f32;
}

.medal-success {
    background: linear-gradient(135deg, #e8f5e9, #81c784);
    color: #1b5e20;
    border: 1px solid #81c784;
}

.medal-success .dashicons {
    color: #2e7d32;
}



/* Link stilleri */
.certificate-link, 
.youtube-link {
    display: inline-flex;
    align-items: center;
    text-decoration: none;
    padding: 6px 12px;
    border-radius: 4px;
    color: #2271b1;
    background: #f0f6fc;
    border: 1px solid #c5d9ed;
    transition: all 0.2s ease;
    margin: 0 5px;
    font-size: 12px;
}

.certificate-link:hover, 
.youtube-link:hover {
    background: #2271b1;
    color: #fff;
    border-color: #2271b1;
}

.youtube-link .dashicons,
.certificate-link .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
    margin-left: 5px;
}

/* Diğer stiller */
.row-actions {
    color: #666;
    font-size: 12px;
    margin-top: 4px;
}

.no-items {
    padding: 15px 20px;
    background: #f8f9fa;
    border: 1px solid #ddd;
    border-radius: 4px;
    color: #666;
    margin: 15px 20px;
}

/* Competition Results özellikleri */
.competition-results td {
    vertical-align: middle;
}

/* Summit & Prize bilgileri için özel stil */
td div strong {
    color: #1d2327;
    margin-right: 5px;
}

/* Status badge'ler için ek stiller */
.status-not-paid {
    background: #ffe8e8;
    color: #d32f2f;
}

/* Rank gösterimi için özel stil */
td:nth-child(3) {
    font-weight: 600;
    font-size: 14px;
}
</style>