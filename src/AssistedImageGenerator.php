<?php

namespace Perk11\Viktor89;

use Orhanerday\OpenAi\OpenAi;

class AssistedImageGenerator implements Prompt2ImgGenerator, PromptAndImg2ImgGenerator
{
    private OpenAi $openAi;

    public function __construct(
        private readonly Automatic1111APiClient $automatic1111APiClient,
        private readonly OpenAiCompletionStringParser $openAiCompletionStringParser,
    ) {
        $this->openAi = new OpenAi('');
        $this->openAi->setBaseURL($_ENV['OPENAI_ASSISTANT_SERVER']);
    }

    public function generateByPromptTxt2Img(string $prompt, int $userId): Automatic1111ImageApiResponse
    {
        $improvedPrompt = $this->processPrompt($prompt);
        return $this->automatic1111APiClient->generateByPromptTxt2Img($improvedPrompt, $userId);
    }

    public function generatePromptAndImageImg2Img(
        string $imageContent,
        string $prompt,
        int $userId
    ): Automatic1111ImageApiResponse {
        $improvedPrompt = $this->processPrompt($prompt);
        return $this->automatic1111APiClient->generatePromptAndImageImg2Img($imageContent, $improvedPrompt, $userId);
    }

    private function processPrompt(string $originalPrompt): string
    {
        $systemPrompt = "You are Gemma. Given a message from the user, you expand on the ideas in the message and output text that will be used to generate an image using automatic text to image generator. Your output should contain a literal description of the image. Be brief since long outputs are not handled very well. Your output will be directly passed to Automatic1111 API, so don't output anything extra. Do not use any syntax or code formatting, just output raw text describing the image and nothing else.";
        $prompt = "$systemPrompt\n\nUser: $originalPrompt\nGemma: ";
        return $this->getCompletion($prompt);
    }

    private function getCompletion(string $prompt): string
    {
        $opts = [
            'prompt' => $prompt,
            'stream' => true,
            "stop"   => [
                "</s>",
                "Gemma:",
                "User:",
            ],
        ];
        $fullContent = '';
        $jsonPart = null;
        $this->openAi->completion($opts, function ($curl_info, $data) use (&$fullContent, &$jsonPart) {
            if ($jsonPart === null) {
                $dataToParse = $data;
            } else {
                $dataToParse = $jsonPart . $data;
            }
            try {
                $parsedData = $this->openAiCompletionStringParser->parse($dataToParse);
                $jsonPart = null;
            } catch (JSONException $e) {
                echo "\nIncomplete JSON received, postponing parsing until more is received\n";
                $jsonPart = $dataToParse;

                return strlen($data);
            }
            echo $parsedData['content'];
            $fullContent .= $parsedData['content'];
            if (mb_strlen($fullContent) > 8192) {
                echo "Max length reached, aborting response\n";

                return 0;
            }

            return strlen($data);
        });


        return trim($fullContent);
    }
}
