/**
 * Views Rendering Module: Kanban Board, List View, Eisenhower Matrix, Calendar, Analytics
 */

const Views = {
    /**
     * Render Kanban Board View
     */
    renderBoard(tasks) {
        const columns = [
            { id: 'inbox', title: 'To Do / Inbox', color: 'border-slate-500', icon: 'inbox' },
            { id: 'in_progress', title: 'In Progress', color: 'border-amber-500', icon: 'play' },
            { id: 'review', title: 'Review / Blocked', color: 'border-purple-500', icon: 'pause-circle' },
            { id: 'completed', title: 'Completed', color: 'border-emerald-500', icon: 'check-circle' }
        ];

        // Group tasks by column
        const grouped = { inbox: [], in_progress: [], review: [], completed: [] };
        tasks.forEach(t => {
            const st = (t.status === 'planned') ? 'inbox' : (grouped[t.status] ? t.status : 'inbox');
            grouped[st].push(t);
        });

        let html = `<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5 pb-8">`;

        columns.forEach(col => {
            const colTasks = grouped[col.id] || [];
            html += `
                <div class="flex flex-col bg-slate-900/60 rounded-xl border border-slate-800 p-4 kanban-column"
                     ondragover="Views.handleDragOver(event)"
                     ondrop="Views.handleDrop(event, '${col.id}')"
                     ondragleave="Views.handleDragLeave(event)">
                    
                    <div class="flex items-center justify-between pb-3 mb-3 border-b ${col.color}">
                        <div class="flex items-center gap-2">
                            <i data-lucide="${col.icon}" class="w-4 h-4 text-slate-400"></i>
                            <h3 class="font-semibold text-slate-200 text-sm tracking-wide uppercase">${col.title}</h3>
                        </div>
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-slate-800 text-slate-400 border border-slate-700">
                            ${colTasks.length}
                        </span>
                    </div>

                    <div class="flex flex-col gap-3 flex-1 overflow-y-auto pr-1" id="column-${col.id}">
                        ${colTasks.map(t => Views.renderTaskCard(t, 'board')).join('')}
                        ${colTasks.length === 0 ? `
                            <div class="flex flex-col items-center justify-center p-6 rounded-lg border border-dashed border-slate-800 text-slate-500 text-xs">
                                <i data-lucide="layers" class="w-6 h-6 mb-2 opacity-40"></i>
                                No tasks here
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        });

        html += `</div>`;
        return html;
    },

    /**
     * Render List View
     */
    renderList(tasks) {
        if (tasks.length === 0) {
            return `
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-slate-800/80 border border-slate-700 flex items-center justify-center mb-4 text-indigo-400 shadow-lg">
                        <i data-lucide="check-check" class="w-8 h-8"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-200 mb-1">All clear! No tasks found</h3>
                    <p class="text-sm text-slate-400 max-w-sm mb-5">Create a task or ask the AI Smart Quick-Add bar to organize your day.</p>
                    <button onclick="App.openCreateModal()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-medium transition shadow-md flex items-center gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i> Add Your First Task
                    </button>
                </div>
            `;
        }

        return `
            <div class="flex flex-col gap-2.5 pb-10">
                ${tasks.map(t => Views.renderTaskCard(t, 'list')).join('')}
            </div>
        `;
    },

    /**
     * Render Single Task Card (used in board & list)
     */
    renderTaskCard(t, mode = 'board') {
        const isDone = t.status === 'completed';
        const isOverdue = t.due_date && !isDone && (new Date(t.due_date) < new Date());
        const isToday = t.due_date && !isDone && (t.due_date.substring(0, 10) === new Date().toISOString().substring(0, 10));

        const priorityBadge = `
            <span class="px-2 py-0.5 text-[11px] font-medium rounded-md uppercase tracking-wider badge-${t.priority}">
                ${t.priority}
            </span>
        `;

        const subtaskProgress = t.subtask_total > 0 ? `
            <div class="flex items-center gap-1.5 text-xs text-slate-400" title="Subtasks">
                <i data-lucide="check-square" class="w-3.5 h-3.5"></i>
                <span>${t.subtask_completed}/${t.subtask_total}</span>
            </div>
        ` : '';

        const loggedTime = (t.spent_minutes > 0 || t.estimated_minutes > 0) ? `
            <div class="flex items-center gap-1 text-xs text-slate-400" title="Time: Spent / Est">
                <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                <span>${t.spent_minutes}m${t.estimated_minutes ? ' / ' + t.estimated_minutes + 'm' : ''}</span>
            </div>
        ` : '';

        const tagsHtml = (t.tags && t.tags.length > 0) ? `
            <div class="flex flex-wrap gap-1 mt-2">
                ${t.tags.map(tag => `
                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 border border-slate-700">
                        #${escapeHtml(tag.name)}
                    </span>
                `).join('')}
            </div>
        ` : '';

        const categoryPill = t.category_name ? `
            <span class="text-[11px] px-2 py-0.5 rounded font-medium flex items-center gap-1" 
                  style="background: ${t.category_color}22; color: ${t.category_color}; border: 1px solid ${t.category_color}44;">
                <span class="w-1.5 h-1.5 rounded-full" style="background: ${t.category_color}"></span>
                ${escapeHtml(t.category_name)}
            </span>
        ` : '';

        let dueDateBadge = '';
        if (t.due_date) {
            let dueClass = 'text-slate-400 bg-slate-800/80 border-slate-700';
            if (isOverdue) dueClass = 'text-red-400 bg-red-950/40 border-red-800';
            else if (isToday) dueClass = 'text-amber-400 bg-amber-950/40 border-amber-800';

            const displayDate = t.due_date.replace('T', ' ');
            dueDateBadge = `
                <span class="text-[11px] px-2 py-0.5 rounded border flex items-center gap-1 ${dueClass}">
                    <i data-lucide="calendar" class="w-3 h-3"></i>
                    ${displayDate}
                    ${isOverdue ? '<span class="text-[9px] font-bold text-red-400 uppercase ml-1">Overdue</span>' : ''}
                </span>
            `;
        }

        if (mode === 'list') {
            return `
                <div class="group bg-slate-900/80 hover:bg-slate-800/90 border border-slate-800 hover:border-slate-700 rounded-xl p-3.5 transition-all shadow-sm flex items-center justify-between gap-4"
                     id="task-row-${t.id}">
                    <div class="flex items-center gap-3.5 flex-1 min-w-0">
                        <input type="checkbox" class="task-checkbox" ${isDone ? 'checked' : ''} 
                               onchange="App.toggleTaskComplete(${t.id}, event)" title="Mark as ${isDone ? 'incomplete' : 'complete'}">
                        
                        <div class="flex flex-col min-w-0 flex-1 cursor-pointer" onclick="App.openTaskModal(${t.id})">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-sm text-slate-100 ${isDone ? 'line-through opacity-50' : ''} truncate">
                                    ${escapeHtml(t.title)}
                                </span>
                                ${t.is_pinned ? '<i data-lucide="pin" class="w-3.5 h-3.5 text-amber-400 shrink-0"></i>' : ''}
                                ${t.recurrence !== 'none' ? `<span class="text-[10px] text-indigo-400 bg-indigo-950/50 px-1.5 py-0.5 rounded border border-indigo-800 uppercase">${t.recurrence}</span>` : ''}
                            </div>
                            ${t.description ? `<p class="text-xs text-slate-400 line-clamp-1 mt-0.5">${escapeHtml(t.description)}</p>` : ''}
                            ${tagsHtml}
                        </div>
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        ${categoryPill}
                        ${priorityBadge}
                        ${subtaskProgress}
                        ${loggedTime}
                        ${dueDateBadge}

                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick="App.startTimerForTask(${t.id}, '${escapeHtml(t.title)}')" title="Start Focus Timer"
                                    class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-400 hover:bg-slate-700 transition">
                                <i data-lucide="timer" class="w-4 h-4"></i>
                            </button>
                            <button onclick="AI.openTaskBreakdown(${t.id})" title="AI Subtask Breakdown"
                                    class="p-1.5 rounded-lg text-slate-400 hover:text-purple-400 hover:bg-slate-700 transition">
                                <i data-lucide="sparkles" class="w-4 h-4"></i>
                            </button>
                            <button onclick="App.openTaskModal(${t.id})" title="Edit Details"
                                    class="p-1.5 rounded-lg text-slate-400 hover:text-slate-200 hover:bg-slate-700 transition">
                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        // Board Card
        return `
            <div class="kanban-task-card bg-slate-800/80 hover:bg-slate-800 border border-slate-700/80 rounded-xl p-3.5 shadow-sm transition hover:shadow-md flex flex-col gap-2.5"
                 id="task-card-${t.id}"
                 draggable="true"
                 ondragstart="Views.handleDragStart(event, ${t.id})">
                
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-start gap-2.5 min-w-0">
                        <input type="checkbox" class="task-checkbox mt-0.5" ${isDone ? 'checked' : ''} 
                               onchange="App.toggleTaskComplete(${t.id}, event)">
                        <div class="cursor-pointer" onclick="App.openTaskModal(${t.id})">
                            <div class="text-sm font-medium text-slate-100 ${isDone ? 'line-through opacity-50' : ''} line-clamp-2 leading-snug">
                                ${escapeHtml(t.title)}
                            </div>
                        </div>
                    </div>
                    ${priorityBadge}
                </div>

                ${t.description ? `<p class="text-xs text-slate-400 line-clamp-2">${escapeHtml(t.description)}</p>` : ''}

                ${tagsHtml}

                <div class="flex items-center justify-between pt-2 border-t border-slate-700/60 mt-1">
                    <div class="flex items-center gap-2">
                        ${categoryPill}
                        ${subtaskProgress}
                    </div>
                    <div class="flex items-center gap-1.5">
                        ${dueDateBadge}
                        <button onclick="AI.openTaskBreakdown(${t.id})" title="AI Breakdown" class="text-slate-400 hover:text-purple-400 transition p-1">
                            <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    },

    /**
     * Render Eisenhower Matrix (Urgent vs Important)
     */
    renderMatrix(tasks) {
        const todayStr = new Date().toISOString().substring(0, 10);

        const q1 = []; // Urgent & Important (Critical/High with due date soon)
        const q2 = []; // Not Urgent & Important (Critical/High with no due date or far away)
        const q3 = []; // Urgent & Not Important (Medium/Low with due date soon)
        const q4 = []; // Not Urgent & Not Important (Medium/Low without urgent due date)

        tasks.filter(t => t.status !== 'completed').forEach(t => {
            const isImportant = (t.priority === 'critical' || t.priority === 'high');
            const isUrgent = (t.due_date && t.due_date.substring(0, 10) <= todayStr);

            if (isImportant && isUrgent) q1.push(t);
            else if (isImportant && !isUrgent) q2.push(t);
            else if (!isImportant && isUrgent) q3.push(t);
            else q4.push(t);
        });

        const quadrants = [
            { title: 'DO FIRST (Urgent & Important)', desc: 'Crisis, deadlines, urgent problems', tasks: q1, color: 'border-red-500 bg-red-950/20 text-red-400' },
            { title: 'SCHEDULE (Important, Not Urgent)', desc: 'Strategic planning, deep work, growth', tasks: q2, color: 'border-blue-500 bg-blue-950/20 text-blue-400' },
            { title: 'DELEGATE / QUICK WINS (Urgent, Not Important)', desc: 'Interruptions, quick errands, alerts', tasks: q3, color: 'border-amber-500 bg-amber-950/20 text-amber-400' },
            { title: 'ELIMINATE (Not Urgent & Not Important)', desc: 'Time wasters, low-priority backlog', tasks: q4, color: 'border-slate-600 bg-slate-900/40 text-slate-400' }
        ];

        return `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 pb-10">
                ${quadrants.map(q => `
                    <div class="flex flex-col rounded-xl border ${q.color.split(' ')[0]} bg-slate-900/80 p-5 shadow-lg min-h-[320px]">
                        <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-3">
                            <div>
                                <h3 class="font-semibold text-sm ${q.color.split(' ')[2]}">${q.title}</h3>
                                <p class="text-xs text-slate-400 mt-0.5">${q.desc}</p>
                            </div>
                            <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-slate-800 text-slate-300">
                                ${q.tasks.length}
                            </span>
                        </div>
                        <div class="flex flex-col gap-2.5 overflow-y-auto flex-1 pr-1">
                            ${q.tasks.map(t => Views.renderTaskCard(t, 'list')).join('')}
                            ${q.tasks.length === 0 ? `
                                <div class="flex items-center justify-center h-28 text-slate-500 text-xs border border-dashed border-slate-800 rounded-lg">
                                    No tasks in this quadrant
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    },

    /**
     * Render Calendar View
     */
    renderCalendar(tasks) {
        const date = new Date();
        const year = date.getFullYear();
        const month = date.getMonth();

        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        const firstDayIndex = new Date(year, month, 1).getDay();
        const lastDay = new Date(year, month + 1, 0).getDate();
        const todayStr = date.toISOString().substring(0, 10);

        // Map tasks by YYYY-MM-DD
        const tasksByDate = {};
        tasks.forEach(t => {
            if (t.due_date) {
                const d = t.due_date.substring(0, 10);
                if (!tasksByDate[d]) tasksByDate[d] = [];
                tasksByDate[d].push(t);
            }
        });

        let gridHtml = '';
        for (let i = 0; i < firstDayIndex; i++) {
            gridHtml += `<div class="bg-slate-900/30 border border-slate-800/40 p-2 min-h-[90px] rounded-lg opacity-40"></div>`;
        }

        for (let day = 1; day <= lastDay; day++) {
            const curDateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const isToday = curDateStr === todayStr;
            const dayTasks = tasksByDate[curDateStr] || [];

            gridHtml += `
                <div class="bg-slate-900/70 border ${isToday ? 'border-indigo-500 shadow-md ring-1 ring-indigo-500/50' : 'border-slate-800'} p-2 min-h-[105px] rounded-lg flex flex-col transition hover:border-slate-700">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-xs font-semibold ${isToday ? 'bg-indigo-600 text-white px-1.5 py-0.5 rounded-full' : 'text-slate-400'}">
                            ${day}
                        </span>
                        ${dayTasks.length > 0 ? `<span class="text-[10px] text-slate-400 font-medium">${dayTasks.length} task${dayTasks.length > 1 ? 's' : ''}</span>` : ''}
                    </div>
                    <div class="flex flex-col gap-1 overflow-y-auto max-h-[75px] pr-0.5">
                        ${dayTasks.map(t => `
                            <div onclick="App.openTaskModal(${t.id})" 
                                 class="cursor-pointer text-[11px] px-1.5 py-0.5 rounded truncate bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700/80 flex items-center gap-1 ${t.status === 'completed' ? 'line-through opacity-50' : ''}"
                                 title="${escapeHtml(t.title)}">
                                <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background: ${t.category_color || '#818cf8'}"></span>
                                <span class="truncate">${escapeHtml(t.title)}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        return `
            <div class="flex flex-col gap-4 pb-10">
                <div class="flex items-center justify-between bg-slate-900/80 border border-slate-800 rounded-xl p-4">
                    <h2 class="text-lg font-bold text-slate-100 flex items-center gap-2">
                        <i data-lucide="calendar" class="w-5 h-5 text-indigo-400"></i>
                        ${monthNames[month]} ${year}
                    </h2>
                    <span class="text-xs text-slate-400">Deadlines & Scheduled Tasks</span>
                </div>

                <div class="grid grid-cols-7 gap-2 text-center text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">
                    <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
                </div>

                <div class="grid grid-cols-7 gap-2">
                    ${gridHtml}
                </div>
            </div>
        `;
    },

    /**
     * Render Analytics & Productivity Stats View
     */
    async renderStats() {
        const container = document.getElementById('view-container');
        container.innerHTML = `<div class="p-8 text-center text-slate-400">Loading productivity insights...</div>`;

        try {
            const res = await fetch('api/stats.php');
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Failed to load stats');

            const m = data.metrics;

            container.innerHTML = `
                <div class="flex flex-col gap-6 pb-12 animate-fade-in">
                    <!-- KPI Cards -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4">
                            <span class="text-xs text-slate-400 font-medium">Completion Rate</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <span class="text-2xl font-bold text-emerald-400">${m.completion_rate}%</span>
                                <span class="text-xs text-slate-400">(${m.completed_tasks}/${m.total_tasks})</span>
                            </div>
                            <div class="w-full bg-slate-800 h-1.5 rounded-full mt-3 overflow-hidden">
                                <div class="bg-emerald-500 h-full rounded-full" style="width: ${m.completion_rate}%"></div>
                            </div>
                        </div>

                        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4">
                            <span class="text-xs text-slate-400 font-medium">Daily Streak</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <span class="text-2xl font-bold text-amber-400">${m.current_streak_days} Days</span>
                                <span class="text-xs text-amber-300">🔥 active</span>
                            </div>
                            <span class="text-[11px] text-slate-500 mt-2 block">Consecutive days achieving goals</span>
                        </div>

                        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4">
                            <span class="text-xs text-slate-400 font-medium">Time Logged</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <span class="text-2xl font-bold text-indigo-400">${Math.floor(m.total_minutes_logged / 60)}h ${m.total_minutes_logged % 60}m</span>
                            </div>
                            <span class="text-[11px] text-slate-400 mt-2 block">Today: ${m.today_minutes_logged} minutes</span>
                        </div>

                        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-4">
                            <span class="text-xs text-slate-400 font-medium">Overdue Tasks</span>
                            <div class="flex items-baseline gap-2 mt-1">
                                <span class="text-2xl font-bold ${m.overdue_tasks > 0 ? 'text-red-400' : 'text-slate-300'}">${m.overdue_tasks}</span>
                                <span class="text-xs text-slate-400">items</span>
                            </div>
                            <span class="text-[11px] text-slate-500 mt-2 block">Active backlog: ${m.active_tasks}</span>
                        </div>
                    </div>

                    <!-- 7-Day Velocity Chart -->
                    <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-5">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-semibold text-slate-200 text-sm">7-Day Completion Velocity</h3>
                            <span class="text-xs text-slate-400">Tasks completed per day</span>
                        </div>
                        <div class="grid grid-cols-7 gap-3 items-end h-40 pt-4 border-b border-slate-800">
                            ${data.last_7_days.map(d => {
                                const maxCount = Math.max(...data.last_7_days.map(x => x.completed_tasks), 5);
                                const heightPercent = Math.max((d.completed_tasks / maxCount) * 100, 8);
                                return `
                                    <div class="flex flex-col items-center gap-2 h-full justify-end">
                                        <span class="text-xs font-bold text-indigo-400">${d.completed_tasks}</span>
                                        <div class="w-full max-w-[40px] bg-indigo-600/80 hover:bg-indigo-500 rounded-t-md transition" style="height: ${heightPercent}%"></div>
                                        <span class="text-[11px] text-slate-400 font-medium mt-1">${d.label}</span>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>

                    <!-- Breakdown Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- Category Distribution -->
                        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-5">
                            <h3 class="font-semibold text-slate-200 text-sm mb-4">Active Tasks by Category</h3>
                            <div class="flex flex-col gap-3">
                                ${data.category_distribution.map(c => `
                                    <div class="flex items-center justify-between text-xs">
                                        <div class="flex items-center gap-2">
                                            <span class="w-3 h-3 rounded-full" style="background: ${c.color}"></span>
                                            <span class="text-slate-300 font-medium">${escapeHtml(c.category)}</span>
                                        </div>
                                        <span class="font-semibold text-slate-400">${c.count} tasks</span>
                                    </div>
                                `).join('')}
                                ${data.category_distribution.length === 0 ? '<span class="text-xs text-slate-500">No active tasks</span>' : ''}
                            </div>
                        </div>

                        <!-- Activity Audit Log -->
                        <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-5">
                            <h3 class="font-semibold text-slate-200 text-sm mb-4">Audit Activity Log</h3>
                            <div class="flex flex-col gap-2.5 max-h-56 overflow-y-auto pr-1">
                                ${data.recent_activities.map(a => `
                                    <div class="flex items-start gap-2.5 text-xs pb-2 border-b border-slate-800/60">
                                        <div class="w-2 h-2 rounded-full bg-indigo-400 mt-1.5 shrink-0"></div>
                                        <div class="flex-1 min-w-0">
                                            <span class="text-slate-300 font-medium">${escapeHtml(a.details || a.action)}</span>
                                            <span class="text-[10px] text-slate-500 block">${a.created_at}</span>
                                        </div>
                                    </div>
                                `).join('')}
                                ${data.recent_activities.length === 0 ? '<span class="text-xs text-slate-500">No activities recorded yet</span>' : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            lucide.createIcons();
        } catch (err) {
            container.innerHTML = `<div class="p-8 text-center text-red-400">Failed to load analytics: ${escapeHtml(err.message)}</div>`;
        }
    },

    // HTML5 Drag & Drop handlers for Kanban
    draggedTaskId: null,
    handleDragStart(e, taskId) {
        Views.draggedTaskId = taskId;
        e.dataTransfer.setData('text/plain', taskId);
        e.currentTarget.classList.add('dragging');
    },
    handleDragOver(e) {
        e.preventDefault();
        e.currentTarget.classList.add('drop-zone-active');
    },
    handleDragLeave(e) {
        e.currentTarget.classList.remove('drop-zone-active');
    },
    async handleDrop(e, targetStatus) {
        e.preventDefault();
        e.currentTarget.classList.remove('drop-zone-active');
        const taskId = Views.draggedTaskId;
        if (!taskId) return;

        // Visual un-drag
        document.querySelectorAll('.dragging').forEach(el => el.classList.remove('dragging'));

        // Save reordered status
        await App.updateTaskStatus(taskId, targetStatus);
    }
};

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
