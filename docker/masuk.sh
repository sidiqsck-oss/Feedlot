#!/bin/sh
# Yang dijalankan tiap kontainer mulai.
#
# Di cPanel langkah-langkah ini dipicu GitHub Actions lewat route /__deploy
# karena tidak ada shell. Di sini shell-nya ada, jadi dikerjakan langsung —
# lebih sedikit yang bisa salah.
set -e

echo "→ Menyiapkan aplikasi…"

# APP_KEY wajib ada. Kalau kosong, dibuat sekali lalu HARUS disalin ke
# environment Render — kunci yang berubah tiap deploy bikin semua sesi
# pengguna dan data terenkripsi jadi tidak terbaca.
if [ -z "${APP_KEY}" ]; then
    echo "!! APP_KEY kosong. Jalankan 'php artisan key:generate --show' lalu"
    echo "   pasang hasilnya sebagai environment variable APP_KEY di Render."
    exit 1
fi

# Sertifikat TLS database.
#
# PDO menerima MYSQL_ATTR_SSL_CA sebagai PATH, bukan isi sertifikat. Isinya
# tidak bisa disimpan sebagai berkas di repo (rahasia) dan disk Render pun
# sementara, jadi isinya dititipkan lewat environment lalu ditulis ke berkas
# di sini — sebelum config:cache, supaya nilainya ikut terekam.
if [ -n "${DB_SSL_CA_CERT}" ]; then
    printf '%s\n' "${DB_SSL_CA_CERT}" > /var/www/storage/ca.pem
    chmod 600 /var/www/storage/ca.pem
    chown www-data:www-data /var/www/storage/ca.pem
    export MYSQL_ATTR_SSL_CA=/var/www/storage/ca.pem
    echo "→ Sertifikat TLS database dipasang."
fi

# Nginx tidak bisa membaca variabel environment sendiri.
envsubst '${PORT}' < /etc/nginx/nginx.conf > /tmp/nginx.conf
mv /tmp/nginx.conf /etc/nginx/nginx.conf

# Disk Render bersifat sementara, jadi struktur folder storage dibangun ulang
# tiap kali — Laravel akan gagal kalau salah satunya hilang.
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/app/public \
         storage/logs
chown -R www-data:www-data storage bootstrap/cache

# Cache dibuat saat mulai, bukan saat build: isinya bergantung pada
# environment variable yang baru ada di sini.
php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan storage:link 2>/dev/null || true

if [ "${JALANKAN_MIGRASI:-1}" = "1" ]; then
    echo "→ Menjalankan migrasi…"
    php artisan migrate --force
fi

echo "→ Siap. Melayani di port ${PORT}."

exec supervisord -c /etc/supervisord.conf
