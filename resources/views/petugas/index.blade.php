@extends('layouts.app')
@section('judul', 'Master Petugas')

@section('aksi')
    <a href="{{ route('petugas.create') }}" class="tombol tombol-utama">Tambah Petugas</a>
@endsection

@section('isi')
<p class="mb-4 max-w-2xl text-sm text-ink-soft">
    Orang yang mengambil barang dari gudang. Nama yang sama juga dipakai untuk
    mencocokkan penanggung jawab di rekam medis dokter.
</p>

<div class="kartu max-w-3xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="tabel">
            <thead>
                <tr><th>Nama</th><th>Peran</th><th class="text-right">Pengambilan</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($daftar as $p)
                    <tr>
                        <td>
                            <span class="font-medium text-ink">{{ $p->nama }}</span>
                            @unless ($p->aktif)
                                <span class="lencana ml-1 bg-ground text-ink-mute">nonaktif</span>
                            @endunless
                        </td>
                        <td>
                            <span @class([
                                'lencana',
                                'bg-accent-soft text-accent' => $p->peran === 'dokter',
                                'bg-ground text-ink-soft' => $p->peran !== 'dokter',
                            ])>{{ $p->peran }}</span>
                        </td>
                        <td class="angka">{{ $p->pengeluaran_count }}</td>
                        <td class="text-right">
                            <a href="{{ route('petugas.edit', $p) }}" class="text-xs font-semibold text-accent hover:underline">Ubah</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-ink-mute">Belum ada petugas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
