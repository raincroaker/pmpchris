<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PhaseEightCompanyCalendarSeeder extends Seeder
{
    public function run(): void
    {
        $organizationId = (int) (DB::table('organizations')->where('code', 'PMPC')->value('id') ?? 0);
        $hrHeadUserId = (int) (DB::table('users')->where('email', 'hrhead@hrnexus.com')->value('id') ?? 0);

        if ($organizationId <= 0 || $hrHeadUserId <= 0) {
            throw new RuntimeException(
                'PhaseEightCompanyCalendarSeeder prerequisites missing: ensure organization code PMPC and HR head user exist.'
            );
        }

        $categoryIdBySlug = DB::table('calendar_event_categories')
            ->where('organization_id', $organizationId)
            ->whereIn('slug', ['meeting', 'team-building', 'operations', 'training'])
            ->pluck('id', 'slug')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $requiredCategorySlugs = ['meeting', 'team-building', 'operations', 'training'];
        foreach ($requiredCategorySlugs as $slug) {
            if (! isset($categoryIdBySlug[$slug])) {
                throw new RuntimeException(
                    sprintf('PhaseEightCompanyCalendarSeeder missing category slug: %s', $slug)
                );
            }
        }

        foreach ($this->eventRows($categoryIdBySlug) as $row) {
            DB::table('company_calendar_events')->updateOrInsert(
                [
                    'organization_id' => $organizationId,
                    'title' => $row['title'],
                    'starts_at' => $row['starts_at'],
                ],
                [
                    'category_id' => $row['category_id'],
                    'ends_at' => $row['ends_at'],
                    'is_all_day' => true,
                    'location' => $row['location'],
                    'details' => $row['details'],
                    'recurrence' => null,
                    'recurrence_exceptions' => null,
                    'set_by_user_id' => $hrHeadUserId,
                    'last_edited_by_user_id' => $hrHeadUserId,
                    'created_at' => $row['set_up_at'],
                    'updated_at' => $row['set_up_at'],
                    'deleted_at' => null,
                ]
            );
        }
    }

    /**
     * @param  array<string, int>  $categoryIdBySlug
     * @return list<array{
     *     category_id: int,
     *     title: string,
     *     starts_at: CarbonImmutable,
     *     ends_at: CarbonImmutable,
     *     set_up_at: CarbonImmutable,
     *     location: string,
     *     details: string
     * }>
     */
    private function eventRows(array $categoryIdBySlug): array
    {
        return [
            [
                'category_id' => (int) $categoryIdBySlug['meeting'],
                'title' => '58th Annual General Assembly',
                'starts_at' => CarbonImmutable::parse('2026-03-01 00:00:00'),
                'ends_at' => CarbonImmutable::parse('2026-03-01 23:59:00'),
                'set_up_at' => CarbonImmutable::parse('2026-02-27 08:42:18'), // Friday morning
                'location' => 'Family Country Hotel and Convention Centre, General Santos City',
                'details' => 'Panabo Co-op Annual General Assembly focused on growth, cooperation, and community sustainability.',
            ],
            [
                'category_id' => (int) $categoryIdBySlug['team-building'],
                'title' => '63rd Founding Anniversary Celebration',
                'starts_at' => CarbonImmutable::parse('2026-03-03 00:00:00'),
                'ends_at' => CarbonImmutable::parse('2026-03-03 23:59:00'),
                'set_up_at' => CarbonImmutable::parse('2026-03-02 09:16:41'), // Monday morning
                'location' => 'Panabo City',
                'details' => 'Celebration of Panabo Co-op\'s 63 years of service, highlighting milestones, unity, and cooperative success.',
            ],
            [
                'category_id' => (int) $categoryIdBySlug['operations'],
                'title' => 'Social and Financial Services Caravan',
                'starts_at' => CarbonImmutable::parse('2026-03-04 00:00:00'),
                'ends_at' => CarbonImmutable::parse('2026-03-04 23:59:00'),
                'set_up_at' => CarbonImmutable::parse('2026-03-03 10:08:24'), // Tuesday morning
                'location' => 'Brgy. Paradise Embac, Paquibato District, Davao City',
                'details' => 'Community outreach with financial literacy seminar, medical services, and free services for residents.',
            ],
            [
                'category_id' => (int) $categoryIdBySlug['training'],
                'title' => 'Financial Literacy Seminar (GMW 2026)',
                'starts_at' => CarbonImmutable::parse('2026-03-16 00:00:00'),
                'ends_at' => CarbonImmutable::parse('2026-03-16 23:59:00'),
                'set_up_at' => CarbonImmutable::parse('2026-03-12 08:27:53'), // Thursday morning
                'location' => 'Paradise Embac National High School, Davao City',
                'details' => 'Seminar for students promoting saving, responsible spending, and financial awareness.',
            ],
            [
                'category_id' => (int) $categoryIdBySlug['meeting'],
                'title' => '58th Annual General Assembly (Butuan Branch)',
                'starts_at' => CarbonImmutable::parse('2026-03-26 00:00:00'),
                'ends_at' => CarbonImmutable::parse('2026-03-26 23:59:00'),
                'set_up_at' => CarbonImmutable::parse('2026-03-23 10:21:35'), // Monday morning
                'location' => 'LMX Convention Center, Butuan City',
                'details' => 'Branch-level General Assembly promoting unity and cooperative development.',
            ],
            [
                'category_id' => (int) $categoryIdBySlug['operations'],
                'title' => 'Mobile Laboratory Caravan (HEAL Program)',
                'starts_at' => CarbonImmutable::parse('2026-03-27 00:00:00'),
                'ends_at' => CarbonImmutable::parse('2026-03-27 23:59:00'),
                'set_up_at' => CarbonImmutable::parse('2026-03-24 08:58:27'), // Tuesday morning
                'location' => 'Monkayo Central Elementary School',
                'details' => 'Mobile laboratory services for educators in partnership with health organizations.',
            ],
            [
                'category_id' => (int) $categoryIdBySlug['operations'],
                'title' => 'Mangrove Tree Planting & Coastal Clean-Up',
                'starts_at' => CarbonImmutable::parse('2026-03-28 00:00:00'),
                'ends_at' => CarbonImmutable::parse('2026-03-28 23:59:00'),
                'set_up_at' => CarbonImmutable::parse('2026-03-25 10:33:49'), // Wednesday morning
                'location' => 'Brgy. JP Laurel, Panabo City',
                'details' => 'Environmental initiative involving tree planting and coastal cleanup with over 200 participants.',
            ],
        ];
    }
}
