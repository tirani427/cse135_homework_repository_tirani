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
    <title>Performance Report - Analytics Dashboard</title>
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
        .cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .card {
            border-radius: 8px;
            padding: 20px;
            color: white;
            text-align: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
        .card .label {
            font-size: 0.8em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.9;
            margin-bottom: 4px;
        }
        .card .value {
            font-size: 2em;
            font-weight: 700;
            line-height: 1.2;
        }
        .card .rating {
            font-size: 0.75em;
            margin-top: 6px;
            padding: 2px 10px;
            background: rgba(255,255,255,0.25);
            border-radius: 10px;
            display: inline-block;
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
        tr:hover td { background: #f8f9fa; }
        .url-cell {
            max-width: 280px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .slow-row { border-left: 4px solid #ff4e42; }
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
            .cards { grid-template-columns: 1fr; }
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            .url-cell { max-width: 140px; }
            table { font-size: 0.85em; }
            th, td { padding: 8px 6px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Performance Report</h1>
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

        <div class="cards">
            <div class="card" id="lcpCard" style="background: #ccc;">
                <div class="label">LCP (p75)</div>
                <div class="value" id="lcpValue">&mdash;</div>
                <div class="rating" id="lcpRating">Loading...</div>
            </div>
            <div class="card" id="clsCard" style="background: #ccc;">
                <div class="label">CLS (p75)</div>
                <div class="value" id="clsValue">&mdash;</div>
                <div class="rating" id="clsRating">Loading...</div>
            </div>
            <div class="card" id="inpCard" style="background: #ccc;">
                <div class="label">INP (p75)</div>
                <div class="value" id="inpValue">&mdash;</div>
                <div class="rating" id="inpRating">Loading...</div>
            </div>
        </div>

        <div class="panel">
            <h2>Per-Page Performance</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>URL</th>
                            <th>Avg Load (ms)</th>
                            <th>Avg TTFB (ms)</th>
                            <th>Avg LCP (ms)</th>
                            <th>Avg CLS</th>
                            <th>Samples</th>
                        </tr>
                    </thead>
                    <tbody id="perfBody">
                        <tr>
                            <td colspan="6" class="empty-state">Loading performance data...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    (function () {
        'use strict';

        var THRESHOLDS = {
            lcp: { good: 2500, poor: 4000 },
            cls: { good: 0.1, poor: 0.25 },
            inp: { good: 200, poor: 500 }
        };

        var COLORS = {
            good: '#0cce6b',
            warn: '#ffa400',
            poor: '#ff4e42'
        };

        // Set default date range: last 30 days
        var today = new Date();
        var thirtyAgo = new Date(Date.now() - 30 * 86400000);
        document.getElementById('endDate').value = formatDate(today);
        document.getElementById('startDate').value = formatDate(thirtyAgo);

        document.getElementById('loadBtn').addEventListener('click', loadData);

        document.getElementById('date-end').value = today.toISOString().slice(0, 10);
        document.getElementById('date-start').value = thirtyDaysAgo.toISOString().slice(0, 10);

        // Initial load
        loadData();

        function formatDate(d) {
            return d.getFullYear() + '-' +
                String(d.getMonth() + 1).padStart(2, '0') + '-' +
                String(d.getDate()).padStart(2, '0');
        }

        function getVitalColor(metric, value) {
            var t = THRESHOLDS[metric];
            if (value < t.good) return COLORS.good;
            if (value < t.poor) return COLORS.warn;
            return COLORS.poor;
        }

        function getRating(metric, value) {
            var t = THRESHOLDS[metric];
            if (value < t.good) return 'Good';
            if (value < t.poor) return 'Needs Work';
            return 'Poor';
        }

        function showError(msg) {
            var box = document.getElementById('errorBox');
            box.textContent = msg;
            box.style.display = 'block';
        }

        function hideError() {
            document.getElementById('errorBox').style.display = 'none';
        }

        function setCard(cardId, valueId, ratingId, displayValue, color, rating) {
            document.getElementById(cardId).style.background = color;
            document.getElementById(valueId).textContent = displayValue;
            document.getElementById(ratingId).textContent = rating;
        }

        async function loadData() {
            hideError();
            var start = document.getElementById('startDate').value;
            var end = document.getElementById('endDate').value;

            if (!start || !end) {
                showError('Please select both a start and end date.');
                return;
            }

            var url = '/api/index.php/performance?start=' +
                encodeURIComponent(start) + '&end=' + encodeURIComponent(end);

            try {
                var resp = await fetch(url, { credentials: 'include' });
                var json = await resp.json();

                if (!json.success) {
                    showError(json.error || 'Failed to load data.');
                    return;
                }

                var byPage = json.data.byPage || [];
                renderCards(byPage);
                renderTable(byPage);
            } catch (err) {
                showError('Network error: could not reach the API.');
            }
        }

        function renderCards(byPage) {
            if (byPage.length === 0) {
                setCard('lcpCard', 'lcpValue', 'lcpRating', '\u2014', '#ccc', 'No data');
                setCard('clsCard', 'clsValue', 'clsRating', '\u2014', '#ccc', 'No data');
                setCard('inpCard', 'inpValue', 'inpRating', '\u2014', '#ccc', 'No data');
                return;
            }

            // Compute weighted averages across all pages
            var totalLcp = 0, totalCls = 0, totalSamples = 0;
            for (var i = 0; i < byPage.length; i++) {
                var row = byPage[i];
                var samples = row.samples || 1;
                totalLcp += (row.avg_lcp || 0) * samples;
                totalCls += (row.avg_cls || 0) * samples;
                totalSamples += samples;
            }

            var avgLcp = totalSamples ? totalLcp / totalSamples : 0;
            var avgCls = totalSamples ? totalCls / totalSamples : 0;

            // LCP is stored in ms from the API
            setCard('lcpCard', 'lcpValue', 'lcpRating',
                avgLcp.toFixed(0) + 'ms',
                getVitalColor('lcp', avgLcp),
                getRating('lcp', avgLcp)
            );

            setCard('clsCard', 'clsValue', 'clsRating',
                avgCls.toFixed(3),
                getVitalColor('cls', avgCls),
                getRating('cls', avgCls)
            );

            // INP is not in the current API response; show N/A
            setCard('inpCard', 'inpValue', 'inpRating',
                'N/A', '#ccc', 'Not collected');
        }

        function renderTable(byPage) {
            var tbody = document.getElementById('perfBody');
            tbody.innerHTML = '';

            if (byPage.length === 0) {
                var emptyTr = document.createElement('tr');
                var emptyTd = document.createElement('td');
                emptyTd.colSpan = 6;
                emptyTd.className = 'empty-state';
                emptyTd.textContent = 'No performance data for the selected date range.';
                emptyTr.appendChild(emptyTd);
                tbody.appendChild(emptyTr);
                return;
            }

            for (var i = 0; i < byPage.length; i++) {
                var row = byPage[i];
                var tr = document.createElement('tr');

                // Highlight slow pages
                if (row.avg_load_ms > 3000) {
                    tr.className = 'slow-row';
                }

                // URL cell (user-sourced data: use textContent)
                var urlTd = document.createElement('td');
                urlTd.className = 'url-cell';
                urlTd.textContent = row.url;
                urlTd.title = row.url;
                tr.appendChild(urlTd);

                // Numeric cells
                var fields = [
                    { key: 'avg_load_ms', fallback: '\u2014' },
                    { key: 'avg_ttfb_ms', fallback: '\u2014' },
                    { key: 'avg_lcp', fallback: '\u2014' },
                    { key: 'avg_cls', fallback: '\u2014' },
                    { key: 'samples', fallback: '0' }
                ];

                for (var j = 0; j < fields.length; j++) {
                    var td = document.createElement('td');
                    var val = row[fields[j].key];
                    td.textContent = val != null ? val : fields[j].fallback;
                    td.style.textAlign = 'right';
                    tr.appendChild(td);
                }

                tbody.appendChild(tr);
            }
        }
    })();
    </script>
</body>
</html>
