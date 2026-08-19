<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Админ хэрэглэгч үүсгэх / байгаа хэрэглэгчийг админ болгох.
 *
 *   php artisan admin:create 99112233
 *   php artisan admin:create 99112233 --name="Бат" --password=secret123
 */
class CreateAdmin extends Command
{
    protected $signature = 'admin:create
        {phone : 8 оронтой утасны дугаар}
        {--name= : Нэр (шинэ хэрэглэгчид, анхдагч: Админ)}
        {--password= : Нууц үг (өгөөгүй бол шинэ хэрэглэгчид санамсаргүй үүсгэнэ)}
        {--demote : Админ эрхийг буцаах}';

    protected $description = 'Админ хэрэглэгч үүсгэх эсвэл байгаа хэрэглэгчид админ эрх олгох';

    public function handle(): int
    {
        $phone = (string) $this->argument('phone');

        if (! preg_match('/^[0-9]{8}$/', $phone)) {
            $this->error('Утасны дугаар 8 оронтой тоо байх ёстой.');

            return self::FAILURE;
        }

        $user = User::where('phone', $phone)->first();

        if ($this->option('demote')) {
            if ($user === null) {
                $this->error("{$phone} дугаартай хэрэглэгч олдсонгүй.");

                return self::FAILURE;
            }

            $user->forceFill(['is_admin' => false])->save();
            $this->info("{$user->name} ({$phone}) — админ эрх хасагдлаа.");

            return self::SUCCESS;
        }

        $generatedPassword = null;

        if ($user === null) {
            $password = $this->option('password');

            if (! $password) {
                $generatedPassword = Str::password(16, symbols: false);
                $password = $generatedPassword;
            }

            $user = User::create([
                'name' => $this->option('name') ?: 'Админ',
                'phone' => $phone,
                'password' => $password,
            ]);

            // CLI-ээр үүсгэсэн админд SMS баталгаажуулалт шаардахгүй
            $user->forceFill(['is_admin' => true, 'phone_verified_at' => now()])->save();

            $this->info("Шинэ админ үүслээ: {$user->name} ({$phone})");

            if ($generatedPassword !== null) {
                $this->warn("Нууц үг (нэг л удаа харагдана): {$generatedPassword}");
            }

            return self::SUCCESS;
        }

        $updates = ['is_admin' => true];

        if ($user->phone_verified_at === null) {
            $updates['phone_verified_at'] = now();
        }

        if ($this->option('password')) {
            $updates['password'] = $this->option('password');
        }

        if ($this->option('name')) {
            $updates['name'] = $this->option('name');
        }

        $user->forceFill($updates)->save();

        $this->info("{$user->name} ({$phone}) — админ эрх олгогдлоо.");

        return self::SUCCESS;
    }
}
