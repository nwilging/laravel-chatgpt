<?php
declare(strict_types=1);

namespace Nwilging\LaravelChatGpt\Models;

use Illuminate\Contracts\Support\Arrayable;

class ChatCompletionMessage implements Arrayable
{
    public const ROLE_USER = 'user';
    public const ROLE_BOT = 'assistant';
    public const ROLE_SYSTEM = 'system';

    public string $role;

    public string $content;

    public string $name;

    public function toArray(): array
    {
        return array_filter([
            'role' => $this->role,
            'content' => $this->content,
            'name' => $this->name,
        ]);
    }
}
