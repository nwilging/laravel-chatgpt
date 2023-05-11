<?php
declare(strict_types=1);

namespace Nwilging\LaravelChatGpt\Providers;

use Illuminate\Support\ServiceProvider;
use Nwilging\LaravelChatGpt\Contracts\Services\ChatGptServiceContract;
use Nwilging\LaravelChatGpt\Services\ChatGptService;
use OpenAI\Client;
use OpenAI;

class ChatGptServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/openai.php', 'openai');
        $this->publishes([
            __DIR__ . '/../../config/storage/openai_tokenizer' => storage_path('app/openai_tokenizer'),
        ], 'laravel-chatgpt');
    }

    public function register(): void
    {
        $this->app->bind(Client::class, function (): Client {
            return OpenAI::client(config('openai.api_key'));
        });

        $this->app->bind(ChatGptServiceContract::class, ChatGptService::class);
    }
}
