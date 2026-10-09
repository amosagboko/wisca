<?php

namespace Tests\Unit;

use App\Support\WiscaOperationalCatalog;
use Tests\TestCase;

class WiscaNavigationTest extends TestCase
{
    public function test_catalog_has_twenty_three_activities(): void
    {
        $pillars = WiscaOperationalCatalog::pillars();
        $count = collect($pillars)->sum(fn (array $pillar) => count($pillar['activities']));

        $this->assertCount(3, $pillars);
        $this->assertSame(23, $count);
        $this->assertSame('Academic Excellence', $pillars[0]['label']);
        $this->assertSame('Christcentric Education', $pillars[1]['label']);
        $this->assertSame('Digital Innovation', $pillars[2]['label']);
        $this->assertNotNull(WiscaOperationalCatalog::findActivity('numeracy-progress'));
        $this->assertNotNull(WiscaOperationalCatalog::findActivity('learner-assimilation-rate'));

        $coverage = $pillars[0]['activities'][0];
        $this->assertSame('ae-01', $coverage['key']);
        $this->assertSame('1', $coverage['children'][0]['key']);
        $this->assertSame('schemes.index', $coverage['children'][0]['route']);
        $this->assertSame('curriculum-coverage.report', $coverage['children'][3]['route']);
        $this->assertNotSame('', $coverage['children'][0]['target']);
        $this->assertNotSame('', $coverage['children'][0]['frequency']);
        $this->assertNotSame('', $coverage['children'][0]['evidence']);
    }

    public function test_visible_children_follow_v2_responsibility(): void
    {
        $coverage = WiscaOperationalCatalog::findActivity('ae-01');
        $exams = WiscaOperationalCatalog::findActivity('ae-02');
        $chapel = WiscaOperationalCatalog::findActivity('ce-01');

        $this->assertCount(2, WiscaOperationalCatalog::visibleChildren($coverage, ['teacher']));
        $this->assertSame(['1', '2'], array_column(WiscaOperationalCatalog::visibleChildren($coverage, ['teacher']), 'key'));
        $this->assertCount(3, WiscaOperationalCatalog::visibleChildren($coverage, ['head_of_department']));
        $this->assertCount(5, WiscaOperationalCatalog::visibleChildren($coverage, ['teacher'], true));
        $this->assertCount(5, WiscaOperationalCatalog::visibleChildren($coverage, ['subject_lead']));

        $this->assertCount(3, WiscaOperationalCatalog::visibleChildren($exams, ['teacher']));
        $this->assertCount(1, WiscaOperationalCatalog::visibleChildren($chapel, ['teacher']));
    }
}
