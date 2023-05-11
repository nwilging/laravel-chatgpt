<?php
declare(strict_types=1);

namespace Nwilging\LaravelChatGptTests\Unit\Models;

use Nwilging\LaravelChatGpt\Models\ChatCompletionMessage;
use Nwilging\LaravelChatGptTests\TestCase;

class ChatCompletionMessageTest extends TestCase
{
    public function testToArray()
    {
        $model = new ChatCompletionMessage();

        $model->role = ChatCompletionMessage::ROLE_USER;
        $model->name = 'username';
        $model->content = 'the message';

        $this->assertSame([
            'role' => ChatCompletionMessage::ROLE_USER,
            'content' => 'the message',
            'name' => 'username',
        ], $model->toArray());
    }
}
