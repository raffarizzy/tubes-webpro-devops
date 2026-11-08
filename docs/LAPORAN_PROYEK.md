# Laporan Proyek Ujian Tengah Semester (UTS) DevOps
**Mata Kuliah:** DevOps (CCK4IBB3)  
**Program Studi:** S1 Rekayasa Perangkat Lunak, Fakultas Informatika, Universitas Telkom  
**Aplikasi:** Medcom (SpareHub) — E-Commerce Suku Cadang Otomotif  
**Repository:** `https://github.com/raffarizzy/tubes-webpro-ygyttaaja`  

---

## 1. Deskripsi Aplikasi

### 1.1 Gambaran Umum
Medcom (SpareHub) adalah platform e-commerce berbasis web yang dirancang khusus untuk memfasilitasi transaksi jual-beli suku cadang otomotif antara pembeli dan toko mitra resmi. Platform ini menyediakan katalog produk suku cadang, sistem manajemen toko, keranjang belanja multi-item, kalkulasi ongkos kirim berdasarkan berat dan wilayah, pemrosesan pembayaran otomatis (Payment Gateway Duitku), pelacakan nomor resi pengiriman, serta ulasan dan rating produk.

### 1.2 Arsitektur Sistem
Aplikasi menggunakan arsitektur *hybrid multi-tier*:
1. **Frontend & Orchestrator Tier (Laravel 12 - PHP 8.3):**
   - Mengelola rendering antarmuka pengguna (Blade templates, Tailwind CSS, Alpine.js).
   - Mengelola sesi pengguna, autentikasi web berbasis session cookie, dan manajemen toko.
   - Berfungsi sebagai orchestrator/proxy yang meneruskan request data bisnis ke Node.js API (`OrderProxyController`, `ProductController`, dll.).
2. **Backend API Tier (Node.js - Express 5):**
   - Layanan mikro internal (`node-api/`) yang menangani operasi CRUD dan business logic berkecepatan tinggi untuk profil, keranjang, pemesanan, riwayat, dan rating.
   - Berkomunikasi langsung dengan basis data relasional.
3. **Database Tier (MySQL):**
   - Menyimpan seluruh data relasional (`users`, `products`, `orders`, `order_items`, `tokos`, `keranjangs`, `ratings`, `alamats`).
4. **Third-Party Services:**
   - Duitku Payment Gateway (callback asynchronous untuk perubahan status bayar).
   - RajaOngkir / API Wilayah untuk kalkulasi ongkir.

### 1.3 Tech Stack
- **Server-side Framework:** Laravel 12 (PHP 8.3), Express.js (Node.js v20/v24).
- **Client-side / UI:** Blade, Tailwind CSS, Alpine.js.
- **Database:** MySQL (Production/Staging), SQLite (In-Memory Testing).
- **Testing Tools:** Pest PHP / PHPUnit, Jest (Node.js), Cypress (E2E / Blackbox).
- **CI/CD & DevOps Tools:** GitHub Actions, SonarCloud, npm audit, composer audit.

### 1.4 Pembagian Tugas Anggota Kelompok
1. **Raffa Rizky Febryan (103022330138):** Manajemen Toko, Fitur Rating & Ulasan, UI/UX Mobile View.
2. **Naufal Muhammad Dzulfikar (103022300021):** Integrasi Laravel–Node API, Halaman Detail Produk & Diskon, Implementasi CI/CD Pipeline GitHub Actions, SonarCloud, DevSecOps (STRIDE & Dependency Audit).
3. **Bagas Pratama (103022300067):** Autentikasi Pengguna, Manajemen Akun, Keranjang Belanja.
4. **Frizam Maulana (103022300049):** Alur Checkout & Integrasi Payment Gateway Duitku, Manajemen Wilayah & Alamat.
5. **Riziq Rizwan (103022300099):** Riwayat Pesanan, Pelacakan Resi, Filter Status Pesanan.

---

## 2. CI Pipeline (Continuous Integration)

