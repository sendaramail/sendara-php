# Sendara PHP SDK

The official PHP SDK for the [Sendara](https://sendara.dev) email API. Send transactional email, run broadcasts, page through your message history, and verify webhooks — with zero runtime dependencies (just `ext-curl` and `ext-json`).

Requires PHP 8.1+.

## Installation

```bash
composer require sendaramail/sendara
```

## Quickstart

Create a client with your API key, then send an email:

```php
<?php

require 'vendor/autoload.php';

use Sendara\Client;

$client = new Client('sk_live_your_api_key');

$message = $client->emails()->send([
    'from'    => 'hello@yourdomain.com',
    'to'      => 'customer@example.com',
    'subject' => 'Welcome to Acme',
    'html'    => '<h1>Welcome aboard</h1><p>Glad to have you.</p>',
    'text'    => 'Welcome aboard. Glad to have you.',
]);

echo $message['id']; // e.g. "msg_..."
```

The resource accessors are also exposed as lazy, memoized properties, so `$client->emails` is equivalent to `$client->emails()`.

### Client options

The second constructor argument is an options array. All fields are optional:

```php
$client = new Client('sk_live_your_api_key', [
    'baseUrl'    => 'https://api.sendara.dev', // default
    'timeout'    => 30,                          // seconds, default 30
    'maxRetries' => 2,                           // default 2
]);
```

The client automatically:

- Sends `Authorization: Bearer <apiKey>`.
- JSON-encodes request bodies.
- Adds an `Idempotency-Key` to every write (POST/PUT/PATCH) request.
- Retries idempotent requests on `429` and `5xx` responses (and network failures) with exponential backoff, honoring `Retry-After`.

### Send options

`emails()->send()` accepts these keys:

| Key | Type | Description |
| --- | --- | --- |
| `to` | string (required) | Recipient address. |
| `from` | string | Sender address (a verified domain). |
| `subject` | string | Email subject. |
| `html` | string | HTML body. |
| `text` | string | Plain-text body. |
| `templateId` | string | Render a stored template instead of inline body. |
| `templateVars` | array | Variables for the template. |
| `messageType` | string | Message type tag. |
| `metadata` | array | Arbitrary metadata stored with the message. |
| `idempotencyKey` | string | Supply your own key; one is generated if omitted. |

For full control over the request body, use the escape hatch:

```php
$client->emails()->sendRaw([
    'channel'     => 'email',
    'destination' => ['email' => 'customer@example.com'],
    'payload'     => ['subject' => 'Hi', 'body_html' => '<p>Hi</p>'],
]);
```

## Broadcasts

Create, send, and manage broadcasts to a list or an explicit recipient set:

```php
$broadcast = $client->broadcasts()->create([
    'name'           => 'June newsletter',
    'fromEmail'      => 'news@yourdomain.com',
    'subject'        => 'What shipped in June',
    'bodyHtml'       => '<h1>June</h1><p>Here is what is new.</p>',
    'audienceListId' => 'list_123',
]);

$client->broadcasts()->send($broadcast['id']);
```

List, fetch, cancel, and delete:

```php
$broadcasts = $client->broadcasts()->list(['limit' => 20, 'offset' => 0]);
$one        = $client->broadcasts()->get('bcast_123');

$client->broadcasts()->cancel('bcast_123');
$client->broadcasts()->delete('bcast_123');
```

Create and fan out in a single call with `bulkSend()`:

```php
$client->broadcasts()->bulkSend([
    'fromEmail' => 'news@yourdomain.com',
    'subject'   => 'Launch day',
    'bodyHtml'  => '<p>We are live.</p>',
    'recipients' => [
        ['email' => 'a@example.com'],
        ['email' => 'b@example.com'],
    ],
    'sendNow' => true,
]);
```

`create()` and `bulkSend()` accept: `name`, `fromEmail`, `subject`, `bodyHtml`, `bodyText`, `templateId`, `messageType`, `audienceListId`, `recipients`, `scheduledAt`, `sendNow`.

## Messages and pagination

Fetch a single page, including the cursor for the next one:

```php
$page = $client->messages()->page([
    'status' => 'delivered',
    'limit'  => 50,
]);

foreach ($page->messages as $message) {
    echo $message['id'], PHP_EOL;
}

if ($page->hasMore()) {
    $next = $client->messages()->page(['cursor' => $page->nextCursor]);
}
```

Or let the SDK walk every page for you. `list()` returns a `Generator` that auto-paginates through `next_cursor`:

```php
foreach ($client->messages()->list(['status' => 'delivered']) as $message) {
    echo $message['id'], PHP_EOL;
}
```

`page()` and `list()` accept the same filters: `status`, `from`, `to`, `limit`, `cursor`.

Fetch a single message by id:

```php
$message = $client->messages()->get('msg_123');
```

## Webhook verification

Verify the authenticity of an inbound webhook with `Sendara\Webhooks::verify()`. Pass the **raw request body** (the exact bytes received — never a re-encoded object), the request headers, and your signing secret. On success it returns the decoded JSON payload; on failure it throws `WebhookVerificationException`.

```php
<?php

use Sendara\Webhooks;
use Sendara\Exception\WebhookVerificationException;

$rawBody = file_get_contents('php://input');
$secret  = getenv('SENDARA_WEBHOOK_SECRET');

try {
    $event = Webhooks::verify($rawBody, getallheaders(), $secret);
} catch (WebhookVerificationException $e) {
    http_response_code(400);
    exit('Invalid signature');
}

// $event is the decoded payload
switch ($event['event_type'] ?? null) {
    case 'message.delivered':
        // ...
        break;
    case 'message.bounced':
        // ...
        break;
}

http_response_code(200);
```

Verification recomputes `HMAC-SHA256(secret, "<timestamp>.<rawBody>")` and compares it in constant time against the `Sendara-Signature` header. It also rejects requests whose `Sendara-Timestamp` falls outside a tolerance window (default 300 seconds). Pass a fourth argument to change the tolerance, or `0` to disable the timestamp check:

```php
$event = Webhooks::verify($rawBody, $headers, $secret, 600);
```

Headers are matched case-insensitively, so framework-normalized header maps work as-is.

## Error handling

Every non-2xx response throws `Sendara\Exception\ApiException`, which extends `Sendara\Exception\SendaraException`. The API's `{ "error": { "code", "message" } }` envelope is decoded onto the exception:

```php
<?php

use Sendara\Exception\ApiException;
use Sendara\Exception\SendaraException;

try {
    $client->emails()->send([
        'to'      => 'customer@example.com',
        'subject' => 'Hi',
        'html'    => '<p>Hi</p>',
    ]);
} catch (ApiException $e) {
    $e->getStatus();    // HTTP status, e.g. 422
    $e->getErrorCode(); // machine code, e.g. "invalid_request"
    $e->getMessage();   // human-readable message
    $e->getRequestId(); // X-Request-Id, or null
    $e->getRetryAfter(); // seconds from Retry-After, or null
} catch (SendaraException $e) {
    // Configuration, encoding, or network errors (no HTTP response)
}
```

Catch `ApiException` for failed API calls, and `SendaraException` to also cover client-side problems such as a missing API key, JSON encoding failures, or network errors that exhausted retries.

## Laravel

The package ships a service provider and facade and is auto-discovered — no manual registration needed.

Set your credentials in `.env`:

```env
SENDARA_API_KEY=sk_live_your_api_key
# optional overrides:
SENDARA_BASE_URL=https://api.sendara.dev
SENDARA_TIMEOUT=30
SENDARA_MAX_RETRIES=2
```

Optionally publish the config file to `config/sendara.php`:

```bash
php artisan vendor:publish --tag=sendara-config
```

Use the `Sendara` facade anywhere:

```php
<?php

use Sendara\Laravel\SendaraFacade as Sendara;

Sendara::emails()->send([
    'from'    => 'hello@yourdomain.com',
    'to'      => 'customer@example.com',
    'subject' => 'Welcome',
    'html'    => '<h1>Welcome</h1>',
]);

$page = Sendara::messages()->page(['limit' => 25]);
```

The underlying `Sendara\Client` is bound as a singleton, so you can also resolve it via dependency injection:

```php
use Sendara\Client;

class WelcomeMailer
{
    public function __construct(private readonly Client $sendara)
    {
    }

    public function send(string $to): void
    {
        $this->sendara->emails()->send([
            'to'      => $to,
            'subject' => 'Welcome',
            'html'    => '<h1>Welcome</h1>',
        ]);
    }
}
```

The facade and client expose the same resources: `emails`, `broadcasts`, `messages`, `contacts`, `lists`, `domains`, `templates`, `suppressions`, `usage`, `apiKeys`, and `billing`.

## License

MIT
