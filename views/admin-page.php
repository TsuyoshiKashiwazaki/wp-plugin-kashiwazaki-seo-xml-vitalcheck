<?php if (!defined('ABSPATH')) { exit; }
$urls = $urls ?? [];
$missingUrls = empty($urls);
$cronEnabled = $cronEnabled ?? false;
$cronTime = $cronTime ?? '03:00';
$tzTokyo = new DateTimeZone('Asia/Tokyo');
$nowTokyo = new DateTime('now', $tzTokyo);
$lastRun = get_option('kashiwazaki_xml_vitalcheck_last_run');
$lastRunText = is_array($lastRun) && !empty($lastRun['time']) ? $lastRun['time'] : '';
?>
<div class="wrap kashiwazaki-xml-vitalcheck">

    <h1>Kashiwazaki SEO XML VitalCheck</h1>

    <?php if ($missingUrls): ?>
        <div class="notice notice-warning"><p>URLが１つも記載されていません。</p></div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field('kashiwazaki_xml_vitalcheck_save'); ?>
        <h2>URLリスト</h2>
        <p>XMLサイトマップやRSSフィードのURLを1行に1つずつ入力してください</p>
        <p><small>例: https://yoursite.com/sitemap.xml, https://yoursite.com/feed/ など</small></p>
        <textarea name="kashiwazaki_xml_vitalcheck_urls" rows="8" style="width:100%;font-family:monospace;"><?php echo esc_textarea(implode("\n", $urls)); ?></textarea>
        <p>
            <button type="button" class="button" id="run-xml-analysis">XMLを解析</button>
        </p>

            <h2>スケジュール</h2>
        <label><input type="checkbox" name="kashiwazaki_xml_vitalcheck_cron_enabled" value="1" <?php if (!empty($cronEnabled)) echo 'checked'; ?>> 定期実行を有効化</label>
        <p>
            実行時刻 (日本時間): <input type="time" name="kashiwazaki_xml_vitalcheck_cron_time" value="<?php echo esc_attr($cronTime ?? '03:00'); ?>" step="60">
        </p>
        <p>
            通知先メールアドレス: <input type="email" name="kashiwazaki_xml_vitalcheck_notification_email" value="<?php echo esc_attr($notificationEmail ?? ''); ?>" placeholder="example@domain.com" style="width: 300px;">
            <br><small>定期実行時にチェック結果をメール送信します（空欄の場合は送信しません）</small>
        </p>
        <p>
            現在時刻: <code><?php echo esc_html($nowTokyo->format('Y-m-d H:i:s')); ?></code><br>
            前回定期実行: <code><?php echo esc_html($lastRunText ?: '—'); ?></code>
        </p>
        <p>
            <button type="submit" class="button button-primary" name="kashiwazaki_xml_vitalcheck_save" value="1">全設定を保存</button>
        </p>
    </form>

    <!-- 解析結果（AJAX表示） -->
    <div id="xml-analysis-results" style="display:none;">
        <h2>解析結果</h2>
        <div id="xml-analysis-loading" style="display:none;">
            <p style="text-align:center; padding:20px;"><span class="kashiwazaki-inline-spinner"></span>解析中...</p>
        </div>
        <div id="xml-analysis-content"></div>
    </div>

    <!-- 到達性チェック結果（AJAX表示） -->
    <div id="kashiwazaki-reach-results" style="display:none;">
        <h2 id="kashiwazaki-reach-title">到達性チェック結果</h2>
        <div id="kashiwazaki-reach-loading" style="display:none;">
            <p style="text-align:center; padding:20px;"><span class="kashiwazaki-inline-spinner"></span>チェック中...</p>
        </div>
        <div id="kashiwazaki-reach-content"></div>
    </div>
</div>
