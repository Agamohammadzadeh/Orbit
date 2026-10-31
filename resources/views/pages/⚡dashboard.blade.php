<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('داشبورد')] class extends Component {};
?>

<div>
    <h1 class="text-2xl font-bold tracking-tight">سلام {{ auth()->user()->name }}</h1>
    <p class="mt-1 text-muted">داشبورد به‌زودی اینجا ساخته می‌شود.</p>
</div>
