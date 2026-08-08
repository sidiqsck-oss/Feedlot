@extends('layouts.app')
@section('judul', $po->exists ? 'Revisi PO ' . $po->nomor : 'Purchase Order Baru')

@php
    $barangJson = $barang->map(fn ($b) => [
        'id' => $b->id,
        'nama' => $b->nama,
        'kode' => $b->kode,
        'satuan' => $b->satuan,
    ])->values();

    $barisAwal = old('items', $po->exists
        ? $po->items->map(fn ($i) => [
            'barang_id' => (string) $i->barang_id,
            'qty' => (float) $i->qty,
            'harga_satuan' => (float) $i->harga_satuan,
            'diterima' => (float) $i->qty_diterima,
        ])->values()->all()
        : [['barang_id' => '', 'qty' => '', 'harga_satuan' => '', 'diterima' => 0]]);
@endphp

@section('isi')

<form
    method="POST"
    action="{{ $po->exists ? route('purchase-order.update', $po) : route('purchase-order.store') }}"
    x-data="formPo({{ Js::from($barangJson) }}, {{ Js::from($barisAwal) }})"
>
    @csrf
    @if ($po->exists) @method('PUT') @endif

    <div class="kartu p-4">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="tanggal" class="label">Tanggal</label>
                <input id="tanggal" name="tanggal" type="date"
                       value="{{ old('tanggal', $po->tanggal?->format('Y-m-d') ?? now()->toDateString()) }}"
                       required class="input">
            </div>

            <div>
                <label for="supplier_id" class="label">Supplier</label>
                <select id="supplier_id" name="supplier_id" required class="input">
                    <option value="">— pilih —</option>
                    @foreach ($supplier as $s)
                        <option value="{{ $s->id }}" @selected(old('supplier_id', $po->supplier_id) == $s->id)>
                            {{ $s->nama }}
                        </option>
                    @endforeach
                </select>
            </div>

            @unless ($po->exists)
                <div>
                    <label for="status" class="label">Status Awal</label>
                    <select id="status" name="status" class="input">
                        <option value="terbuka" @selected(old('status') === 'terbuka')>Terbuka — sudah dikirim ke supplier</option>
                        <option value="draft" @selected(old('status') === 'draft')>Draft — masih disusun</option>
                    </select>
                </div>
            @endunless

            <div class="{{ $po->exists ? 'sm:col-span-2 lg:col-span-2' : '' }}">
                <label for="catatan" class="label">Catatan</label>
                <input id="catatan" name="catatan" value="{{ old('catatan', $po->catatan) }}" class="input">
            </div>
        </div>

        @if ($po->exists)
            <div class="mt-4 border-t border-rule pt-4">
                <label for="alasan" class="label">Alasan Revisi</label>
                <input id="alasan" name="alasan" value="{{ old('alasan') }}"
                       placeholder="mis. supplier cuma sanggup 6 botol" required class="input">
                <p class="mt-1 text-xs text-ink-mute">
                    Dicatat di riwayat PO beserta isi perubahannya.
                </p>
            </div>
        @endif
    </div>

    <div class="kartu mt-4 overflow-hidden">
        <div class="flex items-center justify-between border-b border-rule px-4 py-3">
            <h2 class="text-sm font-bold text-ink">Barang Dipesan</h2>
            <button type="button" @click="tambahBaris" class="tombol tombol-biasa">+ Tambah Baris</button>
        </div>

        <div class="overflow-x-auto">
            <table class="tabel">
                <thead>
                    <tr>
                        <th class="w-[40%]">Barang</th>
                        <th class="w-32 text-right">Jumlah</th>
                        @if ($po->exists)<th class="w-28 text-right">Diterima</th>@endif
                        <th class="w-40 text-right">Harga Satuan</th>
                        <th class="w-40 text-right">Nilai</th>
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
                                    <input type="number" step="0.001" :min="baris.diterima || 0.001"
                                           :name="`items[${i}][qty]`" x-model.number="baris.qty" required
                                           class="input angka" :class="dibawahDiterima(baris) ? 'border-keluar' : ''">
                                    <span class="w-12 shrink-0 text-xs text-ink-mute" x-text="satuan(baris)"></span>
                                </div>
                            </td>

                            @if ($po->exists)
                                <td class="angka text-masuk" x-text="formatQty(baris.diterima || 0)"></td>
                            @endif

                            <td>
                                <input type="number" step="1" min="0"
                                       :name="`items[${i}][harga_satuan]`" x-model.number="baris.harga_satuan"
                                       required class="input angka">
                            </td>

                            <td class="angka font-semibold text-ink" x-text="rupiah(nilai(baris))"></td>

                            <td class="text-right">
                                <button type="button" @click="hapusBaris(i)"
                                        x-show="items.length > 1 && !(baris.diterima > 0)"
                                        class="text-lg leading-none text-keluar" aria-label="Hapus baris">&times;</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
                <tfoot>
                    <tr class="bg-ground">
                        <td colspan="{{ $po->exists ? 4 : 3 }}" class="text-right font-bold text-ink">Total</td>
                        <td class="angka text-base font-bold text-ink" x-text="rupiah(total)"></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div x-show="adaYangDibawahDiterima" x-cloak
             class="border-t border-keluar bg-keluar-soft px-4 py-3 text-sm text-keluar">
            Ada jumlah yang diturunkan di bawah yang sudah diterima. Barangnya sudah jadi stok,
            jadi PO tidak boleh menyatakan lebih sedikit dari itu.
        </div>
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
        <button type="submit" class="tombol tombol-utama">{{ $po->exists ? 'Simpan Revisi' : 'Buat PO' }}</button>
        <a href="{{ $po->exists ? route('purchase-order.show', $po) : route('purchase-order.index') }}"
           class="tombol tombol-biasa">Batal</a>
    </div>
</form>

<script>
function formPo(daftarBarang, barisAwal) {
    return {
        daftarBarang,
        items: barisAwal.length ? barisAwal : [{ barang_id: '', qty: '', harga_satuan: '', diterima: 0 }],

        tambahBaris() {
            this.items.push({ barang_id: '', qty: '', harga_satuan: '', diterima: 0 });
        },

        hapusBaris(i) {
            // Baris yang barangnya sudah sebagian datang tidak boleh dibuang —
            // server juga menolaknya, ini cuma supaya tombolnya tidak muncul.
            if (this.items[i].diterima > 0) return;
            this.items.splice(i, 1);
        },

        satuan(baris) {
            return this.daftarBarang.find(b => b.id == baris.barang_id)?.satuan ?? '';
        },

        dibawahDiterima(baris) {
            return (baris.diterima || 0) > 0 && (Number(baris.qty) || 0) < baris.diterima;
        },

        get adaYangDibawahDiterima() {
            return this.items.some(baris => this.dibawahDiterima(baris));
        },

        nilai(baris) {
            return (Number(baris.qty) || 0) * (Number(baris.harga_satuan) || 0);
        },

        get total() {
            return this.items.reduce((jumlah, baris) => jumlah + this.nilai(baris), 0);
        },

        formatQty(nilai) {
            return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 }).format(nilai);
        },

        rupiah(nilai) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(nilai));
        },
    };
}
</script>

@endsection
