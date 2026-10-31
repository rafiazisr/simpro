{{-- Terapkan tema (terang/gelap) sebelum halaman digambar agar tidak berkedip.
     Pilihan user disimpan di localStorage "theme"; bila belum memilih, ikut pengaturan perangkat. --}}
<meta name="theme-color" content="#FFFFFF">
<script>
    (function () {
        var saved = null;
        try { saved = localStorage.getItem('theme'); } catch (e) {}
        var mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
        var dark = saved ? saved === 'dark' : !!(mq && mq.matches);
        var root = document.documentElement;
        root.classList.toggle('dark', dark);
        root.style.colorScheme = dark ? 'dark' : 'light';
        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) meta.content = dark ? '#111827' : '#FFFFFF';
        if (mq && mq.addEventListener) {
            mq.addEventListener('change', function (e) {
                var s = null;
                try { s = localStorage.getItem('theme'); } catch (err) {}
                if (s) return; // user sudah memilih sendiri
                root.classList.toggle('dark', e.matches);
                root.style.colorScheme = e.matches ? 'dark' : 'light';
                if (meta) meta.content = e.matches ? '#111827' : '#FFFFFF';
                window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark: e.matches } }));
            });
        }
    })();
</script>
