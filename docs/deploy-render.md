# Rilis ke Render (untuk uji coba)

Jalur ini untuk **mencoba** aplikasi sebelum hosting dari kantor tersedia.
Jalur cPanel di [`deploy-cpanel.md`](deploy-cpanel.md) tetap hidup dan tidak
terpengaruh — keduanya bisa jalan berdampingan dari repo yang sama.

Kode aplikasinya **sama persis**. Yang berbeda cuma cara naiknya.

| | cPanel | Render |
|---|---|---|
| Cara rilis | GitHub Actions kirim lewat FTPS | `git push`, Render bangun sendiri |
| Migrasi | Lewat route `/__deploy/migrate` (tidak ada SSH) | `php artisan migrate --force` saat kontainer mulai |
| Queue & scheduler | Cron cPanel | Ikut di dalam kontainer lewat supervisor |
| Database | MySQL cPanel | MySQL dari penyedia luar (mis. Aiven) |

Tiga hal pertama justru **hilang** di Render karena Render punya shell — jadi
tidak ada tambalan yang perlu dijaga.

---

## 1. Siapkan database dulu

Render tidak menyediakan MySQL, jadi databasenya diambil dari luar. Pakai
**MySQL**, bukan Postgres, supaya trial ini benar-benar mewakili cPanel nanti:
kode yang menghitung selisih hari punya cabang khusus per jenis database
(`app/Services/Cpl/KueriCpl.php`), dan MySQL adalah cabang yang sama dengan
yang dipakai cPanel.

Catat enam hal ini dari penyedia database:

```
host        mis. mysql-xxxx.aivencloud.com
port        mis. 12345          ← sering BUKAN 3306
database    mis. defaultdb
username    mis. avnadmin
password    …
sertifikat  ca.pem              ← Aiven mewajibkan TLS
```

**Pilih region yang sama dengan region Render** (Singapore kalau tersedia).
Tiap kueri bolak-balik lewat jaringan; dashboard CPL menarik banyak baris, jadi
jarak antar-region langsung terasa.

---

## 2. Buat APP_KEY

Kunci ini **harus tetap**. Kalau berubah tiap rilis, semua sesi pengguna
terputus dan data terenkripsi tidak terbaca lagi.

```bash
php artisan key:generate --show
# base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=
```

Simpan hasilnya. Jangan dibuat ulang di lain waktu.

---

## 3. Hubungkan repo ke Render

1. Render → **New** → **Blueprint**
2. Pilih repo `sidiqsck-oss/feedlot`, cabang `main`
3. Render membaca [`render.yaml`](../render.yaml) dan menyiapkan layanannya

`render.yaml` sudah mengisi sendiri yang tidak rahasia (nama, locale, zona
waktu, driver antrean). Yang bertanda `sync: false` sengaja dikosongkan dan
harus diisi manual — isinya rahasia dan tidak boleh masuk repo.

---

## 4. Isi environment variable di dasbor Render

Layanan `feedlot` → **Environment**:

| Kunci | Isi |
|---|---|
| `APP_KEY` | hasil langkah 2, lengkap dengan awalan `base64:` |
| `DB_HOST` | host dari penyedia database |
| `DB_PORT` | port dari penyedia database |
| `DB_DATABASE` | nama database |
| `DB_USERNAME` | user database |
| `DB_PASSWORD` | password database |
| `DB_SSL_CA_CERT` | **isi** berkas `ca.pem`, ditempel apa adanya termasuk baris `-----BEGIN CERTIFICATE-----` |

Kalau penyedia tidak mewajibkan TLS, `DB_SSL_CA_CERT` boleh dikosongkan.

> Catatan: PDO sendiri menerima *path* berkas, bukan isi sertifikat.
> `docker/masuk.sh` yang menuliskan isinya ke berkas saat kontainer mulai lalu
> mengarahkan `MYSQL_ATTR_SSL_CA` ke situ — jadi di dasbor Render yang ditempel
> memang isinya, bukan nama berkas.

---

## 5. Rilis pertama

Render membangun sendiri setelah environment lengkap. Yang terjadi berurutan:

1. Aset dibangun (`npm run build`)
2. Dependensi PHP dipasang tanpa paket pengembangan
3. Kontainer mulai → `docker/masuk.sh` menyiapkan folder storage,
   membuat cache, lalu menjalankan migrasi
4. Supervisor menyalakan nginx, php-fpm, pekerja antrean, dan penjadwal

Log rilis bisa dilihat langsung di dasbor Render.

### Bikin akun pertama

Belum ada pengguna sama sekali setelah migrasi. Jalankan lewat **Shell** di
dasbor Render:

```bash
php artisan db:seed --class=DatabaseSeeder
```

Seeder itu membuat akun administrator dengan kata sandi bawaan
`ubah-password-ini`. **Ganti begitu berhasil masuk.**

---

## 6. Yang perlu diketahui soal paket gratis Render

Bukan kekurangan yang bisa diakali — ini memang batas paketnya:

- **Tidur setelah 15 menit nganggur.** Permintaan pertama setelah itu menunggu
  sekitar 50 detik sampai kontainer bangun. Terasa kalau pimpinan cuma sesekali
  membuka dashboard.
- **Disk bersifat sementara.** Apa pun yang ditulis sebagai berkas hilang saat
  layanan mulai ulang. Ini **tidak** jadi masalah di sini: berkas impor dibaca
  langsung dari lokasi sementara lalu dibuang, tidak pernah disimpan; dan
  sesi, cache, serta antrean semuanya ditaruh di database — lihat `render.yaml`.
- **Pekerja antrean ikut mati saat layanan tidur.** Impor besar yang dilempar
  ke antrean baru jalan lagi begitu ada yang membuka aplikasinya.

Kalau tidurnya mengganggu saat mendemokan ke pimpinan, buka aplikasinya
beberapa menit sebelum mulai.

---

## 7. Pindah ke cPanel nanti

Tidak ada yang perlu ditulis ulang:

1. Dump database dari penyedia:
   `mysqldump -h HOST -P PORT -u USER -p NAMA_DB > feedlot.sql`
2. Impor lewat phpMyAdmin di cPanel
3. Ikuti [`deploy-cpanel.md`](deploy-cpanel.md) seperti biasa
4. Isi `DEPLOY_TOKEN` di server dan di GitHub Secrets — route rilis baru hidup
   setelah token terisi

Karena keduanya sama-sama MySQL, tidak ada konversi skema sama sekali.
