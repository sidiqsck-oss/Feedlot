# Rancangan Modul CPL — Cattle Performance Log

Dokumen ini rancangan untuk dikoreksi, **belum jadi kode**. Fokusnya dashboard:
apa yang ditampilkan, dan bagaimana datanya diolah.

CPL berdiri sebagai **menu tersendiri**, terpisah dari OVK.

---

## 1. Tiga temuan dari data yang berjalan sekarang

Ketiganya ditemukan saat membaca `dashboard_cpl.csv` (8.658 baris) dan skrip
yang membuatnya. Semuanya memengaruhi rancangan.

### 1.1 Angka ADG RWT yang dilihat sekarang lebih rendah dari kenyataan

`5_export_dashboard_data.py` menulis `else 0` untuk nilai yang tidak bisa
dihitung. Sapi yang tidak punya data reweight jadi tercatat ADG RWT = **0**,
bukan kosong. Dashboard memperlakukan 0 sebagai angka sah dan ikut
merata-ratakannya.

| | Nilai |
|---|---|
| Sapi terjual | 1.046 ekor |
| Tanpa data reweight | **86 ekor (8,2%)** |
| ADG RWT yang tampil sekarang | **1,943** |
| ADG RWT sebenarnya | **2,117** |

Selisihnya 0,17 kg/hari — sekitar **8% understated**. Aturan di modul baru:
**kosong itu kosong, bukan nol.** Nilai yang tidak bisa dihitung disimpan NULL,
dan semua rata-rata mengabaikannya. Jumlah ekor yang ikut dihitung selalu
ditampilkan di sebelah angkanya, supaya ketahuan kalau datanya tipis.

### 1.2 CLAIM menumpang di kolom JENIS

Isi kolom `JENIS` sekarang:

```
Steer 5.927 · Bull 1.446 · Heifer 971 · Medium Str 226
Medium Bull 52 · Prod Heifer 30 · CLAIM 6
```

`CLAIM` bukan jenis sapi, itu status. Selama menumpang di sana, sapi claim
ikut terhitung sebagai jenis tersendiri di laporan, dan sapi yang mati sebelum
induksi tidak punya tempat sama sekali. Claim dapat tabelnya sendiri.

### 1.3 Dashboard sekarang hanya melihat sapi yang sudah terjual

Baris `if(!exitdate) continue;` membuang setiap sapi yang belum punya tanggal
jual. Akibatnya dua hal tidak terlihat sama sekali:

- **Sapi yang masih di kandang** — berapa ekor, sudah berapa hari, perkiraan bobot
- **Sapi yang mati atau di-claim** — tidak pernah punya tanggal jual, jadi hilang

Ini yang paling penting untuk bos: ADG 2,1 terdengar bagus, tapi artinya beda
jauh kalau dari 300 ekor yang datang ternyata 20 mati. **Angka performa dan
angka susut harus duduk bersebelahan.**

---

## 2. Menu CPL

Terpisah penuh dari OVK.

```
CPL
├── Dashboard CPL          ringkasan & pembanding untuk pimpinan
├── Laporan CPL            tabel rinci per customer, seperti yang sekarang
├── Data Sapi              daftar per ekor, telusur satu-satu
├── Claim                  sapi mati / salvage / sakit bawaan
├── Penjualan              (nanti berupa form)
└── Impor Data
    ├── Induksi
    ├── Reweight
    ├── Pembelian per Shipment
    └── Property
```

Menu OVK tetap seperti sekarang dan tidak bercampur.

---

## 3. Tabel yang perlu ditambah

Yang sudah ada: `shipments`, `induksi`, `reweight`.

### `properties`
Dari `PIC NT.xlsx`. Menjawab: **beli dari properti mana yang hasilnya bagus.**

| Kolom | Catatan |
|---|---|
| `kode` | PIC, mis. `QABC123` — kunci sambungan ke induksi |
| `nama` | Property Name / Holding |
| `daerah` | nullable |
| `aktif` | |

### `pembelian_shipment`
Dari `Cattle Performance Log.xlsx`.

**Penting:** datanya **per shipment + jenis**, bukan per ekor. Load Wt dan
Feedlot Wt di CPL sekarang adalah angka shipment yang disalin ke setiap ekor.
Jadi di dashboard, keduanya tidak boleh disajikan seolah hasil timbangan
per ekor.

| Kolom | Catatan |
|---|---|
| `shipment_id` + `jenis` | pasangan kunci, unik |
| `tanggal_muat` | Load Date |
| `berat_muat` | Load Wt, rata-rata per ekor di pelabuhan asal |
| `tanggal_tiba` | Feedlot Date |
| `berat_tiba` | Feedlot Wt, rata-rata per ekor saat tiba |
| `jumlah_ekor` | untuk menghitung susut angkutan |

### `penjualan`
Dari `SJ INV SCK.xlsm`. Sementara diimpor, nanti diganti form.

