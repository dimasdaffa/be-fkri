<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use Illuminate\Http\Request;

class KabidController extends Controller
{
    /**
     * Menampilkan daftar usulan yang perlu keputusan Kabid.
     */
    public function index()
    {
        $proposals = Proposal::where('status', 'diproses_kabid')->latest()->get();
        return response()->json($proposals);
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
