<?php
declare(strict_types=1);

namespace Nwilging\LaravelChatGpt\Services;

use Nwilging\LaravelChatGpt\Contracts\Services\ChatGptServiceContract;
use Nwilging\LaravelChatGpt\Exceptions\MaxTokensExceededException;
use Nwilging\LaravelChatGpt\Helpers\Tokenizer;
use Nwilging\LaravelChatGpt\Models\ChatCompletionMessage;
use OpenAI\Client;
use Psr\Log\LoggerInterface;

class ChatGptService implements ChatGptServiceContract
{
    protected Tokenizer $tokenizer;

    protected Client $client;

    protected LoggerInterface $log;

    public function __construct(Tokenizer $tokenizer, Client $client, LoggerInterface $log)
    {
        $this->tokenizer = $tokenizer;
        $this->client = $client;
        $this->log = $log;
    }

    public function createChat(
        string $model,
        array $messages,
        float $temperature = 1,
        float $topP = 1,
        int $n = 1,
        bool $stream = false,
        ?array $stop = null,
        ?int $maxTokens = null,
        float $presencePenalty = 0,
        float $frequencyPenalty = 0,
        ?string $user = null
    ): array {
        return $this->create([
            $model,
            $messages,
            $temperature,
            $topP,
            $n,
            $stream,
            $stop,
            $maxTokens,
            $presencePenalty,
            $frequencyPenalty,
            $user,
        ]);
    }

    public function createChatRetainInitialPrompt(
        string $model,
        array $messages,
        float $temperature = 1,
        float $topP = 1,
        int $n = 1,
        bool $stream = false,
        ?array $stop = null,
        ?int $maxTokens = null,
        float $presencePenalty = 0,
        float $frequencyPenalty = 0,
        ?string $user = null
    ): array {
        return $this->create([
            $model,
            $messages,
            $temperature,
            $topP,
            $n,
            $stream,
            $stop,
            $maxTokens,
            $presencePenalty,
            $frequencyPenalty,
            $user,
        ], true);
    }

    protected function create(array $options, bool $withInitialPrompt = false): array
    {
        [
            $model,
            $messages,
            $temperature,
            $topP,
            $n,
            $stream,
            $stop,
            $maxTokens,
            $presencePenalty,
            $frequencyPenalty,
            $user,
        ] = $options;

        $messages = $this->tokenizeAndValidate($messages, $model, $withInitialPrompt);
        $chat = $this->client->chat();

        $response = $chat->create(array_filter([
            'model' => $model,
            'temperature' => $temperature,
            'top_p' => $topP,
            'n' => $n,
            'stream' => $stream,
            'stop' => $stop,
            'max_tokens' => $maxTokens,
            'presence_penalty' => $presencePenalty,
            'frequency_penalty' => $frequencyPenalty,
            'user' => $user,
            'messages' => array_map(fn (ChatCompletionMessage $message) => $message->toArray(), $messages),
        ]));

        return $response->toArray();
    }

    protected function tokenizeAndValidate(array $messages, string $model, bool $retainInitial): array
    {
        $first = $messages[0];
        $removalCoefficient = 0.01;

        do {
            try {
                $this->tokenizer->validateMessages($messages, $model);
                $valid = true;
                continue;
            } catch (MaxTokensExceededException $e) {
                $valid = false;

                // Pull some messages out
                $diff = $e->count - $e->max;
                $this->log->debug(sprintf('Max tokens exceeded by %d. Removing %d messages and retrying.', $diff, $diff * $removalCoefficient));

                // Keep initial prompt at top of stack if desired
                $messages = ($retainInitial) ?
                    [
                        $first, // initial prompt
                        ...array_slice($messages, (int) floor($diff * $removalCoefficient)),
                    ] : array_slice($messages, (int) floor($diff * $removalCoefficient));

                $removalCoefficient += 0.01;
            }
        } while (!$valid);

        return $messages;
    }
}
