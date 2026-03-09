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
    <title>Dashboard</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            display: flex;
            min-height: 100vh;
            background: #f7f7f7;
        }

        .sidebar {
            width: 220px;
            background: #1f2937;
            color: white;
            padding: 1rem;
            box-sizing: border-box;
        }

        .sidebar h2 {
            margin-top: 0;
            font-size: 1.2rem;
        }

        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 0.6rem 0.75rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .sidebar a.active,
        .sidebar a:hover {
            background: #374151;
        }

        .main {
            flex: 1;
            padding: 1rem 1.5rem;
            box-sizing: border-box;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .filters {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .metric-card {
            background: white;
            padding: 1rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        .metric-label {
            color: #666;
            margin-bottom: 0.35rem;
        }

        .metric-value {
            font-size: 1.5rem;
            font-weight: bold;
        }

        #content {
            background: white;
            padding: 1rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th, td {
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            padding: 0.75rem;
        }

        #logout-btn {
            padding: 0.6rem 1rem;
            border: none;
            border-radius: 8px;
            background: #1f6feb;
            color: white;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <nav class="sidebar">
        <h2>Analytics</h2>
        <a href="/dashboard.php" class="active">Overview</a>
        <a href="/speed-reporting.php">Performance</a>
        <a href="/error-report.php">Errors</a>
        <a href="#/admin">Admin</a>
    </nav>

    <main class="main">
        <div class="topbar">
            <div class="filters">
                <label>
                    Start
                    <input type="date" id="date-start">
                </label>
                <label>
                    End
                    <input type="date" id="date-end">
                </label>
            </div>

            <button id="logout-btn">Logout</button>
        </div>

        <div id="content"></div>
    </main>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            loadOverview();
        });
        const today = new Date();
        const thirtyDaysAgo = new Date(Date.now() - 30 * 86400000);

        document.getElementById('date-end').value = today.toISOString().slice(0, 10);
        document.getElementById('date-start').value = thirtyDaysAgo.toISOString().slice(0, 10);
    </script>
    <script src="./dashboard.js"></script>
</body>
</html>