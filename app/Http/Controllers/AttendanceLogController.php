<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\AttendanceLog;
use App\Models\SchoolClass;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Services\AttendanceCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceLogController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        abort_unless($user->canViewAttendance(), 403);

        // All sessions for this school
        $allSessions = AcademicSession::where('school_id', $user->school_id)
            ->orderByDesc('start_date')->get();

        $sessionId = $request->integer('session_id');
        $session = $sessionId
            ? $allSessions->firstWhere('id', $sessionId)
            : AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $allTerms = Term::where('academic_session_id', $session->id)
            ->orderBy('start_date')->get();

        $termId          = $request->integer('term_id');
        $filterClassId   = $request->integer('class_id');
        $filterRecorderId= $request->integer('recorder_id');
        $dateFrom        = $request->query('date_from', '');
        $dateTo          = $request->query('date_to', '');
        $sortBy          = $request->query('sort', 'date_desc'); // date_desc|date_asc|rate_asc|rate_desc

        $query = AttendanceLog::where('academic_session_id', $session->id)
            ->whereHas('schoolClass', fn ($q) => $q->where('school_id', $user->school_id))
            ->when($termId,           fn ($q) => $q->where('term_id', $termId))
            ->when($filterClassId,    fn ($q) => $q->where('school_class_id', $filterClassId))
            ->when($filterRecorderId, fn ($q) => $q->where('recorded_by', $filterRecorderId))
            ->when($dateFrom !== '',  fn ($q) => $q->where('attendance_date', '>=', $dateFrom))
            ->when($dateTo !== '',    fn ($q) => $q->where('attendance_date', '<=', $dateTo))
            ->with(['recorder', 'schoolClass']);

        // Teachers only see their own classes
        if ($user->isTeacher() && ! $user->canManageAttendance()) {
            $query->whereIn('school_class_id', $this->classIds($user, $session->id) ?: [0]);
        }

        // DB-level sorts
        $query = match ($sortBy) {
            'date_asc'  => $query->orderBy('attendance_date'),
            'rate_asc'  => $query->orderByRaw('present_count / NULLIF(enrolled_count,0) ASC')->orderByDesc('attendance_date'),
            'rate_desc' => $query->orderByRaw('present_count / NULLIF(enrolled_count,0) DESC')->orderByDesc('attendance_date'),
            default     => $query->orderByDesc('attendance_date'),
        };

        $logs = $query->get();

        // Dropdown option lists (unfiltered for this session)
        $isStaff = $user->canManageAttendance() || $user->isHoD();
        $allForSession = $isStaff
            ? AttendanceLog::where('academic_session_id', $session->id)
                ->whereHas('schoolClass', fn ($q) => $q->where('school_id', $user->school_id))
                ->with(['recorder', 'schoolClass'])->get()
            : collect();

        $allClasses   = $allForSession->pluck('schoolClass')->filter()->unique('id')->sortBy('name')->values();
        $allRecorders = $allForSession->pluck('recorder')->filter()->unique('id')->sortBy('name')->values();

        $filters = compact(
            'sessionId', 'termId', 'filterClassId', 'filterRecorderId',
            'dateFrom', 'dateTo', 'sortBy'
        );

        return view('attendance.index', [
            'logs'        => $logs,
            'session'     => $session,
            'canRecord'   => $user->canRecordAttendance(),
            'isStaff'     => $isStaff,
            'allSessions' => $allSessions,
            'allTerms'    => $allTerms,
            'allClasses'  => $allClasses,
            'allRecorders'=> $allRecorders,
            'filters'     => $filters,
        ]);
    }

    public function create(AttendanceCalculationService $calculator): View
    {
        $user = auth()->user();
        abort_unless($user->canRecordAttendance(), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $term = Term::currentForSession($session->id);
        $date = $term ? $calculator->suggestedDate($session, $term) : now()->toDateString();
        $classes = $this->classesForForm($user, $session->id);
        $selectedClassId = (int) request('class', old('school_class_id', $classes->first()?->id));
        $suggestedEnrolled = AttendanceLog::where('school_class_id', $selectedClassId)
            ->latest('attendance_date')
            ->value('enrolled_count');

        return view('attendance.form', [
            'log' => new AttendanceLog([
                'attendance_date' => $date,
                'school_class_id' => $selectedClassId ?: null,
                'enrolled_count' => $suggestedEnrolled,
            ]),
            'classes' => $classes,
            'session' => $session,
        ]);
    }

    public function store(Request $request, AttendanceCalculationService $calculator): RedirectResponse
    {
        return $this->persist($request, $calculator, new AttendanceLog);
    }

    public function edit(AttendanceLog $attendanceLog): View
    {
        $this->assertCanWrite($attendanceLog);

        $user = auth()->user();
        $session = AcademicSession::currentForSchool($user->school_id);

        return view('attendance.form', [
            'log' => $attendanceLog,
            'classes' => $this->classesForForm($user, $session->id),
            'session' => $session,
        ]);
    }

    public function update(Request $request, AttendanceLog $attendanceLog, AttendanceCalculationService $calculator): RedirectResponse
    {
        $this->assertCanWrite($attendanceLog);

        return $this->persist($request, $calculator, $attendanceLog);
    }

    public function destroy(AttendanceLog $attendanceLog, AttendanceCalculationService $calculator): RedirectResponse
    {
        $this->assertCanWrite($attendanceLog);

        $session = $attendanceLog->academicSession;
        $term = $attendanceLog->term;
        $attendanceLog->delete();

        if ($session) {
            $calculator->recalculateForSession($session, $term);
        }

        return redirect()->route('attendance.index')->with('success', 'Attendance log removed.');
    }

    protected function persist(Request $request, AttendanceCalculationService $calculator, AttendanceLog $log): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user->canRecordAttendance(), 403);

        $session = AcademicSession::currentForSchool($user->school_id);
        abort_unless($session, 403, 'No active academic session configured.');

        $classIds = $this->classesForForm($user, $session->id)->pluck('id')->all();

        $validated = $request->validate([
            'school_class_id' => ['required', 'integer', Rule::in($classIds)],
            'attendance_date' => [
                'required',
                'date',
                Rule::unique('attendance_logs', 'attendance_date')
                    ->where(fn ($query) => $query->where('school_class_id', $request->integer('school_class_id')))
                    ->ignore($log->id),
            ],
            'enrolled_count' => ['required', 'integer', 'min:1'],
            'present_count' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'attendance_date.unique' => 'A register already exists for this class on that date. Update the existing log instead.',
        ]);

        if ((int) $validated['present_count'] > (int) $validated['enrolled_count']) {
            return back()->withErrors([
                'present_count' => 'Present cannot exceed enrolled learners.',
            ])->withInput();
        }

        $term = Term::currentForSession($session->id);

        $log->fill([
            ...$validated,
            'recorded_by' => $log->exists ? $log->recorded_by : $user->id,
            'academic_session_id' => $session->id,
            'term_id' => $term?->id,
        ])->save();

        $calculator->recalculateForSession($session, $term);

        return redirect()->route('attendance.index')->with('success', 'Attendance saved. AE-04 updated.');
    }

    protected function assertCanWrite(AttendanceLog $log): void
    {
        $user = auth()->user();
        abort_unless($user->canRecordAttendance(), 403);
        abort_unless((int) $log->schoolClass?->school_id === (int) $user->school_id, 403);

        if ($user->canManageAttendance()) {
            return;
        }

        abort_unless(
            $user->isTeacher() && in_array((int) $log->school_class_id, $this->classIds($user, $log->academic_session_id), true),
            403
        );
    }

    protected function classesForForm($user, int $sessionId)
    {
        if ($user->canManageAttendance()) {
            return SchoolClass::where('school_id', $user->school_id)
                ->orderBy('display_order')
                ->orderBy('name')
                ->get();
        }

        $ids = $this->classIds($user, $sessionId);

        return SchoolClass::whereIn('id', $ids ?: [0])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    /** @return array<int, int> */
    protected function classIds($user, int $sessionId): array
    {
        return TeacherAssignment::where('teacher_id', $user->id)
            ->where('academic_session_id', $sessionId)
            ->where('status', 'active')
            ->pluck('school_class_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
