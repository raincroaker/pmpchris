<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAssignment;
use App\Models\Role;
use App\Models\UnitChatMessage;
use App\Models\UnitChatRoom;
use App\Services\BranchContextService;
use App\Services\HrIndexUnitFilterCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function __construct(
        private BranchContextService $branchContextService,
        private HrIndexUnitFilterCatalog $hrIndexUnitFilterCatalog,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        if ($user === null) {
            return Inertia::render('Chat', [
                'rooms' => [],
                'selectedRoomId' => null,
                'searchQuery' => '',
            ]);
        }

        $hasGlobalChatAccess = $user->hasAnyRole([
            Role::CODE_SUPER_ADMIN,
            Role::CODE_HR_HEAD,
        ]);
        $searchQuery = trim((string) $request->string('q'));
        $today = now()->toDateString();

        $memberAssignments = EmployeeAssignment::query()
            ->with([
                'organizationalUnit:id,name,code',
                'employee:id,first_name,last_name,id_number',
                'employee.user:id,employee_id',
            ])
            ->whereNotNull('organizational_unit_id')
            ->whereNull('deleted_at', 'and', false)
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('start_date', 'and', false)
                    ->orWhereDate('start_date', '<=', $today);
            })
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('end_date', 'and', false)
                    ->orWhereDate('end_date', '>=', $today);
            })
            ->orderBy('id')
            ->get();
        $workspace = $this->branchContextService->workspaceBranchContext($request);
        $organization = $this->branchContextService->defaultOrganization();
        $branchAllowedUnitIds = null;
        if ($workspace !== null && $organization !== null) {
            $branchAllowedUnitIds = $this->hrIndexUnitFilterCatalog->collectBranchUnitSubtreeIds(
                (int) $organization->id,
                (int) $workspace['id'],
            );
        }

        $unitIds = $memberAssignments
            ->when(
                ! $hasGlobalChatAccess,
                fn ($assignments) => $assignments->where('employee_id', (int) $user->employee_id),
            )
            ->when(
                is_array($branchAllowedUnitIds),
                fn ($assignments) => $assignments->whereIn('organizational_unit_id', $branchAllowedUnitIds),
            )
            ->pluck('organizational_unit_id')
            ->filter(fn ($id): bool => is_numeric($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($unitIds === []) {
            return Inertia::render('Chat', [
                'rooms' => [],
                'selectedRoomId' => null,
                'searchQuery' => $searchQuery,
            ]);
        }

        foreach ($unitIds as $unitId) {
            UnitChatRoom::query()->firstOrCreate([
                'organizational_unit_id' => $unitId,
            ]);
        }

        $rooms = UnitChatRoom::query()
            ->with([
                'organizationalUnit:id,name,code',
                'messages' => fn ($query) => $query
                    ->with(['sender:id,name'])
                    ->latest('id')
                    ->limit(200),
            ])
            ->whereIn('organizational_unit_id', $unitIds)
            ->get()
            ->filter(function (UnitChatRoom $room) use ($searchQuery): bool {
                if ($searchQuery === '') {
                    return true;
                }

                $needle = mb_strtolower($searchQuery);
                $unitName = mb_strtolower((string) ($room->organizationalUnit?->name ?? ''));
                $unitCode = mb_strtolower((string) ($room->organizationalUnit?->code ?? ''));

                return str_contains($unitName, $needle) || str_contains($unitCode, $needle);
            })
            ->sortBy('organizationalUnit.name')
            ->values();

        $roomIds = $rooms
            ->map(fn (UnitChatRoom $room): int => (int) $room->id)
            ->values()
            ->all();

        /** @var Collection<int, int> $unreadByRoomId */
        $unreadByRoomId = UnitChatMessage::query()
            ->selectRaw('unit_chat_messages.unit_chat_room_id, count(*) as unread_count')
            ->whereIn('unit_chat_messages.unit_chat_room_id', $roomIds, 'and', false)
            ->whereRaw(
                'unit_chat_messages.id > coalesce((select last_read_message_id from unit_chat_reads where unit_chat_reads.unit_chat_room_id = unit_chat_messages.unit_chat_room_id and unit_chat_reads.user_id = ? limit 1), 0)',
                [(int) $user->id],
                'and',
            )
            ->groupBy('unit_chat_messages.unit_chat_room_id')
            ->pluck('unread_count', 'unit_chat_messages.unit_chat_room_id')
            ->map(fn ($value): int => (int) $value);

        $rooms = $rooms->map(function (UnitChatRoom $room) use ($memberAssignments, $unreadByRoomId, $user): array {
            $unitMembers = $memberAssignments
                ->where('organizational_unit_id', $room->organizational_unit_id)
                ->unique('employee_id')
                ->values();

            $messages = $room->messages
                ->sortBy('id')
                ->values();

            /** @var array<int, mixed> $roomMessages */
            $roomMessages = $messages->map(function ($message) use ($user): array {
                return [
                    'id' => (int) $message->id,
                    'text' => (string) $message->body,
                    'createdLabel' => $message->created_at?->format('M j, g:i A') ?? '',
                    'createdAtFullLabel' => $message->created_at?->toDateTimeString(),
                    'role' => (int) $message->sender_user_id === (int) $user->id ? 'me' : 'them',
                    'senderId' => (int) $message->sender_user_id,
                    'senderLabel' => (string) ($message->sender?->name ?? 'Unknown'),
                ];
            })->all();

            $lastMessage = $messages->last();
            $unreadCount = (int) ($unreadByRoomId->get((int) $room->id, 0));

            return [
                'id' => (int) $room->id,
                'name' => (string) ($room->organizationalUnit?->name ?? 'Unit Chat'),
                'createdLabel' => $room->created_at?->format('M j, Y') ?? '',
                'avatarUrl' => null,
                'statusLine' => sprintf('%d members', $unitMembers->count()),
                'unreadCount' => $unreadCount,
                'lastMessage' => $lastMessage?->body ?? 'No messages yet.',
                'lastSeen' => $lastMessage?->created_at?->diffForHumans() ?? 'No activity yet',
                'members' => $unitMembers->map(function ($assignment): array {
                    $firstName = (string) ($assignment->employee?->first_name ?? '');
                    $lastName = (string) ($assignment->employee?->last_name ?? '');
                    $fullName = trim($firstName.' '.$lastName);

                    return [
                        'id' => (int) $assignment->employee_id,
                        'name' => $fullName !== '' ? $fullName : 'Unknown',
                        'employeeCode' => (string) ($assignment->employee?->id_number ?? ''),
                        'avatarUrl' => null,
                        'role' => $assignment->is_head ? 'admin' : 'member',
                        'statusLine' => $assignment->is_head ? 'Unit head' : 'Unit member',
                    ];
                })->all(),
                'messages' => $roomMessages,
            ];
        })
            ->all();

        $selectedRoomId = (int) $request->integer('room', (int) ($rooms[0]['id'] ?? 0));
        $allowedRoomIds = collect($rooms)->pluck('id')->all();
        if (! in_array($selectedRoomId, $allowedRoomIds, true)) {
            $selectedRoomId = (int) ($rooms[0]['id'] ?? 0);
        }

        return Inertia::render('Chat', [
            'rooms' => $rooms,
            'selectedRoomId' => $selectedRoomId > 0 ? $selectedRoomId : null,
            'searchQuery' => $searchQuery,
        ]);
    }
}
