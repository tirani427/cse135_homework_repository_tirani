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
        <title>Pageviews Report - Analytics Dashboard</title>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: #f0f2f5;
                min-height: 100vh;
            }
            .header {
                background: #2E86C1;
                color: white;
                padding: 16px 24px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .container {
                max-width: 900px;
                margin: 30px auto;
                padding: 0 20px;
            }
            .panel {
                background: white;
                border-radius: 8px;
                padding: 24px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                margin-bottom: 24px;
            }
            .controls {
                display: flex;
                gap: 10px;
                margin-bottom: 20px;
                flex-wrap: wrap;
            }
            canvas {
                max-width: 100%;
                margin: 0 auto;
                display: block;
            }
            .error-msg {
                display: none;
                background: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 12px 16px;
                margin-bottom: 16px;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Pageviews Report</h1>
        </div>

        <div class="container">
            <div id="errorBox" class="error-msg"></div>

            <div class="panel">
                <div class="controls">
                    <label for="startDate">From</label>
                    <input type="date" id="startDate">

                    <label for="endDate">To</label>
                    <input type="date" id="endDate">

                    <label for="numberPages">Number of Pages:</label>
                    <input type="number" id="numberPages" value="6" min="2" max="20">

                    <button id="loadBtn">Load</button>
                </div>

                <h2>Most Visited Pages</h2>
                <canvas id="topPagesChart" width="400" height="400"></canvas>
            </div>
        </div>

        <script>
        (function () {
            let chartInstance = null;

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
                const topCount = parseInt(document.getElementById('numberPages').value, 10) || 6;

                const url = '/api/index.php/pageviews?start=' +
                    encodeURIComponent(start) + '&end=' + encodeURIComponent(end);

                try {
                    const resp = await fetch(url, { credentials: 'include' });
                    const json = await resp.json();

                    if (!json.success) {
                        showError(json.error || 'Failed to load pageview data.');
                        return;
                    }

                    const topPages = json.data.topPages || [];
                    renderPieChart(topPages, topCount);
                } catch (err) {
                    showError('Network error: could not load pageview data.');
                }
            }

            function simplifyUrl(url) {
                try {
                    const u = new URL(url);
                    return u.pathname + u.search;
                } catch (e) {
                    return url;
                }
            }

            function groupTopPages(topPages, maxSlices) {
                if (topPages.length <= maxSlices) return topPages;

                const top = topPages.slice(0, maxSlices - 1);
                const rest = topPages.slice(maxSlices - 1);

                const otherViews = rest.reduce((sum, row) => sum + Number(row.views || 0), 0);

                top.push({
                    url: 'Other',
                    views: otherViews
                });

                return top;
            }

            function generateColors(count) {
                const colors = [];

                for (let i = 0; i < count; i++) {

                    const hue = Math.round((360 / count) * i);

                    colors.push(`hsl(${hue}, 65%, 55%)`);
                }

                return colors;
            }

            function renderPieChart(topPages, maxSlices) {
                const ctx = document.getElementById('topPagesChart').getContext('2d');

                if (chartInstance) {
                    chartInstance.destroy();
                }

                if (!topPages.length) {
                    chartInstance = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: ['No data'],
                            datasets: [{
                                data: [1]
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                    return;
                }

                const grouped = groupTopPages(topPages, maxSlices);
                const labels = grouped.map(row => simplifyUrl(row.url));
                const values = grouped.map(row => Number(row.views));

                const colors = generateColors(values.length);

                chartInstance = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: colors
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    font: {
                                        size: 14,
                                        family: 'sans-serif',
                                        weight: 'bold'
                                    },
                                    padding: 20,
                                    boxWidth: 15
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.raw || 0;
                                        return label + ': ' + value + ' views';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        })();
        </script>
    </body>
</html>