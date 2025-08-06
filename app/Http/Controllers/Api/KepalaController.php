<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use Illuminate\Http\Request;

class KepalaController extends Controller
{
    /**
     * Menampilkan daftar usulan yang relevan untuk Kepala BRIDA.
     * Termasuk yang sedang menunggu keputusan dan yang sudah diputuskan.
     */
    public function index()
    {
        $proposals = Proposal::where(function ($query) {
            // 1. Tampilkan yang menunggu keputusan Kepala
            $query->where('status', 'diproses_kepala');

            // 2. Tampilkan yang sudah disetujui final oleh Kepala
            $query->orWhere('status', 'disetujui');
            $query->orWhere('status', 'ditolak');

            // 3. Tampilkan yang DITOLAK oleh Kepala (final)
            //    Kita filter berdasarkan catatan_kepala tidak null untuk memastikan
            //    penolakan berasal dari Kepala, bukan dari level sebelumnya.
            $query->orWhere(function ($subQuery) {
                $subQuery->where('status', 'ditolak')
                         ->whereNotNull('catatan_kepala');
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
                $query->where('status', 'diproses_kepala');
                $query->orWhere('status', 'disetujui');
                $query->orWhere(function ($subQuery) {
                    $subQuery->where('status', 'ditolak')
                             ->whereNotNull('catatan_kepala');
                });
            })
            ->firstOrFail();

        return response()->json($proposal);
    }

    /**
     * Memberikan keputusan final (approve/reject).
     */
    public function decide(Request $request, string $id)
    {
        $validated = $request->validate([
            'keputusan' => ['required', 'in:approve,reject'],
            'catatan'   => ['nullable', 'string'],
        ]);

        $proposal = Proposal::where('status', 'diproses_kepala')->findOrFail($id);

        $status = ($validated['keputusan'] === 'approve') ? 'disetujui' : 'ditolak';

        $proposal->update([
            'status'         => $status,
            'catatan_kepala' => $validated['catatan'],
        ]);

        return response()->json([
            'message' => 'Keputusan final Kepala BRIDA berhasil disimpan.',
            'data'    => $proposal->fresh(),
        ]);
    }
}