<?php

namespace Database\Seeders;

use App\Enums\ReferenceOrigin;
use App\Models\ReferenceSource;
use App\Services\Analysis\ReferenceLibrary;
use App\Services\Analysis\ReferenceLink;
use Illuminate\Database\Seeder;

/**
 * National public datasets that are useful for almost any target. They go into
 * every analysis prompt, so the model uses them directly instead of searching.
 * Safe to re-run: entries are matched by URL and only created when missing.
 *
 * php artisan db:seed --class=ReferenceCatalogSeeder
 */
class ReferenceCatalogSeeder extends Seeder
{
    /**
     * Seed the catalog.
     */
    public function run(): void
    {
        foreach (self::sources() as $source) {
            $exists = ReferenceSource::query()
                ->whereNull('target_id')
                ->where('url_key', ReferenceLink::key($source['url']))
                ->exists();

            if (! $exists) {
                ReferenceSource::create([
                    ...$source,
                    'origin' => ReferenceOrigin::Catalog,
                    'verified_at' => now(),
                    'last_seen_at' => now(),
                ]);
            }
        }

        // Seeding may run with model events disabled, which skips the usual cache busting.
        app(ReferenceLibrary::class)->forget(null);
    }

    /**
     * @return list<array{title: string, url: string, description: string}>
     */
    public static function sources(): array
    {
        return [
            [
                'title' => 'USAspending.gov',
                'url' => 'https://www.usaspending.gov/search',
                'description' => 'Federal contracts, grants, and loans by recipient, agency, and place of performance.',
            ],
            [
                'title' => 'SAM.gov',
                'url' => 'https://sam.gov/',
                'description' => 'Federal entity registrations (UEI, owners, addresses) and the exclusions list of debarred parties.',
            ],
            [
                'title' => 'Federal Audit Clearinghouse',
                'url' => 'https://www.fac.gov/',
                'description' => 'Single Audit reports and findings for organizations spending federal awards.',
            ],
            [
                'title' => 'ProPublica Nonprofit Explorer',
                'url' => 'https://projects.propublica.org/nonprofits/',
                'description' => 'Nonprofit Form 990 filings: revenue, executive pay, and related-party transactions.',
            ],
            [
                'title' => 'IRS Tax Exempt Organization Search',
                'url' => 'https://apps.irs.gov/app/eos/',
                'description' => 'Tax-exempt status, revocations, and copies of nonprofit returns.',
            ],
            [
                'title' => 'HHS-OIG List of Excluded Individuals/Entities',
                'url' => 'https://exclusions.oig.hhs.gov/',
                'description' => 'Providers barred from Medicare, Medicaid, and other federal health programs.',
            ],
            [
                'title' => 'CMS data catalog',
                'url' => 'https://data.cms.gov/',
                'description' => 'Medicare and Medicaid provider enrollment, utilization, and payment data.',
            ],
            [
                'title' => 'SBA Paycheck Protection Program loan data',
                'url' => 'https://data.sba.gov/dataset/ppp-foia',
                'description' => 'Loan-level PPP data with borrower names, addresses, amounts, and lenders.',
            ],
            [
                'title' => 'FEC campaign finance data',
                'url' => 'https://www.fec.gov/data/',
                'description' => 'Federal campaign contributions and expenditures by donor and employer.',
            ],
            [
                'title' => 'FollowTheMoney.org',
                'url' => 'https://www.followthemoney.org/',
                'description' => 'State-level campaign contributions to candidates and officials.',
            ],
            [
                'title' => 'Oversight.gov',
                'url' => 'https://www.oversight.gov/reports',
                'description' => 'Federal inspector general audit and investigation reports.',
            ],
            [
                'title' => 'U.S. Department of Justice news',
                'url' => 'https://www.justice.gov/news',
                'description' => 'Press releases on fraud charges, pleas, and settlements, searchable by district.',
            ],
        ];
    }
}
