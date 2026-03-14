<?php
session_start();

if(!isset($_SESSION['user'])){
    header("Location: /401.html");
    exit();
}

if($_SESSION['user']['role'] == 'viewer'){
    header("Location: /403.html");
    exit();
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Builder - Analytics Dashboard</title>
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
            display:flex;
            flex-direction: column;
            gap: 10px;
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
        .success-msg {
            background: #d1e7dd;
            border-left: 4px solid #198754;
            padding: 12px 16px;
            margin-bottom: 16px;
            border-radius: 4px;
            display: none;
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

        .field{
            display:flex;
            flex-direction: column;
            width: inherit;
        }
        .commentText{
            width: inherit;
        }
        .button-row button{
            padding: 6px 16px;
            background: #2E86C1;
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.85em;
        }
    </style>
</head>
<body>
    <noscript>
        <style>
            .js-app { display: none; }
        </style>
        <div class="noscript-warning">
            <h2>JavaScript is turned off</h2>
            <p>This page requires JavaScript to load report data and charts.</p>
            <p>Please enable JavaScript and refresh the page.</p>
            <p>Saved static report pages can still be viewed directly without JavaScript.</p>
        </div>
    </noscript>

    <div class="js-app">
        <div class="header">
            <h1>Reports Builder</h1>
            <div class="date-controls">
                <!-- <label for="startDate">From</label>
                <input type="date" id="startDate">
                <label for="endDate">To</label>
                <input type="date" id="endDate">
                <button id="loadBtn">Load</button> -->
                <button id="logout-btn">Logout</button>
            </div>
            <!-- <button id="logout-btn">Logout</button> -->
        </div>
        <div class="container">
            <div id="errorBox" class="error-msg"></div>
            <div id="successBox" class="success-msg"></div>

            <div class="panel">
                <h2>Built Report</h2>

                <div class="controls-row">
                    <div class="field">
                        <label for="reportTitle">Report Title</label>
                        <input type="text" id="reportTitle" placeholder="March Site Health Report">
                    </div>

                    <div class="field">
                        <label for="startDate">From</label>
                        <input type="date" id="startDate">
                    </div>

                    <div class="field">
                        <label for="endDate">To</label>
                        <input type="date" id="endDate">
                    </div>
                </div>

                <h2>Include Sections</h2>
                <div class="section-grid">

                    <div class="section-card">
                        <label><input type="checkbox" value="performance" class="section-check" checked>Performance</label>
                        <p>TTFB, LCP, CLS, top pages by performance.</p>
                    </div>

                    <div class="section-card">
                        <label><input type="checkbox" value="errors" class="section-check" checked>Errors</label>
                        <p>Top error messages and error trends over time.</p>
                    </div>

                    <div class="section-card">
                        <label><input type="checkbox" value="pageviews" class="section-check" checked>Pageviews</label>
                        <p>Traffic summaries and most visited pages.</p>
                    </div>

                    <div class="section-card">
                        <label><input type="checkbox" value="sessions" class="section-check" checked>Sessions</label>
                        <p>Session count, duration, bounce rate, pages/sessions</p>
                    </div>

                    <div class="section-card">
                        <label><input type="checkbox" value="events" class="section-check" checked>Events</label>
                        <p>Top event types and event activity trend.</p>
                    </div>
                </div>
            </div>

            <div class="panel">
                <h2>Analyst Comments</h2>

                <div class="field">
                    <label for="performanceComment">Performance Comment</label>
                    <textarea class='commentText' id="performanceComment"></textarea>
                </div>

                <div class="field" style="margin-top:16px;">
                    <label for="errorsComment">Errors Comment</label>
                    <textarea class='commentText' id="errorsComment"></textarea>
                </div>

                <div class="field" style="margin-top:16px;">
                    <label for="pageviewsComment">Pageviews Comment</label>
                    <textarea class='commentText' id="pageviewsComment"></textarea>
                </div>

                <div class="field" style="margin-top:16px;">
                    <label for="sessionsComment">Sessions Comment</label>
                    <textarea class='commentText' id="sessionsComment"></textarea>
                </div>

                <div class="field" style="margin-top:16px;">
                    <label for="eventsComment">Events Comment</label>
                    <textarea class='commentText' id="eventsComment"></textarea>
                </div>

                <div class="button-row">
                    <button id="preview-button">Preview Report</button>
                    <button id="save-html-button">Create Static Report</button>
                    <button id="export-pdf-button">Export PDF</button>
                </div>
            </div>

            <div class="panel">
                <h2>Preview</h2>
                <div id="preview-area">
                    <div class="preview-card">
                        <h3>No preview yet</h3>
                        <p>Select sections and click "Preview Report".</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const today = new Date();
        const thirtyAgo = new Date(Date.now() - 30 * 86400000);

        document.getElementById('startDate').value = formatDate(thirtyAgo);
        document.getElementById('endDate').value = formatDate(today);

        document.getElementById('preview-button').addEventListener('click', () => submitReport('preview'));
        document.getElementById('save-html-button').addEventListener('click', () => submitReport('html'));
        document.getElementById('export-pdf-button').addEventListener('click', () => submitReport('pdf'));

        function formatDate(d){
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
        }

        function showError(msg){
            const box = document.getElementById('errorBox');
            box.textContent = msg;
            box.style.display = 'block';
            document.getElementById('successBox').style.display = 'none';
        }

        function showSuccess(msg){
            const box = document.getElementById('successBox');
            box.textContent = msg;
            box.style.display = 'block';
            document.getElementById('errorBox').style.display = 'none';
        }

        function getSelectedSections() {
            return Array.from(document.querySelectorAll('.section-check:checked')).map(input => input.value);
        }

        function buildPayload(format){
            return{
                title: document.getElementById('reportTitle').value.trim() || 'Untitled Report',
                start: document.getElementById('startDate').value,
                end: document.getElementById('endDate').value,
                sections: getSelectedSections(),
                comments: {
                    performance: document.getElementById('performanceComment').value.trim(),
                    errors: document.getElementById('errorsComment').value.trim(),
                    pageviews: document.getElementById('pageviewsComment').value.trim(),
                    sessions: document.getElementById('sessionsComment').value.trim(),
                    events: document.getElementById('eventsComment').value.trim()
                },
                format: format
            };
        }

        async function submitReport(format){
            const payload = buildPayload(format);

            if(!payload.start || !payload.end){
                showError("Please choose both a start and end date.");
                return;
            }
            if(payload.sections.length === 0){
                showError("Please select at least one report section");
                return;
            }

            try{
                const resp = await fetch('/api/index.php/exports',{
                    method: 'POST',
                    headers: {'Content-Type' : 'application/json'},
                    credentials: 'include',
                    body: JSON.stringify(payload)
                });

                // ADD IN CHECKS FOR ERRORS FROM RESP
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

                if(!json.success){
                    showError(json.error || 'Failed to generate report.');
                    return;
                }

                if(format === 'preview'){
                    renderPreview(json.data.snapshot);
                    showSuccess('Preview updated');
                } else if(format === 'html'){
                    showSuccess('Static report created: ' + json.data.url);
                    renderPreview(json.data.snapshot);
                } else if(format === 'pdf'){
                    showSuccess('PDF export created.');
                    renderPreview(json.data.snapshot);
                    if(json.data.url){
                        window.open(json.data.url, '_blank');
                    }
                }
            } catch (err) {
                showError('Could not reach export API.');
            }
        }

        function renderPreview(snapshot){
            const preview = document.getElementById('preview-area');
            preview.innerHTML = '';

            const sections = snapshot.sections || {};

            Object.keys(sections).forEach(name => {
                const card = document.createElement('div');
                card.className = 'preview-card';

                const h3 = document.createElement('h3');
                h3.textContent = name.charAt(0).toUpperCase() + name.slice(1);
                card.appendChild(h3);

                const meta = document.createElement('div');
                meta.className = 'preview-meta';
                meta.textContent = snapshot.title + ' | ' + snapshot.start + ' to ' + snapshot.end;
                card.appendChild(meta);

                const pre = document.createElement('pre');
                pre.style.whiteSpace = 'pre-wrap';
                pre.style.fontFamily = 'inherit';
                pre.textContent = JSON.stringify(sections[name], null, 2);
                card.appendChild(pre);

                preview.appendChild(card);
            });
        }

        document.addEventListener('click', e => {
            if (e.target.id === 'logout-btn') {
                fetch('/api/index.php/logout', { method: 'POST', credentials: 'include' })
                    .then(() => { window.location.href = '/index.html'; });
            }
        });

    })();
    </script>

</body>
</html>