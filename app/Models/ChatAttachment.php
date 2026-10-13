<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatAttachment extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'chat_id', 'sender_id', 'recipient_id', 'disk', 'storage_path', 'filename', 'mime_type', 'kind', 'size'];
    protected $hidden = ['storage_path', 'disk', 'sender_id', 'recipient_id'];
    protected function casts(): array { return ['size' => 'integer', 'sender_id' => 'integer', 'recipient_id' => 'integer']; }

    public function metadata(): array
    {
        return $this->only(['id', 'filename', 'mime_type', 'kind', 'size']);
    }
}
