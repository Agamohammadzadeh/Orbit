<?php

use App\Models\Project;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('ویرایش پروژه')] class extends Component {
    public Project $project;

    public function mount(Project $project): void
    {
        Gate::authorize('update', $project);

        $this->project = $project;
    }
};
?>

<div class="mx-auto max-w-5xl space-y-6">
    <div>
        <a href="{{ route('projects.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-muted transition hover:text-primary">
            <x-ui.icon name="arrow-left" class="size-4 rtl:rotate-180" />
            پروژه‌ها
        </a>
        <h1 class="mt-2 text-2xl font-bold tracking-tight">ویرایش پروژه</h1>
        <p class="mt-1 text-sm text-muted">{{ $project->name }}</p>
    </div>

    <livewire:project.form :project="$project" />
</div>
