<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            WiscaSeeder::class,
            DemoExamResultsSeeder::class,
            DemoAtRiskSeeder::class,
            DemoReadingSeeder::class,
            DemoChapelSeeder::class,
            DemoCharacterSeeder::class,
            DemoServiceSeeder::class,
            DemoDisciplineSeeder::class,
            DemoBullyingSeeder::class,
            DemoScriptureSeeder::class,
            DemoPartnershipSeeder::class,
            DemoLmsSeeder::class,
            DemoStemSeeder::class,
            DemoDigitalEthicsSeeder::class,
            DemoDigitalCompetencySeeder::class,
            DemoEAssessmentSeeder::class,
            DemoParentPortalSeeder::class,
        ]);
    }
}
