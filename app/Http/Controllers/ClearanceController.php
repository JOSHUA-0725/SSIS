<?php

namespace App\Http\Controllers;

use App\Models\AcademicTerm;
use App\Models\Clearance;
use App\Models\ClearanceItem;
use App\Models\Office;
use App\Models\Student;
use App\Models\User;
use App\Services\ClearanceService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClearanceController extends Controller
{
    /** The office a user signs for is fixed by their role, never chosen in the form. */
    private function officeFor(User $user): Office
    {
        $office = match ($user->roleName()) {
            'Signatory' => $user->office,
            'Department Head' => Office::where('code', 'DEPT')->first(),
            'Cashier' => Office::where('code', ClearanceService::CASHIER_OFFICE)->first(),
            'Registrar' => Office::where('code', ClearanceService::FINAL_OFFICE)->first(),
            default => null,
        };
        abort_unless($office, 403, 'No clearance office is assigned to your account.');

        return $office;
    }

    /** Department heads sign only for students of their own department. */
    private function scope(User $user, $query)
    {
        return $query->when($user->hasRole('Department Head'), fn ($q) => $q
            ->whereHas('clearance.student.program', fn ($p) => $p->where('department_id', $user->department_id)));
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $office = $this->officeFor($user);
        $term = AcademicTerm::active();
        $q = trim((string) $request->query('q'));
        $statuses = ['Pending', 'On Hold', 'Cleared'];
        $status = in_array($request->query('status'), $statuses, true) ? $request->query('status') : 'Pending';

        if ($term) {
            Student::where('status', 'Enrolled')
                ->whereDoesntHave('user', fn ($u) => $u->where('status', 'Inactive'))
                ->whereNotIn('id', Clearance::where('term_id', $term->id)->select('student_id'))
                ->get()->each(fn ($student) => ClearanceService::ensureFor($student, $term));
        }

        $items = $this->scope($user, ClearanceItem::with(['clearance.student.program', 'clearance.items.office']))
            ->where('office_id', $office->id)
            ->where('status', $status)
            ->whereHas('clearance', fn ($c) => $c->where('term_id', $term?->id))
            ->when($q !== '', fn ($query) => $query->whereHas('clearance.student', fn ($s) => $s->where(fn ($w) => $w
                ->where('student_no', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('first_name', 'like', "%{$q}%"))))
            ->paginate(15)->withQueryString();

        return view('clearance.index', compact('office', 'term', 'items', 'q', 'status', 'statuses'));
    }

    public function update(Request $request, ClearanceItem $item)
    {
        $user = $request->user();
        $office = $this->officeFor($user);

        abort_unless(
            $item->office_id === $office->id && $this->scope($user, ClearanceItem::whereKey($item->id))->exists(),
            403
        );

        $data = $request->validate([
            'status' => ['required', Rule::in(['Cleared', 'On Hold', 'Pending'])],
            'remarks' => ['required_if:status,On Hold', 'nullable', 'string', 'max:255'],
        ], ['remarks.required_if' => 'Give the reason for the hold so the student knows what to settle.']);

        try {
            ClearanceService::sign($item, $data['status'], $data['remarks'] ?? null, $user);
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', "{$item->clearance->student->full_name}: {$office->name} set to {$data['status']}.");
    }
}
