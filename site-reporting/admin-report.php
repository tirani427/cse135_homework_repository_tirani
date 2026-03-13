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
    <title>User Management - Analytics Dashboard</title>
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
        .header h1 {
            font-size: 1.3em;
            font-weight: 600;
        }
        .header a {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            font-size: 0.9em;
        }
        .header a:hover {
            color: white;
        }
        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 24px;
            margin-bottom: 24px;
        }
        .card h2 {
            font-size: 1.15em;
            color: #333;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid #2E86C1;
        }
        .table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95em;
            min-width: 500px;
        }
        th, td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
            font-size: 0.85em;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        tr:hover td {
            background: #f8fbfd;
        }
        .role-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .role-owner {
            background: #fdebd0;
            color: #b45309;
        }
        .role-admin {
            background: #d4efdf;
            color: #1e8449;
        }
        .role-viewer {
            background: #e8f4fc;
            color: #2471a3;
        }
        .btn {
            padding: 5px 12px;
            border: none;
            border-radius: 4px;
            font-size: 0.85em;
            cursor: pointer;
            font-family: inherit;
            transition: background 0.2s;
        }
        .btn-edit {
            background: #e8f4fc;
            color: #2E86C1;
        }
        .btn-edit:hover {
            background: #d1e9f7;
        }
        .btn-delete {
            background: #fdecea;
            color: #c0392b;
            margin-left: 6px;
        }
        .btn-delete:hover {
            background: #f5c6cb;
        }
        .form-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .form-group {
            flex: 1;
            min-width: 140px;
        }
        .form-group label {
            display: block;
            font-size: 0.8em;
            font-weight: 600;
            color: #555;
            margin-bottom: 4px;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #d0d0d0;
            border-radius: 4px;
            font-size: 0.95em;
            font-family: inherit;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2E86C1;
            box-shadow: 0 0 0 3px rgba(46,134,193,0.15);
        }
        .btn-submit {
            padding: 8px 20px;
            background: #2E86C1;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 0.95em;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: background 0.2s;
            white-space: nowrap;
            margin-top: 4px;
        }
        .btn-submit:hover {
            background: #2471a3;
        }
        .btn-submit:disabled {
            background: #85c1e9;
            cursor: not-allowed;
        }
        .message {
            padding: 10px 14px;
            border-radius: 4px;
            font-size: 0.9em;
            margin-bottom: 16px;
        }
        .message-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message-error {
            background: #fdecea;
            color: #c0392b;
            border: 1px solid #f5c6cb;
        }
        .empty-state {
            text-align: center;
            padding: 30px;
            color: #888;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>User Management</h1>
        <a href="/index.html">Sign Out</a>
    </div>

    <div class="container">
        <div id="message-area"></div>

        <div class="card">
            <h2>Users</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Display Name</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="user-table-body">
                        <tr><td colspan="4" class="empty-state">Loading users...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <h2>Add User</h2>
            <form id="add-user-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="new-email">Email</label>
                        <input type="email" id="new-email" placeholder="user@example.com" required>
                    </div>
                    <div class="form-group">
                        <label for="new-name">Display Name</label>
                        <input type="text" id="new-name" placeholder="Jane Doe" required>
                    </div>
                    <div class="form-group">
                        <label for="new-password">Password</label>
                        <input type="password" id="new-password" placeholder="Min 8 characters" required>
                    </div>
                    <div class="form-group" style="min-width: 110px; flex: 0.5;">
                        <label for="new-role">Role</label>
                        <select id="new-role">
                            <option value="viewer">Viewer</option>
                            <option value="admin">Admin</option>
                            <option value="owner">Owner</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-submit">Add User</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function() {
        'use strict';

        const tbody = document.getElementById('user-table-body');
        const form = document.getElementById('add-user-form');
        const messageArea = document.getElementById('message-area');

        function showMessage(text, type) {
            const div = document.createElement('div');
            div.className = 'message message-' + type;
            div.textContent = text;
            messageArea.innerHTML = '';
            messageArea.appendChild(div);
            setTimeout(function() { div.remove(); }, 4000);
        }

        function roleBadgeClass(role) {
            if (role === 'owner') return 'role-badge role-owner';
            if (role === 'admin') return 'role-badge role-admin';
            return 'role-badge role-viewer';
        }

        async function loadUsers() {
            try {
                const res = await fetch('/api/index.php/users', { credentials: 'include' });
                if (res.status === 401) {
                    window.location.href = '/index.html';
                    return;
                }
                if (res.status === 403) {
                    tbody.innerHTML = '';
                    var td = document.createElement('td');
                    td.colSpan = 4;
                    td.className = 'empty-state';
                    td.textContent = 'Access denied. Admin role required.';
                    var tr = document.createElement('tr');
                    tr.appendChild(td);
                    tbody.appendChild(tr);
                    return;
                }
                const data = await res.json();
                if (!data.success) throw new Error(data.error);

                tbody.innerHTML = '';
                if (data.data.length === 0) {
                    var emptyTd = document.createElement('td');
                    emptyTd.colSpan = 4;
                    emptyTd.className = 'empty-state';
                    emptyTd.textContent = 'No users found.';
                    var emptyTr = document.createElement('tr');
                    emptyTr.appendChild(emptyTd);
                    tbody.appendChild(emptyTr);
                    return;
                }

                data.data.forEach(function(user) {
                    var tr = document.createElement('tr');

                    var tdEmail = document.createElement('td');
                    tdEmail.textContent = user.email;
                    tr.appendChild(tdEmail);

                    var tdName = document.createElement('td');
                    tdName.textContent = user.display_name;
                    tr.appendChild(tdName);

                    var tdRole = document.createElement('td');
                    var badge = document.createElement('span');
                    badge.className = roleBadgeClass(user.role);
                    badge.textContent = user.role;
                    tdRole.appendChild(badge);
                    tr.appendChild(tdRole);

                    var tdActions = document.createElement('td');
                    var editBtn = document.createElement('button');
                    editBtn.className = 'btn btn-edit';
                    editBtn.textContent = 'Edit';
                    editBtn.addEventListener('click', function() { editUser(user); });
                    tdActions.appendChild(editBtn);

                    var deleteBtn = document.createElement('button');
                    deleteBtn.className = 'btn btn-delete';
                    deleteBtn.textContent = 'Delete';
                    deleteBtn.addEventListener('click', function() { deleteUser(user.id, user.email); });
                    tdActions.appendChild(deleteBtn);

                    tr.appendChild(tdActions);
                    tbody.appendChild(tr);
                });
            } catch (err) {
                showMessage('Failed to load users: ' + err.message, 'error');
            }
        }

        function editUser(user) {
            var newName = prompt('Display name:', user.display_name);
            if (newName === null) return;
            var newRole = prompt('Role (owner, admin, viewer):', user.role);
            if (newRole === null) return;

            fetch('/api/index.php/users/' + user.id, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ displayName: newName, role: newRole })
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    showMessage('User updated.', 'success');
                    loadUsers();
                } else {
                    showMessage(data.error || 'Update failed.', 'error');
                }
            })
            .catch(function() { showMessage('Network error.', 'error'); });
        }

        function deleteUser(id, email) {
            if (!confirm('Delete user ' + email + '? This cannot be undone.')) return;

            fetch('/api/index.php/users/' + id, {
                method: 'DELETE',
                credentials: 'include'
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    showMessage('User deleted.', 'success');
                    loadUsers();
                } else {
                    showMessage(data.error || 'Delete failed.', 'error');
                }
            })
            .catch(function() { showMessage('Network error.', 'error'); });
        }

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            var submitBtn = form.querySelector('.btn-submit');
            submitBtn.disabled = true;

            var payload = {
                email: document.getElementById('new-email').value,
                displayName: document.getElementById('new-name').value,
                password: document.getElementById('new-password').value,
                role: document.getElementById('new-role').value
            };

            try {
                var res = await fetch('/api/index.php/users', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify(payload)
                });
                var data = await res.json();
                if (data.success) {
                    showMessage('User created.', 'success');
                    form.reset();
                    loadUsers();
                } else {
                    showMessage(data.error || 'Create failed.', 'error');
                }
            } catch (err) {
                showMessage('Network error.', 'error');
            } finally {
                submitBtn.disabled = false;
            }
        });

        loadUsers();
    })();
    </script>
</body>
</html>