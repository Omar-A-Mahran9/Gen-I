<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'app:create-admin')]
class CreateAdminCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:create-admin
        {--name= : Full name of the administrator}
        {--email= : Sign-in email address}
        {--password-from-env : Read the password from the GENI_ADMIN_PASSWORD process environment variable instead of prompting}';

    /**
     * @var string
     */
    protected $description = 'Create a GEN-I super administrator account (password is prompted securely, never stored in plain text or logged)';

    public function handle(AuditLogger $audit): int
    {
        $superAdminRole = config('admin.super_admin_role');

        if (! Role::query()->where('name', $superAdminRole)->where('guard_name', 'web')->exists()) {
            $this->components->info('Seeding roles and permissions first…');
            $this->callSilently('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
        }

        $name = trim((string) ($this->option('name') ?: $this->ask('Full name')));
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Email address'))));

        if (User::query()->where('email', $email)->exists()) {
            $this->components->error("A user with the email [{$email}] already exists. No account was created.");

            return self::FAILURE;
        }

        if ($this->option('password-from-env')) {
            $password = (string) getenv('GENI_ADMIN_PASSWORD');
            $confirmation = $password;
        } else {
            $password = (string) $this->secret('Password (input hidden)');
            $confirmation = (string) $this->secret('Confirm password');
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
                'password' => ['required', 'string', 'confirmed', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($name, $email, $password, $superAdminRole, $audit): User {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'is_active' => true,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole($superAdminRole);

            $audit->log('user.created', $user, ['email' => $email, 'roles' => [$superAdminRole], 'source' => 'console'], null);

            return $user;
        });

        $this->components->info("Super administrator [{$user->email}] created.");
        $this->components->twoColumnDetail('Sign in at', route('admin.login'));

        return self::SUCCESS;
    }
}
