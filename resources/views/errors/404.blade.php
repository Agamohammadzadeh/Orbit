<x-layout.error
    code="404"
    title="صفحه پیدا نشد"
    icon="search"
    message="آدرس اشتباه است یا این مورد حذف شده. اگر از یک لینک قدیمی آمده‌ای، ممکن است پروژه یا تسکش پاک شده باشد."
>
    <p dir="ltr" class="tabular mx-auto mt-5 w-fit max-w-full truncate rounded-control border border-line bg-surface px-3 py-1.5 text-xs text-muted">
        {{ request()->getPathInfo() }}
    </p>
</x-layout.error>
