import './bootstrap';

import Alpine from 'alpinejs';

// Alpine dipilih ketimbang Livewire/Inertia karena semua interaksi di aplikasi
// ini sifatnya lokal di satu form — menambah/menghapus baris barang, menghitung
// subtotal. Tidak ada yang butuh bolak-balik ke server, jadi tidak perlu
// membebani hosting yang memorinya terbatas.
window.Alpine = Alpine;
Alpine.start();
