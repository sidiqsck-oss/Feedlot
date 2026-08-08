# Rancangan Modul CPL — Cattle Performance Log

Dokumen ini rancangan untuk dikoreksi, **belum jadi kode**. Fokusnya dashboard:
apa yang ditampilkan, dan bagaimana datanya diolah.

CPL berdiri sebagai **menu tersendiri**, terpisah dari OVK.

---

## 1. Empat temuan dari data yang berjalan sekarang

Semuanya ditemukan saat membaca `dashboard_cpl.csv` (8.658 baris) dan skrip
yang membuatnya. Dari 1.046 sapi terjual, **86 ekor (8,2%) tidak punya data
reweight** — dan dua temuan pertama sama-sama berakar dari sana.

### 1.1 Baris TOTAL di laporan Excel salah populasi

Ini yang paling perlu diperhatikan, karena ada di laporan yang dikirim ke
atasan. Di `4_buat_cpl.py`:

```python
tot_ind = df_cpl['BRT INDCT'].sum()      # SEMUA 1.046 ekor
tot_rwt = df_cpl['RWT Wt (Kg)'].sum()    # HANYA 960 ekor yang punya reweight
val = (tot_rwt - tot_ind) / tot_dof_rwt
```

Pembilangnya mengurangi berat induksi 1.046 ekor dari berat reweight 960 ekor.
Dua populasi berbeda dikurangkan, jadi hasilnya tidak berarti apa-apa.

| | ADG RWT |
|---|---|
| Yang tercetak sekarang | **1,624** |
| Yang seharusnya | **2,126** |

Selisihnya 0,5 kg/hari — sekitar **24% understated**.

**Aturannya di modul baru: pembilang dan penyebut harus dari populasi yang
sama.** Kalau satu ekor tidak punya reweight, dia keluar dari perhitungan ADG
RWT sepenuhnya — bukan cuma dari penyebutnya.

### 1.2 Dashboard juga lebih rendah, dengan sebab berbeda

`5_export_dashboard_data.py` menulis `else 0` untuk nilai yang tidak bisa
dihitung. Sapi tanpa reweight jadi tercatat ADG RWT = **0**, bukan kosong, dan
dashboard memperlakukan 0 sebagai angka sah lalu ikut merata-ratakannya.

| | ADG RWT |
|---|---|
| Yang tampil di dashboard | **1,943** |
| Yang seharusnya | **2,117** |

Aturannya: **kosong itu kosong, bukan nol.** Nilai yang tidak bisa dihitung
disimpan NULL, semua rata-rata mengabaikannya, dan jumlah ekor yang ikut
dihitung selalu ditampilkan di sebelah angkanya supaya ketahuan kalau datanya
tipis.

> Tiga angka untuk hal yang sama — 1,624 di laporan Excel, 1,943 di dashboard,
> 2,126 yang sebenarnya. Ketiganya beda karena tiap tempat menghitungnya
> sendiri-sendiri. Itu sebabnya di modul baru semua angka berasal dari satu
> kelas query yang sama.

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
| `keterangan` | catatan bebas |

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

### Rumus agregat — tertimbang

Perlakuannya mengikuti baris TOTAL di laporan Excel, dengan kesalahan populasi
di bagian 1.1 dibetulkan.

| Agregat | Rumus |
|---|---|
| ADG Induction | `(Σ berat_jual − Σ berat_induksi) ÷ Σ DOF Induction` |
| ADG RWT | `(Σ berat_reweight − Σ berat_induksi) ÷ Σ DOF RWT` |
| ADG Discharge | `(Σ berat_jual − Σ berat_muat) ÷ Σ DOF Discharge` |
| ADG JUAL | `(Σ berat_jual − Σ berat_reweight) ÷ Σ DOF JUAL` |
| SELISIH RWT-JUAL | `ADG JUAL tertimbang − ADG RWT tertimbang` |
| Gain/Loss (%) | `Σ Gain/Loss ÷ Σ berat_muat × 100` |
| Gain (%) | `Σ Gain ÷ Σ berat_induksi × 100` |

**Aturan populasi — ini yang membetulkan temuan 1.1:**

Setiap agregat hanya menjumlahkan ekor yang **semua bahannya lengkap**. Untuk
ADG RWT, sapi tanpa data reweight keluar dari ketiga penjumlahan sekaligus —
berat reweight, berat induksi, maupun DOF. Bukan cuma dari penyebutnya.

Jadi kalau dalam satu shipment ada 100 ekor dan hanya 80 yang di-reweight, ADG
RWT-nya dihitung dari 80 ekor itu saja, dan di sebelah angkanya tertulis
`n = 80` supaya jelas dasarnya berapa ekor.

> Catatan: laporan Excel sekarang tidak konsisten — baris TOTAL memakai
> tertimbang untuk ADG Induction dan ADG RWT, tapi rata-rata sederhana untuk
> ADG Discharge, ADG JUAL, dan SELISIH. Di sini keempatnya dibuat tertimbang.
> Kalau ternyata ADG JUAL memang sengaja rata-rata sederhana, tolong bilang.

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

