<?php

namespace App\Http\Controllers;

use App\Actions\Reports\ExportTasksCsv;
use App\Models\Project;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    /** خروجی CSV تسک‌های یک پروژه؛ دسترسی با can:view,project روی روت چک شده */
    public function __invoke(Request $request, Project $project)
    {
        return (new ExportTasksCsv)->handle($request->user(), $project);
    }
}
