<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobApplication;
use App\Models\Student;

class CertificateController extends Controller
{
    /**
     * Request a Certificate of Employment / Working Student (Student Side)
     */
    public function requestCertificate($id, Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized user.'], 401);
            }

            $student = Student::where('user_id', $user->id)->first();
            if (!$student) {
                return response()->json(['status' => 'error', 'message' => 'Student profile not found.'], 404);
            }

            $application = JobApplication::where('id', $id)
                ->where('student_id', $student->id)
                ->first();

            if (!$application) {
                return response()->json(['status' => 'error', 'message' => 'Application record not found.'], 404);
            }

            $validStatuses = ['accepted', 'completed', 'terminated'];
            if (!in_array(strtolower($application->status), $validStatuses)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Certificates can only be requested for hired or completed jobs.'
                ], 400);
            }

            $application->certificate_status = 'requested';
            $application->certificate_requested_at = now();
            $application->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Certificate of Employment requested successfully. Waiting for employer approval.',
                'application' => $application
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve and Issue Certificate (Employer / Household Side)
     */
    public function approveCertificate($id, Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized user.'], 401);
            }

            $application = JobApplication::with(['job', 'student'])->find($id);
            if (!$application) {
                return response()->json(['status' => 'error', 'message' => 'Application not found.'], 404);
            }

            $signature = $request->input('signature');
            if ($signature) {
                $application->employer_signature = $signature;
            }
            $application->certificate_status = 'approved';
            $application->certificate_issued_at = now();
            $application->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Certificate has been officially issued!',
                'application' => $application
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch formatted data for in-app certificate view
     */
    public function getCertificateData($id, Request $request)
    {
        try {
            $application = JobApplication::with([
                'job.employer.user',
                'job.household.user',
                'job.user',
                'student.user'
            ])->find($id);

            if (!$application) {
                return response()->json(['status' => 'error', 'message' => 'Certificate not found.'], 404);
            }

            $job = $application->job;
            $student = $application->student;

            $companyName = 'Employer';
            $hirerName = '';
            $address = '';

            if ($job) {
                if ($job->household) {
                    $companyName = $job->household->household_name ?? 'Household Employer';
                    $hirerName = $job->household->household_name ?? '';
                    $address = $job->household->location ?? '';
                } elseif ($job->employer) {
                    $companyName = $job->employer->employer_name ?? 'Business Employer';
                    $hirerName = $job->employer->hirer_name ?? $job->employer->employer_name ?? '';
                    $address = $job->employer->location ?? '';
                }
                if (!$address) {
                    $address = $job->location ?? 'Pinamalayan, Oriental Mindoro';
                }
            }

            $cleanDays = '';
            if ($job && $job->available_days) {
                try {
                    $parsed = is_string($job->available_days) ? json_decode($job->available_days, true) : $job->available_days;
                    $cleanDays = is_array($parsed) ? implode(', ', $parsed) : (string)$parsed;
                } catch (\Exception $ex) {
                    $cleanDays = (string)$job->available_days;
                }
            }
            $cleanTime = $job->time_slot ?? '';
            $fullSchedule = trim(($cleanDays ? $cleanDays : '') . ($cleanDays && $cleanTime ? ' • ' : '') . $cleanTime);
            if (!$fullSchedule) {
                $fullSchedule = $application->work_schedule ?: 'Part-Time Working Student Schedule';
            }

            $startDate = $application->start_date ?: ($application->hired_at ? date('F d, Y', strtotime($application->hired_at)) : 'N/A');
            $endDate = $application->status === 'terminated'
                ? ($application->terminated_at ? date('F d, Y', strtotime($application->terminated_at)) : 'Concluded')
                : ($application->end_date ?: 'Present');

            $certNumber = 'DT-COE-' . date('Y') . '-' . str_pad($application->id, 5, '0', STR_PAD_LEFT);
            $issuedDate = $application->certificate_issued_at
                ? date('F d, Y', strtotime($application->certificate_issued_at))
                : date('F d, Y');

            return response()->json([
                'status' => 'success',
                'certificate' => [
                    'certificate_number' => $certNumber,
                    'student_name' => $student->student_name ?? 'Student Applicant',
                    'student_course' => $student->course ?? 'College Student',
                    'student_school' => $student->student_school_name ?? 'Educational Institution',
                    'job_title' => $job->title ?? 'Working Student Employee',
                    'job_category' => $job->category ?? 'General Services',
                    'company_name' => $companyName,
                    'hirer_name' => $hirerName ?: $companyName,
                    'company_address' => $address,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'work_schedule' => $fullSchedule,
                    'agreed_rate' => $application->agreed_rate ? '₱' . $application->agreed_rate : 'Standard Student Rate',
                    'issued_date' => $issuedDate,
                    'certificate_status' => $application->certificate_status ?: 'none',
                    'employer_signature' => $application->employer_signature,
                    'web_view_url' => $request->schemeAndHttpHost() . '/certificate/' . $application->id . '/view',
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
}
