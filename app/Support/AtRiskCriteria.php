<?php

namespace App\Support;

class AtRiskCriteria
{
    public const BELOW_PASS_MARK = 'below_pass_mark';

    public const CONCERN = 'concern';

    public const TIER_2 = 'tier_2';

    public const TIER_3 = 'tier_3';

    /** @return array<string, string> */
    public static function factors(): array
    {
        return [
            self::BELOW_PASS_MARK => 'Below pass mark',
            self::CONCERN => 'Concern',
        ];
    }

    /** @return array<string, string> */
    public static function planTypes(): array
    {
        return [
            self::TIER_2 => 'Tier 2',
            self::TIER_3 => 'Tier 3',
        ];
    }

    /** @return array<string, string> */
    public static function levels(): array
    {
        return [
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'Critical',
        ];
    }

    /** @return array<string, string> */
    public static function identificationStatuses(): array
    {
        return [
            'active' => 'Active',
            'resolved' => 'Resolved',
        ];
    }

    /** @return array<string, string> */
    public static function planStatuses(): array
    {
        return [
            'draft' => 'Draft',
            'active' => 'Active',
            'completed' => 'Completed',
            'discontinued' => 'Discontinued',
        ];
    }

    public static function factorLabel(string $key): string
    {
        return static::factors()[$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    public static function planTypeLabel(string $key): string
    {
        return static::planTypes()[$key] ?? $key;
    }

    public static function isSupportPlan(string $planType): bool
    {
        return in_array($planType, [self::TIER_2, self::TIER_3], true);
    }
}
