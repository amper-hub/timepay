<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class PasswordResetOtpDeliveryFailed extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('The password reset code could not be delivered.', 0, $previous);
    }
}
