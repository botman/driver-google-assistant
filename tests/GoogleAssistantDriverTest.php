<?php

namespace Tests;

use Mockery as m;
use BotMan\BotMan\Http\Curl;
use PHPUnit_Framework_TestCase;
use BotMan\BotMan\Messages\Outgoing\Question;
use Symfony\Component\HttpFoundation\Request;
use BotMan\BotMan\Drivers\Events\GenericEvent;
use Symfony\Component\HttpFoundation\Response;
use BotMan\Drivers\GoogleAssistant\GoogleAssistantDriver;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;

class GoogleAssistantDriverTest extends PHPUnit_Framework_TestCase
{
    private function getDriver($responseData, $htmlInterface = null)
    {
        $request = Request::create('', 'POST', [], [], [], [
            'Content-Type: application/json',
        ], $responseData);
        if ($htmlInterface === null) {
            $htmlInterface = m::mock(Curl::class);
        }

        return new GoogleAssistantDriver($request, [], $htmlInterface);
    }

    private function getValidDriver($htmlInterface = null, $intent = '${INTENTNAME}', $queryText = '${QUERYTEXT}')
    {
        $responseData = '{
  "responseId": "c4b863dd-aafe-41ad-a115-91736b665cb9",
  "queryResult": {
    "queryText": "'.$queryText.'",
    "action": "input.welcome",
    "parameters": {},
    "allRequiredParamsPresent": true,
    "fulfillmentText": "",
    "fulfillmentMessages": [],
    "outputContexts": [
      {
        "name": "projects/${PROJECTID}/agent/sessions/${SESSIONID}/contexts/google_assistant_welcome"
      },
      {
        "name": "projects/${PROJECTID}/agent/sessions/${SESSIONID}/contexts/actions_capability_screen_output"
      },
      {
        "name": "projects/${PROJECTID}/agent/sessions/${SESSIONID}/contexts/google_assistant_input_type_voice"
      },
      {
        "name": "projects/${PROJECTID}/agent/sessions/${SESSIONID}/contexts/actions_capability_audio_output"
      },
      {
        "name": "projects/${PROJECTID}/agent/sessions/${SESSIONID}/contexts/actions_capability_web_browser"
      },
      {
        "name": "projects/${PROJECTID}/agent/sessions/${SESSIONID}/contexts/actions_capability_media_response_audio"
      }
    ],
    "intent": {
      "name": "${INTENTID}",
      "displayName": "'.$intent.'"
    },
    "intentDetectionConfidence": 1,
    "diagnosticInfo": {},
    "languageCode": "en-us"
  },
  "originalDetectIntentRequest": {
    "source": "google",
    "version": "2",
    "payload": {
      "isInSandbox": true,
      "surface": {
        "capabilities": [
          {
            "name": "actions.capability.SCREEN_OUTPUT"
          },
          {
            "name": "actions.capability.AUDIO_OUTPUT"
          },
          {
            "name": "actions.capability.WEB_BROWSER"
          },
          {
            "name": "actions.capability.MEDIA_RESPONSE_AUDIO"
          }
        ]
      },
      "inputs": [
        {
          "rawInputs": [
            {
              "query": "Talk to my test app",
              "inputType": "VOICE"
            }
          ],
          "intent": "actions.intent.MAIN"
        }
      ],
      "user": {
        "lastSeen": "2018-03-16T22:08:48Z",
        "permissions": [
          "UPDATE"
        ],
        "locale": "en-US",
        "userId": "${USERID}"
      },
      "conversation": {
        "conversationId": "${SESSIONID}",
        "type": "NEW"
      },
      "availableSurfaces": [
        {
          "capabilities": [
            {
              "name": "actions.capability.SCREEN_OUTPUT"
            },
            {
              "name": "actions.capability.AUDIO_OUTPUT"
            }
          ]
        }
      ]
    }
  },
  "session": "projects/${PROJECTID}/agent/sessions/${SESSIONID}"
}';

