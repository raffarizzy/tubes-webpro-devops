# Analisis Ancaman STRIDE: Medcom (SpareHub)

Dokumen ini disusun dari pembacaan kode pada branch `devops-uts`. Setiap baris "Bukti" menunjuk
berkas atau perilaku yang bisa diperiksa ulang. Tingkat risiko adalah penilaian tim, bukan hasil
pengukuran, dan perlu ditinjau bersama dosen.

## Ruang lingkup

Aplikasi marketplace suku cadang dengan dua lapisan:

- Laravel 12 (PHP 8.3): tampilan, autentikasi, dan proxy ke Node API.
- `node-api` (Express): akses data MySQL untuk produk, keranjang, pesanan, dan ulasan.
- Layanan luar: gateway pembayaran Duitku (callback), penyimpanan gambar profil.

Aliran data utama: pengguna (browser) -> Laravel -> Node API -> MySQL, serta Duitku -> Laravel (callback).
Batas kepercayaan ada di antara browser dan Laravel, Laravel dan Node API, serta Duitku dan Laravel.

## Tabel STRIDE

| No | Kategori | Ancaman spesifik | Komponen | Risiko | Mitigasi |
|----|----------|------------------|----------|--------|----------|
| 1 | Spoofing | Penyerang memanggil Node API langsung tanpa melalui Laravel dan memakai `user_id` milik orang lain. Node API tidak memeriksa token dan hanya mempercayai `user_id` dari body atau path (`history.controller.js`, `checkout`). | `node-api` | High | Node API hanya menerima permintaan dari Laravel (secret bersama atau jaringan privat), dan `user_id` diambil dari sesi Laravel, bukan dari input klien. |
| 1b | Spoofing | Callback pembayaran palsu yang mengaku dari Duitku. | `DuitkuCallbackController` | Low | Sudah ada verifikasi `md5(merchantCode + amount + merchantOrderId + apiKey)`. Perbandingan memakai `!==`; disarankan `hash_equals` untuk menghindari timing attack. |
| 2 | Tampering | `OrderProxyController::createOrder` meneruskan `user_id`, `alamat_id`, dan `items` dari request tanpa validasi, sehingga klien bisa membuat pesanan atas nama pengguna lain. Harga tidak bisa dimanipulasi karena Node API membaca harga dari tabel `products` (`checkout.service.js`). | `OrderProxyController` | High | Validasi input dan ambil `user_id` dari `auth()->id()`, bukan dari request. |
| 2b | Tampering | Status pesanan diubah melompati tahap. | `TokoController` | Medium | Aturan transisi terpusat di `Order::canTransitionTo` dan diuji (`OrderTransitionTest`). |
| 3 | Repudiation | Penjual atau pembeli menyangkal aksi (terima, tolak, kirim pesanan). Perubahan status tidak mencatat siapa pelakunya. | `TokoController`, `OrderController` | Medium | Catat pelaku dan waktu setiap perubahan status (tabel audit atau log terstruktur). Sudah ada 72 pemanggilan `Log::` namun belum terstruktur. |
| 4 | Information Disclosure | `OrderProxyController` mencatat seluruh `$request->all()` dan isi respons ke log, termasuk data alamat dan pesanan. | `OrderProxyController` | High | Jangan catat body penuh; catat ID saja dan sensor data pribadi. |
| 4b | Information Disclosure | Pesan pengecualian internal dikirim ke klien (`'error' => $e->getMessage()`). | `OrderProxyController` | Medium | Kembalikan pesan umum ke klien; detail hanya di log server. |
| 4c | Information Disclosure | Pengguna login yang sudah mengganti `{userId}` pada `/api/node/orders/user/{userId}` dapat membaca riwayat pesanan orang lain: route berada di grup `auth`, tetapi `getUserOrders` tidak membandingkan `userId` dengan pengguna yang login, dan Node API juga tidak memeriksa kepemilikan. | `OrderProxyController`, `history.controller.js` | High | Abaikan `userId` dari URL dan pakai `auth()->id()`, atau tolak bila tidak sama. |
| 5 | Denial of Service | Node API tidak membatasi laju permintaan, dan `cors()` terbuka untuk semua origin. | `node-api/src/app.js` | Medium | Batasi laju (`express-rate-limit`) dan batasi origin CORS ke domain aplikasi. Login Laravel sudah dibatasi lewat `RateLimiter`. |
| 6 | Elevation of Privilege | Penjual mengakses atau mengubah pesanan toko lain. | `TokoController` | Medium | Sudah ada pemeriksaan kepemilikan produk pada `acceptOrder`, `rejectOrder`, dan `shipOrder`. Perlu diuji otomatis untuk kasus toko lain. |
| 6b | Elevation of Privilege | Endpoint pembayaran bebas CSRF (`api/duitku/callback`) dapat dipicu pihak lain. | `bootstrap/app.php` | Low | Pengecualian CSRF memang diperlukan untuk callback, dan dilindungi verifikasi signature (lihat 1b). |

## Pengelolaan credential dan secret

- `.env` tidak masuk repositori (`.gitignore`), dan `.env.example` hanya berisi placeholder kosong.
- Pencarian pola password, secret, api key, dan token pada kode PHP, JS, dan JSON tidak menemukan
  credential yang di-hardcode (pemeriksaan manual pada 2026-11; ulangi sebelum dikumpulkan).
- Kunci Duitku, kredensial database Node API, dan token SonarCloud disimpan sebagai **GitHub Secrets**
  (`SONAR_TOKEN`) atau variabel lingkungan server, tidak pernah di kode.
- Catatan jujur: `vendor.zip` (sekitar 30 MB) ikut di-commit ke repositori. Isinya dependency, bukan
  secret, tetapi sebaiknya dihapus dari riwayat karena membengkakkan repositori.

## Dependency check

Pipeline menjalankan `composer audit --locked` dan `npm audit` pada setiap push dan PR, lalu menyimpan
hasilnya sebagai artifact `dependency-audit`.

Kondisi awal (diukur lokal sebelum perbaikan):

- `composer audit`: 15 paket rentan, 56 advisory, termasuk `laravel/framework` (critical) dan `phpunit/phpunit` (high).
- `npm audit` pada `node-api`: 15 kerentanan (1 critical, 6 high, 2 moderate, 6 low).

Tindak lanjut yang sudah dikerjakan: `npm audit fix` pada `node-api` menurunkan hasilnya menjadi
6 low (semuanya dari `jest`, dependency pengembangan, dan perbaikannya butuh pembaruan mayor).
Kerentanan Composer belum diperbaiki karena membutuhkan pembaruan versi `laravel/framework` yang perlu
diuji; ini dicatat sebagai pekerjaan lanjutan.

## Branch protection dan code review

Aturan ini diatur di GitHub (Settings > Branches), bukan di kode. Pengaturan yang disarankan untuk
branch `main`:

- Require a pull request before merging, dengan minimal 1 approval.
- Require status checks to pass: `Laravel (build + Pest)`, `Node API (Jest)`, `SonarCloud`.
- Larang push langsung ke `main`.

Status: **belum diverifikasi**. Pengaturan ini harus dipasang oleh admin repositori dan dibuktikan dengan
tangkapan layar serta riwayat PR yang direview.
