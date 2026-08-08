@extends('layouts.app')
@section('judul', 'Master Supplier')

@section('aksi')
    <a href="{{ route('supplier.create') }}" class="tombol tombol-utama">Tambah Supplier</a>
@endsection

@section('isi')
<div class="kartu overflow-hidden">
    <div class="overflow-x-auto">
        <table class="tabel">
            <thead>
                <tr>
                    <th>Kode</th><th>Nama</th><th>Kontak</th><th>Telepon</th>
                    <th class="text-right">Nota</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($daftar as $s)
                    <tr>
                        <td class="font-mono text-xs">{{ $s->kode }}</td>
                        <td>
                            <span class="font-medium text-ink">{{ $s->nama }}</span>
                            @unless ($s->aktif)
                                <span class="lencana ml-1 bg-ground text-ink-mute">nonaktif</span>
                            @endunless
                        </td>
                        <td>{{ $s->kontak ?: '—' }}</td>
                        <td>{{ $s->telepon ?: '—' }}</td>
                        <td class="angka">{{ $s->penerimaan_count }}</td>
                        <td class="text-right">
                            <a href="{{ route('supplier.edit', $s) }}" class="text-xs font-semibold text-accent hover:underline">Ubah</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-ink-mute">Belum ada supplier.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $daftar->links() }}</div>
@endsection