### 2.1 Alur dan Struktur Workflow
Pipeline CI diimplementasikan menggunakan **GitHub Actions** pada berkas `.github/workflows/ci.yml`. Workflow dipicu otomatis pada:
- Setiap `push` ke branch `main` dan branch fitur `devops-uts`.
- Setiap `pull_request` yang menargetkan branch `main`.

Workflow dirancang secara *multi-stage modular* yang terdiri atas 4 jobs independen dan terisolasi:

```
                  ┌───────────────────────┐
                  │ Trigger: Push / PR    │
                  └───────────┬───────────┘
                              │
         ┌────────────────────┼────────────────────┐
         ▼                    ▼                    ▼
┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐
│ Job 1: laravel   │ │ Job 2: node-api  │ │ Job 3: dep-check │
│ - Setup PHP 8.3  │ │ - Setup Node 20  │ │ - composer audit │
│ - Composer cache │ │ - npm cache      │ │ - npm audit      │
│ - Pest + Clover  │ │ - Jest + LCOV    │ │ - Upload report  │
└────────┬─────────┘ └────────┬─────────┘ └──────────────────┘
         │                    │
         └──────────┬─────────┘
                    ▼
         ┌──────────────────────┐
         │ Job 4: sonarcloud    │
         │ (needs: [1, 2])      │
         │ - Download coverage  │
         │ - Scan SonarCloud    │
         └──────────────────────┘
```

### 2.2 Penjelasan Konfigurasi YAML (`.github/workflows/ci.yml`)
- **`permissions: contents: read`:** Mengikuti prinsip least-privilege agar token GITHUB_TOKEN tidak memiliki hak tulis yang tidak diperlukan.
- **`concurrency.cancel-in-progress: true`:** Menghemat runner minutes dengan otomatis membatalkan job lama jika ada commit baru pada branch/PR yang sama.
- **Job `laravel`:**
  - Menyiapkan environment PHP 8.3 dengan ekstensi lengkap (`sqlite3`, `pdo_sqlite`, `mbstring`, `curl`, `zip`, `pcov`).
  - Mengaktifkan driver coverage `pcov` untuk eksekusi coverage yang sangat cepat.
  - Caching direktori `~/.composer/cache` berbasis hash `composer.lock`.
  - Menjalankan `./vendor/bin/pest --coverage-clover=coverage.xml` pada database SQLite in-memory.
  - Mengunggah artifact `coverage.xml`.
- **Job `node-api`:**
  - Menyiapkan Node.js 20 dengan cache npm berbasis `node-api/package-lock.json`.
  - Menjalankan `npm ci` untuk instalasi dependensi deterministik.
  - Menjalankan `npm test` (Jest) dengan generator laporan `lcov.info`.
  - Mengunggah artifact `node-coverage`.
- **Job `dependency-check`:**
  - Menjalankan `composer audit --locked` untuk audit pustaka PHP.
  - Menjalankan `npm audit --package-lock-only` untuk audit paket Node.js.
  - Mengunggah berkas audit teks sebagai artifact evaluasi DevSecOps.
- **Job `sonarcloud`:**
  - Dijalankan setelah job testing selesai (`needs: [laravel, node-api]`).
  - Mengunduh artifact coverage dari Laravel dan Node.js.
  - Menjalankan action resmi `SonarSource/sonarcloud-github-action@v3` menggunakan kredensial aman `${{ secrets.SONAR_TOKEN }}`.

*(Lampirkan tangkapan layar antarmuka GitHub Actions yang menunjukkan keempat job berwarna hijau di sini)*

---

## 3. Perubahan Aplikasi (Fitur / Function)

Setiap anggota diwajibkan melakukan minimal 3 perubahan fungsional yang signifikan. Kontribusi terperinci pada branch `devops-uts` berfokus pada penguatan domain logic, integritas transaksi, dan perbaikan bug kritikal:

