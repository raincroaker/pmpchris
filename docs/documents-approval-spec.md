# Documents — approval, roles, and library rules (product spec)

**Status:** Product specification for future implementation. The current UI under `resources/js/pages/Documents/*` and `DocumentsDriveBrowser.vue` remains a **session mock** until this spec is wired to persistence and policies.

**Audience:** Developers implementing backend + Inertia/Vue for document libraries.

---

## 1. Role codes (Laravel)

Use `App\Models\Role` constants (see [`app/Models/Role.php`](../app/Models/Role.php)):

| Constant | Code |
|----------|------|
| `Role::CODE_SUPER_ADMIN` | `super_admin` |
| `Role::CODE_HR_HEAD` | `hr_head` |
| `Role::CODE_HR_MANAGER` | `hr_manager` |
| `Role::CODE_EMPLOYEE` | `employee` |

**Branch-scoped HR manager:** A user with `hr_manager` who may act **for the active workspace branch** is determined the same way as elsewhere in the app (e.g. [`BranchContextService::managedBranchRootIdsFor()`](../app/Services/BranchContextService.php) vs current branch from session/request — see [`ScheduleAssignmentAccessService`](../app/Services/ScheduleAssignmentAccessService.php) for the “manages this branch root” pattern).

**Unit head (not a Role code):** A user is **unit head** for a given unit when they have an active **`employee_assignments`** row for that unit with **`is_head = true`** (see migration [`2026_04_08_011601_create_employee_assignments_table.php`](../database/migrations/2026_04_08_011601_create_employee_assignments_table.php)). Resolution must use the **correct unit** (team library’s unit / branch context).

---

## 2. Scopes overview

| Scope | Purpose (summary) |
|-------|-------------------|
| **My** | Read-only **catalog of files the current user uploaded** (any library). **No folders.** Not an upload target. |
| **Team** | Unit group library: uploads land in unit **home/root**; **folders + move** restricted; approvals per §4.1. |
| **Branch** | Branch library: **anyone can upload** (per membership rules you add); **move** restricted; approvals per §4.2. |
| **Company** | Org-wide library: visibility broad; **approvals** per §4.3; **moves** per §5. |

---

## 3. Document lifecycle (all scopes that use approval)

- **Single approval step** per submission (one approve or reject decision closes that cycle).
- **Audit:** Record **who** approved or rejected and **when** (and optional comment). No multi-step approval chain in v1.
- **States (conceptual):** `pending` → `approved` **or** `rejected` (plus `cancelled` / withdrawn if uploader cancels while pending).

### 3.1 Pending (uploader)

- Uploader may **cancel** and **delete** (withdraw before a decision).

### 3.2 Rejected

- **Cannot** “resubmit” or re-pass the **same** document record — uploader must **upload a new file** (new record).
- Rejected items: **file removed from the active library** per product rules; a **rejection record** remains in history.
- Uploader may **delete** the rejected record (retention/archival rules can be defined later).

### 3.3 Approver uploads (company only — auto-approved)

- On **Company** scope: if the uploader is **`super_admin`** or **`hr_head`**, the upload **does not require a second approver** — treat as **auto-approved** (still log an audit row for traceability, e.g. policy-based approval).

---

## 4. Who may approve or reject

### 4.1 Team documents

**Approvers** (any of these may approve or reject, for that team/unit context):

| Approver | Rule |
|----------|------|
| `super_admin` | Always when in scope. |
| `hr_head` | Org HR head role. |
| `hr_manager` | Same branch as workspace: user’s managed branch roots include this branch (see §1). |
| **Unit head** | `employee_assignments.is_head` for the **relevant unit** (team library’s unit). |

**Implementation note:** Decide and test whether a **unit head’s own** team uploads remain `pending` or are **auto-approved**; document the chosen behavior in this file once decided.

### 4.2 Branch documents

**Same approver set as Team, except unit head is *not* an approver:**

| Approver | Rule |
|----------|------|
| `super_admin` | Yes |
| `hr_head` | Yes |
| `hr_manager` (this branch) | Yes |
| **Unit head** | **No** — unit head’s uploads **must** be approved by one of the three roles above. |

### 4.3 Company documents

| Approver | Rule |
|----------|------|
| `super_admin` | Yes |
| `hr_head` | Yes |
| `hr_manager` | **No** |
| **Unit head** | **No** |

**Everyone else** who can upload to company: submission is **`pending`** until `super_admin` or `hr_head` acts (unless auto-approved under §3.3).

