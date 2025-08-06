<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $fillable = ['user_id', 'title', 'body', 'read_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
