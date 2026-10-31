@php
    // Ikon tab browser: huruf pertama nama aplikasi (APP_NAME) di kotak warna primer.
    $letter = e(\Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(config('app.name'), 0, 1)));
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
        .'<rect width="64" height="64" rx="14" fill="#17607A"/>'
        .'<text x="32" y="45" text-anchor="middle" font-family="Plus Jakarta Sans,Segoe UI,Arial,sans-serif" font-size="38" font-weight="700" fill="#fff">'.$letter.'</text>'
        .'</svg>';
@endphp
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,{{ rawurlencode($svg) }}">
