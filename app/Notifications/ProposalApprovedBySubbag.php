<?php

namespace App\Notifications;

use App\Models\Proposal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FCMChannel;
use NotificationChannels\Fcm\FCMMessage;
// 1. Tambahkan import untuk kelas Notification dari library FCM
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class ProposalApprovedBySubbag extends Notification
{
    use Queueable;

    protected $proposal;

    public function __construct(Proposal $proposal)
    {
        $this->proposal = $proposal;
    }

    public function via($notifiable)
    {
        return [FCMChannel::class];
    }

    public function toFCM($notifiable)
    {
        return FCMMessage::create()
            ->setData([ // Data tambahan untuk di-handle di aplikasi Java Anda
                'proposal_id' => (string) $this->proposal->id,
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK', // Standar umum, bisa disesuaikan
            ])
            ->setNotification(FcmNotification::create() // Gunakan objek FcmNotification yang sudah di-import
                ->setTitle('Usulan Anda Diproses!')
                ->setBody('Usulan "'.$this->proposal->tema_usulan.'" telah disetujui Subbag dan diteruskan ke Kabid.')
            );
    }
}
