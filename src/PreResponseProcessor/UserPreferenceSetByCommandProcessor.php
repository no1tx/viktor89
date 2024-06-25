<?php

namespace Perk11\Viktor89\PreResponseProcessor;

use Longman\TelegramBot\Entities\Message;
use Longman\TelegramBot\Request;
use Perk11\Viktor89\Database;

class UserPreferenceSetByCommandProcessor implements PreResponseProcessor
{
    public function __construct(
        private readonly Database $database,
        private readonly array $triggeringCommands,
        private readonly string $preferenceName,
    )
    {
    }

    protected function getValueValidationErrors(?string $value): array
    {
        return [];
    }

    protected function transformValue(string $value): mixed
    {
        if ($value === 'reset' || $value === '') {
            return null;
        }

        return $value;
    }

    public function process(Message $message): false|string|null
    {
        $messageText = $message->getText();
        $triggerFound = false;
        foreach ($this->triggeringCommands as $triggeringCommand) {
            if (str_starts_with($messageText, $triggeringCommand)) {
                $preferenceValue = trim(str_replace($triggeringCommand, '', $messageText));
                $triggerFound = true;
                break;
            }
        }
        if (!$triggerFound) {
            return false;
        }
        $preferenceValue = $this->transformValue($preferenceValue);
        $validationErrors = $this->getValueValidationErrors($preferenceValue);
        if (count($validationErrors) > 0) {
            return "Ошибка: " . implode("\n", $validationErrors);
        }
        $this->database->writeUserPreference($message->getFrom()->getId(), $this->preferenceName, $preferenceValue);

        try {
            $response = Request::execute('setMessageReaction', [
                'chat_id'    => $message->getChat()->getId(),
                'message_id' => $message->getMessageId(),
                'reaction'   => [[
                    'type'  => 'emoji',
                    'emoji' => '👌',
                ]],
                'is_big' => true,
            ]);
            echo "Reacting to message result: $response\n";
        } catch (\Exception $e) {
            echo("Failed to react to message: " . $e->getMessage() . "\n");

            return $preferenceValue === null ? "Настройка $this->preferenceName сброшена в состояние по умолчанию" : "Настройка $this->preferenceName установлена в $preferenceValue";
        }

        return null;
    }

    public function getCurrentPreferenceValue(int $userId): ?string
    {
        return $this->database->readUserPreference($userId, $this->preferenceName);
    }
}