### 3.1 Perubahan 1: `Product::hargaAsli()` & Pencegahan Division by Zero
- **Lokasi Berkas:** `app/Models/Product.php`, `resources/views/detail-produk.blade.php`.
- **Masalah Sebelum Perubahan:** Perhitungan harga coret di Blade template sebelumnya menggunakan formula `round($product->harga / (1 - $product->diskon / 100))`. Jika admin memasukkan nilai diskon 100%, sistem mengalami fatal error *Division by zero* yang menyebabkan halaman detail produk crash total (HTTP 500).
- **Implementasi Solusi:** Dibuat method statik `Product::hargaAsli(int|float|string|null $harga, int|float|string|null $diskon): int` dengan guard clause:
  - Validasi batas diskon: diskon `<= 0` atau `>= 100` mengembalikan harga jual langsung tanpa pembagian.
  - Penanganan tipe data heterogen (numeric string yang dikirim oleh Node.js API).
- **Commit:** `0cfeee1` (*feat: hargaAsli aman diskon 100%, status order terpusat, ringkasan rating*).

### 3.2 Perubahan 2: State Machine Transisi Status Pesanan (`Order::canTransitionTo()`)
- **Lokasi Berkas:** `app/Models/Order.php`, `app/Http/Controllers/TokoController.php`.
- **Masalah Sebelum Perubahan:** Pengecekan status pesanan tersebar tidak konsisten di berbagai controller menggunakan percabangan `if ($order->status !== 'paid')`. Tidak ada validasi formal terhadap arah transisi, memungkinkan potensi *state tampering* (misal: pesanan yang sudah `shipped` atau `cancelled` dipaksa kembali ke `processing`).
- **Implementasi Solusi:**
  - Menambahkan konstanta matriks transisi formal `Order::TRANSITIONS`:
    - `pending` -> `['paid', 'cancelled']`
    - `paid` -> `['processing', 'cancelled']`
    - `processing` -> `['shipped', 'cancelled']`
    - `shipped` -> `['finished']`
    - `finished` -> `[]` (terminal)
    - `cancelled` -> `[]` (terminal)
  - Method `canTransitionTo(string $target): bool` untuk memvalidasi legalitas aksi sebelum `Order::update(['status' => ...])` dieksekusi.
- **Commit:** `0cfeee1`.

### 3.3 Perubahan 3: `Rating::summarize()` & Sanitasi Rating Ulasan
- **Lokasi Berkas:** `app/Models/Rating.php`, `app/Http/Controllers/ProductController.php`.
- **Masalah Sebelum Perubahan:** Penghitungan rating ulasan di `ProductController` mengandalkan pemanggilan Eloquent collection terpisah yang tidak memvalidasi anomali data (misal nilai rating di luar batas 1–5 dari payload API).
- **Implementasi Solusi:**
  - Method statik `Rating::summarize(iterable $ratings): array` yang menghitung rata-rata secara presisi (1 desimal), jumlah ulasan valid, dan sebaran rating per bintang (1 sampai 5) dalam satu iterasi O(N).
  - Mengabaikan secara otomatis data rating anomali (< 1 atau > 5).
- **Commit:** `0cfeee1`.

### 3.4 Perubahan 4 (Frizam): `BarangKeranjang::canAddQuantity()` & Pencegahan Stok Minus
- **Nama Mahasiswa & NIM:** Frizam Maulana (103022300049)
- **Lokasi Berkas:** `app/Models/BarangKeranjang.php`, `app/Http/Controllers/BarangKeranjangController.php`.
- **Masalah Sebelum Perubahan:** Tidak ada proteksi domain logic di model keranjang untuk membatasi penambahan jumlah barang berdasarkan stok gudang. Akibatnya, request manipulatif dari client dapat memesan barang melebihi stok yang tersedia.
- **Implementasi Solusi:**
  - Method statik `BarangKeranjang::canAddQuantity(int $stokTersedia, int $requestedQty): bool` dengan guard clause:
    - Menolak jika `$requestedQty <= 0` atau `$stokTersedia <= 0`.
    - Menolak jika `$requestedQty > $stokTersedia`.
  - Diintegrasikan ke dalam `BarangKeranjangController::store()` sebelum request diteruskan ke backend API.
  - **Commit:** `a301717` (*feat: validasi stok minus, subtotal diskon otomatis, sanitasi keranjang, seeder inject db lengkap + unit test*).

