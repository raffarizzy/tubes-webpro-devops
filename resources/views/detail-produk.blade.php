@extends('layouts.main')

@section('title', 'Detail Produk - Medcom')

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .color-medcom {
          color: #122c4f !important; /* !important ensures it overrides standard Bootstrap utility specificity */
        }
        .border-color-medcom {
          border-color: #122c4f !important;
        }
        .description-container {
          background-color: #f8f9fa;
          border: 1px solid #e9ecef;
          border-radius: 12px;
          padding: 1.25rem;
          margin-top: 0.5rem;
        }
        .description-content {
          white-space: pre-line;
          overflow: hidden;
          display: -webkit-box;
          -webkit-line-clamp: 4;
          -webkit-box-orient: vertical;
          transition: all 0.3s ease;
        }
        .description-content.expanded {
          display: block;
          -webkit-line-clamp: unset;
        }
        .read-more-btn {
          color: #122c4f;
          font-weight: 700;
          cursor: pointer;
          font-size: 0.9rem;
          display: none;
          margin-top: 10px;
          text-decoration: none;
        }
        .read-more-btn:hover {
          text-decoration: underline;
        }

        /* Mobile Fix for Horizontal Scroll */
        @media (max-width: 768px) {
          body {
            overflow-x: hidden;
            width: 100%;
          }
          .container {
            overflow-x: hidden;
            padding-left: 15px !important;
            padding-right: 15px !important;
          }
          .row {
            margin-left: 0 !important;
            margin-right: 0 !important;
          }
          /* Fix for large gutters on mobile */
          .g-5 {
            --bs-gutter-x: 1rem !important;
          }
        }
    </style>
@endpush

