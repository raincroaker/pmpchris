<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PhaseNineUnitChatAnnouncementsSeeder extends Seeder
{
    public function run(): void
    {
        $organizationId = (int) (DB::table('organizations')->where('code', 'PMPC')->value('id') ?? 0);
        $kennethUserId = (int) (DB::table('users')->where('email', 'martinez.kenneth@hrnexus.com')->value('id') ?? 0);

        if ($organizationId <= 0 || $kennethUserId <= 0) {
            throw new RuntimeException(
                'PhaseNineUnitChatAnnouncementsSeeder prerequisites missing: ensure organization code PMPC and Kenneth user exist.'
            );
        }

        $unitIdByCode = DB::table('organizational_units')
            ->where('organization_id', $organizationId)
            ->whereIn('code', ['HR', 'MM', 'FM'])
            ->pluck('id', 'code')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        foreach (['HR', 'MM', 'FM'] as $code) {
            if (! isset($unitIdByCode[$code])) {
                throw new RuntimeException(
                    sprintf('PhaseNineUnitChatAnnouncementsSeeder missing organizational unit code: %s', $code)
                );
            }
        }

        foreach ($this->announcementRows() as $row) {
            $unitCode = $row['unit_code'];
            $unitId = (int) $unitIdByCode[$unitCode];
            $sentAt = $row['sent_at'];

            DB::table('unit_chat_rooms')->updateOrInsert(
                ['organizational_unit_id' => $unitId],
                [
                    'last_message_id' => null,
                    'last_message_at' => null,
                    'created_at' => $sentAt,
                    'updated_at' => $sentAt,
                ]
            );

            $roomId = (int) (DB::table('unit_chat_rooms')->where('organizational_unit_id', $unitId)->value('id') ?? 0);
            if ($roomId <= 0) {
                continue;
            }

            DB::table('unit_chat_messages')->updateOrInsert(
                [
                    'unit_chat_room_id' => $roomId,
                    'sender_user_id' => $kennethUserId,
                    'body' => $row['body'],
                    'created_at' => $sentAt,
                ],
                [
                    'updated_at' => $sentAt,
                ]
            );

            $messageId = (int) (
                DB::table('unit_chat_messages')
                    ->where('unit_chat_room_id', $roomId)
                    ->where('sender_user_id', $kennethUserId)
                    ->where('body', $row['body'])
                    ->where('created_at', $sentAt)
                    ->value('id')
                ?? 0
            );

            if ($messageId <= 0) {
                continue;
            }

            DB::table('unit_chat_rooms')
                ->where('id', $roomId)
                ->update([
                    'last_message_id' => $messageId,
                    'last_message_at' => $sentAt,
                    'updated_at' => $sentAt,
                ]);
        }
    }

    /**
     * @return list<array{
     *     unit_code: string,
     *     sent_at: CarbonImmutable,
     *     body: string
     * }>
     */
    private function announcementRows(): array
    {
        return [
            // Around 4:00 PM announcement wave for March 1 annual assembly
            [
                'unit_code' => 'HR',
                'sent_at' => CarbonImmutable::parse('2026-02-28 16:02:14'),
                'body' => 'Good afternoon HR team. Reminder that tomorrow, March 1, is our 58th Annual General Assembly at Family Country Hotel and Convention Centre in General Santos City. Please wrap pending tasks before end of day and coordinate final attendance details. - Kenneth Martinez',
            ],
            [
                'unit_code' => 'MM',
                'sent_at' => CarbonImmutable::parse('2026-02-28 16:02:46'),
                'body' => 'Good afternoon MM team. Heads-up for tomorrow\'s 58th Annual General Assembly (March 1) at Family Country Hotel and Convention Centre, General Santos City. Kindly complete today\'s deliverables and align with your unit lead. - Kenneth Martinez',
            ],
            [
                'unit_code' => 'FM',
                'sent_at' => CarbonImmutable::parse('2026-02-28 16:03:19'),
                'body' => 'Good afternoon FM team. Reminder for tomorrow\'s 58th Annual General Assembly on March 1 at Family Country Hotel and Convention Centre, General Santos City. Please finalize pending reports before close of business. - Kenneth Martinez',
            ],

            // Around 4:00 PM announcement wave for March 20 Eid\'l Fitr holiday
            [
                'unit_code' => 'HR',
                'sent_at' => CarbonImmutable::parse('2026-03-19 16:05:21'),
                'body' => 'Good afternoon HR team. Reminder that tomorrow, March 20, is Eid\'l Fitr holiday. Please settle urgent handoffs today and coordinate any critical concerns before end of shift. Thank you. - Kenneth Martinez',
            ],
            [
                'unit_code' => 'MM',
                'sent_at' => CarbonImmutable::parse('2026-03-19 16:05:58'),
                'body' => 'Good afternoon MM team. Friendly reminder that tomorrow, March 20, is Eid\'l Fitr holiday. Kindly close out urgent items today and update your lead on pending work. Thank you. - Kenneth Martinez',
            ],
            [
                'unit_code' => 'FM',
                'sent_at' => CarbonImmutable::parse('2026-03-19 16:06:29'),
                'body' => 'Good afternoon FM team. Please be reminded that tomorrow, March 20, is Eid\'l Fitr holiday. Ensure urgent tasks are endorsed today and confirm any remaining blockers with your unit head. Thank you. - Kenneth Martinez',
            ],
        ];
    }
}
