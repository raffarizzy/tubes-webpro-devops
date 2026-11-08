<?php

namespace App\Http\Controllers;

use App\Models\Toko;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TokoController extends Controller
{
    // Base URL Node.js API
    private $nodeApiUrl;

    public function __construct()
    {
        $this->nodeApiUrl = config('services.node_api.url') . '/api/toko';
    }

    /**
     * Display toko profile or redirect to create
     */
    public function index()
    {
        // Cek apakah user sudah terverifikasi sebagai penjual
        if (!auth()->user()->is_verified_seller) {
            return redirect()->route('toko.verify');
        }

        try {
            // Panggil Node.js API untuk cek apakah user punya toko
            $response = Http::withHeaders([
                'x-user-id' => auth()->id()
            ])->get($this->nodeApiUrl . '/check');

            if (!$response->successful()) {
                throw new \Exception('Gagal mengecek status toko');
            }

            $result = $response->json();

            // Jika belum punya toko, redirect ke create
            if (!$result['data']['hasToko']) {
                return redirect()->route('toko.create');
            }

            // Jika sudah punya toko, ambil data dari API
            $tokoResponse = Http::withHeaders([
                'x-user-id' => auth()->id()
            ])->get($this->nodeApiUrl . '/my-toko');

            if (!$tokoResponse->successful()) {
                throw new \Exception('Gagal mengambil data toko');
            }

            $toko = $tokoResponse->json()['data'];
            $toko = (object) $toko;
            
            // Cari data di Laravel untuk mendapatkan ID yang benar
            $laravelToko = Toko::where('user_id', auth()->id())->first();
            $realLaravelId = $laravelToko ? $laravelToko->id : ($toko->id ?? null);

            // Ensure is_verified_seller exists
            if (!isset($toko->is_verified_seller)) {
                $toko->is_verified_seller = auth()->user()->is_verified_seller;
            }

            // Pastikan produk ada agar view tidak crash (ambil dari DB Laravel)
            $toko->products = Product::with('category')->where('toko_id', $realLaravelId)->get();

            // Ambil pesanan masuk (termasuk untuk produk yang sudah di-soft delete)
            $productIds = Product::withTrashed()->where('toko_id', $realLaravelId)->pluck('id')->toArray();
            $incomingOrders = \App\Models\OrderItems::whereIn('product_id', $productIds)
                ->with(['order.user', 'order.alamat', 'product' => function($q) {
                    $q->withTrashed();
                }])
                ->orderBy('id', 'desc')
                ->get();

            // Statistik Tambahan
            $successOrdersCount = \App\Models\OrderItems::whereIn('product_id', $productIds)
                ->whereHas('order', function($q) { $q->where('status', 'paid'); })
                ->count();
            
            $averageRating = \App\Models\Rating::whereIn('product_id', $productIds)->avg('rating') ?: 0;
            $categories = Category::all();

            $isOwner = true;

            return view('profil_toko', compact('toko', 'incomingOrders', 'successOrdersCount', 'averageRating', 'isOwner', 'categories'));

        } catch (\Exception $e) {
            Log::error('Error in TokoController@index: ' . $e->getMessage());
            
            // Fallback ke database Laravel jika API error
            $toko = Toko::with('user')->where('user_id', auth()->id())->first();

            if (!$toko) {
                return redirect()->route('toko.create');
            }

            $toko->is_verified_seller = $toko->user->is_verified_seller ?? false;

            $toko->products = Product::with('category')->where('toko_id', $toko->id)->get();
            $productIds = Product::withTrashed()->where('toko_id', $toko->id)->pluck('id')->toArray();
            $incomingOrders = \App\Models\OrderItems::whereIn('product_id', $productIds)
                ->with(['order.user', 'order.alamat', 'product' => function($q) {
                    $q->withTrashed();
                }])
                ->orderBy('id', 'desc')
                ->get();

            $successOrdersCount = \App\Models\OrderItems::whereIn('product_id', $productIds)
                ->whereHas('order', function($q) { $q->where('status', 'paid'); })
                ->count();
            
            $averageRating = \App\Models\Rating::whereIn('product_id', $productIds)->avg('rating') ?: 0;
            $categories = Category::all();

            $isOwner = true;

            return view('profil_toko', compact('toko', 'incomingOrders', 'successOrdersCount', 'averageRating', 'isOwner', 'categories'));
        }
    }

    /**
     * Display public toko profile
     */
    public function show($id)
    {
        try {
            // Ambil data toko by ID dari API
            $tokoResponse = Http::get($this->nodeApiUrl . '/' . $id);
            
            if (!$tokoResponse->successful()) {
                // Fallback ke Eloquent - Cari berdasarkan ID Laravel atau node_toko_id
                $toko = Toko::with('user')->where('id', $id)->orWhere('node_toko_id', $id)->first();
            } else {
                $toko = (object) $tokoResponse->json()['data'];
            }

            if (!$toko) {
                abort(404, 'Toko tidak ditemukan');
            }

            // Cari data di Laravel untuk mendapatkan ID yang benar (karena $id bisa jadi node_id)
            $laravelToko = Toko::where('id', $id)->orWhere('node_toko_id', $id)->first();
            $realLaravelId = $laravelToko ? $laravelToko->id : ($toko->id ?? $id);

            // Ensure is_verified_seller exists
            if (!isset($toko->is_verified_seller)) {
                if ($toko instanceof \App\Models\Toko) {
                    $toko->is_verified_seller = $toko->user->is_verified_seller ?? false;
                } else {
                    // If it's stdClass from API but missing the property, try to find in DB
                    $dbToko = $laravelToko ?: Toko::with('user')->find($realLaravelId);
                    $toko->is_verified_seller = $dbToko->user->is_verified_seller ?? false;
                }
            }

            // Ambil produk (ambil dari DB Laravel menggunakan ID Laravel yang benar)
            $toko->products = Product::with('category')->where('toko_id', $realLaravelId)->get();

            // Ambil pesanan masuk (termasuk untuk produk yang sudah di-soft delete)
            $productIds = Product::withTrashed()->where('toko_id', $realLaravelId)->pluck('id');

            // Statistik
            $successOrdersCount = \App\Models\OrderItems::whereIn('product_id', $productIds)
                ->whereHas('order', function($q) { $q->where('status', 'paid'); })
                ->count();
            
            $averageRating = \App\Models\Rating::whereIn('product_id', $productIds)->avg('rating') ?: 0;

            // Cek apakah ini toko milik user yang login
            $isOwner = auth()->check() && ($toko->user_id ?? ($laravelToko->user_id ?? null)) == auth()->id();

            // Ambil detail pesanan masuk jika owner
            $incomingOrders = collect();
            if ($isOwner) {
                $incomingOrders = \App\Models\OrderItems::whereIn('product_id', $productIds)
                    ->with(['order.user', 'order.alamat', 'product' => function($q) {
                        $q->withTrashed();
                    }])
                    ->orderBy('id', 'desc')
                    ->get();
            }

            $categories = \App\Models\Category::all();

            return view('profil_toko', compact('toko', 'incomingOrders', 'successOrdersCount', 'averageRating', 'isOwner', 'categories'));

        } catch (\Exception $e) {
            Log::error('Error in TokoController@show: ' . $e->getMessage());
            abort(404, 'Toko tidak ditemukan');
        }
    }

    /**
     * Show verification instructions page
     */
    public function verify()
    {
        // Jika sudah terverifikasi, alihkan ke profil toko
        if (auth()->user()->is_verified_seller) {
            return redirect()->route('profil_toko');
        }

        return view('toko.verify-wa');
    }

    /**
     * Show create toko form
     */
    public function create()
    {
        // Cek apakah user sudah terverifikasi sebagai penjual
        if (!auth()->user()->is_verified_seller) {
            return redirect()->route('toko.verify');
        }

        // Cek dulu apakah user sudah punya toko (dari API)
        try {
            $response = Http::withHeaders([
                'x-user-id' => auth()->id()
            ])->get($this->nodeApiUrl . '/check');

            if ($response->successful()) {
                $result = $response->json();
                
                if ($result['data']['hasToko']) {
                    return redirect()->route('profil_toko')
                        ->with('error', 'Anda sudah memiliki toko');
                }
            }
        } catch (\Exception $e) {
            Log::error('Error checking toko: ' . $e->getMessage());
        }

        return view('toko.create');
    }

    /**
     * Store new toko
     */
    public function store(Request $request)
    {
        // Proteksi jika ada yang mencoba menembak API langsung
        if (!auth()->user()->is_verified_seller) {
            return response()->json(['success' => false, 'message' => 'Akun Anda belum terverifikasi sebagai penjual.'], 403);
        }

        $request->validate([
            'nama_toko' => 'required|string|max:255',
            'deskripsi_toko' => 'required|string',
            'lokasi' => 'required|string|max:255',
            'provinsi' => 'required|string',
            'kota' => 'required|string',
            'kecamatan' => 'required|string',
            'kode_pos' => 'required|string|max:10',
            'kode_wilayah' => 'required|string',
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,webp'
        ]);

        try {
            // 1. CEK DULU apakah user sudah punya toko via Node.js API
            $checkResponse = Http::withHeaders([
                'x-user-id' => auth()->id()
            ])->get($this->nodeApiUrl . '/check');

            if ($checkResponse->successful()) {
                $checkResult = $checkResponse->json();
                if ($checkResult['data']['hasToko']) {
                    return redirect()->back()
                        ->with('error', 'Anda sudah memiliki toko');
                }
            }

            // 2. Upload logo ke storage Laravel dengan optimasi
            $logo = $request->file('logo');
            $uniqueId = hexdec(uniqid());
            $filename = $uniqueId . '.webp';
            $logoPath = 'toko/' . $filename;

            // Optimasi Logo: Resize & Convert ke WebP (v4 Syntax)
            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
            $img = $manager->decode($logo);
            $img->scale(width: 500); // Logo cukup 500px

            $encodedLogo = (string) $img->encodeUsingFileExtension('webp', quality: 75);
            
            // Pastikan folder ada dan simpan
            $isSaved = \Illuminate\Support\Facades\Storage::disk('public')->put($logoPath, $encodedLogo);
            
            if ($isSaved) {
                Log::info("Logo Toko saved successfully: " . \Illuminate\Support\Facades\Storage::disk('public')->path($logoPath));
            } else {
                Log::error("Failed to save Logo Toko: " . $logoPath);
            }

            $logoUrl = \Illuminate\Support\Facades\Storage::url($logoPath);

            // 3. Kirim ke Node.js API DULU
            $response = Http::withHeaders([
                'x-user-id' => auth()->id()
            ])->post($this->nodeApiUrl, [
                'nama_toko' => $request->nama_toko,
                'deskripsi_toko' => $request->deskripsi_toko,
                'lokasi' => $request->lokasi,
                'provinsi' => $request->provinsi,
                'kota' => $request->kota,
                'kecamatan' => $request->kecamatan,
                'kode_pos' => $request->kode_pos,
                'kode_wilayah' => $request->kode_wilayah,
                'logo_path' => $logoPath
            ]);

            if ($response->status() < 200 || $response->status() >= 300) {
                Storage::disk('public')->delete($logoPath);

                $errorMsg = $response->json()['message'] ?? 'Gagal menyimpan toko ke API';
                throw new \Exception($errorMsg);
            }

            $nodeTokoId = $response->json()['data']['id'];

            // 4. Simpan ke Database Laravel (Eloquent) - use auto-increment id, simpan Node.js ID terpisah
            Toko::create([
                'user_id' => auth()->id(),
                'node_toko_id' => $nodeTokoId,
                'nama_toko' => $request->nama_toko,
                'deskripsi_toko' => $request->deskripsi_toko,
                'lokasi' => $request->lokasi,
                'provinsi' => $request->provinsi,
                'kota' => $request->kota,
                'kecamatan' => $request->kecamatan,
                'kode_pos' => $request->kode_pos,
                'kode_wilayah' => $request->kode_wilayah,
                'logo_path' => $logoPath,
            ]);

            return redirect()->route('profil_toko')
                ->with('success', 'Toko berhasil dibuat!');

        } catch (\Exception $e) {
            Log::error('Error creating toko: ' . $e->getMessage());

            // Hapus file jika ada error
            if (isset($logoPath)) {
                Storage::disk('public')->delete($logoPath);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal membuat toko: ' . $e->getMessage());
        }
    }

    /**
     * Update toko
     */
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'nama_toko' => 'required|string|max:255',
                'deskripsi_toko' => 'required|string',
                'lokasi' => 'required|string|max:255',
                'provinsi' => 'required|string',
                'kota' => 'required|string',
                'kecamatan' => 'required|string',
                'kode_pos' => 'required|string|max:10',
                'kode_wilayah' => 'required|string',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp'
            ]);

            // 1. Update di database Laravel
            // Cari berdasarkan ID Laravel atau node_toko_id yang sesuai dengan user yang login
            $toko = Toko::where(function($q) use ($id) {
                $q->where('id', $id)
                  ->orWhere('node_toko_id', $id);
            })->where('user_id', auth()->id())->first();

            // Jika tidak ditemukan dengan user_id, cek apakah memang tokonya ada tapi bukan milik user ini
            if (!$toko) {
                $exists = Toko::where('id', $id)->orWhere('node_toko_id', $id)->exists();
                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda tidak memiliki akses untuk mengupdate toko ini'
                    ], 403);
                }
                return response()->json([
                    'success' => false,
                    'message' => 'Toko tidak ditemukan'
                ], 404);
            }

            $logoPath = $toko->logo_path;

            // Handle upload logo baru
            if ($request->hasFile('logo')) {
                // Hapus logo lama
                if ($toko->logo_path) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($toko->logo_path);
                }

                // Upload logo baru dengan optimasi
                $logo = $request->file('logo');
                $uniqueId = hexdec(uniqid());
                $filename = $uniqueId . '.webp';
                $logoPath = 'toko/' . $filename;

                $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                $img = $manager->decode($logo);
                $img->scale(width: 500);

                \Illuminate\Support\Facades\Storage::disk('public')->put($logoPath, (string) $img->encodeUsingFileExtension('webp', quality: 75));
            }

            // Update database Laravel
            $toko->update([
                'nama_toko' => $request->nama_toko,
                'deskripsi_toko' => $request->deskripsi_toko,
                'lokasi' => $request->lokasi,
                'provinsi' => $request->provinsi,
                'kota' => $request->kota,
                'kecamatan' => $request->kecamatan,
                'kode_pos' => $request->kode_pos,
                'kode_wilayah' => $request->kode_wilayah,
                'logo_path' => $logoPath
            ]);

            // 2. Update ke Node.js API
            $apiData = [
                'nama_toko' => $request->nama_toko,
                'deskripsi_toko' => $request->deskripsi_toko,
                'lokasi' => $request->lokasi,
                'provinsi' => $request->provinsi,
                'kota' => $request->kota,
                'kecamatan' => $request->kecamatan,
                'kode_pos' => $request->kode_pos,
                'kode_wilayah' => $request->kode_wilayah,
                'logo_path' => $logoPath
            ];

            // Gunakan node_toko_id yang benar saat memanggil API
            $nodeId = $toko->node_toko_id ?: $id;

            $response = Http::withHeaders([
                'x-user-id' => auth()->id()
            ])->patch($this->nodeApiUrl . '/' . $nodeId, $apiData);

            if (!$response->successful()) {
                Log::warning('Failed to update toko in Node.js API: ' . $response->body());
            }

            return response()->json([
                'success' => true,
                'message' => 'Toko berhasil diperbarui',
                'logo_path' => $logoPath,
                'logo_url' => Storage::url($logoPath)
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error updating toko: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui toko: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete toko
     */
    public function destroy($id)
    {
        try {
            // Cari berdasarkan ID Laravel atau node_toko_id
            $toko = Toko::where(function($q) use ($id) {
                $q->where('id', $id)
                  ->orWhere('node_toko_id', $id);
            })->where('user_id', auth()->id())->first();

            if (!$toko) {
                $exists = Toko::where('id', $id)->orWhere('node_toko_id', $id)->exists();
                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda tidak memiliki akses untuk menghapus toko ini'
                    ], 403);
                }
                return response()->json([
                    'success' => false,
                    'message' => 'Toko tidak ditemukan'
                ], 404);
            }

            // 1. Hapus dari Node.js API
            $nodeIdToUse = $toko->node_toko_id ?: $id;
            $response = Http::withHeaders([
                'x-user-id' => auth()->id()
            ])->delete($this->nodeApiUrl . '/' . $nodeIdToUse);

            if (!$response->successful()) {
                Log::warning('Failed to delete toko in Node.js API: ' . $response->body());
            }

            // 2. Hapus logo dari storage
            if ($toko->logo_path) {
                Storage::disk('public')->delete($toko->logo_path);
            }

            // 3. Hapus dari database Laravel
            $toko->delete();

            return response()->json([
                'success' => true,
                'message' => 'Toko berhasil dihapus'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting toko: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus toko: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get toko data via API (helper method)
     */
    public function getTokoFromApi($tokoId = null)
    {
        try {
            $url = $tokoId 
                ? $this->nodeApiUrl . '/' . $tokoId 
                : $this->nodeApiUrl . '/my-toko';

            $response = Http::withHeaders([
                'x-user-id' => auth()->id()
            ])->get($url);

            if ($response->successful()) {
                return $response->json()['data'];
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Error getting toko from API: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Accept an order (paid -> processing)
     */
    public function acceptOrder($orderId)
    {
        try {
            $order = \App\Models\Order::findOrFail($orderId);
            
            // Verifikasi bahwa order ini berisi produk dari toko user ini (termasuk yang sudah di-delete)
            $toko = Toko::where('user_id', auth()->id())->first();
            $ownsProduct = $order->items()->whereHas('product', function($q) use ($toko) {
                $q->withTrashed()->where('toko_id', $toko->id);
            })->exists();

            if (!$ownsProduct) {
                return back()->with('error', 'Anda tidak memiliki akses ke pesanan ini.');
            }

            if ($order->status !== 'paid' || ! $order->canTransitionTo('processing')) {
                return back()->with('error', 'Hanya pesanan yang sudah dibayar yang dapat diterima.');
            }

            $order->update(['status' => 'processing']);
            return back()->with('success', 'Pesanan diterima dan sedang diproses.');

        } catch (\Exception $e) {
            return back()->with('error', 'Gagal menerima pesanan: ' . $e->getMessage());
        }
    }

    /**
     * Reject/Cancel an order
     */
    public function rejectOrder($orderId)
    {
        try {
            $order = \App\Models\Order::findOrFail($orderId);
            
            $toko = Toko::where('user_id', auth()->id())->first();
            $ownsProduct = $order->items()->whereHas('product', function($q) use ($toko) {
                $q->withTrashed()->where('toko_id', $toko->id);
            })->exists();

            if (!$ownsProduct) {
                return back()->with('error', 'Anda tidak memiliki akses ke pesanan ini.');
            }

            if (! $order->canTransitionTo('cancelled')) {
                return back()->with('error', 'Pesanan ini tidak dapat dibatalkan.');
            }

            $order->update(['status' => 'cancelled']);
            return back()->with('success', 'Pesanan berhasil dibatalkan.');

        } catch (\Exception $e) {
            return back()->with('error', 'Gagal membatalkan pesanan: ' . $e->getMessage());
        }
    }

    /**
     * Ship an order (processing -> shipped)
     */
    public function shipOrder(Request $request, $orderId)
    {
        $request->validate([
            'nomor_resi' => 'required|string|max:100'
        ]);

        try {
            $order = \App\Models\Order::findOrFail($orderId);
            
            $toko = Toko::where('user_id', auth()->id())->first();
            $ownsProduct = $order->items()->whereHas('product', function($q) use ($toko) {
                $q->withTrashed()->where('toko_id', $toko->id);
            })->exists();

            if (!$ownsProduct) {
                return back()->with('error', 'Anda tidak memiliki akses ke pesanan ini.');
            }

            if (! $order->canTransitionTo('shipped')) {
                return back()->with('error', 'Hanya pesanan yang sedang diproses yang dapat dikirim.');
            }

            $order->update([
                'status' => 'shipped',
                'nomor_resi' => $request->nomor_resi
            ]);

            return back()->with('success', 'Pesanan telah dikirim dengan nomor resi: ' . $request->nomor_resi);

        } catch (\Exception $e) {
            return back()->with('error', 'Gagal mengirim pesanan: ' . $e->getMessage());
        }
    }
}