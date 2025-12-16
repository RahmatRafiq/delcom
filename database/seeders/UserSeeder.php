<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]
        );

        // Create regular user
        $user = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Regular User',
                'email' => 'user@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
            ]
        );

        // Assign roles
        $adminRole = Role::where('name', 'admin')->first();
        $userRole = Role::where('name', 'user')->first();

        if ($adminRole) {
            $admin->assignRole($adminRole);
        }

        if ($userRole) {
            $user->assignRole($userRole);
        }

        // Ensure all users have a free subscription (for existing users from firstOrCreate)
        $this->ensureFreeSubscription($admin);
        $this->ensureFreeSubscription($user);
    }

    /**
     * Ensure user has a free subscription if they don't have any active subscription.
     */
    private function ensureFreeSubscription(User $user): void
    {
        // Skip if user already has an active subscription
        if ($user->subscription()->exists()) {
            return;
        }

        $freePlan = Plan::free();
        if ($freePlan) {
            $user->allSubscriptions()->create([
                'plan_id' => $freePlan->id,
                'status' => 'active',
                'billing_cycle' => 'free',
                'current_period_start' => now(),
                'current_period_end' => null,
            ]);
        }
    }
}
