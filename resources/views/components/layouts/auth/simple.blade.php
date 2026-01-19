<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-3 font-medium" wire:navigate>
                    @php
                        $appLogo = \App\Models\Setting::get('company_logo');
                        $appName = \App\Models\Setting::get('app_name', 'PKS Tracking System');
                    @endphp
                    
                    @if($appLogo && file_exists(public_path('storage/' . $appLogo)))
                        <img src="{{ asset('storage/' . $appLogo) }}" alt="{{ $appName }}" class="h-24 w-auto object-contain">
                    @else
                        <span class="flex h-24 w-24 items-center justify-center rounded-md">
                            <x-app-logo-icon class="size-24 fill-current text-black dark:text-white" />
                        </span>
                    @endif
                    
                    <span class="text-3xl font-bold text-neutral-900 dark:text-white">{{ $appName }}</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
