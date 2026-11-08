<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    /**
     * GET semua produk
     */
    public function index()
    {
        $response = Http::get(config('services.node_api.url') . '/api/products');
        $products = $response->json('data') ?? [];
        return view('products.index', compact('products'));
    }

    /**
     * GET detail produk
     */
    public function show($id)
    {
        try {
            // Ambil produk dari Node.js API
            $response = Http::timeout(5)->get(config('services.node_api.url') . "/api/products/{$id}");

            if (!$response->successful()) {
                // Fallback ke Eloquent kalo API gagal
                Log::warning("Node.js API failed, fallback to Eloquent for product {$id}");
                $product = \App\Models\Product::with(['toko', 'category'])->find($id);
            } else {
                $productData = $response->json('data');
                
                if (!$productData) {
                    abort(404, 'Product not found');
                }

                // Convert to object
                $product = (object) $productData;
                
                // Reconstruct toko & category as objects
                if (isset($product->nama_toko)) {
                    $isVerified = $product->is_verified_seller ?? null;
                    
                    // If missing from API, try to check DB
                    if ($isVerified === null && isset($product->toko_id)) {
                        $dbToko = \App\Models\Toko::with('user')->find($product->toko_id);
                        $isVerified = $dbToko->user->is_verified_seller ?? false;
                    }

                    $product->toko = (object) [
                        'id' => $product->toko_id ?? null,
                        'nama_toko' => $product->nama_toko,
                        'lokasi' => $product->toko_lokasi ?? null,
                        'logo_path' => $product->toko_logo ?? null,
                        'is_verified_seller' => $isVerified ?? false,
                    ];
                }
                
                if (isset($product->category_nama)) {
                    $product->category = (object) [
                        'id' => $product->category_id,
                        'judulKategori' => $product->category_nama,
                    ];
                }
            }

            if (!$product) {
                abort(404, 'Product not found');
            }

            // Rating pake Eloquent
            $ratings = \App\Models\Rating::with('user')
                ->where('product_id', $id)
                ->orderBy('created_at', 'desc')
                ->get();

            $summary = \App\Models\Rating::summarize($ratings);
            $avgRating = $summary['average'];
            $ratingCount = $summary['count'];

            return view('detail-produk', compact('product', 'ratings', 'avgRating', 'ratingCount'));

        } catch (\Exception $e) {
            Log::error("Error in ProductController@show: " . $e->getMessage());
            
            // Fallback ke Eloquent
            $product = \App\Models\Product::with(['toko.user', 'category'])->find($id);
            
            if (!$product) {
                abort(404, 'Product not found');
            }

            // Ensure is_verified_seller exists on the toko object
            if ($product->toko) {
                $product->toko->is_verified_seller = $product->toko->user->is_verified_seller ?? false;
            }

            $ratings = \App\Models\Rating::with('user')
                ->where('product_id', $id)
                ->orderBy('created_at', 'desc')
                ->get();

            $summary = \App\Models\Rating::summarize($ratings);
            $avgRating = $summary['average'];
            $ratingCount = $summary['count'];

            return view('detail-produk', compact('product', 'ratings', 'avgRating', 'ratingCount'));
        }
    }

    /**
     * STORE produk
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'nama' => 'required|string',
                'category_id' => 'required|integer',
                'harga' => 'required|numeric',
                'stok' => 'required|integer',
                'berat' => 'required|integer|min:1',
                'deskripsi' => 'required|string',
                'image' => 'nullable|image|mimes:jpg,jpeg,png,webp'
            ]);

            $imagePath = 'produk/default.png'; // Set default image
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $filename = hexdec(uniqid()) . '.webp';
                $imagePath = 'produk/' . $filename;

                // Optimasi Gambar: Resize & Convert ke WebP (v4 Syntax)
                try {
                    $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                    $img = $manager->decode($image);
                    $img->scale(width: 1000);
                    \Illuminate\Support\Facades\Storage::disk('public')->put(
                        $imagePath, 
                        (string) $img->encodeUsingFileExtension('webp', quality: 75)
                    );
                } catch (\Exception $imgError) {
                    Log::error("Image optimization failed: " . $imgError->getMessage());
                    // Fallback: simpan tanpa optimasi jika library gagal
                    $imagePath = $request->file('image')->store('produk', 'public');
                }
            }

            $tokoId = auth()->user()->toko->id ?? null;
            if (!$tokoId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User belum memiliki toko'
                ], 400);
            }

            $apiUrl = config('services.node_api.url') . '/api/products';
            $response = Http::timeout(10)->post($apiUrl, [
                'toko_id' => $tokoId,
                'category_id' => $request->category_id,
                'nama' => $request->nama,
                'harga' => $request->harga,
                'stok' => $request->stok,
                'berat' => $request->berat,
                'deskripsi' => $request->deskripsi,
                'imagePath' => $imagePath,
                'diskon' => $request->diskon ?? 0
            ]);

            if ($response->successful()) {
                return response()->json(['success' => true]);
            } else {
                Log::error("Node.js API Error (Product Store): " . $response->body());
                return response()->json([
                    'success' => false, 
                    'message' => 'Gagal simpan ke API: ' . ($response->json('message') ?? 'Error tidak dikenal')
                ], 500);
            }

        } catch (\Illuminate\Validation\ValidationException $v) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $v->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error("General Error in ProductController@store: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server: ' . $e->getMessage()
            ]);
            }
            }

            /**
            * IMPORT BULK dari JSON (Preview Modal)
            */
            public function importBulk(Request $request)
            {
            try {
            $request->validate([
                'products' => 'required|array',
                'products.*.nama' => 'required|string',
                'products.*.category_id' => 'required',
                'products.*.harga' => 'required|numeric',
                'products.*.stok' => 'required|integer',
                'products.*.berat' => 'required|integer',
                'products.*.deskripsi' => 'nullable|string'
            ]);

            $tokoId = auth()->user()->toko->id ?? null;
            if (!$tokoId) {
                return response()->json(['success' => false, 'message' => 'User belum memiliki toko'], 400);
            }

            $apiUrl = config('services.node_api.url') . '/api/products';
            $successCount = 0;
            $errors = [];

            foreach ($request->products as $p) {
                $response = Http::timeout(5)->post($apiUrl, [
                    'toko_id' => $tokoId,
                    'category_id' => $p['category_id'],
                    'nama' => $p['nama'],
                    'harga' => $p['harga'],
                    'stok' => $p['stok'],
                    'berat' => $p['berat'],
                    'deskripsi' => $p['deskripsi'] ?? '',
                    'imagePath' => 'produk/default.png',
                    'diskon' => 0
                ]);

                if ($response->successful()) {
                    $successCount++;
                } else {
                    $errors[] = "Gagal mengimpor '{$p['nama']}': " . ($response->json('message') ?? 'Error API');
                }
            }

            return response()->json([
                'success' => true,
                'count' => $successCount,
                'errors' => $errors
            ]);

            } catch (\Exception $e) {
            Log::error("Bulk Import Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses impor: ' . $e->getMessage()
            ], 500);
            }
            }

            /**
            * IMPORT produk via CSV
            */
    public function import(Request $request)
    {
        try {
            $request->validate([
                'csv_file' => 'required|file|mimes:csv,txt'
            ]);

            $file = $request->file('csv_file');
            $handle = fopen($file->getRealPath(), 'r');
            
            // Skip header
            $header = fgetcsv($handle, 1000, ',');
            
            $tokoId = auth()->user()->toko->id ?? null;
            if (!$tokoId) {
                return response()->json(['success' => false, 'message' => 'User belum memiliki toko'], 400);
            }

            $apiUrl = config('services.node_api.url') . '/api/products';
            $successCount = 0;
            $errors = [];

            while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                // Mapping: 0: nama, 1: category_id, 2: harga, 3: stok, 4: berat, 5: deskripsi
                if (count($data) < 6) continue;

                $response = Http::timeout(5)->post($apiUrl, [
                    'toko_id' => $tokoId,
                    'category_id' => $data[1],
                    'nama' => $data[0],
                    'harga' => $data[2],
                    'stok' => $data[3],
                    'berat' => $data[4],
                    'deskripsi' => $data[5],
                    'imagePath' => 'produk/default.png',
                    'diskon' => 0
                ]);

                if ($response->successful()) {
                    $successCount++;
                } else {
                    $errors[] = "Gagal mengimpor '{$data[0]}': " . ($response->json('message') ?? 'Error API');
                }
            }

            fclose($handle);

            return response()->json([
                'success' => true,
                'count' => $successCount,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Log::error("CSV Import Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimpor file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * UPDATE produk
     */
    public function update(Request $request, $id)
    {
        try {
            $data = $request->only([
                'nama', 'harga', 'stok', 'berat', 'deskripsi', 'category_id', 'diskon'
            ]);

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $filename = hexdec(uniqid()) . '.webp';
                $imagePath = 'produk/' . $filename;

                // Optimasi Gambar (v4 Syntax)
                try {
                    $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                    $img = $manager->decode($image);
                    $img->scale(width: 1000);
                    \Illuminate\Support\Facades\Storage::disk('public')->put($imagePath, (string) $img->encodeUsingFileExtension('webp', quality: 75));
                    $data['imagePath'] = $imagePath;
                } catch (\Exception $imgError) {
                    Log::error("Image optimization failed (Update): " . $imgError->getMessage());
                    $data['imagePath'] = $request->file('image')->store('produk', 'public');
                }
            }

            $apiUrl = config('services.node_api.url') . "/api/products/{$id}";
            $response = Http::timeout(10)->patch($apiUrl, $data);

            if ($response->successful()) {
                return response()->json(['success' => true]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal update di API: ' . ($response->json('message') ?? 'Error tidak dikenal')
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error("General Error in ProductController@update: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan server: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE produk
     */
    public function destroy($id)
    {
        $response = Http::delete(config('services.node_api.url') . "/api/products/{$id}");

        return $response->successful()
            ? response()->json(['success' => true])
            : response()->json(['success' => false], 500);
    }
}