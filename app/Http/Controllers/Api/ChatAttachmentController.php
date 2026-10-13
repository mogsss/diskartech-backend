<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatAttachment;
use App\Models\User;
use App\Services\ChatAttachmentStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ChatAttachmentController extends Controller
{
    private function participants(Request $request, string $chatId): array
    {
        // The existing app uses deterministic, numeric direct-chat IDs.
        abort_unless(preg_match('/^chat_u([1-9][0-9]{0,18})_u([1-9][0-9]{0,18})$/D', $chatId, $matches), 422, 'Open a direct conversation before attaching a file.');
        $first = filter_var($matches[1], FILTER_VALIDATE_INT); $second = filter_var($matches[2], FILTER_VALIDATE_INT);
        abort_unless($first && $second && $first < $second, 422, 'Invalid conversation.');
        $myId = (int) $request->user()->id;
        abort_unless(in_array($myId, [$first, $second], true), 403);
        abort_unless(User::whereIn('id', [$first, $second])->whereIn('role', ['student', 'employer', 'household'])->count() === 2, 403);
        return [$myId, $myId === $first ? $second : $first];
    }

    public function store(Request $request, string $chatId, ChatAttachmentStorage $storage)
    {
        [$sender, $recipient] = $this->participants($request, $chatId);
        $request->validate(['file' => ['required', 'file', 'max:10240']]);
        $file = $request->file('file');
        $metadata = $storage->inspect($file);
        $stored = $storage->store($file, $chatId, $metadata['extension']);
        unset($metadata['extension']);
        $attachment = ChatAttachment::create([
            'id' => (string) Str::uuid(), 'chat_id' => $chatId,
            'sender_id' => $sender, 'recipient_id' => $recipient, ...$stored, ...$metadata,
        ]);
        return response()->json(['status' => 'success', 'attachment' => $attachment->metadata()], 201)->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, string $chatId, string $attachment)
    {
        $this->participants($request, $chatId);
        $record = ChatAttachment::where('chat_id', $chatId)->findOrFail($attachment);
        abort_unless(in_array((int) $request->user()->id, [$record->sender_id, $record->recipient_id], true), 403);
        $expires = now()->addMinutes(10);
        return response()->json(['status' => 'success', 'attachment' => $record->metadata(),
            'url' => URL::temporarySignedRoute('chat-attachments.view', $expires, ['attachment' => $record->id]),
            'expires_at' => $expires->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    public function view(string $attachment, ChatAttachmentStorage $storage)
    {
        $record = ChatAttachment::findOrFail($attachment);
        $bytes = $storage->read($record->disk, $record->storage_path);
        $inline = $record->kind === 'image' || $record->mime_type === 'application/pdf';
        return response($bytes, 200, [
            'Content-Type' => $record->mime_type,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="attachment"; filename*=UTF-8\'\''.rawurlencode($record->filename),
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'", 'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
