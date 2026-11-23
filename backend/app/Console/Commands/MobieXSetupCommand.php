<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MobieXSetupCommand extends Command
{
    protected $signature = 'mobiex:setup
                            {--fresh : Drop and recreate all tables (WARNING: destroys existing data)}
                            {--seed-only : Only run seeders on existing database, skip migration}
                            {--skip-link : Skip storage:link step}';

    protected $description = 'Interactive MobieX project setup: migrate, seed, and configure storage links';

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <fg=cyan;options=bold>███╗   ███╗ ██████╗ ██████╗ ██╗███████╗██╗  ██╗</>');
        $this->line('  <fg=cyan;options=bold>████╗ ████║██╔═══██╗██╔══██╗██║██╔════╝╚██╗██╔╝</>');
        $this->line('  <fg=cyan;options=bold>██╔████╔██║██║   ██║██████╔╝██║█████╗   ╚███╔╝ </>');
        $this->line('  <fg=cyan;options=bold>██║╚██╔╝██║██║   ██║██╔══██╗██║██╔══╝   ██╔██╗ </>');
        $this->line('  <fg=cyan;options=bold>██║ ╚═╝ ██║╚██████╔╝██████╔╝██║███████╗██╔╝ ██╗</>');
        $this->line('  <fg=cyan;options=bold>╚═╝     ╚═╝ ╚═════╝ ╚═════╝ ╚═╝╚══════╝╚═╝  ╚═╝</>');
        $this->newLine();
        $this->line('  <fg=white;options=bold>MobieX E-Commerce Platform — Setup Wizard</>');
        $this->line('  <fg=gray>FPT Polytechnic — Đồ án Tốt nghiệp</>');
        $this->newLine();
        $this->line('  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        // ── Step 1: Check .env ────────────────────────────────────────────
        $this->stepHeader('1', 'Environment Configuration');

        if (! File::exists(base_path('.env'))) {
            $this->warn('  ⚠  .env not found. Copying from .env.example...');
            File::copy(base_path('.env.example'), base_path('.env'));
            $this->call('key:generate');
            $this->info('  ✓  .env created and APP_KEY generated.');
        } else {
            $this->line('  <fg=green>✓</> .env already exists.');

            if (empty(config('app.key'))) {
                $this->warn('  ⚠  APP_KEY is empty. Generating...');
                $this->call('key:generate');
            }
        }

        // ── Step 2: Database ──────────────────────────────────────────────
        $this->stepHeader('2', 'Database Setup');

        if (! $this->checkDatabaseConnection()) {
            $this->error('  ✗  Cannot connect to database. Check DB_* settings in .env');
            $this->line('  <fg=gray>  → DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD</>');

            return self::FAILURE;
        }
        $this->line('  <fg=green>✓</> Database connection OK.');

        if ($this->option('fresh')) {
            if (! $this->confirm('  ⚠  --fresh will DROP all tables and DELETE all data. Continue?', false)) {
                $this->line('  Cancelled.');

                return self::SUCCESS;
            }
            $this->info('  Running migrate:fresh --seed...');
            $this->call('migrate:fresh', ['--seed' => true]);
        } elseif ($this->option('seed-only')) {
            $this->info('  Running db:seed...');
            $this->call('db:seed');
        } else {
            $this->info('  Running migrate --seed...');
            $this->call('migrate', ['--seed' => true]);
        }

        // ── Step 3: Storage Link ──────────────────────────────────────────
        $this->stepHeader('3', 'Storage Configuration');

        if (! $this->option('skip-link')) {
            $publicLink = public_path('storage');

            if (is_link($publicLink) || File::exists($publicLink)) {
                $this->line('  <fg=green>✓</> public/storage symlink already exists.');
            } else {
                $this->call('storage:link');
            }
        }

        // ── Step 4: Cache ─────────────────────────────────────────────────
        $this->stepHeader('4', 'Clearing Cache');
        $this->call('config:clear');
        $this->call('cache:clear');
        $this->line('  <fg=green>✓</> Cache cleared.');

        // ── Summary ───────────────────────────────────────────────────────
        $this->newLine();
        $this->line('  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('  <fg=green;options=bold>✓  MobieX setup completed successfully!</>');
        $this->newLine();
        $this->line('  <fg=white;options=bold>Admin account:</>');
        $this->line('  <fg=gray>  Configure RBAC_ADMIN_NAME, RBAC_ADMIN_EMAIL, and RBAC_ADMIN_PASSWORD locally.</>');
        $this->line('  <fg=gray>  Seeded demo customer accounts do not have a known password.</>');
        $this->newLine();
        $this->line('  <fg=white;options=bold>Start the development servers:</>');
        $this->line('  <fg=gray>  Backend:  php artisan serve          → http://127.0.0.1:8000</>');
        $this->line('  <fg=gray>  Frontend: cd ../frontend && npm run dev → http://localhost:5173</>');
        $this->newLine();

        return self::SUCCESS;
    }

    private function stepHeader(string $step, string $title): void
    {
        $this->newLine();
        $this->line("  <fg=cyan;options=bold>Step {$step}:</> <fg=white;options=bold>{$title}</>");
        $this->line('  '.str_repeat('─', 50));
    }

    private function checkDatabaseConnection(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
