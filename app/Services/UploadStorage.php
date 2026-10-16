<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UploadStorage
{
    public const DOCUMENT_PREFIX = 'cloudinary/raw/authenticated/';

    public function store(File $file, string $folder, bool $public = false): string
    {
        $this->assertSafePath($folder);
        if ($file->getSize() > config('uploads.max_bytes')) {
            throw new HttpException(422, 'Files must not exceed 10 MB.');
        }
        if ($public && ! str_starts_with((string) $file->getMimeType(), 'image/')) {
            throw new HttpException(422, 'Profile pictures must be image files.');
        }
        $extension = $file->guessExtension() ?: 'bin';
        $name = Str::uuid().'.'.$extension;
        if (config('uploads.driver') === 'public') {
            $path = Storage::disk('public')->putFileAs($folder, $file, $name);
            if (! $path) throw new HttpException(503, 'File storage is temporarily unavailable. Please try again.');
            return $path;
        }
        if (config('uploads.driver') !== 'cloudinary') {
            throw new HttpException(503, 'File storage is not configured.');
        }
        $prefix = trim((string) config('uploads.cloudinary.folder', 'diskartech'), '/');
        $this->assertSafePath($prefix);
        // Registration and profile uploads previously used different local folders.
        $folder = match ($folder) {
            'students/school_ids' => 'student/school_id',
            'students/coes' => 'student/coe',
            'students/resumes' => 'student/resume',
            'household/avatar' => 'households/avatar',
            default => $folder,
        };
        $publicId = $prefix.'/'.$folder.'/'.($public ? pathinfo($name, PATHINFO_FILENAME) : $name);
        if (strlen(self::DOCUMENT_PREFIX.$publicId) > 255) {
            throw new HttpException(503, 'The configured upload folder is too long.');
        }
        try {
            $result = app(CloudinaryGateway::class)->upload($file->getPathname(), [
                'resource_type' => $public ? 'image' : 'raw',
                'type' => $public ? 'upload' : 'authenticated',
                'public_id' => $publicId,
                // In dynamic folder mode, public_id alone does not place assets in a Console folder.
                'asset_folder' => $prefix.'/'.$folder,
                'use_asset_folder_as_public_id_prefix' => false,
                'overwrite' => false,
            ]);
            if (($result['public_id'] ?? null) !== $publicId
                || ($result['resource_type'] ?? null) !== ($public ? 'image' : 'raw')
                || ($result['type'] ?? null) !== ($public ? 'upload' : 'authenticated')) {
                throw new \RuntimeException('Unexpected upload response.');
            }
            if (! $public) return self::DOCUMENT_PREFIX.$publicId;
            $url = $result['secure_url'] ?? '';
            if (parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== 'res.cloudinary.com') {
                throw new \RuntimeException('Unexpected image URL.');
            }
            return $url;
        } catch (\Throwable $exception) {
            // Do not echo SDK exceptions: they can contain signed URLs/credentials.
            throw new HttpException(503, 'Cloudinary file storage is unavailable. Check the backend configuration or try again.');
        }
    }

    public function url(?string $path): ?string
    {
        $path = trim($path ?? '');
        if ($path === '') return null;
        if (preg_match('~^https?://~i', $path)) return $path;
        $path = preg_replace('~^(?:/?storage/)+~', '', $path);
        $path = ltrim($path, '/');
        if ($this->isDocument($path)) {
            $this->documentId($path);
            return URL::temporarySignedRoute('media.document', now()->addMinutes(config('uploads.document_link_minutes')), ['path' => $path]);
        }
        $this->assertSafePath($path);
        return Storage::disk('public')->url($path);
    }

    public function isDocument(string $path): bool
    {
        return str_starts_with($path, self::DOCUMENT_PREFIX);
    }

    public function deleteProfileFile(string $path): void
    {
        if ($this->isDocument($path)) {
            app(CloudinaryGateway::class)->delete($this->documentId($path), ['resource_type' => 'raw', 'type' => 'authenticated', 'invalidate' => true]);
            return;
        }
        if (preg_match('~^https?://~i', $path)) {
            // Never fetch arbitrary URLs or delete an external provider's avatar.
            $cloud = preg_quote((string) config('uploads.cloudinary.cloud_name'), '~');
            $url = parse_url($path);
            if (($url['host'] ?? '') !== 'res.cloudinary.com' || !$cloud) return;
            if (!preg_match('~^/'.$cloud.'/image/upload/(?:v[0-9]+/)?(.+)\.[a-zA-Z0-9]+$~D', rawurldecode($url['path'] ?? ''), $match)) {
                throw new \RuntimeException('Unrecognized profile image reference.');
            }
            $this->assertSafePath($match[1]);
            app(CloudinaryGateway::class)->delete($match[1], ['resource_type' => 'image', 'type' => 'upload', 'invalidate' => true]);
            return;
        }
        $path = ltrim(preg_replace('~^(?:/?storage/)+~', '', $path), '/');
        $this->assertSafePath($path);
        if (Storage::disk('public')->exists($path) && !Storage::disk('public')->delete($path)) {
            throw new \RuntimeException('Profile file cleanup did not complete.');
        }
    }

    public function read(string $path): string
    {
        if ($this->isDocument($path)) {
            $id = $this->documentId($path);
            try {
                $url = app(CloudinaryGateway::class)->documentUrl($id);
                $response = Http::connectTimeout(10)->timeout(60)->get($url);
            } catch (\Throwable $exception) {
                throw new HttpException(503, 'The document is temporarily unavailable. Please try again.');
            }
            if ($response->status() === 404) throw new HttpException(404, 'Document not found.');
            if (! $response->successful()) throw new HttpException(503, 'The document is temporarily unavailable. Check Cloudinary delivery settings.');
            $contents = $response->body();
        } else {
            $this->assertSafePath($path);
            if (! Storage::disk('public')->exists($path)) throw new HttpException(404, 'Document not found.');
            $contents = Storage::disk('public')->get($path);
        }
        if (strlen($contents) > config('uploads.max_bytes')) throw new HttpException(413, 'The document exceeds the supported size.');
        return $contents;
    }

    private function documentId(string $path): string
    {
        $id = substr($path, strlen(self::DOCUMENT_PREFIX));
        $this->assertSafePath($id);
        return $id;
    }

    public function assertSafePath(string $path): void
    {
        if (! preg_match('~^[\pL\pN _./()-]+$~uD', $path) || str_starts_with($path, '/')
            || array_intersect(explode('/', $path), ['', '.', '..'])) {
            throw new HttpException(404, 'File not found.');
        }
    }
}
