<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tunnistamo;

use Drupal\Component\Datetime\TimeInterface;

/**
 * A clock frozen at a given time.
 */
final readonly class Time implements TimeInterface {

  /**
   * Constructs a new instance.
   *
   * @param int $now
   *   The time the clock is frozen at.
   */
  public function __construct(private int $now) {
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestTime(): int {
    return $this->now;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestMicroTime(): float {
    return (float) $this->now;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentTime(): int {
    return $this->now;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentMicroTime(): float {
    return (float) $this->now;
  }

}