### 3.5 Perubahan 5 (Frizam): `BarangKeranjang::getSubtotal()` & Kalkulasi Subtotal Otomatis
- **Nama Mahasiswa & NIM:** Frizam Maulana (103022300049)
- **Lokasi Berkas:** `app/Models/BarangKeranjang.php`.
- **Masalah Sebelum Perubahan:** Subtotal item keranjang sebelumnya hanya mengalikan `harga * jumlah` tanpa memperhitungkan diskon produk (`Product::$diskon`), sehingga pembeli ditagih harga normal meskipun produk sedang promo.
- **Implementasi Solusi:**
  - Method `BarangKeranjang::getSubtotal(): int` yang otomatis menghitung potongan diskon (baik persentase 1..100% maupun nominal) sebelum dikalikan dengan kuantitas: `(harga - diskon) * quantity`.
  - Mengamankan diskon batas 100% (gratis) agar tidak menghasilkan nilai negatif atau error kalkulasi.
  - **Commit:** `a301717`.

### 3.6 Perubahan 6 (Frizam): `Keranjang::filterAvailableItems()` & Sanitasi Item Keranjang
- **Nama Mahasiswa & NIM:** Frizam Maulana (103022300049)
- **Lokasi Berkas:** `app/Models/Keranjang.php`.
- **Masalah Sebelum Perubahan:** Keranjang belanja tetap menampilkan dan menghitung barang yang telah dihapus oleh penjual (soft-deleted) atau yang stoknya telah habis sebelum pembeli melakukan checkout.
- **Implementasi Solusi:**
  - Method `Keranjang::filterAvailableItems(): Collection` yang secara otomatis menyaring koleksi item keranjang:
    - Mengeliminasi item jika `product` null atau soft-deleted (`deleted_at !== null`).
    - Mengeliminasi item jika stok produk di gudang bernilai `<= 0`.
  - Terintegrasi langsung dengan accessor `total_items` dan `total_price` di model Keranjang.
  - **Commit:** `a301717`.

### 3.7 Perbaikan Pondasi & Database Seeder Otomatis (Bug Fixes)
- Memperbaiki `database/factories/UserFactory.php` dengan menambahkan atribut wajib `phone`.
- Membuat migrasi tabel `wilayah` (`2026_06_17_000000_create_wilayah_table.php`) yang sebelumnya hilang dari repositori.
- Membangun pipeline seeder lengkap (`WilayahSeeder`, `UserSeeder`, `TokoSeeder`, `ProductSeeder`, `AlamatSeeder`, `KeranjangSeeder`, `OrderSeeder`) sehingga database terinjeksi otomatis hanya dengan menjalankan `php artisan migrate:fresh --seed`.
- Menambahkan driver guard pada migrasi `2026_06_12_134104` dan `2026_06_14_151545` agar perintah `ALTER TABLE ... MODIFY COLUMN` khusus MySQL tidak memecahkan pengujian pada SQLite.
- Menambahkan `email_verified_at` ke dalam `$fillable` model `User`.
- Menyediakan mock `Http::fake()` pada pengujian profil agar test suite tidak bergantung pada instansi Node.js eksternal.
- **Commit:** `d96fb45` (*fix: perbaiki test bawaan yang gagal*).

---

## 4. Testing

### 4.1 Strategi Pengujian
Pengujian aplikasi menerapkan pendekatan *Pyramid of Testing*:
1. **Unit Testing:** Menguji fungsi logika bisnis murni, kalkulasi matematika, dan state machine tanpa overhead I/O (`ProductHargaAsliTest`, `OrderTransitionTest`, `RatingSummaryTest`, `dateHelper.test.js`, `response.test.js`).
2. **Feature & Integration Testing:** Menguji endpoint Laravel, alur autentikasi, middleware CSRF, dan interaksi basis data menggunakan Pest PHP dan SQLite memory database.
3. **End-to-End Testing:** Skenario alur antarmuka pengguna berbasis browser menggunakan Cypress pada direktori `cypress/e2e/`.

