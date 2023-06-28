<?php
declare(strict_types=1);

namespace Nwilging\LaravelChatGptTests\Unit\Services;

use Illuminate\Support\Arr;
use Mockery\MockInterface;
use Nwilging\LaravelChatGpt\Exceptions\MaxTokensExceededException;
use Nwilging\LaravelChatGpt\Helpers\Tokenizer;
use Nwilging\LaravelChatGpt\Models\ChatCompletionMessage;
use Nwilging\LaravelChatGpt\Services\ChatGptService;
use Nwilging\LaravelChatGptTests\TestCase;
use OpenAI\Client;
use OpenAI\Resources\Chat;
use OpenAI\Responses\Chat\CreateResponse;
use Psr\Log\LoggerInterface;
use DG\BypassFinals;

class ChatGptServiceTest extends TestCase
{
    protected MockInterface $tokenizer;

    protected $openAiClient;

    protected MockInterface $log;

    protected ChatGptService $service;

    public function setUp(): void
    {
        parent::setUp();
        BypassFinals::enable();

        $this->tokenizer = \Mockery::mock(Tokenizer::class);
        $this->openAiClient = \Mockery::mock(Client::class);
        $this->log = \Mockery::mock(LoggerInterface::class);

        $this->service = new ChatGptService($this->tokenizer, $this->openAiClient, $this->log);
    }

    public function testCreateChatSuccess()
    {
        $message1 = $this->generateMessage(ChatCompletionMessage::ROLE_SYSTEM, 'system', 'system message');
        $message2 = $this->generateMessage(ChatCompletionMessage::ROLE_USER, 'username', 'user message');
        $message3 = $this->generateMessage(ChatCompletionMessage::ROLE_BOT, 'bot', 'bot message');

        $this->tokenizer->shouldReceive('validateMessages')
            ->once()
            ->with([$message1, $message2, $message3], 'gpt-3.5-turbo');

        $chat = \Mockery::mock(Chat::class);
        $this->openAiClient->shouldReceive('chat')
            ->once()
            ->andReturn($chat);

        $response = \Mockery::mock(CreateResponse::class);
        $response->shouldReceive('toArray')
            ->once()
            ->andReturn([]);

        $chat->shouldReceive('create')
            ->once()
            ->with([
                'model' => 'gpt-3.5-turbo',
                'temperature' => 1,
                'top_p' => 1,
                'n' => 1,
                'messages' => array_map(fn (ChatCompletionMessage $message) => $message->toArray(), [
                    $message1,
                    $message2,
                    $message3,
                ]),
            ])->andReturn($response);

        $result = $this->service->createChat('gpt-3.5-turbo', [$message1, $message2, $message3]);
        $this->assertSame([], $result);
    }

    public function testCreateChatWithFunctions()
    {
        $message1 = $this->generateMessage(ChatCompletionMessage::ROLE_SYSTEM, 'system', 'system message');
        $message2 = $this->generateMessage(ChatCompletionMessage::ROLE_USER, 'username', 'user message');
        $message3 = $this->generateMessage(ChatCompletionMessage::ROLE_BOT, 'bot', 'bot message');

        $functions = ['func1', 'func2', 'func3'];

        $this->tokenizer->shouldReceive('validateMessages')
            ->once()
            ->with([$message1, $message2, $message3], 'gpt-3.5-turbo');

        $chat = \Mockery::mock(Chat::class);
        $this->openAiClient->shouldReceive('chat')
            ->once()
            ->andReturn($chat);

        $response = \Mockery::mock(CreateResponse::class);
        $response->shouldReceive('toArray')
            ->once()
            ->andReturn([]);

        $chat->shouldReceive('create')
            ->once()
            ->with([
                'model' => 'gpt-3.5-turbo',
                'temperature' => 1,
                'top_p' => 1,
                'n' => 1,
                'functions' => $functions,
                'messages' => array_map(fn (ChatCompletionMessage $message) => $message->toArray(), [
                    $message1,
                    $message2,
                    $message3,
                ]),
            ])->andReturn($response);

        $result = $this->service->createChat(
            'gpt-3.5-turbo',
            [$message1, $message2, $message3],
            1,
            1,
            1,
            false,
            $functions,
        );

        $this->assertSame([], $result);
    }

    public function testCreateChatPrunesMessages()
    {
        $messages = [];
        for ($i=0; $i<5; $i++) {
            $messages[] = $this->generateMessage(Arr::random([
                ChatCompletionMessage::ROLE_SYSTEM,
                ChatCompletionMessage::ROLE_USER,
                ChatCompletionMessage::ROLE_BOT,
            ]), 'username', 'message content');
        }

        // Over by 100 tokens, should remove 2 message
        $overage1 = 4096 + 200;
        $prune1 = array_slice($messages, 2);

        // Over by 100 tokens, should remove 2 message (removal coefficient = 0.02)
        $overage2 = 4096 + 100;
        $prune2 = array_slice($messages, 4);

        $this->log->shouldReceive('debug')->once()->with('Max tokens exceeded by 200. Removing 2 messages and retrying.');
        $this->tokenizer->shouldReceive('validateMessages')
            ->once()
            ->with($messages, 'gpt-3.5-turbo')
            ->andThrow(new MaxTokensExceededException($overage1, 4096));

        $this->log->shouldReceive('debug')->once()->with('Max tokens exceeded by 100. Removing 2 messages and retrying.');
        $this->tokenizer->shouldReceive('validateMessages')
            ->once()
            ->with($prune1, 'gpt-3.5-turbo')
            ->andThrow(new MaxTokensExceededException($overage2, 4096));

        $this->tokenizer->shouldReceive('validateMessages')
            ->once()
            ->with($prune2, 'gpt-3.5-turbo');

        $chat = \Mockery::mock(Chat::class);
        $this->openAiClient->shouldReceive('chat')
            ->once()
            ->andReturn($chat);

        $response = \Mockery::mock(CreateResponse::class);
        $response->shouldReceive('toArray')
            ->once()
            ->andReturn([]);

        $chat->shouldReceive('create')
            ->once()
            ->with([
                'model' => 'gpt-3.5-turbo',
                'temperature' => 1,
                'top_p' => 1,
                'n' => 1,
                'messages' => array_map(fn (ChatCompletionMessage $message) => $message->toArray(), $prune2),
            ])->andReturn($response);

        $result = $this->service->createChat('gpt-3.5-turbo', $messages);
        $this->assertSame([], $result);
    }

