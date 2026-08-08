@extends('layouts.app')
@section('judul', 'Barang Keluar')

@php
    $barangJson = $barang->map(fn ($b) => [
        'id' => $b->id,
        'nama' => $b->nama,
        'kode' => $b->kode,
        'satuan' => $b->satuan,
        'sisa' => (float) ($saldo[$b->id] ?? 0),
    ])->values();

    $barisAwal = old('items', [['barang_id' => '', 'qty' => '']]);
@endphp

@section('isi')

<form
    method="POST"
    action="{{ route('pengeluaran.store') }}"
    x-data="notaKeluar({{ Js::from($barangJson) }}, {{ Js::from($barisAwal) }}, '{{ old('tujuan', 'dokter') }}')"
>
    @csrf

    <div class="kartu p-4">
        <h2 class="mb-3 text-sm font-bold text-ink">Keterangan Nota</h2>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="tanggal" class="label">Tanggal</label>
                <input id="tanggal" name="tanggal" type="date"
                       value="{{ old('tanggal', now()->toDateString()) }}" required class="input">
            </div>

            <div>
                <label for="tujuan" class="label">Tujuan</label>
                <select id="tujuan" name="tujuan" x-model="tujuan" required class="input">
                    <option value="dokter">Dokter</option>
                    <option value="induksi">Induksi</option>
                    <option value="reweight">Reweight</option>
                    <option value="lainnya">Lainnya</option>
                </select>
            </div>

            <div>
                <label for="petugas_id" class="label">Diambil Oleh</label>
                <select id="petugas_id" name="petugas_id" class="input">
                    <option value="">— pilih —</option>
                    @foreach ($petugas as $p)
                        <option value="{{ $p->id }}" @selected(old('petugas_id') == $p->id)>
                            {{ $p->nama }} ({{ $p->peran }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="shipment_id" class="label">
                    Shipment
                    <span x-show="perluShipment" class="text-keluar">*</span>
                </label>
                <select id="shipment_id" name="shipment_id" class="input" :required="perluShipment">
                    <option value="">— pilih —</option>
                    @foreach ($shipment as $s)
                        <option value="{{ $s->id }}" @selected(old('shipment_id') == $s->id)>{{ $s->kode }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs" :class="perluShipment ? 'text-keluar' : 'text-ink-mute'"
                   x-text="perluShipment ? 'Wajib diisi untuk induksi dan reweight.' : 'Opsional untuk tujuan ini.'"></p>
            </div>
        </div>

        <div class="mt-4">
            <label for="catatan" class="label">Catatan</label>
            <textarea id="catatan" name="catatan" rows="2" class="input">{{ old('catatan') }}</textarea>
        </div>
    </div>

    <div class="kartu mt-4 overflow-hidden">
        <div class="flex items-center justify-between border-b border-rule px-4 py-3">
            <h2 class="text-sm font-bold text-ink">Daftar Barang</h2>
            <button type="button" @click="tambahBaris" class="tombol tombol-biasa">+ Tambah Baris</button>
        </div>

        <div class="overflow-x-auto">
            <table class="tabel">
                <thead>
                    <tr>
                        <th class="w-[55%]">Barang</th>
                        <th class="w-36 text-right">Jumlah</th>
                        <th class="w-36 text-right">Sisa Stok</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(baris, i) in items" :key="i">
                        <tr>
                            <td>
                                <select :name="`items[${i}][barang_id]`" x-model="baris.barang_id" required class="input">
                                    <option value="">— pilih barang —</option>
                                    <template x-for="b in daftarBarang" :key="b.id">
                                        <option :value="b.id" x-text="`${b.nama} (${b.kode})`"></option>
                                    </template>
                                </select>
                            </td>

                            <td>
                                <div class="flex items-center gap-1.5">
                                    <input
                                        type="number" step="0.001" min="0.001"
                                        :name="`items[${i}][qty]`"
                                        x-model.number="baris.qty"
                                        required
                                        class="input angka"
                                        :class="melebihi(baris) ? 'border-keluar' : ''"
                                    >
                                    <span class="w-12 shrink-0 text-xs text-ink-mute" x-text="satuan(baris)"></span>
                                </div>
                            </td>

                            <td class="angka">
                                <span
                                    x-show="baris.barang_id"
                                    class="font-semibold"
                                    :class="melebihi(baris) ? 'text-keluar' : 'text-ink'"
                                    x-text="formatQty(sisa(baris)) + ' ' + satuan(baris)"
                                ></span>
                                <span x-show="!baris.barang_id" class="text-ink-mute">—</span>
                            </td>

                            <td class="text-right">
                                <button
                                    type="button" @click="hapusBaris(i)" x-show="items.length > 1"
                                    class="text-lg leading-none text-keluar" aria-label="Hapus baris"
                                >&times;</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div x-show="adaYangMelebihi" x-cloak
             class="border-t border-keluar bg-keluar-soft px-4 py-3 text-sm text-keluar">
            Ada barang yang jumlahnya melebihi sisa stok. Nota ini akan ditolak saat disimpan.
        </div>
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
        <button type="submit" class="tombol tombol-utama">Simpan Nota</button>
        <a href="{{ route('pengeluaran.index') }}" class="tombol tombol-biasa">Batal</a>
    </div>

    <p class="mt-3 max-w-2xl text-xs text-ink-mute">
        Harga tidak diisi di sini — sistem yang menghitungnya dari lot pembelian terlama
        yang masih bersisa. Satu baris bisa mengambil dari beberapa lot dengan harga berbeda.
    </p>
</form>

<script>
function notaKeluar(daftarBarang, barisAwal, tujuanAwal) {
    return {
        daftarBarang,
        tujuan: tujuanAwal,
        items: barisAwal.length ? barisAwal : [{ barang_id: '', qty: '' }],

        get perluShipment() {
            return this.tujuan === 'induksi' || this.tujuan === 'reweight';
        },

        tambahBaris() {
            this.items.push({ barang_id: '', qty: '' });
        },

        hapusBaris(i) {
            this.items.splice(i, 1);
        },

        cari(baris) {
            return this.daftarBarang.find(b => b.id == baris.barang_id);
        },

        satuan(baris) {
            return this.cari(baris)?.satuan ?? '';
        },

        sisa(baris) {
            return this.cari(baris)?.sisa ?? 0;
        },

        // Peringatan di layar saja. Penolakan sebenarnya tetap dilakukan server,
        // karena stok bisa berubah antara halaman dibuka dan nota disimpan.
        melebihi(baris) {
            return baris.barang_id !== '' && (Number(baris.qty) || 0) > this.sisa(baris);
        },

        get adaYangMelebihi() {
            return this.items.some(baris => this.melebihi(baris));
        },

        formatQty(nilai) {
            return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 }).format(nilai);
        },
    };
}
</script>

@endsection
