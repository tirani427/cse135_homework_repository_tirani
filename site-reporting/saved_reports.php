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
    <title>Saved Reports - Analytics Dashboard</title>
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

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .header-actions a,
        .header-actions button {
            text-decoration: none;
            background: white;
            color: #2E86C1;
            border: none;
            border-radius: 6px;
            padding: 8px 14px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.9em;
        }

        .header-actions a:hover,
        .header-actions button:hover {
            background: #e8f4fc;
        }

        .container {
            max-width: 1100px;
            margin: 24px auto;
            padding: 0 20px;
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

        .toolbar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .toolbar input[type="text"] {
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            min-width: 260px;
            font: inherit;
        }

        .toolbar select {
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font: inherit;
            background: white;
        }

        .error-msg,
        .success-msg {
            padding: 12px 16px;
            margin-bottom: 16px;
            border-radius: 6px;
            display: none;
        }

        .error-msg {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }

        .success-msg {
            background: #d1e7dd;
            border-left: 4px solid #198754;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }

        th {
            background: #f8f9fa;
            color: #555;
            font-size: 0.8em;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            font-weight: 600;
        }

        td {
            font-size: 0.92em;
        }

        tr:hover td {
            background: #f8f9fa;
        }

        .title-cell {
            max-width: 280px;
        }

        .title-main {
            font-weight: 600;
            color: #2E86C1;
            margin-bottom: 4px;
        }

        .title-sub {
            color: #777;
            font-size: 0.86em;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            background: #eef4fa;
            color: #2E86C1;
            font-size: 0.8em;
            font-weight: 600;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .actions a,
        .actions button {
            text-decoration: none;
            border: none;
            border-radius: 6px;
            padding: 7px 12px;
            font-size: 0.86em;
            font-weight: 600;
            cursor: pointer;
        }

        .actions a.primary,
        .actions button.primary {
            background: #2E86C1;
            color: white;
        }

        .actions a.secondary,
        .actions button.secondary {
            background: #6c757d;
            color: white;
        }

        .actions a:hover,
        .actions button:hover {
            opacity: 0.95;
        }

        .empty-state {
            text-align: center;
            color: #888;
            padding: 40px 20px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 1000;
            padding: 24px;
        }

        .modal-content {
            background: white;
            width: min(1100px, 100%);
            height: 88vh;
            margin: 0 auto;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.25);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            border-bottom: 1px solid #e5e5e5;
            background: #fafafa;
        }

        .modal-header h3 {
            color: #2E86C1;
            font-size: 1.05em;
        }

        .modal-close {
            border: none;
            background: #2E86C1;
            color: white;
            border-radius: 6px;
            padding: 8px 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .modal-body {
            flex: 1;
        }

        #reportFrame {
            width: 100%;
            height: 100%;
            border: none;
        }

        @media (max-width: 700px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            th, td {
                padding: 8px 6px;
                font-size: 0.86em;
            }

            .title-cell {
                max-width: 180px;
            }

            .modal {
                padding: 10px;
            }

            .modal-content {
                height: 92vh;
            }
        }

        .noscript-warning {
            max-width: 900px;
            margin: 24px auto;
            padding: 20px 24px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 8px;
            color: #664d03;
        }

        .noscript-warning h2 {
            font-size: 1.1em;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="js-app">
        <div class="header">
            <h1>Saved Reports</h1>
            <div class="header-actions">
                <a href="/reports.php">Build Report</a>
                <button id="logout-btn">Logout</button>
            </div>
        </div>

        <div class="container">
            <div id="errorBox" class="error-msg"></div>
            <div id="successBox" class="success-msg"></div>

            <div class="panel">
                <h2>Report Library</h2>

                <div class="toolbar">
                    <input type="text" id="searchInput" placeholder="Search report title or creator">
                    <select id="typeFilter">
                        <option value="">All Types</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Report</th>
                                <th>Created By</th>
                                <th>Type</th>
                                <th>Date Range</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="reportsBody">
                            <tr>
                                <td colspan="6" class="empty-state">Loading saved reports...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="reportModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modalTitle">Saved Report Preview</h3>
                    <button id="closeModal" class="modal-close">Close</button>
                </div>
                <div class="modal-body">
                    <iframe id="reportFrame" src="" title="Saved Report"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        let allReports = [];

        document.getElementById('searchInput').addEventListener('input', renderFilteredReports);
        document.getElementById('typeFilter').addEventListener('change', renderFilteredReports);

        document.getElementById('closeModal').addEventListener('click', closeReportModal);
        document.getElementById('reportModal').addEventListener('click', function (e) {
            if (e.target.id === 'reportModal') {
                closeReportModal();
            }
        });

        document.addEventListener('click', function (e) {
            if (e.target.id === 'logout-btn') {
                fetch('/api/index.php/logout', {
                    method: 'POST',
                    credentials: 'include'
                }).then(() => {
                    window.location.href = '/index.html';
                });
            }
        });

        loadReports();

        function showError(msg) {
            const box = document.getElementById('errorBox');
            box.textContent = msg;
            box.style.display = 'block';
            document.getElementById('successBox').style.display = 'none';
        }

        function showSuccess(msg) {
            const box = document.getElementById('successBox');
            box.textContent = msg;
            box.style.display = 'block';
            document.getElementById('errorBox').style.display = 'none';
        }

        function escapeHtml(str) {
            return String(str ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#39;');
        }

        async function loadReports() {
            try {
                const resp = await fetch('/api/index.php/saved-reports', {
                    credentials: 'include'
                });

                if (resp.status === 401) {
                    window.location.href = '/index.html';
                    return;
                }

                if (resp.status === 403) {
                    window.location.href = '/403.html';
                    return;
                }

                const json = await resp.json();

                if (!json.success) {
                    showError(json.error || 'Failed to load saved reports.');
                    return;
                }

                allReports = Array.isArray(json.data) ? json.data : [];
                renderFilteredReports();
            } catch (err) {
                showError('Network error: could not reach the saved reports API.');
            }
        }

        function renderFilteredReports() {
            const query = document.getElementById('searchInput').value.trim().toLowerCase();
            const type = document.getElementById('typeFilter').value;

            const filtered = allReports.filter(row => {
                const title = (row.title || '').toLowerCase();
                const creator = (row.display_name || '').toLowerCase();
                const rowType = row.report_type || '';

                const matchesQuery = !query || title.includes(query) || creator.includes(query);
                const matchesType = !type || rowType === type;

                return matchesQuery && matchesType;
            });

            renderReports(filtered);
        }

        function renderReports(reports) {
            const tbody = document.getElementById('reportsBody');
            tbody.innerHTML = '';

            if (!reports.length) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 6;
                td.className = 'empty-state';
                td.textContent = 'No saved reports found.';
                tr.appendChild(td);
                tbody.appendChild(tr);
                return;
            }

            reports.forEach(row => {
                const tr = document.createElement('tr');

                const titleTd = document.createElement('td');
                titleTd.className = 'title-cell';
                titleTd.innerHTML = `
                    <div class="title-main">${escapeHtml(row.title || 'Untitled Report')}</div>
                    <div class="title-sub">Token: ${escapeHtml(row.share_token || '—')}</div>
                `;
                tr.appendChild(titleTd);

                const userTd = document.createElement('td');
                userTd.textContent = row.display_name || 'Unknown';
                tr.appendChild(userTd);

                const typeTd = document.createElement('td');
                typeTd.innerHTML = `<span class="badge">${escapeHtml(row.report_type || 'custom')}</span>`;
                tr.appendChild(typeTd);

                const rangeTd = document.createElement('td');
                rangeTd.textContent = (row.start_date || '—') + ' to ' + (row.end_date || '—');
                tr.appendChild(rangeTd);

                const createdTd = document.createElement('td');
                createdTd.textContent = row.created_at || '—';
                tr.appendChild(createdTd);

                const actionsTd = document.createElement('td');
                actionsTd.className = 'actions';
                actionsTd.innerHTML = `
                    <a class="primary" href="/report-view.php?token=${encodeURIComponent(row.share_token)}" target="_blank">Open</a>
                    <button class="secondary" data-token="${escapeHtml(row.share_token)}" data-title="${escapeHtml(row.title || 'Saved Report')}">Quick View</button>
                `;
                tr.appendChild(actionsTd);

                tbody.appendChild(tr);
            });

            tbody.querySelectorAll('button[data-token]').forEach(btn => {
                btn.addEventListener('click', function () {
                    openReportModal(this.dataset.token, this.dataset.title);
                });
            });
        }

        function openReportModal(token, title) {
            document.getElementById('modalTitle').textContent = title || 'Saved Report Preview';
            document.getElementById('reportFrame').src = '/report-view.php?token=' + encodeURIComponent(token);
            document.getElementById('reportModal').style.display = 'block';
            showSuccess('Opened saved report preview.');
        }

        function closeReportModal() {
            document.getElementById('reportModal').style.display = 'none';
            document.getElementById('reportFrame').src = '';
        }
    })();
    </script>
</body>
</html>