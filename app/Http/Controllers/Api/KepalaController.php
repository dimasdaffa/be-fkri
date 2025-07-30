<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use Illuminate\Http\Request;

class KepalaController extends Controller
{
    /**
     * Menampilkan daftar usulan yang perlu keputusan Kepala BRIDA.
     */
    public function index()
    {
        $proposals = Proposal::where('status', 'diproses_kepala')->latest()->get();
        return response()->json($proposals);
    }

    /**
     * Memberikan keputusan final (approve/reject).
     */
    public function decide(Request $request, string $id)
    {
        $validated = $request->validate([
            'keputusan' => ['required', 'in:approve,reject'],
            'catatan' => ['nullable', 'string'],
        ]);

        $proposal = Proposal::findOrFail($id);

        $status = ($validated['keputusan'] === 'approve') ? 'disetujui' : 'ditolak';

        $proposal->update([
            'status' => $status,
            'catatan_kepala' => $validated['catatan'],
        ]);

        return response()->json([
            'message' => 'Keputusan final Kepala BRIDA berhasil disimpan.',
            'data' => $proposal->fresh(),
        ]);
    }
}
