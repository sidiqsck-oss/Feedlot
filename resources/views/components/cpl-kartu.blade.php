@props([
    'judul',
    'nilai',
    'catatan' => null,
    'delta' => null,
    'tebal' => false,
    'bahaya' => false,
])

{{--
    Kartu ringkasan dashboard CPL.

    Delta hanya muncul kalau ada pembandingnya. Menampilkan "▲ 0%" saat tidak
    ada periode sebelumnya justru menyesatkan.
--}}
<div @class(['kartu p-4', 'ring-1 ring-accent' => $tebal])>
    <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-ink-mute">{{ $judul }}</p>

    <p @class([
        'angka mt-1 font-bold',
        'text-2xl' => ! $tebal,
        'text-3xl' => $tebal,
        'text-keluar' => $bahaya,
        'text-ink' => ! $bahaya,
    ])>{{ $nilai }}</p>

    <div class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs">
        @if ($catatan)
            <span class="text-ink-mute">{{ $catatan }}</span>
        @endif

        @if ($delta)
            <span @class([
                'font-semibold',
                'text-masuk' => $delta['arah'] === 'naik',
                'text-keluar' => $delta['arah'] === 'turun',
                'text-ink-mute' => $delta['arah'] === 'tetap',
            ])>{{ $delta['teks'] }}</span>
        @endif
    </div>
</div>
