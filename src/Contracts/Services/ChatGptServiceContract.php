<?php
declare(strict_types=1);

namespace Nwilging\LaravelChatGpt\Contracts\Services;

use Nwilging\LaravelChatGpt\Models\ChatCompletionMessage;

interface ChatGptServiceContract
{
    /**
     * @param string $model
     * @param ChatCompletionMessage[] $messages
     * @param float $temperature
     * @param float $topP
     * @param int $n
     * @param bool $stream
     * @param array $functions
     * @param array|null $stop
     * @param int|null $maxTokens
     * @param float $presencePenalty
     * @param float $frequencyPenalty
     * @param string|null $user
     * @return array
     */
    public function createChat(
        string $model,
        array $messages,
        float $temperature = 1,
        float $topP = 1,
        int $n = 1,
        bool $stream = false,
        array $functions = [],
        ?array $stop = null,
        ?int $maxTokens = null,
        float $presencePenalty = 0,
        float $frequencyPenalty = 0,
        ?string $user = null
    ): array;

    /**
     * @param string $model
     * @param ChatCompletionMessage[] $messages
     * @param float $temperature
     * @param float $topP
     * @param int $n
     * @param bool $stream
     * @param array $functions
     * @param array|null $stop
     * @param int|null $maxTokens
     * @param float $presencePenalty
     * @param float $frequencyPenalty
     * @param string|null $user
     * @return array
     */
    public function createChatRetainInitialPrompt(
        string $model,
        array $messages,
        float $temperature = 1,
        float $topP = 1,
        int $n = 1,
        bool $stream = false,
        array $functions = [],
        ?array $stop = null,
        ?int $maxTokens = null,
        float $presencePenalty = 0,
        float $frequencyPenalty = 0,
        ?string $user = null
    ): array;
}