        return $this->getDriver($responseData, $htmlInterface);
    }

    /** @test */
    public function it_returns_the_driver_name()
    {
        $driver = $this->getDriver(null);
        $this->assertSame('GoogleAssistant', $driver->getName());
    }

    /** @test */
    public function it_matches_the_request()
    {
        $driver = $this->getDriver(null);
        $this->assertFalse($driver->matchesRequest());

        $driver = $this->getValidDriver();
        $this->assertTrue($driver->matchesRequest());
    }

    /** @test */
    public function it_returns_the_message_object()
    {
        $driver = $this->getValidDriver();
        $this->assertTrue(is_array($driver->getMessages()));
    }

    /** @test */
    public function it_returns_the_messages_by_reference()
    {
        $driver = $this->getValidDriver();
        $hash = spl_object_hash($driver->getMessages()[0]);

        $this->assertSame($hash, spl_object_hash($driver->getMessages()[0]));
    }

    /** @test */
    public function it_returns_the_intent()
    {
        $driver = $this->getValidDriver();
        $this->assertSame('${INTENTNAME}', $driver->getMessages()[0]->getText());
    }

    /** @test */
    public function it_detects_bots()
    {
        $driver = $this->getValidDriver();
        $this->assertFalse($driver->isBot());
    }

    /** @test */
    public function it_returns_the_user_id()
    {
        $driver = $this->getValidDriver();
        $this->assertSame('${USERID}', $driver->getMessages()[0]->getSender());
    }

    /** @test */
    public function it_returns_the_channel_id()
    {
        $driver = $this->getValidDriver();
        $this->assertSame('projects/${PROJECTID}/agent/sessions/${SESSIONID}', $driver->getMessages()[0]->getRecipient());
    }

    /** @test */
    public function it_returns_the_user_object()
    {
        $driver = $this->getValidDriver();

        $message = $driver->getMessages()[0];
        $user = $driver->getUser($message);

        $this->assertSame('${USERID}', $user->getId());
        $this->assertNull($user->getFirstName());
        $this->assertNull($user->getLastName());
        $this->assertNull($user->getUsername());
    }

    /** @test */
    public function it_has_extras()
    {
        $driver = $this->getValidDriver();

        /** @var IncomingMessage $message */
        $message = $driver->getMessages()[0];

        $this->assertSame('${QUERYTEXT}', $message->getExtras('queryText'));
        $this->assertSame('${INTENTNAME}', $message->getExtras('intent'));
        $this->assertSame('input.welcome', $message->getExtras('action'));
        $this->assertEquals([], $message->getExtras('parameters'));
        $this->assertSame('en-us', $message->getExtras('languageCode'));
    }

    /** @test */
    public function it_is_configured()
    {
        $driver = $this->getValidDriver();
        $this->assertTrue($driver->isConfigured());
    }

    /** @test */
    public function it_can_build_payload()
    {
        $driver = $this->getValidDriver();

        $incomingMessage = new IncomingMessage('text', '123456', '987654');

        $message = 'string';
        $payload = $driver->buildServicePayload($message, $incomingMessage);

        $this->assertSame([
            'text' => 'string',
        ], $payload);

        $message = new OutgoingMessage('message object');
        $payload = $driver->buildServicePayload($message, $incomingMessage);

        $this->assertSame([
            'text' => 'message object',
        ], $payload);

        $message = new Question('question object');
        $payload = $driver->buildServicePayload($message, $incomingMessage);

        $this->assertSame([
            'text' => 'question object',
        ], $payload);

    }

    /** @test */
    public function it_can_send_payload()
    {
        $driver = $this->getValidDriver();

        $payload = [
            'text' => 'text message',
        ];

        /** @var Response $response */
        $response = $driver->sendPayload($payload);
        $this->assertSame('{"payload":{"google":{"richResponse":{"items":[{"simpleResponse":{"textToSpeech":"text message"}}]}}}}', $response->getContent());
    }

    /** @test */
    public function it_fires_welcome_event()
    {
        $driver = $this->getValidDriver(null, 'Welcome', GoogleAssistantDriver::GOOGLE_ASSISTANT_WELCOME);
        $event = $driver->hasMatchingEvent();
        $this->assertInstanceOf(GenericEvent::class, $event);
        $this->assertSame(GoogleAssistantDriver::GOOGLE_ASSISTANT_WELCOME, $event->getName());
    }

    /** @test */
    public function it_fires_no_events_for_regular_messages()
    {
        $driver = $this->getValidDriver();

        $this->assertFalse($driver->hasMatchingEvent());
    }

    /** @test */
    public function it_can_get_conversation_answers()
    {
        $driver = $this->getValidDriver();

        $incomingMessage = new IncomingMessage('text', '123456', '987654');
        $answer = $driver->getConversationAnswer($incomingMessage);

        $this->assertSame('text', $answer->getText());
    }
}
