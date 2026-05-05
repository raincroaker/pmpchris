<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateWorkScheduleTemplateRequest;
use App\Models\WorkScheduleTemplate;
use App\Support\WorkSchedulePayloadPreparer;
use Illuminate\Http\JsonResponse;

class UpdateWorkScheduleTemplateController extends Controller
{
    public function __invoke(
        UpdateWorkScheduleTemplateRequest $request,
        WorkScheduleTemplate $workScheduleTemplate,
    ): JsonResponse {
        $validated = WorkSchedulePayloadPreparer::fromValidated($request->validated());

        $workScheduleTemplate->fill([
            ...$validated,
            'updated_by_user_id' => $request->user()?->id,
        ]);
        $workScheduleTemplate->save();

        return response()->json([
            'data' => $workScheduleTemplate->fresh()->toShiftRuleArray(),
        ]);
    }
}
