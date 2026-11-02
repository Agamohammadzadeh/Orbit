<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

class RichText
{
    /**
     * «@نام کامل» را به توکن @[user:ID] تبدیل می‌کند (فقط برای اعضای داده‌شده).
     * اسم‌های بلندتر اول جایگزین می‌شوند تا «علی» جلوی «علی رستمی» را نگیرد.
     *
     * @param  iterable<object>  $members  اشیایی با id و name
     */
    public static function toTokens(string $text, iterable $members): string
    {
        $users = collect($members)->sortByDesc(fn ($u) => mb_strlen($u->name));

        foreach ($users as $u) {
            $text = preg_replace(
                '/@'.preg_quote($u->name, '/').'(?=$|[\s\p{P}])/u',
                '@[user:'.$u->id.']',
                $text
            ) ?? $text;
        }

        return $text;
    }

    /** توکن‌ها را برای ویرایش به «@نام» برمی‌گرداند. $names = [id => نام] */
    public static function toNames(string $stored, array $names): string
    {
        return preg_replace_callback(
            config('orbit.mentions.pattern'),
            fn ($m) => '@'.($names[(int) $m[1]] ?? 'کاربر'),
            $stored
        ) ?? $stored;
    }

    /** متن را HTML امن می‌کند: منشن، لینک http(s) و خط جدید. */
    public static function render(?string $raw, array $names = []): HtmlString
    {
        $html = e((string) $raw);

        $html = preg_replace_callback(
            config('orbit.mentions.pattern'),
            fn ($m) => '<span class="mention">@'.e($names[(int) $m[1]] ?? 'کاربر').'</span>',
            $html
        ) ?? $html;

        $html = preg_replace_callback('~https?://[^\s<]+~u', function ($m) {
            $url = $m[0];
            $trail = '';

            if (preg_match('~[.,;:!?)،؛]+$~u', $url, $t)) {
                $trail = $t[0];
                $url = substr($url, 0, -strlen($trail));
            }

            return '<a href="'.$url.'" target="_blank" rel="noopener noreferrer">'.$url.'</a>'.$trail;
        }, $html) ?? $html;

        return new HtmlString(nl2br($html));
    }
}
