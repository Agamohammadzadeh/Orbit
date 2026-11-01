<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('پروژه‌ی جدید')] class extends Component {};
?>

<div class="mx-auto max-w-5xl space-y-6">
    <div>
        <a href="{{ route('projects.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm text-muted transition hover:text-primary">
            <x-ui.icon name="arrow-left" class="size-4 rtl:rotate-180" />
            پروژه‌ها
        </a>
        <h1 class="mt-2 text-2xl font-bold tracking-tight">پروژه‌ی جدید</h1>
        <p class="mt-1 text-sm text-muted">اطلاعات پایه را وارد کن و اعضا را انتخاب کن. بقیه را بعداً داخل پروژه تنظیم می‌کنی.</p>
    </div>

    <livewire:projects.form />
</div>
