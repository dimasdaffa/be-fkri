<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
// 1. Import kelas Notifikasi yang sudah kita buat
use App\Notifications\ProposalApprovedBySubbag;

class SubbagController extends Controller
{
    /**
     * Menampilkan daftar usulan sesuai wilayah kewenangan Subbag.
     * Hanya menampilkan usulan yang relevan untuk dinilai.
     */
    public function index()
    {
        $user = Auth::user();

        // Menampilkan usulan yang baru diajukan atau yang sudah diproses oleh peran lain
        $proposals = Proposal::where('wilayah_kewenangan_lembaga', $user->wilayah_kewenangan)
            ->where('status', 'diajukan') // Hanya tampilkan yang butuh penilaian Subbag
            ->latest()
            ->get();

        return response()->json($proposals);
    }

    /**
     * Menampilkan detail satu usulan.
     */
    public function show(string $id)
    {
        $user = Auth::user();
        $proposal = Proposal::with('user')->findOrFail($id); // 'with('user')' untuk mengambil data pengusul

        // Otorisasi: Pastikan Subbag hanya bisa melihat usulan di wilayahnya
        if ($proposal->wilayah_kewenangan_lembaga !== $user->wilayah_kewenangan) {
            return response()->json(['message' => 'Anda tidak memiliki wewenang untuk melihat usulan ini.'], 403);
        }

        return response()->json($proposal);
    }

    /**
     * Menilai sebuah usulan (approve/reject) dan mengirim notifikasi jika disetujui.
     */
    public function assess(Request $request, string $id)
    {
        $user = Auth::user();

        // Validasi input dari request
        $validated = $request->validate([
            'penilaian' => ['required', Rule::in(['urgent', 'penting', 'normal', 'tidak_penting'])],
            'keputusan' => ['required', Rule::in(['approve', 'reject'])],
            'catatan' => ['nullable', 'string', 'max:5000'],
        ]);

        $proposal = Proposal::findOrFail($id);

        // Otorisasi: Pastikan Subbag hanya bisa menilai usulan di wilayahnya
        if ($proposal->wilayah_kewenangan_lembaga !== $user->wilayah_kewenangan) {
            return response()->json(['message' => 'Anda tidak memiliki wewenang untuk menilai usulan ini.'], 403);
        }

        // Logika untuk menentukan skor dan warna berdasarkan penilaian
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
                $skor = 40; // Sebelumnya 40, bisa disesuaikan
                $warna = 'gray';
                break;
        }

        // Tentukan status akhir berdasarkan keputusan
        $status = ($validated['keputusan'] === 'approve') ? 'diproses_kabid' : 'ditolak_subbag';

        // Update data proposal di database
        $proposal->update([
            'skor' => $skor,
            'warna' => $warna,
            'status' => $status,
            'catatan_subbag' => $validated['catatan'],
        ]);

        // --- LOGIKA PENGIRIMAN NOTIFIKASI ---
        // Kirim notifikasi HANYA JIKA keputusan adalah 'approve'
        if ($validated['keputusan'] === 'approve') {
            // Ambil data user pengusul dari relasi yang ada di model Proposal
            $pengusul = $proposal->user;

            // Pastikan data pengusul ada dan memiliki fcm_token yang tersimpan
            if ($pengusul && $pengusul->fcm_token) {
                // Kirim notifikasi ke pengusul menggunakan kelas notifikasi yang sudah kita buat
                $pengusul->notify(new ProposalApprovedBySubbag($proposal));
            }
        }
        // --- AKHIR LOGIKA NOTIFIKASI ---

        // Kembalikan respons sukses dengan data proposal yang sudah ter-update
        return response()->json([
            'message' => 'Usulan berhasil dinilai.',
            'data' => $proposal->fresh(), // .fresh() untuk mengambil data terbaru dari DB
        ]);
    }
}