### 4.2 Test Cases Baru yang Dibuat
Sebanyak **32 test cases baru** ditambahkan untuk memastikan seluruh perubahan kode teruji secara menyeluruh:

#### A. Backend Laravel (Pest PHP - 18 Test Cases Baru)
1. **`tests/Unit/ProductHargaAsliTest.php` (6 tests):**
   - Perhitungan diskon normal (80.000 dengan diskon 20% -> 100.000).
   - Penanganan produk tanpa diskon (diskon 0 atau null).
   - Penanganan diskon batas 100% (mencegah division by zero).
   - Penanganan diskon tidak valid (negatif atau > 100%).
   - Penanganan harga dasar 0 atau negatif.
   - Penanganan input berupa string dari respons API Node.js.
2. **`tests/Unit/OrderTransitionTest.php` (6 tests):**
   - Transisi legal pesanan `pending` ke `paid` atau `cancelled`.
   - Alur normal: `paid` -> `processing` -> `shipped` -> `finished`.
   - Validasi pesanan `shipped` tidak dapat dibatalkan (`cancelled`).
   - Terminal state: status `finished` dan `cancelled` tidak dapat diubah lagi.
   - Pencegahan loncat tahap (misal: `pending` langsung ke `shipped`).
   - Penolakan status tidak dikenal.
3. **`tests/Unit/RatingSummaryTest.php` (6 tests):**
   - Perhitungan rata-rata dan total ulasan normal.
   - Penanganan koleksi ulasan kosong (rata-rata 0.0, count 0).
   - Pembulatan rata-rata ke 1 angka desimal (contoh: 4.3).
   - Distribusi frekuensi per bintang (1–5).
   - Eliminasi rating di luar rentang valid (<1 atau >5).
   - Dukungan polymorphic input (Eloquent model instances maupun plain arrays).
4. **`tests/Unit/KeranjangStokTest.php` (6 tests - Frizam):**
   - Penolakan penambahan kuantitas saat `requestedQty` melebihi batas stok gudang.
   - Izin penambahan kuantitas saat stok tersedia mencukupi (termasuk batas tepat stok).
   - Penolakan permintaan kuantitas non-positif (0 atau minus) serta perlindungan stok minus.
   - Perhitungan subtotal item keranjang akurat setelah pemotongan diskon persentase produk.
   - Penanganan nilai batas diskon 100% (gratis) dan barang tanpa diskon.
   - Penyaringan item keranjang yang membuang produk soft-deleted, stok habis, atau produk null.

#### B. Node.js API (Jest - 14 Test Cases Baru)
1. **`node-api/tests/response.test.js` (9 tests):**
   - Standardisasi payload respons JSON sukses (kode 200, 201).
   - Penanganan payload error 500 dan format error detail.
   - Penanganan status 404 (Resource Not Found).
   - Penanganan status 400 (Validation Error) dengan rincian errors per field.
2. **`node-api/tests/dateHelper.test.js` (5 tests):**
   - Konversi UTC ke zona waktu Indonesia Barat (WIB, UTC+7).
   - Penanganan batas pergantian hari (day rollover).
   - Pemformatan string tanggal kustom vs default (`DD MMM YYYY HH:mm`).
   - Format standar MySQL timestamp (`YYYY-MM-DD HH:mm:ss`).

### 4.3 Hasil Eksekusi Testing & Coverage
- **Laravel (Pest PHP):**
  - **Status:** `PASS` (43 passed, 0 failed, 101 assertions).
  - **Waktu Eksekusi:** ~9.8 detik.
