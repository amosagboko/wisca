<?php

use App\Http\Controllers\Admin\AcademicSessionController;
use App\Http\Controllers\Admin\BullyingCaseTypeController;
use App\Http\Controllers\Admin\ChapelActivityTypeController;
use App\Http\Controllers\Admin\ChapelSessionAdminController;
use App\Http\Controllers\Admin\DigitalCompetencyAreaController;
use App\Http\Controllers\Admin\DigitalEthicsAuditTypeController;
use App\Http\Controllers\Admin\DisciplineIncidentTypeController;
use App\Http\Controllers\Admin\ScripturePassageController;
use App\Http\Controllers\Admin\ServiceActivityTypeController;
use App\Http\Controllers\Admin\StemProjectTypeController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\GuardianController;
use App\Http\Controllers\Admin\PartnershipCharterController;
use App\Http\Controllers\Admin\KpiController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\SchoolClassController;
use App\Http\Controllers\Admin\SubjectController;
use App\Http\Controllers\Admin\TeacherAssignmentController;
use App\Http\Controllers\Admin\TermController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AcademicPeriodController;
use App\Http\Controllers\AttendanceLogController;
use App\Http\Controllers\AtRiskLearnerController;
use App\Http\Controllers\BullyingCaseController;
use App\Http\Controllers\ChapelController;
use App\Http\Controllers\ComingSoonActivityController;
use App\Http\Controllers\CharacterRatingController;
use App\Http\Controllers\Admin\CharacterDomainController;
use App\Http\Controllers\Admin\ControlPanelController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DigitalCompetencyController;
use App\Http\Controllers\DigitalEthicsAuditController;
use App\Http\Controllers\DisciplineIncidentController;
use App\Http\Controllers\EAssessmentController;
use App\Http\Controllers\ExamResultController;
use App\Http\Controllers\HomeworkLogController;
use App\Http\Controllers\InterventionPlanController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LeadershipWeekReviewController;
use App\Http\Controllers\LearnerController;
use App\Http\Controllers\LessonPlanController;
use App\Http\Controllers\LmsUsageController;
use App\Http\Controllers\ObservationController;
use App\Http\Controllers\ParentPortalController;
use App\Http\Controllers\PartnershipSignatureController;
use App\Http\Controllers\ReadingAssessmentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServiceLogController;
use App\Http\Controllers\StemProjectController;
use App\Http\Controllers\CurriculumCoverageReportController;
use App\Http\Controllers\PlanningPolicyController;
use App\Http\Controllers\SchemeOfWorkController;
use App\Http\Controllers\ScriptureAssessmentController;
use App\Http\Controllers\StatusThresholdController;
use App\Http\Controllers\TopicCatchUpPlanController;
use App\Http\Controllers\TopicCoverageLogController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/leadership-week-reviews', [LeadershipWeekReviewController::class, 'store'])->name('leadership-week-reviews.store');

    Route::get('/academic-period', [AcademicPeriodController::class, 'show'])->name('academic-period.show');
    Route::post('/academic-period/sessions', [AcademicPeriodController::class, 'storeSession'])->name('academic-period.sessions.store');
    Route::post('/academic-period/terms', [AcademicPeriodController::class, 'storeTerm'])->name('academic-period.terms.store');
    Route::post('/academic-period/transition', [AcademicPeriodController::class, 'transition'])->name('academic-period.transition');
    Route::post('/academic-period/rollover', [AcademicPeriodController::class, 'rollover'])->name('academic-period.rollover');
    Route::post('/academic-period/open', [AcademicPeriodController::class, 'openInitial'])->name('academic-period.open');

    Route::get('/status-thresholds', [StatusThresholdController::class, 'edit'])->name('status-thresholds.edit');
    Route::put('/status-thresholds', [StatusThresholdController::class, 'update'])->name('status-thresholds.update');
    Route::get('/planning-policy', [PlanningPolicyController::class, 'edit'])->name('planning-policy.edit');
    Route::put('/planning-policy', [PlanningPolicyController::class, 'update'])->name('planning-policy.update');
    Route::get('/curriculum-coverage', CurriculumCoverageReportController::class)->name('curriculum-coverage.report');
    Route::post('/catch-ups', [TopicCatchUpPlanController::class, 'store'])->name('catch-ups.store');
    Route::post('/catch-ups/{catchUp}/cancel', [TopicCatchUpPlanController::class, 'cancel'])->name('catch-ups.cancel');

    Route::get('/schemes', [SchemeOfWorkController::class, 'index'])->name('schemes.index');
    Route::get('/schemes/create', [SchemeOfWorkController::class, 'create'])->name('schemes.create');
    Route::get('/schemes/template', [SchemeOfWorkController::class, 'template'])->name('schemes.template');
    Route::post('/schemes', [SchemeOfWorkController::class, 'store'])->name('schemes.store');
    Route::get('/schemes/{scheme}', [SchemeOfWorkController::class, 'show'])->name('schemes.show');
    Route::get('/schemes/{scheme}/edit', [SchemeOfWorkController::class, 'edit'])->name('schemes.edit');
    Route::put('/schemes/{scheme}', [SchemeOfWorkController::class, 'update'])->name('schemes.update');
    Route::post('/schemes/{scheme}/submit', [SchemeOfWorkController::class, 'submit'])->name('schemes.submit');
    Route::post('/schemes/{scheme}/clone', [SchemeOfWorkController::class, 'clone'])->name('schemes.clone');
    Route::post('/schemes/{scheme}/approve', [SchemeOfWorkController::class, 'approve'])->name('schemes.approve');
    Route::post('/schemes/{scheme}/reject', [SchemeOfWorkController::class, 'reject'])->name('schemes.reject');
    Route::post('/schemes/{scheme}/activate', [SchemeOfWorkController::class, 'activate'])->name('schemes.activate');
    Route::delete('/schemes/{scheme}', [SchemeOfWorkController::class, 'destroy'])->name('schemes.destroy');
    Route::get('/schemes/{scheme}/file', [SchemeOfWorkController::class, 'file'])->name('schemes.file');

    Route::get('/coverage-logs', [TopicCoverageLogController::class, 'index'])->name('coverage-logs.index');
    Route::get('/coverage-logs/create', [TopicCoverageLogController::class, 'create'])->name('coverage-logs.create');
    Route::post('/coverage-logs', [TopicCoverageLogController::class, 'store'])->name('coverage-logs.store');
    Route::post('/coverage-logs/{coverageLog}/verify', [TopicCoverageLogController::class, 'verify'])->name('coverage-logs.verify');
    Route::post('/coverage-logs/{coverageLog}/reject', [TopicCoverageLogController::class, 'reject'])->name('coverage-logs.reject');

    Route::get('/lesson-plans', [LessonPlanController::class, 'index'])->name('lesson-plans.index');
    Route::get('/lesson-plans/create', [LessonPlanController::class, 'create'])->name('lesson-plans.create');
    Route::post('/lesson-plans', [LessonPlanController::class, 'store'])->name('lesson-plans.store');
    Route::get('/lesson-plans/{lessonPlan}/edit', [LessonPlanController::class, 'edit'])->name('lesson-plans.edit');
    Route::put('/lesson-plans/{lessonPlan}', [LessonPlanController::class, 'update'])->name('lesson-plans.update');
    Route::post('/lesson-plans/{lessonPlan}/approve', [LessonPlanController::class, 'approve'])->name('lesson-plans.approve');
    Route::post('/lesson-plans/{lessonPlan}/reject', [LessonPlanController::class, 'reject'])->name('lesson-plans.reject');

    Route::get('/homework', [HomeworkLogController::class, 'index'])->name('homework.index');
    Route::get('/homework/create', [HomeworkLogController::class, 'create'])->name('homework.create');
    Route::post('/homework', [HomeworkLogController::class, 'store'])->name('homework.store');
    Route::get('/homework/{homeworkLog}/edit', [HomeworkLogController::class, 'edit'])->name('homework.edit');
    Route::put('/homework/{homeworkLog}', [HomeworkLogController::class, 'update'])->name('homework.update');
    Route::post('/homework/{homeworkLog}/verify', [HomeworkLogController::class, 'verify'])->name('homework.verify');
    Route::post('/homework/{homeworkLog}/reject', [HomeworkLogController::class, 'reject'])->name('homework.reject');
    Route::delete('/homework/{homeworkLog}', [HomeworkLogController::class, 'destroy'])->name('homework.destroy');

    Route::get('/attendance', [AttendanceLogController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/create', [AttendanceLogController::class, 'create'])->name('attendance.create');
    Route::post('/attendance', [AttendanceLogController::class, 'store'])->name('attendance.store');
    Route::get('/attendance/{attendanceLog}/edit', [AttendanceLogController::class, 'edit'])->name('attendance.edit');
    Route::put('/attendance/{attendanceLog}', [AttendanceLogController::class, 'update'])->name('attendance.update');
    Route::post('/attendance/{attendanceLog}/verify', [AttendanceLogController::class, 'verify'])->name('attendance.verify');
    Route::post('/attendance/{attendanceLog}/reject', [AttendanceLogController::class, 'reject'])->name('attendance.reject');
    Route::delete('/attendance/{attendanceLog}', [AttendanceLogController::class, 'destroy'])->name('attendance.destroy');

    Route::get('/learners', [LearnerController::class, 'index'])->name('learners.index');
    Route::get('/learners/create', [LearnerController::class, 'create'])->name('learners.create');
    Route::post('/learners', [LearnerController::class, 'store'])->name('learners.store');
    Route::get('/learners/{learner}/edit', [LearnerController::class, 'edit'])->name('learners.edit');
    Route::put('/learners/{learner}', [LearnerController::class, 'update'])->name('learners.update');
    Route::delete('/learners/{learner}', [LearnerController::class, 'destroy'])->name('learners.destroy');

    Route::get('/exam-results', [ExamResultController::class, 'index'])->name('exam-results.index');
    Route::get('/exam-results/marksheet', [ExamResultController::class, 'edit'])->name('exam-results.edit');
    Route::put('/exam-results/marksheet', [ExamResultController::class, 'update'])->name('exam-results.update');
    Route::post('/exam-results/verify', [ExamResultController::class, 'verify'])->name('exam-results.verify');
    Route::post('/exam-results/reject', [ExamResultController::class, 'reject'])->name('exam-results.reject');

    Route::get('/at-risk', [AtRiskLearnerController::class, 'index'])->name('at-risk.index');
    Route::get('/at-risk/create', [AtRiskLearnerController::class, 'create'])->name('at-risk.create');
    Route::post('/at-risk', [AtRiskLearnerController::class, 'store'])->name('at-risk.store');
    Route::post('/at-risk/from-exam', [AtRiskLearnerController::class, 'fromExam'])->name('at-risk.from-exam');
    Route::get('/at-risk/{atRiskLearner}', [AtRiskLearnerController::class, 'show'])->name('at-risk.show');
    Route::post('/at-risk/{atRiskLearner}/resolve', [AtRiskLearnerController::class, 'resolve'])->name('at-risk.resolve');
    Route::get('/at-risk/{atRiskLearner}/plans/create', [InterventionPlanController::class, 'create'])->name('at-risk.plans.create');
    Route::post('/at-risk/{atRiskLearner}/plans', [InterventionPlanController::class, 'store'])->name('at-risk.plans.store');
    Route::get('/intervention-plans/{interventionPlan}/edit', [InterventionPlanController::class, 'edit'])->name('intervention-plans.edit');
    Route::put('/intervention-plans/{interventionPlan}', [InterventionPlanController::class, 'update'])->name('intervention-plans.update');

    Route::get('/activities/{activity}', ComingSoonActivityController::class)
        ->whereIn('activity', ['numeracy-progress', 'learner-assimilation-rate'])
        ->name('activities.coming-soon');

    Route::get('/reading', [ReadingAssessmentController::class, 'index'])->name('reading.index');
    Route::get('/reading/record', [ReadingAssessmentController::class, 'create'])->name('reading.create');
    Route::post('/reading', [ReadingAssessmentController::class, 'store'])->name('reading.store');

    // CE-01 chapel / assembly attendance
    Route::get('/chapel', [ChapelController::class, 'index'])->name('chapel.index');
    Route::get('/chapel/{chapelSession}/roll', [ChapelController::class, 'roll'])->name('chapel.roll');
    Route::post('/chapel/{chapelSession}/roll', [ChapelController::class, 'saveRoll'])->name('chapel.roll.save');

    // CE-02 character development ratings
    Route::get('/character', [CharacterRatingController::class, 'index'])->name('character.index');
    Route::get('/character/rate', [CharacterRatingController::class, 'create'])->name('character.create');
    Route::post('/character/rate', [CharacterRatingController::class, 'store'])->name('character.store');

    // CE-03 community service hours
    Route::get('/service', [ServiceLogController::class, 'index'])->name('service.index');
    Route::get('/service/log', [ServiceLogController::class, 'create'])->name('service.create');
    Route::post('/service/log', [ServiceLogController::class, 'store'])->name('service.store');
    Route::get('/service/{serviceLog}/edit', [ServiceLogController::class, 'edit'])->name('service.edit');
    Route::put('/service/{serviceLog}', [ServiceLogController::class, 'update'])->name('service.update');
    Route::post('/service/{serviceLog}/verify', [ServiceLogController::class, 'verify'])->name('service.verify');
    Route::post('/service/{serviceLog}/reject', [ServiceLogController::class, 'reject'])->name('service.reject');

    // CE-04 restorative discipline
    Route::get('/discipline', [DisciplineIncidentController::class, 'index'])->name('discipline.index');
    Route::get('/discipline/create', [DisciplineIncidentController::class, 'create'])->name('discipline.create');
    Route::post('/discipline', [DisciplineIncidentController::class, 'store'])->name('discipline.store');
    Route::get('/discipline/{disciplineIncident}/edit', [DisciplineIncidentController::class, 'edit'])->name('discipline.edit');
    Route::put('/discipline/{disciplineIncident}', [DisciplineIncidentController::class, 'update'])->name('discipline.update');

    // CE-05 anti-bullying case management
    Route::get('/bullying', [BullyingCaseController::class, 'index'])->name('bullying.index');
    Route::get('/bullying/create', [BullyingCaseController::class, 'create'])->name('bullying.create');
    Route::post('/bullying', [BullyingCaseController::class, 'store'])->name('bullying.store');
    Route::get('/bullying/{bullyingCase}/edit', [BullyingCaseController::class, 'edit'])->name('bullying.edit');
    Route::put('/bullying/{bullyingCase}', [BullyingCaseController::class, 'update'])->name('bullying.update');

    // CE-06 scripture mastery
    Route::get('/scripture', [ScriptureAssessmentController::class, 'index'])->name('scripture.index');
    Route::get('/scripture/assess', [ScriptureAssessmentController::class, 'create'])->name('scripture.create');
    Route::post('/scripture/assess', [ScriptureAssessmentController::class, 'store'])->name('scripture.store');

    // CE-07 parent-school partnership
    Route::get('/partnership', [PartnershipSignatureController::class, 'index'])->name('partnership.index');
    Route::get('/partnership/record', [PartnershipSignatureController::class, 'create'])->name('partnership.create');
    Route::post('/partnership/record', [PartnershipSignatureController::class, 'store'])->name('partnership.store');

    // DI-01 LMS adoption
    Route::get('/lms', [LmsUsageController::class, 'index'])->name('lms.index');
    Route::get('/lms/record', [LmsUsageController::class, 'create'])->name('lms.create');
    Route::post('/lms/record', [LmsUsageController::class, 'store'])->name('lms.store');

    // DI-02 STEM project completion
    Route::get('/stem', [StemProjectController::class, 'index'])->name('stem.index');
    Route::get('/stem/record', [StemProjectController::class, 'create'])->name('stem.create');
    Route::post('/stem/record', [StemProjectController::class, 'store'])->name('stem.store');

    // DI-03 AI & digital ethics compliance
    Route::get('/ethics', [DigitalEthicsAuditController::class, 'index'])->name('ethics.index');
    Route::get('/ethics/create', [DigitalEthicsAuditController::class, 'create'])->name('ethics.create');
    Route::post('/ethics', [DigitalEthicsAuditController::class, 'store'])->name('ethics.store');
    Route::get('/ethics/{digitalEthicsAudit}/edit', [DigitalEthicsAuditController::class, 'edit'])->name('ethics.edit');
    Route::put('/ethics/{digitalEthicsAudit}', [DigitalEthicsAuditController::class, 'update'])->name('ethics.update');

    // DI-04 staff digital competency
    Route::get('/competency', [DigitalCompetencyController::class, 'index'])->name('competency.index');
    Route::get('/competency/record', [DigitalCompetencyController::class, 'create'])->name('competency.create');
    Route::post('/competency/record', [DigitalCompetencyController::class, 'store'])->name('competency.store');

    // DI-05 e-portfolio & digital assessment usage
    Route::get('/eassessment', [EAssessmentController::class, 'index'])->name('eassessment.index');
    Route::get('/eassessment/record', [EAssessmentController::class, 'create'])->name('eassessment.create');
    Route::post('/eassessment/record', [EAssessmentController::class, 'store'])->name('eassessment.store');

    // DI-06 parent portal engagement
    Route::get('/portal-engagement', [ParentPortalController::class, 'index'])->name('portal-engagement.index');
    Route::get('/portal-engagement/record', [ParentPortalController::class, 'create'])->name('portal-engagement.create');
    Route::post('/portal-engagement/record', [ParentPortalController::class, 'store'])->name('portal-engagement.store');

    Route::get('/observations', [ObservationController::class, 'index'])->name('observations.index');
    Route::get('/observations/create', [ObservationController::class, 'create'])->name('observations.create');
    Route::post('/observations', [ObservationController::class, 'store'])->name('observations.store');
    Route::get('/observations/{observation}', [ObservationController::class, 'show'])->name('observations.show');
    Route::get('/observations/{observation}/edit', [ObservationController::class, 'edit'])->name('observations.edit');
    Route::put('/observations/{observation}', [ObservationController::class, 'update'])->name('observations.update');
    Route::delete('/observations/{observation}', [ObservationController::class, 'destroy'])->name('observations.destroy');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::get('control-panel', [ControlPanelController::class, 'edit'])->name('control-panel.edit');
        Route::put('control-panel', [ControlPanelController::class, 'update'])->name('control-panel.update');
        Route::post('sessions/{session}/activate', [AcademicSessionController::class, 'activate'])->name('sessions.activate');
        Route::resource('sessions', AcademicSessionController::class)->except(['show']);
        Route::resource('terms', TermController::class)->except(['show']);
        Route::resource('classes', SchoolClassController::class)->except(['show']);
        Route::resource('departments', DepartmentController::class)->except(['show']);
        Route::resource('subjects', SubjectController::class)->except(['show']);
        Route::resource('users', UserController::class)->except(['show']);
        Route::resource('assignments', TeacherAssignmentController::class)->except(['show'])->parameters(['assignments' => 'assignment']);
        Route::resource('kpis', KpiController::class)->only(['index', 'edit', 'update']);

        // CE-02 admin: character domains
        Route::resource('character-domains', CharacterDomainController::class)
            ->except(['show'])
            ->parameters(['character-domains' => 'characterDomain']);

        // CE-03 admin: service activity types
        Route::resource('service-activity-types', ServiceActivityTypeController::class)
            ->except(['show'])
            ->parameters(['service-activity-types' => 'serviceActivityType']);

        // CE-04 admin: discipline incident types
        Route::resource('discipline-incident-types', DisciplineIncidentTypeController::class)
            ->except(['show'])
            ->parameters(['discipline-incident-types' => 'disciplineIncidentType']);

        // CE-05 admin: bullying case types
        Route::resource('bullying-case-types', BullyingCaseTypeController::class)
            ->except(['show'])
            ->parameters(['bullying-case-types' => 'bullyingCaseType']);

        // CE-06 admin: scripture passages
        Route::resource('scripture-passages', ScripturePassageController::class)
            ->except(['show'])
            ->parameters(['scripture-passages' => 'scripturePassage']);

        // CE-07 admin: partnership charters + parent registry
        Route::resource('partnership-charters', PartnershipCharterController::class)
            ->except(['show'])
            ->parameters(['partnership-charters' => 'partnershipCharter']);
        Route::resource('guardians', GuardianController::class)
            ->except(['show']);

        // DI-02 admin: STEM project types
        Route::resource('stem-project-types', StemProjectTypeController::class)
            ->except(['show'])
            ->parameters(['stem-project-types' => 'stemProjectType']);

        // DI-03 admin: digital ethics audit types
        Route::resource('digital-ethics-audit-types', DigitalEthicsAuditTypeController::class)
            ->except(['show'])
            ->parameters(['digital-ethics-audit-types' => 'digitalEthicsAuditType']);

        // DI-04 admin: digital competency areas
        Route::resource('digital-competency-areas', DigitalCompetencyAreaController::class)
            ->except(['show'])
            ->parameters(['digital-competency-areas' => 'digitalCompetencyArea']);

        // CE-01 admin: activity types + session scheduling
        Route::resource('chapel-activity-types', ChapelActivityTypeController::class)
            ->except(['show'])
            ->parameters(['chapel-activity-types' => 'chapelActivityType']);
        Route::resource('chapel-sessions', ChapelSessionAdminController::class)
            ->except(['show'])
            ->parameters(['chapel-sessions' => 'chapelSession']);
    });
});
