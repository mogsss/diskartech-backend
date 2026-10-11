<?php

namespace App\Http\Controllers;

use App\Services\UploadStorage;

class MediaController extends Controller
{
    public function document(string $path, UploadStorage $storage)
    {
        abort_unless($storage->isDocument($path), 404);
        $contents = $storage->read($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        $inline = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp', 'image/heic', 'image/heif'], true);
        return response($contents, 200, [
            'Content-Type' => $inline ? $mime : 'application/octet-stream',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.basename($path).'"',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
