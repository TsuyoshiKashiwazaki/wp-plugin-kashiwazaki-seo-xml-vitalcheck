<?php
namespace Kashiwazaki\XmlVitalCheck\Repository;

if (!defined('ABSPATH')) exit;

class OptionsRepository {
    private string $optionKey = 'kashiwazaki_xml_vitalcheck_urls';
    private string $cronEnabledKey = 'kashiwazaki_xml_vitalcheck_cron_enabled';
    private string $cronTimeKey = 'kashiwazaki_xml_vitalcheck_cron_time';
    private string $notificationEmailKey = 'kashiwazaki_xml_vitalcheck_notification_email';

    public function getUrls(): array {
        $saved = get_option($this->optionKey, []);
        if (!is_array($saved)) $saved = [];
        return array_values(array_filter(array_map('trim', $saved)));
    }

    public function saveUrls(array $urls): void {
        update_option($this->optionKey, array_values($urls));
    }

    public function getCronEnabled(): bool {
        return (bool) get_option($this->cronEnabledKey, false);
    }

    public function setCronEnabled(bool $enabled): void {
        update_option($this->cronEnabledKey, $enabled ? 1 : 0);
    }

    public function getCronTime(): string {
        $time = (string) get_option($this->cronTimeKey, '03:00');
        return preg_match('/^\d{2}:\d{2}$/', $time) ? $time : '03:00';
    }

    public function setCronTime(string $time): void {
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) return;
        update_option($this->cronTimeKey, $time);
    }

    public function getNotificationEmail(): string {
        return (string) get_option($this->notificationEmailKey, '');
    }

    public function setNotificationEmail(string $email): void {
        if (empty($email) || filter_var($email, FILTER_VALIDATE_EMAIL)) {
            update_option($this->notificationEmailKey, $email);
        }
    }


}
