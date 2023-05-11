<?php
declare(strict_types=1);

namespace Nwilging\LaravelChatGptTests\Unit\Helpers;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Nwilging\LaravelChatGpt\Exceptions\MaxTokensExceededException;
use Nwilging\LaravelChatGpt\Helpers\GptHelper;
use Nwilging\LaravelChatGpt\Helpers\Tokenizer;
use Nwilging\LaravelChatGpt\Models\ChatCompletionMessage;
use Nwilging\LaravelChatGptTests\TestCase;
use phpmock\Mock;
use phpmock\MockBuilder;

class TokenizerTest extends TestCase
{
    protected Mock $randMock;

    public function setUp(): void
    {
        parent::setUp();
        $builder = new MockBuilder();

        $randPointer = 0;
        $builder
            ->setNamespace(substr(Tokenizer::class, 0, strrpos(Tokenizer::class, '\\')))
            ->setName('rand')
            ->setFunction(function () use (&$randPointer) {
                return $randPointer++;
            });

        $this->randMock = $builder->build();
        $this->randMock->enable();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->randMock->disable();
    }

    /**
     * @dataProvider promptTokenizationDataProvider
     */
    public function testTokenize(string $prompt, array $expectedTokens)
    {
        $filesystem = Storage::build([
            'driver' => 'local',
            'root' => __DIR__ . '/../../../config/storage',
        ]);

        $tokenizer = new Tokenizer(
            new GptHelper(),
            $filesystem
        );

        $result = $tokenizer->tokenize($prompt);
        $this->assertSame($expectedTokens, $result);
    }

    /**
     * @dataProvider messageTokenizationDataProvider
     */
    public function testTokenizeMessages(array $messages, array $expectedTokens)
    {
        $filesystem = Storage::build([
            'driver' => 'local',
            'root' => __DIR__ . '/../../../config/storage',
        ]);

        $tokenizer = new Tokenizer(
            new GptHelper(),
            $filesystem
        );

        $result = $tokenizer->tokenizeMessages($messages);
        $this->assertSame($expectedTokens, $result);
    }

    /**
     * @dataProvider validatePromptDataProvider
     */
    public function testValidatePromptThrowException(string $model, int $limit)
    {
        $filesystem = Storage::build([
            'driver' => 'local',
            'root' => __DIR__ . '/../../../config/storage',
        ]);

        $tokenizer = new Tokenizer(
            new GptHelper(),
            $filesystem
        );

        $prompt = $this->randomString($limit);
        $tokens = count($tokenizer->tokenize($prompt));

        $this->expectException(MaxTokensExceededException::class);
        $this->expectExceptionMessage(sprintf('The number of tokens (%d) exceeds the maximum allowed (%d)', $tokens, $limit));

        $tokenizer->validatePrompt($prompt, $model);
    }

    /**
     * @dataProvider validatePromptDataProvider
     */
    public function testValidateMessagesThrowsException(string $model, int $limit)
    {
        $filesystem = Storage::build([
            'driver' => 'local',
            'root' => __DIR__ . '/../../../config/storage',
        ]);

        $tokenizer = new Tokenizer(
            new GptHelper(),
            $filesystem
        );

        $messages = [];
        for ($x=0; $x<5; $x++) {
            $message = new ChatCompletionMessage();
            $message->role = Arr::random([
                ChatCompletionMessage::ROLE_SYSTEM,
                ChatCompletionMessage::ROLE_BOT,
                ChatCompletionMessage::ROLE_USER,
            ]);

            $message->name = $this->randomString(8);
            $message->content = $this->randomString((int) floor($limit / 4));

            $messages[] = $message;
        }

        $tokens = count($tokenizer->tokenizeMessages($messages));

        $this->expectException(MaxTokensExceededException::class);
        $this->expectExceptionMessage(sprintf('The number of tokens (%d) exceeds the maximum allowed (%d)', $tokens, $limit));

        $tokenizer->validateMessages($messages, $model);
    }

    public function promptTokenizationDataProvider(): array
    {
        return [
            'empty string' => [
                '',
                [],
            ],
            'space' => [
                ' ',
                [
                    'Ġ' => 220,
                ],
            ],
            'tab' => [
                "\t",
                [
                    'ĉ' => 197,
                ],
            ],
            'simple text' => [
                'this is a prompt',
                [
                    'this' => 5661,
                    'Ġis' => 318,
                    'Ġa' => 257,
                    'Ġprompt' => 6152,
                ],
            ],
            'multi token word' => [
                'indivisible',
                [
                    'ind' => 521,
                    'iv' => 452,
                    'isible' => 12843,
                ],
            ],
            'emojis' => [
                'hello 👋 world 🌍',
                [
                    'hello' => 31373,
                    'ĠðŁĳ' => 50169,
                    'ĭ' => 233,
                    'Ġworld' => 995,
                    'ĠðŁ' => 12520,
                    'Į' => 234,
                    'į' => 235,
                ],
            ],
        ];
    }

