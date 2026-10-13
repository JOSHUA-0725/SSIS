<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /** The account lists in the Admin menu, one per module, and the role each one shows. */
    public const USER_GROUPS = [
        'students' => ['label' => 'Students', 'role' => 'Student', 'icon' => 'graduation'],
        'cashier' => ['label' => 'Cashier', 'role' => 'Cashier', 'icon' => 'wallet'],
        'registrar' => ['label' => 'Registrar', 'role' => 'Registrar', 'icon' => 'file'],
        'faculty' => ['label' => 'Faculty', 'role' => 'Faculty', 'icon' => 'book-open'],
        'professors' => ['label' => 'Professors', 'role' => 'Professor', 'icon' => 'user'],
    ];

    public function users(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $group = array_key_exists($request->query('group'), self::USER_GROUPS) ? $request->query('group') : 'students';

        $users = User::with('role')
            ->whereHas('role', fn ($role) => $role->where('name', self::USER_GROUPS[$group]['role']))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('username', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%")))
            ->orderBy('username')->paginate(15)->withQueryString();

        return view('admin.users', ['users' => $users, 'q' => $q, 'group' => $group, 'label' => self::USER_GROUPS[$group]['label']]);
    }

    /** The list that shows this account, so a redirect lands where the change can be seen. */
    public static function usersTab(User $user): array
    {
        $group = collect(self::USER_GROUPS)->search(fn ($g) => $g['role'] === $user->role()->value('name'));

        return $group ? ['group' => $group] : [];
    }

    private function formData(): array
    {
        return [
            // Student accounts are created by the Registrar together with the student record.
            'roles' => Role::where('name', '!=', 'Student')->orderBy('id')->get(),
            'offices' => Office::orderBy('sequence_no')->get(),
            'departments' => Department::orderBy('code')->get(),
        ];
    }

    private function rules(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:120', Rule::unique('users')->ignore($user)],
            'role_id' => ['required', Rule::exists('roles', 'id')->whereNot('name', 'Student')],
            'office_id' => ['nullable', 'exists:offices,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ];
    }

    public function createUser()
    {
        return view('admin.user-form', $this->formData() + ['user' => new User]);
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate($this->rules() + [
            'username' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9.\-]+$/', 'unique:users,username'],
        ]);

        $password = Str::password(10, symbols: false);
        $user = User::create($data + ['password' => $password]);
        AuditLog::record('CREATE', 'users', $user->id, null, ['username' => $user->username, 'role_id' => $user->role_id]);

        return redirect()->route('admin.users', $this->usersTab($user))
            ->with('status', "User {$user->username} created. Temporary password: {$password} (shown once).");
    }

    public function editUser(User $user)
    {
        return view('admin.user-form', $this->formData() + ['user' => $user]);
    }

    public function updateUser(Request $request, User $user)
    {
        $isStudent = $user->hasRole('Student');
        $rules = $this->rules($user) + ['status' => ['required', Rule::in(['Active', 'Inactive'])]];
        if ($isStudent) {
            unset($rules['role_id'], $rules['office_id'], $rules['department_id']);
        }
        $data = $request->validate($rules);

        if ($user->is($request->user()) && ($data['status'] !== 'Active' || (int) $data['role_id'] !== $user->role_id)) {
            return back()->withErrors(['status' => 'You cannot deactivate your own account or change your own role.']);
        }

        $old = $user->only(array_keys($data));
        $user->update($data);
        AuditLog::record('UPDATE', 'users', $user->id, $old, $data);

        return redirect()->route('admin.users', $this->usersTab($user))->with('status', "User {$user->username} updated.");
    }

    public function resetPassword(User $user)
    {
        $password = Str::password(10, symbols: false);
        $user->forceFill(['password' => $password, 'failed_attempts' => 0, 'lockout_count' => 0, 'locked_until' => null])->save();
        AuditLog::record('PASSWORD_RESET', 'users', $user->id);

        return back()->with('status', "Password of {$user->username} reset. Temporary password: {$password} (shown once).");
    }

    public function documentTypes()
    {
        return view('admin.document-types', ['types' => DocumentType::orderBy('name')->get()]);
    }

    private function typeRules(?DocumentType $type = null): array
    {
        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('document_types')->ignore($type)],
            'fee_per_copy' => ['required', 'numeric', 'min:0', 'max:99999'],
            'processing_days' => ['required', 'integer', 'between:1,60'],
            'description' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function storeDocumentType(Request $request)
    {
        $data = $request->validate($this->typeRules());
        $type = DocumentType::create($data);
        AuditLog::record('CREATE', 'document_types', $type->id, null, $data);

        return back()->with('status', 'Document type added.');
    }

    public function updateDocumentType(Request $request, DocumentType $type)
    {
        $data = $request->validate($this->typeRules($type) + ['is_active' => ['required', 'boolean']]);
        $old = $type->only(array_keys($data));
        $type->update($data);
        AuditLog::record('UPDATE', 'document_types', $type->id, $old, $data);

        return back()->with('status', "{$type->name} updated.");
    }

    public function auditLog(Request $request)
    {
        $action = trim((string) $request->query('action'));
        $q = trim((string) $request->query('q'));

        return view('admin.audit', [
            'action' => $action,
            'q' => $q,
            'actions' => AuditLog::select('action')->distinct()->orderBy('action')->pluck('action'),
            'logs' => AuditLog::with('user')
                ->when($action !== '', fn ($query) => $query->where('action', $action))
                ->when($q !== '', fn ($query) => $query->whereHas('user', fn ($u) => $u->where('username', 'like', "%{$q}%")))
                ->latest('id')->paginate(25)->withQueryString(),
        ]);
    }
}
