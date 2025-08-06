<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

// Hapus use statement yang tidak terpakai
// use App\Notifications\ProposalApprovedBySubbag; 

class SubbagController extends Controller
{
    /**
     * ✅ FUNGSI INDEX YANG DIPERBARUI
     * Menampilkan daftar usulan di wilayah kewenangan Subbag, baik yang
     * masih menunggu penilaian maupun yang sudah diproses oleh Subbag.
     */
    public function index()
    {
        $user = Auth::user();
        $wilayah = $user->wilayah_kewenangan;

        $proposals = Proposal::where('wilayah_kewenangan_lembaga', $wilayah)
            ->where(function ($query) {
                // 1. Tampilkan yang masih butuh penilaian Subbag
                $query->where('status', 'diajukan');

                // 2. Tampilkan yang sudah diteruskan oleh Subbag ke Kabid
                $query->orWhere('status', 'diproses_kabid');
                
                // 3. Tampilkan yang sudah diproses lebih lanjut oleh Kabid/Kepala
                $query->orWhere('status', 'diproses_kepala');
                $query->orWhere('status', 'disetujui');

                // 4. Tampilkan yang DITOLAK di semua level
                $query->orWhere('status', 'ditolak_subbag');
                $query->orWhere('status', 'ditolak_kabid');
                $query->orWhere('status', 'ditolak_kepala');
            })
            ->latest() // Urutkan dari yang terbaru
            ->get();

        return response()->json($proposals);
    }

    /**
     * ✅ FUNGSI SHOW YANG DIPERBARUI
     * Menampilkan detail satu usulan. Otorisasi tetap berdasarkan wilayah.
     */
    public function show(string $id)
    {
        $user = Auth::user();
        $proposal = Proposal::with('user')->findOrFail($id);

        // Otorisasi: Pastikan Subbag hanya bisa melihat usulan di wilayahnya.
        // Ini adalah pengaman utama.
        if ($proposal->wilayah_kewenangan_lembaga !== $user->wilayah_kewenangan) {
            return response()->json(['message' => 'Anda tidak memiliki wewenang untuk melihat usulan ini.'], 403);
        }

        // Tidak perlu filter status di sini karena otorisasi wilayah sudah cukup.
        // Jika Subbag punya wewenang, dia boleh lihat detailnya apapun statusnya.
        return response()->json($proposal);
    }

    /**
     * Menilai sebuah usulan (approve/reject) dan mengirim notifikasi.
     * Kode ini sudah disempurnakan dan tidak perlu diubah lagi.
     */
    public function assess(Request $request, string $id)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'penilaian' => ['required', Rule::in(['urgent', 'penting', 'normal', 'tidak_penting'])],
            'keputusan' => ['required', Rule::in(['approve', 'reject'])],
            'catatan' => ['nullable', 'string', 'max:5000'],
        ]);

        // Otorisasi hanya pada proposal yang statusnya masih 'diajukan'
        $proposal = Proposal::where('status', 'diajukan')->findOrFail($id);
        
        if ($proposal->wilayah_kewenangan_lembaga !== $user->wilayah_kewenangan) {
            return response()->json(['message' => 'Anda tidak memiliki wewenang untuk menilai usulan ini.'], 403);
        }
        
        // Logika skor dan warna...
        $skor = 0;
        $warna = '';
        switch ($validated['penilaian']) {
            case 'urgent': $skor = 100; $warna = 'merah'; break;
            case 'penting': $skor = 80; $warna = 'merah muda'; break;
            case 'normal': $skor = 60; $warna = 'biru'; break;
            case 'tidak_penting': $skor = 40; $warna = 'gray'; break;
        }

        $status = ($validated['keputusan'] === 'approve') ? 'diproses_kabid' : 'ditolak_subbag';

        // Update proposal
        $proposal->update([
            'skor' => $skor,
            'warna' => $warna,
            'status' => $status,
            'catatan_subbag' => $validated['catatan'],
        ]);

        // Logika notifikasi dan logging...
        $pengusul = $proposal->user;
        if ($pengusul && $pengusul->fcm_token) {
            $messaging = app('firebase.messaging');
            $keputusanTeks = ($validated['keputusan'] === 'approve') ? 'dilanjutkan ke Kabid' : 'ditolak';
            $title = 'Status Usulan Diperbarui';
            $body = "Usulan Anda dengan tema '{$proposal->tema_usulan}' telah {$keputusanTeks} oleh Subbag Program.";

            $message = CloudMessage::withTarget('token', $pengusul->fcm_token)
                ->withNotification(Notification::create($title, $body))
                ->withData(['proposal_id' => (string)$proposal->id]);

            try {
                $messaging->send($message);
                NotificationLog::create(['user_id' => $pengusul->id, 'title' => $title, 'body' => $body]);
            } catch (\Exception $e) {
                Log::error('FCM Send Error: ' . $e->getMessage());
            }
        }
        
        return response()->json(['message' => 'Usulan berhasil dinilai.', 'data' => $proposal->fresh()]);
    }
}