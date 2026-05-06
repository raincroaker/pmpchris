<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUnitChatMessageRequest;
use App\Models\EmployeeAssignment;
use App\Models\Role;
use App\Models\UnitChatRead;
use App\Models\UnitChatRoom;
use App\Services\BranchContextService;
use App\Services\HrIndexUnitFilterCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class StoreUnitChatMessageController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
        private HrIndexUnitFilterCatalog $hrIndexUnitFilterCatalog,
    ) {}

    public function __invoke(StoreUnitChatMessageRequest $request, UnitChatRoom $room): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $hasGlobalChatAccess = $user->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
        ]);
        if (! $hasGlobalChatAccess && $user->employee_id === null) {
            abort(403);
        }

        $today = now()->toDateString();
        $isMember = EmployeeAssignment::query()
            ->where('employee_id', (int) $user->employee_id)
            ->where('organizational_unit_id', (int) $room->organizational_unit_id)
            ->whereNull('deleted_at', 'and', false)
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('start_date', 'and', false)
                    ->orWhereDate('start_date', '<=', $today);
            })
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('end_date', 'and', false)
                    ->orWhereDate('end_date', '>=', $today);
            })
            ->exists();

        if (! $hasGlobalChatAccess && ! $isMember) {
            abort(403);
        }

        $workspace = $this->branchContextService->workspaceBranchContext($request);
        $organization = $this->branchContextService->defaultOrganization();
        if ($workspace !== null && $organization !== null) {
            $allowedBranchUnitIds = $this->hrIndexUnitFilterCatalog->collectBranchUnitSubtreeIds(
                (int) $organization->id,
                (int) $workspace['id'],
            );
            if (! in_array((int) $room->organizational_unit_id, $allowedBranchUnitIds, true)) {
                abort(403);
            }
        }

        DB::transaction(function () use ($room, $request, $user): void {
            $message = $room->messages()->create([
                'sender_user_id' => (int) $user->id,
                'body' => trim((string) $request->string('body')),
            ]);

            $room->update([
                'last_message_id' => (int) $message->id,
                'last_message_at' => $message->created_at,
            ]);

            UnitChatRead::query()->updateOrCreate(
                [
                    'unit_chat_room_id' => (int) $room->id,
                    'user_id' => (int) $user->id,
                ],
                [
                    'last_read_message_id' => (int) $message->id,
                    'last_read_at' => now(),
                ],
            );
        });

        return to_route('chat', ['room' => $room->id]);
    }
}
