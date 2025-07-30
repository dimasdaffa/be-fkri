<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Foreign key ke tabel users
            $table->string('nama_pengusul');
            $table->text('alamat');
            $table->string('no_handphone');
            $table->string('email_pengusul');
            $table->enum('jenis_lembaga', ['pemerintah', 'non_pemerintah', 'perguruan_tinggi', 'bisnis']);
            $table->string('wilayah_kewenangan_lembaga')->nullable();
            $table->string('nama_lembaga')->nullable();
            $table->enum('jenis_usulan', ['kebutuhan_riset', 'kebutuhan_inovasi', 'kebutuhan_kajian']);
            $table->string('tema_usulan');
            $table->enum('bidang_urusan', ['transmigrasi', 'perindustrian', 'perdagangan', 'energi_sdm', 'kehutanan', 'pertanian', 'pariwisata']);
            $table->enum('potensi_kolaborasi', ['sharing_dana', 'sharing_prasarana']);
            $table->enum('pelaksana', ['pengusul', 'brida_jateng', 'kolaborasi']);
            $table->text('permasalahan');
            $table->text('keluaran_diharapkan');
            $table->text('sasaran');
            $table->string('file_dukungan_path')->nullable();
            $table->enum('status', ['diajukan', 'diproses_subbag', 'diproses_kabid', 'diproses_kepala', 'disetujui', 'ditolak'])->default('diajukan');
            $table->integer('skor')->nullable();
            $table->string('warna')->nullable();
            $table->text('catatan_subbag')->nullable();
            $table->text('catatan_kabid')->nullable();
            $table->text('catatan_kepala')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
