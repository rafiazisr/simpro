import { gantt } from 'dhtmlx-gantt';
import 'dhtmlx-gantt/codebase/dhtmlxgantt.css';
import '../css/gantt-theme.css';
import axios from 'axios';

const toYmd = gantt.date.date_to_str('%Y-%m-%d');
const dayMonth = gantt.date.date_to_str('%j %M');

// Skala waktu per tingkat zoom.
const ZOOM = {
    hari: {
        min_column_width: 34,
        scales: [
            { unit: 'week', step: 1, format: (d) => `Minggu ${dayMonth(d)} – ${dayMonth(gantt.date.add(d, 6, 'day'))}` },
            { unit: 'day', step: 1, format: '%j', css: (d) => ([0, 6].includes(d.getDay()) ? 'weekend' : '') },
        ],
    },
    minggu: {
        min_column_width: 64,
        scales: [
            { unit: 'month', step: 1, format: '%F %Y' },
            { unit: 'week', step: 1, format: (d) => dayMonth(d) },
        ],
    },
    bulan: {
        min_column_width: 90,
        scales: [
            { unit: 'quarter', step: 1, format: (d) => `Q${Math.floor(d.getMonth() / 3) + 1} ${d.getFullYear()}` },
            { unit: 'month', step: 1, format: '%M' },
        ],
    },
};

let onSprintClick = () => {};
let onSaved = () => {};
let onError = () => {};

const MOBILE_QUERY = '(max-width: 760px)';
const isNarrow = () => !!window.matchMedia?.(MOBILE_QUERY).matches;

function setup(el, { updateUrl }) {
    try {
        gantt.i18n.setLocale('id');
    } catch (e) {
        // locale tidak tersedia: tetap pakai bahasa bawaan
    }

    gantt.plugins({ tooltip: true });

    Object.assign(gantt.config, {
        date_format: '%Y-%m-%d',
        row_height: 40,
        bar_height: 22,
        scale_height: 56,
        grid_width: 430,
        fit_tasks: true,
        drag_links: false,
        drag_progress: false,
        show_links: false,
        details_on_dblclick: false,
        details_on_create: false,
        select_task: false,
        scroll_on_click: false, // jangan menggulung timeline saat bar diklik
        open_tree_initially: true,
        show_errors: false,
    });

    const columns = [
        {
            name: 'text', label: 'Project / Sprint', tree: true, width: '*', min_width: 200,
            template: (t) => {
                const sw = t.kind === 'project'
                    ? `<span class="gantt-sw" style="background:${t.swatch}"></span><b>${escapeHtml(t.code)}</b> · `
                    : `<span class="gantt-sw gantt-sw-dot bar-${t.status}"></span>`;
                return sw + escapeHtml(t.text);
            },
        },
        { name: 'start', label: 'Mulai', align: 'center', width: 72, template: (t) => t.start_label },
        { name: 'end', label: 'Selesai', align: 'center', width: 72, template: (t) => t.end_label },
        { name: 'pct', label: 'Progres', align: 'center', width: 66, template: (t) => `${Math.round(t.progress * 100)}%` },
    ];

    const applyLayout = () => {
        const narrow = isNarrow();
        gantt.config.grid_width = narrow ? 150 : 430;
        gantt.config.columns = narrow ? [{ ...columns[0], min_width: 120 }] : columns;
        gantt.config.scale_height = narrow ? 48 : 56;
        return narrow;
    };
    let narrow = applyLayout();
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            if (isNarrow() !== narrow) {
                narrow = applyLayout();
                gantt.render();
            }
        }, 150);
    });

    gantt.templates.task_class = (s, e, t) => (t.kind === 'project'
        ? 'gantt_project'
        : `bar-${t.status}${t.readonly ? '' : ' is-draggable'}`);
    gantt.templates.grid_row_class = (s, e, t) => (t.kind === 'project' ? 'gantt_parent' : '');
    gantt.templates.task_row_class = (s, e, t) => (t.kind === 'project' ? 'gantt_parent' : '');
    gantt.templates.task_text = () => '';
    gantt.templates.rightside_text = (s, e, t) => (t.kind === 'project' ? '' : `${escapeHtml(t.text)} · ${Math.round(t.progress * 100)}%`);
    gantt.templates.tooltip_text = (s, e, t) => (t.kind === 'project'
        ? `<b>${escapeHtml(t.text)}</b><br>${t.start_label} – ${t.end_label}<br>Progres ${Math.round(t.progress * 100)}%`
        : `<b>${escapeHtml(t.project)} — ${escapeHtml(t.text)}</b><br>${escapeHtml(t.range_label)} · ${t.backlogs} backlog<br>${t.readonly ? 'Klik untuk melihat backlog' : 'Klik untuk melihat backlog · tarik untuk menggeser'}`);

    gantt.attachEvent('onBeforeTaskDrag', (id, mode) => {
        const t = gantt.getTask(id);
        return t.kind === 'sprint' && !t.readonly && (mode === 'move' || mode === 'resize');
    });

    gantt.attachEvent('onAfterTaskDrag', (id) => {
        const t = gantt.getTask(id);
        const end = gantt.date.add(t.end_date, -1, 'day'); // DHTMLX: tanggal selesai eksklusif
        axios.put(updateUrl.replace('__ID__', t.sprint_id), { start_date: toYmd(t.start_date), end_date: toYmd(end) })
            .then(({ data }) => {
                t.range_label = data.range_label;
                t.start_label = dayMonth(t.start_date);
                t.end_label = dayMonth(end);
                gantt.updateTask(id);
                onSaved(data.message);
            })
            .catch((err) => onError(err.response?.data?.message || 'Gagal menyimpan jadwal sprint.'));
    });

    gantt.attachEvent('onTaskClick', (id, e) => {
        const t = gantt.getTask(id);
        const onBar = e.target.closest('.gantt_task_line');
        if (t.kind === 'sprint' && (onBar || e.target.closest('.gantt_row'))) {
            onSprintClick(t);
        } else if (t.kind === 'project' && onBar) {
            window.location = t.url;
        }
        return true; // tetap izinkan buka/tutup swimlane
    });
    gantt.attachEvent('onTaskDblClick', () => false);
    gantt.attachEvent('onBeforeLightbox', () => false);

    const drawToday = () => {
        const area = gantt.$task_data;
        if (!area) return;
        area.querySelectorAll('.gantt-today').forEach((n) => n.remove());
        const { start_date: from, end_date: to } = gantt.getState();
        const now = new Date();
        if (now < from || now > to) return;
        const line = document.createElement('div');
        line.className = 'gantt-today';
        line.style.left = `${gantt.posFromDate(now)}px`;
        line.innerHTML = `<span>Hari ini, ${dayMonth(now)}</span>`;
        area.appendChild(line);
    };
    gantt.attachEvent('onGanttRender', drawToday);
    gantt.attachEvent('onDataRender', drawToday);

    gantt.init(el);
}

