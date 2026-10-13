<?php

namespace Tests\Feature;

use App\Models\ChatAttachment;
use App\Models\User;
use App\Services\CloudinaryGateway;
use App\Services\UploadStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatAttachmentsTest extends TestCase
{
    private User $student;
    private User $employer;
    private User $outsider;
    private const CHAT = 'chat_u1_u2';
    private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF";
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a2ZkAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'cache.limiter' => 'array', 'uploads.driver' => 'public']);
        (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
        (require database_path('migrations/2026_10_12_160000_create_chat_attachments_table.php'))->up();
        Storage::fake('local'); Storage::fake('public'); Http::preventStrayRequests();
        $this->student = User::create(['role' => 'student', 'email' => 'student@example.com', 'password' => Hash::make('Test-password1!')]);
        $this->employer = User::create(['role' => 'employer', 'email' => 'employer@example.com', 'password' => Hash::make('Test-password1!')]);
        $this->outsider = User::create(['role' => 'household', 'email' => 'household@example.com', 'password' => Hash::make('Test-password1!')]);
    }

    private function upload(?UploadedFile $file = null): array
    {
        Sanctum::actingAs($this->student);
        return $this->post('/api/chats/'.self::CHAT.'/attachments', ['file' => $file ?? UploadedFile::fake()->createWithContent('resume.pdf', self::PDF)], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonMissingPath('attachment.storage_path')->assertJsonMissingPath('attachment.disk')->json('attachment');
    }

    public function test_attachments_require_authentication_and_signed_viewing_links(): void
    {
        $id = '11111111-1111-4111-8111-111111111111';
        $this->postJson('/api/chats/'.self::CHAT.'/attachments')->assertUnauthorized();
        $this->getJson('/api/chats/'.self::CHAT.'/attachments/'.$id)->assertUnauthorized();
        $this->get('/chat-attachments/'.$id.'/view')->assertForbidden();
    }

    public function test_local_files_are_private_and_sender_and_recipient_can_view(): void
    {
        $metadata = $this->upload();
        $record = ChatAttachment::findOrFail($metadata['id']);
        $this->assertSame('local', $record->disk);
        Storage::disk('local')->assertExists($record->storage_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(1, $record->sender_id); $this->assertSame(2, $record->recipient_id);
        foreach ([$this->student, $this->employer] as $user) {
            Sanctum::actingAs($user);
            $response = $this->getJson('/api/chats/'.self::CHAT.'/attachments/'.$record->id)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            $url = $response->json('url');
            $this->assertStringNotContainsString($record->storage_path, $url);
            $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff')->assertSee(self::PDF, false);
        }
        Sanctum::actingAs($this->outsider);
        $this->getJson('/api/chats/'.self::CHAT.'/attachments/'.$record->id)->assertForbidden();
        $this->getJson('/api/chats/chat_u2_u3/attachments/'.$record->id)->assertNotFound();
    }

    public function test_cloudinary_uploads_use_authenticated_raw_storage_and_do_not_expose_cloudinary_paths(): void
    {
        config(['uploads.driver' => 'cloudinary', 'uploads.cloudinary.folder' => 'diskartech']);
        $this->mock(CloudinaryGateway::class, function ($mock) {
            $mock->shouldReceive('upload')->once()->andReturnUsing(function ($file, $options) {
                $this->assertSame('authenticated', $options['type']); $this->assertSame('raw', $options['resource_type']);
                $this->assertSame('diskartech/chats/'.self::CHAT, $options['asset_folder']);
                return [...$options, 'secure_url' => 'https://res.cloudinary.com/test/raw/authenticated/file.pdf'];
            });
            $mock->shouldReceive('documentUrl')->once()->andReturn('https://res.cloudinary.com/test/raw/authenticated/file.pdf');
        });
        $metadata = $this->upload();
        $record = ChatAttachment::findOrFail($metadata['id']);
        $this->assertStringStartsWith(UploadStorage::DOCUMENT_PREFIX, $record->storage_path);
        $this->assertSame('cloudinary', $record->disk);
        Http::fake(['res.cloudinary.com/*' => Http::response(self::PDF)]);
        $url = $this->getJson('/api/chats/'.self::CHAT.'/attachments/'.$record->id)->assertOk()->json('url');
        $this->assertStringNotContainsString('res.cloudinary.com', $url);
        $this->get($url)->assertOk()->assertSee(self::PDF, false);
    }

    public function test_signed_links_expire_and_a_recipient_can_request_a_fresh_link(): void
    {
        $metadata = $this->upload();
        Sanctum::actingAs($this->employer);
        $path = '/api/chats/'.self::CHAT.'/attachments/'.$metadata['id'];
        $url = $this->getJson($path)->assertOk()->json('url');
        $this->travel(11)->minutes();
        $this->get($url)->assertForbidden();
        $fresh = $this->getJson($path)->assertOk()->json('url');
        $this->assertNotSame($url, $fresh);
        $this->get($fresh)->assertOk();
    }

    public function test_household_upload_ownership_is_derived_from_authentication_not_client_fields(): void
    {
        Sanctum::actingAs($this->outsider);
        $response = $this->post('/api/chats/chat_u1_u3/attachments', [
            'file' => UploadedFile::fake()->createWithContent('offer.pdf', self::PDF),
            'sender_id' => 2, 'recipient_id' => 2, 'storage_path' => 'forged-path',
        ], ['Accept' => 'application/json'])->assertCreated();
        $record = ChatAttachment::findOrFail($response->json('attachment.id'));
        $this->assertSame(3, $record->sender_id); $this->assertSame(1, $record->recipient_id);
        $this->assertNotSame('forged-path', $record->storage_path);
        Sanctum::actingAs($this->student);
        $this->getJson('/api/chats/chat_u1_u3/attachments/'.$record->id)->assertOk();
    }

    public function test_filenames_are_sanitized_and_image_mime_and_size_come_from_file_contents(): void
    {
        $metadata = $this->upload(UploadedFile::fake()->createWithContent("photo\r\n<script>.txt", base64_decode(self::PNG)));
        $this->assertSame('image/png', $metadata['mime_type']);
        $this->assertSame('image', $metadata['kind']);
        $this->assertSame('photoscript.png', $metadata['filename']);
        $this->assertSame(strlen(base64_decode(self::PNG)), $metadata['size']);
        $url = $this->getJson('/api/chats/'.self::CHAT.'/attachments/'.$metadata['id'])->json('url');
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_unsupported_spoofed_empty_and_oversized_files_are_rejected(): void
    {
        Sanctum::actingAs($this->student);
        foreach ([
            UploadedFile::fake()->createWithContent('fake.png', '<script>alert(1)</script>'),
            UploadedFile::fake()->createWithContent('fake.pdf', '<svg></svg>'),
            UploadedFile::fake()->createWithContent('empty.pdf', ''),
            UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf'),
            UploadedFile::fake()->createWithContent('fake.docx', 'not a Word document'),
        ] as $file) {
            $this->post('/api/chats/'.self::CHAT.'/attachments', ['file' => $file], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');
        }
        $this->assertSame(0, ChatAttachment::count());
    }

    public function test_docx_package_contents_are_checked_without_extracting_the_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'chat-docx'); unlink($path); $path .= '.zip';
        try {
            $zip = new \PharData($path, 0, null, \Phar::ZIP);
            $zip['[Content_Types].xml'] = '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>';
            $zip['word/document.xml'] = '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"></w:document>';
            unset($zip);
            $metadata = $this->upload(UploadedFile::fake()->createWithContent('offer.docx', file_get_contents($path)));
            $this->assertSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $metadata['mime_type']);
            $this->assertSame('offer.docx', $metadata['filename']);
        } finally { if (file_exists($path)) unlink($path); }
    }

    public function test_foreign_or_noncanonical_chat_ids_and_client_spoofed_ownership_are_rejected(): void
    {
        Sanctum::actingAs($this->outsider);
        $this->postJson('/api/chats/'.self::CHAT.'/attachments')->assertForbidden();
        Sanctum::actingAs($this->student);
        foreach (['chat_u2_u1', 'chat_u1_u1', 'chat_u01_u2', 'arbitrary-chat', 'chat_u1_u9999999999999999999'] as $chat) $this->postJson('/api/chats/'.$chat.'/attachments')->assertUnprocessable();
        $this->postJson('/api/chats/chat_u1_u99/attachments')->assertForbidden();
        $metadata = $this->upload();
        $this->assertSame(1, ChatAttachment::find($metadata['id'])->sender_id);
    }

    public function test_uploads_are_rate_limited_and_migration_is_idempotent(): void
    {
        Sanctum::actingAs($this->student);
        for ($i = 0; $i < 10; $i++) $this->postJson('/api/chats/'.self::CHAT.'/attachments')->assertUnprocessable();
        $this->postJson('/api/chats/'.self::CHAT.'/attachments')->assertStatus(429);
        (require database_path('migrations/2026_10_12_160000_create_chat_attachments_table.php'))->up();
        $this->assertSame(0, ChatAttachment::count());
    }
}
