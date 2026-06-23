<?php

namespace App\Exceptions;

use Exception;

class LeadBillingException extends Exception
{
    public function __construct(string $message, public readonly string $errorCode = 'payment_required')
    {
        parent::__construct($message);
    }
}