---

## 5. Folders and move permissions (structural)

These rules layer on top of upload/approval.

### 5.1 Team documents

- **Employees:** may **upload**; new files go to the unit’s **home/root** (default folder); they **do not** choose subfolders on upload.
- **May create folders + move documents between folders:** `super_admin`, `hr_head`, `hr_manager` (this branch), **unit head** (`is_head` for that unit).

### 5.2 Branch documents

- **Upload:** permitted for users you allow in branch context (product: “anyone” in branch — refine with membership).
- **Move between folders:** only `super_admin`, `hr_head`, `hr_manager` (this branch). **Not** unit head unless product changes.

### 5.3 Company documents

- **Move / structure:** only `super_admin` and `hr_head`.

---

## 6. UI expectations

- **Tabs or routes** for **Pending** and **Rejected** (and main **Library**) per scope where approval applies, so queues are obvious.
- **My documents:** list only **my uploads**; no folder tree; filters/sort as needed.

---

## 7. Implementation plan (phased)

Use this as a checklist; adjust order when migrations depend on each other.

### Phase A — Data model

1. **Tables** (conceptual names — finalize in migrations):
   - Scope / tenancy: tie rows to `organizational_unit_id` (team), branch root (branch), or organization (company) as appropriate.
   - `document_folders` (parent_id, scope, unit_id / branch root / company nullable).
   - `documents` (or `document_files`): storage path, original name, mime, size, `uploaded_by`, `folder_id`, `scope`, `status` (`pending` | `approved` | `rejected` | `cancelled`), `submitted_at`, `decided_at`, `decided_by`, `decision_note` nullable, soft deletes as needed.
   - `document_approval_audits`: `document_id`, `action` (`approved` | `rejected` | `cancelled` | `auto_approved`), `actor_user_id`, `created_at`, `metadata` JSON optional.
2. **Rejected handling:** align DB with “file removed from library but record retained” + uploader delete.
3. **Indexes** for list queries: `(scope, unit_id, status)`, `(uploaded_by, status)`, branch/company variants.

### Phase B — Authorization

1. **Policies** (or dedicated `DocumentPolicy` + scope services):
   - `approve`, `reject`, `upload`, `move`, `createFolder`, `cancel`, `deleteRejected`, `viewPendingQueue` (own vs all).
2. **Reuse** branch context + `managedBranchRootIdsFor` for `hr_manager` checks.
3. **Unit head** helper: given `User` + `OrganizationalUnit` (or assignment id), `exists` active assignment with `is_head`.

### Phase C — HTTP API

1. CRUD: upload (multipart), list by folder/status, move, create folder, cancel pending, delete rejected record, approve, reject.
2. **Form requests** + validation; **403** when policy fails.
3. **Idempotency** where useful (approve twice).

### Phase D — Inertia + Vue

1. Replace or augment mock `DocumentsDriveBrowser` with API-backed lists; keep Trash behavior if still desired for soft-delete flows.
2. Add **Pending / Rejected / Library** tabs or child routes per scope.
3. **My:** dedicated query `uploaded_by = auth`; no folder UI.

### Phase E — Testing (Pest)

1. Matrix tests: each role × approve/reject/upload/move/folder create for team/branch/company.
2. **Unit head** on team: can approve; on branch: cannot act as approver.
3. **Company:** `hr_manager` cannot approve; `super_admin` / `hr_head` uploads auto-approved with audit row.

### Phase F — Documentation and changelog

1. Keep this file updated when behavior changes.
2. Short entry in `PROJECT_CHANGELOG.md` when the feature ships beyond mock.

---

## 8. Open implementation details (resolve in code review)

1. **Team “unit” key:** Ensure every team document row is tied to **`organizational_unit_id`** (or equivalent) so approvers and `is_head` checks are deterministic.
2. **Who may upload** to branch/company: exact employee/user predicates (all branch employees vs HR-only).
3. **Notifications:** email/in-app when pending — optional later phase.
4. **Storage:** S3/local disk, virus scan, max size — infrastructure phase.

---

## 9. Related code (current mock)

- Pages: `resources/js/pages/Documents/{My,Team,Branch,Company,Trash}.vue`
- Browser: `resources/js/components/hris/documents/DocumentsDriveBrowser.vue`
- Session mock trash: `resources/js/components/hris/documents/documentsDriveSession.ts`

These are **not** the source of truth for the rules above until replaced by persisted models and policies.
