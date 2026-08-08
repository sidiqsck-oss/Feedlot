@extends('layouts.app')
@section('judul', 'Purchase Order')

@php use App\Support\Format; @endphp

@section('aksi')
    <a href="{{ route('purchase-order.create') }}" class="tombol tombol-utama">PO Baru</a>
@endsection

@section('isi')

<form method="GET" class="kartu mb-4 flex flex-wrap items-end gap-3 p-3">
    <div class="min-w-40">
        <label for="status" class="label">Status</label>
        <select id="status" name="status" class="input">
            <option value="">Semua status</option>
            @foreach (['draft', 'terbuka', 'sebagian', 'selesai', 'ditutup', 'batal'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
    </div>
    <div class="min-w-44">
        <label for="supplier" class="label">Supplier</label>
        <select id="supplier" name="supplier" class="input">
            <option value="">Semua supplier</option>
            @foreach ($supplier as $s)
                <option value="{{ $s->id }}" @selected(request('supplier') == $s->id)>{{ $s->nama }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="tombol tombol-biasa">Terapkan</button>
</form>

<div class="kartu overflow-hidden">
    <div class="overflow-x-auto">
        <table class="tabel">
            <thead>
                <tr>
                    <th>Nomor</th><th>Tanggal</th><th>Supplier</th>
                    <th>Status</th><th class="text-right">Nota Masuk</th><th class="text-right">Nilai</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftar as $p)
                    <tr>
                        <td>
                            <a href="{{ route('purchase-order.show', $p) }}" class="font-mono text-xs font-semibold text-accent hover:underline">
                                {{ $p->nomor }}
                            </a>
                        </td>
                        <td class="whitespace-nowrap">{{ $p->tanggal->format('d/m/Y') }}</td>
                        <td class="text-ink">{{ $p->supplier->nama }}</td>
                        <td>
                            <span @class([
                                'lencana',
                                'bg-masuk-soft text-masuk' => $p->status === 'selesai',
                                'bg-tanda-soft text-tanda' => in_array($p->status, ['sebagian', 'ditutup']),
                                'bg-keluar-soft text-keluar' => $p->status === 'batal',
                                'bg-ground text-ink-soft' => in_array($p->status, ['draft', 'terbuka']),
                            ])>{{ $p->status }}</span>
                        </td>
                        <td class="angka">{{ $p->penerimaan_count }}</td>
                        <td class="angka font-semibold text-ink">{{ Format::rupiah($p->totalNilai()) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-ink-mute">Belum ada purchase order.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $daftar->links() }}</div>
@endsection
