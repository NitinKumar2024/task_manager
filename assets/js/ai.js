/**
 * AI Assistant Module - Powered by Gemini
 */

const AI = {
    copilotOpen: false,

    /**
     * Parse natural language input and create task
     */
    async handleSmartQuickAdd(inputEl) {
        const text = inputEl.value.trim();
        if (!text) return;

        const btn = document.getElementById('ai-quick-btn');
        const originalHtml = btn.innerHTML;
        btn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 animate-spin text-indigo-400"></i>`;
        btn.disabled = true;
        lucide.createIcons();

        try {
            const res = await fetch('api/ai.php?action=quick_parse', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ text, auto_create: true })
            });
            const data = await res.json();

            if (!data.success) {
                if (data.needs_key) {
                    App.showToast(data.message, 'warning');
                    App.openSettingsModal();
                    return;
                }
                throw new Error(data.message || 'AI parsing failed');
            }

            inputEl.value = '';
            App.showToast(`✨ Smart Task Created: "${data.parsed.title}"`, 'success');
            await App.loadTasks();

            // If parsed with subtasks or details, we could highlight it
            if (data.task_id) {
                const card = document.getElementById(`task-card-${data.task_id}`) || document.getElementById(`task-row-${data.task_id}`);
                if (card) {
                    card.classList.add('ring-2', 'ring-indigo-500');
                    setTimeout(() => card.classList.remove('ring-2', 'ring-indigo-500'), 3000);
                }
            }
        } catch (err) {
            App.showToast(err.message, 'error');
        } finally {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
            lucide.createIcons();
        }
    },

    /**
     * Open AI Daily Plan / Briefing Modal
     */
    async openDailyPlan() {
        const modal = document.getElementById('ai-modal');
        const title = document.getElementById('ai-modal-title');
        const content = document.getElementById('ai-modal-content');

        title.innerHTML = `<i data-lucide="sun" class="w-5 h-5 text-amber-400"></i> AI Daily Briefing & Plan My Day`;
        content.innerHTML = `
            <div class="py-12 flex flex-col items-center justify-center text-slate-400 gap-3">
                <i data-lucide="sparkles" class="w-8 h-8 text-indigo-400 animate-spin"></i>
                <p class="text-sm">Gemini is analyzing your priorities, deadlines, and schedule...</p>
            </div>
        `;
        modal.classList.remove('hidden');
        lucide.createIcons();

        try {
            const res = await fetch('api/ai.php?action=plan_day');
            const data = await res.json();

            if (!data.success) {
                if (data.needs_key) {
                    App.showToast(data.message, 'warning');
                    modal.classList.add('hidden');
                    App.openSettingsModal();
                    return;
                }
                throw new Error(data.message || 'Failed to generate plan');
            }

            const p = data.plan;

            content.innerHTML = `
                <div class="flex flex-col gap-5 text-slate-200">
                    <div class="bg-gradient-to-r from-indigo-950/60 to-purple-950/60 border border-indigo-800/50 rounded-xl p-4">
                        <h4 class="text-base font-bold text-indigo-300">${escapeHtml(p.greeting || 'Good Day!')}</h4>
                        <p class="text-xs text-slate-300 italic mt-1">"${escapeHtml(p.productivity_quote || 'Action produces clarity.')}"</p>
                    </div>

                    <div>
                        <h5 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                            <i data-lucide="target" class="w-4 h-4 text-red-400"></i> Top Priority Focus Today
                        </h5>
                        <div class="flex flex-col gap-2">
                            ${(p.top_priorities || []).map(item => `
                                <div class="bg-slate-900/80 border border-slate-800 p-3 rounded-lg flex flex-col gap-1">
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold text-sm text-slate-100">${escapeHtml(item.title)}</span>
                                        ${item.task_id ? `<button onclick="App.openTaskModal(${item.task_id})" class="text-xs text-indigo-400 hover:underline">Open Task &rarr;</button>` : ''}
                                    </div>
                                    <span class="text-xs text-slate-400">${escapeHtml(item.reason)}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>

                    <div>
                        <h5 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                            <i data-lucide="clock" class="w-4 h-4 text-indigo-400"></i> Optimized Time Blocks
                        </h5>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            ${(p.time_blocks || []).map(b => `
                                <div class="bg-slate-900/90 border border-slate-800 p-3 rounded-lg flex flex-col">
                                    <span class="text-xs font-bold text-indigo-400">${escapeHtml(b.phase)}</span>
                                    <p class="text-[11px] text-slate-400 italic mt-0.5 mb-2">${escapeHtml(b.advice || '')}</p>
                                    <ul class="text-xs text-slate-300 flex flex-col gap-1 list-disc list-inside">
                                        ${(b.tasks || []).map(t => `<li class="truncate">${escapeHtml(t)}</li>`).join('')}
                                    </ul>
                                </div>
                            `).join('')}
                        </div>
                    </div>

                    ${p.strategic_advice ? `
                        <div class="bg-slate-900/50 border border-slate-800 p-3 rounded-lg text-xs text-slate-400">
                            <strong class="text-slate-200">Chief of Staff Tip:</strong> ${escapeHtml(p.strategic_advice)}
                        </div>
                    ` : ''}
                </div>
            `;
            lucide.createIcons();
        } catch (err) {
            content.innerHTML = `<div class="p-6 text-center text-red-400">${escapeHtml(err.message)}</div>`;
        }
    },

    /**
     * AI Task Breakdown Modal
     */
    async openTaskBreakdown(taskId) {
        const modal = document.getElementById('ai-modal');
        const title = document.getElementById('ai-modal-title');
        const content = document.getElementById('ai-modal-content');

        title.innerHTML = `<i data-lucide="sparkles" class="w-5 h-5 text-purple-400"></i> AI Task Breakdown (Decompose)`;
        content.innerHTML = `
            <div class="py-12 flex flex-col items-center justify-center text-slate-400 gap-3">
                <i data-lucide="loader-2" class="w-8 h-8 text-purple-400 animate-spin"></i>
                <p class="text-sm">Gemini is deconstructing this task into actionable step-by-step subtasks...</p>
            </div>
        `;
        modal.classList.remove('hidden');
        lucide.createIcons();

        try {
            const res = await fetch('api/ai.php?action=breakdown', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ task_id: taskId })
            });
            const data = await res.json();

            if (!data.success) {
                if (data.needs_key) {
                    App.showToast(data.message, 'warning');
                    modal.classList.add('hidden');
                    App.openSettingsModal();
                    return;
                }
                throw new Error(data.message || 'AI breakdown failed');
            }

            content.innerHTML = `
                <div class="flex flex-col gap-4 text-slate-200">
                    ${data.summary ? `
                        <div class="p-3 bg-purple-950/40 border border-purple-800/60 rounded-lg text-xs text-purple-200">
                            <strong>Strategy:</strong> ${escapeHtml(data.summary)}
                        </div>
                    ` : ''}

                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-400 uppercase">Generated Actionable Subtasks:</span>
                        <button onclick="AI.selectAllBreakdown(true)" class="text-xs text-indigo-400 hover:underline">Select All</button>
                    </div>

                    <div class="flex flex-col gap-2 max-h-72 overflow-y-auto pr-1" id="breakdown-items-list">
                        ${data.subtasks.map((st, i) => `
                            <label class="flex items-center justify-between gap-3 p-3 bg-slate-900 border border-slate-800 rounded-lg hover:border-slate-700 cursor-pointer">
                                <div class="flex items-center gap-3">
                                    <input type="checkbox" checked class="task-checkbox breakdown-checkbox" data-idx="${i}">
                                    <span class="text-sm text-slate-200 font-medium">${escapeHtml(st.title)}</span>
                                </div>
                                <span class="text-xs px-2 py-0.5 rounded bg-slate-800 text-slate-400">${st.estimated_minutes || 15}m</span>
                            </label>
                        `).join('')}
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-800">
                        <button onclick="document.getElementById('ai-modal').classList.add('hidden')" class="px-4 py-2 rounded-lg text-xs text-slate-400 hover:bg-slate-800 transition">
                            Cancel
                        </button>
                        <button id="apply-breakdown-btn" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white rounded-lg text-xs font-semibold transition flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4"></i> Apply Subtasks to Task
                        </button>
                    </div>
                </div>
            `;
            lucide.createIcons();

            // Bind click handler with payload
            document.getElementById('apply-breakdown-btn').onclick = async () => {
                const selected = [];
                document.querySelectorAll('.breakdown-checkbox:checked').forEach(cb => {
                    const idx = parseInt(cb.dataset.idx);
                    if (data.subtasks[idx]) selected.push(data.subtasks[idx]);
                });

                if (selected.length === 0) {
                    App.showToast('Please select at least one subtask', 'warning');
                    return;
                }

                await fetch('api/subtasks.php?action=bulk_create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ task_id: taskId, subtasks: selected })
                });

                modal.classList.add('hidden');
                App.showToast(`✨ Added ${selected.length} subtasks!`, 'success');
                await App.loadTasks();

                // If task modal was open, refresh it
                const taskModal = document.getElementById('task-modal');
                if (!taskModal.classList.contains('hidden')) {
                    App.openTaskModal(taskId);
                }
            };
        } catch (err) {
            content.innerHTML = `<div class="p-6 text-center text-red-400">${escapeHtml(err.message)}</div>`;
        }
    },

    selectAllBreakdown(val) {
        document.querySelectorAll('.breakdown-checkbox').forEach(cb => cb.checked = val);
    },

    /**
     * Polish current task inside Edit Modal
     */
    async polishTaskInModal() {
        const titleInput = document.getElementById('modal-task-title');
        const descInput = document.getElementById('modal-task-desc');
        const prioritySelect = document.getElementById('modal-task-priority');
        const estInput = document.getElementById('modal-task-estimated');
        const polishBtn = document.getElementById('polish-task-btn');

        if (!titleInput.value.trim()) {
            App.showToast('Please enter a task title first', 'warning');
            return;
        }

        const origHtml = polishBtn.innerHTML;
        polishBtn.innerHTML = `<i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i> Polishing...`;
        polishBtn.disabled = true;
        lucide.createIcons();

        try {
            const res = await fetch('api/ai.php?action=enhance', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    title: titleInput.value.trim(),
                    description: descInput.value.trim()
                })
            });
            const data = await res.json();

            if (!data.success) {
                if (data.needs_key) {
                    App.showToast(data.message, 'warning');
                    App.openSettingsModal();
                    return;
                }
                throw new Error(data.message || 'Enhancement failed');
            }

            const enh = data.enhanced;
            if (enh.improved_title) titleInput.value = enh.improved_title;
            if (enh.enhanced_description) descInput.value = enh.enhanced_description;
            if (enh.suggested_priority && prioritySelect) prioritySelect.value = enh.suggested_priority;
            if (enh.estimated_minutes && estInput && !estInput.value) estInput.value = enh.estimated_minutes;

            App.showToast('✨ Task polished with SMART criteria!', 'success');
        } catch (err) {
            App.showToast(err.message, 'error');
        } finally {
            polishBtn.innerHTML = origHtml;
            polishBtn.disabled = false;
            lucide.createIcons();
        }
    },

    /**
     * AI Copilot Chat Drawer
     */
    toggleCopilot() {
        const drawer = document.getElementById('copilot-drawer');
        AI.copilotOpen = !AI.copilotOpen;
        if (AI.copilotOpen) {
            drawer.classList.remove('translate-x-full');
            document.getElementById('copilot-input').focus();
            AI.loadCopilotHistory();
        } else {
            drawer.classList.add('translate-x-full');
        }
    },

    async loadCopilotHistory() {
        const container = document.getElementById('copilot-messages');
        if (container.children.length > 1) return; // already loaded

        try {
            const res = await fetch('api/ai.php?action=chat_history');
            const data = await res.json();
            if (data.success && data.history.length > 0) {
                container.innerHTML = '';
                data.history.forEach(h => AI.appendChatMessage(h.role, h.message));
                container.scrollTop = container.scrollHeight;
            }
        } catch (e) {
            // ignore
        }
    },

    async sendCopilotMessage(customText = null) {
        const input = document.getElementById('copilot-input');
        const text = customText || input.value.trim();
        if (!text) return;

        if (!customText) input.value = '';

        AI.appendChatMessage('user', text);
        const container = document.getElementById('copilot-messages');
        container.scrollTop = container.scrollHeight;

        // Typing indicator
        const typingEl = document.createElement('div');
        typingEl.id = 'copilot-typing';
        typingEl.className = 'flex items-center gap-2 text-xs text-slate-400 p-3 bg-slate-800/60 rounded-xl max-w-[80%]';
        typingEl.innerHTML = `<i data-lucide="sparkles" class="w-3.5 h-3.5 text-indigo-400 animate-spin"></i> NexusAI is thinking...`;
        container.appendChild(typingEl);
        lucide.createIcons();
        container.scrollTop = container.scrollHeight;

        try {
            const res = await fetch('api/ai.php?action=chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: text })
            });
            const data = await res.json();

            const typing = document.getElementById('copilot-typing');
            if (typing) typing.remove();

            if (!data.success) {
                if (data.needs_key) {
                    AI.appendChatMessage('assistant', '⚠️ Gemini API key is missing. Please open Settings to configure your free Gemini API key.');
                    App.openSettingsModal();
                    return;
                }
                throw new Error(data.message || 'Chat error');
            }

            AI.appendChatMessage('assistant', data.reply);
            container.scrollTop = container.scrollHeight;
        } catch (err) {
            const typing = document.getElementById('copilot-typing');
            if (typing) typing.remove();
            AI.appendChatMessage('assistant', `❌ Error: ${err.message}`);
        }
    },

    appendChatMessage(role, text) {
        const container = document.getElementById('copilot-messages');
        const isUser = role === 'user';
        const msgDiv = document.createElement('div');
        msgDiv.className = `flex flex-col ${isUser ? 'items-end' : 'items-start'} max-w-[88%] ${isUser ? 'self-end' : 'self-start'}`;

        msgDiv.innerHTML = `
            <div class="p-3.5 rounded-2xl text-xs leading-relaxed shadow-sm ${
                isUser 
                    ? 'bg-indigo-600 text-white rounded-br-xs' 
                    : 'bg-slate-800 text-slate-200 border border-slate-700/80 rounded-bl-xs prose-dark'
            }">
                ${isUser ? escapeHtml(text) : AI.renderSimpleMarkdown(text)}
            </div>
            <span class="text-[9px] text-slate-500 mt-1 px-1">${isUser ? 'You' : 'NexusAI Copilot'}</span>
        `;
        container.appendChild(msgDiv);
    },

    async clearCopilotChat() {
        if (!confirm('Clear chat history?')) return;
        await fetch('api/ai.php?action=clear_chat');
        const container = document.getElementById('copilot-messages');
        container.innerHTML = `
            <div class="p-4 bg-slate-800/60 border border-slate-700 rounded-xl text-xs text-slate-300">
                👋 Hello! I am your AI Copilot. Ask me to prioritize tasks, draft emails, organize your schedule, or break down any project.
            </div>
        `;
    },

    renderSimpleMarkdown(text) {
        if (!text) return '';
        let md = escapeHtml(text);
        // Bold
        md = md.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // Italic
        md = md.replace(/\*(.*?)\*/g, '<em>$1</em>');
        // Bullet points
        md = md.replace(/^\s*[\-\*]\s+(.*)$/gm, '<li class="ml-4 list-disc">$1</li>');
        // Linebreaks
        md = md.replace(/\n/g, '<br>');
        return md;
    }
};
