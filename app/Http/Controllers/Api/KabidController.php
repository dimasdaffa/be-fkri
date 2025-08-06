<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use Illuminate\Http\Request;

class KabidController extends Controller
{
    /**
     * Menampilkan daftar usulan yang relevan untuk Kabid.
     */
    public function index()
    {
        $proposals = Proposal::where(function ($query) {
            $query->where('status', 'diproses_kabid');

            $query->orWhere('status', 'diproses_kepala');

            $query->orWhere(function ($subQuery) {
                $subQuery->where('status', 'ditolak')
                         ->whereNotNull('catatan_kabid'); 
            });
        })
        ->latest()
        ->get();

        return response()->json($proposals);
    }

    /**
     * Menampilkan detail satu usulan.
     */
    public function show(string $id)
    {
        // Logika query sama dengan index() untuk konsistensi keamanan
        $proposal = Proposal::where('id', $id)
            ->where(function ($query) {
                $query->where('status', 'diproses_kabid');
                $query->orWhere('status', 'diproses_kepala');
                $query->orWhere(function ($subQuery) {
                    $subQuery->where('status', 'ditolak')
                             ->whereNotNull('catatan_kabid');
                });
            })
            ->firstOrFail();

        return response()->json($proposal);
    }

    // ... method decide() tetap sama ...
    public function decide(Request $request, string $id)
    {
        // Logika ini sudah benar, kita akan ubah validasinya di bawah
        $validated = $request->validate([
            'keputusan' => ['required', 'in:approve,reject'],
            'catatan'   => ['nullable', 'string'], // Dibuat opsional kembali
        ]);

        $proposal = Proposal::whereIn('status', ['diproses_kabid'])->findOrFail($id);

        $status = ($validated['keputusan'] === 'approve') ? 'diproses_kepala' : 'ditolak';

        $proposal->update([
            'status' => $status,
            'catatan_kabid' => $validated['catatan'],
        ]);

        return response()->json([
            'message' => 'Keputusan Kabid KRPI berhasil disimpan.',
            'data'    => $proposal->fresh(),
        ]);
    }
}