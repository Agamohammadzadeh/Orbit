@php
    $custom = trim((string) ($exception?->getMessage() ?? ''));
    $message = $custom !== '' && preg_match('/\p{Arabic}/u', $custom)
        ? $custom
        : 'اجازه‌ی دیدن این صفحه را نداری. اگر فکر می‌کنی باید داشته باشی، از مدیر تیم بخواه تو را به پروژه اضافه کند یا دسترسی‌ات را تغییر بدهد.';
@endphp

<x-layout.error code="403" title="دسترسی نداری" icon="lock" :message="$message" />
