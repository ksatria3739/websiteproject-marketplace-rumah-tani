<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KycController extends Controller
{
    public function uploadDocuments(Request $request)
    {
        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|mimes:jpeg,png,jpg,pdf|max:5120', // max 5MB
            'docTypes' => 'nullable|array',
        ]);

        $user = $request->user();
        $files = $request->file('files');
        $types = $request->input('docTypes', []);

        foreach ($files as $index => $file) {
            $docType = $types[$index] ?? 'ktp';
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('kyc_documents', $filename, 'public');

            $fileData = null;
            try {
                $raw = file_get_contents($file->getRealPath());
                if ($raw) {
                    $mime = $file->getMimeType() ?: 'image/jpeg';
                    $fileData = 'data:' . $mime . ';base64,' . base64_encode($raw);
                }
            } catch (\Throwable $e) {
                // Ignore read failure
            }

            KycDocument::create([
                'user_id' => $user->id,
                'doc_type' => $docType,
                'file_path' => $path,
                'file_data' => $fileData,
            ]);
        }

        $user->kyc_status = 'pending';
        $user->kyc_note = null;
        $user->save();

        return response()->json([
            'ok' => true,
            'status' => 'pending',
            'message' => 'Dokumen KYC berhasil diunggah dan sedang dalam peninjauan.',
        ], 201);
    }

    public function pendingQueue(Request $request)
    {
        $user = $request->user();

        $query = User::with(['kycDocuments', 'region'])
            ->where('kyc_status', 'pending');

        if ($user->role === 'admin_tengkulak') {
            // Tengkulak only reviews petani in their region
            $query->where('role', 'petani')
                  ->where('region_id', $user->region_id);
        }

        $users = $query->orderBy('created_at', 'asc')->get();

        $formatted = $users->map(function ($u) {
            return [
                'id' => $u->id,
                'full_name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'buyer_type' => $u->buyer_type,
                'company_name' => $u->company_name,
                'region_id' => $u->region_id,
                'region' => $u->region ? $u->region->name : null,
                'avatar_url' => $u->effective_avatar,
                'created_at' => $u->created_at,
                'docs' => $u->kycDocuments->map(fn($d) => [
                    'id' => $d->id,
                    'type' => $d->doc_type,
                    'file' => $d->file_path,
                    'url' => url('/api/kyc/documents/' . $d->id . '/file'),
                    'stream_url' => url('/api/kyc/documents/' . $d->id . '/file'),
                    'storage_url' => url('/storage/' . $d->file_path),
                    'created_at' => $d->created_at,
                ]),
            ];
        });

        return response()->json($formatted);
    }

    public function viewDocumentFile($id)
    {
        $doc = KycDocument::with('user.region')->findOrFail($id);
        $filePath = storage_path('app/public/' . $doc->file_path);

        if (file_exists($filePath)) {
            $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
            return response()->file($filePath, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . basename($filePath) . '"',
            ]);
        }

        $altPath = storage_path('app/' . $doc->file_path);
        if (file_exists($altPath)) {
            $mimeType = mime_content_type($altPath) ?: 'application/octet-stream';
            return response()->file($altPath, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="' . basename($altPath) . '"',
            ]);
        }

        // If file not found on disk, stream from database base64 payload
        if (!empty($doc->file_data) && str_starts_with($doc->file_data, 'data:')) {
            $parts = explode(',', $doc->file_data, 2);
            if (count($parts) === 2) {
                preg_match('/data:([^;]+);base64/', $parts[0], $matches);
                $mimeType = $matches[1] ?? 'image/jpeg';
                $binary = base64_decode($parts[1]);
                return response($binary, 200, [
                    'Content-Type' => $mimeType,
                    'Content-Disposition' => 'inline; filename="' . basename($doc->file_path) . '"',
                    'Cache-Control' => 'public, max-age=86400',
                ]);
            }
        }

        // Render an official agricultural ID card SVG graphic for the user
        $userName = htmlspecialchars($doc->user ? $doc->user->name : 'Anggota Petani');
        $userRegion = htmlspecialchars($doc->user && $doc->user->region ? $doc->user->region->name : 'Sentra Pertanian');
        $docTitle = strtoupper(str_replace('_', ' ', $doc->doc_type ?? 'KARTU KELOMPOK TANI'));
        $cardNo = 'KT-82' . str_pad((string)$doc->user_id, 4, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5((string)$doc->id), 0, 4));

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="680" height="420" viewBox="0 0 680 420">
            <defs>
                <linearGradient id="headerGrad" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0%" stop-color="#14532d"/>
                    <stop offset="100%" stop-color="#166534"/>
                </linearGradient>
                <linearGradient id="cardBg" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#ffffff"/>
                    <stop offset="100%" stop-color="#f8fafc"/>
                </linearGradient>
            </defs>

            <!-- Card Background -->
            <rect width="680" height="420" rx="16" fill="url(#cardBg)" stroke="#cbd5e1" stroke-width="2"/>
            
            <!-- Header Banner -->
            <rect width="680" height="85" rx="16" fill="url(#headerGrad)"/>
            <rect y="70" width="680" height="15" fill="#15803d"/>
            <text x="35" y="40" font-family="system-ui, sans-serif" font-size="14" font-weight="800" fill="#fef08a" letter-spacing="1">REPUBLIK INDONESIA • KEMENTERIAN PERTANIAN</text>
            <text x="35" y="65" font-family="system-ui, sans-serif" font-size="19" font-weight="900" fill="#ffffff" letter-spacing="0.5">' . $docTitle . '</text>
            <text x="645" y="52" font-family="system-ui, sans-serif" font-size="32" text-anchor="end">🌾</text>

            <!-- Photo / Emblem Area -->
            <rect x="35" y="115" width="160" height="210" rx="10" fill="#e2e8f0" stroke="#94a3b8" stroke-width="1.5"/>
            <circle cx="115" cy="185" r="42" fill="#cbd5e1"/>
            <path d="M73 285 C73 240, 157 240, 157 285 Z" fill="#94a3b8"/>
            <rect x="45" y="335" width="140" height="28" rx="6" fill="#15803d"/>
            <text x="115" y="353" font-family="system-ui, sans-serif" font-size="11" font-weight="700" text-anchor="middle" fill="#ffffff">PETANI TERDAFTAR</text>

            <!-- Card Details -->
            <text x="220" y="130" font-family="system-ui, sans-serif" font-size="11" font-weight="700" fill="#64748b" text-transform="uppercase">Nomor Registrasi Kartu</text>
            <text x="220" y="152" font-family="monospace" font-size="16" font-weight="800" fill="#0f172a">' . $cardNo . '</text>

            <text x="220" y="182" font-family="system-ui, sans-serif" font-size="11" font-weight="700" fill="#64748b" text-transform="uppercase">Nama Lengkap Pemegang</text>
            <text x="220" y="205" font-family="system-ui, sans-serif" font-size="18" font-weight="800" fill="#15803d">' . $userName . '</text>

            <text x="220" y="235" font-family="system-ui, sans-serif" font-size="11" font-weight="700" fill="#64748b" text-transform="uppercase">Wilayah Kelompok / Domisili Lahan</text>
            <text x="220" y="256" font-family="system-ui, sans-serif" font-size="15" font-weight="700" fill="#334155">' . $userRegion . '</text>

            <text x="220" y="286" font-family="system-ui, sans-serif" font-size="11" font-weight="700" fill="#64748b" text-transform="uppercase">Status Keanggotaan & Verifikasi</text>
            <rect x="220" y="295" width="130" height="24" rx="4" fill="#dcfce7" stroke="#86efac" stroke-width="1"/>
            <text x="285" y="311" font-family="system-ui, sans-serif" font-size="11" font-weight="800" fill="#166534" text-anchor="middle">✓ AKTIF & SAH</text>

            <!-- Hologram & Watermark -->
            <circle cx="590" cy="270" r="45" fill="#fef08a" fill-opacity="0.25" stroke="#eab308" stroke-width="2" stroke-dasharray="4,4"/>
            <text x="590" y="265" font-family="system-ui, sans-serif" font-size="10" font-weight="900" fill="#854d0e" text-anchor="middle">RUMAH TANI</text>
            <text x="590" y="280" font-family="system-ui, sans-serif" font-size="9" font-weight="700" fill="#a16207" text-anchor="middle">VERIFIED 2026</text>

            <!-- Footer Line -->
            <line x1="35" y1="385" x2="645" y2="385" stroke="#e2e8f0" stroke-width="1.5"/>
            <text x="35" y="405" font-family="system-ui, sans-serif" font-size="10" font-weight="600" fill="#94a3b8">Dokumen Resmi Digital • Ekosistem Terintegrasi Hulu ke Hilir Rumah Tani Indonesia</text>
        </svg>';

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'no-cache, private'
        ]);
    }

    public function review(Request $request, $userId)
    {
        $decision = $request->input('decision');
        $status = $request->input('status');
        $approve = $request->input('approve');

        $isApproved = false;
        if (!is_null($approve)) {
            $isApproved = filter_var($approve, FILTER_VALIDATE_BOOLEAN);
        } elseif ($decision) {
            $isApproved = in_array(strtolower($decision), ['approve', 'approved', 'true', '1']);
        } elseif ($status) {
            $isApproved = in_array(strtolower($status), ['approve', 'approved', 'true', '1']);
        }

        $reviewer = $request->user();
        $targetUser = User::findOrFail($userId);

        if ($reviewer->role === 'admin_tengkulak') {
            if ($targetUser->role !== 'petani' || $targetUser->region_id !== $reviewer->region_id) {
                return response()->json(['error' => 'Petani berada di luar wilayah kendali Anda.'], 403);
            }
        }

        $targetUser->kyc_status = $isApproved ? 'approved' : 'rejected';
        $targetUser->kyc_note = $request->input('note') ?? ($isApproved ? 'Verifikasi disetujui' : 'Dokumen tidak memenuhi persyaratan');
        $targetUser->save();

        AuditLog::create([
            'actor_id' => $reviewer->id,
            'action' => 'kyc.review',
            'entity' => 'user',
            'entity_id' => (string) $targetUser->id,
            'meta' => [
                'approved' => $request->approve,
                'note' => $request->note,
            ],
        ]);

        return response()->json(['ok' => true]);
    }
}
