<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class AttachmentDownloadController extends Controller
{
    /** دانلود (یا با ?inline=1 نمایش تصویر)؛ فقط برای کسی که تسک را می‌تواند ببیند */
    public function __invoke(Request $request, Attachment $attachment)
    {
        $task = Task::query()->findOrFail($attachment->task_id);
        Gate::authorize('view', $task);

        $disk = Storage::disk(config('orbit.attachments.disk'));
        abort_unless($disk->exists($attachment->path), 404);

        // نمایش درون‌خطی فقط برای تصویرهای رستری (SVG می‌تواند اسکریپت داشته باشد)
        if ($request->boolean('inline') && $attachment->isImage() && ! str_contains((string) $attachment->mime_type, 'svg')) {
            return $disk->response($attachment->path, $attachment->original_name, [
                'Content-Type' => $attachment->mime_type,
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return $disk->download($attachment->path, $attachment->original_name);
    }
}
