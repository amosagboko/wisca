<?php

namespace App\Http\Controllers\Admin;

use App\Models\AcademicSession;
use App\Models\Kpi;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends AdminController
{
    public function __invoke(): View
    {
        $school = $this->school();

        return view('admin.dashboard', [
            'school' => $school,
            'currentSession' => AcademicSession::currentForSchool($school->id),
            'stats' => [
                'sessions' => AcademicSession::where('school_id', $school->id)->count(),
                'terms' => Term::whereHas('academicSession', fn ($q) => $q->where('school_id', $school->id))->count(),
                'classes' => SchoolClass::where('school_id', $school->id)->count(),
                'subjects' => Subject::where('school_id', $school->id)->count(),
                'staff' => User::where('school_id', $school->id)->whereHas('roles', fn ($q) => $q->where('name', '!=', 'admin'))->count(),
                'assignments' => TeacherAssignment::whereHas('academicSession', fn ($q) => $q->where('school_id', $school->id))->count(),
                'kpis' => Kpi::whereHas('pillar', fn ($q) => $q->where('school_id', $school->id))->count(),
            ],
        ]);
    }
}
