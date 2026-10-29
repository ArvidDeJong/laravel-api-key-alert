<?php

namespace Darvis\LaravelApiKeyAlert\Support;

enum CheckStatus: string
{
    /** The key was accepted. */
    case Ok = 'ok';

    /** The key was rejected; an alert went out unless one did within the throttle window. */
    case Failed = 'failed';

    /** The service has no check, or its key is not set. */
    case Skipped = 'skipped';

    /** No answer, or an answer that says nothing about the key, such as a 500. */
    case Inconclusive = 'inconclusive';
}
