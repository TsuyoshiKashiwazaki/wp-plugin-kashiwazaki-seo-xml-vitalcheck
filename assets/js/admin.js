var jsVersion = new Date().getTime();


// URLからパス+ファイル名を抽出するヘルパー関数
function getDisplayName(url) {
    try {
        var urlObj = new URL(url);
        var pathname = urlObj.pathname;

        // パスの最後の部分（ファイル名）を取得
        var pathParts = pathname.split('/').filter(function (part) { return part.length > 0; });

        if (pathParts.length === 0) {
            return urlObj.hostname; // ルートの場合はホスト名
        } else if (pathParts.length === 1) {
            return pathParts[0]; // ファイル名のみ
        } else {
            // 最後の2つの部分を表示（例: "blog/sitemap.xml"）
            return pathParts.slice(-2).join('/');
        }
    } catch (e) {
        // URL解析に失敗した場合は元のURLをそのまま使用
        return url;
    }
}

jQuery(document).ready(function ($) {

    // 実行中AJAX処理の管理
    var activeAjaxRequests = [];
    var isAnyProcessRunning = false;

    // 実行中処理を追跡
    function addActiveRequest(xhr) {
        activeAjaxRequests.push(xhr);
        updateInstantStatus();
    }

    function removeActiveRequest(xhr) {
        var index = activeAjaxRequests.indexOf(xhr);
        if (index > -1) {
            activeAjaxRequests.splice(index, 1);
        }
        updateInstantStatus();
    }

    function updateInstantStatus() {
        var $status = $('#instant-status');
        if (activeAjaxRequests.length > 0) {
            $status.text('実行中: ' + activeAjaxRequests.length + '件のリクエスト');
            isAnyProcessRunning = true;
        } else {
            $status.text('待機中');
            isAnyProcessRunning = false;
        }
    }

    // 即座停止ボタンの機能
    $('#instant-stop-btn').on('click', function (e) {
        e.preventDefault();

        var $btn = $(this);
        var $status = $('#instant-status');

        if (activeAjaxRequests.length === 0 && !isAnyProcessRunning) {
            alert('⚠️ 現在実行中のプロセスはありません。');
            return;
        }

        $btn.prop('disabled', true).text('停止中...');
        $status.text('全処理を強制停止中...');

        // すべてのアクティブなAJAXリクエストを即座に中断
        var stoppedCount = 0;
        activeAjaxRequests.forEach(function (xhr) {
            if (xhr && xhr.readyState !== 4) {
                xhr.abort();
                stoppedCount++;
            }
        });

        // リクエスト配列をクリア
        activeAjaxRequests = [];

        // UI要素をリセット
        $('#run-xml-analysis').prop('disabled', false).text('XMLを解析');
        $('.kashiwazaki-reach-check').prop('disabled', false).text('到達性チェック');
        $('#xml-analysis-loading, #kashiwazaki-reach-loading').hide();
        $('#emergency-control-panel').hide();

        // 進捗表示を停止
        $('#reach-progress-container h3').html('❌ 処理が中断されました');

        setTimeout(function () {
            $btn.prop('disabled', false).text('⛔ 即座停止');
            $status.text('停止完了: ' + stoppedCount + '件のリクエストを中断');

            // 緊急停止APIも呼び出し（セッションデータクリーンアップ）
            if (typeof kashiwazakiAjax !== 'undefined') {
                $.ajax({
                    url: kashiwazakiAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'kashiwazaki_emergency_stop',
                        nonce: kashiwazakiAjax.nonce
                    },
                    timeout: 5000,
                    success: function (response) {
                    },
                    error: function () {
                    }
                });
            }

            setTimeout(function () {
                updateInstantStatus();
            }, 2000);
        }, 1000);
    });

    // 強制リロードボタン
    $('#force-reload-btn').on('click', function (e) {
        e.preventDefault();
        if (confirm('🔄 ページを強制リロードしますか？\n実行中の処理は全て中断されます。')) {
            // 強制的にキャッシュを無視してリロード
            window.location.reload(true);
        }
    });

    // 基本要素の存在確認
    var $analysisButton = $('#run-xml-analysis');

    if ($analysisButton.length === 0) {
        return;
    }

    // AJAX設定の確認
    if (typeof kashiwazakiAjax === 'undefined') {
        return;
    }

    // XML解析ボタンのイベント設定
    $analysisButton.on('click', function (e) {
        e.preventDefault();

        var $button = $(this);
        var $resultsDiv = $('#xml-analysis-results');
        var $loadingEl = $('#xml-analysis-loading');
        var $contentEl = $('#xml-analysis-content');


        // ボタンを無効化
        $button.prop('disabled', true).html('<span class="kashiwazaki-button-spinner"></span>解析中...');

        // ローディング表示
        $resultsDiv.show();
        $loadingEl.show();
        $contentEl.empty();

        var xhr = $.ajax({
            url: kashiwazakiAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'kashiwazaki_xml_analysis',
                _ajax_nonce: kashiwazakiAjax.nonce
            },
            timeout: 180000, // 180秒（3分）
            beforeSend: function (xhr) {
                addActiveRequest(xhr);
            },
            success: function (response) {

                $loadingEl.hide();
                $button.prop('disabled', false).text('XMLを解析');

                if (response.success && response.data) {
                    var html = '<p>✅ 解析完了: ' + response.data.total + '件</p>';
                    html += '<table class="widefat fixed striped">';
                    html += '<thead><tr><th>#</th><th>XML</th><th>XMLバージョン</th><th>フォーマットタイプ</th><th>件数</th><th>状態</th><th>操作</th></tr></thead>';
                    html += '<tbody>';

                    $.each(response.data.results, function (i, result) {
                        var xmlUrl = response.data.urls[i] || '';
                        var status = '';

                        if (result.message) {
                            status = '<span class="status-badge error">失敗</span>';
                        } else if (result.empty) {
                            status = '<span class="status-badge warning">空</span>';
                        } else {
                            status = '<span class="status-badge ok">OK</span>';
                        }

                        // URLからパス+ファイル名を抽出（グローバル関数を使用）

                        var displayName = getDisplayName(xmlUrl);

                        html += '<tr>';
                        html += '<td>' + (i + 1) + '</td>';
                        html += '<td><a href="' + xmlUrl + '" target="_blank" title="' + xmlUrl + '">' + displayName + '</a></td>';
                        html += '<td>' + (result.xml_version || '') + '</td>';
                        html += '<td>' + (result.format_type || '') + '</td>';
                        html += '<td>' + (result.count || 0) + '</td>';
                        html += '<td>' + status + '</td>';
                        html += '<td><button class="button kashiwazaki-reach-check" data-xml-index="' + i + '">到達性チェック</button></td>';
                        html += '</tr>';
                    });

                    html += '</tbody></table>';
                    $contentEl.html(html);

                    // メインテーブルにもソート機能を適用
                    makeSortable($contentEl.find('table')[0]);
                } else {
                    var errorMsg = (response.data && response.data.message) || '不明なエラーが発生しました';
                    $contentEl.html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
                }
            },
            error: function (xhr, status, error) {

                $loadingEl.hide();
                $button.prop('disabled', false).text('XMLを解析');
                $contentEl.html('<div class="notice notice-error"><p>通信エラー: ' + error + '</p></div>');
            },
            complete: function (xhr) {
                removeActiveRequest(xhr);
            }
        });
    });

    // 到達性チェックボタン（動的生成用）
    $(document).on('click', '.kashiwazaki-reach-check', function (e) {
        e.preventDefault();

        var xmlIndex = $(this).data('xml-index');
        var $button = $(this);
        var $reachResultsDiv = $('#kashiwazaki-reach-results');
        var $reachLoadingDiv = $('#kashiwazaki-reach-loading');
        var $reachContentDiv = $('#kashiwazaki-reach-content');
        var $reachTitleDiv = $('#kashiwazaki-reach-title');


        // UI更新
        $reachResultsDiv.show();
        $reachLoadingDiv.show();
        $reachContentDiv.empty();
        $reachTitleDiv.text('到達性チェック結果 (XML ' + (xmlIndex + 1) + ')');
        $button.prop('disabled', true).html('<span class="kashiwazaki-button-spinner"></span>チェック中...');

        // 段階的処理（チャンク処理）の開始
        processReachCheckInChunks(xmlIndex, $button, $reachResultsDiv, $reachLoadingDiv, $reachContentDiv, $reachTitleDiv);
    });

    // 段階的処理（チャンク処理）関数
    function processReachCheckInChunks(xmlIndex, $button, $reachResultsDiv, $reachLoadingDiv, $reachContentDiv, $reachTitleDiv) {
        var allResults = [];
        var totalUrls = 0;
        var chunkIndex = 0;
        var $tbody;
        var isTableInitialized = false;

        function processNextChunk() {

            var xhr = $.ajax({
                url: kashiwazakiAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'kashiwazaki_xml_reach_check',
                    xml_index: xmlIndex,
                    chunk_index: chunkIndex,
                    nonce: kashiwazakiAjax.nonce
                },
                timeout: 90000, // チャンク処理なので90秒に短縮
                dataType: 'json',
                beforeSend: function (xhr) {
                    addActiveRequest(xhr);
                },
                success: function (response) {

                    if (response.success) {
                        var data = response.data;

                        // 初回チャンクでテーブル初期化
                        if (!isTableInitialized) {
                            totalUrls = data.total;

                            // 進捗バー付きの基本構造を作成
                            var html = '<div id="reach-progress-container" style="margin-bottom: 20px;">';
                            html += '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">';
                            html += '<h3 style="margin: 0;"><span class="kashiwazaki-inline-spinner"></span>到達性チェック進捗</h3>';
                            html += '<span id="reach-percentage">0%</span>';
                            html += '</div>';
                            html += '<div style="background: #f0f0f0; border-radius: 10px; height: 20px; overflow: hidden; position: relative;">';
                            html += '<div id="reach-progress-bar" style="background: linear-gradient(90deg, #0073aa 0%, #00a0d2 100%); height: 100%; width: 0%; transition: width 0.3s ease;"></div>';
                            html += '<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 12px; font-weight: bold; color: #333;" id="reach-progress-text">0 / ' + totalUrls + '</div>';
                            html += '</div>';
                            html += '</div>';
                            html += '<table class="widefat fixed striped" id="reach-table">';
                            html += '<thead><tr><th>#</th><th>URL</th><th>HTTPステータス</th><th>チェック時刻</th></tr></thead>';
                            html += '<tbody id="reach-tbody"></tbody>';
                            html += '</table>';

                            $reachContentDiv.html(html);
                            $tbody = $('#reach-tbody');
                            $reachLoadingDiv.hide(); // 進捗バーがあるのでローディングは非表示
                            isTableInitialized = true;
                        }

                        // チャンクの結果を配列に追加
                        allResults = allResults.concat(data.results);

                        // テーブルに結果を追加（高速表示）
                        data.results.forEach(function (result, index) {
                            var globalIndex = data.start_index + index;
                            var rowNumber = globalIndex + 1;

                            var statusClass = '';
                            var statusText = '';
                            var statusCode = result.code || 0;
                            var status = result.status || 'unknown';

                            if (status === 'ok') {
                                statusClass = 'ok';
                                statusText = 'OK (' + statusCode + ')';
                            } else if (status === 'warning') {
                                statusClass = 'warning';
                                statusText = '注意 (' + statusCode + ')';
                            } else {
                                statusClass = 'error';
                                statusText = 'エラー (' + statusCode + ')';
                                if (result.message) {
                                    statusText += ' - ' + result.message;
                                }
                            }

                            var rowHtml = '<tr>';
                            rowHtml += '<td>' + rowNumber + '</td>';
                            rowHtml += '<td><a href="' + result.url + '" target="_blank">' + result.url + '</a></td>';
                            rowHtml += '<td><span class="status-badge ' + statusClass + '">' + statusText + '</span></td>';
                            rowHtml += '<td>' + (result.time || '—') + '</td>';
                            rowHtml += '</tr>';

                            $tbody.append(rowHtml);
                        });

                        // 進捗更新
                        var processedCount = allResults.length;
                        var percentage = Math.round((processedCount / totalUrls) * 100);
                        $('#reach-percentage').text(percentage + '%');
                        $('#reach-progress-bar').css('width', percentage + '%');
                        $('#reach-progress-text').text(processedCount + ' / ' + totalUrls);

                        // 次のチャンクがある場合は続行
                        if (data.has_more) {
                            chunkIndex++;
                            setTimeout(processNextChunk, 500); // 500ms後に次のチャンク処理
                        } else {
                            // 全処理完了
                            $('#reach-progress-container h3').html('✅ 到達性チェック完了');
                            $('#reach-percentage').text('100%');
                            $('#reach-progress-bar').css('width', '100%');
                            $('#reach-progress-text').text(totalUrls + ' / ' + totalUrls + ' 完了');

                            // ソート機能を有効化
                            makeSortable(document.getElementById('reach-table'));

                            // ボタンを有効化
                            $button.prop('disabled', false).text('到達性チェック');
                        }
                    } else {
                        $reachLoadingDiv.hide();
                        $button.prop('disabled', false).text('到達性チェック');
                        $reachContentDiv.html('<div class="notice notice-error"><p>❌ エラー: ' + (response.data?.message || '不明なエラー') + '</p></div>');
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {

                    // Cloudflareタイムアウト（524）の場合は次のチャンクを試行
                    if (jqXHR.status === 524 && chunkIndex > 0) {
                        chunkIndex++;
                        setTimeout(processNextChunk, 2000); // 2秒後にリトライ
                    } else {
                        $reachLoadingDiv.hide();
                        $button.prop('disabled', false).text('到達性チェック');
                        $reachContentDiv.html('<div class="notice notice-error"><p>❌ 通信エラー: ' + textStatus + ' - ' + errorThrown + ' (Status: ' + jqXHR.status + ')</p></div>');
                    }
                },
                complete: function (xhr) {
                    removeActiveRequest(xhr);
                }
            });
        }

        // 初回チャンク処理開始
        processNextChunk();
    }

    // テーブルソート機能
    function makeSortable(table) {
        if (!table) return;

        var headers = table.querySelectorAll('thead th');
        headers.forEach(function (header, index) {
            header.style.cursor = 'pointer';
            header.style.userSelect = 'none';
            header.addEventListener('click', function () {
                sortTable(table, index);
            });
        });
    }

    function sortTable(table, columnIndex) {
        var tbody = table.querySelector('tbody');
        var rows = Array.from(tbody.querySelectorAll('tr'));
        var isAscending = table.getAttribute('data-sort-dir') !== 'asc';

        rows.sort(function (a, b) {
            var aValue = a.cells[columnIndex].textContent.trim();
            var bValue = b.cells[columnIndex].textContent.trim();

            // 数値の場合は数値比較
            if (!isNaN(aValue) && !isNaN(bValue)) {
                return isAscending ? aValue - bValue : bValue - aValue;
            }

            // 文字列比較
            if (isAscending) {
                return aValue.localeCompare(bValue);
            } else {
                return bValue.localeCompare(aValue);
            }
        });

        // ソート後の行を再配置
        rows.forEach(function (row) {
            tbody.appendChild(row);
        });

        // ソート方向を記録
        table.setAttribute('data-sort-dir', isAscending ? 'asc' : 'desc');

        // ヘッダーにソート指示を表示
        var headers = table.querySelectorAll('thead th');
        headers.forEach(function (header, index) {
            header.classList.remove('sorted-asc', 'sorted-desc');
            if (index === columnIndex) {
                header.classList.add(isAscending ? 'sorted-asc' : 'sorted-desc');
            }
        });
    }

    // 危険な緊急UI/処理は削除

    // プロセス実行状態の管理
    function setProcessRunning(running) {
        isProcessRunning = running;
        if (running) {
            $emergencyPanel.show();
            $emergencyStatus.text('処理実行中...');
        } else {
            $emergencyPanel.hide();
            $emergencyStatus.text('');
        }
    }

    // 危険な緊急UI/処理は削除

    // 全ログ停止機能は削除（500エラーの原因のため）
});
