# SCK Feedlot

Sistem manajemen peternakan Sumber Cipta Kencana. Laravel + MySQL, menggantikan
aplikasi Streamlit yang menyimpan datanya sebagai Google Sheets dan file
CSV/XLSX di dalam repo.

Modul pertama yang dikerjakan: **OVK dan perbekalan kesehatan.**

## Kenapa pindah

Sistem lama tidak punya database. Data disimpan sebagai file yang di-*commit*
ke repo GitHub, dan GitHub Actions dipakai sebagai pengganti backend: aplikasi
memicu workflow, workflow mengolah file, lalu hasilnya di-*commit* balik.

Cerdik untuk keterbatasan Streamlit Cloud, tapi ongkosnya nyata — dua orang
tidak bisa menginput bersamaan, tiap simpan menunggu 1–2 menit, satu password
dipakai bersama tanpa jejak siapa menginput apa, dan payload yang terproses dua
kali membuat stok bertambah dua kali tanpa cara mendeteksinya.

## Prinsip yang membedakan

**Stok dihitung, bukan disimpan.** Tabel `pergerakan_stok` hanya boleh
ditambah — tidak pernah di-*update*, tidak pernah dihapus. Stok adalah hasil
penjumlahan pergerakan, sehingga nota yang masuk dua kali langsung ketahuan dan
riwayatnya tetap utuh. Bug "stok ganda" jadi mustahil secara struktur.

Harga persediaan memakai **FIFO** dengan pelacakan per lot pembelian, dan
alokasinya disimpan sehingga tiap angka bisa ditelusuri sampai ke nota asalnya.

## Struktur

```
app/Services/StokService.php            FIFO, kartu stok, opname
app/Services/PurchaseOrderService.php   revisi, tutup, batal PO
app/Services/NomorDokumenService.php    SCK-OVK-M-V-26-001
app/Services/Ekspor/                    CSV streaming, Excel, PDF
app/Services/Impor/                     baca berkas, templat, pratinjau
app/Models/                             26 model
database/migrations/                    skema OVK + induksi/reweight
tests/Feature/                          57 test
docs/rancangan-database.md              rancangan + alur contoh
docs/deploy-cpanel.md                   cara pasang di hosting tanpa SSH
```

## Ekspor dan impor

**Ekspor** dibedakan menurut kemampuan masing-masing format, bukan disamakan:

| Format | Dipakai untuk | Alasan |
|---|---|---|
| CSV | semua ekspor data | streaming, pemakaian memori tidak tumbuh seiring jumlah baris |
| Excel | laporan berformat, maksimal 5.000 baris | PhpSpreadsheet menyusun seluruh berkas di memori |
| PDF | dokumen yang dicetak dan diarsipkan | bukan untuk mengeluarkan data mentah |

Batas Excel ditegakkan dengan pesan yang menyarankan CSV, bukan dibiarkan mati
kehabisan memori — yang di shared hosting muncul sebagai halaman putih tanpa
keterangan.

**Impor** induksi dan reweight berjalan dua tahap: unggah menghasilkan
pratinjau, dan tidak ada satu baris pun yang masuk sampai dikonfirmasi. Berkas
yang sama tidak bisa diunggah dua kali, kesalahan dilaporkan per baris lengkap
dengan nomor barisnya di Excel, dan batch di atas 100 baris diproses lewat
antrean supaya tidak menabrak batas waktu eksekusi PHP.

Identitas sapi memakai dua kunci sekaligus: `shipment + RFID` sebagai identitas
utama, dan `shipment + ear tag` sebagai jembatan ke rekam medis dokter — sheet
dokter tidak mencatat RFID sama sekali. Ear tag sendiri tidak unik; di data lama
ada 40 nomor yang dipakai ulang di shipment berbeda.

## Menjalankan di lokal

```bash
composer install
cp .env.example .env
php artisan key:generate

# Pakai sqlite untuk lokal
touch database/database.sqlite
# lalu set di .env:  DB_CONNECTION=sqlite  dan  DB_DATABASE=<path absolut>

php artisan migrate --seed
php artisan test
```

## Rilis

Hosting tujuan adalah shared hosting cPanel **tanpa akses SSH**, jadi
`composer` dan `npm` dijalankan di GitHub Actions, lalu hasil jadinya dikirim
lewat FTP. Migrasi dijalankan melalui route terproteksi karena `php artisan`
tidak bisa dipanggil di server.

Langkah lengkapnya ada di [`docs/deploy-cpanel.md`](docs/deploy-cpanel.md).
