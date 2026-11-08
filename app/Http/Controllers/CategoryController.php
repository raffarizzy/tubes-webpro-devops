<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Menampilkan daftar kategori dengan filter harga opsional dan pencarian.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category::query();

        if ($request->filled('q')) {
            $query->where('judulKategori', 'like', '%' . $request->query('q') . '%');
        }

        $categories = $query->with(['products' => function ($q) use ($request) {
            if ($request->hasAny(['min_price', 'max_price'])) {
                $range = Category::sanitizePriceRange(
                    $request->query('min_price'),
                    $request->query('max_price')
                );

                if ($range['min'] > 0) {
                    $q->where('harga', '>=', $range['min']);
                }
                if ($range['max'] > 0) {
                    $q->where('harga', '<=', $range['max']);
                }
            }
        }])->get();

        $data = $categories->map(function (Category $category) {
            return [
                'id' => $category->id,
                'judulKategori' => $category->judulKategori,
                'slug' => Category::createSlug($category->judulKategori ?? ''),
                'products_count' => $category->products->count(),
                'products' => $category->products->map(function (Product $product) {
                    return [
                        'id' => $product->id,
                        'nama' => $product->nama,
                        'harga' => $product->harga,
                        'stok' => $product->stok,
                        'stock_badge' => Product::getStockStatusBadge((int) $product->stok),
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Menyimpan kategori baru dengan auto-slug.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'judulKategori' => 'required|string|max:255',
        ]);

        $slug = Category::createSlug($validated['judulKategori']);

        $category = Category::create([
            'judulKategori' => $validated['judulKategori'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil ditambahkan',
            'data' => [
                'id' => $category->id,
                'judulKategori' => $category->judulKategori,
                'slug' => $slug,
            ],
        ], 201);
    }

    /**
     * Menampilkan detail kategori beserta produk dan badge status stok.
     */
    public function show(Category $category, Request $request): JsonResponse
    {
        $productsQuery = $category->products();

        if ($request->hasAny(['min_price', 'max_price'])) {
            $range = Category::sanitizePriceRange(
                $request->query('min_price'),
                $request->query('max_price')
            );

            if ($range['min'] > 0) {
                $productsQuery->where('harga', '>=', $range['min']);
            }
            if ($range['max'] > 0) {
                $productsQuery->where('harga', '<=', $range['max']);
            }
        }

        $products = $productsQuery->get()->map(function (Product $product) {
            return [
                'id' => $product->id,
                'nama' => $product->nama,
                'harga' => $product->harga,
                'stok' => $product->stok,
                'stock_badge' => Product::getStockStatusBadge((int) $product->stok),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $category->id,
                'judulKategori' => $category->judulKategori,
                'slug' => Category::createSlug($category->judulKategori ?? ''),
                'products' => $products,
            ],
        ]);
    }

    /**
     * Memperbarui data kategori.
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'judulKategori' => 'required|string|max:255',
        ]);

        $category->update([
            'judulKategori' => $validated['judulKategori'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil diperbarui',
            'data' => [
                'id' => $category->id,
                'judulKategori' => $category->judulKategori,
                'slug' => Category::createSlug($category->judulKategori),
            ],
        ]);
    }

    /**
     * Menghapus kategori.
     */
    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dihapus',
        ]);
    }
}
