@extends('layouts.app')
@section('judul', 'Catat Penjualan')

@php
    $shipmentJson = $shipment->map(fn ($s) => ['id' => $s->id, 'kode' => $s->kode])->values();

    $barisAwal = old('items', [[
        'shipment_id' => $shipment->first()?->id,
        'rfid' => '', 'berat' => '', 'realisasi' => '', 'potongan' => '',
    ]]);
@endphp

@section('isi')

<form
    method="POST"
    action="{{ route('cpl.penjualan.store') }}"
    x-data="notaJual(
        {{ Js::from($shipmentJson) }},
        {{ Js::from($barisAwal) }},
        {{ Js::from((float) old('harga_per_kg', 0)) }},
        @js(route('cpl.penjualan.cari'))
    )"
>
    @csrf

    <div class="kartu p-4">
        <h2 class="mb-3 text-sm font-bold text-ink">Keterangan Nota</h2>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="tanggal" class="label">Tanggal</label>
                <input id="tanggal" name="tanggal" type="date" required class="input"
                       value="{{ old('tanggal', now()->toDateString()) }}">
            </div>

            <div>
                <label for="no_invoice" class="label">No Invoice</label>
                <input id="no_invoice" name="no_invoice" class="input"
                       value="{{ old('no_invoice') }}" placeholder="0091/INV-SCK/VI/26">
            </div>

            <div>
                <label for="no_surat_jalan" class="label">No Surat Jalan</label>
                <input id="no_surat_jalan" name="no_surat_jalan" class="input"
                       value="{{ old('no_surat_jalan') }}" placeholder="SJ-0091/26">
            </div>

            <div>
                <label for="status_sapi" class="label">Status Sapi</label>
                <select id="status_sapi" name="status_sapi" class="input">
                    @foreach (['Sehat', 'Salvage'] as $s)
                        <option value="{{ $s }}" @selected(old('status_sapi', 'Sehat') === $s)>{{ $s }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="customer" class="label">Customer</label>
                <input id="customer" name="customer" required class="input" value="{{ old('customer') }}">
            </div>

            <div>
                <label for="kode_customer" class="label">Kode Customer</label>
                <input id="kode_customer" name="kode_customer" class="input"
                       value="{{ old('kode_customer') }}" placeholder="C-014">
            </div>

            <div>
                <label for="nama_barang" class="label">Nama Barang</label>
                <input id="nama_barang" name="nama_barang" class="input"
                       value="{{ old('nama_barang', 'Sapi Bakalan') }}">
            </div>

            <div>
                <label for="harga_per_kg" class="label">Harga per Kg</label>
                <input id="harga_per_kg" name="harga_per_kg" type="number" step="1" min="0" required
                       class="input angka" x-model.number="harga" value="{{ old('harga_per_kg') }}">
                <p class="mt-1 text-xs text-ink-mute">Berlaku untuk seluruh baris di nota ini.</p>
            </div>
        </div>

        <input type="hidden" name="satuan" value="{{ old('satuan', 'Kg') }}">
    </div>

    <div class="kartu mt-4 overflow-hidden">
        <div class="flex items-center justify-between border-b border-rule px-4 py-3">
            <h2 class="text-sm font-bold text-ink">Daftar Ekor</h2>
            <button type="button" @click="tambahBaris" class="tombol tombol-biasa">+ Tambah Baris</button>
        </div>

        <div class="overflow-x-auto">
            <table class="tabel">
                <thead>
                    <tr>
                        <th class="w-28">Ship</th>
                        <th class="w-52">Nomor RFID</th>
                        <th>Sapi</th>
                        <th class="w-28 text-right">Berat (kg)</th>
                        <th class="w-32 text-right">Realisasi</th>
                        <th class="w-28 text-right">Potongan</th>
                        <th class="w-36 text-right">Total</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(baris, i) in items" :key="i">
                        <tr>
                            <td>
                                <select :name="`items[${i}][shipment_id]`" x-model="baris.shipment_id"
                                        @change="cari(baris)" required class="input">
                                    <template x-for="s in daftarShipment" :key="s.id">
                                        <option :value="s.id" x-text="s.kode"></option>
                                    </template>
                                </select>
                            </td>

                            <td>
                                <input :name="`items[${i}][rfid]`" x-model="baris.rfid"
                                       @input.debounce.400ms="cari(baris)" required
                                       class="input font-mono" placeholder="982000000000001">
                            </td>

                            {{-- Kolom ini tidak dikirim ke server; gunanya supaya salah
                                 ketik RFID ketahuan sebelum disimpan, bukan sesudah. --}}
                            <td class="text-xs">
                                <template x-if="baris.status === 'mencari'">
                                    <span class="text-ink-mute">mencari…</span>
                                </template>
                                <template x-if="baris.status === 'ketemu'">
                                    <span>
                                        <span class="font-mono text-ink" x-text="'ear tag ' + (baris.ear_tag || '—')"></span>
                                        <span class="block text-ink-mute"
                                              x-text="(baris.jenis || '') + (baris.berat_induksi ? ' · induksi ' + baris.berat_induksi + ' kg' : '')"></span>
                                        <span x-show="baris.sudah_terjual" class="block text-tanda"
                                              x-text="'sudah tercatat terjual ' + baris.sudah_terjual"></span>
                                    </span>
                                </template>
                                <template x-if="baris.status === 'tidak'">
                                    <span class="text-keluar">tidak ada di data induksi shipment ini</span>
                                </template>
                                <template x-if="! baris.status">
                                    <span class="text-ink-mute">—</span>
                                </template>
                            </td>

                            <td>
                                <input type="number" step="0.1" min="1" :name="`items[${i}][berat]`"
                                       x-model.number="baris.berat" required class="input angka">
                            </td>

                            <td>
                                <input type="number" step="1" min="0" :name="`items[${i}][realisasi]`"
                                       x-model.number="baris.realisasi" class="input angka">
                            </td>

                            <td>
                                <input type="number" step="1" min="0" :name="`items[${i}][potongan]`"
                                       x-model.number="baris.potongan" class="input angka">
                            </td>

                            <td class="angka font-semibold text-ink" x-text="rupiah(total(baris))"></td>

                            <td class="text-right">
                                <button type="button" @click="hapusBaris(i)" x-show="items.length > 1"
                                        class="text-lg leading-none text-keluar" aria-label="Hapus baris">&times;</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
                <tfoot>
                    <tr class="font-semibold">
                        <td colspan="3">Total <span x-text="items.length"></span> ekor</td>
                        <td class="angka" x-text="angka(totalBerat) + ' kg'"></td>
                        <td colspan="2"></td>
                        <td class="angka" x-text="rupiah(totalNilai)"></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div x-show="adaYangTidakKetemu" x-cloak
             class="border-t border-keluar bg-keluar-soft px-4 py-3 text-sm text-keluar">
            Ada RFID yang tidak ketemu di data induksi. Nota ini akan ditolak saat disimpan.
        </div>
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
        <button type="submit" class="tombol tombol-utama">Simpan Nota</button>
        <a href="{{ route('cpl.penjualan.index') }}" class="tombol tombol-biasa">Batal</a>
    </div>

    <p class="mt-3 max-w-2xl text-xs text-ink-mute">
        Total dihitung sendiri dari berat × harga, sama seperti di berkas invoice.
        Realisasi dan potongan dicatat apa adanya dan tidak ikut mengubah total.
    </p>
</form>

<script>
function notaJual(daftarShipment, barisAwal, hargaAwal, ruteCari) {
    const kosong = () => ({
        shipment_id: daftarShipment[0]?.id ?? '',
        rfid: '', berat: '', realisasi: '', potongan: '', status: null,
    });

    return {
        daftarShipment,
        harga: hargaAwal,
        items: (barisAwal.length ? barisAwal : [kosong()]).map(b => ({ ...kosong(), ...b })),

        tambahBaris() {
            // Shipment baris baru ikut baris terakhir — satu nota biasanya
            // menarik dari rombongan yang sama.
            this.items.push({ ...kosong(), shipment_id: this.items.at(-1)?.shipment_id ?? '' });
        },

        hapusBaris(i) {
            this.items.splice(i, 1);
        },

        cari(baris) {
            baris.status = null;

            if (! baris.rfid || baris.rfid.length < 4 || ! baris.shipment_id) return;

            baris.status = 'mencari';

            fetch(ruteCari + '?' + new URLSearchParams({
                rfid: baris.rfid, shipment_id: baris.shipment_id,
            }))
                .then(r => r.json())
                .then(d => {
                    if (! d.ketemu) {
                        baris.status = 'tidak';
                        return;
                    }

                    baris.status = 'ketemu';
                    baris.ear_tag = d.ear_tag;
                    baris.jenis = d.jenis;
                    baris.berat_induksi = d.berat_induksi;
                    baris.sudah_terjual = d.sudah_terjual;
                })
                .catch(() => baris.status = null);
        },

        total(baris) {
            return (Number(baris.berat) || 0) * (Number(this.harga) || 0);
        },

        get totalBerat() {
            return this.items.reduce((j, b) => j + (Number(b.berat) || 0), 0);
        },

        get totalNilai() {
            return this.items.reduce((j, b) => j + this.total(b), 0);
        },

        get adaYangTidakKetemu() {
            return this.items.some(b => b.status === 'tidak');
        },

        angka(nilai) {
            return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(nilai);
        },

        rupiah(nilai) {
            return 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(nilai);
        },
    };
}
</script>

@endsection
