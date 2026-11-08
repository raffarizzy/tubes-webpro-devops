<?php

namespace App\Http\Controllers;

use App\Models\Alamat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlamatController extends Controller
{
    private $nodeApiUrl;

    public function __construct()
    {
        $this->nodeApiUrl = config('services.node_api.url') . '/api';
    }

    /**
     * Aturan validasi kode pos: harus tepat 5 digit angka agar diterima API kurir.
     */
    private function kodePosRules(): array
    {
        return [
            'nullable',
            'string',
            function (string $attribute, mixed $value, \Closure $fail) {
                if (!is_string($value) || !Alamat::isValidPostalCode($value)) {
                    $fail('Kode pos harus terdiri dari tepat 5 digit angka.');
                }
            },
        ];
    }

    /**
     * GET /alamat
     * Get all alamat for authenticated user - Consume Node.js API
     */
    public function index()
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            // Call Node.js API to get alamat
            $response = Http::timeout(30)
                ->get("{$this->nodeApiUrl}/alamat/{$user->id}");

            if ($response->successful()) {
                $alamats = $response->json('data');
                
                Log::info('Alamat fetched successfully', [
                    'user_id' => $user->id,
                    'count' => count($alamats)
                ]);

                return response()->json($alamats);
            }

            throw new \Exception($response->json('message') ?? 'Failed to fetch alamat');

        } catch (\Exception $e) {
            Log::error('Failed to fetch alamat', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar alamat: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /alamat
     * Create new alamat - Consume Node.js API
     */
    public function store(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $validated = $request->validate([
            'alamat' => 'required|string',
            'provinsi' => 'nullable|string',
            'kota' => 'nullable|string',
            'kecamatan' => 'nullable|string',
            'kode_pos' => $this->kodePosRules(),
            'kode_wilayah' => 'nullable|string',
            'nama_penerima' => 'required|string|max:255',
            'nomor_penerima' => 'required|string|max:20',
            'is_default' => 'nullable|boolean',
        ]);

        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            // Convert is_default to proper format
            $isDefault = $request->input('is_default', false) ? 1 : 0;

            $data = [
                'user_id' => $user->id,
                'alamat' => $request->input('alamat'),
                'provinsi' => $request->input('provinsi') ?: null,
                'kota' => $request->input('kota') ?: null,
                'kecamatan' => $request->input('kecamatan') ?: null,
                'kode_pos' => $request->input('kode_pos') ?: null,
                'kode_wilayah' => $request->input('kode_wilayah') ?: null,
                'nama_penerima' => $request->input('nama_penerima'),
                'nomor_penerima' => $request->input('nomor_penerima'),
                'is_default' => $isDefault,
            ];

            Log::debug('Data sent to Node.js:', $data);

            Log::info('Creating alamat', [
                'user_id' => $user->id,
                'data' => $data
            ]);

            // Call Node.js API to create alamat
            $response = Http::timeout(30)
                ->post("{$this->nodeApiUrl}/alamat", $data);

            if ($response->successful()) {
                $result = $response->json();
                
                Log::info('Alamat created successfully', [
                    'alamat_id' => $result['data']['id'] ?? null
                ]);

                return response()->json($result['data'], 201);
            }

            throw new \Exception($response->json('message') ?? 'Failed to create alamat');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to create alamat', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan alamat: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /alamat/{id}
     * Update alamat - Consume Node.js API
     */
    public function update(Request $request, $id)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $validated = $request->validate([
            'alamat' => 'required|string',
            'provinsi' => 'nullable|string',
            'kota' => 'nullable|string',
            'kecamatan' => 'nullable|string',
            'kode_pos' => $this->kodePosRules(),
            'kode_wilayah' => 'nullable|string',
            'nama_penerima' => 'required|string|max:255',
            'nomor_penerima' => 'required|string|max:20',
            'is_default' => 'nullable|boolean',
        ]);

        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            // Convert is_default to proper format
            $isDefault = $request->input('is_default', false) ? 1 : 0;

            $data = [
                'user_id' => $user->id,
                'alamat' => $request->input('alamat'),
                'provinsi' => $request->input('provinsi') ?: null,
                'kota' => $request->input('kota') ?: null,
                'kecamatan' => $request->input('kecamatan') ?: null,
                'kode_pos' => $request->input('kode_pos') ?: null,
                'kode_wilayah' => $request->input('kode_wilayah') ?: null,
                'nama_penerima' => $request->input('nama_penerima'),
                'nomor_penerima' => $request->input('nomor_penerima'),
                'is_default' => $isDefault,
            ];

            Log::debug('Data sent to Node.js:', $data);

            Log::info('Updating alamat', [
                'alamat_id' => $id,
                'user_id' => $user->id,
                'data' => $data
            ]);

            // Call Node.js API to update alamat
            $response = Http::timeout(30)
                ->put("{$this->nodeApiUrl}/alamat/{$id}", $data);

            if ($response->successful()) {
                $result = $response->json();
                
                Log::info('Alamat updated successfully', [
                    'alamat_id' => $id
                ]);

                return response()->json($result['data']);
            }

            // Handle specific error cases
            $statusCode = $response->status();
            $errorMessage = $response->json('message') ?? 'Failed to update alamat';

            if ($statusCode === 404) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 404);
            }

            if ($statusCode === 403) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses ke alamat ini'
                ], 403);
            }

            throw new \Exception($errorMessage);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to update alamat', [
                'alamat_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui alamat: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /alamat/{id}
     * Delete alamat - Consume Node.js API
     */
    public function destroy($id)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            Log::info('Deleting alamat', [
                'alamat_id' => $id,
                'user_id' => $user->id
            ]);

            // Call Node.js API to delete alamat
            $response = Http::timeout(30)
                ->delete("{$this->nodeApiUrl}/alamat/{$id}", [
                    'user_id' => $user->id
                ]);

            if ($response->successful()) {
                Log::info('Alamat deleted successfully', [
                    'alamat_id' => $id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Alamat berhasil dihapus'
                ]);
            }

            // Handle specific error cases
            $statusCode = $response->status();
            $errorMessage = $response->json('message') ?? 'Failed to delete alamat';

            if ($statusCode === 404) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 404);
            }

            if ($statusCode === 403) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses ke alamat ini'
                ], 403);
            }

            throw new \Exception($errorMessage);

        } catch (\Exception $e) {
            Log::error('Failed to delete alamat', [
                'alamat_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus alamat: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /alamat/{id}/set-default
     * Set alamat as default - Consume Node.js API
     */
    public function setDefault($id)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        try {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            Log::info('Setting alamat as default', [
                'alamat_id' => $id,
                'user_id' => $user->id
            ]);

            // Call Node.js API to set default alamat
            $response = Http::timeout(30)
                ->put("{$this->nodeApiUrl}/alamat/{$id}/set-default", [
                    'user_id' => $user->id
                ]);

            if ($response->successful()) {
                $result = $response->json();
                
                Log::info('Alamat set as default successfully', [
                    'alamat_id' => $id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Alamat berhasil diatur sebagai default',
                    'data' => $result['data']
                ]);
            }

            // Handle specific error cases
            $statusCode = $response->status();
            $errorMessage = $response->json('message') ?? 'Failed to set default alamat';

            if ($statusCode === 404) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 404);
            }

            if ($statusCode === 403) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses ke alamat ini'
                ], 403);
            }

            throw new \Exception($errorMessage);

        } catch (\Exception $e) {
            Log::error('Failed to set default alamat', [
                'alamat_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengatur alamat sebagai default: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /alamat/{id}
     * Get alamat detail - Consume Node.js API
     */
    public function show($id)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        try {
            // Call Node.js API to get alamat detail
            $response = Http::timeout(30)
                ->get("{$this->nodeApiUrl}/alamat/detail/{$id}");

            if ($response->successful()) {
                $alamat = $response->json('data');
                
                // Verify ownership
                if ($alamat['user_id'] != Auth::id()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda tidak memiliki akses ke alamat ini'
                    ], 403);
                }

                return response()->json([
                    'success' => true,
                    'data' => $alamat
                ]);
            }

            throw new \Exception($response->json('message') ?? 'Failed to fetch alamat');

        } catch (\Exception $e) {
            Log::error('Failed to fetch alamat detail', [
                'alamat_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail alamat: ' . $e->getMessage()
            ], 500);
        }
    }
}