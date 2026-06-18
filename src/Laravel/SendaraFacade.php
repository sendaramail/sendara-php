<?php

declare(strict_types=1);

namespace Sendara\Laravel;

use Illuminate\Support\Facades\Facade;
use Sendara\Client;
use Sendara\Resources\ApiKeys;
use Sendara\Resources\Billing;
use Sendara\Resources\Broadcasts;
use Sendara\Resources\Contacts;
use Sendara\Resources\Domains;
use Sendara\Resources\Emails;
use Sendara\Resources\Lists;
use Sendara\Resources\Messages;
use Sendara\Resources\Suppressions;
use Sendara\Resources\Templates;
use Sendara\Resources\Usage;

/**
 * @method static Emails       emails()
 * @method static Broadcasts   broadcasts()
 * @method static Messages     messages()
 * @method static Contacts     contacts()
 * @method static Lists        lists()
 * @method static Domains      domains()
 * @method static Templates    templates()
 * @method static Suppressions suppressions()
 * @method static Usage        usage()
 * @method static ApiKeys      apiKeys()
 * @method static Billing      billing()
 *
 * @see Client
 */
final class SendaraFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }
}
