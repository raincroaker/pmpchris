<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyWorkScheduleTemplateRequest;
use App\Models\WorkScheduleTemplate;
use Illuminate\Http\Response;

class DestroyWorkScheduleTemplateController extends Controller
{
    public function __invoke(
        DestroyWorkScheduleTemplateRequest $request,
        WorkScheduleTemplate $workScheduleTemplate,
    ): Response {
        $workScheduleTemplate->delete();

        return response()->noContent();
    }
}
