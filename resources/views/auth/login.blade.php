<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — SCK Feedlot</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center px-4">

<div class="w-full max-w-sm">
    <div class="mb-6 text-center">
        <h1 class="text-xl font-bold text-ink">Sumber Cipta Kencana</h1>
        <p class="mt-1 text-sm text-ink-mute">Sistem OVK &amp; Perbekalan Kesehatan</p>
    </div>

    <div class="kartu p-6">
        @if ($errors->any())
            <div class="mb-4 rounded-md border border-keluar bg-keluar-soft px-3 py-2 text-sm text-keluar">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.proses') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="label">Email</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                    class="input"
                >
            </div>

            <div>
                <label for="password" class="label">Password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="input"
                >
            </div>

            <label class="flex items-center gap-2 text-sm text-ink-soft">
                <input type="checkbox" name="ingat" value="1" class="rounded border-rule">
                Ingat saya di perangkat ini
            </label>

            <button type="submit" class="tombol tombol-utama w-full justify-center">Masuk</button>
        </form>
    </div>

    <p class="mt-4 text-center text-xs text-ink-mute">
        Belum punya akun? Hubungi administrator.
    </p>
</div>

</body>
</html>
