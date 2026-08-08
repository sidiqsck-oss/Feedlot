@extends('layouts.app')
@section('judul', 'Kartu Stok')

@php use App\Support\Format; @endphp

@section('aksi')
    @if ($barang)
        <x-tombol-unduh rute="laporan.kartu.unduh" :pdf="true" />
    @endif
@endsection

@section('isi')

<form method="GET" class="kartu mb-4 flex flex-wrap items-end gap-3 p-3">
    <div class="min-w-56 flex-1">
        <label for="barang" class="label">Barang</label>
        <select id="barang" name="barang" required class="input">
            <option value="">— pilih barang —</option>
            @foreach ($daftarBarang as $b)
                <option value="{{ $b->id }}" @selected(request('barang') == $b->id)>
                    {{ $b->nama }} ({{ $b->kode }})
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="dari" class="label">Dari</label>
        <input id="dari" name="dari" type="date" value="{{ request('dari') }}" class="input">
    </div>
    <div>
        <label for="sampai" class="label">Sampai</label>
        <input id="sampai" name="sampai" type="date" value="{{ request('sampai') }}" class="input">
    </div>
    <button type="submit" class="tombol tombol-biasa">Tampilkan</button>
</form>

@if (! $barang)
    <div class="kartu p-8 text-center text-ink-mute">
        Pilih barang untuk melihat kartu stoknya.
    </div>
@else
    <div class="kartu mb-4 p-4">
        <p class="text-sm font-bold text-ink">{{ $barang->nama }}</p>
        <p class="mt-0.5 font-mono text-xs text-ink-mute">{{ $barang->kode }} · satuan {{ $barang->satuan }}</p>
    </div>

    <div class="kartu overflow-hidden">
        <div class="overflow-x-auto">
            <table class="tabel">
                <thead>
                    <tr>
                        <th>Tanggal</th><th>Jenis</th><th>Keterangan</th>
                        <th class="text-right">Masuk</th><th class="text-right">Keluar</th>
                        <th class="text-right">Saldo</th><th class="text-right">Harga</th><th>Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    @php $saldo = $saldoAwal; @endphp

                    @if (request('dari'))
                        <tr class="bg-ground">
                            <td colspan="5" class="font-semibold text-ink">Saldo awal</td>
                            <td class="angka font-bold text-ink">{{ Format::qty($saldoAwal) }}</td>
                            <td colspan="2"></td>
                        </tr>
                    @endif

                    @forelse ($baris as $p)
                        @php $saldo += (float) $p->qty; @endphp
                        <tr>
                            <td class="whitespace-nowrap">{{ $p->tanggal->format('d/m/Y') }}</td>
                            <td>
                                <span @class([
                                    'lencana',
                                    'bg-masuk-soft text-masuk' => $p->tipe === 'masuk',
                                    'bg-keluar-soft text-keluar' => $p->tipe === 'keluar',
                                    'bg-tanda-soft text-tanda' => in_array($p->tipe, ['opname', 'koreksi']),
                                ])>{{ $p->tipe }}</span>
                            </td>
                            <td class="text-xs">{{ $p->keterangan ?: '—' }}</td>
                            <td class="angka text-masuk">{{ (float) $p->qty > 0 ? Format::qty($p->qty) : '' }}</td>
                            <td class="angka text-keluar">{{ (float) $p->qty < 0 ? Format::qty(abs((float) $p->qty)) : '' }}</td>
                            <td class="angka font-semibold text-ink">{{ Format::qty($saldo) }}</td>
                            <td class="angka text-xs">{{ Format::rupiah($p->harga_satuan) }}</td>
                            <td class="text-xs">{{ $p->pembuat->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-8 text-center text-ink-mute">Belum ada pergerakan.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="bg-ground">
                        <td colspan="5" class="text-right font-bold text-ink">Saldo akhir</td>
                        <td class="angka text-base font-bold text-ink">{{ Format::qty($saldo) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <p class="mt-3 max-w-2xl text-xs text-ink-mute">
        Baris di kartu stok tidak pernah diubah atau dihapus. Kalau ada angka yang keliru,
        yang bertambah adalah baris koreksi — riwayat aslinya tetap terlihat di sini.
    </p>
@endif
@endsection
