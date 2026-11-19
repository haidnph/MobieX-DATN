<?php

namespace App\Console\Commands;

use App\Models\Banner;
use App\Models\ProductImage;
use App\Services\MediaStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateMediaToCloudinaryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:migrate-cloudinary
                            {--run : Actually perform the migration (default is dry-run mode)}
                            {--type=all : Resource types to migrate: all, products, banners}
                            {--limit=0 : Maximum number of items to migrate (0 = no limit)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely migrate local media assets (products, banners) to Cloudinary';

    /**
     * Execute the console command.
     */
    public function handle(MediaStorageService $mediaService): int
    {
        $isRun = (bool) $this->option('run');
        $type = strtolower((string) $this->option('type'));
        $limit = (int) $this->option('limit');

        $this->info('=====================================================');
        $this->info(' MobieX Cloud Storage Migration: Local -> Cloudinary ');
        $this->info('=====================================================');
        $this->line('Mode: '.($isRun ? '<fg=yellow;options=bold>LIVE EXECUTION</>' : '<fg=cyan;options=bold>DRY RUN (Simulation)</>'));
        $this->line('Type filter: '.$type);
        if ($limit > 0) {
            $this->line('Item limit: '.$limit);
        }
        $this->newLine();

        if ($isRun && ! $mediaService->isCloudinaryConfigured()) {
            $this->error('Cloudinary is not configured. Please set CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, and CLOUDINARY_API_SECRET in your .env file.');
            return self::FAILURE;
        }

        $items = [];

        // 1. Scan Product Images
        if (in_array($type, ['all', 'products', 'product'])) {
            $query = ProductImage::query()
                ->where(function ($q) {
                    $q->whereNull('storage_provider')
                      ->orWhere('storage_provider', '!=', 'cloudinary');
                });

            if ($limit > 0) {
                $query->limit($limit);
            }

            foreach ($query->get() as $img) {
                $rawPath = $img->getRawOriginal('image_path') ?: $img->image_url;
                if (empty($rawPath) || str_starts_with($rawPath, 'http://') || str_starts_with($rawPath, 'https://')) {
                    continue;
                }

                $normalized = $mediaService->normalizeLocalPath($rawPath);
                $fullDiskPath = Storage::disk('public')->path($normalized);
                $exists = file_exists($fullDiskPath);

                $items[] = [
                    'model' => 'ProductImage',
                    'id' => $img->id,
                    'record' => $img,
                    'raw_path' => $rawPath,
                    'disk_path' => $normalized,
                    'full_path' => $fullDiskPath,
                    'exists' => $exists,
                    'folder' => 'products',
                    'size' => $exists ? filesize($fullDiskPath) : 0,
                ];
            }
        }

        // 2. Scan Banners
        if (in_array($type, ['all', 'banners', 'banner'])) {
            $bannerQuery = Banner::query()
                ->where(function ($q) {
                    $q->whereNull('storage_provider')
                      ->orWhere('storage_provider', '!=', 'cloudinary');
                });

            if ($limit > 0 && count($items) >= $limit) {
                // already at limit
            } else {
                foreach ($bannerQuery->get() as $banner) {
                    $rawPath = $banner->image_path;
                    if (! empty($rawPath) && ! str_starts_with($rawPath, 'http://') && ! str_starts_with($rawPath, 'https://')) {
                        $normalized = $mediaService->normalizeLocalPath($rawPath);
                        $fullDiskPath = Storage::disk('public')->path($normalized);
                        $exists = file_exists($fullDiskPath);

                        $items[] = [
                            'model' => 'Banner',
                            'id' => $banner->id,
                            'record' => $banner,
                            'raw_path' => $rawPath,
                            'disk_path' => $normalized,
                            'full_path' => $fullDiskPath,
                            'exists' => $exists,
                            'folder' => 'banners',
                            'size' => $exists ? filesize($fullDiskPath) : 0,
                        ];
                    }
                }
            }
        }

        if (empty($items)) {
            $this->info('No local media items found requiring migration to Cloudinary.');
            return self::SUCCESS;
        }

        $tableData = [];
        $totalBytes = 0;
        foreach ($items as $item) {
            $totalBytes += $item['size'];
            $tableData[] = [
                $item['model'],
                $item['id'],
                $item['disk_path'],
                $item['exists'] ? sprintf('%.1f KB', $item['size'] / 1024) : '<fg=red>MISSING</>',
                $item['folder'],
                $item['exists'] ? '<fg=green>Ready</>' : '<fg=red>Skip</>',
            ];
        }

        $this->table(
            ['Entity', 'ID', 'Local Path', 'Size', 'Cloud Folder', 'Status'],
            $tableData
        );

        $this->line(sprintf('Found <fg=yellow>%d</> candidate assets (Total: <fg=yellow>%.2f MB</>).', count($items), $totalBytes / (1024 * 1024)));

        if (! $isRun) {
            $this->newLine();
            $this->warn('DRY RUN COMPLETE. No assets were uploaded or modified in database.');
            $this->line('To perform actual migration, run: <fg=green>php artisan media:migrate-cloudinary --run</>');
            return self::SUCCESS;
        }

        // Live execution
        $migrated = 0;
        $failed = 0;
        $skipped = 0;

        $progressBar = $this->output->createProgressBar(count($items));
        $progressBar->start();

        foreach ($items as $item) {
            if (! $item['exists']) {
                $skipped++;
                $progressBar->advance();
                continue;
            }

            try {
                $result = $mediaService->upload(
                    $item['full_path'],
                    $item['folder'],
                    ['driver' => 'cloudinary']
                );

                if ($result['provider'] === 'cloudinary') {
                    $record = $item['record'];
                    if ($item['model'] === 'ProductImage') {
                        $record->storage_provider = 'cloudinary';
                        $record->public_id = $result['public_id'];
                        $record->image_path = $result['public_id'];
                        $record->save();
                    } elseif ($item['model'] === 'Banner') {
                        $record->storage_provider = 'cloudinary';
                        $record->public_id = $result['public_id'];
                        $record->image_path = $result['url'];
                        $record->save();
                    }
                    $migrated++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->error("\nFailed to migrate item {$item['id']}: {$e->getMessage()}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Migration completed: {$migrated} succeeded, {$failed} failed, {$skipped} skipped.");
        $this->line('Notice: Local files have been preserved as backup.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
