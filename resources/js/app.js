import './bootstrap';

import Alpine from 'alpinejs';

// Alpine dipilih ketimbang Livewire/Inertia karena semua interaksi di aplikasi
// ini sifatnya lokal di satu form — menambah/menghapus baris barang, menghitung
// subtotal. Tidak ada yang butuh bolak-balik ke server, jadi tidak perlu
// membebani hosting yang memorinya terbatas.
window.Alpine = Alpine;
Alpine.start();

// Chart.js dibundel lewat Vite, bukan ditarik dari CDN. Hosting tujuan
// dipakai dari lapangan yang koneksinya tidak selalu bagus, dan grafik yang
// gagal muat karena CDN tidak terjangkau bikin dashboard terlihat rusak.
import {
    Chart,
    BarController, BarElement,
    LineController, LineElement, PointElement,
    CategoryScale, LinearScale,
    Tooltip, Legend,
} from 'chart.js';

Chart.register(
    BarController, BarElement,
    LineController, LineElement, PointElement,
    CategoryScale, LinearScale,
    Tooltip, Legend,
);

Chart.defaults.font.family = getComputedStyle(document.documentElement)
    .getPropertyValue('font-family') || 'sans-serif';
Chart.defaults.font.size = 11;
Chart.defaults.color = '#6c7f75';

window.Chart = Chart;
