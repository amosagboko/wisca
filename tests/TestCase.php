<?php

namespace Tests;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Collection;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param  SchoolClass|list<SchoolClass>|Collection<int, SchoolClass>  $classes
     * @param  list<int>  $subjectIds
     */
    protected function assignSectionHod(School $school, User $hod, mixed $classes, string $name, array $subjectIds = []): SchoolSection
    {
        $section = SchoolSection::create([
            'school_id' => $school->id,
            'name' => $name,
            'status' => 'active',
            'hod_id' => $hod->id,
        ]);

        foreach (Collection::wrap($classes) as $class) {
            $class->update(['school_section_id' => $section->id]);
            if ($subjectIds !== []) {
                $class->offeredSubjects()->syncWithoutDetaching($subjectIds);
            }
        }

        return $section;
    }
}
