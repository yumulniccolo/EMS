<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name'))</title>

        @vite(['resources/scss/app.scss', 'resources/js/app.js'])

        @stack('styles')
    </head>
    <body>
        {{--
            data-server-user: the signed-in user is printed by the server, so the script must not overwrite it with sample data.
            data-admin-ui: the HR pages' jQuery UI widgets apply inside it (resources/js/admin-widgets.js).
        --}}
        <div class="app-shell" data-app-shell data-server-user data-admin-ui>
            <x-app-header />

            <x-app-sidebar />

            <main class="app-main">
                <div class="app-content">
                    <x-flash-messages />

                    @yield('content')
                </div>
            </main>
        </div>

        <div class="toast-container position-fixed bottom-0 end-0 p-3">
            <div class="toast app-toast" role="status" aria-live="polite" aria-atomic="true" data-app-toast>
                <div class="d-flex">
                    <div class="toast-body" data-app-toast-message></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                        aria-label="Close"></button>
                </div>
            </div>
        </div>

        @stack('scripts')
    </body>
</html>
