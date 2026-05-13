<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class PhaseSevenCompanyDocumentsSeeder extends Seeder
{
    public function run(): void
    {
        $organizationId = (int) (DB::table('organizations')->where('code', 'PMPC')->value('id') ?? 0);
        $kennethUserId = (int) (DB::table('users')->where('email', 'martinez.kenneth@hrnexus.com')->value('id') ?? 0);

        if ($organizationId <= 0 || $kennethUserId <= 0) {
            throw new RuntimeException(
                'PhaseSevenCompanyDocumentsSeeder prerequisites missing: ensure organization code PMPC and user martinez.kenneth@hrnexus.com exist before seeding.'
            );
        }

        $userIdByEmail = DB::table('users')
            ->whereIn('email', [
                'jannah.cartagena@gmail.com',
                'ivy.moya@gmail.com',
                'rubyrose.arellano@gmail.com',
                'archie.josol@gmail.com',
                'lealyn.gentica@gmail.com',
            ])
            ->pluck('id', 'email')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $extractedTextByKey = $this->loadExtractedTextByKey();
        $workflowOverrides = $this->workflowOverrides($kennethUserId, $userIdByEmail);
        $publicDocKeys = [
            'pdf-policy-001',
            'pdf-policy-002',
            'pdf-policy-003',
            'pptx-001',
            'pptx-002',
        ];

        foreach ($this->documentRows() as $row) {
            $storedName = $this->buildStoredName($row['doc_key'], $row['extension']);
            $path = 'company-documents/hr/2026/03/'.$storedName;

            $uploadedAt = $row['uploaded_at'];
            $tags = $row['tags'];
            $defaultAccessMode = in_array($row['doc_key'], $publicDocKeys, true) ? 'public' : 'private';
            $override = $workflowOverrides[$row['doc_key']] ?? null;

            $uploadedByUserId = is_array($override)
                ? (int) $override['uploaded_by_user_id']
                : $kennethUserId;
            $status = is_array($override)
                ? (string) $override['status']
                : 'approved';
            $accessMode = is_array($override)
                ? 'private'
                : $defaultAccessMode;
            $submittedAt = is_array($override)
                ? $uploadedAt->addMinutes((int) $override['submitted_after_minutes'])
                : $uploadedAt->subMinutes(2);
            $decidedAt = is_array($override) && ($override['decided_after_minutes'] !== null)
                ? $uploadedAt->addMinutes((int) $override['decided_after_minutes'])
                : null;
            $decidedByUserId = is_array($override) && $status !== 'pending'
                ? $kennethUserId
                : null;
            $decisionNote = is_array($override)
                ? (string) $override['decision_note']
                : 'Uploaded by HR Head for internal HR repository seeding.';
            $updatedAt = is_array($override)
                ? $uploadedAt->addMinutes((int) $override['updated_after_minutes'])
                : $uploadedAt;

            DB::table('company_documents')->updateOrInsert(
                [
                    'organization_id' => $organizationId,
                    'path' => $path,
                ],
                [
                    'folder_id' => null,
                    'original_name' => $row['original_name'],
                    'stored_name' => $storedName,
                    'disk' => 'local',
                    'mime_type' => $row['mime_type'],
                    'extension' => $row['extension'],
                    'size_bytes' => $row['size_bytes'],
                    'checksum_sha256' => hash('sha256', $row['doc_key'].'|'.$row['original_name'].'|'.$row['size_bytes']),
                    'uploaded_by_user_id' => $uploadedByUserId,
                    'access_mode' => $accessMode,
                    'status' => $status,
                    'submitted_at' => $submittedAt,
                    'decided_at' => $decidedAt,
                    'decided_by_user_id' => $decidedByUserId,
                    'decision_note' => $decisionNote,
                    'tags' => $tags !== [] ? json_encode($tags, JSON_THROW_ON_ERROR) : null,
                    'notes' => $row['notes'],
                    'extracted_text' => $extractedTextByKey[$row['doc_key']] ?? null,
                    'created_at' => $uploadedAt,
                    'updated_at' => $updatedAt,
                    'deleted_at' => null,
                ]
            );
        }
    }

    /**
     * @param  array<string, int>  $userIdByEmail
     * @return array<string, array{
     *     uploaded_by_user_id: int,
     *     status: string,
     *     submitted_after_minutes: int,
     *     decided_after_minutes: int|null,
     *     updated_after_minutes: int,
     *     decision_note: string
     * }>
     */
    private function workflowOverrides(int $kennethUserId, array $userIdByEmail): array
    {
        $jannahId = (int) ($userIdByEmail['jannah.cartagena@gmail.com'] ?? 0);
        $ivyId = (int) ($userIdByEmail['ivy.moya@gmail.com'] ?? 0);
        $rubyId = (int) ($userIdByEmail['rubyrose.arellano@gmail.com'] ?? 0);
        $archieId = (int) ($userIdByEmail['archie.josol@gmail.com'] ?? 0);
        $lealynId = (int) ($userIdByEmail['lealyn.gentica@gmail.com'] ?? 0);

        if ($jannahId <= 0 || $ivyId <= 0 || $rubyId <= 0 || $archieId <= 0 || $lealynId <= 0 || $kennethUserId <= 0) {
            return [];
        }

        return [
            // 3 private approved requests by interns (same request-type flow)
            'memo-006' => [
                'uploaded_by_user_id' => $jannahId,
                'status' => 'approved',
                'submitted_after_minutes' => 6,
                'decided_after_minutes' => 61,
                'updated_after_minutes' => 63,
                'decision_note' => 'Approved by Kenneth Martinez after HR content review.',
            ],
            'memo-011' => [
                'uploaded_by_user_id' => $ivyId,
                'status' => 'approved',
                'submitted_after_minutes' => 5,
                'decided_after_minutes' => 49,
                'updated_after_minutes' => 52,
                'decision_note' => 'Approved by Kenneth Martinez for restricted HR circulation.',
            ],
            'xlsx-002' => [
                'uploaded_by_user_id' => $rubyId,
                'status' => 'approved',
                'submitted_after_minutes' => 4,
                'decided_after_minutes' => 44,
                'updated_after_minutes' => 46,
                'decision_note' => 'Approved by Kenneth Martinez as private DTR template.',
            ],
            // 1 private rejected request
            'memo-015' => [
                'uploaded_by_user_id' => $archieId,
                'status' => 'rejected',
                'submitted_after_minutes' => 7,
                'decided_after_minutes' => 55,
                'updated_after_minutes' => 58,
                'decision_note' => 'Rejected by Kenneth Martinez: duplicate memo with incorrect references.',
            ],
            // 1 private pending request
            'docx-002' => [
                'uploaded_by_user_id' => $lealynId,
                'status' => 'pending',
                'submitted_after_minutes' => 3,
                'decided_after_minutes' => null,
                'updated_after_minutes' => 18,
                'decision_note' => 'Pending review by HR Head.',
            ],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function loadExtractedTextByKey(): array
    {
        $path = database_path('seed-data/company-documents-extracted-texts.json');
        if (! File::exists($path)) {
            return [];
        }

        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded)) {
            return [];
        }

        $map = [];
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = $row['doc_key'] ?? null;
            if (! is_string($key) || $key === '') {
                continue;
            }

            $text = $row['extracted_text'] ?? null;
            $map[$key] = is_string($text) && trim($text) !== '' ? $text : null;
        }

        return $map;
    }

    private function buildStoredName(string $docKey, string $extension): string
    {
        $base = substr(hash('sha256', $docKey), 0, 24);

        return sprintf('%s.%s', $base, strtolower($extension));
    }

    /**
     * @return list<array{
     *     doc_key: string,
     *     original_name: string,
     *     mime_type: string,
     *     extension: string,
     *     size_bytes: int,
     *     uploaded_at: CarbonImmutable,
     *     tags: list<string>,
     *     notes: string|null
     * }>
     */
    private function documentRows(): array
    {
        return [
            ['doc_key' => 'memo-001', 'original_name' => 'MEMORANDUM 47 - attendance compliance.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 214355, 'uploaded_at' => CarbonImmutable::parse('2026-03-04 09:12:21'), 'tags' => ['memo', 'attendnce'], 'notes' => 'old copy lang ni'],
            ['doc_key' => 'memo-002', 'original_name' => 'leave_filing_update_2022_FINAL (1).pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 189322, 'uploaded_at' => CarbonImmutable::parse('2026-03-04 09:20:18'), 'tags' => ['leave', 'forms'], 'notes' => null],
            ['doc_key' => 'memo-003', 'original_name' => 'Guidlines for intern records - feb2021.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 176901, 'uploaded_at' => CarbonImmutable::parse('2026-03-04 09:28:07'), 'tags' => ['intenr', 'records'], 'notes' => 'spelling off pero oks'],

            ['doc_key' => 'memo-004', 'original_name' => 'SCAN_2021-02-14_10-14.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 233870, 'uploaded_at' => CarbonImmutable::parse('2026-03-04 10:14:22'), 'tags' => [], 'notes' => null],
            ['doc_key' => 'memo-005', 'original_name' => 'MEMORANDUM21-privacy_update.PDF', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 205778, 'uploaded_at' => CarbonImmutable::parse('2026-03-04 10:23:51'), 'tags' => ['privacy', 'polcy', 'MEMO'], 'notes' => 'check if latest pa ni'],

            ['doc_key' => 'memo-006', 'original_name' => 'OJT daily reports routing slip.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 194441, 'uploaded_at' => CarbonImmutable::parse('2026-03-04 10:31:19'), 'tags' => ['ojt', 'routing-slip'], 'notes' => null],
            ['doc_key' => 'memo-007', 'original_name' => 'Memo #203 Incident report protocol.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 201609, 'uploaded_at' => CarbonImmutable::parse('2026-03-05 08:55:44'), 'tags' => ['incident', 'ops'], 'notes' => 'galing sa ops gc to'],
            ['doc_key' => 'memo-008', 'original_name' => 'hr_desk_policy_2023(1).pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 171900, 'uploaded_at' => CarbonImmutable::parse('2026-03-05 09:03:27'), 'tags' => [], 'notes' => null],
            ['doc_key' => 'memo-009', 'original_name' => 'TRAINING attendance reminder - MEMO 88.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 216004, 'uploaded_at' => CarbonImmutable::parse('2026-03-05 09:12:12'), 'tags' => ['trainng', 'attendance', 'reminder'], 'notes' => 'for monday huddle'],

            ['doc_key' => 'pdf-policy-001', 'original_name' => 'HR-Policy-Manual-2026.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 1045240, 'uploaded_at' => CarbonImmutable::parse('2026-03-05 09:18:08'), 'tags' => ['HR', 'manual'], 'notes' => null],
            ['doc_key' => 'pdf-policy-002', 'original_name' => 'Code-of-conduct employees and interns.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 652884, 'uploaded_at' => CarbonImmutable::parse('2026-03-05 09:25:54'), 'tags' => ['conduct', 'interns'], 'notes' => 'v2 yata to'],
            ['doc_key' => 'pdf-policy-003', 'original_name' => 'Workplace safety handbook (rev2).pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 744590, 'uploaded_at' => CarbonImmutable::parse('2026-03-05 09:34:39'), 'tags' => ['saftey', 'ops'], 'notes' => null],
            ['doc_key' => 'pdf-policy-004', 'original_name' => 'data privacy primer for interns.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 513442, 'uploaded_at' => CarbonImmutable::parse('2026-03-05 09:43:25'), 'tags' => ['privcy', 'intern'], 'notes' => null],

            ['doc_key' => 'docx-001', 'original_name' => 'HR-Intern-Onboarding-Checklist.docx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'extension' => 'docx', 'size_bytes' => 98443, 'uploaded_at' => CarbonImmutable::parse('2026-03-05 10:11:04'), 'tags' => ['onboarding', 'checklist'], 'notes' => 'print lang kung needed'],
            ['doc_key' => 'docx-002', 'original_name' => 'panabo coop internship guidline draft_2021.docx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'extension' => 'docx', 'size_bytes' => 121355, 'uploaded_at' => CarbonImmutable::parse('2026-03-05 10:19:52'), 'tags' => ['draft', 'guidline'], 'notes' => null],
            ['doc_key' => 'memo-010', 'original_name' => 'Memorandum No.114 - internet acceptable use.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 209188, 'uploaded_at' => CarbonImmutable::parse('2026-03-05 10:27:18'), 'tags' => ['internet', 'comply'], 'notes' => null],

            ['doc_key' => 'xlsx-001', 'original_name' => 'Intern Masterlist - Batch Feb2026.xlsx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'extension' => 'xlsx', 'size_bytes' => 142228, 'uploaded_at' => CarbonImmutable::parse('2026-03-06 11:07:35'), 'tags' => ['masterlist', 'intern'], 'notes' => 'numbers mostly ok'],
            ['doc_key' => 'xlsx-002', 'original_name' => 'intern dtr template march 2026.xlsx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'extension' => 'xlsx', 'size_bytes' => 118772, 'uploaded_at' => CarbonImmutable::parse('2026-03-06 11:16:49'), 'tags' => [], 'notes' => null],
            ['doc_key' => 'memo-011', 'original_name' => 'Evaluation timeline for interns (rev 2022).pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 182340, 'uploaded_at' => CarbonImmutable::parse('2026-03-06 11:24:03'), 'tags' => ['eval', 'intern', 'sched'], 'notes' => 'keep muna'],

            ['doc_key' => 'pptx-001', 'original_name' => 'Intern-Orientation-Panabo-Coop.pptx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'extension' => 'pptx', 'size_bytes' => 2878450, 'uploaded_at' => CarbonImmutable::parse('2026-03-06 13:08:14'), 'tags' => ['orientation', 'slides'], 'notes' => null],
            ['doc_key' => 'pptx-002', 'original_name' => 'HR training - data privacy basics FINAL FINAL.pptx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'extension' => 'pptx', 'size_bytes' => 2654102, 'uploaded_at' => CarbonImmutable::parse('2026-03-06 13:17:30'), 'tags' => ['training', 'privcy'], 'notes' => 'final final daw haha'],
            ['doc_key' => 'memo-012', 'original_name' => 'visitor_handling_protocol_v2.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 190112, 'uploaded_at' => CarbonImmutable::parse('2026-03-07 13:24:56'), 'tags' => ['visitor', 'frontdesk'], 'notes' => null],

            ['doc_key' => 'memo-013', 'original_name' => 'month end reports advisory.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 199775, 'uploaded_at' => CarbonImmutable::parse('2026-03-07 09:09:22'), 'tags' => ['month-end', 'reports'], 'notes' => 'remind fm/mm/hr'],
            ['doc_key' => 'memo-014', 'original_name' => 'MEMO 302 leave form cut-off.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 183956, 'uploaded_at' => CarbonImmutable::parse('2026-03-07 09:18:11'), 'tags' => ['leave', 'forms', 'cutoff'], 'notes' => null],
            ['doc_key' => 'memo-015', 'original_name' => 'unauthorized_file_sharing_notice_2021.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 186420, 'uploaded_at' => CarbonImmutable::parse('2026-03-07 09:26:58'), 'tags' => ['file-share', 'notice'], 'notes' => 'old memo pero gamit pa'],
            ['doc_key' => 'pdf-policy-005', 'original_name' => 'employee_contract_scanned.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 492311, 'uploaded_at' => CarbonImmutable::parse('2026-03-07 09:34:42'), 'tags' => ['contract', 'scan'], 'notes' => 'scanned copy for reference'],
            ['doc_key' => 'pdf-policy-006', 'original_name' => 'Remote Work and Telecommuting Agreement.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 538904, 'uploaded_at' => CarbonImmutable::parse('2026-03-07 09:43:09'), 'tags' => ['remote-work', 'agreement'], 'notes' => null],
        ];
    }
}
