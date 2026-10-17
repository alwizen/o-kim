<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="O-KIM — sistem digital pendaftaran transportir, pemeriksaan kendaraan tangki, dan penerbitan Kartu Izin Masuk Fuel Terminal Tegal.">
    <title>O-KIM — Kartu Izin Masuk Fuel Terminal Tegal</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('img/fav.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#fbfaff] font-sans text-slate-800 antialiased selection:bg-lavender-600 selection:text-white">

    @include('landing.partials.scroll-progress')

    @include('landing.partials.navbar')

    @include('landing.partials.flash-error')

    @include('landing.partials.hero')

    @include('landing.partials.stats')

    @include('landing.partials.layanan')

    @include('landing.partials.persyaratan')

    @include('landing.partials.panduan')

    @include('landing.partials.faq')

    @include('landing.partials.cta')

    @include('landing.partials.footer')

</body>
</html>
