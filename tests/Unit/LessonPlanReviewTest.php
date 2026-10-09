<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\LessonPlanReview;
use Tests\TestCase;

class LessonPlanReviewTest extends TestCase
{
    public function test_all_passed_requires_every_item(): void
    {
        $review = new LessonPlanReview;
        $user = new User;
        $user->id = 1;

        $pass = $review->snapshot($user, [
            'alignment' => true,
            'quality' => true,
            'engagement' => true,
            'assessment' => true,
        ]);
        $this->assertTrue($review->isComplete($pass));
        $this->assertTrue($review->allPassed($pass));

        $fail = $review->snapshot($user, [
            'alignment' => true,
            'quality' => true,
            'engagement' => false,
            'assessment' => true,
        ]);
        $this->assertTrue($review->isComplete($fail));
        $this->assertFalse($review->allPassed($fail));
        $this->assertFalse($review->isComplete(null));
        $this->assertFalse($review->isComplete(['items' => ['alignment' => ['passed' => true]]]));
    }
}
