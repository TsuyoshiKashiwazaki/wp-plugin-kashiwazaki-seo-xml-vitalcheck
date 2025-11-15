<?php
/*
Plugin Name: Kashiwazaki SEO XML VitalCheck
Plugin URI: https://www.tsuyoshikashiwazaki.jp
Description: 複数のXMLファイルURLをチェックし、XMLバージョン、フォーマットタイプ、件数を可視化します。SEO対策研究室による開発。
Version: 1.0.0
Author: 柏崎剛 (Tsuyoshi Kashiwazaki)
Author URI: https://www.tsuyoshikashiwazaki.jp/profile/
Text Domain: kashiwazaki-xml-vitalcheck
Domain Path: /languages
*/

if (!defined('ABSPATH')) exit;

define('KASHIWAZAKI_XML_VITALCHECK_PATH', plugin_dir_path(__FILE__));
define('KASHIWAZAKI_XML_VITALCHECK_URL', plugin_dir_url(__FILE__));
define('KASHIWAZAKI_XML_VITALCHECK_CRON_HOOK', 'kashiwazaki_xml_vitalcheck_cron');

spl_autoload_register(function ($class) {
    $prefix = 'Kashiwazaki\\XmlVitalCheck\\';
    $base_dir = __DIR__ . '/includes/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

// プラグインの初期化
add_action('plugins_loaded', function() {
    if (is_admin()) {
        new \Kashiwazaki\XmlVitalCheck\Admin\Menu();
    }
});


// XML解析用AJAXハンドラー
add_action('wp_ajax_kashiwazaki_xml_analysis', function() {
    try {
        $settingsPage = new \Kashiwazaki\XmlVitalCheck\Admin\SettingsPage();
        $settingsPage->ajaxXmlAnalysis();
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'ハンドラー例外: ' . $e->getMessage()]);
    }
});

// 到達性チェック用AJAXハンドラー
add_action('wp_ajax_kashiwazaki_xml_reach_check', function() {
    try {
        $settingsPage = new \Kashiwazaki\XmlVitalCheck\Admin\SettingsPage();
        $settingsPage->ajaxReachCheck();
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'ハンドラー例外: ' . $e->getMessage()]);
    }
});

// 緊急停止用AJAXハンドラー
add_action('wp_ajax_kashiwazaki_emergency_stop', function() {
    try {
        $settingsPage = new \Kashiwazaki\XmlVitalCheck\Admin\SettingsPage();
        $settingsPage->ajaxEmergencyStop();
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'ハンドラー例外: ' . $e->getMessage()]);
    }
});

add_action(KASHIWAZAKI_XML_VITALCHECK_CRON_HOOK, ['\\Kashiwazaki\\XmlVitalCheck\\Service\\Scheduler', 'run']);

function kashiwazaki_xml_vitalcheck_action_links($links) {
    if (is_user_logged_in()) {
        $url = admin_url('admin.php?page=kashiwazaki-xml-vitalcheck');
        $links[] = '<a href="' . esc_url($url) . '">設定</a>';
    }
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'kashiwazaki_xml_vitalcheck_action_links');

function kashiwazaki_xml_vitalcheck_activate() {
    if (class_exists('Kashiwazaki\\XmlVitalCheck\\Service\\Scheduler')) {
        \Kashiwazaki\XmlVitalCheck\Service\Scheduler::maybeScheduleFromOptions();
    }
}
register_activation_hook(__FILE__, 'kashiwazaki_xml_vitalcheck_activate');

function kashiwazaki_xml_vitalcheck_deactivate() {
    if (class_exists('Kashiwazaki\\XmlVitalCheck\\Service\\Scheduler')) {
        \Kashiwazaki\XmlVitalCheck\Service\Scheduler::clearSchedule();
    }
}
register_deactivation_hook(__FILE__, 'kashiwazaki_xml_vitalcheck_deactivate');
