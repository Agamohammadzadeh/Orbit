<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectAccess;

class CommentPolicy
{
    use ChecksProjectAccess;

    /** استفاده: $user->can('create', [Comment::class, $task]) */
    public function create(User $user, Task $task): bool
    {
        return $user->can(Ability::CommentsCreate->value)
            && $this->canAccessProject($user, $task->project_id);
    }

    public function update(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->id
            && $user->can(Ability::CommentsCreate->value);
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $this->update($user, $comment);
    }
}
