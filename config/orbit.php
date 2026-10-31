<?php

/*
 * تنظیمات اختصاصی Orbit.
 * کلیدهای status / priority / type باید دقیقاً با مقدار (value) Enumها یکی باشند.
 */

return [

    'name' => 'Orbit',

    // rtl | ltr
    'direction' => env('ORBIT_DIRECTION', 'rtl'),

    // jalali | gregorian  (نمایش تاریخ؛ در دیتابیس همیشه میلادی ذخیره می‌شود)
    'calendar' => env('ORBIT_CALENDAR', 'jalali'),

    // سیستم داخلی است؛ ثبت‌نام عمومی نداریم
    'registration_enabled' => (bool) env('ORBIT_REGISTRATION_ENABLED', false),

    // دیتای ادمین اصلی
    'admin' => [
        'name' => env('ORBIT_ADMIN_NAME', 'مدیر Orbit'),
        'email' => env('ORBIT_ADMIN_EMAIL', 'admin@orbit.test'),
        'password' => env('ORBIT_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | پیوست‌ها
    |--------------------------------------------------------------------------
    | دیسک attachments را در config/filesystems.php تعریف کن (راهنما در SETUP.md).
    | دانلود فقط از مسیر کنترل‌شده‌ی attachments.download و بعد از چک Policy.
    */
    'attachments' => [
        'disk' => env('ORBIT_ATTACHMENTS_DISK', 'attachments'),
        'max_kb' => (int) env('ORBIT_MAX_UPLOAD_KB', 10240),
        'max_per_task' => 20,
        'allowed_extensions' => [
            'png', 'jpg', 'jpeg', 'webp', 'gif', 'svg',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'md', 'csv',
            'zip', 'rar', '7z', 'fig', 'sketch', 'xd', 'psd', 'ai',
        ],
    ],

    'avatars' => [
        'disk' => 'public',
        'max_kb' => 2048,
    ],

    'pagination' => [
        'tasks' => 25,
        'activity' => 20,
        'notifications' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | کانبان
    |--------------------------------------------------------------------------
    | ترتیب کارت‌ها با ستون position و «فاصله‌ی ثابت» ذخیره می‌شود
    | (مثلاً ۱۰۰۰، ۲۰۰۰، ۳۰۰۰) تا با هر درگ لازم نباشد همه‌ی ردیف‌ها بازنویسی شوند.
    | وقتی فاصله‌ی دو کارت به ۱ رسید، ستون یک‌بار ری‌ایندکس می‌شود.
    */
    'kanban' => [
        'position_gap' => 1000,
        'columns' => [
            'todo'        => ['label' => 'انجام‌نشده',  'color' => '#8B8BA3'],
            'in_progress' => ['label' => 'در حال انجام', 'color' => '#3E8EF7'],
            'in_review'   => ['label' => 'در بازبینی',   'color' => '#F5A524'],
            'done'        => ['label' => 'تمام‌شده',     'color' => '#2FB67C'],
        ],
    ],

    'priorities' => [
        'low'    => ['label' => 'کم',    'color' => '#8B8BA3'],
        'medium' => ['label' => 'متوسط', 'color' => '#3E8EF7'],
        'high'   => ['label' => 'زیاد',  'color' => '#F76B15'],
        'urgent' => ['label' => 'فوری',  'color' => '#E5484D'],
    ],

    'task_types' => [
        'feature'     => ['label' => 'فیچر',  'color' => '#5B5BD6'],
        'bug'         => ['label' => 'باگ',   'color' => '#E5484D'],
        'improvement' => ['label' => 'بهبود', 'color' => '#12A594'],
        'design'      => ['label' => 'طراحی', 'color' => '#D6409F'],
        'content'     => ['label' => 'محتوا', 'color' => '#F5A524'],
    ],

    'project_statuses' => [
        'active' => ['label' => 'فعال',      'color' => '#2FB67C'],
        'paused' => ['label' => 'متوقف',     'color' => '#F5A524'],
        'done'   => ['label' => 'تمام‌شده', 'color' => '#8B8BA3'],
    ],

    /*
    | لینک‌ها: host برای تشخیص خودکار نوع از روی URL
    */
    'link_types' => [
        'figma'   => ['label' => 'Figma',   'hosts' => ['figma.com']],
        'repo'    => ['label' => 'ریپازیتوری', 'hosts' => ['github.com', 'gitlab.com', 'bitbucket.org']],
        'staging' => ['label' => 'Staging', 'hosts' => []],
        'other'   => ['label' => 'سایر',    'hosts' => []],
    ],

    /*
    |--------------------------------------------------------------------------
    | قالب مراحل پروژه
    |--------------------------------------------------------------------------
    | هنگام ساخت پروژه، یکی از این قالب‌ها به‌صورت رکوردهای Phase ساخته می‌شود.
    */
    'phase_templates' => [
        'custom_website' => [
            'label' => 'سایت سفارشی',
            'phases' => [
                'تحلیل و نیازسنجی',
                'طراحی UI/UX',
                'پیاده‌سازی فرانت‌اند',
                'پیاده‌سازی بک‌اند',
                'تست و رفع باگ',
                'تحویل و استقرار',
            ],
        ],
        'landing_page' => [
            'label' => 'لندینگ‌پیج',
            'phases' => ['محتوا و ساختار', 'طراحی', 'پیاده‌سازی', 'تست و تحویل'],
        ],
        'redesign' => [
            'label' => 'بازطراحی سایت',
            'phases' => ['بررسی وضع موجود', 'طراحی جدید', 'پیاده‌سازی', 'انتقال محتوا', 'تست', 'تحویل'],
        ],
        'maintenance' => [
            'label' => 'پشتیبانی و نگهداری',
            'phases' => ['درخواست‌ها', 'در حال انجام', 'تحویل'],
        ],
        'blank' => [
            'label' => 'خالی',
            'phases' => [],
        ],
    ],

    // برچسب‌های اولیه (Seeder)
    'default_labels' => [
        ['name' => 'فرانت‌اند', 'color' => '#5B5BD6'],
        ['name' => 'بک‌اند',    'color' => '#12A594'],
        ['name' => 'طراحی',     'color' => '#D6409F'],
        ['name' => 'باگ',       'color' => '#E5484D'],
        ['name' => 'محتوا',     'color' => '#F5A524'],
        ['name' => 'فوری',      'color' => '#F76B15'],
    ],

    /*
    |--------------------------------------------------------------------------
    | منشن
    |--------------------------------------------------------------------------
    | user-picker هنگام انتخاب همکار در متن کامنت توکن @[user:12] درج می‌کند.
    | به این ترتیب منشن به id وصل است و با تغییر نام کاربر خراب نمی‌شود.
    | هنگام نمایش، توکن به نام فعلی کاربر تبدیل می‌شود.
    */
    'mentions' => [
        'pattern' => '/@\[user:(\d+)\]/',
        'max_per_comment' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | یادآوری ددلاین (دستور orbit:send-deadline-reminders)
    |--------------------------------------------------------------------------
    */
    'reminders' => [
        'enabled' => (bool) env('ORBIT_REMINDERS_ENABLED', true),
        'hours_before' => (int) env('ORBIT_REMINDER_HOURS', 24),
        'time' => env('ORBIT_REMINDER_TIME', '09:00'),
        'mail' => (bool) env('ORBIT_REMINDER_MAIL', false),
    ],

    'time_tracking' => [
        'step_minutes' => 15,
        'max_entry_minutes' => 960,
    ],

    // کش گزارش‌ها (ثانیه)
    'reports' => [
        'cache_ttl' => (int) env('ORBIT_REPORT_CACHE_TTL', 300),
    ],
];
