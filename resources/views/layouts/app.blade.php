<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('judul', 'OVK') — SCK Feedlot</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">

<div x-data="{ menuTerbuka: false }" class="flex min-h-screen">

    {{-- Sidebar. Di layar kecil disembunyikan dan dibuka lewat tombol,
         karena aplikasi ini sering dibuka dari HP di lapangan. --}}
    <aside
        class="fixed inset-y-0 left-0 z-30 w-60 shrink-0 overflow-y-auto border-r border-rule bg-surface
               transition-transform lg:static lg:translate-x-0"
        :class="menuTerbuka ? 'translate-x-0' : '-translate-x-full'"
    >
        <div class="border-b border-rule px-4 py-4">
            <p class="text-sm font-bold leading-tight text-ink">Sumber Cipta Kencana</p>
            <p class="mt-0.5 text-xs text-ink-mute">OVK &amp; Perbekalan Kesehatan</p>
        </div>

        <nav class="space-y-5 px-3 py-4 text-sm">
            <x-nav-grup judul="OVK">
                <x-nav-tautan :href="route('dashboard')" :aktif="request()->routeIs('dashboard')">Dashboard OVK</x-nav-tautan>
            </x-nav-grup>

            <x-nav-grup judul="Transaksi OVK">
                <x-nav-tautan :href="route('penerimaan.index')" :aktif="request()->routeIs('penerimaan.*')">Barang Masuk</x-nav-tautan>
                <x-nav-tautan :href="route('pengeluaran.index')" :aktif="request()->routeIs('pengeluaran.*')">Barang Keluar</x-nav-tautan>
                <x-nav-tautan :href="route('purchase-order.index')" :aktif="request()->routeIs('purchase-order.*')">Purchase Order</x-nav-tautan>
                <x-nav-tautan :href="route('opname.index')" :aktif="request()->routeIs('opname.*')">Stok Opname</x-nav-tautan>
            </x-nav-grup>

            <x-nav-grup judul="Laporan OVK">
                <x-nav-tautan :href="route('laporan.stok')" :aktif="request()->routeIs('laporan.stok')">Stok &amp; Nilai</x-nav-tautan>
                <x-nav-tautan :href="route('laporan.mutasi')" :aktif="request()->routeIs('laporan.mutasi')">Masuk &amp; Keluar</x-nav-tautan>
                <x-nav-tautan :href="route('laporan.kartu')" :aktif="request()->routeIs('laporan.kartu')">Kartu Stok</x-nav-tautan>
            </x-nav-grup>

            <x-nav-grup judul="CPL">
                <x-nav-tautan :href="route('cpl.dashboard')" :aktif="request()->routeIs('cpl.dashboard')">Dashboard CPL</x-nav-tautan>
                <x-nav-tautan :href="route('cpl.laporan')" :aktif="request()->routeIs('cpl.laporan')">Laporan CPL</x-nav-tautan>
                <x-nav-tautan :href="route('cpl.closing')" :aktif="request()->routeIs('cpl.closing')">Closing CPL</x-nav-tautan>
                <x-nav-tautan :href="route('cpl.claim.index')" :aktif="request()->routeIs('cpl.claim.*')">Claim Importir</x-nav-tautan>
                <x-nav-tautan :href="route('impor.index')" :aktif="request()->routeIs('impor.*')">Impor Data</x-nav-tautan>
            </x-nav-grup>

            <x-nav-grup judul="Master OVK">
                <x-nav-tautan :href="route('barang.index')" :aktif="request()->routeIs('barang.*')">Barang</x-nav-tautan>
                <x-nav-tautan :href="route('supplier.index')" :aktif="request()->routeIs('supplier.*')">Supplier</x-nav-tautan>
                <x-nav-tautan :href="route('petugas.index')" :aktif="request()->routeIs('petugas.*')">Petugas</x-nav-tautan>
                <x-nav-tautan :href="route('shipment.index')" :aktif="request()->routeIs('shipment.*')">Shipment</x-nav-tautan>
            </x-nav-grup>
        </nav>

        <div class="border-t border-rule px-3 py-3">
            <p class="px-2 text-xs text-ink-mute">Masuk sebagai</p>
            <p class="px-2 text-sm font-semibold text-ink">{{ auth()->user()->name }}</p>
            <form method="POST" action="{{ route('logout') }}" class="mt-2">
                @csrf
                <button type="submit" class="tombol tombol-biasa w-full justify-center">Keluar</button>
            </form>
        </div>
    </aside>

    {{-- Lapisan gelap saat menu terbuka di layar kecil. --}}
    <div
        x-show="menuTerbuka"
        x-cloak
        @click="menuTerbuka = false"
        class="fixed inset-0 z-20 bg-black/40 lg:hidden"
    ></div>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="flex items-center gap-3 border-b border-rule bg-surface px-4 py-3 lg:px-6">
            <button
                @click="menuTerbuka = !menuTerbuka"
                class="tombol tombol-biasa lg:hidden"
                aria-label="Buka menu"
            >☰</button>
            <h1 class="text-base font-bold text-ink">@yield('judul', 'OVK')</h1>
            <div class="ml-auto">@yield('aksi')</div>
        </header>

        <main class="min-w-0 flex-1 px-4 py-5 lg:px-6">
            @if (session('sukses'))
                <div class="mb-4 rounded-md border border-masuk bg-masuk-soft px-4 py-3 text-sm text-masuk">
                    {{ session('sukses') }}
                </div>
            @endif

            @if (session('gagal'))
                <div class="mb-4 rounded-md border border-keluar bg-keluar-soft px-4 py-3 text-sm text-keluar">
                    {{ session('gagal') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-md border border-keluar bg-keluar-soft px-4 py-3 text-sm text-keluar">
                    <p class="font-semibold">Periksa lagi isiannya:</p>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach ($errors->all() as $pesan)
                            <li>{{ $pesan }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('isi')
        </main>
    </div>
</div>

<style>[x-cloak] { display: none !important; }</style>
</body>
</html>
