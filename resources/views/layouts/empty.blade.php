<!DOCTYPE html>
<html  class="scroll-smooth" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>
        <link rel="icon" href="{{ asset('icon.png') }}" type="image/png">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body>
        {{ $slot }}
        <script src="https://cdn.jsdelivr.net/npm/flowbite@4.0.1/dist/flowbite.min.js"></script>
        <script>
            // Event ini dipicu setiap kali Livewire selesai navigasi (wire:navigate)
            document.addEventListener('livewire:navigated', () => { 
                initFlowbite();
            });
        </script>
        @livewireScripts
    </body>
</html>
