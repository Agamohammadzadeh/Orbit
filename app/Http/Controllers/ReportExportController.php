<?php

namespace App\Http\Controllers;

use App\Actions\Reports\ExportTasksCsv;
use Illuminate\Http\Request;

class ReportExportController extends Controller
{
    /** خروجی CSV از همه‌ی تسک‌های داخل محدوده‌ی گزارش‌ها؛ ?project=ID برای یک پروژه */
    public function __invoke(Request $request)
    {
        $project = (string) $request->query('project');

        return (new ExportTasksCsv)->handle(
            $request->user(),
            null,
            ctype_digit($project) ? (int) $project : null,
        );
    }
}
