<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashierController;
use App\Http\Controllers\ClearanceController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\RegistrarController;
use App\Http\Controllers\StudentPortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::get('/documentation', [AuthController::class, 'documentation'])->name('documentation');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/login/{portal}', [AuthController::class, 'showPortal'])->whereIn('portal', array_keys(AuthController::PORTALS))->name('login.portal');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/forgot-password', [AuthController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:5,1')->name('password.send');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [AccountController::class, 'dashboard'])->name('dashboard');
    Route::get('/live', [AccountController::class, 'live'])->name('live');
    Route::get('/profile', [AccountController::class, 'profile'])->name('profile');
    Route::put('/profile/password', [AccountController::class, 'updatePassword'])->name('profile.password');
    Route::get('/notifications', [AccountController::class, 'notifications'])->name('notifications');
    Route::post('/notifications/read', [AccountController::class, 'markNotificationsRead'])->name('notifications.read');

    // Student Portal and Document Request System
    Route::middleware('role:Student')->prefix('student')->name('student.')->group(function () {
        Route::get('/grades', [StudentPortalController::class, 'grades'])->name('grades');
        Route::get('/clearance', [StudentPortalController::class, 'clearance'])->name('clearance');
        Route::get('/clearance/slip', [StudentPortalController::class, 'clearanceSlip'])->name('clearance.slip');
        Route::get('/queue', [StudentPortalController::class, 'queue'])->name('queue');
        Route::post('/queue', [StudentPortalController::class, 'joinQueue'])->name('queue.join');
        Route::post('/queue/leave', [StudentPortalController::class, 'leaveQueue'])->name('queue.leave');
        Route::post('/enroll', [StudentPortalController::class, 'enrollBlock'])->name('enroll');
        Route::get('/payments', [StudentPortalController::class, 'payments'])->name('payments');
        Route::get('/payments/checkout', [StudentPortalController::class, 'checkout'])->name('payments.checkout');
        Route::post('/payments', [StudentPortalController::class, 'pay'])->name('payments.pay');
        Route::get('/requests', [StudentPortalController::class, 'requests'])->name('requests');
        Route::post('/requests', [StudentPortalController::class, 'storeRequest'])->name('requests.store');
        Route::get('/requests/{docRequest:reference_no}', [StudentPortalController::class, 'showRequest'])->name('requests.show');
        Route::post('/requests/{docRequest:reference_no}/cancel', [StudentPortalController::class, 'cancelRequest'])->name('requests.cancel');
        Route::post('/requests/{docRequest:reference_no}/proof', [StudentPortalController::class, 'uploadProof'])->name('requests.proof');
        Route::get('/requests/{docRequest:reference_no}/stub', [StudentPortalController::class, 'claimStub'])->name('requests.stub');
        Route::get('/requests/{docRequest:reference_no}/ecopy', [StudentPortalController::class, 'ecopy'])->name('requests.ecopy');
    });

    // Registrar Module
    Route::middleware('role:Registrar')->prefix('registrar')->name('registrar.')->group(function () {
        Route::get('/students', [RegistrarController::class, 'students'])->name('students');
        Route::get('/students/new', [RegistrarController::class, 'createStudent'])->name('students.create');
        Route::post('/students', [RegistrarController::class, 'storeStudent'])->name('students.store');
        Route::get('/students/{student}', [RegistrarController::class, 'showStudent'])->name('students.show');
        Route::put('/students/{student}', [RegistrarController::class, 'updateStudent'])->name('students.update');
        Route::post('/students/{student}/enroll', [RegistrarController::class, 'enrollStudent'])->name('students.enroll');
        Route::get('/terms', [RegistrarController::class, 'terms'])->name('terms');
        Route::post('/terms', [RegistrarController::class, 'storeTerm'])->name('terms.store');
        Route::put('/terms/{term}', [RegistrarController::class, 'updateTerm'])->name('terms.update');
        Route::get('/slot-requests', [RegistrarController::class, 'slotRequests'])->name('slots');
        Route::put('/slot-requests/{slotRequest}', [RegistrarController::class, 'decideSlotRequest'])->name('slots.decide');
        Route::get('/queue', [RegistrarController::class, 'queue'])->name('queue');
        Route::post('/queue/next', [RegistrarController::class, 'callNext'])->name('queue.next');
        Route::put('/queue/{ticket}', [RegistrarController::class, 'closeTicket'])->name('queue.close');
        Route::get('/requests', [RegistrarController::class, 'requests'])->name('requests');
        Route::get('/requests/{docRequest:reference_no}', [RegistrarController::class, 'showRequest'])->name('requests.show');
        Route::put('/requests/{docRequest:reference_no}', [RegistrarController::class, 'updateRequest'])->name('requests.update');
        Route::get('/requests/{docRequest:reference_no}/ecopy', [RegistrarController::class, 'ecopy'])->name('requests.ecopy');
    });

    // Cashier Module
    Route::middleware('role:Cashier')->prefix('cashier')->name('cashier.')->group(function () {
        Route::get('/payments', [CashierController::class, 'index'])->name('payments');
        Route::get('/students/{student}', [CashierController::class, 'student'])->name('student');
        Route::post('/students/{student}/payments', [CashierController::class, 'storePayment'])->name('payments.store');
        Route::post('/students/{student}/assessments', [CashierController::class, 'storeAssessment'])->name('assessments.store');
        Route::get('/proofs', [CashierController::class, 'proofs'])->name('proofs');
        Route::get('/proofs/{payment}/file', [CashierController::class, 'proofFile'])->name('proofs.file');
        Route::put('/proofs/{payment}', [CashierController::class, 'decideProof'])->name('proofs.decide');
        Route::put('/online-payments/{reference}', [CashierController::class, 'decideOnline'])->name('online.decide');
        Route::get('/report', [CashierController::class, 'report'])->name('report');
    });

    // Department Module
    Route::middleware('role:Faculty')->prefix('faculty')->name('faculty.')->group(function () {
        Route::get('/classes', [DepartmentController::class, 'classes'])->name('classes');
        Route::get('/classes/{section}', [DepartmentController::class, 'encode'])->name('encode');
        Route::put('/classes/{section}', [DepartmentController::class, 'saveGrades'])->name('encode.save');
        Route::get('/enrollment', [DepartmentController::class, 'enrollment'])->name('enrollment');
        Route::put('/enrollment/block', [DepartmentController::class, 'setBlockEnrollment'])->name('enrollment.block');
        Route::post('/enrollment/slots', [DepartmentController::class, 'requestSlots'])->name('enrollment.slots');
    });
    Route::middleware('role:Department Head')->prefix('department')->name('head.')->group(function () {
        Route::get('/sheets', [DepartmentController::class, 'sheets'])->name('sheets');
        Route::get('/sheets/{section}', [DepartmentController::class, 'sheet'])->name('sheet');
        Route::put('/sheets/{section}', [DepartmentController::class, 'decideSheet'])->name('sheet.decide');
    });

    // Clearance signing: every office that signs
    Route::middleware('role:Signatory,Department Head,Cashier,Registrar')->prefix('clearance')->name('clearance.')->group(function () {
        Route::get('/', [ClearanceController::class, 'index'])->name('index');
        Route::put('/{item}', [ClearanceController::class, 'update'])->name('update');
    });

    // Admin Module
    Route::middleware('role:Administrator')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::get('/users/new', [AdminController::class, 'createUser'])->name('users.create');
        Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
        Route::get('/users/{user}/edit', [AdminController::class, 'editUser'])->name('users.edit');
        Route::put('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::post('/users/{user}/reset', [AdminController::class, 'resetPassword'])->name('users.reset');
        Route::get('/document-types', [AdminController::class, 'documentTypes'])->name('doctypes');
        Route::post('/document-types', [AdminController::class, 'storeDocumentType'])->name('doctypes.store');
        Route::put('/document-types/{type}', [AdminController::class, 'updateDocumentType'])->name('doctypes.update');
        Route::get('/audit-log', [AdminController::class, 'auditLog'])->name('audit');
    });
});
