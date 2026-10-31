import './bootstrap';
import Alpine from 'alpinejs';
import 'trix';
import 'trix/dist/trix.css';

// Editor rich text: lampiran hanya diizinkan di editor yang punya data-upload-url
// (dokumentasi), berupa gambar jpg/png/webp maksimal 2 MB (FR-33).
const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

document.addEventListener('trix-file-accept', (e) => {
    const editor = e.target;
    const reason = !editor.dataset.uploadUrl ? 'Lampiran tidak didukung di editor ini.'
        : !IMAGE_TYPES.includes(e.file.type) ? 'Format gambar harus jpg, png, atau webp.'
            : e.file.size > 2 * 1024 * 1024 ? 'Ukuran gambar maksimal 2 MB.' : null;

    if (reason) {
        e.preventDefault();
        if (editor.dataset.uploadUrl) window.alert(reason);
    }
});

document.addEventListener('trix-attachment-add', (e) => {
    const { attachment } = e;
    const editor = e.target;
    if (!attachment.file || !editor.dataset.uploadUrl) return;

    const form = new FormData();
    form.append('file', attachment.file);
    form.append('project', editor.dataset.uploadProject ?? '');

    window.axios.post(editor.dataset.uploadUrl, form, {
        onUploadProgress: (p) => p.total && attachment.setUploadProgress((p.loaded / p.total) * 100),
    }).then(({ data }) => {
        attachment.setAttributes({ url: data.url, href: data.url });
    }).catch((err) => {
        attachment.remove();
        window.alert(err.response?.data?.message || 'Upload gambar gagal.');
    });
});

window.Alpine = Alpine;

// Toast flash: hilang otomatis setelah 4 detik.
Alpine.data('toast', () => ({
    show: true,
    init() {
        setTimeout(() => (this.show = false), 4000);
    },
}));

// Dialog konfirmasi generik: <button @click="$dispatch('confirm', {...})">
Alpine.data('confirmDialog', () => ({
    open: false,
    title: '',
    message: '',
    detail: '',
    action: '',
    method: 'DELETE',
    confirmLabel: 'Ya, lanjutkan',
    show(e) {
        Object.assign(this, { confirmLabel: 'Ya, lanjutkan', method: 'DELETE', detail: '' }, e.detail, { open: true });
        this.$nextTick(() => this.$refs.cancel?.focus());
    },
}));

// Combobox status (FR-13): cari status; jika belum ada, tawarkan "Buat status baru".
Alpine.data('statusCombobox', (options = [], value = '') => ({
    options,          // [{ name, cls, meta }]
    value,            // nama status terpilih (dikirim sebagai status_name)
    query: '',
    open: false,
    active: 0,
    get q() {
        return this.query.trim();
    },
    get results() {
        const q = this.q.toLowerCase();
        return q ? this.options.filter((o) => o.name.toLowerCase().includes(q)) : this.options;
    },
    get exact() {
        return this.options.some((o) => o.name.toLowerCase() === this.q.toLowerCase());
    },
    get showCreate() {
        return this.q !== '' && !this.exact;
    },
    get selected() {
        return this.options.find((o) => o.name === this.value) ?? { name: this.value, cls: 'badge st-custom' };
    },
    highlight(name) {
        const q = this.q;
        if (!q) return [name, '', ''];
        const i = name.toLowerCase().indexOf(q.toLowerCase());
        return i < 0 ? [name, '', ''] : [name.slice(0, i), name.slice(i, i + q.length), name.slice(i + q.length)];
    },
    toggle() {
        this.open = !this.open;
        if (this.open) this.$nextTick(() => this.$refs.search.focus());
    },
    pick(name) {
        this.value = name;
        this.query = '';
        this.open = false;
    },
    create() {
        const name = this.q;
        if (!name) return;
        this.options.push({ name, cls: 'badge st-custom', meta: 'baru → To Do' });
        this.pick(name);
    },
    enter() {
        if (this.results[this.active]) this.pick(this.results[this.active].name);
        else if (this.showCreate) this.create();
    },
    move(step) {
        const max = this.results.length - 1;
        this.active = Math.min(Math.max(this.active + step, 0), Math.max(max, 0));
    },
}));

