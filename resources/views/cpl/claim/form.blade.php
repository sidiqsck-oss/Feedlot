@extends('layouts.app')
@section('judul', $claim->exists ? 'Ubah Claim' : 'Catat Claim')

@section('isi')

{{--
    Pencarian RFID dilakukan sambil mengetik supaya operator langsung tahu
    sapinya sudah diinduksi atau belum — itu yang menentukan fase claim, dan
    kalau ketemu, ear tag serta berat induksinya tidak perlu diketik ulang.
--}}
<form
    method="POST"
    action="{{ $claim->exists ? route('cpl.claim.update', $claim) : route('cpl.claim.store') }}"
    class="kartu max-w-2xl p-4"
    x-data="{
        rfid: @js(old('rfid', $claim->rfid)),
        shipmentId: @js(old('shipment_id', $claim->shipment_id)),
        hasil: null,
        mencari: false,
        cari() {
            this.hasil = null

            if (! this.rfid || this.rfid.length < 4 || ! this.shipmentId) return

            this.mencari = true

            fetch(@js(route('cpl.claim.cari')) + '?' + new URLSearchParams({
                rfid: this.rfid, shipment_id: this.shipmentId,
            }))
                .then(r => r.json())
                .then(d => {
                    this.hasil = d
                    if (d.ketemu) {
                        if (! this.$refs.earTag.value) this.$refs.earTag.value = d.ear_tag ?? ''
                        this.$refs.fase.value = 'sesudah_induksi'
                    } else {
                        this.$refs.fase.value = 'sebelum_induksi'
                    }
                })
                .finally(() => this.mencari = false)
        },
    }"
>
    @csrf
    @if ($claim->exists) @method('PUT') @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="shipment_id" class="label">Shipment</label>
            <select id="shipment_id" name="shipment_id" required class="input"
                    x-model="shipmentId" @change="cari()">
                <option value="">— pilih shipment —</option>
                @foreach ($shipment as $s)
                    <option value="{{ $s->id }}" @selected(old('shipment_id', $claim->shipment_id) == $s->id)>
                        {{ $s->kode }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="tanggal_kejadian" class="label">Tanggal Kejadian</label>
            <input id="tanggal_kejadian" name="tanggal_kejadian" type="date" required class="input"
                   value="{{ old('tanggal_kejadian', $claim->tanggal_kejadian?->format('Y-m-d')) }}">
        </div>

        <div>
            <label for="rfid" class="label">Nomor RFID</label>
            <input id="rfid" name="rfid" class="input font-mono" x-model="rfid" @input.debounce.400ms="cari()"
                   value="{{ old('rfid', $claim->rfid) }}" placeholder="982000000000001">

            <p class="mt-1 text-xs" x-show="mencari" x-cloak>
                <span class="text-ink-mute">Mencari…</span>
            </p>
            <template x-if="hasil && hasil.ketemu">
                <p class="mt-1 text-xs text-masuk" x-cloak>
                    Ketemu — sapi ini sudah diinduksi
                    <span x-text="hasil.tanggal_induksi ? 'tanggal ' + hasil.tanggal_induksi : ''"></span>,
                    berat induksi <span x-text="hasil.berat_induksi ?? '—'"></span> kg.
                </p>
            </template>
            <template x-if="hasil && ! hasil.ketemu">
                <p class="mt-1 text-xs text-ink-mute" x-cloak>
                    Tidak ada di data induksi shipment ini — wajar kalau matinya sebelum induksi.
                    Claim tetap bisa dicatat.
                </p>
            </template>
        </div>

        <div>
            <label for="ear_tag" class="label">Ear Tag</label>
            <input id="ear_tag" name="ear_tag" class="input font-mono" x-ref="earTag"
                   value="{{ old('ear_tag', $claim->ear_tag) }}">
        </div>

        <div>
            <label for="jenis_claim" class="label">Jenis Claim</label>
            <select id="jenis_claim" name="jenis_claim" required class="input">
                @foreach ($jenisClaim as $nilai => $nama)
                    <option value="{{ $nilai }}" @selected(old('jenis_claim', $claim->jenis_claim) === $nilai)>
                        {{ $nama }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="fase" class="label">Fase</label>
            <select id="fase" name="fase" required class="input" x-ref="fase">
                @foreach ($fase as $nilai => $nama)
                    <option value="{{ $nilai }}" @selected(old('fase', $claim->fase) === $nilai)>{{ $nama }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-ink-mute">
                Terisi sendiri dari hasil pencarian RFID. Tanpa baris induksi, fasenya
                selalu dianggap sebelum induksi.
            </p>
        </div>

        <div class="sm:col-span-2">
            <label for="diagnosa" class="label">Diagnosa</label>
            <input id="diagnosa" name="diagnosa" class="input"
                   value="{{ old('diagnosa', $claim->diagnosa) }}"
                   placeholder="Pneumonia, patah kaki, kembung, …">
        </div>

        <div>
            <label for="berat" class="label">Berat (kg)</label>
            <input id="berat" name="berat" type="number" step="0.1" min="0" class="input"
                   value="{{ old('berat', $claim->berat) }}">
        </div>

        <div>
            <label for="nilai_klaim" class="label">Nilai Klaim (Rp)</label>
            <input id="nilai_klaim" name="nilai_klaim" type="number" step="1" min="0" class="input"
                   value="{{ old('nilai_klaim', $claim->nilai_klaim) }}">
        </div>

        <div>
            <label for="status_klaim" class="label">Status Klaim</label>
            <select id="status_klaim" name="status_klaim" required class="input">
                @foreach ($statusKlaim as $nilai => $nama)
                    <option value="{{ $nilai }}" @selected(old('status_klaim', $claim->status_klaim) === $nilai)>
                        {{ $nama }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="sm:col-span-2">
            <label for="keterangan" class="label">Keterangan</label>
            <textarea id="keterangan" name="keterangan" rows="3" class="input">{{ old('keterangan', $claim->keterangan) }}</textarea>
        </div>
    </div>

    <div class="mt-5 flex flex-wrap gap-2 border-t border-rule pt-4">
        <button type="submit" class="tombol tombol-utama">Simpan</button>
        <a href="{{ route('cpl.claim.index') }}" class="tombol tombol-biasa">Batal</a>

        @if ($claim->exists)
            <button type="submit" form="hapus-claim" class="tombol tombol-biasa ml-auto text-keluar"
                    onclick="return confirm('Hapus claim ini?')">Hapus</button>
        @endif
    </div>
</form>

@if ($claim->exists)
    <form id="hapus-claim" method="POST" action="{{ route('cpl.claim.destroy', $claim) }}" class="hidden">
        @csrf @method('DELETE')
    </form>
@endif

@endsection
