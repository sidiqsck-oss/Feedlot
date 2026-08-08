# Pasang di Hosting cPanel (Tanpa SSH)

Panduan sekali-jalan untuk menaruh aplikasi ini di shared hosting. Ditulis
untuk kondisi: **versi PHP bisa dipilih, tapi tidak ada akses SSH.**

Setelah langkah-langkah di sini selesai, rilis berikutnya cukup `git push` —
GitHub Actions yang mengerjakan sisanya.

---

## Ringkasnya kenapa begini

Tanpa SSH, di server tidak ada `composer`, tidak ada `npm`, dan tidak ada cara
menjalankan `php artisan`. Jadi pembagiannya:

| Dikerjakan di GitHub Actions | Dikerjakan di server |
|---|---|
| `composer install` | — |
| `npm run build` | — |
| Kirim file lewat FTP | Menerima file |
| Panggil `/__deploy/migrate` | Menjalankan migrasi |

Server cuma menerima hasil jadi. Ini juga alasan `vendor/` tidak ikut di-commit
ke repo — dia dibangun ulang tiap rilis.

---

## 1. Pilih versi PHP

cPanel → **Select PHP Version** → pilih **8.2 atau lebih baru**.

Laravel 12 tidak jalan di bawah 8.2. Sekalian aktifkan ekstensi ini:

```
mbstring   bcmath   pdo_mysql   zip   gd   intl   fileinfo   openssl
```

> **Penting:** versi yang dipilih di sini harus sama dengan `php-version` di
> `.github/workflows/deploy.yml`. Kalau di cPanel 8.3 tapi di workflow 8.2,
> `vendor/` yang dibangun bisa tidak cocok.

---

## 2. Buat subdomain — jangan pakai domain utama

cPanel → **Domains** → **Create A New Domain**

| Isian | Nilai |
|---|---|
| Domain | `app.ptscki.co.id` |
| Document Root | `/home/USERNAME/feedlot/public` |

**Hilangkan centang** "Share document root". Perhatikan `/public` di akhir —
itu kuncinya.

Kenapa subdomain: Laravel butuh document root menunjuk ke folder `public/`,
sementara sisa aplikasinya harus berada di luar jangkauan web. Di domain utama,
`public_html` terkunci dan kamu harus membongkar `index.php` bawaan Laravel —
bisa, tapi bongkarannya hilang tiap kali rilis dan harus diulang terus.

Struktur akhirnya:

```
/home/USERNAME/
├── feedlot/              ← seluruh aplikasi, TIDAK bisa diakses dari web
│   ├── app/
│   ├── vendor/
│   ├── .env              ← kredensial, aman di sini
│   └── public/           ← HANYA folder ini yang terbuka
└── public_html/          ← biarkan untuk company profile
```

---

## 3. Buat database

cPanel → **MySQL Databases**

1. Buat database, mis. `feedlot`
2. Buat user beserta passwordnya
3. **Add User To Database** → centang **ALL PRIVILEGES**

Nama sebenarnya selalu dapat awalan akun, jadi `feedlot` menjadi
`sckxxxxx_feedlot`. Catat nama lengkapnya — itu yang dipakai di `.env`.

---

## 4. Pasang SSL

cPanel → **SSL/TLS Status** → pilih subdomain → **Run AutoSSL**

Hostingmu sudah termasuk SSL gratis Grade A+. Tunggu sampai statusnya hijau
sebelum lanjut, karena `APP_URL` memakai `https://`.

---

## 5. Buat `.env` di server

File Manager → masuk ke `/home/USERNAME/feedlot/` → **+ File** → beri nama
`.env` → Edit, lalu isi:

```dotenv
APP_NAME="SCK Feedlot"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://app.ptscki.co.id

APP_LOCALE=id
APP_TIMEZONE=Asia/Jakarta

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sckxxxxx_feedlot
DB_USERNAME=sckxxxxx_feedlot
DB_PASSWORD=password-database-kamu

SESSION_DRIVER=database
SESSION_LIFETIME=480
QUEUE_CONNECTION=database
CACHE_STORE=database

DEPLOY_TOKEN=
```

### Mengisi `APP_KEY`

Tidak bisa `php artisan key:generate` tanpa SSH. Ambil dari GitHub Actions:
buka tab **Actions** → jalankan workflow apa pun → atau lebih cepat, generate
di komputermu kalau ada PHP. Bentuknya `base64:` diikuti 44 karakter acak.

Kalau tidak ada PHP sama sekali, string acak 32 karakter yang di-base64 juga
sah. Yang penting **jangan pernah diganti** setelah ada data masuk — semua sesi
dan data terenkripsi ikut hangus kalau kuncinya berubah.

