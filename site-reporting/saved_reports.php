<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: /index.html");
    exit();
}

require_once __DIR__ . '/api/db.php';

$stmt = $pdo->prepare("
    SELECT
        sr.id,
        sr.title,
        sr.report_type,
        sr.start_date,
        sr.end_date,
        sr.created_at,
        sr.share_token,
        u.display_name
    FROM saved_reports sr
    LEFT JOIN users u
        ON sr.created_by = u.id
    ORDER BY sr.created_at DESC
");

$stmt->execute();
$reports = $stmt->fetchAll();
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
    </style>
</head>
<body>

    <noscript>
        <div class="noscript-warning">
            <h2>JavaScript is disabled</h2>
            <p>Saved reports are still available below, but search, filtering, and quick preview are disabled.</p>
        </div>
    </noscript>

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
                            <?php if (empty($reports)): ?>
                                <tr>
                                    <td colspan="6" class="empty-state">No saved reports found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($reports as $row): ?>
                                    <tr
                                        data-title="<?= htmlspecialchars(strtolower($row['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-creator="<?= htmlspecialchars(strtolower($row['display_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-type="<?= htmlspecialchars($row['report_type'] ?? 'custom', ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                        <td class="title-cell">
                                            <div class="title-main"><?= htmlspecialchars($row['title'] ?? 'Untitled Report') ?></div>
                                            <div class="title-sub">Token: <?= htmlspecialchars($row['share_token'] ?? '—') ?></div>
                                        </td>

                                        <td><?= htmlspecialchars($row['display_name'] ?? 'Unknown') ?></td>

                                        <td>
                                            <span class="badge"><?= htmlspecialchars($row['report_type'] ?? 'custom') ?></span>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($row['start_date'] ?? '—') ?>
                                            to
                                            <?= htmlspecialchars($row['end_date'] ?? '—') ?>
                                        </td>

                                        <td><?= htmlspecialchars($row['created_at'] ?? '—') ?></td>

                                        <td class="actions">
                                            <a
                                                class="primary"
                                                href="/report-view.php?token=<?= urlencode($row['share_token'] ?? '') ?>"
                                                target="_blank"
                                            >
                                                Open
                                            </a>

                                            <button
                                                class="secondary quick-view-btn"
                                                data-token="<?= htmlspecialchars($row['share_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                data-title="<?= htmlspecialchars($row['title'] ?? 'Saved Report', ENT_QUOTES, 'UTF-8') ?>"
                                            >
                                                Quick View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
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
        const searchInput = document.getElementById('searchInput');
        const typeFilter = document.getElementById('typeFilter');
        const reportsBody = document.getElementById('reportsBody');

        searchInput?.addEventListener('input', filterRows);
        typeFilter?.addEventListener('change', filterRows);

        bindQuickViewButtons();

        document.getElementById('closeModal')?.addEventListener('click', closeReportModal);

        document.getElementById('reportModal')?.addEventListener('click', function (e) {
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

        function showSuccess(msg) {
            const box = document.getElementById('successBox');
            box.textContent = msg;
            box.style.display = 'block';
            document.getElementById('errorBox').style.display = 'none';
        }

        function filterRows() {
            const query = (searchInput?.value || '').trim().toLowerCase();
            const selectedType = typeFilter?.value || '';

            const rows = reportsBody.querySelectorAll('tr[data-title]');
            let visibleCount = 0;

            rows.forEach(row => {
                const title = row.dataset.title || '';
                const creator = row.dataset.creator || '';
                const type = row.dataset.type || '';

                const matchesQuery =
                    !query || title.includes(query) || creator.includes(query);

                const matchesType =
                    !selectedType || type === selectedType;

                const show = matchesQuery && matchesType;
                row.style.display = show ? '' : 'none';

                if (show) visibleCount++;
            });

            updateEmptyState(visibleCount);
        }

        function updateEmptyState(visibleCount) {
            let emptyRow = document.getElementById('js-empty-row');

            if (visibleCount === 0) {
                if (!emptyRow) {
                    emptyRow = document.createElement('tr');
                    emptyRow.id = 'js-empty-row';

                    const td = document.createElement('td');
                    td.colSpan = 6;
                    td.className = 'empty-state';
                    td.textContent = 'No saved reports match the current filters.';

                    emptyRow.appendChild(td);
                    reportsBody.appendChild(emptyRow);
                }
            } else if (emptyRow) {
                emptyRow.remove();
            }
        }

        function bindQuickViewButtons() {
            document.querySelectorAll('.quick-view-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    openReportModal(this.dataset.token, this.dataset.title);
                });
            });
        }

        function openReportModal(token, title) {
            document.getElementById('modalTitle').textContent = title || 'Saved Report Preview';
            document.getElementById('reportFrame').src =
                '/report-view.php?token=' + encodeURIComponent(token);
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