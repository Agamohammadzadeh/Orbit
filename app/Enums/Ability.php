<?php

namespace App\Enums;

/**
 * دسترسی‌های سراسری Orbit (Permission در Spatie).
 * نقش‌ها (Role) مجموعه‌ای از این دسترسی‌ها هستند و مدیر می‌تواند نقش جدید بسازد.
 */
enum Ability: string
{
    case ProjectsCreate = 'projects.create';
    case ProjectsViewAll = 'projects.view-all';
    case ProjectsArchive = 'projects.archive';
    case TasksWrite = 'tasks.write';
    case TasksDelete = 'tasks.delete';
    case CommentsCreate = 'comments.create';
    case ClientsManage = 'clients.manage';
    case ReportsViewAll = 'reports.view-all';
    case UsersManage = 'users.manage';

    public function label(): string
    {
        return match ($this) {
            self::ProjectsCreate => 'ساخت و ویرایش پروژه',
            self::ProjectsViewAll => 'دیدن همه‌ی پروژه‌ها',
            self::ProjectsArchive => 'آرشیو پروژه',
            self::TasksWrite => 'ساخت، ویرایش و جابه‌جایی تسک',
            self::TasksDelete => 'حذف هر تسک',
            self::CommentsCreate => 'نوشتن کامنت',
            self::ClientsManage => 'مدیریت مشتری‌ها',
            self::ReportsViewAll => 'گزارش همه‌ی پروژه‌ها',
            self::UsersManage => 'مدیریت کاربران و نقش‌ها',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ProjectsCreate => 'ساخت پروژه‌ی جدید و ویرایش پروژه‌هایی که عضوشان است.',
            self::ProjectsViewAll => 'همه‌ی پروژه‌ها را می‌بیند، حتی اگر عضو آن‌ها نباشد.',
            self::ProjectsArchive => 'پروژه را آرشیو یا از آرشیو خارج می‌کند.',
            self::TasksWrite => 'فقط در پروژه‌هایی که عضو است. حذف تسک‌های خودش هم با همین دسترسی ممکن است.',
            self::TasksDelete => 'تسک‌های دیگران را هم می‌تواند حذف کند.',
            self::CommentsCreate => 'کامنت می‌گذارد و کامنت‌های خودش را ویرایش یا حذف می‌کند.',
            self::ClientsManage => 'مشتری‌ها را می‌بیند، می‌سازد و ویرایش می‌کند.',
            self::ReportsViewAll => 'گزارش‌ها همه‌ی پروژه‌ها را شامل می‌شود، نه فقط پروژه‌های خودش.',
            self::UsersManage => 'کاربر می‌سازد، نقش می‌دهد، غیرفعال می‌کند و نقش‌ها را مدیریت می‌کند.',
        };
    }

    /** گروه برای نمایش در ماتریس تیک‌ها */
    public function group(): string
    {
        return match ($this) {
            self::ProjectsCreate, self::ProjectsViewAll, self::ProjectsArchive => 'پروژه‌ها',
            self::TasksWrite, self::TasksDelete, self::CommentsCreate => 'تسک‌ها',
            self::ClientsManage, self::ReportsViewAll => 'مشتری و گزارش',
            self::UsersManage => 'مدیریت',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_map(fn (self $a) => $a->value, self::cases());
    }

    /** @return array<string,array<int,self>> گروه => دسترسی‌ها */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::cases() as $ability) {
            $groups[$ability->group()][] = $ability;
        }

        return $groups;
    }
}
