<?php

namespace Perk11\Viktor89\Quiz;

use Longman\TelegramBot\Entities\PollOption;
use Longman\TelegramBot\Request;
use Perk11\Viktor89\InternalMessage;
use Perk11\Viktor89\TelegramChainBasedResponderInterface;

class RandomQuizResponder implements TelegramChainBasedResponderInterface
{

    public function __construct(private readonly QuestionRepository $questionRepository)
    {
    }

    public function getResponseByMessageChain(array $messageChain): ?InternalMessage
    {
        /** @var ?InternalMessage $lastMessage */
        $lastMessage = $messageChain[count($messageChain) - 1];
        $question = $this->questionRepository->findRandom();
        if ($question === null) {
            echo "Failed to find a random question\n";

            return null;
        }
        $options = [];
        $answerIndex = 0;
        $answers = $question->answers;
        shuffle($answers);
        foreach ($answers as $answer) {
            $options[] = new PollOption([
                                            'text' => $answer->text,
                                        ]);
            if ($answer->correct) {
                $correctAnswerIndex = $answerIndex;
            }
            $answerIndex++;
        }
        if (!isset($correctAnswerIndex)) {
            throw new \Exception("Question " . $question->id . " does not have a correct answer");
        }
        $pollData = [
            'question'            => $question->getTextWithAuthor() . "\n\nЧтобы добавить свой вопрос, присылайте quiz-опрос мне в лс!",
            'chat_id'             => $lastMessage->chatId,
            'reply_to_message_id' => $lastMessage->id,
            'options'             => $options,
            'type'                => 'quiz',
            'correct_option_id'   => $correctAnswerIndex,
            'is_anonymous'        => false,
        ];
        if ($question->explanation !== null) {
            $pollData['explanation'] = $question->explanation;
        }
        Request::sendPoll($pollData);
        return null;
    }
}