**Tampilan dan cara kerjanya** mengikuti berkas HTML yang dikirim — susunan
kartu, warna, dan perilaku penyaringnya. **Cara menghitungnya** mengikuti
Streamlit lama, dengan pembetulan di bagian 1.

### Penyaring saling terhubung

Persis seperti `subFilters()` di HTML: pilihan di satu penyaring dipersempit
oleh penyaring lain yang sedang aktif, sehingga tidak pernah ada pilihan yang
hasilnya kosong.

```
Rentang tanggal  →  mempersempit  →  Shipment
                                     Jenis
                                     Property
                                     Customer
                                     Invoice
```

Mengubah rentang tanggal mengosongkan penyaring di bawahnya, sama seperti
`$('selDate').addEventListener('change', ...)` di HTML.

**Kalau tidak ada tanggal dipilih**, yang tampil adalah **10 invoice terakhir**
— perilaku bawaan dari Streamlit, supaya halaman tidak pernah kosong saat
pertama dibuka.

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
Dibatasi **8 shipment terakhir**, karena shipment lama isinya tinggal
sisa-sisa dan cuma bikin tabelnya panjang.

| Shipment | Ekor aktif | DOF berjalan | Bobot induksi | Perkiraan bobot kini |
|---|---|---|---|---|

Perkiraan bobot = `berat terakhir + (ADG rombongan × hari sejak ditimbang)`,
dan diberi label jelas sebagai perkiraan.

---

## 6. Laporan CPL

Halaman terpisah. Fungsinya mengikuti Streamlit lama, karena tim sudah hafal
alurnya.

### 6.1 CPL Detail — per ekor

- Penyaring sama dan saling terhubung seperti di dashboard
- **Satu tabel per customer**, plus tabel gabungan kalau lebih dari satu
- Baris **rata-rata di atas** judul kolom, baris **total di bawah**
- Kolom berwarna sama seperti sekarang: peach untuk bobot, kuning untuk exit,
  biru untuk DOF, abu untuk ADG, merah muda untuk selisih
- SELISIH RWT-JUAL merah kalau negatif, hijau kalau positif
- Cetak PDF dan unduh CSV/Excel memakai mesin yang sudah ada

### 6.2 Personalisasi kolom

Dibawa apa adanya dari Streamlit, termasuk **semuanya tercentang sejak awal**
supaya laporan bawaannya ringkas.

| Sembunyikan | Bawaan |
|---|---|
| RFID · Load Date · Gain/Loss · Detail Asal | tersembunyi |
| RWT Date · RWT Wt · DOF RWT · ADG RWT | tersembunyi |
| DOF JUAL · ADG JUAL · SELISIH RWT-JUAL | tersembunyi |

Pilihannya diingat per pengguna, jadi tidak perlu diatur ulang tiap buka.

### 6.3 Closing CPL — ringkasan

Jalur kedua dari Streamlit: laporan yang **baris detail per sapinya dibuang**,
tinggal ringkasan per kelompok. Dipakai untuk penutupan, bukan pemeriksaan
per ekor.

Penyaringnya sama, tapi pilihan sembunyikan kolom tidak berlaku karena memang
tidak ada baris detailnya.

---

## 7. Yang sudah dikunci

| Hal | Keputusan |
|---|---|
| Tampilan & perilaku dashboard | Ikut berkas HTML yang dikirim |
| Cara menghitung | Ikut Streamlit lama, dengan pembetulan di bagian 1 |
| Rata-rata ADG | **Tertimbang** |
| ADG RWT | Hanya untuk yang punya reweight; yang tidak punya, kosong |
| Sebagian reweight dalam satu shipment | Tetap dihitung dari yang punya saja, `n` ditampilkan |
| Jenis claim | `mati` · `salvage` · `sakit_bawaan`, ditambah kolom keterangan |
| Umur saat claim | Dihitung dari tanggal tiba |
| Populasi aktif | Perlu, dibatasi 8 shipment terakhir |
| Penyaring | Sama dan saling terhubung |
| Laporan | CPL Detail + Closing, dengan pilihan sembunyikan kolom |

---

## 8. Yang masih perlu kamu jawab

1. **Angka ADG RWT akan berubah cukup jauh.** Di laporan Excel dari 1,624 jadi
   2,126; di dashboard dari 1,943 jadi 2,117. Kalau angka lama sudah terlanjur
   dipakai di laporan ke atasan, sebaiknya dijelaskan dulu sebelum berubah
   sendiri di sistem baru.
2. **ADG Discharge, ADG JUAL, dan SELISIH** di laporan sekarang memakai
   rata-rata sederhana, sementara ADG Induction dan ADG RWT tertimbang. Aku
   samakan jadi tertimbang semua — kecuali kalau tiga itu memang sengaja
   dibuat rata-rata sederhana.
3. **Corong shipment** butuh jumlah ekor yang benar-benar tiba per shipment.
   Angka itu ada di berkas pembelian, atau perlu diinput manual?
4. **Analisa tambahan yang bos minta** — kamu bilang ada. Apa saja? Empat tabel
   pembanding di bagian 5 itu tebakanku; kalau ada yang spesifik dia minta,
   lebih baik masuk sekarang daripada ditambal belakangan.
