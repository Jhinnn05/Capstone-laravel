<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;

class StudentController extends Controller
{
   /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $student = Student::all();
        return $student;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorestudentRequest $request)
    {
        $validatedData = $request->validate([
            'LRN' => 'required|digits_between:10,14',
            'fname' => 'required|string|max:255',
            'lname' => 'required|string|max:255',
            'mname' => 'required|string|max:12',
            'suffix' => 'nullable|string|max:255',
            'bdate' => 'required|string|max:255',
            'bplace' => 'required|string|max:255',
            'gender' => 'required|string|max:255',
            'religion' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'contact_no' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:students',
            'password' => 'required|string|min:8|max:255'
        ]);
        $validatedData['suffix'] = $validatedData['suffix'] ?? '';

        $student = Student::create($validatedData);
        return response()->json($student, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(student $student)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatestudentRequest $request, student $student)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(student $student)
    {
        //
    }
}
