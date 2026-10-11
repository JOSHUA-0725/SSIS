<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\AuditLog;
use App\Models\ClassSection;
use App\Models\Clearance;
use App\Models\ClearanceItem;
use App\Models\DocumentRequest;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\QueueTicket;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $term = AcademicTerm::active();
        $notifications = Notification::where('user_id', $user->id)->latest()->limit(5)->get();

        if ($user->hasRole('Student')) {
            return app(StudentPortalController::class)->overview($request);
        }

        return view('dashboard', [
            'term' => $term,
            'cards' => $this->staffCards($user, $term),
            'notifications' => $notifications,
        ]);
    }

    /**
     * A short fingerprint of the data this user can see. Open pages poll it every few seconds
     * and refresh themselves when it changes, so a payment or a new subject shows up at once.
     */
    public function live(Request $request)
    {
        $userId = $request->user()->id;
        $quiet = ['LOGIN', 'LOGOUT', 'LOGIN_FAILED', 'LOGIN_LOCKED', 'ACCESS_DENIED', 'EXPORT', 'PASSWORD_CHANGE', 'PASSWORD_RESET_REQUEST'];

        return response()->json(['signature' => md5(implode('|', [
            // Every change to a record writes an audit entry, so its newest id moves with any change.
            AuditLog::whereNotIn('action', $quiet)->max('id'),
            Payment::count(), Payment::max('updated_at'),
            Notification::where('user_id', $userId)->max('id'),
            Notification::where('user_id', $userId)->where('is_read', false)->count(),
        ]))])->header('Cache-Control', 'no-store');
    }

    /** Status cards for each staff role: [label, value, route]. */
    private function staffCards(User $user, ?AcademicTerm $term): array
    {
        $termId = $term?->id;

        return match ($user->roleName()) {
            'Registrar' => [
                ['Requests to process', DocumentRequest::where('status', 'Paid')->count(), route('registrar.requests', ['status' => 'Paid'])],
                ['Ready for pickup', DocumentRequest::where('status', 'Ready for Pickup')->count(), route('registrar.requests', ['status' => 'Ready for Pickup'])],
                ['Waiting in the enrollment queue', QueueTicket::today()->where('status', 'Waiting')->count(), route('registrar.queue')],
                ['Enrolled students', Student::where('status', 'Enrolled')->count(), route('registrar.students')],
                ['Cleared this term', Clearance::where('term_id', $termId)->where('overall_status', 'Cleared')->count(), route('clearance.index')],
            ],
            'Cashier' => [
                ['Collected today', '₱'.number_format((float) Payment::where('status', 'Posted')->whereDate('paid_at', today())->sum('amount'), 2), route('cashier.report')],
                ['Proofs to verify', Payment::where('status', 'For Verification')->count(), route('cashier.proofs')],
                ['Unpaid document requests', DocumentRequest::where('status', 'Pending Payment')->count(), route('cashier.payments')],
            ],
            'Faculty' => [
                ['My classes this term', ClassSection::where('faculty_id', $user->id)->where('term_id', $termId)->count(), route('faculty.classes')],
                ['Sheets returned to me', ClassSection::where('faculty_id', $user->id)->where('sheet_status', 'Returned')->count(), route('faculty.classes')],
                ['Encoding period', $term?->encoding_open ? 'Open' : 'Closed', route('faculty.classes')],
            ],
            'Department Head' => [
                ['Sheets awaiting approval', ClassSection::where('sheet_status', 'Submitted')->whereHas('subject', fn ($q) => $q->where('department_id', $user->department_id))->count(), route('head.sheets')],
                ['Clearances pending', $this->pendingClearanceCount('DEPT', $termId), route('clearance.index')],
            ],
            'Signatory' => [
                ['Clearances pending', $this->pendingClearanceCount($user->office?->code, $termId), route('clearance.index')],
            ],
            'Administrator' => [
                ['Active users', User::where('status', 'Active')->count(), route('admin.users')],
                ['Failed logins today', AuditLog::where('action', 'LOGIN_FAILED')->whereDate('created_at', today())->count(), route('admin.audit', ['action' => 'LOGIN_FAILED'])],
                ['Access denied today', AuditLog::where('action', 'ACCESS_DENIED')->whereDate('created_at', today())->count(), route('admin.audit', ['action' => 'ACCESS_DENIED'])],
            ],
            default => [],
        };
    }

    private function pendingClearanceCount(?string $officeCode, ?int $termId): int
    {
        return ClearanceItem::where('status', 'Pending')
            ->whereHas('office', fn ($q) => $q->where('code', $officeCode))
            ->whereHas('clearance', fn ($q) => $q->where('term_id', $termId))
            ->count();
    }

    public function profile(Request $request)
    {
        return view('account.profile', ['user' => $request->user()]);
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $request->user()->forceFill(['password' => Hash::make($data['password'])])->save();
        AuditLog::record('PASSWORD_CHANGE', 'users', $request->user()->id);

        return back()->with('status', 'Password updated.');
    }

    public function notifications(Request $request)
    {
        return view('account.notifications', [
            'notifications' => Notification::where('user_id', $request->user()->id)->latest()->paginate(20),
        ]);
    }

    public function markNotificationsRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)->update(['is_read' => true]);

        return back();
    }
}