| Kolom | Catatan |
|---|---|
| `induksi_id` | disambungkan lewat RFID |
| `tanggal` | Exit Date |
| `berat` | Exit Wt |
| `customer` · `no_invoice` | |
| `harga_per_kg` · `total` | |
| `status_sapi` | Sehat / Salvage |

Satu ekor bisa punya lebih dari satu baris kalau ada koreksi; yang dipakai
selalu yang terakhir, sama seperti `drop_duplicates(keep='last')` sekarang.

### `claim` — tabel baru
Menjawab pertanyaanmu: **berapa ekor, sakitnya apa, umur berapa hari.**

| Kolom | Catatan |
|---|---|
| `shipment_id` | **wajib** |
| `induksi_id` | **nullable** — sapi yang mati sebelum induksi tidak punya |
| `rfid` · `ear_tag` | teks apa adanya, karena mungkin belum tercatat di induksi |
| `tanggal_kejadian` | |
| `jenis_claim` | `mati` · `salvage` · `sakit_bawaan` |
| `fase` | `sebelum_induksi` · `sesudah_induksi` |
| `diagnosa` | |
| `berat` | diisi kalau salvage |
| `nilai_klaim` | |
| `status_klaim` | `diajukan` · `disetujui` · `ditolak` |

`induksi_id` sengaja boleh kosong. Kamu bilang **lebih sering mati sebelum
induksi** — sapi itu tidak punya baris induksi sama sekali, jadi kalau claim
dipaksa menempel ke induksi, justru kasus yang paling sering yang tidak bisa
dicatat.

**Umur saat claim** dihitung dari `tanggal_kejadian − tanggal_tiba` shipment,
bukan dari tanggal induksi. Hanya patokan itu yang berlaku untuk semua kasus,
termasuk yang mati sebelum induksi.

---

## 4. Bagaimana satu baris CPL disusun

```
induksi  (satu baris = satu ekor)
   ├── shipment
   ├── pembelian_shipment   cocok lewat shipment + jenis  → Load / Feedlot
   ├── properties           cocok lewat kode_prop         → nama property
   ├── reweight             terakhir per ekor             → RWT
   ├── penjualan            terakhir per ekor             → Exit
   └── claim                kalau ada                     → keluar dari populasi
```

### Rumusnya — sama persis dengan yang sekarang

| Turunan | Rumus |
|---|---|
| Gain/Loss (Kg) | `berat_induksi − berat_muat` |
| Gain/Loss (%) | `Gain/Loss ÷ berat_muat × 100` |
| Gain (Kg) | `berat_jual − berat_induksi` |
| Gain (%) | `Gain ÷ berat_induksi × 100` |
| DOF Discharge | `tanggal_jual − tanggal_muat` |
| ADG Discharge | `(berat_jual − berat_muat) ÷ DOF Discharge` |
| DOF Induction | `tanggal_jual − tanggal_induksi` |
| ADG Induction | `Gain ÷ DOF Induction` |
| DOF RWT | `tanggal_reweight − tanggal_induksi` |
| ADG RWT | `(berat_reweight − berat_induksi) ÷ DOF RWT` |
| DOF JUAL | `tanggal_jual − tanggal_reweight` |
| ADG JUAL | `(berat_jual − berat_reweight) ÷ DOF JUAL` |
| SELISIH RWT-JUAL | `ADG JUAL − ADG RWT` |

**Satu-satunya perubahan:** pembagi nol atau data kosong menghasilkan **NULL**,
bukan 0. Semua rata-rata mengabaikan NULL, dan jumlah ekor yang ikut dihitung
selalu ditampilkan.

> Ada satu ketidakcocokan di sistem sekarang yang ikut dibereskan: skrip Python
> menghitung DOF Discharge dari **Load Date**, sementara dashboard HTML
> menghitungnya dari **Feedlot Date** kalau kolomnya kosong. Di modul baru
> patokannya satu: **Load Date**, sesuai skrip yang jadi sumber angkanya.

### Status setiap ekor

Diturunkan, bukan diketik — jadi tidak mungkin ada sapi berstatus "terjual"
tapi tidak punya baris penjualan.

| Status | Syarat |
|---|---|
| `tiba` | ada di pembelian shipment, belum ada baris induksi |
| `aktif` | sudah induksi, belum terjual, belum claim |
| `terjual` | ada baris penjualan |
| `claim` | ada baris claim |

### Dihitung di query, bukan disimpan

Satu kelas pembangun query (`KueriCpl`) dipakai bersama oleh dashboard,
laporan, dan unduhan — sama seperti laporan OVK, supaya angka di tiga tempat
itu tidak mungkin berbeda.

Sengaja **tidak** memakai view database: hak `CREATE VIEW` belum tentu diberikan
di shared hosting, alasan yang sama seperti kartu stok tidak memakai trigger.
Dengan 8.658 baris dan indeks yang benar, query gabungan ini ringan.

---

## 5. Dashboard CPL

Dashboard yang sekarang adalah **penampil laporan**: pilih satu tanggal jual,
lihat rinciannya. Bagus untuk memeriksa satu transaksi.

