<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Employer;
use App\Models\Household;
use App\Jobs\AnalyzeVerificationDocument;

class VerificationController extends Controller
{
    public function uploadVerificationDoc(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:employer,household',
            'valid_id_path' => 'nullable|file|mimes:jpeg,png,jpg,pdf',
            'certificate_path' => 'nullable|file|mimes:jpeg,png,jpg,pdf',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();
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
                    $path = $file->store($folder, 'public');
                    
                    $profile->valid_id_path = $path;
                    $profile->isVerified = 0;
                    $profile->rejection_reason = null;
                    $profile->save();

                    // job Dispatch
                    AnalyzeVerificationDocument::dispatch($profile, $path, $file->getClientMimeType(), 'valid_id');
                }

                // 2. PROSESO PARA SA BUSINESS CERTIFICATE / PERMIT (Employer lang)
                if ($request->type === 'employer' && $request->hasFile('certificate_path')) {
                    $certFile = $request->file('certificate_path');
                    $certPath = $certFile->store('employers/certificates', 'public');
                    
                    $profile->employer_certificate_path = $certPath;
                    $profile->isVerified = 0;
                    $profile->rejection_reason = null;
                    $profile->save();

                    // I-dispatch sa Background Job ang Certificate AI analysis
                    AnalyzeVerificationDocument::dispatch($profile, $certPath, $certFile->getClientMimeType(), 'certificate');
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