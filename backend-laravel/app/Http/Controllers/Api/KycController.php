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

            KycDocument::create([
                'user_id' => $user->id,
                'doc_type' => $docType,
                'file_path' => $path,
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
                'created_at' => $u->created_at,
                'docs' => $u->kycDocuments->map(fn($d) => [
                    'type' => $d->doc_type,
                    'file' => $d->file_path,
                ]),
            ];
        });

        return response()->json($formatted);
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
