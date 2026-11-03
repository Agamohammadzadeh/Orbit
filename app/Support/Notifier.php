<?php

namespace App\Support;

use App\Mail\OrbitNotificationMail;
use App\Models\User;
use App\Notifications\OrbitNotification;
use Illuminate\Support\Facades\Mail;
use Throwable;

class Notifier
{
    /**
     * ارسال اعلان طبق ترجیح هر کاربر: درون‌برنامه (همان لحظه) و ایمیل (توی صف).
     * خطای ایمیل فقط گزارش می‌شود و کار اصلی را خراب نمی‌کند.
     *
     * @param  User|iterable<User>  $users
     */
    public static function send(User|iterable $users, OrbitNotification $notification): void
    {
        $users = $users instanceof User ? [$users] : $users;

        foreach ($users as $user) {
            if (! $user->is_active) {
                continue;
            }

            $type = $notification->type();

            if (NotificationPreferences::wants($user, $type, 'app')) {
                $user->notify($notification);
            }

            if ($user->email && NotificationPreferences::wants($user, $type, 'email')) {
                try {
                    Mail::to($user->email)->queue(new OrbitNotificationMail(
                        mailSubject: config('app.name').': '.$type->label(),
                        text: $notification->message(),
                        url: url($notification->path()),
                    ));
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }
    }
}
