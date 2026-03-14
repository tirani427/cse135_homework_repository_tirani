<?php
session_start();

$cfg = require "/etc/cse135/collector_db.php";

try {
    $pdo = new PDO(
        $cfg["dsn"],
        $cfg["user"],
        $cfg["pass"],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (Exception $e) {
    http_response_code(500);
    echo "Database connection failed.";
    exit();
}

$token = $_GET['token'] ?? '';
if ($token === '') {
    http_response_code(404);
    echo "Report not found.";
    exit();
}

$stmt = $pdo->prepare("
    SELECT
        sr.title,
        sr.snapshot,
        sr.created_at,
        u.display_name
    FROM saved_reports sr
    LEFT JOIN users u
        ON sr.created_by = u.id
    WHERE sr.share_token = :token
    LIMIT 1
");
$stmt->execute([':token' => $token]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    echo "Report not found.";
    exit();
}

$snapshot = json_decode($row['snapshot'], true);
if (!is_array($snapshot)) {
    http_response_code(500);
    echo "Invalid report snapshot.";
    exit();
}

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$title = $snapshot['title'] ?? $row['title'] ?? 'Saved Report';
$start = $snapshot['start'] ?? '';
$end = $snapshot['end'] ?? '';
$sections = $snapshot['sections'] ?? [];
$createdBy = $row['display_name'] ?? 'Unknown';
$createdAt = $row['created_at'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }

        .header {
            background: #2E86C1;
            color: white;
            padding: 24px 28px;
        }

        .header h1 {
            font-size: 1.6em;
            margin-bottom: 8px;
        }

        .meta {
            font-size: 0.95em;
            opacity: 0.95;
        }

        .container {
            max-width: 1100px;
            margin: 24px auto;
            padding: 0 20px 40px;
        }

        .panel {
            background: white;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .panel h2 {
            color: #2E86C1;
            font-size: 1.2em;
            margin-bottom: 14px;
        }

        .comment-box {
            background: #f8f9fa;
            border-left: 4px solid #2E86C1;
            padding: 14px 16px;
            border-radius: 4px;
            margin-bottom: 18px;
            white-space: pre-wrap;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .summary-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 16px;
        }

        .summary-card .label {
            font-size: 0.78em;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #666;
            margin-bottom: 6px;
        }

        .summary-card .value {
            font-size: 1.4em;
            font-weight: 700;
            color: #2E86C1;
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

        td {
            font-size: 0.92em;
        }

        tr:hover td {
            background: #fafafa;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .muted {
            color: #777;
            font-size: 0.95em;
        }

        @media print {
            body {
                background: white;
            }

            .panel {
                box-shadow: none;
                border: 1px solid #ddd;
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?= h($title) ?></h1>
        <div class="meta">
            Date Range: <?= h($start) ?> to <?= h($end) ?><br>
            Created By: <?= h($createdBy) ?><br>
            Created At: <?= h($createdAt) ?>
        </div>
    </div>

    <div class="container">

        <?php if (isset($sections['performance'])): ?>
            <?php $performance = $sections['performance']; ?>
            <div class="panel">
                <h2>Performance</h2>

                <?php if (!empty($performance['comment'])): ?>
                    <div class="comment-box">
                        <strong>Analyst Comment:</strong><br>
                        <?= nl2br(h($performance['comment'])) ?>
                    </div>
                <?php endif; ?>

                <?php
                $perfRows = $performance['byPage'] ?? [];
                $avgTtfb = null;
                $avgLcp = null;
                $avgCls = null;
                if (!empty($perfRows)) {
                    $ttfbVals = array_filter(array_column($perfRows, 'avg_ttfb'), fn($v) => $v !== null);
                    $lcpVals = array_filter(array_column($perfRows, 'avg_lcp'), fn($v) => $v !== null);
                    $clsVals = array_filter(array_column($perfRows, 'avg_cls'), fn($v) => $v !== null);

                    $avgTtfb = count($ttfbVals) ? round(array_sum($ttfbVals) / count($ttfbVals), 2) : null;
                    $avgLcp = count($lcpVals) ? round(array_sum($lcpVals) / count($lcpVals), 2) : null;
                    $avgCls = count($clsVals) ? round(array_sum($clsVals) / count($clsVals), 4) : null;
                }
                ?>

                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="label">Avg TTFB</div>
                        <div class="value"><?= h($avgTtfb ?? '—') ?></div>
                    </div>
                    <div class="summary-card">
                        <div class="label">Avg LCP</div>
                        <div class="value"><?= h($avgLcp ?? '—') ?></div>
                    </div>
                    <div class="summary-card">
                        <div class="label">Avg CLS</div>
                        <div class="value"><?= h($avgCls ?? '—') ?></div>
                    </div>
                    <div class="summary-card">
                        <div class="label">Pages Included</div>
                        <div class="value"><?= h(count($perfRows)) ?></div>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>URL</th>
                                <th>Samples</th>
                                <th>Avg Load Time</th>
                                <th>Avg TTFB</th>
                                <th>Avg LCP</th>
                                <th>Avg CLS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($perfRows as $row): ?>
                                <tr>
                                    <td><?= h($row['url'] ?? '—') ?></td>
                                    <td><?= h($row['samples'] ?? '—') ?></td>
                                    <td><?= h($row['avg_load_time'] ?? '—') ?></td>
                                    <td><?= h($row['avg_ttfb'] ?? '—') ?></td>
                                    <td><?= h($row['avg_lcp'] ?? '—') ?></td>
                                    <td><?= h($row['avg_cls'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($sections['errors'])): ?>
            <?php $errors = $sections['errors']; ?>
            <div class="panel">
                <h2>Errors</h2>

                <?php if (!empty($errors['comment'])): ?>
                    <div class="comment-box">
                        <strong>Analyst Comment:</strong><br>
                        <?= nl2br(h($errors['comment'])) ?>
                    </div>
                <?php endif; ?>

                <?php $errorRows = $errors['topErrors'] ?? []; ?>

                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="label">Unique Error Types</div>
                        <div class="value"><?= h(count($errorRows)) ?></div>
                    </div>
                    <div class="summary-card">
                        <div class="label">Top Error Occurrences</div>
                        <div class="value"><?= h($errorRows[0]['occurrences'] ?? '—') ?></div>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Error Message</th>
                                <th>Occurrences</th>
                                <th>Last Seen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($errorRows as $row): ?>
                                <tr>
                                    <td><?= h($row['error_message'] ?? '—') ?></td>
                                    <td><?= h($row['occurrences'] ?? '—') ?></td>
                                    <td><?= h($row['last_seen'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($sections['pageviews'])): ?>
            <?php $pageviews = $sections['pageviews']; ?>
            <div class="panel">
                <h2>Pageviews</h2>

                <?php if (!empty($pageviews['comment'])): ?>
                    <div class="comment-box">
                        <strong>Analyst Comment:</strong><br>
                        <?= nl2br(h($pageviews['comment'])) ?>
                    </div>
                <?php endif; ?>

                <?php $pageRows = $pageviews['topPages'] ?? []; ?>

                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="label">Tracked Pages</div>
                        <div class="value"><?= h(count($pageRows)) ?></div>
                    </div>
                    <div class="summary-card">
                        <div class="label">Top Page Views</div>
                        <div class="value"><?= h($pageRows[0]['views'] ?? '—') ?></div>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>URL</th>
                                <th>Views</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pageRows as $row): ?>
                                <tr>
                                    <td><?= h($row['url'] ?? '—') ?></td>
                                    <td><?= h($row['views'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($sections['sessions'])): ?>
            <?php $sessions = $sections['sessions']; ?>
            <?php $stats = $sessions['stats'] ?? []; ?>
            <div class="panel">
                <h2>Sessions</h2>

                <?php if (!empty($sessions['comment'])): ?>
                    <div class="comment-box">
                        <strong>Analyst Comment:</strong><br>
                        <?= nl2br(h($sessions['comment'])) ?>
                    </div>
                <?php endif; ?>

                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="label">Avg Session Duration</div>
                        <div class="value"><?= h($stats['avg_session_duration'] ?? '—') ?></div>
                    </div>
                    <div class="summary-card">
                        <div class="label">Avg Pages / Session</div>
                        <div class="value"><?= h($stats['avg_pages_per_session'] ?? '—') ?></div>
                    </div>
                    <div class="summary-card">
                        <div class="label">Bounce Rate</div>
                        <div class="value"><?= h($stats['bounce_rate'] ?? '—') ?></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($sections['events'])): ?>
            <?php $events = $sections['events']; ?>
            <?php $eventRows = $events['topEvents'] ?? []; ?>
            <div class="panel">
                <h2>Events</h2>

                <?php if (!empty($events['comment'])): ?>
                    <div class="comment-box">
                        <strong>Analyst Comment:</strong><br>
                        <?= nl2br(h($events['comment'])) ?>
                    </div>
                <?php endif; ?>

                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="label">Tracked Event Types</div>
                        <div class="value"><?= h(count($eventRows)) ?></div>
                    </div>
                    <div class="summary-card">
                        <div class="label">Top Event Count</div>
                        <div class="value"><?= h($eventRows[0]['occurrences'] ?? '—') ?></div>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Event Name</th>
                                <th>Occurrences</th>
                                <th>Last Seen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eventRows as $row): ?>
                                <tr>
                                    <td><?= h($row['event_name'] ?? '—') ?></td>
                                    <td><?= h($row['occurrences'] ?? '—') ?></td>
                                    <td><?= h($row['last_seen'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($sections)): ?>
            <div class="panel">
                <h2>No Sections Available</h2>
                <p class="muted">This saved report does not contain any renderable sections.</p>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>