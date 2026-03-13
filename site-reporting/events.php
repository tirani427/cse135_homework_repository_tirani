<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: /index.html");
    exit();
}

if($_SESSION['user']['role'] === 'viewer'){
    header("Location: /403.html");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Events Report - Analytics Dashboard</title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <h1>Events Report</h1>
            <div class="controls">
                <label for="startDate">From</label>
                <input type="date" id="startDate">

                <label for="endDate">To</label>
                <input type="date" id="endDate">

                <button id="loadBtn">Load</button>
            </div>
        </div>

        <div class="container">
            <div id="errorBox" class="error-msg"></div>

            <div class="panel">
                <h2>Analyst Comment</h2>
                <div id="analystComment" class="comment-box">Loading summary...</div>
            </div>

            <div class="panel">
                <h2>Event Trend Over Time</h2>
                <div class="chart-wrap">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>

            <div class="panel">
                <h2>Top Event Types</h2>
                <div class="chart-wrap">
                    <canvas id="topEventsChart"></canvas>
                </div>
            </div>

            <div class="panel">
                <h2>Event Details</h2>
                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Event Name</th>
                                <th>Occurrences</th>
                                <th>Last Seen</th>
                            </tr>
                        </thead>
                        <tbody id="eventsBody">
                            <tr>
                                <td colspan="3" class="empty-state">Loading event data...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
        (function () {
            'use strict';

            let trendChart = null;
            let topChart = null;

            const today = new Date();
            const thirtyAgo = new Date(Date.now() - 30 * 86400000);

            document.getElementById('startDate').value = formatDate(thirtyAgo);
            document.getElementById('endDate').value = formatDate(today);
            document.getElementById('loadBtn').addEventListener('click', loadData);

            loadData();

            function formatDate(d) {
                return d.getFullYear() + '-' +
                    String(d.getMonth() + 1).padStart(2, '0') + '-' +
                    String(d.getDate()).padStart(2, '0');
            }

            function showError(msg) {
                const box = document.getElementById('errorBox');
                box.textContent = msg;
                box.style.display = 'block';
            }

            function hideError() {
                document.getElementById('errorBox').style.display = 'none';
            }

            async function loadData() {
                hideError();

                const start = document.getElementById('startDate').value;
                const end = document.getElementById('endDate').value;

                const url = '/api/index.php/events-report?start=' +
                    encodeURIComponent(start) + '&end=' + encodeURIComponent(end);

                try {
                    const resp = await fetch(url, { credentials: 'include' });

                    if(resp.status == 400){
                        window.location.href = "/400.html";
                        return;
                    }
                    if(resp.status === 401) {
                        window.location.href = '/index.html';
                        return;
                    }

                    if(resp.status === 403){
                        window.location.href= "/403.html";
                        return;
                    }

                    if(resp.status === 500){
                        window.location.href = "/500.html";
                        return;
                    }

                    const json = await resp.json();

                    if (!json.success) {
                        showError(json.error || 'Failed to load event report.');
                        return;
                    }

                    const trend = json.data.trend || [];
                    const topEvents = json.data.topEvents || [];

                    renderTrendChart(trend);
                    renderTopEventsChart(topEvents);
                    renderTable(topEvents);
                    renderComment(trend, topEvents);
                } catch (err) {
                    showError('Network error: could not reach the API.');
                }
            }

            function renderTrendChart(trend) {
                const ctx = document.getElementById('trendChart').getContext('2d');

                if (trendChart) {
                    trendChart.destroy();
                }

                trendChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: trend.map(row => row.day),
                        datasets: [{
                            label: 'Events',
                            data: trend.map(row => Number(row.event_count)),
                            tension: 0.25,
                            fill: false
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            }

            function renderTopEventsChart(topEvents) {
                const ctx = document.getElementById('topEventsChart').getContext('2d');

                if (topChart) {
                    topChart.destroy();
                }

                topChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: topEvents.map(row => row.event_name),
                        datasets: [{
                            label: 'Occurrences',
                            data: topEvents.map(row => Number(row.occurrences))
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }

            function renderTable(topEvents) {
                const tbody = document.getElementById('eventsBody');
                tbody.innerHTML = '';

                if (!topEvents.length) {
                    const tr = document.createElement('tr');
                    const td = document.createElement('td');
                    td.colSpan = 3;
                    td.className = 'empty-state';
                    td.textContent = 'No events found for the selected date range.';
                    tr.appendChild(td);
                    tbody.appendChild(tr);
                    return;
                }

                for (let i = 0; i < topEvents.length; i++) {
                    const row = topEvents[i];
                    const tr = document.createElement('tr');

                    const nameTd = document.createElement('td');
                    nameTd.textContent = row.event_name || 'unknown';
                    tr.appendChild(nameTd);

                    const countTd = document.createElement('td');
                    countTd.textContent = row.occurrences ?? '0';
                    countTd.style.textAlign = 'right';
                    tr.appendChild(countTd);

                    const lastTd = document.createElement('td');
                    lastTd.textContent = row.last_seen || '—';
                    tr.appendChild(lastTd);

                    tbody.appendChild(tr);
                }
            }

            function renderComment(trend, topEvents) {
                const box = document.getElementById('analystComment');

                if (!topEvents.length) {
                    box.textContent = 'No event activity was recorded during the selected date range, so no behavioral interpretation can be made yet.';
                    return;
                }

                const top = topEvents[0];
                const total = topEvents.reduce((sum, row) => sum + Number(row.occurrences || 0), 0);
                const topPct = total ? ((Number(top.occurrences) / total) * 100).toFixed(1) : '0.0';

                let peakDay = null;
                if (trend.length) {
                    peakDay = trend.reduce((best, row) =>
                        Number(row.event_count) > Number(best.event_count) ? row : best
                    );
                }

                let text = '';
                text += 'The most common tracked event during the selected period was "' + top.event_name +
                    '", with ' + top.occurrences + ' occurrences';
                text += ' (' + topPct + '% of the top tracked events shown). ';

                if (peakDay) {
                    text += 'Overall event activity peaked on ' + peakDay.day +
                        ', when ' + peakDay.event_count + ' total events were recorded. ';
                }

                text += 'This report suggests which user actions are most common and can help identify the most engaged interaction points in the site flow.';

                box.textContent = text;
            }
        })();
        </script>
    </body>
</html>