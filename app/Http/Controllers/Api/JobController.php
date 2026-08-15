<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobPosting; // <--- Siguraduhing nandito ito!

class JobController extends Controller
{
    public function store(Request $request)
    {
       $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'salary' => 'required|numeric',
            'schedules' => 'required',
        ]);

        $jobPosting = JobPosting::create([
            'user_id' => $request->user() ? $request->user()->id : 1, // Pansamantalang default sa ID 1 para madaling ma-test
            'title' => $request->title,
            'description' => $request->description,
            'salary' => $request->salary,
            'schedules' => json_encode($request->schedules),
            'requirements' => json_encode($request->requirements),
            'skills' => json_encode($request->skills),
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Job posted successfully!',
            'data' => $jobPosting
        ], 201);
    
    }
}