<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage; // ✅ 1. IMPORT THE STORAGE FACADE

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

    /**
     * ✅ 2. ADD THE $appends PROPERTY.
     * This tells Laravel to always include our new 'file_dukungan_url'
     * attribute whenever this model is converted to JSON.
     */
    protected $appends = ['file_dukungan_url'];


    /**
     * ✅ 3. ADD THE ACCESSOR METHOD.
     * This method generates the full, public URL for the stored file path.
     * It will be called automatically by Laravel because of the $appends property.
     *
     * @return string|null
     */
    public function getFileDukunganUrlAttribute(): ?string
    {
        if ($this->file_dukungan_path) {
            // This function correctly converts a path like "public/file.pdf"
            // into a full URL like "http://your-domain.com/storage/file.pdf"
            return Storage::url($this->file_dukungan_path);
        }

        return null;
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
