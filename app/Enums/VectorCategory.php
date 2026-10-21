<?php

namespace App\Enums;

enum VectorCategory: string
{
    use HasOptions;

    case Procurement = 'procurement';
    case ConflictOfInterest = 'conflict_of_interest';
    case SocialServices = 'social_services';
    case Healthcare = 'healthcare';
    case GrantsAndNonprofits = 'grants_nonprofits';
    case EmergencyRelief = 'emergency_relief';
    case PayrollAndBenefits = 'payroll_benefits';
    case FinancialReporting = 'financial_reporting';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Procurement => 'Procurement & contracting',
            self::ConflictOfInterest => 'Conflicts of interest',
            self::SocialServices => 'Social services & childcare',
            self::Healthcare => 'Healthcare & Medicaid',
            self::GrantsAndNonprofits => 'Grants & nonprofits',
            self::EmergencyRelief => 'Emergency relief programs',
            self::PayrollAndBenefits => 'Payroll & benefits',
            self::FinancialReporting => 'Financial reporting',
            self::Other => 'Other',
        };
    }
}
