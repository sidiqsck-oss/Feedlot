@props(['rute', 'pdf' => false])

{{--
    Tombol unduhan laporan.

    Query yang sedang aktif ikut dibawa apa adanya, jadi berkas yang terunduh
    persis sama dengan yang sedang dilihat di layar — bukan seluruh data tanpa
    penyaring.

    CSV ditaruh paling depan dan tanpa batas baris; Excel dan PDF menyusun
    seluruh isi di memori, jadi keduanya punya batas.
--}}
<div class="flex flex-wrap gap-2">
    <a href="{{ route($rute, request()->query()) }}" class="tombol tombol-biasa">Unduh CSV</a>

    <a href="{{ route($rute, request()->query() + ['format' => 'excel']) }}" class="tombol tombol-biasa">Excel</a>

    @if ($pdf)
        <a href="{{ route($rute, request()->query() + ['format' => 'pdf']) }}"
           target="_blank" class="tombol tombol-biasa">Cetak PDF</a>
    @endif
</div>
