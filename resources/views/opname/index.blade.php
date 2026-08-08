@extends('layouts.app')
@section('judul', 'Stok Opname')

@php use App\Support\Format; @endphp

@section('aksi')
    <a href="{{ route('opname.create') }}" class="tombol tombol-utama">Opname Baru</a>
@endsection

@section('isi')

<p class="mb-4 max-w-2xl text-sm text-ink-soft">
    Opname dilakukan sebulan sekali dan dikunci per periode — satu bulan tidak bisa
    dibuat dua kali.
</p>

<div class="kartu overflow-hidden">
    <div class="overflow-x-auto">
        <table class="tabel">
            <thead>
                <tr>
                    <th>Nomor</th><th>Periode</th><th>Tanggal Hitung</th>
                    <th class="text-right">Barang</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftar as $o)
                    <tr>
                        <td>
                            <a href="{{ route('opname.show', $o) }}" class="font-mono text-xs font-semibold text-accent hover:underline">
                                {{ $o->nomor }}
                            </a>
                        </td>
                        <td class="text-ink">{{ Format::namaBulan($o->periode_bulan) }} {{ $o->periode_tahun }}</td>
                        <td class="whitespace-nowrap">{{ $o->tanggal->format('d/m/Y') }}</td>
                        <td class="angka">{{ $o->items_count }}</td>
                        <td>
                            <span @class([
                                'lencana',
                                'bg-masuk-soft text-masuk' => $o->sudahFinal(),
                                'bg-tanda-soft text-tanda' => ! $o->sudahFinal(),
                            ])>{{ $o->sudahFinal() ? 'Final' : 'Draft' }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-ink-mute">Belum ada opname.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $daftar->links() }}</div>
@endsection
