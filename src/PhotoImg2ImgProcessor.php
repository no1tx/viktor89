<?php

namespace Perk11\Viktor89;

use Longman\TelegramBot\Entities\Message;
use Longman\TelegramBot\Request;

class PhotoImg2ImgProcessor
{
    public function __construct(
        private readonly TelegramPhotoDownloader $telegramPhotoDownloader,
        private readonly Automatic1111APiClient $automatic1111APiClient,
        private readonly PhotoResponder $photoResponder,
    ) {
    }

    public function processPhoto(Message $message): void
    {
        $caption = $message->getCaption();
        echo "Photo received with caption $caption\n";
        if (!str_contains($caption, '@' . $_ENV['TELEGRAM_BOT_USERNAME'])) {
            return;
        }

        $prompt = trim(
            str_replace(
                '@' . $_ENV['TELEGRAM_BOT_USERNAME'],
                '',
                $message->getCaption()
            )
        );
        if ($prompt === '') {
            return;
        }
        echo "Generating img2img for prompt: $prompt\n";
        Request::execute('setMessageReaction', [
            'chat_id'    => $message->getChat()->getId(),
            'message_id' => $message->getMessageId(),
            'reaction'   => [
                [
                    'type'  => 'emoji',
                    'emoji' => '👀',
                ],
            ],
        ]);
        try {
            $photo = $this->telegramPhotoDownloader->downloadPhotoFromMessage($message);
            $transformedPhotoResponse = $this->automatic1111APiClient->generatePromptAndImageImg2Img(
                $photo,
                $prompt,
                $message->getFrom()->getId(),
            );
            $this->photoResponder->sendPhoto(
                $message,
                $transformedPhotoResponse->getFirstImageAsPng(),
                $transformedPhotoResponse->getCaption(),
            );
        } catch (\Exception $e) {
            echo "Failed to generate image:\n" . $e->getMessage(),
            Request::execute('setMessageReaction', [
                'chat_id'    => $message->getChat()->getId(),
                'message_id' => $message->getMessageId(),
                'reaction'   => [
                    [
                        'type'  => 'emoji',
                        'emoji' => '🤔',
                    ],
                ],
            ]);
        }
    }
}
