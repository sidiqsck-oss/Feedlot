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
app/Models/                             22 model
database/migrations/                    skema OVK
tests/Feature/                          alur FIFO & siklus PO
docs/rancangan-database.md              rancangan + alur contoh
docs/deploy-cpanel.md                   cara pasang di hosting tanpa SSH
```

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
