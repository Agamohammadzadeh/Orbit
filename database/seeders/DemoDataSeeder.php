<?php

namespace Database\Seeders;

use App\Enums\LinkType;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\ChecklistItem;
use App\Models\Client;
use App\Models\Comment;
use App\Models\Label;
use App\Models\Link;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Database\Factories\CommentFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * داده‌ی نمونه برای توسعه: ۶ کاربر، ۴ مشتری، ۷ پروژه و حدود ۱۲۰ تسک
 * همراه با چک‌لیست، کامنت (با منشن)، ثبت زمان، لینک و برچسب.
 *
 * فقط یک‌بار اجرا می‌شود؛ برای ساخت دوباره: php artisan migrate:fresh --seed
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolePermissionSeeder::class, LabelSeeder::class, AdminUserSeeder::class]);

        if (Project::query()->withTrashed()->exists()) {
            $this->command?->warn('داده‌ی نمونه قبلاً ساخته شده؛ رد شد. (migrate:fresh --seed برای ساخت دوباره)');

            return;
        }

        // برای اینکه ده‌ها لاگ «ساخته شد» با زمان الان تاریخچه را شلوغ نکند
        activity()->disableLogging();

        try {
            DB::transaction(fn () => $this->seedDemo());
        } finally {
            activity()->enableLogging();
        }
    }

    private function seedDemo(): void
    {
        $admin = User::role(UserRole::Admin->value)->firstOrFail();
        $labels = Label::query()->get();

        // ---------- تیم ----------
        $team = collect([
            ['سارا احمدی', 'sara@orbit.test'],
            ['رضا کریمی', 'reza@orbit.test'],
            ['مریم حسینی', 'maryam@orbit.test'],
            ['علی رستمی', 'ali@orbit.test'],
            ['نیلوفر صادقی', 'niloufar@orbit.test'],
        ])->map(fn (array $u) => User::factory()->create(['name' => $u[0], 'email' => $u[1]]));

        // عضو غیرفعال (برای تست فیلتر و نمایش)
        User::factory()->inactive()->create(['name' => 'پویا نوری', 'email' => 'pooya@orbit.test']);

        // دو کاربر با نقش‌های سفارشی برای تست دسترسی‌ها (نام نقش‌ها از config('orbit.default_roles'))
        $manager = User::factory()->withRole('مدیر پروژه')->create(['name' => 'کیان مرادی', 'email' => 'pm@orbit.test']);
        $viewer = User::factory()->withRole('ناظر')->create(['name' => 'ناظر نمونه', 'email' => 'viewer@orbit.test']);

        $clients = Client::factory()->count(4)->create();

        // ---------- پروژه‌ها ----------
        $specs = [
            ['name' => 'سایت شرکتی آریان', 'slug' => 'arian-website', 'template' => 'custom_website', 'color' => '#5B5BD6'],
            ['name' => 'فروشگاه آنلاین مهتاب', 'slug' => 'mahtab-shop', 'template' => 'custom_website', 'color' => '#12A594'],
            ['name' => 'لندینگ کمپین یلدا', 'slug' => 'yalda-landing', 'template' => 'landing_page', 'color' => '#D6409F'],
            ['name' => 'بازطراحی پرتال سپهر', 'slug' => 'sepehr-redesign', 'template' => 'redesign', 'color' => '#3E8EF7'],
            ['name' => 'پشتیبانی سایت‌های مشتریان', 'slug' => 'client-support', 'template' => 'maintenance', 'color' => '#F5A524'],
            ['name' => 'وب‌سایت کلینیک سلامت نو', 'slug' => 'salamat-clinic', 'template' => 'custom_website', 'color' => '#FF7A59', 'state' => 'done'],
            ['name' => 'سایت قدیمی املاک بهار', 'slug' => 'bahar-old-site', 'template' => 'custom_website', 'color' => '#8B8BA3', 'state' => 'archived'],
        ];

        foreach ($specs as $i => $spec) {
            $state = $spec['state'] ?? 'active';

            $factory = Project::factory();
            if ($state === 'done') {
                $factory = $factory->done();
            } elseif ($state === 'archived') {
                $factory = $factory->archived();
            }

            $project = $factory->create([
                'name' => $spec['name'],
                'slug' => $spec['slug'],
                'color' => $spec['color'],
                'client_id' => $clients[$i % $clients->count()]->id,
                'owner_id' => $admin->id,
            ]);

            // ---------- اعضا ----------
            $members = $team->shuffle()->take(3)->push($admin)->values();
            $project->members()->attach($members->pluck('id')->all());

            // مدیر پروژه در همه‌ی پروژه‌ها عضو است، ناظر فقط در دو پروژه‌ی اول
            // (این دو در $members نیستند، پس تسکی به آن‌ها اختصاص داده نمی‌شود)
            $project->members()->attach($manager->id);
            if ($i < 2) {
                $project->members()->attach($viewer->id);
            }

            // ---------- مراحل از قالب ----------
            $phases = collect(config("orbit.phase_templates.{$spec['template']}.phases", []))
                ->map(fn (string $name, int $pos) => Phase::query()->create([
                    'project_id' => $project->id,
                    'name' => $name,
                    'position' => $pos + 1,
                ]));

            // ---------- لینک‌های پروژه ----------
            Link::factory()->figma()->attachedTo($project)->create();
            Link::factory()->repo()->attachedTo($project)->create();
            if ($state === 'active') {
                Link::factory()->staging()->attachedTo($project)->create();
            }

            // ---------- اسپرینت‌ها ----------
            $sprints = collect();
            if ($state === 'active') {
                $sprints->push(Sprint::factory()->closed()->create(['project_id' => $project->id, 'name' => 'اسپرینت ۱']));
                $sprints->push(Sprint::factory()->active()->create(['project_id' => $project->id, 'name' => 'اسپرینت ۲']));
                $sprints->push(Sprint::factory()->create(['project_id' => $project->id, 'name' => 'اسپرینت ۳']));
            } else {
                $sprints->push(Sprint::factory()->closed()->create(['project_id' => $project->id, 'name' => 'اسپرینت ۱']));
            }

            // ---------- تسک‌ها ----------
            $count = $state === 'active' ? random_int(18, 26) : 12;

            for ($n = 0; $n < $count; $n++) {
                $this->seedTask($project, $members, $phases, $sprints, $labels, $state !== 'active');
            }
        }

        // چند تسک عقب‌افتاده‌ی قطعی برای گزارش‌ها (روی اولین پروژه)
        $first = Project::query()->where('slug', 'arian-website')->first();
        $firstMembers = $first->members()->get(); // در strict mode lazy load ممنوع است
        Task::factory()->count(3)->overdue()->create([
            'project_id' => $first->id,
            'creator_id' => $admin->id,
            'assignee_id' => $firstMembers->random()->id,
        ]);

        $this->command?->info('داده‌ی نمونه ساخته شد.');
        $this->command?->table(['ایمیل', 'رمز', 'نقش'], [
            [config('orbit.admin.email'), config('orbit.admin.password') ?: 'password', 'مدیر'],
            ['sara@orbit.test (و بقیه‌ی اعضا)', 'password', 'عضو تیم'],
            ['pm@orbit.test', 'password', 'مدیر پروژه'],
            ['viewer@orbit.test', 'password', 'ناظر (فقط‌خواندنی)'],
        ]);
    }

    /**
     * @param  Collection<int,User>  $members
     * @param  Collection<int,Phase>  $phases
     * @param  Collection<int,Sprint>  $sprints
     * @param  Collection<int,Label>  $labels
     */
    private function seedTask(Project $project, Collection $members, Collection $phases, Collection $sprints, Collection $labels, bool $allDone): void
    {
        $factory = $allDone ? Task::factory()->done() : Task::factory();

        $task = $factory->create([
            'project_id' => $project->id,
            'phase_id' => $phases->isNotEmpty() ? $phases->random()->id : null,
            'sprint_id' => ($sprints->isNotEmpty() && fake()->boolean(70)) ? $sprints->random()->id : null,
            'creator_id' => $members->random()->id,
            'assignee_id' => fake()->boolean(85) ? $members->random()->id : null,
        ]);

        // برچسب‌ها
        $task->labels()->attach($labels->random(random_int(1, 2))->pluck('id')->all());

        // چک‌لیست
        if (fake()->boolean(40)) {
            $items = random_int(3, 5);
            for ($p = 1; $p <= $items; $p++) {
                ChecklistItem::factory()->create([
                    'task_id' => $task->id,
                    'position' => $p,
                    'is_done' => $task->status === TaskStatus::Done || fake()->boolean(40),
                ]);
            }
        }

        // کامنت‌ها (بعضی با منشن)
        if (fake()->boolean(55)) {
            for ($c = random_int(1, 4); $c > 0; $c--) {
                $author = $members->random();
                $body = fake()->randomElement(CommentFactory::BODIES);

                if (fake()->boolean(30) && $members->count() > 1) {
                    $mentioned = $members->where('id', '!=', $author->id)->random();
                    $body = "@[user:{$mentioned->id}] ".$body;
                }

                $at = fake()->dateTimeBetween($task->created_at->toDateTimeString(), 'now');

                Comment::factory()->create([
                    'task_id' => $task->id,
                    'user_id' => $author->id,
                    'body' => $body,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
            }
        }

        // ثبت زمان برای تسک‌هایی که کار رویشان شروع شده
        if ($task->status !== TaskStatus::Todo) {
            for ($t = random_int(1, 3); $t > 0; $t--) {
                TimeEntry::factory()->create([
                    'task_id' => $task->id,
                    'user_id' => $task->assignee_id ?? $members->random()->id,
                ]);
            }
        }

        // لینک روی تسک (Figma یا ریپو)
        if (fake()->boolean(15)) {
            $link = fake()->boolean() ? Link::factory()->figma() : Link::factory()->repo();
            $link->attachedTo($task)->create();
        }
    }
}
