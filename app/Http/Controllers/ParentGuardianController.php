<?php

namespace App\Http\Controllers;

use App\Models\ParentGuardian;
use App\Models\Student;
use App\Models\Enrollment;
use App\Models\tuition;
use App\Models\Payment;
use App\Http\Requests\StoreParentGuardianRequest;
use App\Http\Requests\UpdateParentGuardianRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ParentGuardianController extends Controller
{
     /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Log the incoming request
        Log::info('Received request in index method', ['request' => $request->all()]);

        // Validate the request
        $request->validate([
            'email' => 'required|email',
        ]);

        // Retrieve the email from the request
        $email = $request->input('email');
        Log::info('Validating email', ['email' => $email]);

        // Perform a join query to get parent guardians with their associated students, classes, and sections
        $parents = ParentGuardian::select(
                'parent_guardians.guardian_id',
                'parent_guardians.LRN',
                'parent_guardians.fname',
                'parent_guardians.lname',
                'parent_guardians.relationship',
                'parent_guardians.contact_no',
                'parent_guardians.email',
                'students.LRN as student_LRN',
                'students.fname as student_fname',
                'students.lname as student_lname',
                'rosters.roster_id',
                'classes.class_id',
                'sections.section_id',
                'sections.section_name',
                'sections.grade_level',
                'sections.strand'
            )
            ->join('students', 'students.LRN', '=', 'parent_guardians.LRN') // Join parents to students via LRN
            ->join('rosters', 'rosters.LRN', '=', 'students.LRN') // Join students to rosters via LRN
            ->join('classes', 'classes.class_id', '=', 'rosters.class_id') // Join rosters to classes
            ->join('sections', 'sections.section_id', '=', 'classes.section_id') // Join classes to sections
            ->where('parent_guardians.email', $email) // Only fetch matching email
            ->get()
            ->groupBy('email');

        // Log the number of parents found
        Log::info('Number of parents found', ['count' => $parents->count()]);

        // Format the response to include LRNs, associated students, and sections
        $formattedParents = $parents->map(function ($group) {
            // Get all LRNs for this guardian
            $lrns = $group->pluck('LRN')->toArray();

            // Use unique LRNs for students to avoid duplicates
            $students = $group->unique('student_LRN')->map(function ($item) {
                return [
                    'LRN' => $item->student_LRN,
                    'fname' => $item->student_fname,
                    'lname' => $item->student_lname,
                    'section' => [
                        'section_id' => $item->section_id,
                        'section_name' => $item->section_name,
                        'grade_level' => $item->grade_level,
                        'strand' => $item->strand,
                    ],
                ];
            });

            return [
                'fname' => $group[0]->fname,
                'lname' => $group[0]->lname,
                'relationship' => $group[0]->relationship,
                'contact_no' => $group[0]->contact_no,
                'email' => $group[0]->email,
                'LRNs' => $lrns,
                'students' => $students->values(), // Include the fetched students with sections
            ];
        })->values();

        // Log the final response
        Log::info('Formatted parents response', ['response' => $formattedParents]);

        return response()->json($formattedParents);
    }

