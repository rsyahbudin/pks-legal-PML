<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? \App\Models\Setting::get('app_name', 'PKS Tracking System') }}</title>

@php
    $logoPath = \App\Models\Setting::get('company_logo');
    $hasFavicon = $logoPath && file_exists(storage_path('app/public/' . $logoPath));
@endphp

@if($hasFavicon)
    <link rel="icon" href="{{ asset('storage/' . $logoPath) }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('storage/' . $logoPath) }}">
@else
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
@endif

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
