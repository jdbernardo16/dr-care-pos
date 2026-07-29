<?php

namespace Database\Seeders;

use App\Events\UserAfterActivationSuccessfulEvent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAttribute;
use App\Services\WidgetService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run()
    {
        $authorId = User::where('username', 'admin')->value('id') ?? 1;

        $this->createOrUpdateUser('admin', 'admin@drcare.test', 'admin', $authorId);
        $this->createOrUpdateUser('cashier', 'cashier@drcare.test', 'nexopos.store.cashier', $authorId);
        $this->createOrUpdateUser('renzoOng', 'renzoong@drcare.test', 'nexopos.store.cashier', $authorId);

        $this->createDeveloperRole();
        $this->createOrUpdateUser('developer', 'developer@drcare.test', 'nexopos.developer', $authorId);
    }

    private function createOrUpdateUser(string $username, string $email, string $roleNamespace, int $authorId): User
    {
        $user = User::where('username', $username)->first();

        if (!$user) {
            $user = new User;
            $user->username = $username;
            $user->email = $email;
            $user->password = Hash::make('password');
            $user->active = true;
            $user->author_id = $authorId;
            $user->save();
            $user->assignRole($roleNamespace);
            $user->attribute()->create(['language' => 'en']);
            app(WidgetService::class)->addDefaultWidgetsToAreas($user);
            $this->command->info("Created user: {$username}");
        } else {
            $user->password = Hash::make('password');
            $user->save();
            $this->command->info("Updated password for: {$username}");
        }

        if ( ! $user->attribute()->exists() ) {
            $user->attribute()->create(['language' => 'en']);
            $this->command->info("Created missing attribute for: {$username}");
        }

        return $user;
    }

    private function createDeveloperRole(): void
    {
        $role = Role::firstOrNew(['namespace' => 'nexopos.developer']);
        $role->name = 'Developer';
        $role->namespace = 'nexopos.developer';
        $role->locked = false;
        $role->description = 'Has all permissions for development purposes.';
        $role->save();

        $allPermissions = Permission::all()->pluck('namespace');
        $role->addPermissions($allPermissions);

        $this->command->info('Developer role created with all permissions.');
    }
}
