<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\JobApplication;

class CertificateWebController extends Controller
{
    public function viewCertificate($id)
    {
        $application = JobApplication::with([
            'job.employer.user',
            'job.household.user',
            'job.user',
            'student.user'
        ])->findOrFail($id);

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

        $data = [
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
            'is_approved' => $application->certificate_status === 'approved',
            'employer_signature' => $application->employer_signature,
        ];

        return view('certificate.print', compact('data', 'application'));
    }
}
