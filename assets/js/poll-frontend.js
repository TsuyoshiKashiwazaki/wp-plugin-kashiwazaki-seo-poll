// assets/js/poll-frontend.js
(function () {
    function initKashiwazakiPolls() {

        if (typeof window.kashiwazakiPollAllData === 'undefined') {
            console.warn('kashiwazakiPollAllData object not found. Initializing as empty object.');
            window.kashiwazakiPollAllData = {};
        }

        document.querySelectorAll('.kashiwazaki-poll-block').forEach(pollBlock => {
            const pollIdAttr = pollBlock.getAttribute('data-poll-id');
            if (!pollIdAttr) {
                console.warn('Poll block found without data-poll-id attribute.');
                return;
            }
            const pollId = parseInt(pollIdAttr, 10);
            const logPrefix = `[Poll ${pollId}]`;

            if (typeof window.kashiwazakiPollAllData[pollId] === 'undefined') {
                console.error(`${logPrefix} Data for this poll ID not found in kashiwazakiPollAllData. Skipping initialization.`);
                return;
            }

            const pollData = window.kashiwazakiPollAllData[pollId];

            const resultContainer = pollBlock.querySelector('#kashiwazaki-poll-result-' + pollId);
            const previewContainer = pollBlock.querySelector('#kashiwazaki-poll-preview-' + pollId);
            const form = pollBlock.querySelector('.kashiwazaki-poll-form');
            const viewResultArea = pollBlock.querySelector('#kashiwazaki-poll-view-result-area-' + pollId);
            const firstSubmitBtn = pollBlock.querySelector('.kashiwazaki-poll-submit'); // For initial check

            if (!resultContainer || !previewContainer || !form || !viewResultArea) {
                console.error(`${logPrefix} One or more required container elements not found within the poll block. Initialization aborted.`);
                return;
            }

            if (!firstSubmitBtn && !pollData.alreadyVoted) { // Check if at least one submit button exists
                console.warn(`${logPrefix} Submit button element not found! Voting will not work.`);
            }


            let alreadyVoted = pollData.alreadyVoted;
            const hasData = pollData.hasData;
            const siteName = pollData.siteName;
            const pollQuestion = pollData.pollQuestion;
            const ajaxUrl = pollData.ajaxUrl;
            const nonce = pollData.nonce;

            let chartInstance = null;

            function fetchAndShowResults(isFullView = false) {
                var fd = new FormData(); fd.append("action", "kashiwazaki_poll_result"); fd.append("poll_id", pollId);
                fetch(ajaxUrl, { method: "POST", body: fd, credentials: "same-origin" })
                    .then(resp => { if (!resp.ok) { return Promise.reject(`HTTP error! status: ${resp.status}`); } return resp.json(); })
                    .then(data => {
                        if (data.status === "ok" && data.labels && data.counts) {
                            if (data.total > 0) {
                                if (alreadyVoted || isFullView) {
                                    if (resultContainer) { showResult(data, resultContainer, 'kashiwazaki-poll-chart-' + pollId, false); if (previewContainer) previewContainer.style.display = 'none'; if (viewResultArea) viewResultArea.style.display = 'none'; }
                                } else if (!alreadyVoted && !isFullView && previewContainer) { showResult(data, previewContainer, 'kashiwazaki-poll-chart-preview-' + pollId, true); if (resultContainer) resultContainer.innerHTML = ''; }
                            } else {
                                if (resultContainer && (alreadyVoted || isFullView)) { resultContainer.innerHTML = "<p>まだ投票がありません。</p>"; resultContainer.style.display = 'block'; } if (previewContainer) previewContainer.style.display = 'none'; if (viewResultArea) viewResultArea.style.display = 'none';
                            }
                        } else {
                            console.error(`${logPrefix} Error fetching results or invalid data format:`, data?.message || "Unknown error or missing data");
                            if (data?.message && (alreadyVoted || isFullView)) alert(data.message);
                            else if (alreadyVoted || isFullView) alert("結果データの取得または形式に問題がありました。");
                        }
                    })
                    .catch(err => { console.error(`${logPrefix} Fetch error for results:`, err); if (alreadyVoted || isFullView) alert("結果の取得中にエラーが発生しました。"); });
            }

            function showResult(data, targetContainer, canvasId, isPreview) {
                if (!targetContainer) { console.error(`${logPrefix} Target container not found for ${canvasId}`); return; }

                if (chartInstance) { try { chartInstance.destroy(); } catch (e) { console.error(`${logPrefix} Error destroying previous chart instance:`, e); } chartInstance = null; }
                targetContainer.innerHTML = '';

                if (!isPreview) {
                    targetContainer.classList.remove('initial-chart-preview'); targetContainer.style.display = 'block';
                    const downloadBtn = document.createElement('button'); downloadBtn.type = 'button'; downloadBtn.id = 'download-btn-' + data.poll_id; downloadBtn.textContent = 'グラフをダウンロード'; downloadBtn.className = 'button kashiwazaki-poll-download-btn'; targetContainer.appendChild(downloadBtn);
                    if (!alreadyVoted && form && !form.classList.contains('kashiwazaki-poll-form-disabled')) { const backBtn = document.createElement('button'); backBtn.type = 'button'; backBtn.className = 'button kashiwazaki-poll-back-to-vote'; backBtn.textContent = '投票に戻る'; targetContainer.appendChild(backBtn); }
                } else { targetContainer.classList.add('initial-chart-preview'); targetContainer.style.display = 'block'; }

                const chartWrapper = document.createElement('div'); chartWrapper.className = 'kashiwazaki-poll-chart-container'; const canvas = document.createElement('canvas'); canvas.id = canvasId; chartWrapper.appendChild(canvas); targetContainer.appendChild(chartWrapper);

                // 各フォーマットの個別ページリンクを追加（フルビューのみ）
                if (!isPreview) {
                    const linksContainer = document.createElement('div'); linksContainer.className = 'kashiwazaki-poll-format-links'; linksContainer.innerHTML = '<div class="format-links-title">📊 フォーマット別データダウンロード</div><p>同じアンケートデータを異なるファイル形式で取得できます</p>';
                    const formatLinks = document.createElement('div'); formatLinks.className = 'format-links-list';
                    const formats = [
                        { type: 'csv', name: 'CSV形式', desc: 'Excel・スプレッドシート用' },
                        { type: 'json', name: 'JSON形式', desc: 'API・プログラム連携用' },
                        { type: 'xml', name: 'XML形式', desc: 'システム間データ交換用' },
                        { type: 'yaml', name: 'YAML形式', desc: '設定ファイル・自動化用' },
                        { type: 'svg', name: 'SVG形式', desc: 'ベクターグラフ・印刷用' }
                    ];
                    formats.forEach(format => {
                        const link = document.createElement('a'); link.href = `${window.location.origin}/datasets/${format.type}/${data.poll_id}/`; link.target = '_blank'; link.className = 'format-link dataset-themed'; link.innerHTML = `${format.name}<br><span class="format-desc">${format.desc}</span>`; formatLinks.appendChild(link);
                    });
                    linksContainer.appendChild(formatLinks); targetContainer.appendChild(linksContainer);

                    // データセットテーマを適用
                    if (pollData.datasetTheme && pollData.datasetTheme.button_primary) {
                        formatLinks.querySelectorAll('.format-link.dataset-themed').forEach(link => {
                            link.style.borderColor = pollData.datasetTheme.button_primary;
                            link.style.color = pollData.datasetTheme.button_primary;
                            link.style.setProperty('--dataset-hover-bg', pollData.datasetTheme.button_primary);
                        });
                    }
                }

                let ctx;
                try {
                    if (typeof Chart === 'undefined') { throw new Error('Chart.js is not loaded.'); } ctx = canvas.getContext("2d"); if (!ctx) { throw new Error('Failed to get 2D context'); }
                } catch (e) { console.error(`${logPrefix} Failed to initialize canvas or Chart.js not found:`, e); chartWrapper.innerHTML = '<p style="color:red;">グラフ描画に必要なライブラリ(Chart.js)が読み込まれていないか、初期化に失敗しました。</p>'; return; }

                if (!data.labels || !data.counts || data.labels.length !== data.counts.length) {
                    console.error(`${logPrefix} Mismatch between labels and counts length or missing data.`);
                    chartWrapper.innerHTML = '<p style="color:red;">グラフデータの形式に問題があります。</p>';
                    return;
                }

                const chartData = { labels: data.labels, datasets: [{ data: data.counts, backgroundColor: ["#FF6384", "#36A2EB", "#FFCE56", "#4BC0C0", "#9966FF", "#FF9F40", "#E7E9ED", "#7FFFD4", "#FF7F50", "#6495ED", "#FFD700", "#DC143C", "#00FFFF", "#00008B", "#ADFF2F", "#FF69B4", "#F0E68C", "#D2691E"], hoverOffset: isPreview ? 0 : 10 }] };
                const paddingTop = 60; const paddingBottom = 80;

                const localCustomChartTextPlugin = {
                    id: 'customChartText',
                    afterDraw: (chart, args, options) => {
                        try {
                            const { ctx } = chart;
                            const titleText = options.pollTitle || '';
                            const currentYear = new Date().getFullYear();
                            const siteNameText = options.siteName || '';
                            const copyrightText = `© ${siteNameText} ${currentYear}`;
                            const topPadding = options.paddingTop || 60;
                            const bottomPadding = options.paddingBottom || 80;
                            const totalVotes = options.totalVotes;
                            const isPreviewFlag = options.isPreview || false;
                            ctx.save();
                            if (titleText) {
                                ctx.font = 'bold 14px Arial'; ctx.fillStyle = 'rgba(0, 0, 0, 0.85)'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                                const titleX = chart.width / 2; const titleY = topPadding / 2; const maxWidth = chart.width - 60;
                                const words = titleText.split(''); let line = ''; let lines = [];
                                for (let charIndex = 0; charIndex < words.length; charIndex++) {
                                    let testLine = line + words[charIndex];
                                    let metrics = ctx.measureText(testLine);
                                    let testWidth = metrics.width;
                                    if (testWidth > maxWidth && charIndex > 0) { lines.push(line); line = words[charIndex]; } else { line = testLine; }
                                }
                                lines.push(line);
                                let currentTitleY = titleY - ((Math.min(lines.length, 2) - 1) * 7);
                                for (let i = 0; i < Math.min(lines.length, 2); i++) { ctx.fillText(lines[i], titleX, currentTitleY); currentTitleY += 14; }
                            }
                            if (!isPreviewFlag && typeof totalVotes !== 'undefined' && totalVotes !== null) {
                                const totalVotesText = `投票総数 ${totalVotes} 票`;
                                ctx.font = '12px Arial'; ctx.fillStyle = 'rgba(0, 0, 0, 0.8)'; ctx.textAlign = 'center'; ctx.textBaseline = 'bottom';
                                const totalVotesX = chart.width / 2; const totalVotesY = chart.height - (bottomPadding / 2) - 5; ctx.fillText(totalVotesText, totalVotesX, totalVotesY);
                            }
                            if (siteNameText) {
                                ctx.font = '11px Arial'; ctx.fillStyle = 'rgba(0, 0, 0, 0.7)'; ctx.textAlign = 'center'; ctx.textBaseline = 'bottom';
                                const copyrightX = chart.width / 2; const copyrightY = chart.height - (bottomPadding / 5); ctx.fillText(copyrightText, copyrightX, copyrightY);
                            }
                            ctx.restore();
                        } catch (e) { console.error(`${logPrefix} Error in customChartTextPlugin afterDraw:`, e); }
                    }
                };

                let useDataLabels = !isPreview && typeof ChartDataLabels !== 'undefined';

                let chartOptions;
                try {
                    chartOptions = {
                        responsive: true, maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: !isPreview, position: "bottom", align: "center",
                                labels: {
                                    boxWidth: 15, padding: 20, generateLabels: function (chart) {
                                        const data = chart.data;
                                        if (data.labels && data.labels.length && data.datasets.length) {
                                            const labels = data.labels; const dataset = data.datasets[0]; const counts = dataset.data; const backgroundColors = dataset.backgroundColor;
                                            const totalVotes = counts.reduce((sum, count) => sum + (Number(count) || 0), 0);
                                            const maxLabelLength = 15;
                                            try {
                                                return labels.map((label, index) => {
                                                    const voteCount = (counts && typeof counts[index] !== 'undefined') ? Number(counts[index]) : 0;
                                                    const percentage = totalVotes > 0 ? ((voteCount / totalVotes) * 100).toFixed(1) : '0.0';
                                                    const labelText = typeof label === 'string' ? label : `項目 ${index + 1}`;
                                                    const truncatedLabel = labelText.length > maxLabelLength ? labelText.substring(0, maxLabelLength) + '...' : labelText;
                                                    const text = `${truncatedLabel} (${voteCount}票 / ${percentage}%)`;
                                                    const fullText = `${labelText} (${voteCount}票 / ${percentage}%)`;
                                                    return { text: text, fullText: fullText, fillStyle: backgroundColors[index % backgroundColors.length], strokeStyle: backgroundColors[index % backgroundColors.length], lineWidth: 0, hidden: !chart.getDataVisibility(index), index: index };
                                                });
                                            } catch (mapError) { console.error(`${logPrefix} Error during legend labels map:`, mapError); return []; }
                                        }
                                        return [];
                                    }
                                },
                                onHover: function(event, legendItem) {
                                    if (legendItem && legendItem.fullText && legendItem.fullText !== legendItem.text) {
                                        let tooltip = document.getElementById('legend-tooltip');
                                        if (!tooltip) {
                                            tooltip = document.createElement('div');
                                            tooltip.id = 'legend-tooltip';
                                            tooltip.style.cssText = 'position:fixed;background:#333;color:#fff;padding:8px 12px;border-radius:4px;font-size:12px;z-index:10000;pointer-events:none;max-width:300px;word-wrap:break-word;box-shadow:0 2px 8px rgba(0,0,0,0.3);';
                                            document.body.appendChild(tooltip);
                                        }
                                        tooltip.textContent = legendItem.fullText;
                                        tooltip.style.display = 'block';
                                        tooltip.style.left = (event.native.clientX + 10) + 'px';
                                        tooltip.style.top = (event.native.clientY + 10) + 'px';
                                    }
                                },
                                onLeave: function() {
                                    const tooltip = document.getElementById('legend-tooltip');
                                    if (tooltip) { tooltip.style.display = 'none'; }
                                }
                            },
                            tooltip: { enabled: !isPreview },
                            customChartText: { pollTitle: pollQuestion, siteName: siteName, paddingTop: paddingTop, paddingBottom: paddingBottom, totalVotes: data.total, isPreview: isPreview },
                            datalabels: { display: useDataLabels ? 'auto' : false, formatter: (value, context) => { try { const dataset = context.chart.data.datasets?.[0]; const allData = dataset?.data; if (!allData || !Array.isArray(allData)) { return ''; } const total = allData.reduce((a, b) => a + (Number(b) || 0), 0); const percentage = total > 0 ? ((Number(value) || 0) / total * 100) : 0; return percentage >= 0.1 ? percentage.toFixed(1) + '%' : ''; } catch (e) { console.error(`${logPrefix} Datalabels formatter error:`, e); return ''; } }, color: '#ffffff', textStrokeColor: 'black', textStrokeWidth: 1, font: { weight: 'bold', size: 12 } }
                        },
                        animation: false, events: isPreview ? [] : Chart.defaults.events, layout: { padding: { top: paddingTop, right: 30, bottom: paddingBottom, left: 30 } }
                    };
                } catch (e) { console.error(`${logPrefix} Error creating chart options:`, e); chartWrapper.innerHTML = '<p style="color:red;">グラフオプションの設定中にエラーが発生しました。</p>'; return; }

                const chartPlugins = [localCustomChartTextPlugin];
                if (useDataLabels) {
                    try { if (typeof ChartDataLabels === 'object' && ChartDataLabels.id === 'datalabels') { chartPlugins.push(ChartDataLabels); } else { console.warn(`${logPrefix} ChartDataLabels is NOT a valid plugin object. Disabling datalabels in options. Type: ${typeof ChartDataLabels}`); if (chartOptions?.plugins?.datalabels) { chartOptions.plugins.datalabels.display = false; } } }
                    catch (e) { console.error(`${logPrefix} Error while preparing ChartDataLabels for chart plugins:`, e); if (chartOptions?.plugins?.datalabels) { chartOptions.plugins.datalabels.display = false; } }
                }

                try {
                    chartInstance = new Chart(ctx, { type: "pie", data: chartData, options: chartOptions, plugins: chartPlugins });
                } catch (error) { console.error(`${logPrefix} Error creating chart instance:`, error); chartWrapper.innerHTML = '<p style="color:red;">グラフの表示に失敗しました。開発者コンソールで詳細を確認してください。</p>'; console.error(`${logPrefix} Chart Data:`, JSON.stringify(chartData)); console.error(`${logPrefix} Chart Plugins being passed:`, chartPlugins.map(p => p?.id || 'Unknown/Invalid Plugin')); return; }
            } // end of showResult

            function downloadChart(canvasId, poll_id) {
                const canvas = document.getElementById(canvasId);
                if (!canvas) { console.error(`${logPrefix} Canvas element with id "${canvasId}" not found for download.`); alert('グラフ要素が見つからず、ダウンロードできませんでした。'); return; }
                if (canvas.offsetParent === null) { console.warn(`${logPrefix} Canvas element "${canvasId}" seems to be hidden. Download might fail or produce blank image.`); }
                try {
                    const dataURL = canvas.toDataURL("image/png");
                    if (!dataURL || dataURL.length < 100) { console.error(`${logPrefix} Failed to generate valid data URL from canvas "${canvasId}". URL: ${dataURL}`); throw new Error('グラフ画像のデータ生成に失敗しました。(Invalid URL)'); }
                    const link = document.createElement("a");
                    link.href = dataURL;
                    const safeSiteName = siteName.replace(/[^a-zA-Z0-9_\-]/g, '_').toLowerCase() || 'site';
                    link.download = `chart_${poll_id}_${safeSiteName}.png`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                } catch (error) { console.error(`${logPrefix} Error during chart download process for canvas "${canvasId}":`, error); alert('グラフ画像の生成またはダウンロード中にエラーが発生しました。\n詳細: ' + error.message); }
            }

            pollBlock.addEventListener('click', function (e) {
                const target = e.target;

                // Check if a submit button was clicked
                if (target && target.classList && target.classList.contains('kashiwazaki-poll-submit')) {
                    e.preventDefault();
                    if (!form) { console.error(`${logPrefix} CRITICAL: Form not found within event listener.`); return; }
                    const checks = form.querySelectorAll("input[name='poll_options[]']:checked");
                    if (checks.length === 0) { alert("選択肢を選んでください。"); return; }

                    target.disabled = true; // Disable the clicked button
                    target.textContent = '投票中...'; // Change text of the clicked button

                    const fd = new FormData(form);
                    fd.append("action", "kashiwazaki_poll_vote");

                    let nonceFoundInFormData = false;
                    for (let [key, value] of fd.entries()) { if (key === '_wpnonce') nonceFoundInFormData = true; }
                    if (!nonceFoundInFormData) {
                        console.warn(`${logPrefix} Nonce field '_wpnonce' not found in FormData. Adding manually.`);
                        if (nonce) { fd.append("_wpnonce", nonce); }
                        else { console.error(`${logPrefix} Nonce value is undefined. Cannot add manually.`); alert("セキュリティトークンが見つかりません。ページを再読み込みしてください。"); target.disabled = false; target.textContent = '投票する'; return; }
                    }

                    fetch(ajaxUrl, { method: "POST", body: fd, credentials: "same-origin" })
                        .then(response => { if (!response.ok) { return response.text().then(text => { console.error(`${logPrefix} Network response error text: ${text}`); throw new Error(`Network response was not ok: ${response.statusText}`); }); } return response.json(); })
                        .then(data => {
                            if (data.status === "ok") {
                                alreadyVoted = true; fetchAndShowResults(true);
                                if (form) form.style.display = 'none'; // Hide the form
                                const votedMsg = document.createElement('p'); votedMsg.className = 'voted-msg'; votedMsg.textContent = '投票しました。';
                                pollBlock.insertBefore(votedMsg, resultContainer || pollBlock.firstChild);
                                // Submit buttons are inside the form, which is now hidden. No need to remove them individually.
                                if (viewResultArea) viewResultArea.style.display = 'none';
                            } else {
                                if (data.message) alert(data.message); else alert("投票に失敗しました。");
                                target.disabled = false; // Re-enable the clicked button
                                target.textContent = '投票する'; // Restore text of the clicked button
                            }
                        })
                        .catch(error => {
                            console.error(`${logPrefix} Vote submission fetch/processing error:`, error);
                            alert("投票処理中にエラーが発生しました。");
                            target.disabled = false; // Re-enable the clicked button
                            target.textContent = '投票する'; // Restore text of the clicked button
                        });

                } else if (target && target.classList.contains('kashiwazaki-poll-view-result')) {
                    e.preventDefault(); fetchAndShowResults(true); if (form) form.style.display = 'none'; if (viewResultArea) viewResultArea.style.display = 'none'; if (previewContainer) previewContainer.style.display = 'none';
                } else if (target && target.classList.contains('kashiwazaki-poll-back-to-vote')) {
                    e.preventDefault();
                    if (resultContainer) { resultContainer.innerHTML = ''; resultContainer.style.display = 'none'; }
                    if (previewContainer) { previewContainer.style.display = 'none'; }
                    if (chartInstance) { try { chartInstance.destroy(); } catch (e) { } chartInstance = null; }
                    if (form) form.style.display = 'block';
                    if (!alreadyVoted && hasData && viewResultArea) {
                        viewResultArea.style.display = 'block';
                    } else if (viewResultArea) { viewResultArea.style.display = 'none'; }
                    if (!alreadyVoted && hasData) {
                        fetchAndShowResults(false);
                    } else if (previewContainer) { previewContainer.style.display = 'none'; }
                    const backBtn = pollBlock.querySelector('.kashiwazaki-poll-back-to-vote'); if (backBtn) backBtn.remove(); const downloadBtn = pollBlock.querySelector('.kashiwazaki-poll-download-btn'); if (downloadBtn) downloadBtn.remove();
                } else if (target && target.classList.contains('kashiwazaki-poll-download-btn')) {
                    e.preventDefault();
                    const canvasId = 'kashiwazaki-poll-chart-' + pollId;
                    downloadChart(canvasId, pollId);
                }
            });

            // 追加: 各ボタンに直接リスナーを付与して冗長性を確保（複数pollでの干渉を防ぐ）
            const viewResultBtn = pollBlock.querySelector('.kashiwazaki-poll-view-result');
            if (viewResultBtn) {
                viewResultBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    fetchAndShowResults(true);
                    if (form) form.style.display = 'none';
                    if (viewResultArea) viewResultArea.style.display = 'none';
                    if (previewContainer) previewContainer.style.display = 'none';
                });
            }

            // --- 初期表示処理 ---
            const initializePollView = () => {
                if (!resultContainer || !previewContainer || !form || !viewResultArea) {
                    console.error(`${logPrefix} Skipping initialization due to missing elements.`);
                    return;
                }
                if (alreadyVoted) { fetchAndShowResults(true); }
                else if (hasData) { fetchAndShowResults(false); }
            };

            // 初期化実行
            initializePollView();

        }); // End of forEach
    } // End of initKashiwazakiPolls

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initKashiwazakiPolls);
    } else {
        initKashiwazakiPolls();
    }

})();
