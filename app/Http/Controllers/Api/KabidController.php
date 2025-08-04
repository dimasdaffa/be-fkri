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
        $statuses = ['diproses_kabid', 'diproses_kepala', 'ditolak'];

        $proposals = Proposal::whereIn('status', $statuses)
            ->where(function ($query) {
                $query->where('status', '!=', 'ditolak')
                      ->orWhereNotNull('catatan_kabid');
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
        $proposal = Proposal::findOrFail($id);
        return response()->json($proposal);
    }

    /**
     * Memutuskan usulan (approve/reject).
     */
    public function decide(Request $request, string $id)
    {
        $validated = $request->validate([
            'keputusan' => ['required', 'in:approve,reject'],
            'catatan' => ['nullable', 'string'],
        ]);

        $proposal = Proposal::findOrFail($id);

        $status = ($validated['keputusan'] === 'approve') ? 'diproses_kepala' : 'ditolak';

        $proposal->update([
            'status' => $status,
            'catatan_kabid' => $validated['catatan'],
        ]);

        return response()->json([
            'message' => 'Keputusan Kabid KRPI berhasil disimpan.',
            'data' => $proposal->fresh(),
        ]);
    }
}
