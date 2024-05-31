<?php

namespace Perk11\Viktor89;

use Longman\TelegramBot\Entities\Message;
use Orhanerday\OpenAi\OpenAi;
use SQLite3;

class SiepatchNonInstruct5 implements TelegramResponderInterface
{
    private const CONTEXT_MESSAGES_COUNT = 5;
    private OpenAi $openAi;

    private array $personalityByUser = [];

    private array $videos = [
        'https://www.youtube.com/watch?v=JdGgys-QQdE',
        'https://www.youtube.com/watch?v=2oe_7IRb_rI',
        'https://www.youtube.com/watch?v=_L0QyGE4nJM',
        'https://www.youtube.com/watch?v=KvHSQkTQpX8',
        'https://www.youtube.com/watch?v=krt2AXyXHHE',
        'https://www.youtube.com/watch?v=WDaNJW_jEBo',
        'https://www.youtube.com/watch?v=8EM5R3VkaWI',
        'https://www.youtube.com/watch?v=HvGsbZ1e2sw',
        'https://www.youtube.com/watch?v=qCljI3cIObU',
        'https://www.youtube.com/watch?v=5P6ADakiwcg',

    ];

    public function __construct(private Database $database)
    {
        $this->openAi = new OpenAi('');
        $this->openAi->setBaseURL($_ENV['OPENAI_SERVER']);
    }

    private function getCompletion(string $prompt): string
    {
//        $prompt = mb_substr($prompt, -1024);
        $opts = [
            'prompt'            => $prompt,
            'temperature'       => 0.6,
            'cache_prompt'      => false,
            'repeat_penalty'    => 1.18,
            'repeat_last_n'     => 4096,
            "penalize_nl"       => true,
            "top_k"             => 40,
            "top_p"             => 0.95,
            "min_p"             => 0.1,
            "tfs_z"             => 1,
//        "max_tokens"        => 150,
            "frequency_penalty" => 0,
            "presence_penalty"  => 0,
            "stream"            => true,
            "stop"              => [
                "<human>",
                "<bot>",
            ],
        ];
        $fullContent = '';
        try {
            $this->openAi->completion($opts, function ($curl_info, $data) use (&$fullContent) {
                $parsedData = parse_completion_string($data);
                echo $parsedData['content'];
                $fullContent .= $parsedData['content'];
                if (mb_strlen($fullContent) > 4096) {
                    return 0;
                }

                return strlen($data);
            });
        } catch (\Exception $e) {
        }

        return trim($fullContent);
    }

    public function getResponseByMessage(Message $message): string
    {
        $incomingMessageText = $message->getText();
        if ($incomingMessageText === null) {
            echo "Warning, empty message text!\n";

            return 'Твое сообщение было пустым';
        }
        if (str_starts_with($incomingMessageText, '/personality')) {
            $personality = trim(str_replace('/personality', '', $incomingMessageText));
            if ($personality === 'reset' || $personality === '') {
                unset ($this->personalityByUser[$message->getFrom()->getId()]);

                return 'Теперь я буду тебе отвечать как случайный пользователь';
            }
            $this->personalityByUser[$message->getFrom()->getId()] = $personality;

            return 'Теперь я буду тебе отвечать как ' . $personality;
        }
        $userName = $message->getFrom()->getFirstName();
        if ($message->getFrom()->getLastName() !== null) {
            $userName .= ' ' . $message->getFrom()->getLastName();
        }
        $userName = str_replace(' ', '_', $userName);
        $previousMessages = $this->getPreviousMessages($message);
        $prompt = '';
        foreach ($previousMessages as $previousMessage) {
            $prompt .= "<bot>: [{$previousMessage->userName}] {$previousMessage->messageText}\n";
        }
        $prompt .= "<bot>: [$userName] $incomingMessageText\n<bot>: [";
        if (array_key_exists($message->getFrom()->getId(), $this->personalityByUser)) {
            $personality = str_replace(' ', '_', $this->personalityByUser[$message->getFrom()->getId()]);
            $prompt .= "{$personality}] ";
        }
        echo $prompt;

//        $this->chatsByUser[$message->getFrom()->getId()] = mb_substr($this->chatsByUser[$message->getFrom()->getId()], -512);

        $response = trim($this->getCompletion($prompt));
//        $response = $this->checkForBadResponse($response, $message, $toAddToPrompt);
//        $response = $this->checkForBadResponse($response, $message, $toAddToPrompt);
//        $response = $this->checkForBadResponse($response, $message, $toAddToPrompt);
//        $response = $this->checkForBadResponse($response, $message, $toAddToPrompt);
//        $response = $this->checkForBadResponse($response, $message, $toAddToPrompt);
//        $response = $this->checkForBadResponse($response, $message, $toAddToPrompt);

        if (!array_key_exists($message->getFrom()->getId(), $this->personalityByUser)) {
            $response =  '[отвечает ' . $response;
        }
//        echo $addToChat;
        $youtube_pattern = '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/';
        $response = preg_replace($youtube_pattern, $this->videos[array_rand($this->videos)], $response);

        return $response;
    }

    private function checkForBadResponse(string $response, Message $message, string $resetText): string
    {
        $responseAfterAuthor = mb_substr($response, strpos($response, ']') + 1);
        if (str_contains($this->chatsByUser[$message->getFrom()->getId()], $responseAfterAuthor)) {
            //avoid repetitions
            $this->chatsByUser[$message->getFrom()->getId()] = $resetText;
            return $this->getResponse($message);
        }
        if (str_ends_with($response, ']') || str_contains(mb_strtolower($response), 'не умею') || str_contains(mb_strtolower($response), 'не могу')) {
            return $this->getResponse($message);
        }

        return $response;
    }

    private function getResponse(Message $message): string
    {
        return trim($this->getCompletion($this->chatsByUser[$message->getFrom()->getId()]));
    }

    /** @return InternalMessage[] */
    private function getPreviousMessages(Message $message): array
    {
        $messages = [];
        if ($message->getReplyToMessage() !== null) {
            $responseMessage = $this->database->findMessageByIdInChat($message->getReplyToMessage()->getMessageId(), $message->getChat()->getId());
            if ($responseMessage !== null) {
                $messages[] = $responseMessage;
                while (count($messages) < self::CONTEXT_MESSAGES_COUNT - 1 && $responseMessage?->replyToMessageId !== null) {
                    $responseMessage = $this->database->findMessageByIdInChat(
                        $responseMessage->replyToMessageId,
                        $message->getChat()->getId()
                    );
                    $messages[] = $responseMessage;
                }
            }
        }
        $messagesFromHistoryNumber = self::CONTEXT_MESSAGES_COUNT - count($messages) - 1;
        if ($messagesFromHistoryNumber > 0) {
            $messagesFromHistory = $this->database->findNPreviousMessagesInChat($message->getChat()->getId(), $message->getMessageId(), $messagesFromHistoryNumber);
            $messages = array_merge($messages, $messagesFromHistory);
        }

        return array_reverse($messages);
    }
}