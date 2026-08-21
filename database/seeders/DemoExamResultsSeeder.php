<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\ExamResult;
use App\Models\Kpi;
use App\Models\Learner;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Services\ExamCalculationService;
use Illuminate\Database\Seeder;

class DemoExamResultsSeeder extends Seeder
{
    public function run(): void
    {
        $school = School::where('slug', 'wca-makurdi')->first();
        if (! $school) {
            return;
        }

        $session = AcademicSession::currentForSchool($school->id);
        $term = $session ? Term::currentForSession($session->id) : null;
        $class = SchoolClass::where('school_id', $school->id)->where('name', 'JSS 1A')->first();
        $subject = Subject::where('school_id', $school->id)->where('code', 'MTH')->first();
        $teacher = User::where('email', 'teacher@wisca.test')->first();

        if (! $session || ! $term || ! $class || ! $subject || ! $teacher) {
            return;
        }

        $kpi = Kpi::where('code', 'AE-02')->first();
        if ($kpi) {
            $config = $kpi->config ?? [];
            $config['pass_mark'] = $config['pass_mark'] ?? 50;
            $kpi->update(['config' => $config]);
        }

        $roll = [
            ['name' => 'Ada Terhemba', 'admission_no' => 'WCA/2025/001', 'gender' => 'female', 'score' => 88],
            ['name' => 'David Iorbee', 'admission_no' => 'WCA/2025/002', 'gender' => 'male', 'score' => 76],
            ['name' => 'Faith Ngodoo', 'admission_no' => 'WCA/2025/003', 'gender' => 'female', 'score' => 91],
            ['name' => 'Grace Mnguember', 'admission_no' => 'WCA/2025/004', 'gender' => 'female', 'score' => 55],
            ['name' => 'Isaac Terver', 'admission_no' => 'WCA/2025/005', 'gender' => 'male', 'score' => 72],
            ['name' => 'Joy Dooshima', 'admission_no' => 'WCA/2025/006', 'gender' => 'female', 'score' => 41],
            ['name' => 'Michael Tor', 'admission_no' => 'WCA/2025/007', 'gender' => 'male', 'score' => 81],
            ['name' => 'Patience Sewuese', 'admission_no' => 'WCA/2025/008', 'gender' => 'female', 'score' => 64],
            ['name' => 'Samuel Iember', 'admission_no' => 'WCA/2025/009', 'gender' => 'male', 'score' => 53],
            ['name' => 'Blessing Nguvan', 'admission_no' => 'WCA/2025/010', 'gender' => 'female', 'score' => 50],
        ];

        foreach ($roll as $row) {
            $learner = Learner::firstOrCreate(
                [
                    'school_id' => $school->id,
                    'admission_no' => $row['admission_no'],
                ],
                [
                    'school_class_id' => $class->id,
                    'name' => $row['name'],
                    'gender' => $row['gender'],
                    'status' => 'enrolled',
                ]
            );

            ExamResult::updateOrCreate(
                [
                    'learner_id' => $learner->id,
                    'subject_id' => $subject->id,
                    'academic_session_id' => $session->id,
                    'term_id' => $term->id,
                    'assessment_key' => ExamCalculationService::ASSESSMENT_KEY,
                ],
                [
                    'school_class_id' => $class->id,
                    'recorded_by' => $teacher->id,
                    'assessment_name' => $term->name.' examination',
                    'score' => $row['score'],
                ]
            );
        }

        app(ExamCalculationService::class)->recalculateForSession($session, $term);
    }
}
