<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
// 1. Import kelas Notifikasi yang sudah kita buat
use App\Notifications\ProposalApprovedBySubbag;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as MessagingNotification;

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

        $validated = $request->validate([
            'penilaian' => ['required', Rule::in(['urgent', 'penting', 'normal', 'tidak_penting'])],
            'keputusan' => ['required', Rule::in(['approve', 'reject'])],
            'catatan' => ['nullable', 'string', 'max:5000'],
        ]);

        $proposal = Proposal::findOrFail($id);

        if ($proposal->wilayah_kewenangan_lembaga !== $user->wilayah_kewenangan) {
            return response()->json(['message' => 'Anda tidak memiliki wewenang untuk menilai usulan ini.'], 403);
        }

        // ❌ HAPUS BLOK FCM DARI SINI, KITA PINDAHKAN KE BAWAH SETELAH UPDATE

        $skor = 0;
        $warna = '';
        switch ($validated['penilaian']) {
            case 'urgent': $skor = 100; $warna = 'merah'; break;
            case 'penting': $skor = 80; $warna = 'merah muda'; break;
            case 'normal': $skor = 60; $warna = 'biru'; break;
            case 'tidak_penting': $skor = 40; $warna = 'gray'; break;
        }

        $status = ($validated['keputusan'] === 'approve') ? 'diproses_kabid' : 'ditolak_subbag';

        // Update proposal di database
        $proposal->update([
            'skor' => $skor,
            'warna' => $warna,
            'status' => $status,
            'catatan_subbag' => $validated['catatan'],
        ]);

        // ✅ PINDAHKAN LOGIKA NOTIFIKASI KE SINI, SETELAH PROPOSAL BERHASIL DI-UPDATE
        $pengusul = $proposal->user;

        // Kirim notifikasi HANYA JIKA pengusul ada dan punya fcm_token
        if ($pengusul && $pengusul->fcm_token) {
            $messaging = app('firebase.messaging');
            $keputusanTeks = ($validated['keputusan'] === 'approve') ? 'dilanjutkan ke Kabid' : 'ditolak';

            $title = 'Status Usulan Diperbarui';
            $body = "Usulan Anda dengan tema '{$proposal->tema_usulan}' telah {$keputusanTeks} oleh Subbag Program.";

            $message = CloudMessage::withTarget('token', $pengusul->fcm_token)
                ->withNotification(MessagingNotification::create($title, $body))
                ->withData(['proposal_id' => (string)$proposal->id]); // Kirim data tambahan

            try {
                $messaging->send($message);

                // ✅ SIMPAN LOG KE DATABASE SETELAH NOTIFIKASI BERHASIL DIKIRIM
                NotificationLog::create([
                    'user_id' => $pengusul->id,
                    'title' => $title,
                    'body' => $body,
                ]);

            } catch (\Exception $e) {
                Log::error('FCM Send Error: ' . $e->getMessage());
            }
        }
        
        // ❌ HAPUS BLOK INI - Ini adalah logika notifikasi lama yang duplikat
        /*
        if ($validated['keputusan'] === 'approve') {
            $pengusul = $proposal->user;
            if ($pengusul && $pengusul->fcm_token) {
                $pengusul->notify(new ProposalApprovedBySubbag($proposal));
            }
        }
        */

        return response()->json([
            'message' => 'Usulan berhasil dinilai.',
            'data' => $proposal->fresh(),
        ]);
    }

}