function hideTooltip() {
    try {
        gantt.ext.tooltips?.tooltip?.hide();
    } catch (e) {
        // abaikan
    }
}

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

function setZoom(level) {
    const z = ZOOM[level] ?? ZOOM.bulan;
    gantt.config.scales = z.scales;
    gantt.config.min_column_width = z.min_column_width;
}

window.Alpine.data('ganttPage', (cfg) => ({
    projects: cfg.initialProjects ?? [],
    sprintStatus: '',
    from: '',
    to: '',
    zoom: 'minggu',
    loading: true,
    empty: false,
    projectMenu: false,
    toast: null,
    modal: { open: false, loading: false, sprint: null, backlogs: [] },

    init() {
        setZoom(this.zoom);
        setup(this.$refs.chart, { updateUrl: cfg.updateUrl });
        onSprintClick = (t) => this.openSprint(t);
        onSaved = (msg) => this.notify(msg);
        onError = (msg) => { this.notify(msg, 'error'); this.load(); };
        this.load();
        ['projects', 'sprintStatus', 'from', 'to'].forEach((k) => this.$watch(k, () => this.load()));
    },

    get projectLabel() {
        if (!this.projects.length) return 'Semua project';
        const names = cfg.allProjects.filter((p) => this.projects.includes(p.id)).map((p) => p.name);
        return names.length > 2 ? `${names.slice(0, 2).join(', ')} +${names.length - 2}` : names.join(', ');
    },

    get rangeLabel() {
        const fmt = (s) => (s ? dayMonth(new Date(`${s}T00:00:00`)) : '…');
        return this.from || this.to ? `${fmt(this.from)} – ${fmt(this.to)}` : 'Semua tanggal';
    },

    toggleProject(id) {
        this.projects = this.projects.includes(id) ? this.projects.filter((p) => p !== id) : [...this.projects, id];
    },

    async load() {
        this.loading = true;
        const params = new URLSearchParams();
        this.projects.forEach((id) => params.append('projects[]', id));
        if (this.sprintStatus) params.set('sprint_status', this.sprintStatus);
        if (this.from) params.set('from', this.from);
        if (this.to) params.set('to', this.to);

        try {
            const { data } = await axios.get(`${cfg.dataUrl}?${params}`);
            gantt.clearAll();
            // Rentang tampilan mengikuti filter tanggal bila diisi.
            gantt.config.start_date = this.from ? new Date(`${this.from}T00:00:00`) : undefined;
            gantt.config.end_date = this.to ? gantt.date.add(new Date(`${this.to}T00:00:00`), 1, 'day') : undefined;
            gantt.config.fit_tasks = !(this.from || this.to);
            gantt.parse(data);
            this.empty = data.data.length === 0;
        } catch (e) {
            this.notify(e.response?.data?.message || 'Gagal memuat data Gantt.', 'error');
        } finally {
            this.loading = false;
            this.$nextTick(() => gantt.render());
        }
    },

    setZoom(level) {
        this.zoom = level;
        setZoom(level);
        gantt.render();
    },

    reset() {
        this.projects = cfg.lockedProject ? [cfg.lockedProject] : [];
        this.sprintStatus = '';
        this.from = '';
        this.to = '';
        this.setZoom('minggu');
    },

    async openSprint(task) {
        hideTooltip(); // tooltip DHTMLX jangan tertinggal di atas modal
        this.modal = { open: true, loading: true, sprint: { name: task.text, project: task.project, range: task.range_label }, backlogs: [] };
        try {
            const { data } = await axios.get(cfg.backlogsUrl.replace('__ID__', task.sprint_id));
            this.modal = { open: true, loading: false, ...data };
        } catch (e) {
            this.modal.open = false;
            this.notify('Gagal memuat backlog sprint.', 'error');
        }
    },

    barStyle(b) {
        if (!b.start && !b.end) return 'display: none';
        const s = this.modal.sprint;
        const day = (x) => new Date(`${x}T00:00:00`).getTime() / 864e5;
        const lo = day(s.start);
        const span = Math.max(day(s.end) - lo + 1, 1);
        const a = Math.max(day(b.start ?? b.end) - lo, 0);
        const z = Math.min(day(b.end ?? b.start) - lo + 1, span);
        return `left: ${(a / span) * 100}%; width: ${Math.max(((z - a) / span) * 100, 2)}%; background: ${b.color}`;
    },

    notify(message, type = 'success') {
        this.toast = { message, type };
        clearTimeout(this._t);
        this._t = setTimeout(() => (this.toast = null), 4000);
    },
}));