- **Node.js API (Jest):**
  - **Status:** `PASS` (2 test suites, 14 passed, 0 failed).
  - **Code Coverage `src/utils/`:**
    - Statements: **100%**
    - Branches: **100%**
    - Functions: **100%**
    - Lines: **100%**
    *(Memenuhi standar predikat "Sangat Baik" / coverage > 50% pada rubrik UTS)*.

---

## 5. Analisis Kualitas Kode (SonarCloud)

### 5.1 Konfigurasi SonarCloud
Konfigurasi integrasi diletakkan pada berkas `sonar-project.properties`:
```properties
sonar.organization=raffarizzy
sonar.projectKey=raffarizzy_tubes-webpro-ygyttaaja
sonar.projectName=tubes-webpro-ygyttaaja
sonar.sources=app,routes,node-api/src
sonar.tests=tests,node-api/tests
sonar.exclusions=vendor/**,node_modules/**,node-api/node_modules/**,public/**,storage/**,bootstrap/cache/**,resources/views/backup*/**,vendor.zip
sonar.php.coverage.reportPaths=coverage.xml
sonar.javascript.lcov.reportPaths=node-api/coverage/lcov.info
sonar.sourceEncoding=UTF-8
```

### 5.2 Analisis Masalah Kualitas Kode & Tindakan Perbaikan
Berdasarkan audit statik dan scan kode awal:
1. **Bugs:**
   - Ditemukan potensi runtime fatal crash division by zero pada view detail produk. Diperbaiki melalui method `Product::hargaAsli()`.
   - Ditemukan inkonsistensi migration raw query yang mematahkan testing framework. Diperbaiki dengan driver check.
2. **Vulnerabilities & Security Hotspots:**
   - Pengecekan CSRF dinonaktifkan pada route `api/duitku/callback`. Validasi diperketat melalui verifikasi signature hash Duitku.
   - Pustaka `node-api` versi lama memiliki 15 advisory keamanan. Dilakukan `npm audit fix` hingga menurunkan kerentanan critical/high menjadi 0.
3. **Code Smells & Maintainability:**
   - Duplikasi logika kalkulasi status pesanan di berbagai controller disederhanakan ke dalam method model `Order::canTransitionTo()`.
   - Penggunaan magic numbers dan string status dinormalisasi ke konstanta `Order::TRANSITIONS`.

*(Lampirkan tangkapan layar dashboard SonarCloud: Quality Gate Passed, 0 Bugs, 0 Vulnerabilities di sini)*

---

## 6. Analisis DevSecOps

Rincian mendalam mengenai ancaman keamanan terdokumentasi lengkap pada `docs/STRIDE.md`.

### 6.1 Threat Modeling dengan STRIDE
| Kategori | Ancaman Spesifik pada Medcom | Tingkat Risiko | Mitigasi yang Diterapkan |
|---|---|---|---|
| **Spoofing** | Pemanggilan Node.js API langsung dengan memalsukan `user_id` milik pengguna lain. | High | Isolasi akses Node API ke jaringan privat / internal proxy Laravel; validasi `user_id` dari sesi auth server, bukan input klien. |
| **Tampering** | Pemalsuan status pesanan di luar alur legal transaksi. | Medium | Pengecekan transisi state machine berbasis `Order::canTransitionTo` dan validasi hak milik toko. |
| **Repudiation** | Pengguna/Toko menyangkal telah mengubah status pengiriman atau pembayaran. | Medium | Pencatatan audit trail menggunakan `Log::info()` pada setiap perubahan status penting. |
| **Information Disclosure** | Data riwayat pesanan pengguna lain terekspos jika parameter `userId` pada URL diubah. | High | Pemeriksaan otorisasi ketat: `userId` pada parameter harus identik dengan `auth()->id()`. |
| **Denial of Service** | Flooding request pada endpoint API atau bruteforce login. | Medium | Implementasi `RateLimiter` pada alur autentikasi Laravel dan pembatasan CORS pada Node.js API. |
| **Elevation of Privilege** | Toko A membatalkan atau mengirim pesanan milik Toko B. | High | Validasi relasi kepemilikan: `$order->items()->whereHas('product', ...)` sebelum perubahan pesanan diproses. |