Yang diminta bos berbeda — dia mau **membandingkan**. Jadi dashboard baru
disusun sebagai pembanding, dan laporan rinci tetap ada di halamannya sendiri.

**Penyaring:** rentang tanggal · shipment · jenis · property · customer

### Baris 1 — Ringkasan periode

Tiap angka membawa pembanding periode sebelumnya (▲/▼), karena satu angka tanpa
pembanding tidak memberi tahu apa pun.

| Kartu | Isi |
|---|---|
| Ekor terjual | jumlah + Δ |
| ADG Induction | rata-rata + Δ + `n` ekor |
| Bobot jual rata-rata | kg + Δ |
| Gain per ekor | kg + Δ |
| DOF rata-rata | hari + Δ |
| **Susut (claim)** | % ekor + Δ |

Kartu susut sengaja ditaruh sederet dengan ADG. Keduanya harus dibaca bersama.

### Baris 2 — Corong shipment

Menjawab: **dari yang datang, berapa yang jadi uang.**

```
Tiba 312  →  Induksi 306  →  Aktif 54  →  Terjual 240  →  Claim 18
                  (98,1%)                      (76,9%)      (5,8%)
```

Hilang 6 ekor antara tiba dan induksi — itu yang mati sebelum induksi, dan di
sistem sekarang benar-benar tidak terlihat di mana pun.

### Baris 3 — Perbandingan

Empat tabel ringkas, semuanya bisa diurutkan. Inilah inti dashboard ini.

**a. Per Shipment**

| Shipment | Ekor | ADG Induct | Gain/ekor | DOF | Claim % | Bobot jual |
|---|---|---|---|---|---|---|

**b. Per Property** — nilai bisnisnya paling besar: menentukan mau beli dari
mana lagi. Kolom sama, dikelompokkan per property asal.

**c. Per Jenis** — Steer vs Bull vs Heifer.

**d. Per Customer** — ekor, bobot rata-rata, nilai.

### Baris 4 — Grafik

Dua grafik yang sudah dipakai sekarang dibawa apa adanya, karena sudah dikenal:

- **Distribusi ADG Induction** — histogram bin 0,25 dengan zona warna
  (<1,0 merah · <1,5 oranye · <2,0 hijau muda · ≥2,0 hijau tua)
- **ADG RWT vs ADG JUAL** — batang per ear tag, merah kalau melambat

Ditambah satu:

- **Tren bulanan** — ADG Induction dan jumlah ekor terjual per bulan

### Baris 5 — Claim

| Isi | Keterangan |
|---|---|
| Ringkasan | mati sebelum induksi · mati sesudah · salvage |
| Diagnosa terbanyak | urut dari yang paling sering |
| Umur rata-rata saat claim | hari sejak tiba |
| Claim per shipment | untuk melihat rombongan mana yang bermasalah |

### Baris 6 — Populasi aktif

Sapi yang masih di kandang, yang sekarang tidak terlihat sama sekali.

| Shipment | Ekor aktif | DOF berjalan | Bobot induksi | Perkiraan bobot kini |
|---|---|---|---|---|

Perkiraan bobot = `berat terakhir + (ADG rombongan × hari sejak ditimbang)`,
dan diberi label jelas sebagai perkiraan.

---

## 6. Laporan CPL

Halaman terpisah, dibuat semirip mungkin dengan yang sekarang — bos dan tim
sudah hafal bentuknya.

- Penyaring: tanggal jual → jenis · shipment · customer · invoice
- **Satu tabel per customer**, plus tabel gabungan kalau lebih dari satu
- Baris **rata-rata di atas** judul kolom, baris **total di bawah**
- 24 kolom dengan warna yang sama: peach untuk bobot, kuning untuk exit,
  biru untuk DOF, abu untuk ADG, merah muda untuk selisih
- SELISIH RWT-JUAL merah kalau negatif, hijau kalau positif
- Cetak PDF dan unduh CSV/Excel memakai mesin yang sudah ada

---

## 7. Yang perlu kamu koreksi

1. **ADG RWT sebenarnya 2,117, bukan 1,943.** Kalau angka lama sudah terlanjur
   dipakai di laporan ke atasan, perlu dijelaskan dulu sebelum berubah sendiri.
2. **Rata-rata ADG: sederhana atau tertimbang?** Yang sekarang rata-rata
   sederhana (tiap ekor bobot sama). Untuk membandingkan shipment, tertimbang
   (total gain ÷ total hari) lebih jujur karena satu ekor luar biasa tidak
   mengangkat seluruh rombongan. Pakai yang mana?
3. **Jenis claim** — cukup `mati` / `salvage` / `sakit_bawaan`, atau ada lagi?
4. **Umur saat claim dihitung dari tanggal tiba** — sudah sesuai dengan yang
   kamu maksud?
5. **Corong shipment** butuh jumlah ekor yang benar-benar tiba per shipment.
   Angka itu ada di berkas pembelian, atau perlu diinput manual?
6. **Populasi aktif** — perlu tidak? Ini tambahan dari aku, bukan dari
   dashboard yang kamu kirim.
