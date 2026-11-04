<?php

use App\Actions\Projects\CreateProject;
use App\Actions\Projects\SetProjectArchived;
use App\Actions\Projects\UpdateProject;
use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?int $projectId = null;

    public string $name = '';

    public string $description = '';

    public string $status = 'active';

    public string $color = '#5B5BD6';

    public string $clientId = '';

    public string $startDate = '';

    public string $endDate = '';

    /** فقط هنگام ساخت: قالب مراحل */
    public string $template = 'custom_website';

    /** @var array<int,int|string> */
    public array $memberIds = [];

    public function mount(?Project $project = null): void
    {
        if ($project) {
            Gate::authorize('update', $project);

            $this->projectId = $project->id;
            $this->name = $project->name;
            $this->description = (string) $project->description;
            $this->status = $project->status->value;
            $this->color = $project->color ?? '#5B5BD6';
            $this->clientId = (string) $project->client_id;
            $this->startDate = $project->start_date?->format('Y-m-d') ?? '';
            $this->endDate = $project->end_date?->format('Y-m-d') ?? '';
            $this->memberIds = $project->members()->pluck('users.id')->all();

            return;
        }

        Gate::authorize('create', Project::class);

        $this->memberIds = [auth()->id()];
    }

    /* ---------------- داده‌ها ---------------- */

    #[Computed]
    public function project(): ?Project
    {
        return $this->projectId ? Project::query()->find($this->projectId) : null;
    }

    #[Computed]
    public function users()
    {
        return User::query()->active()->orderBy('name')->get(['id', 'name', 'email', 'avatar_path']);
    }

    /** انتخاب مشتری فقط با دسترسی «مدیریت مشتری‌ها» */
    #[Computed]
    public function canPickClient(): bool
    {
        return auth()->user()->can('viewAny', Client::class);
    }

    #[Computed]
    public function clients()
    {
        return $this->canPickClient
            ? Client::query()->orderBy('name')->get(['id', 'name'])
            : collect();
    }

    /* ---------------- ذخیره ---------------- */

    public function save(): void
    {
        $project = $this->project;

        $project
            ? Gate::authorize('update', $project)
            : Gate::authorize('create', Project::class);

        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'clientId' => ['nullable', Rule::exists('clients', 'id')],
            'startDate' => ['nullable', 'date_format:Y-m-d'],
            'endDate' => array_filter([
                'nullable',
                'date_format:Y-m-d',
                $this->startDate !== '' ? 'after_or_equal:startDate' : null,
            ]),
            'memberIds' => ['array'],
            'memberIds.*' => [Rule::exists('users', 'id')->where('is_active', true)],
        ];

        if (! $project) {
            $rules['template'] = ['required', Rule::in(array_keys(config('orbit.phase_templates', [])))];
        }

        $this->validate($rules, [
            'name.required' => 'نام پروژه را وارد کن.',
            'name.min' => 'نام پروژه باید حداقل :min حرف باشد.',
            'name.max' => 'نام پروژه نباید بیشتر از :max حرف باشد.',
            'description.max' => 'توضیحات نباید بیشتر از :max حرف باشد.',
            'status.required' => 'وضعیت را انتخاب کن.',
            'color.regex' => 'رنگ معتبر نیست.',
            'clientId.exists' => 'این مشتری وجود ندارد.',
            'startDate.date_format' => 'تاریخ شروع معتبر نیست.',
            'endDate.date_format' => 'تاریخ پایان معتبر نیست.',
            'endDate.after_or_equal' => 'تاریخ پایان نمی‌تواند قبل از تاریخ شروع باشد.',
            'memberIds.*.exists' => 'یکی از اعضای انتخاب‌شده معتبر نیست.',
            'template.in' => 'قالب انتخاب‌شده معتبر نیست.',
        ]);

        $data = [
            'name' => trim($this->name),
            'description' => trim($this->description) !== '' ? trim($this->description) : null,
            'status' => $this->status,
            'color' => $this->color,
            'start_date' => $this->startDate !== '' ? $this->startDate : null,
            'end_date' => $this->endDate !== '' ? $this->endDate : null,
        ];

        // مشتری فقط وقتی تغییر می‌کند که کاربر دسترسی انتخابش را دارد
        if ($this->canPickClient) {
            $data['client_id'] = $this->clientId !== '' ? (int) $this->clientId : null;
        }

        $ids = array_map('intval', $this->memberIds);

        if ($project) {
            (new UpdateProject)->handle(auth()->user(), $project, $data, $ids);
            session()->flash('status', 'تغییرات پروژه ذخیره شد.');
        } else {
            (new CreateProject)->handle(auth()->user(), $data, $ids, $this->template);
            session()->flash('status', 'پروژه ساخته شد.');
        }

        $this->redirectRoute('projects.index', navigate: true);
    }

    public function toggleArchive(): void
    {
        $project = $this->project;
        abort_unless($project, 404);
        Gate::authorize('archive', $project);

        $archive = ! $project->isArchived();
        (new SetProjectArchived)->handle($project, $archive);

        session()->flash('status', $archive ? 'پروژه آرشیو شد.' : 'پروژه به فهرست فعال برگشت.');
        $this->redirectRoute('projects.index', navigate: true);
    }
};
?>

