<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php($meta = $meta ?? [])
    @php($title = $meta['title'] ?? 'Хаана.mn — Монголын бизнес лавлах')
    @php($description = $meta['description'] ?? 'Монголын бизнесүүдийг нэг дороос. Ресторан, эмнэлэг, авто засвар, хууль зүйн үйлчилгээ — хаяг, цагийн хуваарь, үнэлгээ бүхий баталгаажсан лавлах.')
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    @if (!empty($meta['canonical']))
    <link rel="canonical" href="{{ $meta['canonical'] }}">
    @endif
    <meta property="og:site_name" content="Хаана.mn">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $meta['canonical'] ?? url()->current() }}">
    @if (!empty($meta['image']))
    <meta property="og:image" content="{{ $meta['image'] }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ $meta['image'] }}">
    @else
    <meta name="twitter:card" content="summary">
    @endif
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    @if (!empty($meta['jsonld']))
    <script type="application/ld+json">{!! json_encode($meta['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endif
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap&subset=cyrillic,latin" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    <div id="app"></div>
</body>
</html>
