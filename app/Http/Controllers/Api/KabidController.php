<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use Illuminate\Http\Request;
use App\Models\NotificationLog;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
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

public function decide(Request $request, string $id)
    {
        $validated = $request->validate([
            'keputusan' => ['required', 'in:approve,reject'],
            'catatan'   => ['nullable', 'string'],
        ]);

        $proposal = Proposal::where('status', 'diproses_kabid')->findOrFail($id);

        $status = ($validated['keputusan'] === 'approve') ? 'diproses_kepala' : 'ditolak_kabid';

        $proposal->update([
            'status'         => $status,
            'catatan_kabid' => $validated['catatan'],
        ]);

        // ✅ LOGIKA NOTIFIKASI DAN LOGGING
        $pengusul = $proposal->user;

        if ($pengusul && $pengusul->fcm_token) {
            $messaging = app('firebase.messaging');
            $keputusanTeks = ($validated['keputusan'] === 'approve') ? 'dilanjutkan ke Kepala BRIDA' : 'ditolak';

            $title = 'Status Usulan Diperbarui';
            $body = "Usulan Anda dengan tema '{$proposal->tema_usulan}' telah {$keputusanTeks} oleh Kabid KRPI.";

            $message = CloudMessage::withTarget('token', $pengusul->fcm_token)
                ->withNotification(Notification::create($title, $body))
                ->withData(['proposal_id' => (string)$proposal->id]);

            try {
                $messaging->send($message);

                // SIMPAN LOG KE DATABASE
                NotificationLog::create([
                    'user_id' => $pengusul->id,
                    'title' => $title,
                    'body' => $body,
                ]);

            } catch (\Exception $e) {
                Log::error('FCM Send Error (Kabid): ' . $e->getMessage());
            }
        }
        // --- AKHIR LOGIKA NOTIFIKASI ---

        return response()->json([
            'message' => 'Keputusan Kabid KRPI berhasil disimpan.',
            'data'    => $proposal->fresh(),
        ]);
    }

}