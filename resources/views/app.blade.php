<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Хаана.mn — Монголын бизнес лавлах</title>
    <meta name="description" content="Монгол дахь 42,000 бизнесийг нэг дороос. Ресторан, эмнэлэг, авто засвар, хууль зүйн үйлчилгээ — хаяг, цагийн хуваарь, үнэлгээ бүхий баталгаажсан лавлах.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap&subset=cyrillic,latin" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    <div id="app"></div>
</body>
</html>
