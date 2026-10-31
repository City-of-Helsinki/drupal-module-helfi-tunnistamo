<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo;

/**
 * Thrown when the user was just logged in again, and it did not help.
 */
final class ReauthenticationFailedException extends \LogicException {
}
