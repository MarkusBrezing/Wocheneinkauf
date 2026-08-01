<?php

namespace App\Core\Services;

use RuntimeException;

/**
 * Die Einladung war beim Einloesen bereits widerrufen, abgelaufen oder
 * aufgebraucht.
 */
class InvitationNotRedeemable extends RuntimeException
{
    public function __construct(string $message = 'Diese Einladung ist nicht mehr gültig.')
    {
        parent::__construct($message);
    }
}
