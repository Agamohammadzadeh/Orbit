<?php

namespace App\Actions\Reports;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Queries\ReportsQuery;
use App\Support\Dates;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportTasksCsv
{
    /**
     * فایل CSV (UTF-8 با BOM تا Excel فارسی را درست بخواند) از تسک‌ها را استریم می‌کند.
     * - $project: خروجی یک پروژه (دسترسی را قبلاً چک کن؛ پروژه‌ی آرشیو هم مجاز است)
     * - بدون پروژه: همه‌ی تسک‌های داخل محدوده‌ی گزارش‌های کاربر، با فیلتر اختیاری $projectId
     */
    public function handle(User $user, ?Project $project = null, ?int $projectId = null): StreamedResponse
    {
        $query = $project
            ? Task::query()->where('project_id', $project->id)
            : (new ReportsQuery($user, $projectId))->tasks();

        $query->with(['project:id,name', 'assignee:id,name', 'phase:id,name', 'sprint:id,name'])
            ->withSum('timeEntries as spent_minutes', 'minutes');

        $filename = 'orbit-tasks-'.($project?->slug ?? 'all').'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            $this->write($out, [
                'شناسه', 'پروژه', 'عنوان', 'وضعیت', 'اولویت', 'نوع', 'مسئول', 'مرحله', 'اسپرینت',
                'ددلاین', 'برآورد (ساعت)', 'زمان ثبت‌شده (ساعت)', 'تاریخ ساخت', 'تاریخ تکمیل',
            ]);

            $query->chunkById(500, function ($tasks) use ($out) {
                foreach ($tasks as $t) {
                    $this->write($out, [
                        $t->id,
                        $t->project?->name,
                        $t->title,
                        $t->status->label(),
                        $t->priority->label(),
                        $t->type->label(),
                        $t->assignee?->name,
                        $t->phase?->name,
                        $t->sprint?->name,
                        $t->due_date ? Dates::short($t->due_date) : '',
                        $t->estimate_minutes ? round($t->estimate_minutes / 60, 2) : '',
                        $t->spent_minutes ? round(((int) $t->spent_minutes) / 60, 2) : '',
                        Dates::short($t->created_at),
                        $t->completed_at ? Dates::short($t->completed_at) : '',
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @param resource $out */
    private function write($out, array $row): void
    {
        // escape خالی: رفتار ثابت در PHP 8.3 و 8.4
        fputcsv($out, array_map(fn ($v) => $this->safe($v), $row), ',', '"', '');
    }

    /** جلوگیری از اجرای فرمول در Excel (سلولی که با = + - @ شروع شود) */
    private function safe(mixed $value): string
    {
        $text = (string) $value;

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$text : $text;
    }
}