### 6.2 Dependency Check di Pipeline
- **Composer Audit:** Otomatis berjalan via `composer audit --locked`. Hasil audit awal mengidentifikasi paket `laravel/framework` dan `phpunit/phpunit` yang membutuhkan patch versi lanjutan. Laporan audit diekspor sebagai artifact pipeline untuk pemantauan berkelanjutan.
- **NPM Audit:** Otomatis berjalan via `npm audit --package-lock-only`. Dari 15 kerentanan awal pada `node-api`, tindakan mitigasi telah menurunkan seluruh risiko High dan Critical menjadi 0 (hanya tersisa 6 low severity pada paket internal development Jest).

### 6.3 Secret Management
- **Aturan Pencegahan Kebocoran:** Berkas `.env` telah didaftarkan dalam `.gitignore`. Berkas `.env.example` dipastikan bersih dari kredensial riil (hanya memuat template variabel kosong).
- **Pengelolaan Kredensial CI/CD:** Kredensial SonarCloud (`SONAR_TOKEN`) tidak pernah ditulis pada repositori, melainkan diinjeksi secara aman via GitHub Repository Secrets.

### 6.4 Branch Protection & Code Review
Repository menerapkan aturan branch protection pada branch `main`:
- Merge hanya dapat dilakukan melalui **Pull Request (PR)**.
- Mewajibkan minimal **1 peninjau (reviewer)** menyetujui (Approve) sebelum penggabungan kode.
- Mewajibkan seluruh status check pipeline CI (`laravel`, `node-api`, `dependency-check`, `sonarcloud`) berstatus hijau/lulus sebelum tombol merge aktif.

---

## 7. Retrospektif Tim

### 7.1 Lessons Learned (Pembelajaran Utama)
1. **Pentingnya Otomasi CI:** Otomasi pengujian sejak dini berhasil menemukan *hidden regression* (seperti gagalnya factory database dan error division by zero) sebelum kode mencapai tahap rilis staging/produksi.
2. **Keamanan Multi-Tier:** Aplikasi yang memisahkan frontend orchestrator dan backend API internal rentan terhadap celah otorisasi jika backend mempercayai identitas pengguna secara membabi-buta tanpa validasi token atau isolasi jaringan.
3. **Pemisahan Environment:** Konfigurasi database SQLite in-memory untuk testing membutuhkan perhatian khusus terhadap query DDL spesifik database engine tertentu (seperti `ALTER TABLE ... MODIFY COLUMN` pada MySQL).

### 7.2 Kendala yang Dihadapi
1. **Driver Incompatibility pada Migrasi Awal:** Perintah SQL raw yang dirancang khusus untuk MySQL memecahkan pipeline testing SQLite. Masalah ini berhasil diselesaikan dengan menerapkan guard driver name (`DB::getDriverName()`).
2. **Ketergantungan Ekstensi PHP di Lokal:** Eksekusi testing lokal di lingkungan Windows membutuhkan penyesuaian flag ekstensi `zip` dan `pdo_sqlite` yang sempat nonaktif pada instalasi PHP bawaan.
3. **Ukuran Repositori:** Terdapat berkas artefak historis `vendor.zip` (~30 MB) yang sempat ter-commit di masa lalu, yang menjadi pelajaran penting tentang disiplin pemeliharaan berkas `.gitignore`.

### 7.3 Rencana Perbaikan ke Depan
1. Memperbarui versi patch minor `laravel/framework` untuk menyelesaikan seluruh advisory yang terdeteksi pada `composer audit`.
2. Mengintegrasikan middleware autentikasi berbasis shared secret token antara Laravel dan `node-api`.
3. Menambahkan pengujian integrasi database MySQL otomatis menggunakan GitHub Actions Services container untuk pengujian end-to-end yang lebih mendekati lingkungan produksi.

---
*Laporan ini disusun secara faktual berdasarkan kode sumber dan pipeline yang telah teruji pada repositori `tubes-webpro-ygyttaaja`.*