@php
    $me = auth()->user();
    $project = $this->project;
    $isEdit = $project !== null;
    $lockSelf = ! $me->can('projects.view-all');

    $statusOptions = [];
    foreach (\App\Enums\ProjectStatus::cases() as $s) {
        $statusOptions[$s->value] = $s->label();
    }

    $clientOptions = ['' => 'بدون مشتری'] + $this->clients->pluck('name', 'id')->all();

    $templateOptions = collect(config('orbit.phase_templates', []))->map(fn ($t) => $t['label'])->all();
    $phases = config("orbit.phase_templates.{$template}.phases", []);

    $palette = ['#5B5BD6', '#12A594', '#D6409F', '#F5A524', '#3E8EF7', '#FF7A59', '#E5484D', '#8B8BA3'];
@endphp

<form wire:submit="save" class="grid grid-cols-1 items-start gap-6 lg:grid-cols-3" novalidate>
    {{-- ستون اصلی --}}
    <div class="space-y-6 lg:col-span-2">
        <div class="card space-y-5 p-6">
            <h2 class="text-base font-bold">اطلاعات پروژه</h2>

            <div class="space-y-1.5">
                <label for="project-name" class="text-sm font-medium">نام پروژه</label>
                <input
                    id="project-name"
                    type="text"
                    wire:model="name"
                    class="input"
                    placeholder="مثلاً: سایت شرکتی آریان"
                    autocomplete="off"
                    aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                />
                @error('name')
                <p class="text-sm text-danger">{{ $message }}</p>
                @enderror
            </div>

            @if ($this->canPickClient)
                <div class="space-y-1.5">
                    <label for="project-client" class="text-sm font-medium">مشتری</label>
                    <x-ui.select id="project-client" wire:model="clientId" :options="$clientOptions" />
                    @error('clientId')
                    <p class="text-sm text-danger">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div class="space-y-1.5">
                <label for="project-description" class="text-sm font-medium">توضیحات <span class="font-normal text-muted">(اختیاری)</span></label>
                <textarea
                    id="project-description"
                    wire:model="description"
                    rows="4"
                    class="input h-auto! min-h-28 resize-y py-2.5"
                    placeholder="هدف پروژه، محدوده‌ی کار و نکات مهم…"
                    aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}"
                ></textarea>
                @error('description')
                <p class="text-sm text-danger">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- قالب مراحل (فقط هنگام ساخت) --}}
        @unless ($isEdit)
            <div class="card space-y-4 p-6">
                <div>
                    <h2 class="text-base font-bold">مراحل پروژه</h2>
                    <p class="mt-0.5 text-sm text-muted">یک قالب انتخاب کن تا مراحل آماده ساخته شود. بعداً قابل تغییر است.</p>
                </div>

                <x-ui.select id="project-template" wire:model.live="template" :options="$templateOptions" />

                @if ($phases)
                    <ol class="flex flex-wrap items-center gap-2 text-xs">
                        @foreach ($phases as $i => $phase)
                            <li class="inline-flex items-center gap-1.5 rounded-full bg-line/60 px-3 py-1.5">
                                <span class="tabular text-muted">{{ $i + 1 }}</span>
                                {{ $phase }}
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="text-sm text-muted">پروژه بدون مرحله ساخته می‌شود.</p>
                @endif
            </div>
        @endunless

        {{-- اعضا --}}
        <div class="card" x-data="{ q: '' }">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-4">
                <div>
                    <h2 class="text-base font-bold">اعضای پروژه</h2>
                    <p class="mt-0.5 text-sm text-muted"><span class="tabular">{{ count($memberIds) }}</span> نفر انتخاب شده</p>
                </div>

                <div class="relative w-full sm:w-56">
                    <x-ui.icon name="search" class="pointer-events-none absolute start-3.5 top-1/2 size-4.5 -translate-y-1/2 text-muted" />
                    <input type="search" x-model="q" class="input h-10 ps-10!" placeholder="جستجوی عضو…" aria-label="جستجوی عضو" />
                </div>
            </div>

            <div class="max-h-96 divide-y divide-line overflow-y-auto">
                @foreach ($this->users as $u)
                    @php $isMe = $u->id === $me->id; @endphp
                    <label
                        wire:key="member-{{ $u->id }}"
                        data-search="{{ mb_strtolower($u->name.' '.$u->email) }}"
                        x-show="!q || $el.dataset.search.includes(q.toLowerCase())"
                        class="flex items-center gap-3 px-6 py-3 transition hover:bg-line/30"
                    >
                        <input
                            type="checkbox"
                            wire:model="memberIds"
                            value="{{ $u->id }}"
                            @disabled($isMe && $lockSelf)
                            class="size-4.5 shrink-0 rounded border-line accent-primary"
                        />
                        <x-ui.avatar :user="$u" :size="32" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">{{ $u->name }}</span>
                            <span class="block truncate text-xs text-muted">{{ $u->roleLabel() }}</span>
                        </span>
                        @if ($isMe)
                            <span class="shrink-0 rounded-full bg-line/60 px-2 py-0.5 text-[0.6875rem] text-muted">شما</span>
                        @endif
                    </label>
                @endforeach
            </div>

            @error('memberIds.*')
            <p class="border-t border-line px-6 py-3 text-sm text-danger">{{ $message }}</p>
            @enderror

            @if ($lockSelf)
                <p class="border-t border-line px-6 py-3 text-xs leading-relaxed text-muted">
                    خودت همیشه عضو پروژه می‌مانی تا دسترسی‌ات به آن قطع نشود.
                </p>
            @endif
        </div>
    </div>

    {{-- ستون کناری --}}
    <div class="space-y-6">
        <div class="card space-y-5 p-6">
            <h2 class="text-base font-bold">تنظیمات</h2>

            <div class="space-y-1.5">
                <label for="project-status" class="text-sm font-medium">وضعیت</label>
                <x-ui.select id="project-status" wire:model="status" :options="$statusOptions" />
                @error('status')
                <p class="text-sm text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-2">
                <span class="text-sm font-medium">رنگ پروژه</span>
                <div class="flex flex-wrap gap-2.5">
                    @foreach ($palette as $c)
                        <button
                            type="button"
                            wire:click="$set('color', '{{ $c }}')"
                            style="background-color: {{ $c }}"
                            aria-label="رنگ {{ $c }}"
                            aria-pressed="{{ $color === $c ? 'true' : 'false' }}"
                            @class([
                                'flex size-8 items-center justify-center rounded-full text-white ring-offset-2 ring-offset-surface transition',
                                'ring-2 ring-fg' => $color === $c,
                            ])
                        >
                            @if ($color === $c)
                                <x-ui.icon name="check" class="size-4" />
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="space-y-1.5">
                <label for="project-start" class="text-sm font-medium">تاریخ شروع</label>
                <x-ui.date-field id="project-start" wire:model="startDate" placeholder="انتخاب تاریخ" />
                @error('startDate')
                <p class="text-sm text-danger">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-1.5">
                <label for="project-end" class="text-sm font-medium">تاریخ پایان</label>
                <x-ui.date-field id="project-end" wire:model="endDate" placeholder="انتخاب تاریخ" />
                @error('endDate')
                <p class="text-sm text-danger">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- آرشیو (فقط ویرایش) --}}
        @if ($isEdit)
            @can('archive', $project)
                <div class="card space-y-3 p-6">
                    <h2 class="text-base font-bold">{{ $project->isArchived() ? 'پروژه آرشیو شده' : 'آرشیو پروژه' }}</h2>
                    <p class="text-sm leading-relaxed text-muted">
                        {{ $project->isArchived()
                            ? 'پروژه از فهرست فعال خارج است. با بازگردانی دوباره دیده می‌شود.'
                            : 'پروژه از فهرست فعال و جستجو خارج می‌شود. تسک‌ها و اعضا حفظ می‌شوند.' }}
                    </p>

                    @if ($project->isArchived())
                        <button type="button" wire:click="toggleArchive" class="btn-secondary w-full">
                            <x-ui.icon name="restore" class="size-4.5" />
                            بازگردانی پروژه
                        </button>
                    @else
                        <button
                            type="button"
                            @click="$store.confirm.ask({
                                title: @js('آرشیو «'.$project->name.'»'),
                                message: 'پروژه از فهرست فعال خارج می‌شود و هر وقت بخواهی بازش می‌گردانی.',
                                confirmLabel: 'آرشیو کن',
                                tone: 'primary',
                                action: () => $wire.toggleArchive(),
                            })"
                            class="btn-secondary w-full"
                        >
                            <x-ui.icon name="archive" class="size-4.5" />
                            آرشیو پروژه
                        </button>
                    @endif
                </div>
            @endcan
        @endif

        <div class="flex items-center gap-2.5">
            <a href="{{ route('projects.index') }}" wire:navigate class="btn-secondary flex-1">انصراف</a>
            <button type="submit" class="btn-primary flex-1" wire:loading.attr="disabled" wire:target="save">
                <svg wire:loading wire:target="save" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3" />
                    <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                </svg>
                {{ $isEdit ? 'ذخیره' : 'ساخت پروژه' }}
            </button>
        </div>
    </div>
</form>
