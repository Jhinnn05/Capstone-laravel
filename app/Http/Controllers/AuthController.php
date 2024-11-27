<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
// use App\Models\User;
use App\Models\ParentGuardian;
use App\Models\Message;
use App\Models\Announcement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
class AuthController extends Controller
{
    //login
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:parent_guardians,email',
            'password' => 'required'
        ]);

        $user = ParentGuardian::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'The provided credentials are incorrect'], 401);
        }
        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json([
            'parent' => $user,
            'token' => $token,
            'id' => $user->guardian_id,
            'email' => $user->email
        ]);
    }
    //logout
    public function logout(Request $request)
    {
        $user = $request->user(); 
        if ($user) {
            $user->currentAccessToken()->delete();
            return response()->json(['message' => 'Logged out successfully.']);
        }
        return response()->json(['message' => 'No user authenticated'], 401);
    }
    //password update
    public function UserDetails($email){
        $user = ParentGuardian::where('email',$email)
                        ->first();
        return $user;
    }    
        public function updatePass(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255|exists:parent_guardians,email', 
            'oldPassword' => 'nullable|string',
            'newPassword' => 'nullable|string|min:8|confirmed', 
            'fname' => 'required|string|max:255',
            'mname' => 'required|string|max:255',
            'lname' => 'required|string|max:255',
            'address' => 'required|string|max:255',
        ]);

       
        $user = ParentGuardian::where('email', $request->email)->first();

        if ($request->oldPassword && !Hash::check($request->oldPassword, $user->password)) {
            return response()->json(['message' => 'Wrong password'], 401);
        }

        if ($request->newPassword) {
            $user->password = Hash::make($request->newPassword); 
        }

        $user->fname = $request->fname;
        $user->mname = $request->mname;
        $user->lname = $request->lname;
        $user->address = $request->address;

        $user->save();

        return response()->json(['message' => 'User  details updated successfully']);
    }
    //upload image
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'email' => 'required|exists:parent_guardians,email'
        ]);

        try {
            
            $email = $request->input('email');
            \Log::info('Uploading image for email: ' . $email); 

            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $destinationPath = public_path('assets/parentPic');

           
            if (!is_dir($destinationPath)) {
                \Log::info('Creating directory: ' . $destinationPath);
                mkdir($destinationPath, 0755, true);
            }

          
            $parentGuardians = ParentGuardian::where('email', $email)->get();
            \Log::info('Found ' . $parentGuardians->count() . ' parent guardians with email: ' . $email);
            foreach ($parentGuardians as $parentGuardian) {
                if ($parentGuardian->parent_pic && file_exists($path = $destinationPath . '/' . $parentGuardian->parent_pic)) {
                    \Log::info('Deleting old image: ' . $path);
                    unlink($path);
                }
            }

            
            $image->move($destinationPath, $imageName);
            \Log::info('New image uploaded: ' . $imageName);
            ParentGuardian::where('email', $email)->update(['parent_pic' => $imageName]);
            \Log::info('Updated parent guardians with new image name: ' . $imageName);
            return response()->json([
                'message' => 'Image uploaded successfully.',
                'image_url' => url('assets/parentPic/' . $imageName)
            ]);
        } catch (\Exception $e) {
            \Log::error('Image upload failed: ' . $e->getMessage());
            return response()->json(['error' => 'Image upload failed.'], 500);
        }
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

    //getMessages
    public function getMessages(Request $request) {
        $uid = $request->input('uid');
    
        // Main query to get messages
        $msg = DB::table('messages')
            ->leftJoin('admins', function ($join) {
                $join->on('messages.message_sender', '=', 'admins.admin_id');
            })
            ->where('messages.message_reciever', '=', $uid) // Filter by receiver
            ->select('messages.*', 
                DB::raw('CASE 
                    WHEN messages.message_sender IN (SELECT admin_id FROM admins) THEN CONCAT(admins.fname, " ", LEFT(admins.mname, 1), ". ", admins.lname)
                    ELSE "Unknown Sender"  -- Fallback for senders not in admins
                END as sender_name'))
            ->orderBy('messages.created_at', 'desc')
            ->get();
    
        return $msg;
    }
    //getAdmins
    public function getAdmins() {
        $admins = DB::table('admins')
            ->select(
                'admins.admin_id',
                'admins.role',
                DB::raw('IF(admins.mname IS NOT NULL AND admins.mname != "", 
                            CONCAT(admins.fname, " ", LEFT(admins.mname, 1), ". ", admins.lname), 
                            CONCAT(admins.fname, " ", admins.lname)) as account_name')
            )
            ->where('admins.role', '!=', 'DSF')
            ->get()
            ->map(function ($admin) {
                return [
                    'account_id' => $admin->admin_id,
                    'account_name' => $admin->account_name,
                    'type' => $admin->role,
                ];
            });
        return response()->json($admins);
    } 
    //getConvo
    public function getConvo(Request $request, $sid) {
        $user = null;
            $admins = DB::table('admins')
            ->where('admins.admin_id', $sid)
            ->select('admins.admin_id', 'admins.role', DB::raw('CONCAT(admins.fname, " ", LEFT(admins.mname, 1), ". ", admins.lname) as account_name'))
            ->where('admins.role', '!=', 'DSF')
            ->first();
            $user = [
                'account_id' => $admins->admin_id,
                'account_name' => $admins->account_name,
                'type' => $admins->role,
            ];
        $convo = [];
            if ($admins) {
            $uid = $request->input('uid');
    
            $convo = DB::table('messages')
            ->leftJoin('admins', 'messages.message_sender', '=', 'admins.admin_id')
            ->where(function ($query) use ($uid) {
                $query->where('messages.message_sender', $uid)
                    ->orWhere('messages.message_reciever', $uid);
            })
            ->where(function ($query) use ($sid) {
                $query->where('messages.message_sender', $sid)
                    ->orWhere('messages.message_reciever', $sid);
            })
            ->selectRaw("
                messages.*,
                CASE 
                    WHEN messages.message_sender = ? THEN 'me' 
                    ELSE NULL 
                END as me,
                CASE 
                    WHEN messages.message_sender IN (SELECT admin_id FROM admins) THEN CONCAT(admins.fname, ' ', LEFT(admins.mname, 1), '. ', admins.lname)
                    ELSE NULL 
                END as sender_name
            ", [$uid])
            ->get();

        }
            return response()->json([
            'user' => $user ?: ['message' => 'User  not found'],
            'conversation' => $convo,
        ]);
    }
    //sendMessage
    public function sendMessage(Request $request){
        $validator = Validator::make($request->all(), [
            'message_sender' => 'required',
            'message_reciever' => 'required',
            'message' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $message = Message::create([
            'message_sender' => $request->input('message_sender'),
            'message_reciever' => $request->input('message_reciever'),
            'message' => $request->input('message'),
            'message_date' => now(),
        ]);
        return response()->json($message, 201);
    }
    //getrecepeints
    public function getrecepeints(Request $request)
    {
     $students = DB::table('students')
     ->select(DB::raw('LRN AS receiver_id, CONCAT(fname, " ", lname) AS receiver_name'));
    $guardians = DB::table('parent_guardians')
        ->select(DB::raw('guardian_id AS receiver_id, CONCAT(fname, " ", lname) AS receiver_name'));
    $admins = DB::table('admins')
        ->select(DB::raw('admin_id AS receiver_id, CONCAT(fname, " ", lname) AS receiver_name'));
    $recipients = $students->unionAll($guardians)->unionAll($admins)->get();
    return response()->json($recipients);
    }
    public function composenewmessage(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'message_date' => 'required|date',
            'message_sender' => [
                'required',
                function ($attribute, $value, $fail) {
                    $existsInStudents = DB::table('students')->where('LRN', $value)->exists();
                    $existsInGuardians = DB::table('parent_guardians')->where('guardian_id', $value)->exists();
                    $existsInAdmins = DB::table('admins')->where('admin_id', $value)->exists();
    
                    if (!$existsInStudents && !$existsInGuardians && !$existsInAdmins) {
                        $fail("The selected $attribute is invalid.");
                    }
                },
            ],
            'message_reciever' => [
                'required',
                function ($attribute, $value, $fail) {
                    $existsInStudents = DB::table('students')->where('LRN', $value)->exists();
                    $existsInGuardians = DB::table('parent_guardians')->where('guardian_id', $value)->exists();
                    $existsInAdmins = DB::table('admins')->where('admin_id', $value)->exists();
    
                    if (!$existsInStudents && !$existsInGuardians && !$existsInAdmins) {
                        $fail("The selected $attribute is invalid.");
                    }
                },
            ],
        ]);
    
        try {
            $message = new Message();
            $message->message_sender = $validated['message_sender'];
            $message->message_reciever = $validated['message_reciever'];
            $message->message = $validated['message'];
            $message->message_date = $validated['message_date'];
            $message->save();
    
            Log::info('Message successfully composed', [
                'message_id' => $message->message_id,
                'sender' => $validated['message_sender'],
                'receiver' => $validated['message_reciever'],
                'message_content' => $validated['message'],
                'message_date' => $validated['message_date'],
            ]);
    
            return $this->getMessages($request); 
        } catch (\Exception $e) {
            Log::error('Error sending message: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to send message'], 500);
        }
    }

    //announcements
    public function getAnnouncements()
    {
        $announcements = Announcement::all();
        return $announcements;
    }

}
