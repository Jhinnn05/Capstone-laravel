<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Http\Requests\StoreEnrollmentRequest;
use App\Http\Requests\UpdateEnrollmentRequest;

class EnrollmentController extends Controller
{
   /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEnrollmentRequest $request)
    {
        $validatedData = $request->validate([
            'LRN' => 'required|digits_between:10,14',
            'regapproval_date' => 'required|date|max:255',
            'payment_approval' => 'required|string|max:255',
            'grade_level' => 'required|string|max:12',
            'guardian_name' => 'nullable|string|max:255',
            'last_attended' => 'required|string|max:255',
            'public_private' => 'required|string|max:255',
            'date_register' => 'required|date|max:255',
            'strand' => 'nullable|string|max:255',
            'school_year' => 'required|string|max:255',
        ]);
        $validatedData['strand'] = $validatedData['strand'] ?? '';
        $enrollment = Enrollment::create($validatedData);
        return response()->json($enrollment, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Enrollment $enrollment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEnrollmentRequest $request, Enrollment $enrollment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Enrollment $enrollment)
    {
        //
    }
}