// Lonceng notifikasi + koneksi SSE + notifikasi browser (FR-40 s.d. FR-42).
Alpine.data('notificationBell', (cfg) => ({
    items: cfg.items ?? [],
    unread: cfg.unread ?? 0,
    open: false,
    toast: null,
    askPermission: false,
    seen: new Set((cfg.items ?? []).map((n) => n.id)),

    init() {
        this.askPermission = 'Notification' in window
            && Notification.permission === 'default'
            && !safeStorage('get', 'notif-asked');
        this.connect();
    },

    connect() {
        if (!('EventSource' in window)) return;
        const url = `${cfg.streamUrl}?since=${encodeURIComponent(cfg.since)}`;
        const source = new EventSource(url);

        source.addEventListener('notification', (e) => this.receive(JSON.parse(e.data)));
        source.addEventListener('unread', (e) => { this.unread = parseInt(e.data, 10) || 0; });
        // Jika sesi berakhir (401/419) EventSource berhenti sendiri; tidak perlu tindakan.
    },

    receive(n) {
        if (this.seen.has(n.id)) return;
        this.seen.add(n.id);
        this.items = [n, ...this.items].slice(0, 8);

        this.toast = n;
        clearTimeout(this._t);
        this._t = setTimeout(() => (this.toast = null), 6000);

        if ('Notification' in window && Notification.permission === 'granted') {
            const native = new Notification(`${cfg.appName ?? 'Simpro'} · ${n.title}`, { body: n.text, tag: n.id });
            native.onclick = () => { window.focus(); window.location = n.url; };
        }
    },

    iconClass(kind) {
        return { project: 'n-ico n-proj', assign: 'n-ico n-assign', qa: 'n-ico n-qa' }[kind] ?? 'n-ico n-done';
    },

    async readAll() {
        await window.axios.post(cfg.readAllUrl, {}, { headers: { Accept: 'application/json' } });
        this.unread = 0;
        this.items = this.items.map((n) => ({ ...n, unread: false }));
    },

    async requestPermission() {
        safeStorage('set', 'notif-asked', '1');
        this.askPermission = false;
        try {
            await Notification.requestPermission();
        } catch (e) {
            // browser lama: abaikan
        }
    },

    dismissPermission() {
        safeStorage('set', 'notif-asked', '1');
        this.askPermission = false;
    },
}));

