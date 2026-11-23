<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaStorageService
{
    protected string $driver;

    protected ?Cloudinary $cloudinary = null;

    public function __construct(?string $driver = null)
    {
        $this->driver = $driver ?? config('media.default', 'local');
    }

    /**
     * Get the active storage driver name.
     */
    public function getDriver(): string
    {
        return $this->driver;
    }

    /**
     * Dynamically switch active driver.
     */
    public function setDriver(string $driver): self
    {
        $this->driver = $driver;

        return $this;
    }

    /**
     * Check if Cloudinary credentials are fully configured.
     */
    public function isCloudinaryConfigured(): bool
    {
        $url = config('media.drivers.cloudinary.url');
        if (! empty($url)) {
            return true;
        }

        $cloudName = config('media.drivers.cloudinary.cloud_name');
        $apiKey = config('media.drivers.cloudinary.api_key');
        $apiSecret = config('media.drivers.cloudinary.api_secret');

        return ! empty($cloudName) && ! empty($apiKey) && ! empty($apiSecret);
    }

    /**
     * Get or instantiate Cloudinary client.
     */
    public function getCloudinaryClient(): Cloudinary
    {
        if ($this->cloudinary !== null) {
            return $this->cloudinary;
        }

        $url = config('media.drivers.cloudinary.url');
        if (! empty($url)) {
            $this->cloudinary = new Cloudinary($url);

            return $this->cloudinary;
        }

        $config = [
            'cloud' => [
                'cloud_name' => config('media.drivers.cloudinary.cloud_name'),
                'api_key' => config('media.drivers.cloudinary.api_key'),
                'api_secret' => config('media.drivers.cloudinary.api_secret'),
            ],
            'url' => [
                'secure' => (bool) config('media.drivers.cloudinary.secure', true),
            ],
        ];

        $this->cloudinary = new Cloudinary($config);

        return $this->cloudinary;
    }

    /**
     * Set a custom Cloudinary client instance (useful for unit tests / mocking).
     */
    public function setCloudinaryClient(Cloudinary $client): self
    {
        $this->cloudinary = $client;

        return $this;
    }

    /**
     * Upload a file to active storage (Local or Cloudinary).
     *
     * @param  UploadedFile|string  $file  UploadedFile instance or local file path
     * @param  string  $folder  Target directory/subfolder (e.g. 'products', 'banners/2026/11')
     * @param  array  $options  Additional options (e.g. 'filename', 'primary', 'driver')
     * @return array Standardized media metadata array
     */
    public function upload(UploadedFile|string $file, string $folder = 'uploads', array $options = []): array
    {
        $driver = $options['driver'] ?? $this->driver;

        // Extract file details
        $originalName = $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($file);
        $extension = $file instanceof UploadedFile
            ? ($file->getClientOriginalExtension() ?: 'webp')
            : (pathinfo($file, PATHINFO_EXTENSION) ?: 'webp');
        $size = $file instanceof UploadedFile ? $file->getSize() : filesize($file);
        $mimeType = $file instanceof UploadedFile
            ? ($file->getMimeType() ?: 'image/jpeg')
            : (mime_content_type($file) ?: 'image/jpeg');

        if ($driver === 'cloudinary') {
            if (! $this->isCloudinaryConfigured()) {
                if (config('media.fallback_to_local', true)) {
                    Log::warning('[MediaStorageService] Cloudinary credentials missing; falling back to local storage.');

                    return $this->uploadLocal($file, $folder, $options, $originalName, $extension, $size, $mimeType);
                }
                throw new \InvalidArgumentException('Cloudinary credentials are not configured.');
            }

            try {
                return $this->uploadCloudinary($file, $folder, $options, $originalName, $size, $mimeType);
            } catch (\Throwable $e) {
                if (config('media.fallback_to_local', true)) {
                    Log::error('[MediaStorageService] Cloudinary upload failed: '.$e->getMessage().'; falling back to local disk.');

                    return $this->uploadLocal($file, $folder, $options, $originalName, $extension, $size, $mimeType);
                }
                throw $e;
            }
        }

        return $this->uploadLocal($file, $folder, $options, $originalName, $extension, $size, $mimeType);
    }

    /**
     * Store file on local public storage disk.
     */
    protected function uploadLocal(
        UploadedFile|string $file,
        string $folder,
        array $options,
        string $originalName,
        string $extension,
        int $size,
        string $mimeType
    ): array {
        $subFolder = trim($folder, '/');
        $fileName = $options['filename'] ?? (Str::random(32).'.'.$extension);

        if ($file instanceof UploadedFile) {
            $path = $file->storeAs($subFolder, $fileName, 'public');
        } else {
            $path = $subFolder.'/'.$fileName;
            Storage::disk('public')->put($path, file_get_contents($file));
        }

        $fullUrl = asset('storage/'.$path);
        $relativeUrl = '/storage/'.ltrim($path, '/');

        $dimensions = null;
        $realPath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        if ($realPath && file_exists($realPath) && @getimagesize($realPath)) {
            $dimensions = @getimagesize($realPath);
        }

        return [
            'provider' => 'local',
            'disk' => 'public',
            'path' => $path,
            'public_id' => null,
            'url' => $fullUrl,
            'relative_url' => $relativeUrl,
            'name' => $originalName,
            'size' => $size,
            'mime_type' => $mimeType,
            'width' => $dimensions ? $dimensions[0] : null,
            'height' => $dimensions ? $dimensions[1] : null,
        ];
    }

    /**
     * Store file on Cloudinary.
     */
    protected function uploadCloudinary(
        UploadedFile|string $file,
        string $folder,
        array $options,
        string $originalName,
        int $size,
        string $mimeType
    ): array {
        $client = $this->getCloudinaryClient();

        $rootFolder = config('media.drivers.cloudinary.folder', 'mobiex');
        $targetFolder = trim($rootFolder.'/'.trim($folder, '/'), '/');

        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        $uploadOptions = [
            'folder' => $targetFolder,
            'resource_type' => $options['resource_type'] ?? 'auto',
            'use_filename' => true,
            'unique_filename' => true,
            'overwrite' => false,
        ];

        if (! empty($options['public_id'])) {
            $uploadOptions['public_id'] = $options['public_id'];
        }

        $result = $client->uploadApi()->upload($filePath, $uploadOptions);

        $secureUrl = $result['secure_url'] ?? ($result['url'] ?? null);
        $publicId = $result['public_id'] ?? null;
        $width = $result['width'] ?? null;
        $height = $result['height'] ?? null;
        $bytes = $result['bytes'] ?? $size;
        $format = $result['format'] ?? null;

        return [
            'provider' => 'cloudinary',
            'disk' => 'cloudinary',
            'path' => $publicId,
            'public_id' => $publicId,
            'url' => $secureUrl,
            'relative_url' => $secureUrl,
            'name' => $originalName,
            'size' => $bytes,
            'mime_type' => $format ? ('image/'.$format) : $mimeType,
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Delete file from storage (Local or Cloudinary).
     */
    public function delete(?string $pathOrPublicId, ?string $provider = null): bool
    {
        if (empty($pathOrPublicId)) {
            return false;
        }

        $isCloudinary = ($provider === 'cloudinary')
            || str_contains($pathOrPublicId, 'res.cloudinary.com')
            || (str_starts_with($pathOrPublicId, config('media.drivers.cloudinary.folder', 'mobiex').'/'));

        if ($isCloudinary) {
            return $this->deleteCloudinary($pathOrPublicId);
        }

        return $this->deleteLocal($pathOrPublicId);
    }

    /**
     * Delete local file.
     */
    protected function deleteLocal(string $path): bool
    {
        $normalized = $this->normalizeLocalPath($path);

        if (! empty($normalized) && Storage::disk('public')->exists($normalized)) {
            return Storage::disk('public')->delete($normalized);
        }

        return true;
    }

    /**
     * Delete file on Cloudinary.
     */
    protected function deleteCloudinary(string $pathOrUrl): bool
    {
        if (! $this->isCloudinaryConfigured()) {
            Log::warning('[MediaStorageService] Cannot delete Cloudinary asset; credentials missing.');

            return false;
        }

        $publicId = $this->extractPublicId($pathOrUrl);
        if (empty($publicId)) {
            return false;
        }

        try {
            $response = $this->getCloudinaryClient()->uploadApi()->destroy($publicId, ['invalidate' => true]);

            return ($response['result'] ?? '') === 'ok';
        } catch (\Throwable $e) {
            Log::error('[MediaStorageService] Cloudinary delete failed for '.$publicId.': '.$e->getMessage());

            return false;
        }
    }

    /**
     * Extract Cloudinary public_id from a URL or public_id string.
     */
    public function extractPublicId(string $pathOrUrl): string
    {
        if (! str_starts_with($pathOrUrl, 'http://') && ! str_starts_with($pathOrUrl, 'https://')) {
            return $pathOrUrl;
        }

        // e.g. https://res.cloudinary.com/cloud/image/upload/v12345/mobiex/products/sample.jpg
        $parsed = parse_url($pathOrUrl, PHP_URL_PATH);
        if (! $parsed) {
            return $pathOrUrl;
        }

        if (preg_match('#/upload/(?:v\d+/)?(.+?)(?:\.[a-zA-Z0-9]+)?$#', $parsed, $matches)) {
            return $matches[1];
        }

        return basename($parsed);
    }

    /**
     * Resolve fully-qualified or public URL for any asset.
     */
    public function url(string $pathOrPublicId, ?string $provider = null, array $transformations = []): string
    {
        if (empty($pathOrPublicId)) {
            return '';
        }

        if (str_starts_with($pathOrPublicId, 'http://')
            || str_starts_with($pathOrPublicId, 'https://')
            || str_starts_with($pathOrPublicId, 'data:')
            || str_starts_with($pathOrPublicId, 'blob:')) {
            return $pathOrPublicId;
        }

        if ($provider === 'cloudinary' || str_starts_with($pathOrPublicId, config('media.drivers.cloudinary.folder', 'mobiex').'/')) {
            $cloudName = config('media.drivers.cloudinary.cloud_name');
            if (! empty($cloudName)) {
                $transforms = ! empty($transformations)
                    ? implode(',', array_map(fn ($k, $v) => "{$k}_{$v}", array_keys($transformations), $transformations)).'/'
                    : 'f_auto,q_auto/';

                return "https://res.cloudinary.com/{$cloudName}/image/upload/{$transforms}{$pathOrPublicId}";
            }
        }

        if (str_starts_with($pathOrPublicId, '/storage/')) {
            return $pathOrPublicId;
        }

        if (str_starts_with($pathOrPublicId, 'storage/')) {
            return '/'.$pathOrPublicId;
        }

        return '/storage/'.ltrim($pathOrPublicId, '/');
    }

    /**
     * Check if a file exists.
     */
    public function exists(string $pathOrPublicId, ?string $provider = null): bool
    {
        if (empty($pathOrPublicId)) {
            return false;
        }

        if ($provider === 'cloudinary' || str_contains($pathOrPublicId, 'res.cloudinary.com')) {
            return ! empty($pathOrPublicId);
        }

        $normalized = $this->normalizeLocalPath($pathOrPublicId);

        return Storage::disk('public')->exists($normalized);
    }

    /**
     * Normalize various local path formats to storage/app/public relative path.
     */
    public function normalizeLocalPath(string $path): string
    {
        $parsed = parse_url($path, PHP_URL_PATH);
        $clean = $parsed !== false && $parsed !== null ? $parsed : $path;
        $clean = ltrim($clean, '/');

        $storagePos = strpos($clean, 'storage/');
        if ($storagePos !== false) {
            return substr($clean, $storagePos + strlen('storage/'));
        }

        return $clean;
    }
}
