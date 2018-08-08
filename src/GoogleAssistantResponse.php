<?php

namespace BotMan\Drivers\GoogleAssistant;


class GoogleAssistantResponse
{
    const TYPE_PLAIN_TEXT = 'PlainText';

    protected $text;

    public function respondText(string $text)
    {
        $this->text = $text;

        return $this;
    }

    /**
     *
     * @see https://developers.google.com/actions/build/json/dialogflow-webhook-json
     *
     * @return array
     */
    public function render() : array
    {
        return [
            'payload' => (object) [
                'google' => (object) [
                    'richResponse' => (object) [
                        'items' => [
                            (object) [
                                'simpleResponse' => [
                                    'textToSpeech' => $this->text
                                ]
                            ],
                        ]
                    ]
                ]
            ]
        ];
    }
}