public function displaySOA($LRN) {
    // No need to validate LRN here, as it's a route parameter
    $id = $LRN;

    // Query the payments and related data
    $payments = DB::table('payments')
        ->join('enrollments', 'payments.LRN', '=', 'enrollments.LRN')
        ->join('students', 'payments.LRN', '=', 'students.LRN')
        ->leftJoin('tuitions', 'enrollments.grade_level', '=', 'tuitions.grade_level')
        ->select(
            'students.lname',
            'students.fname',
            'students.mname',
            'payments.amount_paid',
            'payments.description',
            'payments.OR_number',
            'payments.date_of_payment',
            'tuitions.tuition',
            DB::raw('COALESCE(SUM(payments.amount_paid), 0) AS total_paid'),
            DB::raw('COALESCE(SUM(tuitions.tuition), 0) AS total_tuition')
        )
        ->where('payments.LRN', $id)
        ->groupBy(
            'students.lname',
            'students.fname',
            'students.mname',
            'payments.amount_paid',
            'payments.description',
            'payments.OR_number',
            'payments.date_of_payment',
            'tuitions.tuition',
        )
        ->get();

        // Calculate the tuition fee (assumed to be the same for the student)
        $tuition = $payments->isNotEmpty() ? $payments[0]->total_tuition : 0;

        // Initialize remaining balance
        $remainingBalance = $tuition;

        // Create an array to hold the payment details with running balance
        $paymentDetails = [];

        foreach ($payments as $payment) {
            // Subtract the current payment from the remaining balance
            $remainingBalance -= $payment->amount_paid;

            // Add to payment details with the current balance
            $paymentDetails[] = [
                'name' => "{$payment->lname} {$payment->fname} {$payment->mname}",
                'tuition' => $payment->tuition,
                'OR_number' => $payment->OR_number,
                'description' => $payment->description,
                'amount_paid' => $payment->amount_paid,
                'date_of_payment' => $payment->date_of_payment,
                'remaining_balance' => $remainingBalance
            ];
        }

        // Return the response
        return response()->json([
            'tuition_fee' => $tuition,
            'payments' => $paymentDetails,
            'remaining_balance' => $remainingBalance,
        ], 200);
    }

    public function getClassGrades($cid) {
    $grades = DB::table('rosters')
        ->join('students', 'rosters.LRN', '=', 'students.LRN')
        ->leftJoin('grades', 'rosters.LRN', '=', 'grades.LRN')
        ->join('classes', 'rosters.class_id', '=', 'classes.class_id')  // Joining classes table
        ->join('subjects', 'classes.subject_id', '=', 'subjects.subject_id')  // Joining subjects table
        ->select(
            'students.LRN',
            'students.fname AS student_fname',
            'students.lname AS student_lname',
            'students.contact_no AS student_contact_no',
            'subjects.subject_name',  // Selecting subject_name from subjects table
            DB::raw("MAX(CASE WHEN grades.term = 'First Quarter' THEN grades.grade ELSE NULL END) AS grade_Q1"),
            DB::raw("MAX(CASE WHEN grades.term = 'Second Quarter' THEN grades.grade ELSE NULL END) AS grade_Q2"),
            DB::raw("MAX(CASE WHEN grades.term = 'Third Quarter' THEN grades.grade ELSE NULL END) AS grade_Q3"),
            DB::raw("MAX(CASE WHEN grades.term = 'Fourth Quarter' THEN grades.grade ELSE NULL END) AS grade_Q4")
        )
        ->where('students.LRN', '=', $cid)  // Filtering by student LRN only
        ->groupBy('students.LRN', 'students.fname', 'students.lname', 'students.contact_no', 'subjects.subject_name')
        ->orderBy('students.lname')
        ->get();
        return response()->json($grades);  // Return results as JSON
    }
    public function getAttendance($lcn) {
        $attendance = DB::table('attendances')
            ->select(
                'attendances.LRN',
                'attendances.status AS attendance_status',
                'attendances.date'  // Use the date column
            )
            ->where('attendances.LRN', '=', $lcn)  // Filtering by student LRN
            ->orderBy('attendances.date')  // Order by date
            ->get();
    
        return response()->json($attendance);  // Return results as JSON
    }
    
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreParentGuardianRequest $request)
    {
         // Validate incoming request data
        $validatedData = $request->validate([
            'LRN' => 'required|array',
            'LRN.*' => 'exists:students,LRN',
            'fname' => 'required|string|max:255',
            'mname' => 'required|string|max:12',
            'lname' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'relationship' => 'required|string|max:255',
            'contact_no' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:parent_guardians,email', // Ensure email is unique
            'password' => 'required|string|min:8|max:255'
        ]);

        // Check if guardian already exists based on other criteria
        $existingGuardian = ParentGuardian::where('fname', $validatedData['fname'])
                                        ->where('lname', $validatedData['lname'])
                                        ->where('contact_no', $validatedData['contact_no'])
                                        ->first();

        if ($existingGuardian) {
            return response()->json(['message' => 'Guardian already exists.'], 409);
        }

        // Create new ParentGuardian record
        $parents = [];
        foreach ($validatedData['LRN'] as $l) {
            $parentData = array_merge($validatedData, ['LRN' => $l]);
            $parents[] = ParentGuardian::create($parentData);
        }

        return response()->json($parents, 201);

    }

    /**
     * Display the specified resource.
     */
    public function show(ParentGuardian $parentGuardian)
    {
        
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateParentGuardianRequest $request, ParentGuardian $parentGuardian)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($email)
    {
        // Find all ParentGuardians by email
    $parentGuardians = ParentGuardian::where('email', $email)->get();

    // Check if any records exist
    if ($parentGuardians->isEmpty()) {
        return response()->json(['message' => 'No Parent/Guardian found with that email.'], 404);
    }
    try {
        // Delete all matching records
        foreach ($parentGuardians as $guardian) {
            $guardian->delete();
        }

        return response()->json(['message' => 'All Parent/Guardians with that email deleted successfully.'], 200);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Error deleting Parent/Guardians: ' . $e->getMessage()], 500);
    }
    }
     
}
