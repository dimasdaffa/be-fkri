<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Proposal;
use App\Notifications\ProposalApprovedBySubbag;
use Illuminate\Http\Request;

class TestNotificationController extends Controller
{
    public function testFcm(Request $request)
    {
        // Validasi input
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        // Cari user berdasarkan ID
        $user = User::findOrFail($request->user_id);

        // Cek apakah user memiliki FCM token
        if (!$user->fcm_token) {
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak memiliki FCM token'
            ], 400);
        }

        // Ambil proposal terbaru untuk data sample notifikasi
        $proposal = Proposal::latest()->first();

        if (!$proposal) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tidak ada proposal yang dapat digunakan untuk test'
            ], 404);
        }

        // Kirim notifikasi test
        $user->notify(new ProposalApprovedBySubbag($proposal));

        return response()->json([
            'status' => 'success',
            'message' => 'Notifikasi berhasil dikirim',
            'user' => $user->only(['id', 'full_name', 'email']),
            'proposal' => $proposal->only(['id', 'tema_usulan']),
        ]);
    }
}
