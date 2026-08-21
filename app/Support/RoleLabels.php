<?php

namespace App\Support;

class RoleLabels
{
    /** @return array<string, string> */
    public static function all(): array
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
            'it_consultant' => 'IT Consultant',
            'stem_coordinator' => 'STEM Coordinator',
            'subject_lead' => 'Subject Lead',
            'assistant_head_secondary' => 'Assistant Head (Secondary)',
        ];
    }

    public static function label(?string $role): string
    {
        if (! $role) {
            return '—';
        }

        return static::all()[$role] ?? ucwords(str_replace('_', ' ', $role));
    }
}
