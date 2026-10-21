<?php

namespace Database\Seeders;

use App\Enums\ProposalStatus;
use App\Enums\VectorCategory;
use App\Models\Vector;
use Illuminate\Database\Seeder;

/**
 * The starter catalog of fraud vectors. Safe to re-run: entries are matched by
 * title and only created when missing, so edits made in the app are kept.
 *
 * php artisan db:seed --class=VectorCatalogSeeder
 */
class VectorCatalogSeeder extends Seeder
{
    /**
     * Seed the catalog.
     */
    public function run(): void
    {
        foreach (self::vectors() as $vector) {
            Vector::firstOrCreate(
                ['title' => $vector['title']],
                [...$vector, 'status' => ProposalStatus::Active],
            );
        }
    }

    /**
     * @return list<array{title: string, category: VectorCategory, summary: string, description: string, indicators: list<string>, data_sources: list<string>}>
     */
    public static function vectors(): array
    {
        return [
            [
                'title' => 'Relatives of officials owning awarded vendors',
                'category' => VectorCategory::ConflictOfInterest,
                'summary' => 'Contracts or grants flow to companies owned by spouses, relatives, or close associates of the officials who approve them.',
                'description' => 'Officials with influence over procurement, grants, or permits steer awards to businesses owned by family members or associates, often without disclosing the relationship or recusing themselves.',
                'indicators' => [
                    'Vendor owners or registered agents share a surname or home address with an official involved in the award',
                    'Official did not recuse or file a conflict-of-interest disclosure for the award',
                    'Vendor was formed shortly before the solicitation or has no prior public-sector work',
                    'Awards to the vendor increase after the related official takes office',
                    'Sole-source or emergency justification used for the related vendor',
                ],
                'data_sources' => [
                    'Secretary of State business registrations (officers, registered agents, addresses)',
                    'Contract award and vendor payment (checkbook) data',
                    'Statements of economic interest and recusal records',
                    'Council or board meeting minutes and votes',
                    'Property records linking addresses',
                ],
            ],
            [
                'title' => 'Circular financing and round-tripped funds',
                'category' => VectorCategory::FinancialReporting,
                'summary' => 'Money moves through a loop of related entities so that public funds return to their source or inflate apparent revenue or matching contributions.',
                'description' => 'A grantee, developer, or contractor passes funds through affiliated entities (subcontractors, landlords, consultants, nonprofits) that pay back to the original party, disguising self-dealing or manufacturing the "match" a program requires.',
                'indicators' => [
                    'Payments flow to entities that share officers, addresses, or registered agents with the payer',
                    'Matching funds or donations arrive from entities that were recently paid by the recipient',
                    'Related-party transactions disclosed on audits or Form 990 Schedule L',
                    'Lease or consulting payments to entities controlled by the recipient\'s executives',
                    'Revenue and expenses of related entities rise and fall together',
                ],
                'data_sources' => [
                    'Nonprofit Form 990s (Schedule L related-party transactions)',
                    'Single Audit (Uniform Guidance) reports and findings',
                    'Business registrations and UCC filings',
                    'Grant award and drawdown records',
                    'Property and lease records',
                ],
            ],
            [
                'title' => 'Childcare centers billing for inflated attendance',
                'category' => VectorCategory::SocialServices,
                'summary' => 'Daycares receiving childcare-assistance subsidies bill for more children or hours than they actually serve.',
                'description' => 'Providers paid through state childcare assistance programs claim children who are absent, never enrolled, or exceed the licensed capacity, sometimes with kickbacks to parents who lend their eligibility.',
                'indicators' => [
                    'Billed attendance exceeds licensed capacity or staffing ratios could support',
                    'Subsidy payments far above peers of similar licensed size',
                    'Near-perfect attendance billed every day, including holidays and closures',
                    'Multiple centers sharing owners, addresses, or staff with sudden payment growth',
                    'Licensing inspections note few children present or attendance records missing',
                ],
                'data_sources' => [
                    'State childcare licensing lookup and inspection reports',
                    'Childcare assistance program (CCAP/CCDF) provider payment data',
                    'State legislative auditor and inspector general reports',
                    'Business registrations for provider owners',
                ],
            ],
            [
                'title' => 'Phantom meals in child nutrition programs',
                'category' => VectorCategory::SocialServices,
                'summary' => 'Sponsors of federal meal programs claim reimbursement for meals that were never served.',
                'description' => 'Sites under the Child Nutrition Programs (such as the Summer Food Service Program and CACFP) report implausible meal counts, often through newly created sites and vendors controlled by the same people.',
                'indicators' => [
                    'Meal counts implausibly high for the site\'s size, location, or the local child population',
                    'Rapid growth in new sites under one sponsor',
                    'Food vendors and sites with shared ownership or addresses',
                    'Identical or round-number meal counts across days',
                    'Sponsors continuing to add sites after state agency warnings',
                ],
                'data_sources' => [
                    'State education or agriculture department sponsor and site lists',
                    'USDA Food and Nutrition Service program data',
                    'Court filings and DOJ press releases for comparable schemes',
                    'Business and nonprofit registrations',
                ],
            ],
            [
                'title' => 'Medicaid billing for services not rendered',
                'category' => VectorCategory::Healthcare,
                'summary' => 'Providers bill Medicaid or other public health programs for services that were not delivered or were not medically necessary.',
                'description' => 'Common in fast-growing home- and community-based services (housing stabilization, autism therapy, personal care, adult day care), where newly enrolled providers bill aggressively with little oversight.',
                'indicators' => [
                    'Billed hours that exceed what staff could physically deliver in a day',
                    'Many new providers enrolled at the same address or by related owners',
                    'Billing growth far faster than the program overall',
                    'Recipients recruited with payments or gifts',
                    'Providers previously excluded or sanctioned under other names',
                ],
                'data_sources' => [
                    'State Medicaid provider enrollment and payment data',
                    'HHS-OIG exclusion list (LEIE) and state exclusion lists',
                    'State Medicaid Fraud Control Unit reports',
                    'CMS open payment and provider data',
                ],
            ],
            [
                'title' => 'Hospice and home health enrollment clusters',
                'category' => VectorCategory::Healthcare,
                'summary' => 'Large numbers of hospices or home health agencies are licensed at the same few addresses, often as fronts for Medicare billing.',
                'description' => 'Shell agencies are licensed in bulk, sometimes using straw owners, to bill Medicare for patients who are not terminally ill or who never received care.',
                'indicators' => [
                    'Many licensed agencies sharing a street address or suite',
                    'Owners or administrators appearing on several unrelated agencies',
                    'High rates of live discharge from hospice',
                    'Agencies with Medicare billing but no web presence or verifiable staff',
                ],
                'data_sources' => [
                    'State health department licensing databases',
                    'CMS Care Compare and provider enrollment data',
                    'Medicare provider utilization and payment data',
                    'Investigative news reporting',
                ],
            ],
            [
                'title' => 'Shell company vendors',
                'category' => VectorCategory::Procurement,
                'summary' => 'Payments go to vendors with no real operations, existing only to receive public money.',
                'description' => 'Fictitious or front companies invoice for goods or services that are never delivered, frequently set up by insiders with access to vendor onboarding or payment approval.',
                'indicators' => [
                    'Vendor address is a residence, mailbox service, or virtual office',
                    'Vendor formed shortly before its first payment',
                    'Vendor shares a bank account, phone number, or address with an employee',
                    'No website, licenses, or other customers',
                    'Invoices for vague services with round-dollar amounts',
                ],
                'data_sources' => [
                    'Vendor payment (checkbook) data',
                    'Secretary of State business registrations',
                    'SAM.gov entity registrations',
                    'Employee and vendor address comparisons where public',
                ],
            ],
            [
                'title' => 'Contract splitting to avoid bidding thresholds',
                'category' => VectorCategory::Procurement,
                'summary' => 'Purchases are split into several smaller orders so each stays under the limit that would require competitive bids or board approval.',
                'description' => 'Staff or officials break one purchase into pieces, or route it through purchasing cards, to steer work to a favored vendor without competition or oversight.',
                'indicators' => [
                    'Clusters of payments to one vendor just under a bidding or approval threshold',
                    'Sequential invoices or purchase orders on the same or adjacent days',
                    'Heavy purchasing-card use with a single vendor',
                    'Same project described across several small contracts',
                ],
                'data_sources' => [
                    'Vendor payment and purchase order data',
                    'Procurement policy thresholds',
                    'Purchasing card transaction data',
                    'Internal audit reports',
                ],
            ],
            [
                'title' => 'Bid rigging and rotation among contractors',
                'category' => VectorCategory::Procurement,
                'summary' => 'Competing bidders coordinate so that awards rotate among them at inflated prices.',
                'description' => 'Contractors agree in advance who will win, submit cover bids, or subcontract to the losers, defeating competition on public works and supply contracts.',
                'indicators' => [
                    'The same small group of bidders appears repeatedly and wins in turn',
                    'Losing bidders become subcontractors to the winner',
                    'Bid prices clustered at a fixed margin above the winner',
                    'Identical errors, formatting, or contact details across competing bids',
                ],
                'data_sources' => [
                    'Bid tabulations and award notices',
                    'Subcontractor and DBE utilization reports',
                    'DOJ Antitrust Division procurement collusion cases',
                ],
            ],
            [
                'title' => 'Change-order inflation after low bids',
                'category' => VectorCategory::Procurement,
                'summary' => 'A contractor wins with a low bid and then recovers the margin through change orders and amendments.',
                'description' => 'Contract value grows well beyond the award through change orders, scope additions, or amendments that were foreseeable at bid time, sometimes with insider knowledge.',
                'indicators' => [
                    'Final contract value far above the awarded amount',
                    'Change orders approved administratively just under council approval limits',
                    'The same contractor repeatedly wins low and grows contracts',
                    'Change orders for work that should have been in the original scope',
                ],
                'data_sources' => [
                    'Contract amendments and change-order logs',
                    'Council or board agendas approving amendments',
                    'Capital project budget reports',
                ],
            ],
            [
                'title' => 'Pay-to-play: contributions before contract awards',
                'category' => VectorCategory::ConflictOfInterest,
                'summary' => 'Vendors or their owners donate to officials who then award them contracts.',
                'description' => 'Campaign contributions, gifts, or paid travel from vendors cluster around procurement decisions made by the recipients.',
                'indicators' => [
                    'Contributions from vendor owners or employees shortly before or after an award',
                    'Contributions bundled through many employees of one vendor',
                    'Awards to contributors despite lower-priced or higher-scored competitors',
                    'Officials voting on contracts for contributors without recusal',
                ],
                'data_sources' => [
                    'State and local campaign finance filings',
                    'Gift and travel disclosures',
                    'Contract award records and evaluation scoring',
                    'Meeting minutes and roll-call votes',
                ],
            ],
            [
                'title' => 'Ghost employees on public payroll',
                'category' => VectorCategory::PayrollAndBenefits,
                'summary' => 'Payroll includes people who do not work for the agency, or who are paid for hours they did not work.',
                'description' => 'Fictitious employees, former staff kept on payroll, or no-show jobs for connected people divert salary and benefit dollars.',
                'indicators' => [
                    'Employees with pay but no assigned supervisor, location, or timesheets',
                    'Multiple employees sharing a bank account or address',
                    'Overtime far above peers in the same role',
                    'Relatives of officials in positions with no visible duties',
                ],
                'data_sources' => [
                    'Public payroll and salary databases',
                    'Overtime reports',
                    'Internal audit and inspector general reports',
                ],
            ],
            [
                'title' => 'Nonprofit pass-throughs with excessive insider pay',
                'category' => VectorCategory::GrantsAndNonprofits,
                'summary' => 'Grant-funded nonprofits spend little on programs while paying insiders, related vendors, or real estate purchases.',
                'description' => 'Organizations that depend on public grants divert funds to executive compensation, luxury purchases, related-party leases, or property bought in leaders\' names.',
                'indicators' => [
                    'Executive compensation out of line with the organization\'s size',
                    'Program spending ratio low relative to grant revenue',
                    'Related-party transactions with officers or their families',
                    'Rapid revenue growth from government grants with thin staffing',
                    'Late, missing, or qualified audits',
                ],
                'data_sources' => [
                    'IRS Form 990 filings (ProPublica Nonprofit Explorer)',
                    'Grant award databases (USAspending.gov, state grant portals)',
                    'Single Audit reports (Federal Audit Clearinghouse)',
                    'County property records',
                ],
            ],
            [
                'title' => 'Pandemic relief loans to ineligible or fabricated businesses',
                'category' => VectorCategory::EmergencyRelief,
                'summary' => 'Emergency relief such as PPP, EIDL, or rental assistance went to businesses that did not exist or overstated payroll.',
                'description' => 'Applicants fabricated businesses, payroll, or tenants, often in clusters tied to the same preparers, addresses, or lenders.',
                'indicators' => [
                    'Loan amounts implying payroll far above the business\'s apparent size',
                    'Many loans to businesses at one residential address',
                    'Businesses registered just before applying',
                    'Recipients with no prior licensing or tax footprint',
                ],
                'data_sources' => [
                    'SBA PPP and EIDL loan-level data',
                    'Emergency rental assistance program reports',
                    'DOJ COVID-19 fraud enforcement press releases',
                    'Business registrations',
                ],
            ],
            [
                'title' => 'Duplicate and inflated invoice payments',
                'category' => VectorCategory::FinancialReporting,
                'summary' => 'The same invoice is paid more than once, or invoices are inflated beyond contracted rates.',
                'description' => 'Weak payment controls let vendors resubmit invoices with small changes, or bill above contract rates, sometimes with insider help.',
                'indicators' => [
                    'Payments with the same amount, vendor, and date range',
                    'Invoice numbers that are sequential across different agencies',
                    'Billed rates above the contract schedule',
                    'Audit findings on payment controls',
                ],
                'data_sources' => [
                    'Vendor payment (checkbook) data',
                    'Contract rate schedules',
                    'Financial and compliance audit reports',
                ],
            ],
        ];
    }
}
