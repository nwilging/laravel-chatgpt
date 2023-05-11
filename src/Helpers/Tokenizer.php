<?php
declare(strict_types=1);

namespace Nwilging\LaravelChatGpt\Helpers;

use Nwilging\LaravelChatGpt\Exceptions\MaxTokensExceededException;
use Nwilging\LaravelChatGpt\Models\ChatCompletionMessage;
use Illuminate\Contracts\Filesystem\Filesystem;

class Tokenizer
{
    public const MAX_TOKENS_MAP = [
        /**
         * GPT-4
         * @see https://platform.openai.com/docs/models/gpt-4
         */
        'gpt-4' => 8192,
        'gpt-4-0314' => 8192,
        'gpt-4-32k' => 32768,
        'gpt-4-32k-0314' => 32768,

        /**
         * GPT-3.5
         * @see https://platform.openai.com/docs/models/gpt-3-5
         */
        'gpt-3.5-turbo' => 4096,
        'gpt-3.5-turbo-0301' => 4096,
        'text-davinci-003' => 4097,
        'text-davinci-002' => 4097,
        'code-davinci-002' => 8001,

        /**
         * GPT-3
         * @see https://platform.openai.com/docs/models/gpt-3
         */
        'text-curie-001' => 2049,
        'text-babbage-001' => 2049,
        'text-ada-001' => 2049,
        'davinci' => 2049,
        'curie' => 2049,
        'babbage' => 2049,
        'ada' => 2049,
    ];

    protected GptHelper $gptHelper;

    protected Filesystem $filesystem;

    public function __construct(GptHelper $gptHelper, Filesystem $filesystem)
    {
        $this->gptHelper = $gptHelper;
        $this->filesystem = $filesystem;
    }

    public function validatePrompt(string $prompt, string $model): void
    {
        $count = count($this->tokenize($prompt));
        $max = static::MAX_TOKENS_MAP[$model] ?? -1;

        if ($max !== -1 && $count >= $max) {
            throw new MaxTokensExceededException($count, $max);
        }
    }

    /**
     * @param ChatCompletionMessage[] $messages
     * @return void
     */
    public function validateMessages(array $messages, string $model): void
    {
        $count = count($this->tokenizeMessages($messages));
        $max = static::MAX_TOKENS_MAP[$model] ?? -1;

        if ($max !== -1 && $count >= $max) {
            throw new MaxTokensExceededException($count, $max);
        }
    }

    /**
     * @param ChatCompletionMessage[] $messages
     * @return array
     */
    public function tokenizeMessages(array $messages): array
    {
        $transformer = function (ChatCompletionMessage $message): array {
            $template = ($message->role === ChatCompletionMessage::ROLE_SYSTEM) ? 'system' : '%s name=%s';
            return [
                sprintf('<|im_start|>%s', sprintf($template, $message->role, $message->name)),
                $message->content,
                '<|im_end|>',
            ];
        };

        $text = [];
        foreach ($messages as $message) {
            $data = $transformer($message);
            $text[] = implode("\n", $data);
        }

        return $this->tokenize(implode("\n", $text));
    }

    public function tokenize(string $text): array
    {
        $bpeTokens = [];
        if (empty($text)) {
            return $bpeTokens;
        }

        $byteEncoder = $this->charactersJson();
        $encoder = $this->encoderJson();
        $bpe = $this->vocabBpe();

        preg_match_all("#'s|'t|'re|'ve|'m|'ll|'d| ?\p{L}+| ?\p{N}+| ?[^\s\p{L}\p{N}]+|\s+(?!\S)|\s+#u", $text, $matches);
        if (empty($matches[0])) {
            return $bpeTokens;
        }

        $lines = preg_split('/\r\n|\r|\n/', $bpe);

        $bpeMerges = [];
        $bpeMergesTmp = array_slice($lines, 1, count($lines), true);

        foreach ($bpeMergesTmp as $bmt) {
            $split = preg_split('#(\s+)#', $bmt);
            $filtered = array_filter($split, [$this->gptHelper, 'varFilter']);
            if (!empty($filtered)) {
                $bpeMerges[] = $filtered;
            }
        }

        $bpeRanks = $this->gptHelper->dictZip(
            $bpeMerges,
            range(0, count($bpeMerges) - 1)
        );

        $cache = [];
        foreach ($matches[0] as $token) {
            $newTokens = [];
            $chars = [];
            $token = $this->gptHelper->utf8Encode($token);

            $len = mb_strlen($token, 'UTF-8');
            for ($i = 0; $i < $len; $i++) {
                $chars[] = mb_substr($token, $i, 1, 'UTF-8');
            }

            $result = '';
            foreach ($chars as $char) {
                $unichr = $this->gptHelper->uniChr($char);
                if (isset($encoder[$unichr])) {
                    $result .= $byteEncoder[$unichr];
                }
            }

            $newTokensBpe = $this->gptHelper->getBpe($result, $bpeRanks, $cache);
            $split = explode(' ', $newTokensBpe);

            foreach ($split as $x) {
                if (isset($encoder[$x])) {
                    if (isset($newTokens[$x])) {
                        $newTokens[sprintf('%d---%s', rand(), $x)] = $encoder[$x];
                        continue;
                    }

                    $newTokens[$x] = $encoder[$x];
                    continue;
                }

                if (isset($newTokens[$x])) {
                    $newTokens[sprintf('%d---%s', rand(), $x)] = $encoder[$x];
                    continue;
                }

                $newTokens[$x] = $encoder[$x];
            }

            foreach ($newTokens as $ninx => $nval) {
                if (isset($bpeTokens[$ninx])) {
                    $bpeTokens[sprintf('%d---%s', rand(), $ninx)] = $nval;
                    continue;
                }

                $bpeTokens[$ninx] = $nval;
            }
        }

        return $bpeTokens;
    }

    protected function charactersJson(): array
    {
        return json_decode($this->filesystem->get('openai_tokenizer/characters.json'), true);
    }

    protected function encoderJson(): array
    {
        return json_decode($this->filesystem->get('openai_tokenizer/encoder.json'), true);
    }

    protected function vocabBpe(): string
    {
        return $this->filesystem->get('openai_tokenizer/vocab.bpe');
    }
}
