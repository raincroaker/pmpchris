<?php

use App\Models\CompanyDocument;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\BranchContextService;
use Database\Seeders\DemoCooperativeSeeder;
use Database\Seeders\OrganizationalStructureSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new RoleSeeder)->run();
    (new OrganizationalStructureSeeder)->run();
    (new DemoCooperativeSeeder)->run();
    config(['hris.default_organization_code' => 'PMPC']);
});

function workspaceBranchRootId(): int
{
    return (int) OrganizationalUnit::query()
        ->where('code', 'PAN')
        ->whereNull('parent_id')
        ->value('id');
}

function workspaceOrganizationId(): int
{
    return (int) DB::table('organizations')
        ->where('code', 'PMPC')
        ->value('id');
}

test('hr head can create company document folders', function (): void {
    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => workspaceBranchRootId()])
        ->postJson(route('documents.company.folders.store'), [
            'name' => 'Policies',
            'parent_id' => null,
        ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'folder')
        ->assertJsonPath('data.name', 'Policies');

    expect(DB::table('company_document_folders')->where('name', 'Policies')->exists())
        ->toBeTrue();
});

test('hr head can upload company documents and record auto approval audit', function (): void {
    Storage::fake('local');

    $user = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $response = $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => workspaceBranchRootId()])
        ->post(route('documents.company.files.store'), [
            'file' => UploadedFile::fake()->create('handbook.pdf', 250, 'application/pdf'),
            'tags' => ['Handbook', 'HR'],
            'notes' => 'Official version',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.type', 'file')
        ->assertJsonPath('data.accessMode', 'private')
        ->assertJsonPath('data.approvalStatusLabel', 'Approved');

    /** @var CompanyDocument $document */
    $document = CompanyDocument::query()->firstOrFail();

    expect($document->access_mode)->toBe('private')
        ->and($document->status)->toBe('approved')
        ->and($document->uploaded_by_user_id)->toBe($user->id)
        ->and($document->decided_by_user_id)->toBe($user->id);

    Storage::disk('local')->assertExists($document->path);

    expect(DB::table('company_document_approval_audits')
        ->where('company_document_id', $document->id)
        ->where('action', 'auto_approved')
        ->exists())->toBeTrue();
});

test('employee cannot mutate company documents backend', function (): void {
    $user = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($user)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => workspaceBranchRootId()])
        ->postJson(route('documents.company.folders.store'), [
            'name' => 'Forbidden',
        ])
        ->assertForbidden();
});

test('employee cannot preview or download company documents', function (): void {
    $admin = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();

    $document = CompanyDocument::factory()->create([
        'uploaded_by_user_id' => $admin->id,
        'decided_by_user_id' => $admin->id,
    ]);

    $employee = User::factory()->withRoles(Role::CODE_EMPLOYEE)->create();

    $this->actingAs($employee)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => workspaceBranchRootId()])
        ->get(route('documents.company.files.preview', $document))
        ->assertForbidden();

    $this->actingAs($employee)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => workspaceBranchRootId()])
        ->get(route('documents.company.files.download', $document))
        ->assertForbidden();
});

test('hr head can download company document file', function (): void {
    Storage::fake('local');

    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();
    $document = CompanyDocument::factory()->create([
        'organization_id' => workspaceOrganizationId(),
        'disk' => 'local',
        'path' => 'company-documents/'.workspaceOrganizationId().'/employee-handbook.pdf',
        'original_name' => 'employee-handbook.pdf',
    ]);

    Storage::disk('local')->put((string) $document->path, 'pdf-bytes');

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => workspaceBranchRootId()])
        ->get(route('documents.company.files.download', $document))
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=employee-handbook.pdf');
});

test('company document download returns not found when file is missing', function (): void {
    Storage::fake('local');

    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();
    $document = CompanyDocument::factory()->create([
        'organization_id' => workspaceOrganizationId(),
        'disk' => 'local',
        'path' => 'company-documents/'.workspaceOrganizationId().'/missing-file.pdf',
        'original_name' => 'missing-file.pdf',
    ]);

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => workspaceBranchRootId()])
        ->get(route('documents.company.files.download', $document))
        ->assertNotFound();
});

test('super admin can update company document internal metadata', function (): void {
    $superAdmin = User::factory()->withRoles(Role::CODE_SUPER_ADMIN)->create();

    $document = CompanyDocument::factory()->create([
        'organization_id' => workspaceOrganizationId(),
        'status' => 'approved',
        'access_mode' => 'private',
        'notes' => 'Before',
    ]);

    $this->actingAs($superAdmin)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => workspaceBranchRootId()])
        ->patchJson(route('documents.company.files.internal-metadata.update', $document), [
            'created_at' => '2025-01-02 03:04:00',
            'updated_at' => '2025-01-03 04:05:00',
            'submitted_at' => '2025-01-01 01:02:00',
            'decided_at' => '2025-01-04 05:06:00',
            'status' => 'rejected',
            'access_mode' => 'public',
            'decision_note' => 'Updated for correction',
            'notes' => 'After',
            'tags' => ['Compliance', 'Correction'],
        ])
        ->assertOk()
        ->assertJsonPath('data.approvalStatusLabel', 'Rejected')
        ->assertJsonPath('data.accessMode', 'public')
        ->assertJsonPath('data.notesLabel', 'After');

    $document->refresh();

    expect($document->status)->toBe('rejected')
        ->and($document->access_mode)->toBe('public')
        ->and($document->notes)->toBe('After')
        ->and($document->decision_note)->toBe('Updated for correction')
        ->and($document->tags)->toBe(['Compliance', 'Correction']);
});

test('hr head cannot update company document internal metadata', function (): void {
    $hrHead = User::factory()->withRoles(Role::CODE_HR_HEAD)->create();
    $document = CompanyDocument::factory()->create([
        'organization_id' => workspaceOrganizationId(),
    ]);

    $this->actingAs($hrHead)
        ->withSession([BranchContextService::SESSION_BRANCH_ID => workspaceBranchRootId()])
        ->patchJson(route('documents.company.files.internal-metadata.update', $document), [
            'notes' => 'Not allowed',
        ])
        ->assertForbidden();
});
