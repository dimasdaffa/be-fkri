<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubbagController extends Controller
{
    /**
     * Menampilkan daftar usulan sesuai wilayah kewenangan Subbag.
     */
    public function index()
    {
        $user = Auth::user();
        $statuses = ['diajukan', 'diproses_kabid', 'ditolak'];

        $proposals = Proposal::whereIn('status', $statuses)
            ->where('wilayah_kewenangan_lembaga', $user->wilayah_kewenangan)
            ->latest()
            ->get();
        return response()->json($proposals);
    }
    public function show(string $id)
    {
        $user = Auth::user();
        $proposal = Proposal::findOrFail($id);
        if ($proposal->wilayah_kewenangan_lembaga !== $user->wilayah_kewenangan) {
            return response()->json(['message' => 'Anda tidak memiliki wewenang untuk melihat usulan ini.'], 403);
        }

        return response()->json($proposal);
    }

    public function assess(Request $request, string $id)
    {
        $validated = $request->validate([
            'penilaian' => ['required', 'in:urgent,penting,normal,tidak_penting'],
            'keputusan' => ['required', 'in:approve,reject'],
            'catatan' => ['nullable', 'string'],
        ]);

        $proposal = Proposal::findOrFail($id);

        $skor = 0;
        $warna = '';
        switch ($validated['penilaian']) {
            case 'urgent':
                $skor = 100;
                $warna = 'merah';
                break;
            case 'penting':
                $skor = 80;
                $warna = 'merah muda';
                break;
            case 'normal':
                $skor = 60;
                $warna = 'biru';
                break;
            case 'tidak_penting':
                $skor = 40;
                $warna = 'gray';
                break;
        }

        $status = ($validated['keputusan'] === 'approve') ? 'diproses_kabid' : 'ditolak';

        $proposal->update([
            'skor' => $skor,
            'warna' => $warna,
            'status' => $status,
            'catatan_subbag' => $validated['catatan'],
        ]);

        return response()->json([
            'message' => 'Usulan berhasil dinilai.',
            'data' => $proposal->fresh(), 
        ]);
    }
}