### Mengisi `DEPLOY_TOKEN`

Isi dengan string acak panjang, minimal 40 karakter. Ini yang mengizinkan
GitHub Actions menjalankan migrasi. Nilainya harus **sama persis** dengan
secret `DEPLOY_TOKEN` di GitHub.

Selama kosong, route `/__deploy/*` mati total dan membalas 404.

---

## 6. Isi rahasia di GitHub

Repo → **Settings** → **Secrets and variables** → **Actions** → **New repository secret**

| Nama | Contoh isi |
|---|---|
| `FTP_SERVER` | `ftp.ptscki.co.id` |
| `FTP_USERNAME` | user FTP dari cPanel |
| `FTP_PASSWORD` | password FTP |
| `FTP_PATH` | `/feedlot/` |
| `APP_URL` | `https://app.ptscki.co.id` |
| `DEPLOY_TOKEN` | sama persis dengan yang di `.env` server |

> `FTP_PATH` menunjuk ke folder aplikasi, **bukan** `public_html` dan bukan
> `feedlot/public`. Salah di sini bikin seluruh kode sumber terbuka ke publik.

Buat user FTP khusus di cPanel → **FTP Accounts**, jangan pakai user utama —
kalau kredensialnya bocor, yang bisa disentuh cuma folder itu.

---

## 7. Rilis pertama

Repo → **Actions** → **Rilis ke Hosting** → **Run workflow**

Yang terjadi berurutan: pasang dependensi → bangun aset → kirim lewat FTP →
jalankan migrasi → bangun ulang cache → cek `/up`.

Rilis pertama paling lama (mengunggah `vendor/` yang isinya ribuan file).
Berikutnya jauh lebih cepat karena hanya yang berubah yang dikirim.

### Bikin user pertama

Migrasi cuma bikin tabel, tidak mengisi data. Untuk user pertama, jalankan
seeder lewat **phpMyAdmin** atau sementara tambahkan `--seed`. Setelah bisa
login, **ganti passwordnya dari dalam aplikasi.**

---

## 8. Pasang cron

cPanel → **Cron Jobs** → **Add New Cron Job**

### Penjadwal Laravel — tiap menit

```
* * * * * /usr/local/bin/php /home/USERNAME/feedlot/artisan schedule:run >/dev/null 2>&1
```

### Pekerja antrean — tiap menit

```
* * * * * /usr/local/bin/php /home/USERNAME/feedlot/artisan queue:work --stop-when-empty --max-time=55 >/dev/null 2>&1
```

Di server biasa, `queue:work` dijaga hidup terus oleh Supervisor. Shared
hosting tidak punya itu, jadi polanya dibalik: cron membangunkan pekerja tiap
menit, dia menghabiskan antrean lalu berhenti sendiri. `--max-time=55` menjaga
supaya tidak bertabrakan dengan panggilan menit berikutnya.

> Path `/usr/local/bin/php` sering berbeda antar hosting. Cek di cPanel →
> **Select PHP Version**, atau tanya penyedia hostingmu.

---

## 9. Setelah stabil

**Kosongkan `DEPLOY_TOKEN` di `.env` server.** Route `/__deploy/*` langsung
mati. Isi lagi kalau memang mau rilis, kosongkan setelahnya.

Selama masih terisi, siapa pun yang tahu tokennya bisa menjalankan migrasi.

---

## Kalau ada masalah

| Gejala | Kemungkinan besar |
|---|---|
| Halaman putih kosong | `storage/` tidak bisa ditulis. Set izin folder `storage` dan `bootstrap/cache` ke **755** lewat File Manager |
| "500 Server Error" | `APP_KEY` kosong, atau `.env` belum ada di server |
| Kode sumber kelihatan di browser | Document Root belum menunjuk ke `/public` |
| "Access denied for user" | Nama database/user belum pakai awalan akun |
| `/__deploy/migrate` balas 404 | `DEPLOY_TOKEN` kosong di server, atau beda dengan secret di GitHub |
| CSS berantakan | `npm run build` gagal, atau folder `public/build` tidak terkirim |
| Perubahan `.env` tidak berpengaruh | Cache konfigurasi belum dibangun ulang — panggil `/__deploy/optimize` |

Untuk melihat detail error tanpa SSH: buka
`/home/USERNAME/feedlot/storage/logs/` lewat File Manager. Jangan menyalakan
`APP_DEBUG=true` di server yang dipakai orang — halaman error Laravel
menampilkan isi `.env`, termasuk password database.
