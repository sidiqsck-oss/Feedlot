@extends('layouts.app')
@section('judul', 'PO ' . $po->nomor)

@php use App\Support\Format; @endphp

@section('aksi')
    @if ($po->bolehDirevisi())
        <a href="{{ route('purchase-order.edit', $po) }}" class="tombol tombol-biasa">Revisi</a>
    @endif
@endsection

@section('isi')

<div class="kartu p-4">
    <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <dt class="label">Nomor</dt>
            <dd class="font-mono font-semibold text-ink">{{ $po->nomor }}</dd>
        </div>
        <div>
            <dt class="label">Tanggal</dt>
            <dd class="text-ink">{{ $po->tanggal->translatedFormat('d F Y') }}</dd>
        </div>
        <div>
            <dt class="label">Supplier</dt>
            <dd class="text-ink">{{ $po->supplier->nama }}</dd>
        </div>
        <div>
            <dt class="label">Status</dt>
            <dd>
                <span @class([
                    'lencana',
                    'bg-masuk-soft text-masuk' => $po->status === 'selesai',
                    'bg-tanda-soft text-tanda' => in_array($po->status, ['sebagian', 'ditutup']),
                    'bg-keluar-soft text-keluar' => $po->status === 'batal',
                    'bg-ground text-ink-soft' => in_array($po->status, ['draft', 'terbuka']),
                ])>{{ $po->status }}</span>
            </dd>
        </div>
    </dl>

    @if ($po->status === 'ditutup')
        <div class="mt-4 rounded-md border border-tanda bg-tanda-soft px-3 py-2 text-sm text-tanda">
            <strong>Ditutup</strong> {{ $po->ditutup_pada->format('d/m/Y') }} —
            {{ $po->alasan_penutupan }}
        </div>
    @endif
</div>

<div class="kartu mt-4 overflow-hidden">
    <div class="border-b border-rule px-4 py-3">
        <h2 class="text-sm font-bold text-ink">Barang Dipesan</h2>
    </div>

    <div class="overflow-x-auto">
        <table class="tabel">
            <thead>
                <tr>
                    <th>Barang</th><th class="text-right">Dipesan</th>
                    <th class="text-right">Diterima</th><th class="text-right">Sisa</th>
                    <th class="text-right">Harga</th><th class="text-right">Nilai</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($po->items as $item)
                    <tr>
                        <td>
                            <span class="font-medium text-ink">{{ $item->barang->nama }}</span>
                            <span class="block font-mono text-xs text-ink-mute">{{ $item->barang->kode }}</span>
                        </td>
                        <td class="angka">{{ Format::qtySatuan($item->qty, $item->barang->satuan) }}</td>
                        <td class="angka text-masuk">{{ Format::qty($item->qty_diterima) }}</td>
                        <td class="angka {{ $item->sisa() > 0 ? 'font-semibold text-tanda' : 'text-ink-mute' }}">
                            {{ Format::qty($item->sisa()) }}
                        </td>
                        <td class="angka">{{ Format::rupiah($item->harga_satuan) }}</td>
                        <td class="angka font-semibold text-ink">
                            {{ Format::rupiah((float) $item->qty * (float) $item->harga_satuan) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="bg-ground">
                    <td colspan="5" class="text-right font-bold text-ink">Total</td>
                    <td class="angka text-base font-bold text-ink">{{ Format::rupiah($po->totalNilai()) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">

    {{-- Nota masuk yang memenuhi PO ini --}}
    <div class="kartu overflow-hidden">
        <div class="border-b border-rule px-4 py-3">
            <h2 class="text-sm font-bold text-ink">Barang Masuk</h2>
        </div>
        <ul class="divide-y divide-rule text-sm">
            @forelse ($po->penerimaan as $n)
                <li class="flex items-center justify-between gap-2 px-4 py-2">
                    <a href="{{ route('penerimaan.show', $n) }}" class="font-mono text-xs text-accent hover:underline">
                        {{ $n->nomor }}
                    </a>
                    <span class="text-xs text-ink-mute">{{ $n->tanggal->format('d/m/Y') }}</span>
                </li>
            @empty
                <li class="px-4 py-4 text-center text-ink-mute">Belum ada barang yang datang.</li>
            @endforelse
        </ul>
    </div>

    {{-- Riwayat perubahan --}}
    <div class="kartu overflow-hidden">
        <div class="border-b border-rule px-4 py-3">
            <h2 class="text-sm font-bold text-ink">Riwayat</h2>
            <p class="mt-0.5 text-xs text-ink-mute">PO adalah komitmen ke supplier, jadi perubahannya dicatat.</p>
        </div>
        <ul class="divide-y divide-rule text-sm">
            @foreach ($po->riwayat as $r)
                <li class="px-4 py-2">
                    <div class="flex items-center justify-between gap-2">
                        <span class="lencana bg-ground text-ink-soft">{{ $r->aksi }}</span>
                        <span class="text-xs text-ink-mute">
                            {{ $r->created_at->format('d/m/Y H:i') }} · {{ $r->pelaku->name }}
                        </span>
                    </div>
                    @if ($r->alasan)
                        <p class="mt-1 text-xs text-ink-soft">{{ $r->alasan }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</div>

{{-- Tutup / batal --}}
@if ($po->bolehDitutup())
    <div class="kartu mt-4 p-4">
        <h2 class="text-sm font-bold text-ink">Sudahi PO Ini</h2>

        <div class="mt-3 grid gap-4 lg:grid-cols-2">
            <form method="POST" action="{{ route('purchase-order.tutup', $po) }}"
                  onsubmit="return confirm('Tutup PO ini?')">
                @csrf
                <p class="text-sm font-semibold text-ink">Tutup PO</p>
                <p class="mt-1 text-xs text-ink-soft">
                    Untuk barang yang kosong atau datang kurang, tapi diputuskan sudahi saja.
                    Penerimaan yang sudah tercatat tetap sah.
                </p>
                <input name="alasan" placeholder="Alasan, mis. sisa kosong di supplier" required class="input mt-2">
                <button type="submit" class="tombol tombol-biasa mt-2">Tutup PO</button>
            </form>

            <form method="POST" action="{{ route('purchase-order.batal', $po) }}"
                  onsubmit="return confirm('Batalkan PO ini?')">
                @csrf
                <p class="text-sm font-semibold text-ink">Batalkan PO</p>
                <p class="mt-1 text-xs text-ink-soft">
                    @if ($po->bolehDibatalkan())
                        Hanya untuk PO yang belum ada barangnya sama sekali.
                    @else
                        <span class="text-keluar">
                            Tidak bisa dibatalkan — sudah ada barang yang masuk. Gunakan Tutup PO.
                        </span>
                    @endif
                </p>
                <input name="alasan" placeholder="Alasan pembatalan" required
                       class="input mt-2" @disabled(! $po->bolehDibatalkan())>
                <button type="submit" class="tombol tombol-bahaya mt-2" @disabled(! $po->bolehDibatalkan())>
                    Batalkan PO
                </button>
            </form>
        </div>
    </div>
@endif

@endsection
