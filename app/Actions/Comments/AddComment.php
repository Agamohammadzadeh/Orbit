<?php

namespace App\Actions\Comments;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\MentionedInCommentNotification;
use App\Notifications\NewCommentNotification;
use App\Support\RichText;

class AddComment
{
    /**
     * ثبت کامنت. «@نام» اعضای پروژه به توکن تبدیل می‌شود؛ منشن‌شده‌ها اعلان «منشن» و
     * مسئول و سازنده‌ی تسک (اگر منشن نشده باشند) اعلان «کامنت جدید» می‌گیرند.
     * دسترسی را قبلاً با Gate چک کن.
     */
    public function handle(User $actor, Task $task, string $body): Comment
    {
        $members = Project::query()->findOrFail($task->project_id)
            ->members()->active()->get(['users.id', 'users.name']);

        $comment = Comment::query()->create([
            'task_id' => $task->id,
            'user_id' => $actor->id,
            'body' => RichText::toTokens(trim($body), $members),
        ]);

        // فقط اعضای پروژه و نه خود نویسنده
        $mentioned = collect($comment->mentionedUserIds())
            ->intersect($members->pluck('id'))
            ->reject(fn ($id) => $id === $actor->id)
            ->values();

        $others = collect([$task->assignee_id, $task->creator_id])
            ->filter()
            ->unique()
            ->reject(fn ($id) => $id === $actor->id || $mentioned->contains($id))
            ->values();

        User::query()->active()->whereIn('id', $mentioned)->get()
            ->each->notify(new MentionedInCommentNotification($task, $actor));

        User::query()->active()->whereIn('id', $others)->get()
            ->each->notify(new NewCommentNotification($task, $actor));

        return $comment;
    }
}
