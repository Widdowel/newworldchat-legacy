<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;
    protected $fillable = [
        'conversation_id',
        'group_id',
        'content',
        'attachment_path',
        'sender_type',
        'is_read',
        'user_id',
        'reply_to_message_id'
    ];


    protected $casts = [
        'reply_to_message_id' => 'integer',
        'user_id' => 'integer',
    ];


    public function replyToMessage()
    {
        return $this->belongsTo(Message::class, 'reply_to_message_id');
    }

    public function readBy()
    {
        return $this->belongsToMany(User::class, 'message_user')->withTimestamps();
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getAttachmentPathAttribute($value)
    {
        if ($value) {
            return 'https://apiinbox.leedixpay.com/' . $value;
        }
        return null;
    }



    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }
}
