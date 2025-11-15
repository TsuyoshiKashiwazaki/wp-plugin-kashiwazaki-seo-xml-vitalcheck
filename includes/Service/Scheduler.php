<?php
namespace Kashiwazaki\XmlVitalCheck\Service;

use Kashiwazaki\XmlVitalCheck\Repository\OptionsRepository;

if (!defined('ABSPATH')) exit;

class Scheduler {
    public static function clearSchedule(): void {
        $timestamp = wp_next_scheduled(KASHIWAZAKI_XML_VITALCHECK_CRON_HOOK);
        if ($timestamp) wp_unschedule_event($timestamp, KASHIWAZAKI_XML_VITALCHECK_CRON_HOOK);
    }

    public static function maybeScheduleFromOptions(): void {
        $repo = new OptionsRepository();
        $enabled = $repo->getCronEnabled();
        $time = $repo->getCronTime();
        self::clearSchedule();
        if (!$enabled) return;
        $next = self::nextTimestampFromTime($time);
        wp_schedule_event($next, 'daily', KASHIWAZAKI_XML_VITALCHECK_CRON_HOOK);
    }

    public static function nextTimestampFromTime(string $hhmm): int {
        [$h, $m] = array_map('intval', explode(':', $hhmm));
        $tzTokyo = new \DateTimeZone('Asia/Tokyo');
        $tzUtc = new \DateTimeZone('UTC');
        $nowTokyo = new \DateTime('now', $tzTokyo);
        $targetTokyo = (clone $nowTokyo)->setTime($h, $m, 0);
        if ($targetTokyo <= $nowTokyo) {
            $targetTokyo->modify('+1 day');
        }
        $targetUtc = (clone $targetTokyo)->setTimezone($tzUtc);
        return $targetUtc->getTimestamp();
    }

    public static function run(): void {
        $repo = new OptionsRepository();
        $urls = $repo->getUrls();
        $analyzer = new XmlAnalyzer();
        $results = [];
        foreach ($urls as $i => $url) {
            $rawResult = $analyzer->analyzeUrl($url);
            // XmlAnalyzerの戻り値を正しい形式に変換
            $results[$i] = [
                'success' => $rawResult['ok'],
                'type' => $rawResult['format_type'] ?? 'Unknown',
                'item_count' => $rawResult['count'] ?? 0,
                'version' => $rawResult['xml_version'] ?? '',
                'error' => $rawResult['ok'] ? null : ($rawResult['message'] ?? '不明なエラー')
            ];
        }
        $tzTokyo = new \DateTimeZone('Asia/Tokyo');
        $nowTokyo = new \DateTime('now', $tzTokyo);
        update_option('kashiwazaki_xml_vitalcheck_last_run', [
            'time' => $nowTokyo->format('Y-m-d H:i:s'),
            'results' => $results,
        ]);
        
        // メール送信処理
        $notificationEmail = $repo->getNotificationEmail();
        if (!empty($notificationEmail)) {
            self::sendNotificationEmail($notificationEmail, $urls, $results, $nowTokyo);
        }
    }
    
    private static function sendNotificationEmail(string $to, array $urls, array $results, \DateTime $checkTime): void {
        $subject = 'XML VitalCheck 定期チェック結果 - ' . $checkTime->format('Y年m月d日 H:i');
        
        $message = "XML VitalCheck 定期チェック結果\n";
        $message .= "=====================================\n";
        $message .= "チェック日時: " . $checkTime->format('Y年m月d日 H:i:s') . "\n\n";
        
        $hasErrors = false;
        
        foreach ($urls as $i => $url) {
            $result = $results[$i] ?? null;
            $message .= "【" . ($i + 1) . "】 " . $url . "\n";
            
            if ($result && $result['success']) {
                $message .= "  状態: ✓ 正常\n";
                $message .= "  タイプ: " . $result['type'] . "\n";
                $message .= "  件数: " . number_format($result['item_count']) . " 件\n";
                if (!empty($result['version'])) {
                    $message .= "  バージョン: " . $result['version'] . "\n";
                }
            } else {
                $hasErrors = true;
                $message .= "  状態: ✗ エラー\n";
                $errorMsg = $result['error'] ?? '不明なエラー';
                $message .= "  エラー: " . $errorMsg . "\n";
            }
            $message .= "\n";
        }
        
        if ($hasErrors) {
            $message .= "⚠ エラーが検出されました。確認が必要です。\n\n";
        } else {
            $message .= "✓ すべてのXMLファイルは正常です。\n\n";
        }
        
        $message .= "-------------------------------------\n";
        $message .= "Kashiwazaki SEO XML VitalCheck\n";
        $message .= site_url('/wp-admin/admin.php?page=kashiwazaki-xml-vitalcheck');
        
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        
        wp_mail($to, $subject, $message, $headers);
    }
}


