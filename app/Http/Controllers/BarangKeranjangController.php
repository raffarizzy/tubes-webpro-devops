<?php

namespace App\Http\Controllers;

use App\Models\BarangKeranjang;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class BarangKeranjangController extends Controller
{
    private $nodeApiUrl;

    public function __construct()
    {
        $this->nodeApiUrl = config('services.node_api.url') . '/api';
    }

    /**
     * Add item to cart
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'product_id' => 'required|exists:products,id',
                'jumlah' => 'required|integer|min:1',
            ]);

            // Validasi ketersediaan stok menggunakan helper domain logic
            $product = Product::find($validated['product_id']);
            if (!$product || !BarangKeranjang::canAddQuantity((int) $product->stok, (int) $validated['jumlah'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stok tidak mencukupi untuk jumlah yang diminta.',
                ], 422);
            }

            /** @var User $user */
            $user = Auth::user();

            // Call Node.js API to add item
            $response = Http::post("{$this->nodeApiUrl}/cart/item", [
                'user_id' => $user->id,
                'product_id' => $validated['product_id'],
                'jumlah' => $validated['jumlah']
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Produk berhasil ditambahkan ke keranjang',
                    'data' => $response->json('data')
                ]);
            }

            $error = $response->json('message') ?? 'Gagal menambahkan ke keranjang';
            return response()->json([
                'success' => false,
                'message' => $error
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan ke keranjang: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update cart item quantity
     */
    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'jumlah' => 'required|integer|min:1',
            ]);

            // Call Node.js API to update item
            $response = Http::put("{$this->nodeApiUrl}/cart/item/{$id}", [
                'jumlah' => $validated['jumlah']
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Jumlah berhasil diperbarui',
                    'data' => $response->json('data')
                ]);
            }

            $error = $response->json('message') ?? 'Gagal memperbarui jumlah';
            return response()->json([
                'success' => false,
                'message' => $error
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui jumlah: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove item from cart
     */
    public function destroy($id)
    {
        try {
            // Call Node.js API to remove item
            $response = Http::delete("{$this->nodeApiUrl}/cart/item/{$id}");

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => $response->json('message') ?? 'Item berhasil dihapus dari keranjang'
                ]);
            }

            $error = $response->json('message') ?? 'Gagal menghapus item';
            return response()->json([
                'success' => false,
                'message' => $error
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus item: ' . $e->getMessage()
            ], 500);
        }
    }
}