// Dashboard (FR-50 s.d. FR-53): donut status + list backlog yang bisa difilter.
Alpine.data('dashboardPage', (cfg) => ({
    data: cfg.initial ?? { slices: [], total: 0, project: { name: '' }, selected: null, tab: 'programmer' },
    projectId: cfg.initial?.project?.id ?? null,
    loading: false,

    // Irisan pie: sudut mulai/selesai tiap status (mulai dari jam 12, searah jarum jam).
    get arcs() {
        const total = this.data.total || 1;
        let angle = 0;
        return this.data.slices.filter((s) => s.count > 0).map((s) => {
            const sweep = (s.count / total) * Math.PI * 2;
            const arc = { ...s, start: angle, end: angle + sweep };
            angle += sweep;
            return arc;
        });
    },

    // String SVG irisan pie (x-for tidak bisa dipakai di dalam <svg>).
    get arcsSvg() {
        const esc = (v) => String(v).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
        const cx = 100, cy = 100, r = 90;
        const pt = (a, rad = r) => [cx + rad * Math.sin(a), cy - rad * Math.cos(a)].map((n) => n.toFixed(2));
        const arcs = this.arcs;
        return arcs.map((s) => {
            const sel = this.data.selected?.id === s.id;
            const dim = this.data.selected && !sel ? 0.3 : 1;
            // Irisan terpilih sedikit "ditarik" keluar.
            const mid = (s.start + s.end) / 2;
            const [dx, dy] = sel && arcs.length > 1 ? [6 * Math.sin(mid), -6 * Math.cos(mid)] : [0, 0];
            let shape;
            if (s.end - s.start >= Math.PI * 2 - 1e-6) {
                shape = `<circle cx="${cx}" cy="${cy}" r="${r}"`;
            } else {
                const [x1, y1] = pt(s.start);
                const [x2, y2] = pt(s.end);
                const large = s.end - s.start > Math.PI ? 1 : 0;
                shape = `<path d="M${cx} ${cy} L${x1} ${y1} A${r} ${r} 0 ${large} 1 ${x2} ${y2} Z"`;
            }
            return `${shape} data-id="${s.id}" fill="${esc(s.color)}" transform="translate(${dx.toFixed(2)} ${dy.toFixed(2)})"`
                + ` style="cursor:pointer;stroke:var(--surface);stroke-width:2;stroke-linejoin:round;transition:opacity .15s,transform .15s;opacity:${dim}">`
                + `<title>${esc(s.name)}: ${s.count} (${s.pct}%)</title>${shape.startsWith('<circle') ? '</circle>' : '</path>'}`;
        }).join('');
    },

    get selectedCount() {
        return this.data.slices.find((s) => s.id === this.data.selected?.id)?.count ?? 0;
    },

    pick(statusId) {
        const same = this.data.selected?.id === statusId;
        this.load(false, same ? null : statusId);
    },

    setTab(tab) {
        this.data.tab = tab;
        this.load(false, null);
    },

    async load(resetFilter = false, statusId = undefined) {
        if (!this.projectId) return;
        const status = resetFilter ? null : (statusId === undefined ? this.data.selected?.id : statusId);
        const params = new URLSearchParams({ project: this.projectId, tab: resetFilter ? 'programmer' : this.data.tab });
        if (status) params.set('status', status);

        this.loading = true;
        try {
            const { data } = await window.axios.get(`${cfg.dataUrl}?${params}`);
            this.data = data;
            const url = new URL(window.location);
            url.searchParams.set('project', this.projectId);
            window.history.replaceState({}, '', url);
        } finally {
            this.loading = false;
        }
    },
}));

function safeStorage(op, key, value) {
    try {
        return op === 'get' ? window.localStorage.getItem(key) : window.localStorage.setItem(key, value);
    } catch (e) {
        return null;
    }
}

function applyTheme(dark) {
    const root = document.documentElement;
    root.classList.toggle('dark', dark);
    root.style.colorScheme = dark ? 'dark' : 'light';
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.content = dark ? '#111827' : '#FFFFFF';
}

// Tombol tema terang/gelap. Kelas .dark dipasang di <html> (lihat components/theme-script).
Alpine.data('themeToggle', () => ({
    dark: document.documentElement.classList.contains('dark'),
    init() {
        window.addEventListener('theme-changed', (e) => { this.dark = e.detail.dark; });
    },
    toggle() {
        this.dark = !this.dark;
        applyTheme(this.dark);
        safeStorage('set', 'theme', this.dark ? 'dark' : 'light');
        // Sinkronkan tombol lain di halaman yang sama (mis. topbar + halaman login).
        window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark: this.dark } }));
    },
}));

// Tab lain mengganti tema: ikut berganti.
window.addEventListener('storage', (e) => {
    if (e.key !== 'theme' || !e.newValue) return;
    const dark = e.newValue === 'dark';
    applyTheme(dark);
    window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark } }));
});

// Penanda perubahan matriks hak akses yang belum disimpan.
Alpine.store('matrix', { dirty: false });

// Alpine dijalankan setelah SEMUA modul halaman (mis. gantt.js) selesai mendaftarkan
// komponennya. Modul ES dieksekusi saat readyState sudah "interactive" tetapi sebelum
// DOMContentLoaded, jadi tunggu DOMContentLoaded kecuali halaman sudah selesai dimuat.
if (document.readyState === 'complete') {
    Alpine.start();
} else {
    document.addEventListener('DOMContentLoaded', () => Alpine.start(), { once: true });
}