    public function testCreateChatRetainInitialPrunesMessagesAndRetainsInitial()
    {
        $messages = [];
        for ($i=0; $i<5; $i++) {
            $messages[] = $this->generateMessage(Arr::random([
                ChatCompletionMessage::ROLE_SYSTEM,
                ChatCompletionMessage::ROLE_USER,
                ChatCompletionMessage::ROLE_BOT,
            ]), 'username', 'message content');
        }

        // Over by 100 tokens, should remove 2 message
        $overage1 = 4096 + 200;
        $prune1 = [$messages[0], ...array_slice($messages, 2)];

        $overage2 = 4096 + 100;
        $prune2 = [$messages[0], ...array_slice($messages, 3)];

        $this->log->shouldReceive('debug')->once()->with('Max tokens exceeded by 200. Removing 2 messages and retrying.');
        $this->tokenizer->shouldReceive('validateMessages')
            ->once()
            ->with($messages, 'gpt-3.5-turbo')
            ->andThrow(new MaxTokensExceededException($overage1, 4096));

        $this->log->shouldReceive('debug')->once()->with('Max tokens exceeded by 100. Removing 2 messages and retrying.');
        $this->tokenizer->shouldReceive('validateMessages')
            ->once()
            ->with($prune1, 'gpt-3.5-turbo')
            ->andThrow(new MaxTokensExceededException($overage2, 4096));

        $this->tokenizer->shouldReceive('validateMessages')
            ->once()
            ->with($prune2, 'gpt-3.5-turbo');

        $chat = \Mockery::mock(Chat::class);
        $this->openAiClient->shouldReceive('chat')
            ->once()
            ->andReturn($chat);

        $response = \Mockery::mock(CreateResponse::class);
        $response->shouldReceive('toArray')
            ->once()
            ->andReturn([]);

        $chat->shouldReceive('create')
            ->once()
            ->with([
                'model' => 'gpt-3.5-turbo',
                'temperature' => 1,
                'top_p' => 1,
                'n' => 1,
                'messages' => array_map(fn (ChatCompletionMessage $message) => $message->toArray(), $prune2),
            ])->andReturn($response);

        $result = $this->service->createChatRetainInitialPrompt('gpt-3.5-turbo', $messages);
        $this->assertSame([], $result);
    }

    public function testCreateChatRetainInitialPrunesMessagesAndRetainsInitialUsingFunctions()
    {
        $messages = [];
        for ($i=0; $i<5; $i++) {
            $messages[] = $this->generateMessage(Arr::random([
                ChatCompletionMessage::ROLE_SYSTEM,
                ChatCompletionMessage::ROLE_USER,
                ChatCompletionMessage::ROLE_BOT,
            ]), 'username', 'message content');
        }

        $functions = ['func1', 'func2', 'func3'];

        // Over by 100 tokens, should remove 2 message
        $overage1 = 4096 + 200;
        $prune1 = [$messages[0], ...array_slice($messages, 2)];

        $overage2 = 4096 + 100;
        $prune2 = [$messages[0], ...array_slice($messages, 3)];

        $this->log->shouldReceive('debug')->once()->with('Max tokens exceeded by 200. Removing 2 messages and retrying.');
        $this->tokenizer->shouldReceive('validateMessages')
            ->once()
            ->with($messages, 'gpt-3.5-turbo')
            ->andThrow(new MaxTokensExceededException($overage1, 4096));

        $this->log->shouldReceive('debug')->once()->with('Max tokens exceeded by 100. Removing 2 messages and retrying.');
        $this->tokenizer->shouldReceive('validateMessages')
            ->once()
            ->with($prune1, 'gpt-3.5-turbo')
            ->andThrow(new MaxTokensExceededException($overage2, 4096));

        $this->tokenizer->shouldReceive('validateMessages')
            ->once()
            ->with($prune2, 'gpt-3.5-turbo');

        $chat = \Mockery::mock(Chat::class);
        $this->openAiClient->shouldReceive('chat')
            ->once()
            ->andReturn($chat);

        $response = \Mockery::mock(CreateResponse::class);
        $response->shouldReceive('toArray')
            ->once()
            ->andReturn([]);

        $chat->shouldReceive('create')
            ->once()
            ->with([
                'model' => 'gpt-3.5-turbo',
                'temperature' => 1,
                'top_p' => 1,
                'n' => 1,
                'functions' => $functions,
                'messages' => array_map(fn (ChatCompletionMessage $message) => $message->toArray(), $prune2),
            ])->andReturn($response);

        $result = $this->service->createChatRetainInitialPrompt(
            'gpt-3.5-turbo',
            $messages,
            1,
            1,
            1,
            false,
            $functions,
        );

        $this->assertSame([], $result);
    }

    protected function generateMessage(string $role, string $name, string $content): ChatCompletionMessage
    {
        $model = new ChatCompletionMessage();

        $model->role = $role;
        $model->name = $name;
        $model->content = $content;

        return $model;
    }
}
