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
            [
                'title' => 'Customs duty and tariff evasion',
                'category' => VectorCategory::TaxAndCustoms,
                'summary' => 'Importers underpay customs duties and tariffs by misstating what goods are, where they were made, or what they cost.',
                'description' => 'Importers or their brokers misclassify goods under lower-duty tariff codes, undervalue shipments, falsely claim a country of origin or trade-agreement preference, or route goods through third countries to dodge antidumping, countervailing, Section 232, or Section 301 duties. Knowingly avoiding duties owed is a false claim against the United States: whistleblowers can receive 15–30% of the recovery through a False Claims Act qui tam suit, and criminal trade, tariff, and customs fraud is covered by the DOJ Corporate Whistleblower Awards Pilot Program.',
                'indicators' => [
                    'Declared values far below market prices or what other importers pay for the same goods',
                    'Country of origin shifting to a third country soon after duties are imposed on the original source',
                    'Shipments routed through countries with little capacity to manufacture the declared goods',
                    'Tariff classifications changing to lower-duty codes without any change in the product',
                    'Two sets of invoices for the same shipment, or supplier payments above the declared value',
                    'Products marketed as made in a high-duty country but imported as originating elsewhere',
                ],
                'data_sources' => [
                    'Import bill-of-lading and shipment records',
                    'Commerce Department antidumping and countervailing duty orders and scope rulings',
                    'CBP classification rulings (CROSS) and the Harmonized Tariff Schedule',
                    'CBP Enforce and Protect Act (EAPA) evasion determinations',
                    'DOJ press releases on customs-fraud False Claims Act settlements',
                    'Company catalogs, marketing, and supplier disclosures',
                ],
            ],
            [
                'title' => 'Unpaid payroll taxes and misclassified workers',
                'category' => VectorCategory::TaxAndCustoms,
                'summary' => 'Contractors, often on public works, pay workers off the books or as fake independent contractors to skip payroll taxes and underbid honest competitors.',
                'description' => 'Employers misclassify employees as independent contractors, pay cash through shell subcontractors and check-cashing schemes, or withhold employment taxes from workers\' pay without remitting them to the IRS. On public projects this lets the contractor win with low bids while shifting costs to taxpayers, and it often travels with prevailing-wage and insurance fraud. The IRS Whistleblower Office pays 15–30% of the taxes, penalties, and interest collected when more than $2 million is in dispute.',
                'indicators' => [
                    'Certified payroll shows few employees for the size of the job, or lists most workers as 1099 subcontractors',
                    'Federal or state tax liens filed against a contractor that keeps winning public work',
                    'Layers of small subcontractors that appear briefly, cash their checks, and dissolve',
                    'Labor costs implausibly low compared with competing bids',
                    'Prevailing-wage, wage-theft, or unemployment-insurance findings against the contractor',
                ],
                'data_sources' => [
                    'Certified payroll reports on public works projects',
                    'County recorder filings of federal and state tax liens',
                    'State labor department prevailing-wage and misclassification enforcement actions',
                    'Contract award and subcontractor utilization records',
                    'Secretary of State business registrations for subcontractors',
                ],
            ],
            [
                'title' => 'Upcoded diagnoses in Medicare Advantage risk adjustment',
                'category' => VectorCategory::Healthcare,
                'summary' => 'Medicare Advantage plans and their providers add or exaggerate diagnoses to raise the risk-adjusted payments Medicare makes for each member.',
                'description' => 'Medicare pays Medicare Advantage plans more for sicker members, so plans and contracted providers can inflate payments by submitting diagnoses that medical records do not support, mining charts in reviews that only ever add codes, or capturing diagnoses in in-home assessments that lead to no treatment. Risk-adjustment fraud is a leading source of False Claims Act recoveries, and whistleblowers can receive 15–30% of the recovery.',
                'indicators' => [
                    'Risk scores rising far faster than peer plans or the members\' actual use of care',
                    'Chart reviews or in-home health assessments that add diagnoses but never delete unsupported ones',
                    'Diagnoses reported for payment with no follow-up treatment, prescriptions, or referrals',
                    'Provider incentive pay tied to the number of diagnoses captured',
                    'CMS risk-adjustment data validation (RADV) audits finding high error rates',
                ],
                'data_sources' => [
                    'CMS Medicare Advantage enrollment and plan payment data',
                    'HHS-OIG audits of Medicare Advantage diagnosis coding',
                    'CMS RADV audit results',
                    'DOJ False Claims Act settlements involving Medicare Advantage',
                    'Plan SEC filings and earnings calls describing risk-adjustment programs',
                ],
            ],
            [
                'title' => 'Kickbacks for patient referrals and prescriptions',
                'category' => VectorCategory::Healthcare,
                'summary' => 'Providers, labs, pharmacies, or drug and device makers pay doctors, marketers, or patients to steer business that public health programs then pay for.',
                'description' => 'Payments disguised as consulting fees, sham medical directorships, speaker programs, above-market rent, or per-referral "marketing" commissions reward referrals of Medicare, Medicaid, or TRICARE patients, tests, and prescriptions. Claims tainted by kickbacks or prohibited self-referrals violate the Anti-Kickback Statute and the Stark Law and are false claims: whistleblowers can receive 15–30% of the recovery, and corporate health care offenses are covered by the DOJ Corporate Whistleblower Awards Pilot Program.',
                'indicators' => [
                    'Large industry payments to physicians who are also top prescribers or referrers of the payer\'s products',
                    'Medical directorships or consulting agreements with no documented work',
                    'Marketers paid per referral, per patient, or per test ordered',
                    'Free services, waived copays, or gifts offered to patients',
                    'Referrals concentrated on one lab, pharmacy, or facility with financial ties to the referrer',
                ],
                'data_sources' => [
                    'CMS Open Payments (industry payments to physicians)',
                    'Medicare Part D prescriber and Part B utilization data',
                    'HHS-OIG fraud alerts, advisory opinions, and enforcement actions',
                    'DOJ press releases on Anti-Kickback Statute settlements',
                    'State Medicaid provider payment data',
                ],
            ],
            [
                'title' => 'Front companies winning small-business and DBE set-asides',
                'category' => VectorCategory::Procurement,
                'summary' => 'Large or ineligible firms use front companies to win contracts reserved for small, disadvantaged, women-owned, or veteran-owned businesses.',
                'description' => 'A front is certified as a small or disadvantaged business (SBA 8(a), HUBZone, service-disabled veteran-owned, women-owned, or a transportation DBE) while an ineligible firm actually controls the work, decisions, and profits, or a certified firm passes nearly all of its work to others. Each set-aside award and payment obtained through a false eligibility claim is a false claim: whistleblowers can receive 15–30% of the recovery through a False Claims Act qui tam suit, and procurement fraud against the United States is covered by the DOJ Corporate Whistleblower Awards Pilot Program.',
                'indicators' => [
                    'Certified firm shares an address, equipment, employees, or bonding with a large non-certified firm',
                    'The listed owner lacks industry experience or works elsewhere full time',
                    'Certified prime or subcontractor performs little of the work and passes it to another firm',
                    'Revenue or headcount above the size standard, or a recent acquisition by a large company',
                    'The same few certified firms repeatedly paired with one large contractor',
                ],
                'data_sources' => [
                    'SBA Dynamic Small Business Search and certification directories',
                    'State Unified Certification Program (UCP) DBE directories',
                    'SAM.gov entity registrations and size representations',
                    'USAspending.gov set-aside award data',
                    'Subcontractor and DBE utilization reports',
                    'Secretary of State business registrations',
                ],
            ],
            [
                'title' => 'False cybersecurity compliance claims by contractors',
                'category' => VectorCategory::Procurement,
                'summary' => 'Contractors and grantees claim to meet required cybersecurity standards that they do not actually follow.',
                'description' => 'Federal contracts and grants increasingly require protections such as NIST SP 800-171, CMMC certification, FedRAMP authorization, and prompt incident reporting. Companies that misstate their security scores, skip required controls, or hide breaches while billing for compliant work submit false claims. DOJ\'s Civil Cyber-Fraud Initiative pursues these cases under the False Claims Act, and whistleblowers can receive 15–30% of the recovery.',
                'indicators' => [
                    'Self-assessed NIST SP 800-171 scores inconsistent with the company\'s size or known practices',
                    'Contracts requiring CMMC or FedRAMP awarded to firms without the certification or authorization',
                    'Breaches or ransomware incidents that were never reported to the contracting agency',
                    'Government data hosted with offshore or unauthorized cloud providers',
                    'Security staff departures or lawsuits alleging ignored compliance warnings',
                ],
                'data_sources' => [
                    'Contract solicitations and award terms (SAM.gov, USAspending.gov)',
                    'FedRAMP Marketplace authorization listings',
                    'CMMC certification status (Cyber AB Marketplace)',
                    'State data-breach notification filings',
                    'DOJ Civil Cyber-Fraud Initiative settlements',
                ],
            ],
            [
                'title' => 'Substituted products and falsified test or origin certifications',
                'category' => VectorCategory::Procurement,
                'summary' => 'Suppliers deliver cheaper, foreign-made, or untested goods while certifying that they meet the contract\'s specifications.',
                'description' => 'Vendors swap in off-spec materials, falsify inspection or testing records, or certify that goods are domestic under the Buy American Act, Build America, Buy America (BABA) requirements, or the Trade Agreements Act when they are not. Common in defense parts, infrastructure materials such as steel and concrete, and medical supplies. Each invoice for non-conforming goods is a false claim, and whistleblowers can receive 15–30% of the recovery through a False Claims Act qui tam suit.',
                'indicators' => [
                    'Test reports or mill certificates with identical results across batches, or from labs that never ran the tests',
                    'Delivered goods with foreign markings despite Buy American or BABA certifications',
                    'Prices far below competitors for items with strict specifications',
                    'Quality failures, recalls, or early replacement of delivered products',
                    'Schedule products listed as Trade Agreements Act compliant while sourced from non-designated countries',
                ],
                'data_sources' => [
                    'Contract specifications and Buy American or BABA waiver notices',
                    'Inspection, materials testing, and quality reports on public works projects',
                    'Import shipment records for the supplier',
                    'GSA Advantage and schedule product listings',
                    'DOJ press releases on product-substitution settlements',
                ],
            ],
            [
                'title' => 'Price fixing and market allocation among suppliers',
                'category' => VectorCategory::Procurement,
                'summary' => 'Competing suppliers agree on prices or divide up customers and territories, so public buyers pay inflated prices.',
                'description' => 'Instead of competing, suppliers to government buyers (such as road materials, school food, generic drugs, or vehicle and equipment services) fix prices, agree not to bid in each other\'s territories, or allocate customers among themselves. Criminal price fixing, bid rigging, and market allocation are covered by the DOJ Antitrust Division\'s Whistleblower Rewards Program, which pays up to 30% of criminal fines and recoveries of at least $1 million; when the government was overcharged, a False Claims Act suit may also apply.',
                'indicators' => [
                    'Prices from competing suppliers rising together, or moving in lockstep, without a cost reason',
                    'Each supplier winning only in its own region, or never bidding against certain competitors',
                    'Identical bids or price lists from supposed competitors',
                    'Prices falling sharply when a new supplier enters the market',
                    'Competitors meeting through trade associations around bid or price changes',
                ],
                'data_sources' => [
                    'Bid tabulations and award notices over several years',
                    'Cooperative purchasing and statewide contract price lists',
                    'DOJ Antitrust Division criminal cases',
                    'State attorney general antitrust actions',
                ],
            ],
            [
                'title' => 'Bribes and kickbacks to officials for contracts and approvals',
                'category' => VectorCategory::ConflictOfInterest,
                'summary' => 'Companies pay officials cash, gifts, jobs, or hidden payments in return for contracts, permits, inspections, or favorable votes.',
                'description' => 'Unlike disclosed campaign contributions, bribes and kickbacks are hidden: payments are routed through consultants, sham subcontracts, relatives\' businesses, or inflated invoices that are partly returned to the official. Company misconduct involving domestic corruption is covered by the DOJ Corporate Whistleblower Awards Pilot Program, and federally funded contracts obtained through bribery can support a False Claims Act suit, which pays whistleblowers 15–30% of the recovery.',
                'indicators' => [
                    'Officials\' lifestyles, property purchases, or cash deposits out of line with their salaries',
                    'Consultants or lobbyists paid by a vendor with no clear deliverables who are close to the deciding official',
                    'Awards to one vendor despite worse scoring, higher prices, or poor past performance',
                    'Specifications or evaluation criteria written to fit one vendor',
                    'Officials taking jobs with, or relatives hired by, vendors they oversaw',
                ],
                'data_sources' => [
                    'Contract award records and evaluation scoring',
                    'Statements of economic interest, gift, and outside-income disclosures',
                    'County property records for officials and relatives',
                    'Lobbyist registrations and payment reports',
                    'Inspector general reports and public-corruption prosecutions',
                ],
            ],
            [
                'title' => 'Misrepresented costs and results on government grants',
                'category' => VectorCategory::GrantsAndNonprofits,
                'summary' => 'Grant recipients charge unallowable costs, bill several grants for the same work, or report results that never happened.',
                'description' => 'Universities, nonprofits, local governments, and companies receiving federal grants (research, education, housing, infrastructure, and more) overstate effort on time-and-effort reports, charge personal or unrelated expenses, fund the same work from overlapping awards, or fabricate progress and performance data to keep the money flowing. False statements in grant applications, reports, and drawdowns are false claims: whistleblowers can receive 15–30% of the recovery through a False Claims Act qui tam suit, and fraud against federal programs is covered by the DOJ Corporate Whistleblower Awards Pilot Program.',
                'indicators' => [
                    'The same researchers or staff charged at more than 100% of their time across awards',
                    'Overlapping awards from different agencies for the same project',
                    'Drawdowns bunched at year-end or ahead of reported activity',
                    'Reported outcomes (people served, units built, jobs created) that cannot be verified',
                    'Single Audit findings on allowable costs, cash management, or reporting',
                ],
                'data_sources' => [
                    'USAspending.gov and agency award databases (such as NIH RePORTER and NSF award search)',
                    'Single Audit reports (Federal Audit Clearinghouse)',
                    'Grant progress and performance reports',
                    'Agency inspector general reports',
                ],
            ],
            [
                'title' => 'Misleading disclosures to municipal bond investors',
                'category' => VectorCategory::FinancialReporting,
                'summary' => 'Governments and conduit issuers hide financial problems, such as pension shortfalls or failing projects, from investors buying their bonds.',
                'description' => 'Cities, counties, states, school districts, and conduit issuers must give investors accurate official statements and ongoing disclosures. Overstated revenues, understated pension or debt obligations, concealed defaults or failing bond-funded projects, and skipped continuing-disclosure filings mislead investors and can hide deeper mismanagement of public money. These are securities-fraud matters for the SEC, whose whistleblower program pays 10–30% of sanctions over $1 million.',
                'indicators' => [
                    'Official statements whose revenue projections or pension figures differ from audited financial statements',
                    'Late or missing annual financial filings and material-event notices on EMMA',
                    'Bond-funded projects behind schedule or over budget without disclosure',
                    'Repeated use of one-time revenues or interfund borrowing to balance the budget',
                    'Auditor going-concern or material-weakness findings',
                ],
                'data_sources' => [
                    'MSRB EMMA (official statements and continuing disclosures)',
                    'Annual comprehensive financial reports (ACFRs) and audits',
                    'Pension plan actuarial valuations',
                    'SEC municipal securities enforcement actions',
                    'Credit rating agency reports',
                ],
            ],
            [
                'title' => 'Pay-to-play and hidden fees in public pension investing',
                'category' => VectorCategory::ConflictOfInterest,
                'summary' => 'Investment managers win public pension and treasury mandates through political contributions or connected placement agents, or charge fees they never disclosed.',
                'description' => 'Advisers seeking business from public pension funds, state treasuries, or local investment pools donate to officials who influence manager selection, pay politically connected placement agents, or bury excess fees and expenses in fund documents. Swap and derivatives advisers can also mislead local governments about costs and risks. These violations fall under the SEC whistleblower program and, for swaps and commodity investments, the CFTC whistleblower program; both pay 10–30% of sanctions over $1 million.',
                'indicators' => [
                    'Contributions from investment firm executives to officials who sit on or appoint pension boards',
                    'Politically connected placement agents or consultants paid when a manager is hired',
                    'Fees and expenses above those reported in the fund\'s financial statements',
                    'Managers hired despite poor performance or without a competitive search',
                    'Interest-rate swaps or complex products sold to small governments, followed by large termination payments',
                ],
                'data_sources' => [
                    'Pension board meeting minutes and investment consultant reports',
                    'State and local campaign finance filings',
                    'SEC Form ADV filings and enforcement actions (including Rule 206(4)-5 pay-to-play cases)',
                    'Pension plan annual reports and fee disclosures',
                    'CFTC enforcement actions',
                ],
            ],
        ];
    }
}
