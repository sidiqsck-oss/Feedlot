@extends('layouts.app')
@section('judul', 'Barang Masuk')

@php
    use App\Support\Format;

    // Data barang dikirim ke Alpine supaya satuan dan subtotal bisa ditampilkan
    // seketika tanpa bolak-balik ke server.
    $barangJson = $barang->map(fn ($b) => [
        'id' => $b->id,
        'nama' => $b->nama,
        'kode' => $b->kode,
        'satuan' => $b->satuan,
    ])->values();

    $barisAwal = $poDipilih
        ? $poDipilih->items
            ->filter(fn ($i) => $i->sisa() > 0)
            ->map(fn ($i) => [
                'barang_id' => (string) $i->barang_id,
                'qty' => (float) $i->sisa(),
                'harga_satuan' => (float) $i->harga_satuan,
                'purchase_order_item_id' => (string) $i->id,
            ])->values()
        : collect([['barang_id' => '', 'qty' => '', 'harga_satuan' => '', 'purchase_order_item_id' => '']]);
@endphp

@section('isi')

<form
    method="POST"
    action="{{ route('penerimaan.store') }}"
    x-data="notaMasuk({{ Js::from($barangJson) }}, {{ Js::from(old('items', $barisAwal)) }})"
>
    @csrf

    {{-- Header nota --}}
    <div class="kartu p-4">
        <h2 class="mb-3 text-sm font-bold text-ink">Keterangan Nota</h2>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="tanggal" class="label">Tanggal</label>
                <input id="tanggal" name="tanggal" type="date"
                       value="{{ old('tanggal', now()->toDateString()) }}" required class="input">
            </div>

            <div>
                <label for="supplier_id" class="label">Supplier</label>
                <select id="supplier_id" name="supplier_id" required class="input">
                    <option value="">— pilih —</option>
                    @foreach ($supplier as $s)
                        <option value="{{ $s->id }}"
                            @selected(old('supplier_id', $poDipilih?->supplier_id) == $s->id)>{{ $s->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="purchase_order_id" class="label">Purchase Order</label>
                <select id="purchase_order_id" name="purchase_order_id" class="input"
                        onchange="if (this.value) window.location = '{{ route('penerimaan.create') }}?po=' + this.value">
                    <option value="">Tanpa PO</option>
                    @foreach ($poTerbuka as $p)
                        <option value="{{ $p->id }}" @selected(old('purchase_order_id', $poDipilih?->id) == $p->id)>
                            {{ $p->nomor }} — {{ $p->supplier->nama }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-ink-mute">Boleh dikosongkan kalau barang masuk tanpa PO.</p>
            </div>

            <div>
                <label for="no_faktur_supplier" class="label">No. Faktur Supplier</label>
                <input id="no_faktur_supplier" name="no_faktur_supplier"
                       value="{{ old('no_faktur_supplier') }}" class="input">
            </div>
        </div>

        <div class="mt-4">
            <label for="catatan" class="label">Catatan</label>
            <textarea id="catatan" name="catatan" rows="2" class="input">{{ old('catatan') }}</textarea>
        </div>
    </div>

    {{-- Baris barang --}}
    <div class="kartu mt-4 overflow-hidden">
        <div class="flex items-center justify-between border-b border-rule px-4 py-3">
            <h2 class="text-sm font-bold text-ink">Daftar Barang</h2>
            <button type="button" @click="tambahBaris" class="tombol tombol-biasa">+ Tambah Baris</button>
        </div>

        <div class="overflow-x-auto">
            <table class="tabel">
                <thead>
                    <tr>
                        <th class="w-[45%]">Barang</th>
                        <th class="w-32 text-right">Jumlah</th>
                        <th class="w-40 text-right">Harga Satuan</th>
                        <th class="w-40 text-right">Subtotal</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(baris, i) in items" :key="i">
                        <tr>
                            <td>
                                <select
                                    :name="`items[${i}][barang_id]`"
                                    x-model="baris.barang_id"
                                    required
                                    class="input"
                                >
                                    <option value="">— pilih barang —</option>
                                    <template x-for="b in daftarBarang" :key="b.id">
                                        <option :value="b.id" x-text="`${b.nama} (${b.kode})`"></option>
                                    </template>
                                </select>
                                <input type="hidden" :name="`items[${i}][purchase_order_item_id]`"
                                       :value="baris.purchase_order_item_id || ''">
                            </td>

                            <td>
                                <div class="flex items-center gap-1.5">
                                    <input
                                        type="number" step="0.001" min="0.001"
                                        :name="`items[${i}][qty]`"
                                        x-model.number="baris.qty"
                                        required
                                        class="input angka"
                                    >
                                    <span class="w-12 shrink-0 text-xs text-ink-mute" x-text="satuan(baris)"></span>
                                </div>
                            </td>

                            <td>
                                <input
                                    type="number" step="1" min="0"
                                    :name="`items[${i}][harga_satuan]`"
                                    x-model.number="baris.harga_satuan"
                                    required
                                    class="input angka"
                                >
                            </td>

                            <td class="angka font-semibold text-ink" x-text="rupiah(subtotal(baris))"></td>

                            <td class="text-right">
                                <button
                                    type="button"
                                    @click="hapusBaris(i)"
                                    x-show="items.length > 1"
                                    class="text-lg leading-none text-keluar"
                                    aria-label="Hapus baris"
                                >&times;</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
                <tfoot>
                    <tr class="bg-ground">
                        <td colspan="3" class="text-right text-sm font-bold text-ink">Total</td>
                        <td class="angka text-base font-bold text-ink" x-text="rupiah(total)"></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
        <button type="submit" class="tombol tombol-utama">Simpan Nota</button>
        <a href="{{ route('penerimaan.index') }}" class="tombol tombol-biasa">Batal</a>
    </div>

    <p class="mt-3 max-w-2xl text-xs text-ink-mute">
        Tiap baris di nota ini membuat satu lot pembelian dengan harganya sendiri.
        Saat barang dikeluarkan nanti, lot yang paling lama masuk yang dipakai lebih dulu.
    </p>
</form>

<script>
function notaMasuk(daftarBarang, barisAwal) {
    return {
        daftarBarang,
        items: barisAwal.length ? barisAwal : [{ barang_id: '', qty: '', harga_satuan: '', purchase_order_item_id: '' }],

        tambahBaris() {
            this.items.push({ barang_id: '', qty: '', harga_satuan: '', purchase_order_item_id: '' });
        },

        hapusBaris(i) {
            this.items.splice(i, 1);
        },

        satuan(baris) {
            return this.daftarBarang.find(b => b.id == baris.barang_id)?.satuan ?? '';
        },

        subtotal(baris) {
            return (Number(baris.qty) || 0) * (Number(baris.harga_satuan) || 0);
        },

        get total() {
            return this.items.reduce((jumlah, baris) => jumlah + this.subtotal(baris), 0);
        },

        rupiah(nilai) {
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(nilai));
        },
    };
}
</script>

@endsection
