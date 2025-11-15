<?php
namespace Kashiwazaki\XmlVitalCheck\Admin;

use Kashiwazaki\XmlVitalCheck\Repository\OptionsRepository;
use Kashiwazaki\XmlVitalCheck\Service\XmlAnalyzer;
use Kashiwazaki\XmlVitalCheck\Service\Scheduler;

if (!defined('ABSPATH')) exit;

class SettingsPage {
    private OptionsRepository $optionsRepository;
    private XmlAnalyzer $xmlAnalyzer;

    public function __construct() {
        $this->optionsRepository = new OptionsRepository();
        $this->xmlAnalyzer = new XmlAnalyzer();
    }

    public function handleActions(): void {
        if (!is_user_logged_in()) return;
        if (isset($_POST['kashiwazaki_xml_vitalcheck_save'])) {
            check_admin_referer('kashiwazaki_xml_vitalcheck_save');
            $urlsText = isset($_POST['kashiwazaki_xml_vitalcheck_urls']) ? wp_unslash($_POST['kashiwazaki_xml_vitalcheck_urls']) : '';
            $urls = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $urlsText)));
            $urls = array_map('esc_url_raw', $urls);
            $this->optionsRepository->saveUrls($urls);
            $cronEnabled = !empty($_POST['kashiwazaki_xml_vitalcheck_cron_enabled']);
            $cronTime = isset($_POST['kashiwazaki_xml_vitalcheck_cron_time']) ? sanitize_text_field(wp_unslash($_POST['kashiwazaki_xml_vitalcheck_cron_time'])) : '03:00';
            $notificationEmail = isset($_POST['kashiwazaki_xml_vitalcheck_notification_email']) ? sanitize_email(wp_unslash($_POST['kashiwazaki_xml_vitalcheck_notification_email'])) : '';
            $this->optionsRepository->setCronEnabled($cronEnabled);
            $this->optionsRepository->setCronTime($cronTime);
            $this->optionsRepository->setNotificationEmail($notificationEmail);
            Scheduler::maybeScheduleFromOptions();
            add_settings_error('kashiwazaki_xml_vitalcheck', 'saved', '保存しました。', 'updated');
        }
    }

    public function render(): void {
        if (!is_user_logged_in()) {
            echo '<div class="wrap">';
            echo '<h1>Kashiwazaki SEO XML VitalCheck</h1>';
            echo '<div style="background: #f8d7da; padding: 15px; margin: 20px 0; border-left: 4px solid #dc3545;">';
            echo '<h3>❌ ログインが必要です</h3>';
            echo '<p>このページにアクセスするには、WordPressにログインしてください。</p>';
            echo '<p><a href="' . wp_login_url(admin_url('admin.php?page=kashiwazaki-xml-vitalcheck')) . '" class="button button-primary">ログインページへ</a></p>';
            echo '</div>';
            echo '</div>';
            return;
        }

        $urls = $this->optionsRepository->getUrls();
        $cronEnabled = $this->optionsRepository->getCronEnabled();
        $cronTime = $this->optionsRepository->getCronTime();
        $notificationEmail = $this->optionsRepository->getNotificationEmail();
        settings_errors('kashiwazaki_xml_vitalcheck');
        include KASHIWAZAKI_XML_VITALCHECK_PATH . 'views/admin-page.php';
    }

    public function ajaxXmlAnalysis(): void {
        // タイムアウト対策：最大実行時間を60秒に設定
        set_time_limit(60);

        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'ログインが必要です']);
                return;
            }

            $urls = $this->optionsRepository->getUrls();

            if (empty($urls)) {
                wp_send_json_error(['message' => 'URLが登録されていません']);
                return;
            }

            $results = [];
            foreach ($urls as $index => $url) {
                // 無限ループ防止：処理開始時間をチェック
                if (defined('WP_START_TIMESTAMP') && (microtime(true) - WP_START_TIMESTAMP) > 50) {
                    wp_send_json_error(['message' => 'タイムアウト：処理時間が制限を超えました']);
                    return;
                }

                $results[$index] = $this->xmlAnalyzer->analyzeUrl($url, false);
            }

            wp_send_json_success([
                'total' => count($results),
                'results' => $results,
                'urls' => $urls
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => 'システムエラー: ' . $e->getMessage()]);
        }
    }

    public function ajaxReachCheck(): void {
        try {
            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'ログインが必要です']);
                return;
            }

            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'kashiwazaki_xml_reach_check')) {
                wp_send_json_error(['message' => 'セキュリティチェックに失敗しました']);
                return;
            }

            $xmlIndex = isset($_POST['xml_index']) ? (int)$_POST['xml_index'] : -1;
            $chunkIndex = isset($_POST['chunk_index']) ? (int)$_POST['chunk_index'] : 0;
            $chunkSize = 50;

            $urls = $this->optionsRepository->getUrls();

            if (!isset($urls[$xmlIndex])) {
                wp_send_json_error(['message' => '無効なXMLインデックスです (Index: ' . $xmlIndex . ')']);
                return;
            }

            $xmlUrl = $urls[$xmlIndex];

            if ($chunkIndex === 0) {
                $urlList = $this->xmlAnalyzer->extractUrlsForIndex($xmlUrl);

                if (!empty($urlList['error'])) {
                    wp_send_json_error(['message' => $urlList['error']]);
                    return;
                }

                $allItems = $urlList['items'] ?? [];
                update_option('kashiwazaki_temp_urls_' . $xmlIndex, $allItems, false);
                update_option('kashiwazaki_temp_format_' . $xmlIndex, $urlList['format_type'] ?? '', false);
            } else {
                $allItems = get_option('kashiwazaki_temp_urls_' . $xmlIndex, []);
                if (empty($allItems)) {
                    wp_send_json_error(['message' => 'セッションデータが見つかりません。最初からやり直してください。']);
                    return;
                }
            }

            $totalUrls = count($allItems);
            $startIndex = $chunkIndex * $chunkSize;
            $endIndex = min($startIndex + $chunkSize, $totalUrls);
            $currentChunk = array_slice($allItems, $startIndex, $chunkSize);

            $results = [];
            $tz = new \DateTimeZone('Asia/Tokyo');

            foreach ($currentChunk as $u) {
                $sanitized_u = $this->sanitizeUrl($u);
                $checkedAt = new \DateTime('now', $tz);

                $result = [
                    'url' => $u,
                    'sanitized_url' => $sanitized_u,
                    'time' => $checkedAt->format('Y-m-d H:i:s')
                ];

                $curlResult = $this->checkUrlWithCurl($sanitized_u, $u);

                $result['code'] = $curlResult['code'];
                $result['status'] = $curlResult['status'];
                if (!empty($curlResult['message'])) {
                    $result['message'] = $curlResult['message'];
                }

                $results[] = $result;
            }

            $isLastChunk = ($endIndex >= $totalUrls);
            if ($isLastChunk) {
                delete_option('kashiwazaki_temp_urls_' . $xmlIndex);
                delete_option('kashiwazaki_temp_format_' . $xmlIndex);
            }

            $formatType = get_option('kashiwazaki_temp_format_' . $xmlIndex, '');

            $responseData = [
                'total' => $totalUrls,
                'checked' => count($results),
                'format_type' => $formatType,
                'results' => $results,
                'chunk_index' => $chunkIndex,
                'start_index' => $startIndex,
                'end_index' => $endIndex,
                'is_last_chunk' => $isLastChunk,
                'has_more' => !$isLastChunk
            ];

            wp_send_json_success($responseData);

        } catch (Exception $e) {
            wp_send_json_error(['message' => 'システムエラー: ' . $e->getMessage()]);
        }
    }

    private function sanitizeUrl(string $url): string {
        $url = trim($url);
        if ($url === '') return '';
        $url = ltrim($url, '@');

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            if (!preg_match('/^https?:\/\//', $url)) {
                $url = 'https://' . $url;
            }
        }

        return $url;
    }

    private function checkUrlWithCurl(string $processedUrl, string $originalUrl): array {
        $googleBotUA = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

        $result = $this->performCurlRequest($processedUrl, $googleBotUA);

        if (($result['code'] >= 400 || $result['code'] == 0) && $processedUrl !== $originalUrl) {
            $fallbackResult = $this->performCurlRequest($originalUrl, $googleBotUA);

            if ($fallbackResult['code'] < 400 && $fallbackResult['code'] > 0) {
                return $fallbackResult;
            }
        }

        return $result;
    }

    private function performCurlRequest(string $url, string $userAgent): array {
        if (!function_exists('curl_init')) {
            return [
                'code' => 0,
                'status' => 'error',
                'message' => 'cURL拡張が利用できません'
            ];
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: ja,en-US;q=0.9,en;q=0.8',
                'Accept-Encoding: gzip, deflate',
                'Cache-Control: no-cache',
                'Connection: close'
            ],
            CURLOPT_ENCODING => '',
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        curl_close($ch);

        if ($curlErrno !== 0) {
            return [
                'code' => 0,
                'status' => 'error',
                'message' => 'cURLエラー: ' . $curlError
            ];
        }

        if ($httpCode >= 200 && $httpCode < 400) {
            $status = 'ok';
        } elseif ($httpCode >= 400 && $httpCode < 500) {
            $status = 'warning';
        } else {
            $status = 'error';
        }

        return [
            'code' => $httpCode,
            'status' => $status,
            'message' => $httpCode == 0 ? 'リクエスト失敗' : ''
        ];
    }

    public function ajaxEmergencyStop(): void {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'ログインが必要です']);
            return;
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'kashiwazaki_xml_reach_check')) {
            wp_send_json_error(['message' => 'セキュリティチェックに失敗しました']);
            return;
        }

        try {
            global $wpdb;
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                'kashiwazaki_temp_%'
            ));

            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }

            update_option('kashiwazaki_emergency_stop', time(), false);

            wp_send_json_success([
                'message' => '緊急停止を実行しました。実行中の処理を停止し、セッションデータをクリーンアップしました。',
                'timestamp' => time()
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => '緊急停止中にエラーが発生: ' . $e->getMessage()]);
        }
    }

    public function ajaxKillProcesses(): void {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'ログインが必要です']);
            return;
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'kashiwazaki_xml_reach_check')) {
            wp_send_json_error(['message' => 'セキュリティチェックに失敗しました']);
            return;
        }

        try {
            $killedProcesses = [];

            if (function_exists('gc_collect_cycles')) {
                $cycles = gc_collect_cycles();
                $killedProcesses[] = "Garbage collected {$cycles} cycles";
            }

            global $wpdb;
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                'kashiwazaki_temp_%',
                'kashiwazaki_emergency_%'
            ));

            if ($deleted) {
                $killedProcesses[] = "Deleted {$deleted} temporary options";
            }

            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
                $killedProcesses[] = 'WordPress cache flushed';
            }

            if (function_exists('opcache_reset')) {
                opcache_reset();
                $killedProcesses[] = 'OPcache reset';
            }

            wp_send_json_success([
                'message' => '全プロセス強制終了を実行しました。',
                'actions' => $killedProcesses,
                'timestamp' => time()
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => 'プロセス強制終了中にエラーが発生: ' . $e->getMessage()]);
        }
    }
}
