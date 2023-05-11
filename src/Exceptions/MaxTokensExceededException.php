<?php
declare(strict_types=1);

namespace Nwilging\LaravelChatGpt\Exceptions;

use Nwilging\LaravelChatGpt\Helpers\Tokenizer;

class MaxTokensExceededException extends \InvalidArgumentException
{
    public int $count;

    public int $max;

    public function __construct(int $count, int $max, int $code = 0)
    {
        $this->count = $count;
        $this->max = $max;

        $message = sprintf(
            'The number of tokens (%d) exceeds the maximum allowed (%d).',
            $count,
            $max
        );

        parent::__construct($message, $code);
    }
}
