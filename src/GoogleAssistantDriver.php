<?php

namespace BotMan\Drivers\GoogleAssistant;

use BotMan\BotMan\Drivers\Events\GenericEvent;
use BotMan\BotMan\Users\User;
use Illuminate\Support\Collection;
use BotMan\BotMan\Drivers\HttpDriver;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\Question;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use BotMan\BotMan\Interfaces\DriverEventInterface;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;

class GoogleAssistantDriver extends HttpDriver
{
    const DRIVER_NAME = 'GoogleAssistant';
    const GOOGLE_ASSISTANT_WELCOME = 'GOOGLE_ASSISTANT_WELCOME';
    const ACTION_INPUT_UNKNOWN = 'input.unknown';

    protected $messages = [];

    /**
     * @param Request $request
     */
    public function buildPayload(Request $request)
    {
        $this->payload = Collection::make((array) json_decode($request->getContent(), true));
        $this->event = Collection::make((array) $this->payload->get('queryResult'));
        $this->config = Collection::make($this->config->get('google-assistant', []));
    }

    /**
     * @param IncomingMessage $matchingMessage
     * @return \BotMan\BotMan\Users\User
     */
    public function getUser(IncomingMessage $matchingMessage)
    {
        return new User($matchingMessage->getSender());
    }

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        return $this->event->has('queryText') && $this->payload->has('originalDetectIntentRequest');
    }

    /**
     * @param  IncomingMessage $message
     * @return \BotMan\BotMan\Messages\Incoming\Answer
     */
    public function getConversationAnswer(IncomingMessage $message)
    {
        return Answer::create($message->getText())->setMessage($message);
    }

    /**
     * Retrieve the chat message.
     *
     * @return array
     */
    public function getMessages()
    {
        if (empty($this->messages)) {
            $intent = $this->event->get('intent')['displayName'];
            $session = $this->payload->get('session');
            $user = $this->payload->get('originalDetectIntentRequest')['payload']['user'] ?? null;

            $message = new IncomingMessage($intent, $user ? $user['userId'] : $session, $session, $this->payload);

            $message->addExtras('queryText', $this->event->get('queryText'));
            $message->addExtras('intent', $intent);
            $message->addExtras('action', $this->event->get('action'));
            $message->addExtras('parameters', $this->event->get('parameters'));
            $message->addExtras('languageCode', $this->event->get('languageCode'));

            $this->messages = [$message];
        }

        return $this->messages;
    }

    /**
     * @return bool|DriverEventInterface
     */
    public function hasMatchingEvent()
    {
        $text = $this->event->get('queryText');
        if ($text === self::GOOGLE_ASSISTANT_WELCOME) {
            $event = new GenericEvent($this->event);
            $event->setName($text);
            return $event;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isBot()
    {
        return false;
    }

    /**
     * @param string|Question|IncomingMessage $message
     * @param IncomingMessage $matchingMessage
     * @param array $additionalParameters
     * @return Response
     */
    public function buildServicePayload($message, $matchingMessage, $additionalParameters = [])
    {
        $parameters = $additionalParameters;

        if ($message instanceof Question) {
            $text = $message->getText();
        } elseif ($message instanceof OutgoingMessage) {
            $text = $message->getText();
        } else {
            $text = $message;
        }

        $parameters['text'] = $text;

        return $parameters;
    }

    /**
     * @param mixed $payload
     * @return Response
     */
    public function sendPayload($payload)
    {
        $response = new GoogleAssistantResponse();
        if (is_string($payload['text'])) {
            $response->respondText($payload['text']);
        }

        return Response::create(json_encode($response->render()))->send();
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return true;
    }

    /**
     * Low-level method to perform driver specific API requests.
     *
     * @param string $endpoint
     * @param array $parameters
     * @param IncomingMessage $matchingMessage
     * @return Response
     */
    public function sendRequest($endpoint, array $parameters, IncomingMessage $matchingMessage)
    {
        //
    }
}
