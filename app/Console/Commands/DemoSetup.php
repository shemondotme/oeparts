<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DemoSetup extends Command
{
    protected $signature = 'demo:setup
                            {--fresh : Drop all tables and re-run migrations}
                            {--seed : Seed demo data after setup}
                            {--yes : Skip confirmation prompts}';

    protected $description = 'Set up a demo instance of OeParts with sample data';

    public function handle(): int
    {
        $this->info('╔══════════════════════════════════════════════════════════╗');
        $this->info('║                OeParts Demo Setup                         ║');
        $this->info('╚══════════════════════════════════════════════════════════╝');

        if (file_exists(storage_path('installed.lock')) && ! $this->option('fresh')) {
            $this->warn('The application appears to be already installed.');
            if (! $this->option('yes') && ! $this->confirm('Do you want to reset everything? This will delete all existing data.', false)) {
                $this->info('Demo setup cancelled.');

                return 0;
            }
        }

        $fresh = $this->option('fresh') || $this->option('yes');

        $this->step('Running migrations...');
        $migrateCommand = $fresh ? 'migrate:fresh' : 'migrate';
        $this->call($migrateCommand, ['--force' => true]);
        $this->line('  <info>✓</info> Database migrated.');

        $this->step('Seeding core data...');
        $coreSeeders = [
            'Database\\Seeders\\SettingsSeeder',
            'Database\\Seeders\\LanguagesSeeder',
            'Database\\Seeders\\RolesSeeder',
            'Database\\Seeders\\SequencesSeeder',
            'Database\\Seeders\\CarriersSeeder',
            'Database\\Seeders\\SectionsSeeder',
        ];

        foreach ($coreSeeders as $seeder) {
            $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
            $this->line("  <info>✓</info> {$seeder}");
        }

        $this->step('Creating admin user...');
        if (Schema::hasTable('admins')) {
            $admin = Admin::updateOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'name' => 'Demo Admin',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );
            $admin->assignRole('super_admin');
            $this->line('  <info>✓</info> Admin user created (admin@example.com / password).');
        }

        $seedDemo = $this->option('seed') || $this->option('yes');
        if ($seedDemo) {
            $this->step('Seeding demo data...');
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\DemoDataSeeder', '--force' => true]);
            $this->line('  <info>✓</info> Demo products, manufacturers, blog posts, etc.');
        }

        $this->step('Finalizing setup...');
        file_put_contents(storage_path('installed.lock'), 'Demo instance created at '.now()->toDateTimeString());
        $this->call('view:clear');
        $this->line('  <info>✓</info> Setup finalized.');

        $this->newLine();
        $this->info('╔══════════════════════════════════════════════════════════╗');
        $this->info('║                    SETUP COMPLETE                        ║');
        $this->info('╚══════════════════════════════════════════════════════════╝');
        $this->newLine();

        $this->table(['Access', 'URL / Credentials'], [
            ['Frontend', url('/')],
            ['Admin Panel', route('filament.admin.auth.login')],
            ['Admin Login', 'admin@example.com / password'],
            ['Demo Customer', 'customer@example.com / password'],
            ['Demo Manager', 'manager@example.com / password'],
        ]);

        $this->newLine();
        $this->line('  <comment>Note:</comment> The installer is now disabled. To re‑enable it, delete:');
        $this->line('        <comment>storage/installed.lock</comment>');
        $this->newLine();

        return 0;
    }

    /**
     * Output a step header.
     */
    private function step(string $message): void
    {
        $this->newLine();
        $this->line("  <fg=blue>➤</> {$message}");
    }
}
