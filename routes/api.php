<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ParentGuardianController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\EnrollmentController;
// Route::middleware('auth:sanctum')->group(function () {

Route::get('/user', function (Request $request) {
    return $request->user();
});    
Route::post('/register',[AuthController::class, 'register']);
Route::post('/login',[AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
Route::get('/Account/{email}',[AuthController::class, 'UserDetails']);
Route::post('/Account/updatePass/{email}',[AuthController::class, 'updatePass']);
//student
Route::apiResource('/student',StudentController::class);
//parent
Route:: apiResource('/parentguardians',ParentGuardianController::class);
//statement of account
Route::get('/displaySOA/{LRN}', [ParentGuardianController::class, 'displaySOA']);
//grades
Route::get('/displaygrades/{cid}', [ParentGuardianController::class, 'getClassGrades']);

//attendance
Route::get('/attendances/{lcn}', [ParentGuardianController::class, 'getAttendance']);

Route:: apiResource('/enrollment',EnrollmentController::class);
Route::put('/update-password', [AuthController::class, 'updatePass']);

//messages
Route::get('/getAdmins', [AuthController::class, 'getAdmins']);
Route::get('/getMessages', [AuthController::class, 'getMessages']);
Route::get('/getConvo/{sid}', [AuthController::class, 'getConvo']);
Route::post('/sendMessage', [AuthController::class, 'sendMessage']);
Route::get('/getrecepeints', [AuthController::class, 'getrecepeints']);
Route::post('/composemessage', [AuthController::class, 'composenewmessage']);

//announcements
Route::get('announcements',[AuthController::class,'getAnnouncements']);

//upload image
Route::post('/upload-image', [AuthController::class, 'uploadImage']);
Route::get('assets/parentPic/{filename}', function ($filename) {
    $path = public_path('assets/parentPic/' . $filename);
    
    if (file_exists($path)) {
        return response()->file($path);
    }
    abort(404);
});
// });