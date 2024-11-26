<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
// use App\Models\User;
use App\Models\ParentGuardian;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
class AuthController extends Controller
{
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

    public function logout(Request $request)
    {
        $user = $request->user(); // Retrieve the authenticated user

        if ($user) {
            // Revoke the user's current token
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
        // Validate incoming request
        $request->validate([
            'email' => 'required|email|max:255|exists:parent_guardians,email', // Check existence for email
            'oldPassword' => 'nullable|string', // Make oldPassword optional
            'newPassword' => 'nullable|string|min:8|confirmed', // Allow newPassword to be optional
            'fname' => 'required|string|max:255',
            'mname' => 'required|string|max:255',
            'lname' => 'required|string|max:255',
            'address' => 'required|string|max:255',
        ]);

        // Retrieve user by email
        $user = ParentGuardian::where('email', $request->email)->first();

        // If old password is provided, check it
        if ($request->oldPassword && !Hash::check($request->oldPassword, $user->password)) {
            return response()->json(['message' => 'Wrong password'], 401);
        }

        // Update user details
        if ($request->newPassword) {
            $user->password = Hash::make($request->newPassword); // Update password if provided
        }

        $user->fname = $request->fname;
        $user->mname = $request->mname;
        $user->lname = $request->lname;
        $user->address = $request->address;

        $user->save(); // Save all changes

        return response()->json(['message' => 'User  details updated successfully']);
    }
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'email' => 'required|exists:parent_guardians,email'
        ]);

        try {
            // Get the email from the request
            $email = $request->input('email');
            \Log::info('Uploading image for email: ' . $email); // Log email

            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $destinationPath = public_path('assets/parentPic');

            // Ensure the directory exists
            if (!is_dir($destinationPath)) {
                \Log::info('Creating directory: ' . $destinationPath); // Log directory creation
                mkdir($destinationPath, 0755, true);
            }

            // Get all parent guardians with the same email
            $parentGuardians = ParentGuardian::where('email', $email)->get();
            \Log::info('Found ' . $parentGuardians->count() . ' parent guardians with email: ' . $email); // Log count of guardians

            // Loop through each parent guardian to delete old images
            foreach ($parentGuardians as $parentGuardian) {
                // Delete the old image if it exists
                if ($parentGuardian->parent_pic && file_exists($path = $destinationPath . '/' . $parentGuardian->parent_pic)) {
                    \Log::info('Deleting old image: ' . $path); // Log old image deletion
                    unlink($path);
                }
            }

            // Move the new image to the destination path
            $image->move($destinationPath, $imageName);
            \Log::info('New image uploaded: ' . $imageName); // Log new image upload

            // Update all matching records with the new image name
            ParentGuardian::where('email', $email)->update(['parent_pic' => $imageName]);
            \Log::info('Updated parent guardians with new image name: ' . $imageName); // Log database update

            return response()->json([
                'message' => 'Image uploaded successfully.',
                'image_url' => url('assets/parentPic/' . $imageName)
            ]);
        } catch (\Exception $e) {
            \Log::error('Image upload failed: ' . $e->getMessage()); // Log the error
            return response()->json(['error' => 'Image upload failed.'], 500);
        }
    }

    //messages
    public function getMessages(Request $request) {
        $uid = $request->input('uid');
    
        // Subquery to get the latest message for each sender
        $latestMessages = DB::table('messages')
            ->select('message_sender', DB::raw('MAX(created_at) as max_created_at'))
            ->groupBy('message_sender');
    
        // Main query to get messages
        $msg = DB::table('messages')
            // ->leftJoin('students', function ($join) {
            //     $join->on('messages.message_sender', '=', 'students.LRN');
            // })
            ->leftJoin('admins', function ($join) {
                $join->on('messages.message_sender', '=', 'admins.admin_id');
            })
            // ->leftJoin('parent_guardians', function ($join) {
            //     $join->on('messages.message_sender', '=', 'parent_guardians.guardian_id');
            // })
            ->joinSub($latestMessages, 'latest_messages', function ($join) {
                $join->on('messages.message_sender', '=', 'latest_messages.message_sender')
                    ->on('messages.created_at', '=', 'latest_messages.max_created_at');
            })
            ->where('messages.message_reciever', '=', $uid) // Filter by receiver
            ->select('messages.*', 
                DB::raw('CASE 
                    WHEN messages.message_sender IN (SELECT admin_id FROM admins) THEN CONCAT(admins.fname, " ", LEFT(admins.mname, 1), ". ", admins.lname)
                END as sender_name'))
            ->orderBy('messages.created_at', 'desc')
            ->get();
        
        return $msg;
        
    }

    public function getAdmins() {
        // Fetch students
        $admins = DB::table('admins')
            ->select('admins.admin_id', 'admins.role', DB::raw('CONCAT(admins.fname, " ", LEFT(admins.mname, 1), ". ", admins.lname) as account_name'))
            ->where('admins.role', '!=', 'DSF')
            ->get()
            ->map(function ($admins) {
                return [
                    'account_id' => $admins->admin_id,
                    'account_name' => $admins->account_name,
                    'type' => $admins->role,
                ];
            });

        // Combine both collections into one
        // $accounts = $students;

        return response()->json($admins);
    } 
    
    public function getConvo(Request $request, $sid) {
        // Initialize the response variable
        $user = null;
    
        // Check if the $sid corresponds to a student
        $admins = DB::table('admins')
            ->where('admins.admin_id', $sid)
            ->select('admins.admin_id', 'admins.role', DB::raw('CONCAT(admins.fname, " ", LEFT(admins.mname, 1), ". ", admins.lname) as account_name'))
            ->where('admins.role', '!=', 'DSF')
            ->first(); // Use first() to get a single record

          
            $user = [
                'account_id' => $admins->admin_id,
                'account_name' => $admins->account_name,
                'type' => $admins->role,
            ];
      
    
        // Initialize the conversation variable
        $convo = [];
    
        // If user is found, fetch the conversation
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
    
        // Return the user information and conversation or a not found message
        return response()->json([
            'user' => $user ?: ['message' => 'User  not found'],
            'conversation' => $convo,
        ]);
    }
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
            'message_sender' => $request->input('message_sender'), // Ensure the key matches your database column
            'message_reciever' => $request->input('message_reciever'), // Ensure the key matches your database column
            'message' => $request->input('message'), // Ensure the key matches your database column
            'message_date' => now(),
        ]);

        return response()->json($message, 201);
    }
    
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
        // Validate the incoming request data
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
            // Create a new message
            $message = new Message();
            $message->message_sender = $validated['message_sender'];
            $message->message_reciever = $validated['message_reciever'];
            $message->message = $validated['message'];
            $message->message_date = $validated['message_date'];
            $message->save();
    
            // Log a success message
            Log::info('Message successfully composed', [
                'message_id' => $message->message_id,
                'sender' => $validated['message_sender'],
                'receiver' => $validated['message_reciever'],
                'message_content' => $validated['message'],
                'message_date' => $validated['message_date'],
            ]);
    
            // Return the updated list of messages
            return $this->getMessages($request);  // Call getMessages method to return updated conversation
        } catch (\Exception $e) {
            // Log any error that occurs
            Log::error('Error sending message: ' . $e->getMessage());
    
            // Return an error response
            return response()->json(['error' => 'Failed to send message'], 500);
        }
    }

}
