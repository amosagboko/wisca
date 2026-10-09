<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;

abstract class AdminController extends Controller
{
    protected function school(): School
    {
        $school = auth()->user()->school;

        abort_unless($school, 403, 'Your account is not linked to a school.');

        return $school;
    }

    protected function schoolId(): int
    {
        return $this->school()->id;
    }

    /** @return array<int, string> */
    protected function assignableRoles(): array
    {
        return [
            'teacher' => 'Teacher',
            'head_of_department' => 'Head of Department',
            'head_of_school' => 'Head of School',
            'board' => 'Board',
            'admin_officer' => 'Admin Officer',
            'learning_support_coordinator' => 'Learning Support Coordinator',
            'literacy_coordinator' => 'Literacy Coordinator',
            'chaplain' => 'Chaplain',
            'student_life_coordinator' => 'Student Life Coordinator',
            'parent_relations_lead' => 'Parent Relations Lead',
            'ict_coordinator' => 'ICT Coordinator',
            'admin_manager' => 'Admin Manager',
            'stem_coordinator' => 'STEM Coordinator',
            'subject_lead' => 'Subject Lead',
            'assistant_head_secondary' => 'Assistant Head (Secondary)',
        ];
    }

    protected function staffUsers()
    {
        return User::where('school_id', $this->schoolId())
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'admin'))
            ->orderBy('name')
            ->get();
    }
}