@section('content')
  <!-- KONTEN -->
  <div class="container py-5">
    <div class="row g-5 justify-content-center">
      <!-- GAMBAR PRODUK -->
      <div class="col-md-6 d-flex justify-content-center align-items-center">
          @php
            $imagePath = $product->imagePath ?? 'produk/default.png';
            if (!$imagePath) {
                $imagePath = 'produk/default.png';
            }

            if (!str_starts_with($imagePath, 'http')) {
              if (str_starts_with($imagePath, '/storage/')) {
              } elseif (str_starts_with($imagePath, 'storage/')) {
                $imagePath = '/' . $imagePath;
              } elseif (str_starts_with($imagePath, 'produk/') || str_starts_with($imagePath, 'images/')) {
                $imagePath = '/storage/' . $imagePath;
              } elseif (str_starts_with($imagePath, '/img/') || str_starts_with($imagePath, 'img/')) {
                if (!str_starts_with($imagePath, '/')) {
                  $imagePath = '/' . $imagePath;
                }
              } else {
                $imagePath = '/storage/' . $imagePath;
              }
            }
          @endphp

          <img
            id="product-image"
            src="{{ $imagePath }}"
            alt="{{ $product->nama }}"
            class="img-fluid rounded shadow mx-auto d-block"
            style="max-width: 500px; width: 100%; height: auto; object-fit: contain;"
          />
        </div>

      <!-- DETAIL PRODUK -->
      <div class="col-md-6">
        <h3 id="product-name" class="fw-bold color-medcom">{{ $product->nama }}</h3>

        <!-- Harga -->
        <div class="d-flex align-items-center gap-2 my-3">
          <span id="product-price" class="fw-bold fs-4 color-medcom">Rp {{ number_format($product->harga, 0, ',', '.') }}</span>
          @if($product->diskon && $product->diskon > 0)
            @php
              $hargaAsli = round($product->harga / (1 - $product->diskon / 100));
            @endphp
            <span id="product-original-price" class="text-muted text-decoration-line-through">Rp {{ number_format($hargaAsli, 0, ',', '.') }}</span>
            <span id="product-discount" class="badge bg-danger">-{{ $product->diskon }}%</span>
          @endif
        </div>

        <p><strong>Stok:</strong> <span id="product-stok">{{ $product->stok }}</span> Tersedia</p>
        <p><strong>Berat:</strong> <span id="product-berat">{{ $product->berat ?? 1000 }}</span> Gram</p>

        <!-- Quantity -->
        <div class="p-3 bg-body-secondary rounded">
          <p class="fw-semibold text-primary mb-2">Jumlah:</p>

          <div class="d-flex align-items-center gap-3">
            <button id="btn-decrease" class="btn btn-outline-primary fw-bold px-3">
              -
            </button>

            <span id="quantity-display" class="fw-bold fs-5 px-3 py-1 bg-white rounded shadow-sm">
              1
            </span>

            <button id="btn-increase" class="btn btn-outline-primary fw-bold px-3">
              +
            </button>
          </div>

          <p class="mt-3">
            <strong>Total:</strong>
            <span id="total-price" class="fw-bold text-primary" data-price="{{ $product->harga }}">Rp {{ number_format($product->harga, 0, ',', '.') }}</span>
          </p>
        </div>

        <!-- Info Toko (Clickable) -->
        <a href="{{ route('toko.show', $product->toko->id) }}" class="text-decoration-none d-block">
          <div class="d-flex align-items-center gap-3 p-3 bg-white rounded shadow-sm my-3 border border-transparent hover-shadow transition" style="cursor: pointer;">
            @php
              $tokoLogo = $product->toko->logo_path ?? '/img/iconPengguna.png';
              if (!str_starts_with($tokoLogo, 'http') && !str_starts_with($tokoLogo, '/img/')) {
                if (str_starts_with($tokoLogo, 'toko/')) {
                  $tokoLogo = '/storage/' . $tokoLogo;
                } elseif (!str_starts_with($tokoLogo, '/storage/')) {
                  $tokoLogo = '/storage/' . $tokoLogo;
                }
              }
            @endphp

            <div class="flex-shrink-0">
              <img
                src="{{ $tokoLogo }}"
                alt="{{ $product->toko->nama_toko ?? 'Toko' }}"
                class="rounded-circle border border-2 border-color-medcom"
                style="width: 60px; height: 60px; object-fit: cover;"
                onerror="this.src='/img/iconPengguna.png'"
              />
            </div>

            <div>
              <p id="toko-nama" class="fw-bold color-medcom m-0 d-flex align-items-center gap-1">
                {{ $product->toko->nama_toko ?? '-' }}
                @if($product->toko->is_verified_seller)
                  <i class="bi bi-patch-check-fill text-primary" style="font-size: 0.9rem;" title="Verified Seller"></i>
                @endif
              </p>
              <p id="toko-lokasi" class="text-muted small m-0">
                <i class="bi bi-geo-alt-fill me-1"></i>{{ $product->toko->lokasi ?? '-' }}
              </p>
              <small class="text-primary mt-1 d-block">Lihat Toko <i class="bi bi-chevron-right ms-1"></i></small>
            </div>
          </div>
        </a>

        <style>
          .hover-shadow:hover {
            box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
            border-color: #122c4f !important;
          }
          .transition {
            transition: all 0.2s ease-in-out;
          }
        </style>

        <!-- Deskripsi -->
        <div class="mt-4">
          <h5 class="color-medcom fw-semibold">Deskripsi</h5>
          <div class="description-container">
            <p id="product-description" class="text-muted mb-0 description-content">{{ $product->deskripsi }}</p>
            <a href="javascript:void(0)" id="btn-read-more" class="read-more-btn">Baca Selengkapnya</a>
          </div>
        </div>

        <!-- Rating -->
        <div class="d-flex align-items-center gap-2 mt-3">
          <span class="text-warning fs-3">★</span>
          <span id="avg-rating" class="fw-bold text-warning">{{ number_format($avgRating, 1) }}</span>
          <span id="rating-count" class="text-muted">({{ $ratingCount }} ulasan)</span>
        </div>

        <!-- Aksi -->
        <div class="d-flex gap-3 mt-4">
          <button id="btn-Keranjang" class="btn btn-dark w-50">
            Tambah ke Keranjang
          </button>
          <button id="btn-Beli" class="btn btn-success w-50">
            Beli Sekarang
          </button>
        </div>
      </div>
    </div>

    <!-- Ulasan -->
    <div class="mt-5">
      <h4 class="color-medcom fw-bold mb-3">Ulasan Pengguna</h4>
      <div id="reviews-list" class="row g-3">
        @forelse($ratings as $rating)
          <div class="col-12 col-md-6 col-lg-4">
            <div class="card shadow-sm h-100">
              <div class="card-body d-flex">
                <!-- Icon -->
                <div class="me-3 d-flex align-items-start">
                  <img src="{{ $rating->user->pfpPath }}" id="iconPengguna" alt="User Icon" class="rounded-circle"/>
                </div>

                <!-- Content -->
                <div>
                  <p class="fw-semibold mb-1 text-dark">{{ $rating->user?->getMaskedName() ?? 'Anonymous' }}</p>

                  <div class="text-warning fw-bold" style="font-size: 14px;">
                    @for($i = 1; $i <= 5; $i++)
                      @if($i <= $rating->rating)
                        ★
                      @else
                        ☆
                      @endif
                    @endfor
                  </div>

                  <p class="mb-1">{{ $rating->review ?? '-' }}</p>

                  <small class="text-muted">{{ $rating->created_at->format('Y-m-d') }}</small>
                </div>
              </div>
            </div>
          </div>
        @empty
          <p style="color: #999; text-align: center;">Belum ada ulasan</p>
        @endforelse
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Data produk untuk JavaScript
    @php
      $productJsData = [
        'id' => $product->id,
        'nama' => $product->nama,
        'harga' => $product->harga,
        'stok' => $product->stok,
        'berat' => $product->berat ?? 1000,
        'imagePath' => $imagePath,
        'deskripsi' => $product->deskripsi,
      ];
    @endphp
    window.PRODUCT_DATA = @json($productJsData);
    window.USER_ID = {{ auth()->check() ? auth()->id() : 'null' }};

    // Read More functionality for Description
    document.addEventListener("DOMContentLoaded", () => {
      const descContent = document.getElementById('product-description');
      const readMoreBtn = document.getElementById('btn-read-more');

      if (descContent && readMoreBtn) {
        // Show button only if content exceeds 4 lines
        if (descContent.scrollHeight > descContent.clientHeight) {
          readMoreBtn.style.display = 'inline-block';
        }

        readMoreBtn.addEventListener('click', function() {
          const isExpanded = descContent.classList.contains('expanded');
          
          if (isExpanded) {
            descContent.classList.remove('expanded');
            this.textContent = 'Baca Selengkapnya';
          } else {
            descContent.classList.add('expanded');
            this.textContent = 'Sembunyikan';
          }
        });
      }
    });
  </script>
  <script src="{{ asset('js/detail-produk.js') }}"></script>
@endpush