    public function messageTokenizationDataProvider(): array
    {
        $message1 = new ChatCompletionMessage();
        $message2 = new ChatCompletionMessage();
        $message3 = new ChatCompletionMessage();

        $message1->role = ChatCompletionMessage::ROLE_SYSTEM;
        $message2->role = ChatCompletionMessage::ROLE_USER;
        $message3->role = ChatCompletionMessage::ROLE_BOT;

        $message1->name = 'system';
        $message2->name = 'TheUser';
        $message3->name = 'TheBot';

        $message1->content = 'system message';
        $message2->content = 'user message';
        $message3->content = 'bot message';

        return [
            'one message' => [
                [$message1],
                [
                    '<' => 27,
                    '|' => 91,
                    'im' => 320,
                    '_' => 62,
                    'start' => 9688,
                    '0---|' => 91,
                    '>' => 29,
                    'system' => 10057,
                    'Ċ' => 198,
                    '1---system' => 10057,
                    'Ġmessage' => 3275,
                    '2---Ċ' => 198,
                    '3---<' => 27,
                    '4---|' => 91,
                    '5---im' => 320,
                    '6---_' => 62,
                    'end' => 437,
                    '7---|' => 91,
                    '8--->' => 29,
                ],
            ],
            'all messages' => [
                [$message1, $message2, $message3],
                [
                    '<' => 27,
                    '|' => 91,
                    'im' => 320,
                    '_' => 62,
                    'start' => 9688,
                    '0---|' => 91,
                    '>' => 29,
                    'system' => 10057,
                    'Ċ' => 198,
                    '1---system' => 10057,
                    'Ġmessage' => 3275,
                    '2---Ċ' => 198,
                    '3---<' => 27,
                    '4---|' => 91,
                    '5---im' => 320,
                    '6---_' => 62,
                    'end' => 437,
                    '7---|' => 91,
                    '8--->' => 29,

                    '9---Ċ' => 198,
                    '10---<' => 27,
                    '11---|' => 91,
                    '12---im' => 320,
                    '13---_' => 62,
                    '14---start' => 9688,
                    '15---|' => 91,
                    '16--->' => 29,
                    'user' => 7220,
                    'Ġname' => 1438,
                    '=' => 28,
                    'The' => 464,
                    'User' => 12982,
                    '17---Ċ' => 198,
                    '18---user' => 7220,
                    '19---Ġmessage' => 3275,
                    '20---Ċ' => 198,
                    '21---<' => 27,
                    '22---|' => 91,
                    '23---im' => 320,
                    '24---_' => 62,
                    '25---end' => 437,
                    '26---|' => 91,
                    '27--->' => 29,
                    '28---Ċ' => 198,
                    '29---<' => 27,
                    '30---|' => 91,
                    '31---im' => 320,
                    '32---_' => 62,
                    '33---start' => 9688,
                    '34---|' => 91,
                    '35--->' => 29,
                    'ass' => 562,
                    'istant' => 10167,
                    '36---Ġname' => 1438,
                    '37---=' => 28,
                    '38---The' => 464,
                    'Bot' => 20630,
                    '39---Ċ' => 198,
                    'bot' => 13645,
                    '40---Ġmessage' => 3275,
                    '41---Ċ' => 198,
                    '42---<' => 27,
                    '43---|' => 91,
                    '44---im' => 320,
                    '45---_' => 62,
                    '46---end' => 437,
                    '47---|' => 91,
                    '48--->' => 29,
                ],
            ],
            'no messages' => [
                [],
                [],
            ],
        ];
    }

    public function validatePromptDataProvider(): array
    {
        return array_map(function (string $model): array {
            return [
                $model,
                Tokenizer::MAX_TOKENS_MAP[$model],
            ];
        }, array_keys(Tokenizer::MAX_TOKENS_MAP));
    }

    protected function randomString(int $length = 12): string
    {
        $prompt = '';
        for ($i=0; $i<$length; $i++) {
            $char = \rand(0,255);
            $prompt .= (new GptHelper())->utf8Encode(chr($char));
        }

        return $prompt;
    }
}
