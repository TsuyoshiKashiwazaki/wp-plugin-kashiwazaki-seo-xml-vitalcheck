<?php
namespace Kashiwazaki\XmlVitalCheck\Admin;

use Kashiwazaki\XmlVitalCheck\Admin\SettingsPage;

if (!defined('ABSPATH')) exit;

class Menu {
    private SettingsPage $settingsPage;

    public function __construct() {
        $this->settingsPage = new SettingsPage();
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_init', [$this->settingsPage, 'handleActions']);
    }

    public function registerMenu(): void {
        // メニューを確実に表示するため、条件をチェック
        if (!is_user_logged_in()) {
            return; // ログインしていない場合は何もしない
        }

        // 権限を動的に決定
        $capability = 'read';
        if (current_user_can('manage_options')) {
            $capability = 'manage_options';
        } elseif (current_user_can('edit_posts')) {
            $capability = 'edit_posts';
        }

        $hook = add_menu_page(
            'Kashiwazaki SEO XML VitalCheck',
            'Kashiwazaki SEO XML VitalCheck',
            $capability,
            'kashiwazaki-xml-vitalcheck',
            [$this->settingsPage, 'render'],
            'dashicons-media-spreadsheet',
            81
        );

        if ($hook) {
            add_action('load-' . $hook, [$this, 'enqueueAssets']);
        }
    }

    public function grantTempCapability($capabilities, $cap, $args): array {
        // プラグインページへのアクセス時のみ権限を付与
        if (isset($_GET['page']) && $_GET['page'] === 'kashiwazaki-xml-vitalcheck') {
            $capabilities['read'] = true;
            $capabilities['manage_options'] = true;
        }
        return $capabilities;
    }

    public function enqueueAssets(): void {
        $css_url = KASHIWAZAKI_XML_VITALCHECK_URL . 'assets/css/admin.css';
        $js_url = KASHIWAZAKI_XML_VITALCHECK_URL . 'assets/js/admin.js';
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('kashiwazaki_xml_reach_check');

        $version = time() . '_' . rand(1000, 9999);

        wp_enqueue_style(
            'kashiwazaki-xml-vitalcheck-admin',
            $css_url . '?v=' . $version,
            [],
            $version
        );
        wp_enqueue_script(
            'kashiwazaki-xml-vitalcheck-admin',
            $js_url . '?v=' . $version,
            ['jquery'],
            $version,
            true
        );

        $ajax_data = [
            'ajaxurl' => $ajax_url,
            'nonce' => $nonce
        ];

        wp_localize_script('kashiwazaki-xml-vitalcheck-admin', 'kashiwazakiAjax', $ajax_data);
    }
}
