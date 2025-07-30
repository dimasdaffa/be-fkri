<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Proposal extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'nama_pengusul',
        'alamat',
        'no_handphone',
        'email_pengusul',
        'jenis_lembaga',
        'wilayah_kewenangan_lembaga',
        'nama_lembaga',
        'jenis_usulan',
        'tema_usulan',
        'bidang_urusan',
        'potensi_kolaborasi',
        'pelaksana',
        'permasalahan',
        'keluaran_diharapkan',
        'sasaran',
        'file_dukungan_path',
        'status',
        'skor',
        'warna',
        'catatan_subbag',
        'catatan_kabid',
        'catatan_kepala',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
