<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Employer;
use App\Models\Household;
use App\Jobs\AnalyzeVerificationDocument;
use App\Services\UploadStorage;

class VerificationController extends Controller
{
    public function uploadVerificationDoc(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:employer,household',
            'valid_id_path' => 'nullable|file|mimes:jpeg,png,jpg,pdf,webp,jfif,heic,heif,bmp|max:10240',
            'certificate_path' => 'nullable|file|mimes:jpeg,png,jpg,pdf,webp,jfif,heic,heif,bmp|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        abort_unless($user->role === $request->input('type'), 403);
        DB::beginTransaction();

        try {
            $profile = null;

            if ($request->type === 'employer') {
                $profile = Employer::where('user_id', $user->id)->first();
            } elseif ($request->type === 'household') {
                $profile = Household::where('user_id', $user->id)->first();
            }

            if ($profile) {
                // 1. PROSESO PARA SA VALID ID
                if ($request->hasFile('valid_id_path')) {
                    $file = $request->file('valid_id_path');
                    $folder = ($request->type === 'employer') ? 'employers/validID' : 'households/validIDs';
                    $path = app(UploadStorage::class)->store($file, $folder);
                    
                    $profile->valid_id_path = $path;
                    $profile->isVerified = 0;
                    $profile->rejection_reason = null;
                    $profile->ai_is_valid = null;
                    $profile->ai_remarks = null;
                    $profile->save();

                    // job Dispatch
                    AnalyzeVerificationDocument::dispatch($profile, $path, $file->getMimeType(), 'valid_id')->afterCommit();
                }

            
                // 2. PROSESO PARA SA BUSINESS CERTIFICATE / PERMIT (Employer lang)
                if ($request->type === 'employer' && $request->hasFile('certificate_path')) {
                    $certFile = $request->file('certificate_path');
                    $certPath = app(UploadStorage::class)->store($certFile, 'employers/certificates');
                    
                    $profile->employer_certificate_path = $certPath;
                    $profile->isVerified = 0;
                    $profile->rejection_reason = null;
                    $profile->cert_ai_is_valid = null;
                    $profile->cert_ai_remarks = null;
                    $profile->save();

                    // I-dispatch sa Background Job ang Certificate AI analysis
                    AnalyzeVerificationDocument::dispatch($profile, $certPath, $certFile->getMimeType(), 'certificate')->afterCommit();
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Documents uploaded successfully! Processing verification in background.',
                'profile' => $profile
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
