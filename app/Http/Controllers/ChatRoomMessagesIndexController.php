<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAssignment;
use App\Models\Role;
use App\Models\UnitChatMessage;
use App\Models\UnitChatRoom;
use App\Services\BranchContextService;
use App\Services\HrIndexUnitFilterCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatRoomMessagesIndexController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
        private HrIndexUnitFilterCatalog $hrIndexUnitFilterCatalog,
    ) {}

    public function __invoke(Request $request, UnitChatRoom $room): JsonResponse
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

        $limit = min(100, max(1, (int) $request->integer('limit', 50)));
        $beforeId = (int) $request->integer('before_id', 0);

        $query = UnitChatMessage::query()
            ->where('unit_chat_room_id', (int) $room->id)
            ->with('sender:id,name')
            ->orderByDesc('id');

        if ($beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $page = $hasMore ? $rows->take($limit) : $rows;
        $nextBeforeId = $hasMore ? (int) ($page->last()?->id ?? 0) : null;

        $messages = $page
            ->reverse()
            ->values()
            ->map(function (UnitChatMessage $message) use ($user): array {
                return [
                    'id' => (int) $message->id,
                    'text' => (string) $message->body,
                    'createdLabel' => $message->created_at?->format('M j, g:i A') ?? '',
                    'createdAtFullLabel' => $message->created_at?->toDateTimeString(),
                    'role' => (int) $message->sender_user_id === (int) $user->id ? 'me' : 'them',
                    'senderId' => (int) $message->sender_user_id,
                    'senderLabel' => (string) ($message->sender?->name ?? 'Unknown'),
                ];
            })
            ->all();

        return response()->json([
            'messages' => $messages,
            'hasMore' => $hasMore,
            'nextBeforeId' => $nextBeforeId,
        ]);
    }
}
