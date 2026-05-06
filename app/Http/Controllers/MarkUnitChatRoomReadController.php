<?php

namespace App\Http\Controllers;

use App\Http\Requests\MarkUnitChatRoomReadRequest;
use App\Models\EmployeeAssignment;
use App\Models\Role;
use App\Models\UnitChatMessage;
use App\Models\UnitChatRead;
use App\Models\UnitChatRoom;
use App\Services\BranchContextService;
use App\Services\HrIndexUnitFilterCatalog;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\Response;

class MarkUnitChatRoomReadController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
        private HrIndexUnitFilterCatalog $hrIndexUnitFilterCatalog,
    ) {}

    public function __invoke(MarkUnitChatRoomReadRequest $request, UnitChatRoom $room): Response
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

        $lastReadMessageId = (int) $request->integer('last_read_message_id', 0);
        if ($lastReadMessageId <= 0) {
            $lastReadMessageId = (int) (UnitChatMessage::query()
                ->where('unit_chat_room_id', (int) $room->id)
                ->max('id') ?? 0);
        } else {
            $belongsToRoom = UnitChatMessage::query()
                ->whereKey($lastReadMessageId)
                ->where('unit_chat_room_id', (int) $room->id)
                ->exists();
            if (! $belongsToRoom) {
                return response()->json([
                    'message' => 'Selected message does not belong to this room.',
                ], 422);
            }
        }

        UnitChatRead::query()->updateOrCreate(
            [
                'unit_chat_room_id' => (int) $room->id,
                'user_id' => (int) $user->id,
            ],
            [
                'last_read_message_id' => $lastReadMessageId > 0 ? $lastReadMessageId : null,
                'last_read_at' => now(),
            ],
        );

        return response()->noContent();
    }
}
