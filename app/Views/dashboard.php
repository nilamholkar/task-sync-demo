<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Sync Engine</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            color: #1f2937;
        }

        .header {
            background: #111827;
            color: white;
            padding: 18px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            margin: 0;
            font-size: 22px;
        }

        .container {
            max-width: 1250px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .toolbar {
            background: white;
            padding: 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
        }

        input, select, textarea {
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }

        #search {
            width: 280px;
        }

        textarea {
            width: 100%;
            min-height: 100px;
            resize: vertical;
        }

        button {
            border: 0;
            border-radius: 6px;
            padding: 10px 15px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #111827;
        }

        .btn-danger {
            background: #dc2626;
            color: white;
        }

        .btn-warning {
            background: #d97706;
            color: white;
        }

        .btn-success {
            background: #16a34a;
            color: white;
        }

        .task-card {
            background: white;
            border-radius: 8px;
            margin-bottom: 12px;
            padding: 18px;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
        }

        .task-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1.5fr;
            gap: 15px;
            align-items: center;
        }

        .task-title {
            font-weight: bold;
            font-size: 16px;
        }

        .task-description {
            color: #6b7280;
            font-size: 13px;
            margin-top: 5px;
        }

        .label {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-in_progress {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-completed {
            background: #dcfce7;
            color: #166534;
        }

        .sync-synced {
            background: #dcfce7;
            color: #166534;
        }

        .sync-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .sync-conflict {
            background: #fee2e2;
            color: #991b1b;
        }

        .sync-error {
            background: #fecaca;
            color: #7f1d1d;
        }

        .actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .actions button {
            padding: 7px 10px;
            font-size: 12px;
        }

        .empty {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 8px;
        }

        .loading {
            text-align: center;
            padding: 30px;
        }

        .message {
            display: none;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .message.success {
            display: block;
            background: #dcfce7;
            color: #166534;
        }

        .message.error {
            display: block;
            background: #fee2e2;
            color: #991b1b;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.55);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-content {
            background: white;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 10px;
            padding: 25px;
        }

        .modal-content h2 {
            margin-top: 0;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        /* =========================
        Conflict UI
        ========================= */

        .conflict-modal-content {
            max-width: 1000px;
        }

        .conflict-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 15px;
        }

        .conflict-panel {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 18px;
            background: #f9fafb;
        }

        .conflict-panel.local {
            border-left: 5px solid #2563eb;
        }

        .conflict-panel.provider {
            border-left: 5px solid #16a34a;
        }

        .conflict-panel h3 {
            margin-top: 0;
            margin-bottom: 18px;
        }

        .conflict-field {
            margin-bottom: 14px;
        }

        .conflict-field label {
            display: block;
            font-size: 12px;
            color: #6b7280;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .conflict-value {
            background: white;
            border: 1px solid #e5e7eb;
            padding: 9px;
            border-radius: 5px;
            min-height: 20px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .manual-merge {
            margin-top: 20px;
            padding: 18px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #f9fafb;
        }

        .manual-merge h3 {
            margin-top: 0;
        }

        .conflict-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .conflict-loading {
            text-align: center;
            padding: 30px;
        }

        @media (max-width: 700px) {

            .conflict-grid {
                grid-template-columns: 1fr;
            }

        }
                /* Conflict UI */
        .conflict-modal-content {
            max-width: 1000px;
        }

        .conflict-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 15px;
        }

        .conflict-panel {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 18px;
            background: #f9fafb;
        }

        .conflict-panel.local {
            border-left: 5px solid #2563eb;
        }

        .conflict-panel.provider {
            border-left: 5px solid #16a34a;
        }

        .conflict-panel h3 {
            margin-top: 0;
        }

        .conflict-field {
            margin-bottom: 14px;
        }

        .conflict-field label {
            display: block;
            font-size: 12px;
            color: #6b7280;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .conflict-value {
            background: white;
            border: 1px solid #e5e7eb;
            padding: 9px;
            border-radius: 5px;
            min-height: 20px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .manual-merge {
            margin-top: 20px;
            padding: 18px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #f9fafb;
        }

        .manual-merge h3 {
            margin-top: 0;
        }

        .conflict-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .conflict-loading {
            text-align: center;
            padding: 30px;
        }

        @media (max-width: 700px) {
            .conflict-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 900px) {
            .task-row {
                grid-template-columns: 1fr;
            }

            #search {
                width: 100%;
            }

            .toolbar {
                align-items: stretch;
            }
        }
    </style>
</head>

<body>

<div class="header">
    <h1>Task Sync Engine</h1>
    <button class="btn-primary" onclick="openCreateModal()">+ New Task</button>
</div>

<div class="container">

    <div id="message" class="message"></div>

    <div class="toolbar">
        <input
            type="text"
            id="search"
            placeholder="Search tasks..."
            oninput="loadTasks()"
        >

        <select id="statusFilter" onchange="loadTasks()">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="in_progress">In Progress</option>
            <option value="completed">Completed</option>
        </select>

        <select id="syncFilter" onchange="loadTasks()">
            <option value="">All Sync Status</option>
            <option value="synced">Synced</option>
            <option value="pending">Pending</option>
            <option value="conflict">Conflict</option>
            <option value="error">Error</option>
        </select>

        <button class="btn-secondary" onclick="loadTasks()">Refresh</button>
    </div>

    <div id="taskList" class="loading">
        Loading tasks...
    </div>

</div>


<!-- Create/Edit Modal -->

<div id="taskModal" class="modal">
    <div class="modal-content">

        <h2 id="modalTitle">Create Task</h2>

        <input type="hidden" id="taskId">
        <input type="hidden" id="taskVersion">

        <div class="form-group">
            <label>Title *</label>
            <input type="text" id="taskTitle" placeholder="Enter task title">
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea id="taskDescription" placeholder="Enter description"></textarea>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select id="taskStatus">
                <option value="pending">Pending</option>
                <option value="in_progress">In Progress</option>
                <option value="completed">Completed</option>
            </select>
        </div>

        <div class="form-group">
            <label>Priority</label>
            <select id="taskPriority">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
            </select>
        </div>

        <div class="form-group">
            <label>Due Date</label>
            <input type="date" id="taskDueDate">
        </div>

        <div class="modal-actions">
            <button class="btn-secondary" onclick="closeTaskModal()">Cancel</button>
            <button class="btn-primary" onclick="saveTask()">Save Task</button>
        </div>

    </div>
</div>

<!-- =========================
     Conflict Modal
     ========================= -->

<div id="conflictModal" class="modal">

    <div class="modal-content conflict-modal-content">

        <h2>Resolve Task Conflict</h2>

        <!-- Loading -->
        <div id="conflictLoading" class="conflict-loading">
            Loading conflict...
        </div>


        <!-- Conflict Content -->
        <div id="conflictContent" style="display:none;">

            <div class="conflict-grid">

                <!-- =====================
                     LOCAL VERSION
                     ===================== -->

                <div class="conflict-panel local">

                    <h3>Local Version</h3>


                    <div class="conflict-field">

                        <label>Title</label>

                        <div
                            id="localTitle"
                            class="conflict-value">
                        </div>

                    </div>


                    <div class="conflict-field">

                        <label>Description</label>

                        <div
                            id="localDescription"
                            class="conflict-value">
                        </div>

                    </div>


                    <div class="conflict-field">

                        <label>Status</label>

                        <div
                            id="localStatus"
                            class="conflict-value">
                        </div>

                    </div>


                    <div class="conflict-field">

                        <label>Priority</label>

                        <div
                            id="localPriority"
                            class="conflict-value">
                        </div>

                    </div>


                    <div class="conflict-field">

                        <label>Due Date</label>

                        <div
                            id="localDueDate"
                            class="conflict-value">
                        </div>

                    </div>

                </div>


                <!-- =====================
                     GITHUB VERSION
                     ===================== -->

                <div class="conflict-panel provider">

                    <h3>GitHub Version</h3>


                    <div class="conflict-field">

                        <label>Title</label>

                        <div
                            id="providerTitle"
                            class="conflict-value">
                        </div>

                    </div>


                    <div class="conflict-field">

                        <label>Description</label>

                        <div
                            id="providerDescription"
                            class="conflict-value">
                        </div>

                    </div>


                    <div class="conflict-field">

                        <label>Status</label>

                        <div
                            id="providerStatus"
                            class="conflict-value">
                        </div>

                    </div>


                    <div class="conflict-field">

                        <label>Updated At</label>

                        <div
                            id="providerUpdated"
                            class="conflict-value">
                        </div>

                    </div>


                    <div class="conflict-field">

                        <label>GitHub URL</label>

                        <div
                            id="providerUrl"
                            class="conflict-value">
                        </div>

                    </div>

                </div>

            </div>


            <!-- =====================
                 MANUAL MERGE
                 ===================== -->

            <div class="manual-merge">

                <h3>Manual Merge</h3>


                <div class="form-group">

                    <label>Title *</label>

                    <input
                        type="text"
                        id="mergeTitle"
                        placeholder="Merged title">

                </div>


                <div class="form-group">

                    <label>Description</label>

                    <textarea
                        id="mergeDescription"
                        placeholder="Merged description">
                    </textarea>

                </div>


                <div class="form-group">

                    <label>Status</label>

                    <select id="mergeStatus">

                        <option value="pending">
                            Pending
                        </option>

                        <option value="in_progress">
                            In Progress
                        </option>

                        <option value="completed">
                            Completed
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>Priority</label>

                    <select id="mergePriority">

                        <option value="low">
                            Low
                        </option>

                        <option value="medium">
                            Medium
                        </option>

                        <option value="high">
                            High
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>Due Date</label>

                    <input
                        type="date"
                        id="mergeDueDate">

                </div>

            </div>


            <!-- =====================
                 ACTION BUTTONS
                 ===================== -->

            <div class="conflict-actions">

                <button
                    class="btn-secondary"
                    onclick="closeConflictModal()">

                    Cancel

                </button>


                <button
                    class="btn-primary"
                    onclick="resolveConflict('keep_local')">

                    Keep Local

                </button>


                <button
                    class="btn-success"
                    onclick="resolveConflict('keep_provider')">

                    Keep GitHub

                </button>


                <button
                    class="btn-warning"
                    onclick="resolveConflict('manual_merge')">

                    Apply Manual Merge

                </button>

            </div>

        </div>

    </div>

</div>
<script>

let currentTasks = [];

async function loadTasks() {

    const list = document.getElementById('taskList');

    list.innerHTML = '<div class="loading">Loading tasks...</div>';

    try {

        const search = document.getElementById('search').value;
        const status = document.getElementById('statusFilter').value;
        const syncStatus = document.getElementById('syncFilter').value;

        const params = new URLSearchParams();

        if (search) params.append('search', search);
        if (status) params.append('status', status);
        if (syncStatus) params.append('sync_status', syncStatus);

        const response = await fetch('/api/tasks?' + params.toString());

        const result = await response.json();

        if (!response.ok || result.success === false) {
            throw new Error(result.error || 'Unable to load tasks');
        }

        currentTasks = result.data || [];

        renderTasks();

    } catch (error) {

        list.innerHTML =
            '<div class="empty">Unable to load tasks: ' +
            escapeHtml(error.message) +
            '</div>';
    }
}


function renderTasks() {

    const list = document.getElementById('taskList');

    if (!currentTasks.length) {

        list.innerHTML =
            '<div class="empty">No tasks found.</div>';

        return;
    }

    list.innerHTML = currentTasks.map(task => {

        const statusText = formatStatus(task.status);

        const syncText = formatSyncStatus(task.sync_status);

        let actions = `
            <button class="btn-secondary"
                    onclick="editTask(${task.id})">
                Edit
            </button>

            <button class="btn-danger"
                    onclick="deleteTask(${task.id}, ${task.version})">
                Delete
            </button>
        `;

        if (task.sync_status === 'conflict') {

            actions += `
                <button class="btn-warning"
                        onclick="viewConflict(${task.id})">
                    Resolve Conflict
                </button>
            `;
        }

        return `
            <div class="task-card">

                <div class="task-row">

                    <div>
                        <div class="label">Task</div>
                        <div class="task-title">
                            ${escapeHtml(task.title)}
                        </div>

                        <div class="task-description">
                            ${escapeHtml(task.description || '')}
                        </div>
                    </div>

                    <div>
                        <div class="label">Status</div>
                        <span class="badge status-${task.status}">
                            ${statusText}
                        </span>
                    </div>

                    <div>
                        <div class="label">Priority</div>
                        <span class="badge">
                            ${escapeHtml(task.priority)}
                        </span>
                    </div>

                    <div>
                        <div class="label">Sync</div>
                        <span class="badge sync-${task.sync_status}">
                            ${syncText}
                        </span>
                    </div>

                    <div>
                        <div class="label">Actions</div>
                        <div class="actions">
                            ${actions}
                        </div>
                    </div>

                </div>

            </div>
        `;

    }).join('');
}


function formatStatus(status) {

    const values = {
        pending: 'Pending',
        in_progress: 'In Progress',
        completed: 'Completed'
    };

    return values[status] || status;
}


function formatSyncStatus(status) {

    const values = {
        synced: '✓ Synced',
        pending: '⟳ Pending',
        conflict: '⚠ Conflict',
        error: '✕ Error'
    };

    return values[status] || status;
}


function openCreateModal() {

    document.getElementById('modalTitle').innerText = 'Create Task';

    document.getElementById('taskId').value = '';
    document.getElementById('taskVersion').value = '';

    document.getElementById('taskTitle').value = '';
    document.getElementById('taskDescription').value = '';
    document.getElementById('taskStatus').value = 'pending';
    document.getElementById('taskPriority').value = 'medium';
    document.getElementById('taskDueDate').value = '';

    document.getElementById('taskModal').style.display = 'flex';
}


function closeTaskModal() {

    document.getElementById('taskModal').style.display = 'none';
}


async function editTask(id) {

    try {

        const response = await fetch('/api/tasks/' + id);

        const result = await response.json();

        if (!response.ok || result.success === false) {
            throw new Error(result.error || 'Unable to load task');
        }

        const task = result.data;

        document.getElementById('modalTitle').innerText = 'Edit Task';

        document.getElementById('taskId').value = task.id;
        document.getElementById('taskVersion').value = task.version;

        document.getElementById('taskTitle').value = task.title || '';
        document.getElementById('taskDescription').value = task.description || '';
        document.getElementById('taskStatus').value = task.status;
        document.getElementById('taskPriority').value = task.priority;
        document.getElementById('taskDueDate').value = task.due_date || '';

        document.getElementById('taskModal').style.display = 'flex';

    } catch (error) {

        showMessage(error.message, 'error');
    }
}


async function saveTask() {

    const title = document.getElementById('taskTitle').value.trim();

    if (!title) {

        showMessage('Title is required.', 'error');

        return;
    }

    const id = document.getElementById('taskId').value;

    const data = {

        title: title,

        description:
            document.getElementById('taskDescription').value,

        status:
            document.getElementById('taskStatus').value,

        priority:
            document.getElementById('taskPriority').value,

        due_date:
            document.getElementById('taskDueDate').value || null
    };


    try {

        let response;

        if (id) {

            data.version =
                parseInt(document.getElementById('taskVersion').value);

            response = await fetch('/api/tasks/' + id, {

                method: 'PATCH',

                headers: {
                    'Content-Type': 'application/json'
                },

                body: JSON.stringify(data)
            });

        } else {

            response = await fetch('/api/tasks', {

                method: 'POST',

                headers: {
                    'Content-Type': 'application/json'
                },

                body: JSON.stringify(data)
            });
        }


        const result = await response.json();

        if (!response.ok || result.success === false) {

            if (response.status === 409) {

                showMessage(
                    'This task was modified by another request. Refresh and try again.',
                    'error'
                );

            } else {

                throw new Error(
                    result.error || 'Unable to save task'
                );
            }

            return;
        }


        closeTaskModal();

        showMessage(
            id ? 'Task updated successfully.' : 'Task created successfully.',
            'success'
        );

        loadTasks();

    } catch (error) {

        showMessage(error.message, 'error');
    }
}


async function deleteTask(id, version) {

    if (!confirm('Are you sure you want to delete this task?')) {
        return;
    }

    try {

        const response = await fetch('/api/tasks/' + id, {

            method: 'DELETE',

            headers: {
                'Content-Type': 'application/json'
            },

            body: JSON.stringify({
                version: version
            })
        });


        const result = await response.json();

        if (!response.ok || result.success === false) {

            throw new Error(
                result.error || 'Unable to delete task'
            );
        }


        showMessage(
            'Task deleted and queued for synchronization.',
            'success'
        );

        loadTasks();

    } catch (error) {

        showMessage(error.message, 'error');
    }
}


// ============================================
// Conflict Management
// ============================================

let currentConflictId = null;
let currentConflict = null;


/**
 * Open conflict modal
 */
async function viewConflict(taskId) {

    const modal =
        document.getElementById('conflictModal');

    const loading =
        document.getElementById('conflictLoading');

    const content =
        document.getElementById('conflictContent');


    modal.style.display = 'flex';

    loading.style.display = 'block';

    loading.innerHTML =
        'Loading conflict...';

    content.style.display = 'none';


    try {

        // ----------------------------------------
        // Get all open conflicts
        // ----------------------------------------

        const listResponse =
            await fetch('/api/conflicts');


        const listResult =
            await listResponse.json();


        if (
            !listResponse.ok ||
            listResult.success === false
        ) {

            throw new Error(
                listResult.error ||
                'Unable to load conflicts'
            );

        }


        const conflicts =
            listResult.data || [];


        // ----------------------------------------
        // Find conflict belonging to task
        // ----------------------------------------

        const conflict =
            conflicts.find(function (item) {

                return parseInt(item.task_id) ===
                       parseInt(taskId);

            });


        if (!conflict) {

            throw new Error(
                'No open conflict found for this task.'
            );

        }


        // ----------------------------------------
        // Get complete conflict
        // ----------------------------------------

        const response =
            await fetch(
                '/api/conflicts/' + conflict.id
            );


        const result =
            await response.json();


        if (
            !response.ok ||
            result.success === false
        ) {

            throw new Error(
                result.error ||
                'Unable to load conflict'
            );

        }


        currentConflictId =
            result.data.id;

        currentConflict =
            result.data;


        // ----------------------------------------
        // Render conflict
        // ----------------------------------------

        renderConflict(result.data);


        loading.style.display = 'none';

        content.style.display = 'block';


    } catch (error) {

        loading.innerHTML =
            '<div class="empty">' +
            'Unable to load conflict: ' +
            escapeHtml(error.message) +
            '</div>';

    }

}


/**
 * Render Local vs GitHub versions
 */
function renderConflict(conflict) {

    const local =
        conflict.local_snapshot || {};

    const provider =
        conflict.provider_snapshot || {};


    // ==========================================
    // LOCAL VERSION
    // ==========================================

    document.getElementById('localTitle').innerText =
        local.title || '';


    document.getElementById('localDescription').innerText =
        local.description || '';


    document.getElementById('localStatus').innerText =
        formatStatus(
            local.status || ''
        );


    document.getElementById('localPriority').innerText =
        local.priority || '';


    document.getElementById('localDueDate').innerText =
        local.due_date ||
        'No due date';


    // ==========================================
    // GITHUB VERSION
    // ==========================================

    document.getElementById('providerTitle').innerText =
        provider.title || '';


    document.getElementById('providerDescription').innerText =
        provider.description || '';


    let providerStatus =
        'Pending';


    if (provider.state === 'closed') {

        providerStatus = 'Completed';

    }


    document.getElementById('providerStatus').innerText =
        providerStatus;


    document.getElementById('providerUpdated').innerText =
        provider.updated_at || '';


    document.getElementById('providerUrl').innerText =
        provider.html_url || '';


    // ==========================================
    // PRE-FILL MANUAL MERGE
    // ==========================================

    document.getElementById('mergeTitle').value =
        local.title || '';


    document.getElementById('mergeDescription').value =
        local.description || '';


    document.getElementById('mergeStatus').value =
        local.status || 'pending';


    document.getElementById('mergePriority').value =
        local.priority || 'medium';


    document.getElementById('mergeDueDate').value =
        local.due_date || '';

}


/**
 * Resolve conflict
 */
async function resolveConflict(resolution) {

    if (!currentConflictId) {

        showMessage(
            'No conflict selected.',
            'error'
        );

        return;

    }


    // ------------------------------------------
    // Prepare request
    // ------------------------------------------

    let data = {

        resolution: resolution

    };


    // ------------------------------------------
    // Manual merge
    // ------------------------------------------

    if (resolution === 'manual_merge') {

        const title =
            document.getElementById('mergeTitle')
                .value
                .trim();


        if (!title) {

            showMessage(
                'Title is required for manual merge.',
                'error'
            );

            return;

        }


        data.title =
            title;


        data.description =
            document.getElementById('mergeDescription')
                .value;


        data.status =
            document.getElementById('mergeStatus')
                .value;


        data.priority =
            document.getElementById('mergePriority')
                .value;


        data.due_date =
            document.getElementById('mergeDueDate')
                .value || null;

    }


    // ------------------------------------------
    // Confirmation
    // ------------------------------------------

    let confirmation = '';


    if (resolution === 'keep_local') {

        confirmation =
            'Keep the local version and overwrite the GitHub version?';

    }


    if (resolution === 'keep_provider') {

        confirmation =
            'Keep the GitHub version and replace the local version?';

    }


    if (resolution === 'manual_merge') {

        confirmation =
            'Apply this manual merge and synchronize it to GitHub?';

    }


    if (!confirm(confirmation)) {

        return;

    }


    try {

        // --------------------------------------
        // Send resolution
        // --------------------------------------

        const response =
            await fetch(
                '/api/conflicts/' +
                currentConflictId +
                '/resolve',
                {

                    method: 'POST',

                    headers: {

                        'Content-Type':
                            'application/json'

                    },

                    body:
                        JSON.stringify(data)

                }
            );


        const result =
            await response.json();


        // --------------------------------------
        // Error handling
        // --------------------------------------

        if (
            !response.ok ||
            result.success === false
        ) {

            throw new Error(
                result.error ||
                'Unable to resolve conflict'
            );

        }


        // --------------------------------------
        // Close modal
        // --------------------------------------

        closeConflictModal();


        // --------------------------------------
        // Show success
        // --------------------------------------

        showMessage(
            result.message ||
            'Conflict resolved successfully.',
            'success'
        );


        // --------------------------------------
        // Reload task list
        // --------------------------------------

        await loadTasks();


    } catch (error) {

        showMessage(
            error.message,
            'error'
        );

    }

}


/**
 * Close conflict modal
 */
function closeConflictModal() {

    document.getElementById('conflictModal')
        .style.display = 'none';


    currentConflictId = null;

    currentConflict = null;

}





function showMessage(message, type) {

    const box = document.getElementById('message');

    box.className = 'message ' + type;

    box.innerText = message;

    setTimeout(() => {

        box.className = 'message';

    }, 4000);
}


function escapeHtml(value) {

    if (value === null || value === undefined) {
        return '';
    }

    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}


document.addEventListener('DOMContentLoaded', function () {

    loadTasks();

});

</script>

</body>
</html>