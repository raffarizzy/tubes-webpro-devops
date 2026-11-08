<?php

namespace App\Http\Controllers;

use App\Models\OrderItems;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RatingController extends Controller
{
    private string $nodeApiUrl;

    public function __construct()
    {
        $this->nodeApiUrl = config('services.node_api.url');
    }

    /**
     * Display ratings page (Riwayat Ulasan)
     */
    public function index()
    {
        try {
            // Ambil ratings user dari Node.js API
            $response = Http::timeout(10)->get($this->nodeApiUrl . '/api/ratings', [
                'user_id' => auth()->id()
            ]);

            // Parse response jadi collection
            $ratings = collect();
            if ($response->successful()) {
                $ratingsData = $response->json('data') ?? [];
                $ratings = collect($ratingsData);
            }

            return view('ratings.index', compact('ratings'));

        } catch (\Exception $e) {
            Log::error('Failed to fetch ratings', ['error' => $e->getMessage()]);
            return view('ratings.index', [
                'ratings' => collect(),
            ])->with('error', 'Koneksi ke server rating gagal');
        }
    }

    /**
     * Show form to create rating for a specific product
     */
    public function createRating($productId)
    {
        try {
            $product = Product::findOrFail($productId);

            // Cek apakah user sudah pernah memberikan rating untuk produk ini
            $response = Http::timeout(10)->get($this->nodeApiUrl . '/api/ratings', [
                'user_id' => auth()->id()
            ]);

            $existingRating = null;
            if ($response->successful()) {
                $ratings = collect($response->json('data'));
                $existingRating = $ratings->firstWhere('product_id', $productId);
            }

            return view('ratings.create', compact('product', 'existingRating'));

        } catch (\Exception $e) {
            return redirect()->route('riwayat.pesanan')->with('error', 'Gagal memuat form rating: ' . $e->getMessage());
        }
    }

    /**
     * Store rating via Node.js API
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'rating'     => 'required|integer|min:1|max:5',
            'review'     => 'required|string|max:1000',
        ]);

        // Verifikasi pembeli sah (mencegah fake review / ulasan dari user yang belum pernah beli)
        if (!self::isVerifiedBuyer(auth()->id(), (int) $validated['product_id'])) {
            return back()
                ->withErrors(['product_id' => 'Anda hanya dapat memberikan ulasan untuk produk yang telah Anda beli.'])
                ->withInput();
        }

        // Sanitasi input ulasan untuk mencegah Stored XSS
        $sanitizedReview = self::sanitizeReview($validated['review']);
        if ($sanitizedReview === '') {
            return back()
                ->withErrors(['review' => 'Isi ulasan tidak boleh kosong.'])
                ->withInput();
        }

        try {
            // Cek duplikasi sebelum post ke Node API
            $checkResponse = Http::timeout(10)->get($this->nodeApiUrl . '/api/ratings', [
                'user_id' => auth()->id()
            ]);

            if ($checkResponse->successful()) {
                $ratings = collect($checkResponse->json('data'));
                if ($ratings->contains('product_id', $validated['product_id'])) {
                    return back()
                        ->withErrors(['product_id' => 'Anda sudah memberikan ulasan untuk produk ini.'])
                        ->withInput();
                }
            }

            $response = Http::timeout(10)->post(
                $this->nodeApiUrl . '/api/ratings',
                [
                    'user_id'    => auth()->id(),
                    'product_id' => $validated['product_id'],
                    'rating'     => $validated['rating'],
                    'review'     => $sanitizedReview,
                ]
            );

            if ($response->failed()) {
                $errorMessage = $response->json('message') ?? 'Gagal menambahkan rating';
                return back()
                    ->withErrors(['product_id' => $errorMessage])
                    ->withInput();
            }

            return redirect()
                ->route('ratings.index')
                ->with('success', 'Rating berhasil ditambahkan');

        } catch (\Exception $e) {
            return back()
                ->withErrors(['product_id' => 'Koneksi ke server rating gagal'])
                ->withInput();
        }
    }

    /**
     * Delete rating via Node.js API
     */
    public function destroy($id)
    {
        try {
            $response = Http::timeout(10)->delete(
                $this->nodeApiUrl . "/api/ratings/{$id}",
                ['user_id' => auth()->id()]
            );

            if ($response->successful()) {
                return redirect()
                    ->route('ratings.index')
                    ->with('success', 'Rating berhasil dihapus');
            }

            return back()->with('error', 'Gagal menghapus rating');

        } catch (\Exception $e) {
            return back()->with('error', 'Koneksi ke server rating gagal');
        }
    }

    /**
     * Verifikasi pembeli sah (Verified Buyer) untuk mencegah fake review.
     * Hanya pembeli yang memiliki pesanan sah yang diizinkan mengulas.
     */
    public static function isVerifiedBuyer(?int $userId, int $productId): bool
    {
        if (!$userId) {
            return false;
        }

        return OrderItems::where('product_id', $productId)
            ->whereHas('order', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->whereIn('status', ['paid', 'processing', 'shipped', 'finished']);
            })
            ->exists();
    }

    /**
     * Sanitasi isi teks ulasan untuk mencegah celah keamanan Stored XSS.
     */
    public static function sanitizeReview(?string $review): string
    {
        if ($review === null) {
            return '';
        }

        return trim(strip_tags($review));
    }

    /**
     * Label sentimen tingkat kepuasan berdasarkan bintang rating 1-5.
     */
    public static function getStarLabel(int $rating): string
    {
        return match ($rating) {
            1 => 'Sangat Buruk',
            2 => 'Buruk',
            3 => 'Cukup',
            4 => 'Puas',
            5 => 'Sangat Puas',
            default => 'Tidak Valid',
        };
    }
}
