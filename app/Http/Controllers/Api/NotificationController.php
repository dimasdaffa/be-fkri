<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        // Mengambil notifikasi milik user yang sedang login
        $notifications = $user->notificationLogs()->get();
        return response()->json($notifications);
    }
}