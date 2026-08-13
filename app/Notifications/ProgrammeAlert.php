<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ProgrammeAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, scalar|null>  $titleTranslationParameters
     * @param  array<string, scalar|null>  $messageTranslationParameters
     */
    public function __construct(
        public string $title,
        public string $message,
        public string $category,
        public ?string $url = null,
        public ?string $titleTranslationKey = null,
        public ?string $messageTranslationKey = null,
        public array $titleTranslationParameters = [],
        public array $messageTranslationParameters = [],
    ) {
        $this->afterCommit();
    }

    /**
     * @param  array<string, scalar|null>  $titleParameters
     * @param  array<string, scalar|null>  $messageParameters
     */
    public static function translated(string $titleKey, string $messageKey, string $category, array $titleParameters = [], array $messageParameters = [], ?string $url = null): self
    {
        return new self(
            title: $titleKey,
            message: $messageKey,
            category: $category,
            url: $url,
            titleTranslationKey: $titleKey,
            messageTranslationKey: $messageKey,
            titleTranslationParameters: $titleParameters,
            messageTranslationParameters: $messageParameters,
        );
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array{title: string, message: string, category: string, url: string|null} */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->titleTranslationKey !== null
                ? __($this->titleTranslationKey, $this->titleTranslationParameters)
                : $this->title,
            'message' => $this->messageTranslationKey !== null
                ? __($this->messageTranslationKey, $this->messageTranslationParameters)
                : $this->message,
            'category' => $this->category,
            'url' => $this->url,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
