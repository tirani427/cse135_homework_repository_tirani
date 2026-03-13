<?php
session_start();

if(!isset($_SESSION['user'])){
    header("Location: /401.html");
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
        <h1>Reports</h1>
        <div class="date-controls">
            <label for="startDate">From</label>
            <input type="date" id="startDate">
            <label for="endDate">To</label>
            <input type="date" id="endDate">
            <button id="loadBtn">Load</button>
        </div>
    </div>
</body>
</html>