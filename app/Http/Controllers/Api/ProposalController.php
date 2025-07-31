<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProposalController extends Controller
{
    /**
     * Menampilkan daftar usulan milik pengguna yang login.
     */
    public function index(Request $request)
    {
        $proposals = $request->user()->proposals()->latest()->get();
        return response()->json($proposals);
    }

    /**
     * Menyimpan usulan baru.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'alamat' => ['required', 'string'],
            'no_handphone' => ['required', 'string', 'max:15'],
            'jenis_lembaga' => ['required', 'in:pemerintah,non_pemerintah,perguruan_tinggi,bisnis'],
            'wilayah_kewenangan_lembaga' => ['required_if:jenis_lembaga,pemerintah', 'nullable', 'string'],
            'nama_lembaga' => ['required_if:jenis_lembaga,non_pemerintah,perguruan_tinggi,bisnis', 'nullable', 'string'],
            'jenis_usulan' => ['required', 'in:kebutuhan_riset,kebutuhan_inovasi,kebutuhan_kajian'],
            'tema_usulan' => ['required', 'string'],
            'bidang_urusan' => ['required', 'in:transmigrasi,perindustrian,perdagangan,energi_sdm,kehutanan,pertanian,pariwisata'],
            'potensi_kolaborasi' => ['required', 'in:sharing_dana,sharing_prasarana'],
            'pelaksana' => ['required', 'in:pengusul,brida_jateng,kolaborasi'],
            'permasalahan' => ['required', 'string'],
            'keluaran_diharapkan' => ['required', 'string'],
            'sasaran' => ['required', 'string'],
            'file_dukungan' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'], // Max 2MB
        ]);

        $filePath = null;
        if ($request->hasFile('file_dukungan')) {
            $filePath = $request->file('file_dukungan')->store('public/file_dukungan');
        }

        $proposal = Proposal::create(array_merge($validated, [
            'user_id' => $user->id,
            'nama_pengusul' => $user->full_name,
            'email_pengusul' => $user->email,
            'file_dukungan_path' => $filePath,
        ]));

        return response()->json([
            'message' => 'Usulan berhasil diajukan.',
            'data' => $proposal,
        ], 201);
    }

    /**
     * Menampilkan detail satu usulan.
     */
    public function show(Request $request, string $id)
    {
        $proposal = Proposal::findOrFail($id);

        // Pastikan pengusul hanya bisa melihat usulannya sendiri
        if ($request->user()->id !== $proposal->user_id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        return response()->json($proposal);
    }

    public function update(Request $request, string $id)
    {
        $proposal = Proposal::findOrFail($id);
        $user = $request->user();

        // Keamanan: Pastikan hanya pemilik usulan yang bisa mengedit
        if ($user->id !== $proposal->user_id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        // Aturan Bisnis: Hanya bisa diedit jika status masih 'diajukan'
        if ($proposal->status !== 'diajukan') {
            return response()->json(['message' => 'Usulan tidak dapat diubah karena sudah diproses.'], 422);
        }

        // Validasi sama seperti 'store', tapi 'sometimes' agar tidak semua wajib diisi
        $validated = $request->validate([
            'alamat' => ['sometimes', 'required', 'string'],
            'no_handphone' => ['sometimes', 'required', 'string', 'max:15'],
            'jenis_lembaga' => ['sometimes', 'required', 'in:pemerintah,non_pemerintah,perguruan_tinggi,bisnis'],
            'wilayah_kewenangan_lembaga' => ['required_if:jenis_lembaga,pemerintah', 'nullable', 'string'],
            'nama_lembaga' => ['required_if:jenis_lembaga,non_pemerintah,perguruan_tinggi,bisnis', 'nullable', 'string'],
            'jenis_usulan' => ['sometimes', 'required', 'in:kebutuhan_riset,kebutuhan_inovasi,kebutuhan_kajian'],
            'tema_usulan' => ['sometimes', 'required', 'string'],
            'bidang_urusan' => ['sometimes', 'required', 'in:transmigrasi,perindustrian,perdagangan,energi_sdm,kehutanan,pertanian,pariwisata'],
            'potensi_kolaborasi' => ['sometimes', 'required', 'in:sharing_dana,sharing_prasarana'],
            'pelaksana' => ['sometimes', 'required', 'in:pengusul,brida_jateng,kolaborasi'],
            'permasalahan' => ['sometimes', 'required', 'string'],
            'keluaran_diharapkan' => ['sometimes', 'required', 'string'],
            'sasaran' => ['sometimes', 'required', 'string'],
            'file_dukungan' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        // Handle file upload jika ada file baru
        if ($request->hasFile('file_dukungan')) {
            // Hapus file lama jika ada
            if ($proposal->file_dukungan_path) {
                Storage::delete($proposal->file_dukungan_path);
            }
            // Simpan file baru
            $validated['file_dukungan_path'] = $request->file('file_dukungan')->store('public/file_dukungan');
        }

        $proposal->update($validated);

        return response()->json([
            'message' => 'Usulan berhasil diperbarui.',
            'data' => $proposal,
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        $proposal = Proposal::findOrFail($id);
        $user = $request->user();

        // Keamanan: Pastikan hanya pemilik usulan yang bisa menghapus
        if ($user->id !== $proposal->user_id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        // Aturan Bisnis: Hanya bisa dihapus jika status masih 'diajukan'
        if ($proposal->status !== 'diajukan') {
            return response()->json(['message' => 'Usulan tidak dapat dihapus karena sudah diproses.'], 422);
        }

        // Hapus file dukungan dari storage jika ada
        if ($proposal->file_dukungan_path) {
            Storage::delete($proposal->file_dukungan_path);
        }

        $proposal->delete();

        return response()->json(['message' => 'Usulan berhasil dihapus.']);
    }
}
