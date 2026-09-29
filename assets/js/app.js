/**
 * Core Application Controller
 */

const App = {
    state: {
        authenticated: false,
        configured: false,
        user: { name: 'Me' },
        currentTab: 'all',
        currentView: 'board', // 'board', 'list', 'matrix', 'calendar', 'stats'
        selectedCategoryId: null,
        selectedTag: null,
        searchQuery: '',
        priorityFilter: '',
        sortBy: 'position',
        tasks: [],
        categories: [],
        tags: [],
        settings: {},
        editingTaskId: null,
        // Pomodoro / Focus Timer
        timer: {
            active: false,
            interval: null,
            secondsLeft: 25 * 60,
            initialSeconds: 25 * 60,
            taskId: null,
            taskTitle: 'General Focus'
        }
    },

    /**
     * App Initialization
     */
    async init() {
        lucide.createIcons();
        await App.checkAuthStatus();
        App.initEventListeners();
    },

    /**
     * Check if app is configured and user authenticated
     */
    async checkAuthStatus() {
        try {
            const res = await fetch('api/auth.php?action=status');
            const data = await res.json();

            App.state.configured = data.configured;
            App.state.authenticated = data.authenticated;
            App.state.user.name = data.user_name || 'Me';

            const lockScreen = document.getElementById('lock-screen');
            const setupScreen = document.getElementById('setup-screen');
            const appShell = document.getElementById('app-shell');

            if (!data.configured) {
                // Show Setup Wizard
                setupScreen.classList.remove('hidden');
                lockScreen.classList.add('hidden');
                appShell.classList.add('hidden');
            } else if (!data.authenticated) {
                // Show Master Password Lock Screen
                lockScreen.classList.remove('hidden');
                setupScreen.classList.add('hidden');
                appShell.classList.add('hidden');
                document.getElementById('lock-password-input').focus();
            } else {
                // Fully Authenticated! Load app
                lockScreen.classList.add('hidden');
                setupScreen.classList.add('hidden');
                appShell.classList.remove('hidden');
                document.getElementById('header-user-name').textContent = App.state.user.name;

                await App.loadInitialData();
            }
            lucide.createIcons();
        } catch (err) {
            console.error('Auth status check error:', err);
            App.showToast('Failed to check authentication status', 'error');
        }
    },

    /**
     * Handle Initial Setup Wizard Form Submission
     */
    async handleSetup(e) {
        e.preventDefault();
        const pwd = document.getElementById('setup-password').value.trim();
        const pwdConfirm = document.getElementById('setup-password-confirm').value.trim();
        const name = document.getElementById('setup-name').value.trim();
        const geminiKey = document.getElementById('setup-gemini-key').value.trim();

        if (pwd.length < 4) {
            App.showToast('Password must be at least 4 characters long.', 'warning');
            return;
        }
        if (pwd !== pwdConfirm) {
            App.showToast('Passwords do not match.', 'error');
            return;
        }

        try {
            const res = await fetch('api/auth.php?action=setup', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    password: pwd,
                    user_name: name || 'Me',
                    gemini_api_key: geminiKey
                })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Setup failed');

            App.showToast('🎉 Setup complete! Welcome to NexusAI.', 'success');
            await App.checkAuthStatus();
        } catch (err) {
            App.showToast(err.message, 'error');
        }
    },

    /**
     * Handle Login Form Submission
     */
    async handleLogin(e) {
        e.preventDefault();
        const pwdInput = document.getElementById('lock-password-input');
        const remember = document.getElementById('lock-remember-me').checked;
        const password = pwdInput.value;

        if (!password) return;

        const btn = document.getElementById('lock-submit-btn');
        btn.disabled = true;
        btn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Unlocking...`;
        lucide.createIcons();

        try {
            const res = await fetch('api/auth.php?action=login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ password, remember })
            });
            const data = await res.json();

            if (!data.success) {
                // Shake animation on lock card
                const card = document.getElementById('lock-card');
                card.classList.add('animate-shake');
                setTimeout(() => card.classList.remove('animate-shake'), 600);
                pwdInput.value = '';
                pwdInput.focus();
                throw new Error(data.message || 'Invalid password');
            }

            App.showToast(`Welcome back, ${data.user_name || 'Chief'}!`, 'success');
            pwdInput.value = '';
            await App.checkAuthStatus();
        } catch (err) {
            App.showToast(err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = `Unlock Workspace <i data-lucide="arrow-right" class="w-4 h-4"></i>`;
            lucide.createIcons();
        }
    },

    /**
     * Immediate Lock Screen Trigger
     */
    async lockApp() {
        try {
            await fetch('api/auth.php?action=logout', { method: 'POST' });
            App.state.authenticated = false;
            await App.checkAuthStatus();
            App.showToast('Application locked.', 'info');
        } catch (err) {
            console.error(err);
        }
    },

    /**
     * Load Categories, Tags, Settings, and Tasks
     */
    async loadInitialData() {
        await Promise.all([
            App.loadCategories(),
            App.loadTags(),
            App.loadSettings(),
            App.loadTasks()
        ]);
    },

    async loadCategories() {
        try {
            const res = await fetch('api/categories.php?action=list');
            const data = await res.json();
            if (data.success) {
                App.state.categories = data.categories;
                App.renderCategoriesSidebar();
                App.populateCategorySelects();
            }
        } catch (e) {
            console.error(e);
        }
    },

    async loadTags() {
        try {
            const res = await fetch('api/tags.php?action=list');
            const data = await res.json();
            if (data.success) {
                App.state.tags = data.tags;
                App.renderTagsSidebar();
            }
        } catch (e) {
            console.error(e);
        }
    },

    async loadSettings() {
        try {
            const res = await fetch('api/settings.php?action=get');
            const data = await res.json();
            if (data.success) {
                App.state.settings = data.settings;
                document.getElementById('header-app-title').textContent = data.settings.app_title || 'NexusAI Task Master';
                document.getElementById('header-model-badge').textContent = data.settings.gemini_model || 'gemini-3.8-flash';
            }
        } catch (e) {
            console.error(e);
        }
    },

    /**
     * Load tasks with filters
     */
    async loadTasks() {
        const viewContainer = document.getElementById('view-container');
        
        let url = `api/tasks.php?action=list&sort=${encodeURIComponent(App.state.sortBy)}`;

        if (App.state.currentTab === 'today') url += '&due=today';
        else if (App.state.currentTab === 'upcoming') url += '&due=upcoming';
        else if (App.state.currentTab === 'overdue') url += '&due=overdue';
        else if (App.state.currentTab === 'completed') url += '&status=completed';
        else if (App.state.currentTab === 'all') url += '&status=all';
        else url += '&status=active';

        if (App.state.selectedCategoryId) url += `&category_id=${App.state.selectedCategoryId}`;
        if (App.state.selectedTag) url += `&tag=${encodeURIComponent(App.state.selectedTag)}`;
        if (App.state.priorityFilter) url += `&priority=${encodeURIComponent(App.state.priorityFilter)}`;
        if (App.state.searchQuery) url += `&search=${encodeURIComponent(App.state.searchQuery)}`;

        try {
            const res = await fetch(url);
            const data = await res.json();

            if (!data.success) throw new Error(data.message || 'Failed to fetch tasks');
            App.state.tasks = data.tasks;

            // Render current view
            App.renderCurrentView();
            App.updateSidebarCounters();
        } catch (err) {
            viewContainer.innerHTML = `<div class="p-6 text-center text-red-400">${escapeHtml(err.message)}</div>`;
        }
    },

    /**
     * Render active view (Board, List, Matrix, Calendar, Stats)
     */
    renderCurrentView() {
        const container = document.getElementById('view-container');
        container.innerHTML = '';

        switch (App.state.currentView) {
            case 'board':
                container.innerHTML = Views.renderBoard(App.state.tasks);
                break;
            case 'list':
                container.innerHTML = Views.renderList(App.state.tasks);
                break;
            case 'matrix':
                container.innerHTML = Views.renderMatrix(App.state.tasks);
                break;
            case 'calendar':
                container.innerHTML = Views.renderCalendar(App.state.tasks);
                break;
            case 'stats':
                Views.renderStats();
                return;
        }

        lucide.createIcons();
    },

    /**
     * Switch view mode
     */
    switchView(viewName) {
        App.state.currentView = viewName;
        document.querySelectorAll('.view-tab-btn').forEach(btn => {
            const isActive = btn.dataset.view === viewName;
            btn.className = isActive 
                ? 'view-tab-btn px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-600 text-white shadow-sm flex items-center gap-1.5 transition'
                : 'view-tab-btn px-3 py-1.5 rounded-lg text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-800 flex items-center gap-1.5 transition';
        });

        if (viewName === 'stats') {
            Views.renderStats();
        } else {
            App.renderCurrentView();
        }
    },

    /**
     * Switch Navigation Tab
     */
    switchTab(tabName, categoryId = null, tagName = null) {
        App.state.currentTab = tabName;
        App.state.selectedCategoryId = categoryId;
        App.state.selectedTag = tagName;

        document.querySelectorAll('.nav-item').forEach(el => {
            el.classList.remove('bg-indigo-600/20', 'text-indigo-400', 'font-semibold');
            el.classList.add('text-slate-400');
        });

        const activeEl = document.getElementById(`nav-${tabName}`);
        if (activeEl) {
            activeEl.classList.add('bg-indigo-600/20', 'text-indigo-400', 'font-semibold');
            activeEl.classList.remove('text-slate-400');
        }

        App.loadTasks();
    },

    /**
     * Toggle Task Completion
     */
    async toggleTaskComplete(taskId, event) {
        if (event) event.stopPropagation();

        try {
            const res = await fetch('api/tasks.php?action=toggle_complete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: taskId })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message);

            if (data.is_completed) {
                App.showToast('✅ Task completed!', 'success');
                if (data.spawned_next) {
                    App.showToast('🔁 Recurring task next occurrence scheduled!', 'info');
                }
            } else {
                App.showToast('Task marked incomplete.', 'info');
            }

            await App.loadTasks();
        } catch (err) {
            App.showToast(err.message, 'error');
        }
    },

    /**
     * Update Task Status (Drag and drop or select)
     */
    async updateTaskStatus(taskId, newStatus) {
        try {
            const task = App.state.tasks.find(t => t.id == taskId);
            if (!task) return;

            task.status = newStatus;
            await fetch('api/tasks.php?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: taskId,
                    title: task.title,
                    description: task.description,
                    status: newStatus,
                    priority: task.priority,
                    category_id: task.category_id,
                    due_date: task.due_date,
                    estimated_minutes: task.estimated_minutes
                })
            });

            await App.loadTasks();
        } catch (err) {
            App.showToast(err.message, 'error');
        }
    },

    /**
     * Open Task Create / Edit Modal
     */
    async openTaskModal(taskId = null) {
        App.state.editingTaskId = taskId;
        const modal = document.getElementById('task-modal');
        const modalTitle = document.getElementById('modal-title');
        const deleteBtn = document.getElementById('modal-delete-btn');
        const subtasksSection = document.getElementById('modal-subtasks-section');
        const timelogSection = document.getElementById('modal-timelog-section');

        // Reset form fields
        document.getElementById('modal-task-title').value = '';
        document.getElementById('modal-task-desc').value = '';
        document.getElementById('modal-task-category').value = '';
        document.getElementById('modal-task-priority').value = 'medium';
        document.getElementById('modal-task-energy').value = 'medium';
        document.getElementById('modal-task-status').value = 'inbox';
        document.getElementById('modal-task-due').value = '';
        document.getElementById('modal-task-estimated').value = '';
        document.getElementById('modal-task-recurrence').value = 'none';
        document.getElementById('modal-task-tags').value = '';

        if (taskId) {
            modalTitle.textContent = 'Edit Task Details';
            deleteBtn.classList.remove('hidden');
            subtasksSection.classList.remove('hidden');
            timelogSection.classList.remove('hidden');

            try {
                const res = await fetch(`api/tasks.php?action=get&id=${taskId}`);
                const data = await res.json();
                if (!data.success) throw new Error(data.message);

                const t = data.task;
                document.getElementById('modal-task-title').value = t.title || '';
                document.getElementById('modal-task-desc').value = t.description || '';
                document.getElementById('modal-task-category').value = t.category_id || '';
                document.getElementById('modal-task-priority').value = t.priority || 'medium';
                document.getElementById('modal-task-energy').value = t.energy_level || 'medium';
                document.getElementById('modal-task-status').value = t.status || 'inbox';
                document.getElementById('modal-task-due').value = t.due_date ? t.due_date.replace(' ', 'T') : '';
                document.getElementById('modal-task-estimated').value = t.estimated_minutes || '';
                document.getElementById('modal-task-recurrence').value = t.recurrence || 'none';
                document.getElementById('modal-task-tags').value = (t.tags || []).map(x => x.name).join(', ');

                App.renderSubtasksInModal(t.subtasks || []);
                App.renderTimeLogsInModal(t.time_logs || []);
            } catch (err) {
                App.showToast(err.message, 'error');
                return;
            }
        } else {
            modalTitle.textContent = 'Create New Task';
            deleteBtn.classList.add('hidden');
            subtasksSection.classList.add('hidden');
            timelogSection.classList.add('hidden');
        }

        modal.classList.remove('hidden');
        document.getElementById('modal-task-title').focus();
        lucide.createIcons();
    },

    openCreateModal() {
        App.openTaskModal(null);
    },

    /**
     * Save Task from Modal
     */
    async saveTaskFromModal() {
        const title = document.getElementById('modal-task-title').value.trim();
        if (!title) {
            App.showToast('Task title is required.', 'warning');
            return;
        }

        const tagsRaw = document.getElementById('modal-task-tags').value;
        const tags = tagsRaw.split(',').map(s => s.trim()).filter(Boolean);

        const payload = {
            id: App.state.editingTaskId,
            title: title,
            description: document.getElementById('modal-task-desc').value.trim(),
            category_id: document.getElementById('modal-task-category').value || null,
            priority: document.getElementById('modal-task-priority').value,
            energy_level: document.getElementById('modal-task-energy').value,
            status: document.getElementById('modal-task-status').value,
            due_date: document.getElementById('modal-task-due').value.replace('T', ' ') || null,
            estimated_minutes: parseInt(document.getElementById('modal-task-estimated').value) || 0,
            recurrence: document.getElementById('modal-task-recurrence').value,
            tags: tags
        };

        const action = App.state.editingTaskId ? 'update' : 'create';

        try {
            const res = await fetch(`api/tasks.php?action=${action}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message);

            document.getElementById('task-modal').classList.add('hidden');
            App.showToast(action === 'create' ? 'Task created!' : 'Task updated!', 'success');
            await App.loadTasks();
            await App.loadTags();
        } catch (err) {
            App.showToast(err.message, 'error');
        }
    },

    /**
     * Delete Task
     */
    async deleteTask(taskId = null) {
        const id = taskId || App.state.editingTaskId;
        if (!id) return;

        if (!confirm('Are you sure you want to delete this task? All subtasks and logs will be removed.')) return;

        try {
            const res = await fetch('api/tasks.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message);

            document.getElementById('task-modal').classList.add('hidden');
            App.showToast('Task deleted.', 'info');
            await App.loadTasks();
        } catch (err) {
            App.showToast(err.message, 'error');
        }
    },

    /**
     * Render Subtasks in Edit Modal
     */
    renderSubtasksInModal(subtasks) {
        const list = document.getElementById('modal-subtasks-list');
        list.innerHTML = subtasks.map(st => `
            <div class="flex items-center justify-between gap-3 p-2 rounded-lg bg-slate-900 border border-slate-800 text-xs">
                <div class="flex items-center gap-2.5 flex-1 min-w-0">
                    <input type="checkbox" class="task-checkbox" ${st.is_completed ? 'checked' : ''} 
                           onchange="App.toggleSubtask(${st.id}, this.checked)">
                    <span class="${st.is_completed ? 'line-through text-slate-500' : 'text-slate-200'} truncate">${escapeHtml(st.title)}</span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    ${st.estimated_minutes ? `<span class="text-[10px] text-slate-400 bg-slate-800 px-1.5 py-0.5 rounded">${st.estimated_minutes}m</span>` : ''}
                    <button onclick="App.deleteSubtask(${st.id})" class="text-slate-500 hover:text-red-400 p-1">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </button>
                </div>
            </div>
        `).join('');
        lucide.createIcons();
    },

    async addSubtaskFromModal() {
        const input = document.getElementById('new-subtask-input');
        const title = input.value.trim();
        if (!title || !App.state.editingTaskId) return;

        try {
            const res = await fetch('api/subtasks.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ task_id: App.state.editingTaskId, title })
            });
            const data = await res.json();
            if (data.success) {
                input.value = '';
                // Reload subtasks
                const sRes = await fetch(`api/subtasks.php?action=list&task_id=${App.state.editingTaskId}`);
                const sData = await sRes.json();
                App.renderSubtasksInModal(sData.subtasks || []);
                await App.loadTasks();
            }
        } catch (e) {
            App.showToast(e.message, 'error');
        }
    },

    async toggleSubtask(id, isChecked) {
        await fetch('api/subtasks.php?action=toggle', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        await App.loadTasks();
    },

    async deleteSubtask(id) {
        await fetch('api/subtasks.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const sRes = await fetch(`api/subtasks.php?action=list&task_id=${App.state.editingTaskId}`);
        const sData = await sRes.json();
        App.renderSubtasksInModal(sData.subtasks || []);
        await App.loadTasks();
    },

    /**
     * Render Time Logs in Edit Modal
     */
    renderTimeLogsInModal(logs) {
        const list = document.getElementById('modal-timelog-list');
        const totalMinutes = logs.reduce((sum, l) => sum + parseInt(l.duration_minutes), 0);
        document.getElementById('modal-total-time-badge').textContent = `${totalMinutes}m logged`;

        list.innerHTML = logs.map(l => `
            <div class="flex items-center justify-between p-2 rounded-lg bg-slate-900 border border-slate-800 text-xs text-slate-300">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-indigo-400">${l.duration_minutes}m</span>
                    <span class="text-slate-400">${escapeHtml(l.note || 'Work session')}</span>
                </div>
                <span class="text-[10px] text-slate-500">${l.logged_at}</span>
            </div>
        `).join('');
    },

    async logManualTime() {
        const minutes = parseInt(document.getElementById('manual-time-minutes').value);
        const note = document.getElementById('manual-time-note').value.trim();

        if (!minutes || minutes <= 0 || !App.state.editingTaskId) {
            App.showToast('Please enter valid minutes', 'warning');
            return;
        }

        try {
            await fetch('api/timelogs.php?action=log', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    task_id: App.state.editingTaskId,
                    duration_minutes: minutes,
                    note: note || 'Manual entry'
                })
            });

            document.getElementById('manual-time-minutes').value = '';
            document.getElementById('manual-time-note').value = '';

            const tRes = await fetch(`api/timelogs.php?action=list&task_id=${App.state.editingTaskId}`);
            const tData = await tRes.json();
            App.renderTimeLogsInModal(tData.time_logs || []);
            App.showToast(`Logged ${minutes} minutes!`, 'success');
            await App.loadTasks();
        } catch (e) {
            App.showToast(e.message, 'error');
        }
    },

    /**
     * Focus / Pomodoro Timer
     */
    startTimerForTask(taskId, taskTitle) {
        App.state.timer.taskId = taskId;
        App.state.timer.taskTitle = taskTitle;
        document.getElementById('timer-task-label').textContent = taskTitle;
        App.startTimer();
    },

    startTimer() {
        if (App.state.timer.active) return;
        App.state.timer.active = true;
        document.getElementById('timer-toggle-btn').innerHTML = `<i data-lucide="pause" class="w-4 h-4"></i>`;
        lucide.createIcons();

        App.state.timer.interval = setInterval(() => {
            App.state.timer.secondsLeft--;
            App.updateTimerDisplay();

            if (App.state.timer.secondsLeft <= 0) {
                App.completeTimerSession();
            }
        }, 1000);
    },

    pauseTimer() {
        App.state.timer.active = false;
        clearInterval(App.state.timer.interval);
        document.getElementById('timer-toggle-btn').innerHTML = `<i data-lucide="play" class="w-4 h-4"></i>`;
        lucide.createIcons();
    },

    toggleTimer() {
        if (App.state.timer.active) App.pauseTimer();
        else App.startTimer();
    },

    resetTimer() {
        App.pauseTimer();
        App.state.timer.secondsLeft = App.state.timer.initialSeconds;
        App.updateTimerDisplay();
    },

    updateTimerDisplay() {
        const mins = Math.floor(App.state.timer.secondsLeft / 60);
        const secs = App.state.timer.secondsLeft % 60;
        const timeStr = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        document.getElementById('timer-display').textContent = timeStr;
    },

    async completeTimerSession() {
        App.pauseTimer();
        App.state.timer.secondsLeft = App.state.timer.initialSeconds;
        App.updateTimerDisplay();

        App.showToast('🔔 Focus session complete! Take a breather.', 'success');

        if (App.state.timer.taskId) {
            const minutesLogged = Math.round(App.state.timer.initialSeconds / 60);
            await fetch('api/timelogs.php?action=log', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    task_id: App.state.timer.taskId,
                    duration_minutes: minutesLogged,
                    note: 'Pomodoro Focus Session'
                })
            });
            await App.loadTasks();
        }
    },

    /**
     * Categories Management
     */
    renderCategoriesSidebar() {
        const list = document.getElementById('sidebar-categories-list');
        list.innerHTML = App.state.categories.map(c => `
            <button onclick="App.switchTab('cat-${c.id}', ${c.id}, null)" 
                    id="nav-cat-${c.id}"
                    class="nav-item w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 transition group">
                <div class="flex items-center gap-2.5 truncate">
                    <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background: ${c.color}"></span>
                    <span class="truncate">${escapeHtml(c.name)}</span>
                </div>
                <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-slate-800 text-slate-400 group-hover:bg-slate-700">
                    ${c.active_count || 0}
                </span>
            </button>
        `).join('');
    },

    renderTagsSidebar() {
        const cloud = document.getElementById('sidebar-tags-cloud');
        cloud.innerHTML = App.state.tags.map(tg => `
            <button onclick="App.switchTab('tag-${tg.name}', null, '${escapeHtml(tg.name)}')"
                    class="text-[11px] px-2.5 py-1 rounded-lg bg-slate-800/80 hover:bg-indigo-950/60 hover:text-indigo-400 text-slate-400 border border-slate-700/80 transition flex items-center gap-1">
                <span>#${escapeHtml(tg.name)}</span>
                <span class="text-[9px] opacity-60">(${tg.task_count || 0})</span>
            </button>
        `).join('');
    },

    populateCategorySelects() {
        const selects = [document.getElementById('modal-task-category'), document.getElementById('filter-category')];
        selects.forEach(sel => {
            if (!sel) return;
            const curVal = sel.value;
            const defaultOpt = sel.id === 'filter-category' ? '<option value="">All Categories</option>' : '<option value="">Select Project / Category</option>';
            sel.innerHTML = defaultOpt + App.state.categories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
            sel.value = curVal;
        });
    },

    async openNewCategoryModal() {
        const name = prompt('Enter new Category / Project name:');
        if (!name || !name.trim()) return;

        const colors = ['#3b82f6', '#10b981', '#8b5cf6', '#f59e0b', '#ec4899', '#06b6d4', '#f97316'];
        const randomColor = colors[Math.floor(Math.random() * colors.length)];

        try {
            const res = await fetch('api/categories.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: name.trim(), color: randomColor })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message);

            App.showToast(`Category '${name}' created!`, 'success');
            await App.loadCategories();
        } catch (e) {
            App.showToast(e.message, 'error');
        }
    },

    updateSidebarCounters() {
        const todayStr = new Date().toISOString().substring(0, 10);
        let todayCount = 0;
        let overdueCount = 0;
        let upcomingCount = 0;
        let allCount = 0;

        App.state.tasks.forEach(t => {
            if (t.status !== 'completed' && t.status !== 'archived') {
                allCount++;
                if (t.due_date) {
                    const d = t.due_date.substring(0, 10);
                    if (d === todayStr) todayCount++;
                    else if (d < todayStr) overdueCount++;
                    else if (d > todayStr) upcomingCount++;
                }
            }
        });

        const badgeAll = document.getElementById('badge-all');
        const badgeToday = document.getElementById('badge-today');
        const badgeOverdue = document.getElementById('badge-overdue');
        const badgeUpcoming = document.getElementById('badge-upcoming');

        if (badgeAll) badgeAll.textContent = allCount;
        if (badgeToday) badgeToday.textContent = todayCount;
        if (badgeOverdue) badgeOverdue.textContent = overdueCount;
        if (badgeUpcoming) badgeUpcoming.textContent = upcomingCount;
    },

    /**
     * Settings Modal Management
     */
    async openSettingsModal() {
        const modal = document.getElementById('settings-modal');
        await App.loadSettings();

        const s = App.state.settings;
        document.getElementById('settings-name').value = s.user_name || '';
        document.getElementById('settings-app-title').value = s.app_title || '';
        document.getElementById('settings-gemini-key').value = '';
        document.getElementById('settings-gemini-key').placeholder = s.has_gemini_key ? `Configured (${s.masked_gemini_key}) - leave blank to keep` : 'Paste Gemini API key here';
        document.getElementById('settings-gemini-model').value = s.gemini_model || 'gemini-3.8-flash';

        modal.classList.remove('hidden');
        lucide.createIcons();
    },

    async saveSettings() {
        const name = document.getElementById('settings-name').value.trim();
        const appTitle = document.getElementById('settings-app-title').value.trim();
        const key = document.getElementById('settings-gemini-key').value.trim();
        const model = document.getElementById('settings-gemini-model').value;

        const payload = {
            user_name: name,
            app_title: appTitle,
            gemini_model: model
        };
        if (key) payload.gemini_api_key = key;

        try {
            const res = await fetch('api/settings.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message);

            App.showToast('Settings saved!', 'success');
            document.getElementById('settings-modal').classList.add('hidden');
            await App.loadSettings();
            document.getElementById('header-user-name').textContent = name || 'Me';
        } catch (err) {
            App.showToast(err.message, 'error');
        }
    },

    async testGeminiInSettings() {
        const btn = document.getElementById('test-key-btn');
        const key = document.getElementById('settings-gemini-key').value.trim();
        const model = document.getElementById('settings-gemini-model').value;

        const origHtml = btn.innerHTML;
        btn.innerHTML = `<i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i> Testing...`;
        btn.disabled = true;
        lucide.createIcons();

        try {
            const res = await fetch('api/ai.php?action=test_key', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ api_key: key, model })
            });
            const data = await res.json();

            if (!data.success) throw new Error(data.message);

            App.showToast(`✨ Success: Connected to ${data.model}!`, 'success');
        } catch (err) {
            App.showToast(err.message, 'error');
        } finally {
            btn.innerHTML = origHtml;
            btn.disabled = false;
            lucide.createIcons();
        }
    },

    async changeMasterPassword() {
        const curPwd = prompt('Enter Current Master Password:');
        if (!curPwd) return;
        const newPwd = prompt('Enter New Master Password (min 4 characters):');
        if (!newPwd || newPwd.length < 4) {
            alert('Password must be at least 4 characters');
            return;
        }

        try {
            const res = await fetch('api/auth.php?action=change_password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ current_password: curPwd, new_password: newPwd })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message);

            App.showToast('Master password updated successfully!', 'success');
        } catch (e) {
            App.showToast(e.message, 'error');
        }
    },

    exportDb() {
        window.location.href = 'api/settings.php?action=export_db';
    },

    exportJson() {
        window.location.href = 'api/settings.php?action=export_json';
    },

    /**
     * Toast Notifications
     */
    showToast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');

        let bg = 'bg-slate-800 border-slate-700 text-slate-200';
        let icon = 'info';

        if (type === 'success') {
            bg = 'bg-emerald-950/90 border-emerald-700 text-emerald-200';
            icon = 'check-circle-2';
        } else if (type === 'error') {
            bg = 'bg-red-950/90 border-red-700 text-red-200';
            icon = 'alert-triangle';
        } else if (type === 'warning') {
            bg = 'bg-amber-950/90 border-amber-700 text-amber-200';
            icon = 'alert-circle';
        }

        toast.className = `toast-msg flex items-center gap-2.5 px-4 py-3 rounded-xl border shadow-xl text-xs font-medium max-w-sm animate-fade-in ${bg}`;
        toast.innerHTML = `
            <i data-lucide="${icon}" class="w-4 h-4 shrink-0"></i>
            <span class="flex-1">${escapeHtml(message)}</span>
        `;

        container.appendChild(toast);
        lucide.createIcons();

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    },

    /**
     * Event Listeners Setup
     */
    initEventListeners() {
        // Global Escape key closes modals
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.app-modal').forEach(m => m.classList.add('hidden'));
                if (AI.copilotOpen) AI.toggleCopilot();
            }
        });

        // Search debouncing
        let searchTimeout;
        const searchInput = document.getElementById('global-search-input');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    App.state.searchQuery = e.target.value.trim();
                    App.loadTasks();
                }, 300);
            });
        }

        // Priority filter
        const prioSelect = document.getElementById('filter-priority');
        if (prioSelect) {
            prioSelect.addEventListener('change', (e) => {
                App.state.priorityFilter = e.target.value;
                App.loadTasks();
            });
        }

        // Category filter
        const catSelect = document.getElementById('filter-category');
        if (catSelect) {
            catSelect.addEventListener('change', (e) => {
                App.state.selectedCategoryId = e.target.value || null;
                App.loadTasks();
            });
        }

        // Sort filter
        const sortSelect = document.getElementById('filter-sort');
        if (sortSelect) {
            sortSelect.addEventListener('change', (e) => {
                App.state.sortBy = e.target.value;
                App.loadTasks();
            });
        }
    }
};

// Bootstrap on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    App.init();
});
