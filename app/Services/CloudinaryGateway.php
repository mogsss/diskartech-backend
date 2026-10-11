<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use RuntimeException;

class CloudinaryGateway
{
    public function upload(string $file, array $options): array
    {
        return $this->client()->uploadApi()->upload($file, $options)->getArrayCopy();
    }

    public function documentUrl(string $publicId): string
    {
        // Signed CDN URL stays on the backend. Clients receive a separate,
        // expiring application URL, and cannot request unsigned originals.
        $asset = $this->client()->raw($publicId)->deliveryType('authenticated');
        $asset->urlConfig->signUrl();
        return (string) $asset->toUrl();
    }

    private function client(): Cloudinary
    {
        $cloud = config('uploads.cloudinary');
        foreach (['cloud_name', 'api_key', 'api_secret'] as $key) {
            if (! is_string($cloud[$key] ?? null) || trim($cloud[$key]) === '') {
                throw new RuntimeException('Cloudinary storage is not configured.');
            }
        }
        return new Cloudinary([
            'cloud' => array_intersect_key($cloud, array_flip(['cloud_name', 'api_key', 'api_secret'])),
            'url' => ['secure' => true],
            'api' => ['connection_timeout' => 10, 'timeout' => 60, 'upload_timeout' => 60],
        ]);
    }
}
