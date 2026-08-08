@extends('layouts.app')
@section('judul', 'Master Shipment')

@section('aksi')
    <a href="{{ route('shipment.create') }}" class="tombol tombol-utama">Tambah Shipment</a>
@endsection

@section('isi')
<div class="kartu max-w-3xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="tabel">
            <thead>
                <tr><th>Kode</th><th>Tanggal Masuk</th><th>Keterangan</th><th class="text-right">Nota Keluar</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($daftar as $s)
                    <tr>
                        <td>
                            <span class="font-mono font-medium text-ink">{{ $s->kode }}</span>
                            @unless ($s->aktif)
                                <span class="lencana ml-1 bg-ground text-ink-mute">nonaktif</span>
                            @endunless
                        </td>
                        <td>{{ $s->tanggal_masuk?->format('d/m/Y') ?: '—' }}</td>
                        <td>{{ $s->keterangan ?: '—' }}</td>
                        <td class="angka">{{ $s->pengeluaran_count }}</td>
                        <td class="text-right">
                            <a href="{{ route('shipment.edit', $s) }}" class="text-xs font-semibold text-accent hover:underline">Ubah</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-ink-mute">Belum ada shipment.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $daftar->links() }}</div>
@endsection
