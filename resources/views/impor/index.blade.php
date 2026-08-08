@extends('layouts.app')
@section('judul', 'Impor Data')

@section('isi')

<div class="grid gap-5 lg:grid-cols-3">

    {{-- Form unggah --}}
    <form method="POST" action="{{ route('impor.store') }}" enctype="multipart/form-data"
          x-data="{ jenis: '{{ old('jenis', $jenis->keys()->first()) }}', perShipment: {{ Js::from($perShipment) }} }"
          class="kartu p-4 lg:col-span-2">
        @csrf

        <h2 class="text-sm font-bold text-ink">Unggah Berkas</h2>
        <p class="mt-1 text-sm text-ink-soft">
            Satu berkas untuk satu shipment. Setelah diunggah kamu akan melihat pratinjaunya dulu —
            belum ada data yang masuk sampai kamu menekan Proses.
        </p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="jenis" class="label">Jenis Data</label>
                <select id="jenis" name="jenis" x-model="jenis" required class="input">
                    @foreach ($jenis as $nilai => $nama)
                        <option value="{{ $nilai }}" @selected(old('jenis') === $nilai)>{{ $nama }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Hanya induksi dan reweight yang berkasnya milik satu rombongan.
                 Property, pembelian, dan penjualan mencakup banyak shipment
                 sekaligus dan membawa kolom Ship-nya sendiri. --}}
            <div>
                <label for="shipment_id" class="label">Shipment</label>
                <select id="shipment_id" name="shipment_id" class="input"
                        :required="perShipment[jenis]" :disabled="! perShipment[jenis]">
                    <option value="">— pilih shipment —</option>
                    @foreach ($shipment as $s)
                        <option value="{{ $s->id }}" @selected(old('shipment_id') == $s->id)>{{ $s->kode }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-ink-mute" x-show="! perShipment[jenis]" x-cloak>
                    Berkas jenis ini mencakup banyak shipment, jadi tidak perlu dipilih.
                </p>
            </div>

            <div class="sm:col-span-2">
                <label for="berkas" class="label">Berkas Excel atau CSV</label>
                <input id="berkas" name="berkas" type="file" accept=".xlsx,.xls,.csv" required
                       class="input file:mr-3 file:rounded file:border-0 file:bg-accent-soft file:px-3 file:py-1 file:text-sm file:font-semibold file:text-accent">
                <p class="mt-1 text-xs text-ink-mute">Maksimal 10 MB. Kalau lebih besar, pecah per shipment.</p>
            </div>
        </div>

        <div class="mt-5 border-t border-rule pt-4">
            <button type="submit" class="tombol tombol-utama">Unggah &amp; Periksa</button>
        </div>
    </form>

    {{-- Templat --}}
    <div class="space-y-4">
        <div class="kartu p-4">
            <h2 class="text-sm font-bold text-ink">Unduh Templat</h2>
            <p class="mt-1 text-sm text-ink-soft">
                Berkas kosong dengan judul kolom yang benar, plus lembar petunjuk isian.
            </p>

            <div class="mt-3 space-y-2">
                @foreach ($jenis as $nilai => $nama)
                    <a href="{{ route('impor.templat', $nilai) }}" class="tombol tombol-biasa w-full justify-center">
                        Templat {{ $nama }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="kartu p-4">
            <h2 class="text-sm font-bold text-ink">Urutannya penting</h2>
            <ol class="mt-2 list-decimal space-y-1 pl-4 text-sm text-ink-soft">
                <li><strong>Property</strong> dan <strong>Pembelian</strong> — data dasar, boleh kapan saja</li>
                <li><strong>Induksi</strong> — sumber identitas tiap ekor</li>
                <li><strong>Reweight</strong> dan <strong>Penjualan</strong> — keduanya menempel ke induksi</li>
            </ol>
            <p class="mt-2 text-sm text-ink-soft">
                Reweight dan penjualan menempel ke sapi yang sudah tercatat induksinya. Kalau
                induksinya belum diunggah, semua barisnya akan ditolak dengan pesan yang jelas.
            </p>
        </div>
    </div>
</div>

{{-- Riwayat --}}
<div class="kartu mt-5 overflow-hidden">
    <div class="border-b border-rule px-4 py-3">
        <h2 class="text-sm font-bold text-ink">Riwayat Unggahan</h2>
    </div>

    <div class="overflow-x-auto">
        <table class="tabel">
            <thead>
                <tr>
                    <th>Berkas</th><th>Jenis</th><th>Shipment</th><th>Status</th>
                    <th class="text-right">Baris</th><th class="text-right">Masuk</th>
                    <th>Waktu</th><th>Oleh</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftar as $b)
                    <tr>
                        <td>
                            <a href="{{ route('impor.show', $b) }}" class="font-medium text-accent hover:underline">
                                {{ $b->nama_berkas }}
                            </a>
                        </td>
                        <td class="text-xs">{{ $b->jenis }}</td>
                        <td class="font-mono text-xs">{{ $b->shipment?->kode ?: '—' }}</td>
                        <td>
                            <span @class([
                                'lencana',
                                'bg-masuk-soft text-masuk' => $b->status === 'selesai',
                                'bg-tanda-soft text-tanda' => in_array($b->status, ['pratinjau', 'dibaca', 'diproses']),
                                'bg-keluar-soft text-keluar' => in_array($b->status, ['gagal', 'dibatalkan']),
                            ])>{{ $b->status }}</span>
                        </td>
                        <td class="angka">{{ $b->jumlah_baris }}</td>
                        <td class="angka {{ $b->jumlah_baru > 0 ? 'font-semibold text-masuk' : 'text-ink-mute' }}">
                            {{ $b->status === 'selesai' ? $b->jumlah_baru : '—' }}
                        </td>
                        <td class="whitespace-nowrap text-xs">{{ $b->created_at->format('d/m/y H:i') }}</td>
                        <td class="text-xs">{{ $b->pengunggah->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-8 text-center text-ink-mute">Belum ada unggahan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $daftar->links() }}</div>

@endsection
