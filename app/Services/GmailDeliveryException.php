<?php

namespace App\Services;

use RuntimeException;

// Messages contain only application-defined diagnostics, never raw provider data.
class GmailDeliveryException extends RuntimeException
{
}
