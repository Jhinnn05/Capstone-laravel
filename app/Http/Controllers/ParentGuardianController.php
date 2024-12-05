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

    public function displaySOA($LRN)
    {
        // Fetch the filename from the financial_statement table for the given LRN
        $filename = DB::table('financial_statements')
            ->where('LRN', $LRN)
            ->value('filename'); // Assuming the column storing the filename is named 'filename'

        // Return the filename in the response
        return response()->json([
            'filename' => $filename,
        ], 200);
    }
    public function getClassGrades($cid) {
        // Step 1: Get the latest school year
        $latestSchoolYear = DB::table('enrollments')
            ->where('LRN', $cid) // Ensure you are looking for the specific student
            ->max('school_year');
    
        // Step 2: Query to get the grades for the latest school year
        $grades = DB::table('rosters')
            ->join('students', 'rosters.LRN', '=', 'students.LRN')
            ->join('enrollments', 'students.LRN', '=', 'enrollments.LRN')
            ->join('classes', 'rosters.class_id', '=', 'classes.class_id')
            ->join('sections', 'classes.section_id', '=', 'sections.section_id')
            ->leftJoin('subjects', 'classes.subject_id', '=', 'subjects.subject_id')
            ->leftJoin('grades', function($join) {
                $join->on('rosters.LRN', '=', 'grades.LRN')
                     ->on('rosters.class_id', '=', 'grades.class_id');
            })
            ->select(
                'students.LRN',
                DB::raw('CONCAT(students.fname, " ", LEFT(students.mname, 1), ". ", students.lname) AS student_name'),
                'students.contact_no AS student_contact_no',
                'subjects.subject_id',
                'subjects.subject_name',
                'subjects.strand',
                'subjects.grade_level',
                'enrollments.school_year',
                DB::raw('MAX(CASE WHEN grades.term = "First Quarter" THEN grades.grade ELSE NULL END) AS `First_Quarter`'),
                DB::raw('MAX(CASE WHEN grades.term = "Second Quarter" THEN grades.grade ELSE NULL END) AS `Second_Quarter`'),
                DB::raw('MAX(CASE WHEN grades.term = "Third Quarter" THEN grades.grade ELSE NULL END) AS `Third_Quarter`'),
                DB::raw('MAX(CASE WHEN grades.term = "Fourth Quarter" THEN grades.grade ELSE NULL END) AS `Fourth_Quarter`'),
                DB::raw('MAX(CASE WHEN grades.term = "Midterm" THEN grades.grade ELSE NULL END) AS `Midterm`'),
                DB::raw('MAX(CASE WHEN grades.term = "Final" THEN grades.grade ELSE NULL END) AS `Final`')
            )
            ->where('students.LRN', '=', $cid) // Filter by the student's LRN
            ->where('enrollments.school_year', '=', $latestSchoolYear) // Filter by the latest school year
            ->groupBy(
                'students.LRN',
                'students.fname',
                'students.mname',
                'students.lname',
                'students.contact_no',
                'subjects.subject_id',
                'subjects.subject_name',
                'subjects.strand',
                'subjects.grade_level',
                'enrollments.school_year',
            )
            ->orderBy('subjects.subject_name')
            ->get();
    
        return response()->json($grades); // Return results as JSON
    }
    
    public function getAttendance($lcn) {
        // Get the attendance records for the student along with subject names, ordered by date
        $attendance = DB::table('attendances')
            ->join('classes', 'attendances.class_id', '=', 'classes.class_id')  // Joining with classes
            ->leftJoin('subjects', 'classes.subject_id', '=', 'subjects.subject_id')  // Left joining with subjects
            ->select(
                'attendances.LRN',
                'attendances.status AS attendance_status',
                'attendances.date',  // Use the date column
                'subjects.subject_name'  // Selecting subject name from classes
            )
            ->where('attendances.LRN', '=', $lcn)  // Filtering by student LRN
            ->orderBy('attendances.date')  // Order by date
            ->get();
        
        // Get the total number of subjects the student is enrolled in, including those with no attendance
        $subjectCount = DB::table('rosters')
            ->join('classes', 'rosters.class_id', '=', 'classes.class_id')
            ->leftJoin('subjects', 'classes.subject_id', '=', 'subjects.subject_id')
            ->where('rosters.LRN', '=', $lcn)  // Filter by the student LRN in the rosters table
            ->distinct()  // Ensures counting distinct subjects
            ->count('subjects.subject_id');  // Count unique subjects the student is enrolled in
    
        // Create the desired response format
        $formattedAttendance = $attendance->map(function ($item) use ($subjectCount) {
            return [
                'LRN' => $item->LRN,
                'attendance_status' => $item->attendance_status,
                'date' => $item->date,
                'subject_name' => $item->subject_name,
                'subject_count' => $subjectCount  // Total number of subjects, including those without attendance
            ];
        });
        
        // Return the formatted attendance with subject count, already ordered by date
        return response()->json($formattedAttendance);  // Return results as JSON
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
