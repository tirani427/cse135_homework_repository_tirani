<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: /index.html");
    exit();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error Report - Analytics Dashboard</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            background: #f5f5f5;
            color: #333;
        }
        .header {
            background: #2E86C1;
            color: white;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .header h1 {
            font-size: 1.3em;
            font-weight: 600;
        }
        .date-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .date-controls label {
            font-size: 0.85em;
            opacity: 0.9;
        }
        .date-controls input[type="date"] {
            padding: 6px 10px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 4px;
            background: rgba(255,255,255,0.15);
            color: white;
            font-size: 0.85em;
        }
        .date-controls input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
        }
        .date-controls button {
            padding: 6px 16px;
            background: white;
            color: #2E86C1;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.85em;
        }
        .date-controls button:hover { background: #e8f4fc; }
        .container {
            max-width: 1000px;
            margin: 24px auto;
            padding: 0 20px;
        }
        .summary-card {
            background: #2E86C1;
            color: white;
            border-radius: 8px;
            padding: 20px 24px;
            margin-bottom: 24px;
            display: inline-block;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
        .summary-card .label {
            font-size: 0.8em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.9;
        }
        .summary-card .value {
            font-size: 2.2em;
            font-weight: 700;
            line-height: 1.2;
        }
        .panel {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 24px;
            margin-bottom: 24px;
        }
        .panel h2 {
            color: #2E86C1;
            font-size: 1.1em;
            margin-bottom: 16px;
        }
        canvas {
            width: 100%;
            height: 220px;
            display: block;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            color: #555;
            font-size: 0.8em;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-weight: 600;
        }
        td { font-size: 0.9em; }
        .clickable-row { cursor: pointer; }
        .clickable-row:hover td { background: #f0f7fc; }
        .msg-cell {
            max-width: 400px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .detail-row td {
            background: #f8f9fa;
            white-space: pre-wrap;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 12px;
            color: #555;
            padding: 12px 16px;
            border-left: 3px solid #2E86C1;
        }
        .empty-state {
            text-align: center;
            color: #888;
            padding: 40px 20px;
        }
        .error-msg {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px 16px;
            margin-bottom: 16px;
            border-radius: 4px;
            display: none;
        }
        @media (max-width: 600px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            .msg-cell { max-width: 180px; }
            table { font-size: 0.85em; }
            th, td { padding: 8px 6px; }
            canvas { height: 180px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Error Report</h1>
        <div class="date-controls">
            <label for="startDate">From</label>
            <input type="date" id="startDate">
            <label for="endDate">To</label>
            <input type="date" id="endDate">
            <button id="loadBtn">Load</button>
        </div>
    </div>

    <div class="container">
        <div id="errorBox" class="error-msg"></div>

        <div class="summary-card">
            <div class="label">Total Errors</div>
            <div class="value" id="totalErrors">&mdash;</div>
        </div>

        <div class="panel">
            <h2>Errors Per Day</h2>
            <canvas id="trendChart" width="950" height="220"></canvas>
        </div>

        <div class="panel">
            <h2>Error Frequency</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Error Message</th>
                            <th>Occurrences</th>
                            <th>Last Seen</th>
                        </tr>
                    </thead>
                    <tbody id="errorBody">
                        <tr>
                            <td colspan="3" class="empty-state">Loading error data...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    (function () {
        'use strict';

        // Set default date range: last 30 days
        var today = new Date();
        var thirtyAgo = new Date(Date.now() - 30 * 86400000);
        document.getElementById('endDate').value = formatDate(today);
        document.getElementById('startDate').value = formatDate(thirtyAgo);

        document.getElementById('loadBtn').addEventListener('click', loadData);

        // Initial load
        loadData();

        function formatDate(d) {
            return d.getFullYear() + '-' +
                String(d.getMonth() + 1).padStart(2, '0') + '-' +
                String(d.getDate()).padStart(2, '0');
        }

        function formatShortDate(dateStr) {
            var d = new Date(dateStr);
            var months = ['Jan','Feb','Mar','Apr','May','Jun',
                          'Jul','Aug','Sep','Oct','Nov','Dec'];
            return months[d.getMonth()] + ' ' + d.getDate();
        }

        function showError(msg) {
            var box = document.getElementById('errorBox');
            box.textContent = msg;
            box.style.display = 'block';
        }

        function hideError() {
            document.getElementById('errorBox').style.display = 'none';
        }

        async function loadData() {
            hideError();
            var start = document.getElementById('startDate').value;
            var end = document.getElementById('endDate').value;

            if (!start || !end) {
                showError('Please select both a start and end date.');
                return;
            }

            var url = '/api/errors?start=' +
                encodeURIComponent(start) + '&end=' + encodeURIComponent(end);

            try {
                var resp = await fetch(url, { credentials: 'include' });
                var json = await resp.json();

                if (!json.success) {
                    showError(json.error || 'Failed to load data.');
                    return;
                }

                var byMessage = json.data.byMessage || [];
                var trend = json.data.trend || [];

                renderSummary(byMessage);
                renderChart(trend);
                renderTable(byMessage);
            } catch (err) {
                showError('Network error: could not reach the API.');
            }
        }

        function renderSummary(byMessage) {
            var total = 0;
            for (var i = 0; i < byMessage.length; i++) {
                total += byMessage[i].occurrences || 0;
            }
            document.getElementById('totalErrors').textContent =
                total.toLocaleString();
        }

        function renderChart(trend) {
            var canvas = document.getElementById('trendChart');
            // Set canvas resolution to match CSS size
            var rect = canvas.getBoundingClientRect();
            canvas.width = rect.width * (window.devicePixelRatio || 1);
            canvas.height = rect.height * (window.devicePixelRatio || 1);
            var ctx = canvas.getContext('2d');
            ctx.scale(window.devicePixelRatio || 1, window.devicePixelRatio || 1);

            var W = rect.width;
            var H = rect.height;
            var pad = { top: 20, right: 20, bottom: 40, left: 50 };

            ctx.clearRect(0, 0, W, H);

            if (trend.length === 0) {
                ctx.fillStyle = '#888';
                ctx.font = '14px -apple-system, sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('No error data for the selected range.', W / 2, H / 2);
                return;
            }

            var maxVal = 0;
            for (var i = 0; i < trend.length; i++) {
                if (trend[i].error_count > maxVal) maxVal = trend[i].error_count;
            }
            if (maxVal === 0) maxVal = 1;

            var chartW = W - pad.left - pad.right;
            var chartH = H - pad.top - pad.bottom;
            var xStep = trend.length > 1 ? chartW / (trend.length - 1) : chartW;

            // Draw grid lines
            ctx.strokeStyle = '#eee';
            ctx.lineWidth = 1;
            var gridLines = 4;
            for (var g = 0; g <= gridLines; g++) {
                var gy = pad.top + (chartH / gridLines) * g;
                ctx.beginPath();
                ctx.moveTo(pad.left, gy);
                ctx.lineTo(W - pad.right, gy);
                ctx.stroke();

                // Y-axis labels
                var yLabel = Math.round(maxVal - (maxVal / gridLines) * g);
                ctx.fillStyle = '#888';
                ctx.font = '11px -apple-system, sans-serif';
                ctx.textAlign = 'right';
                ctx.fillText(yLabel, pad.left - 8, gy + 4);
            }

            // Draw axes
            ctx.strokeStyle = '#ccc';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(pad.left, pad.top);
            ctx.lineTo(pad.left, H - pad.bottom);
            ctx.lineTo(W - pad.right, H - pad.bottom);
            ctx.stroke();

            // Draw fill area
            ctx.fillStyle = 'rgba(46, 134, 193, 0.1)';
            ctx.beginPath();
            for (var i = 0; i < trend.length; i++) {
                var x = pad.left + i * xStep;
                var y = pad.top + chartH - (trend[i].error_count / maxVal) * chartH;
                if (i === 0) ctx.moveTo(x, y);
                else ctx.lineTo(x, y);
            }
            ctx.lineTo(pad.left + (trend.length - 1) * xStep, H - pad.bottom);
            ctx.lineTo(pad.left, H - pad.bottom);
            ctx.closePath();
            ctx.fill();

            // Draw line
            ctx.strokeStyle = '#2E86C1';
            ctx.lineWidth = 2;
            ctx.beginPath();
            for (var i = 0; i < trend.length; i++) {
                var x = pad.left + i * xStep;
                var y = pad.top + chartH - (trend[i].error_count / maxVal) * chartH;
                if (i === 0) ctx.moveTo(x, y);
                else ctx.lineTo(x, y);
            }
            ctx.stroke();

            // Draw dots
            ctx.fillStyle = '#2E86C1';
            for (var i = 0; i < trend.length; i++) {
                var x = pad.left + i * xStep;
                var y = pad.top + chartH - (trend[i].error_count / maxVal) * chartH;
                ctx.beginPath();
                ctx.arc(x, y, 3, 0, Math.PI * 2);
                ctx.fill();
            }

            // X-axis labels (show a subset to avoid overlap)
            ctx.fillStyle = '#888';
            ctx.font = '11px -apple-system, sans-serif';
            ctx.textAlign = 'center';
            var labelInterval = Math.max(1, Math.floor(trend.length / 7));
            for (var i = 0; i < trend.length; i += labelInterval) {
                var x = pad.left + i * xStep;
                ctx.fillText(formatShortDate(trend[i].day), x, H - pad.bottom + 20);
            }
            // Always show last label
            if ((trend.length - 1) % labelInterval !== 0) {
                var lastX = pad.left + (trend.length - 1) * xStep;
                ctx.fillText(formatShortDate(trend[trend.length - 1].day), lastX, H - pad.bottom + 20);
            }
        }

        function renderTable(byMessage) {
            var tbody = document.getElementById('errorBody');
            tbody.innerHTML = '';

            if (byMessage.length === 0) {
                var emptyTr = document.createElement('tr');
                var emptyTd = document.createElement('td');
                emptyTd.colSpan = 3;
                emptyTd.className = 'empty-state';
                emptyTd.textContent = 'No errors for the selected date range.';
                emptyTr.appendChild(emptyTd);
                tbody.appendChild(emptyTr);
                return;
            }

            for (var i = 0; i < byMessage.length; i++) {
                var row = byMessage[i];

                // Summary row (always visible)
                var tr = document.createElement('tr');
                tr.className = 'clickable-row';

                var msgTd = document.createElement('td');
                msgTd.className = 'msg-cell';
                msgTd.textContent = row.error_message;   // XSS-safe
                msgTd.title = row.error_message;
                tr.appendChild(msgTd);

                var countTd = document.createElement('td');
                countTd.textContent = row.occurrences;
                countTd.style.textAlign = 'right';
                tr.appendChild(countTd);

                var dateTd = document.createElement('td');
                dateTd.textContent = row.last_seen ? formatShortDate(row.last_seen) : '\u2014';
                tr.appendChild(dateTd);

                // Detail row (hidden by default)
                var detailTr = document.createElement('tr');
                detailTr.className = 'detail-row';
                detailTr.style.display = 'none';
                var detailTd = document.createElement('td');
                detailTd.colSpan = 3;
                detailTd.textContent = row.error_message; // Full message, XSS-safe
                detailTr.appendChild(detailTd);

                // Toggle on click (closure to capture detailTr)
                (function (detail) {
                    tr.addEventListener('click', function () {
                        detail.style.display =
                            detail.style.display === 'none' ? '' : 'none';
                    });
                })(detailTr);

                tbody.appendChild(tr);
                tbody.appendChild(detailTr);
            }
        }

        // Redraw chart on window resize
        var resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                // Re-fetch to trigger chart redraw at new size
                loadData();
            }, 250);
        });
    })();
    </script>
</body>
</html>
