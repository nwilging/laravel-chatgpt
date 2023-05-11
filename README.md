# Laravel ChatGPT

Super simple wrapper for openai-php/client with error handling.
Specifically for ChatGPT conversations.

![Tests](https://github.com/nwilging/laravel-chatgpt/actions/workflows/main-branch.yml/badge.svg?branch=main)
![Coverage](./.github/coverage-badge.svg)
[![Latest Stable Version](http://poser.pugx.org/nwilging/laravel-chatgpt/v)](https://packagist.org/packages/nwilging/laravel-chatgpt)
[![License](http://poser.pugx.org/nwilging/laravel-chatgpt/license)](https://packagist.org/packages/nwilging/laravel-chatgpt)
[![Total Downloads](http://poser.pugx.org/nwilging/laravel-chatgpt/downloads)](https://packagist.org/packages/nwilging/laravel-chatgpt)

---

### About

This package is a very simple wrapper for interacting with OpenAI Chat Completions (ChatGPT).
A common problem with larger conversations is "too many tokens", which happens when a prompt
is sent to the API that contains a number of tokens greater than the specified model's
token limit.

This package will attempt to prune messages from the conversation starting from the beginning,
so that the most recent conversation context still exists in the prompt. Additionally, if an
"initial prompt" or other "system" level instruction message is required, this message will
be locked to the top of the message stack so that it is always the first message.

---

# Installation

### Pre Requisites
1. Laravel v8+
2. PHP 7.4+
3. OpenAI API Key

### Install with Composer
```
composer require nwilging/laravel-chatgpt
```

## Configuration

Two things must be configured for this package to work:
1. OpenAI API key
2. OpenAI Tokenizer

### .env setup

First, [get an API key](https://platform.openai.com/docs/api-reference/introduction)
from OpenAI.

Add this key to your `.env` as:
```
OPENAI_API_KEY=sk_your-key
```

### Tokenizer Setup

To publish the tokenizer files to `storage/app/openai_tokenizer`:
```
php artisan vendor:publish --provider=Nwilging\\LaravelChatGpt\\Providers\\ChatGptServiceProvider
```

This will add 3 files to the `storage/app/openai_tokenizer` directory:
1. `characters.json`
2. `encoder.json`
3. `vocab.bpe`

These files **must be present** for the tokenizer to work! It is best to commit these files
to your codebase since they are relatively small. You may also need to add the following to
your `storage/app/.gitignore`:
```
!openai_tokenizer/*.json
!openai_tokenizer/*.bpe
```

## Usage

You may use this package to execute chat completions while automatically pruning message
payloads that are too large for the given OpenAI model. Additionally you may use each
component separately, for example if you wish to tokenize a prompt.

### The `ChatCompletionMessage` Model

This is a helper model that must be used to generate chat completions. Since the chat completion
API [supports message objects](https://platform.openai.com/docs/api-reference/chat/create), this
class exists to help build lists of those message objects.

Example:
```php
use Nwilging\LaravelChatGpt\Models\ChatCompletionMessage;

$message1 = new ChatCompletionMessage();
$message2 = new ChatCompletionMessage();

$message1->role = ChatCompletionMessage::ROLE_SYSTEM;
$message1->name = 'system';
$message1->content = 'Initial prompt provided by system.';

$message2->role = ChatCompletionMessage::ROLE_USER;
$message2->name = 'username';
$message2->content = 'The user\'s message';
```

These messages may be sent in an array to the `ChatGptService`.

### Automatic Chat Completions

Send any number of messages to the `ChatGptService` and automatically generate a chat
completion based on the conversation context, automatically pruning messages from the 
top of the stack in the event of a token exceeded exception.

Example:
```php
use Nwilging\LaravelChatGpt\Contracts\Services\ChatGptServiceContract;

$service = app(ChatGptServiceContract::class);

// Use the messages from above!
$messages = [$message1, $message2];

$model = 'gpt-3.5-turbo';

// Create a completion:
$result = $service->createChat($model, $messages);

// Create a completion that retains the initial prompt:
$result = $service->createChatRetainInitialPrompt($model, $messages);
```

In the above example, `createChat` will prune messages from the top of the stack
when the payload is too large, disregarding the initial prompt.

If the initial prompt helps define parameters for the entire conversation, you should
retain it in the payload. Use `createChatRetainInitialPrompt` to do this.

### Tokenizer

The Tokenizer is very similar to OpenAI's tokenizer and can be used to extract tokens
from a prompt. This can be used to determine number of tokens in a prompt, etc.

The tokenizer has the ability to tokenize an array of `ChatCompletionMessage`s, or just
tokenize a basic string prompt.

**Tokenizing Prompts:**
```php
use Nwilging\LaravelChatGpt\Helpers\Tokenizer;

$tokenizer = app(Tokenizer::class);
$prompt = 'this is a test prompt!';

$tokens = $tokenizer->tokenize($prompt);
dd($tokens);
/**
 * Output:
 * [
 *  "this" => 5661
 *  "Ġis" => 318
 *  "Ġa" => 257
 *  "Ġtest" => 1332
 *  "Ġprompt" => 6152
 *  "!" => 0
 * ]
 */

// Get token count:
$numberOfTokens = count($tokens);
```

Tokenizing `ChatCompletionMessage`s is slightly more complicated. The tokenizer will wrap
each message in ChatGPT directives denoting messages and their attributes. This differs
from simple prompt tokenization since messages themselves are more complex than simply
text -- e.g. they include a role, username, and the message content.

For **bot** and **user** messages, the format is as follows:
```
<|im_start|>role name=username
message content
<|im_end|>
```

For user messages:
```
<|im_start|>user name=TheUserName
hello this is a message from a user!
<|im_end|>
```

For bot messages:
```
<|im_start|>bot name=TheBotUsername
response from chatgpt!
<|im_end|>
```

Finally, `system` messages are treated slightly differently:
```
<|im_start|>system
This is a system message
<|im_end|>
```

The resulting formatted messages are what will be tokenized.
