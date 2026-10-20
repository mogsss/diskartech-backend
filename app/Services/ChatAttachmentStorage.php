<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ChatAttachmentStorage
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public function inspect(UploadedFile $file): array
    {
        // Sniff the bytes independently of client MIME headers and filename extensions.
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
        $extension = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
            'application/pdf' => 'pdf', default => null,
        };
        if (!$extension && in_array($mime, ['application/zip', 'application/x-zip-compressed', 'application/octet-stream', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'], true)
            && strtolower($file->getClientOriginalExtension()) === 'docx' && $this->isDocx($file->getPathname())) {
            $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            $extension = 'docx';
        }
        if (!$extension || !$file->getSize() || $file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => 'Choose a JPG, PNG, WEBP, GIF, PDF, or DOCX file no larger than 10 MB.']);
        }
        $kind = str_starts_with($mime, 'image/') ? 'image' : 'file';
        if ($kind === 'image') {
            $dimensions = @getimagesize($file->getPathname());
            if (!$dimensions || $dimensions[0] > 12000 || $dimensions[1] > 12000) {
                throw ValidationException::withMessages(['file' => 'Choose a valid image no larger than 12000 pixels on either side.']);
            }
        }
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = preg_replace('/[\p{Cc}\p{Cf}<>:"|?*]/u', '', pathinfo($name, PATHINFO_FILENAME)) ?? '';
        $name = mb_substr(trim($name, " .\t\r\n"), 0, 160) ?: 'attachment';
        return ['mime_type' => $mime, 'kind' => $kind, 'size' => $file->getSize(), 'filename' => $name.'.'.$extension, 'extension' => $extension];
    }

    private function isDocx(string $path): bool
    {
        $archive = null;
        try {
            // PHP's Linux upload paths have no extension; PharData rejects those
            // even for valid DOCX bytes. ZipArchive reads the actual ZIP contents.
            $zip = new \ZipArchive();
            if ($zip->open($path, \ZipArchive::RDONLY) !== true) return false;
            $archive = $zip;
            if ($archive->locateName('[Content_Types].xml') === false
                || $archive->locateName('word/document.xml') === false
                || $archive->numFiles > 2048) return false;
            $total = 0;
            // Read entry metadata only: no extraction or document execution.
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entry = $archive->statIndex($index);
                if ($entry === false) return false;
                $total += $entry['size'];
                if ($total > 50 * 1024 * 1024
                    || ($entry['encryption_method'] ?? 0) !== 0
                    || str_ends_with(strtolower($entry['name']), 'vbaproject.bin')) return false;
            }
            return true;
        } catch (\Throwable) { return false; }
        finally { $archive?->close(); }
    }

    public function store(UploadedFile $file, string $chatId, string $extension): array
    {
        if (config('uploads.driver') === 'cloudinary') {
            return ['disk' => 'cloudinary', 'storage_path' => app(UploadStorage::class)->store($file, 'chats/'.$chatId, false)];
        }
        // Local development keeps chat files outside the public storage directory.
        abort_unless(config('uploads.driver') === 'public', 503, 'File storage is unavailable.');
        $path = Storage::disk('local')->putFileAs('chat-attachments/'.$chatId, $file, Str::uuid().'.'.$extension);
        if (!$path) throw new HttpException(503, 'File storage is unavailable.');
        return ['disk' => 'local', 'storage_path' => $path];
    }

    public function read(string $disk, string $path): string
    {
        if ($disk === 'cloudinary') return app(UploadStorage::class)->read($path);
        abort_unless($disk === 'local' && str_starts_with($path, 'chat-attachments/'), 404);
        app(UploadStorage::class)->assertSafePath($path);
        abort_unless(Storage::disk('local')->exists($path), 404);
        $bytes = Storage::disk('local')->get($path);
        abort_if(strlen($bytes) > self::MAX_BYTES, 413);
        return $bytes;
    }
}
