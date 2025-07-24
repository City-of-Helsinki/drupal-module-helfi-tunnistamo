<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo\Session;

use Drupal\Core\Session\SessionConfiguration;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Decorates the session configuration service.
 */
#[AsDecorator(decorates: 'session_configuration')]
final class Configuration extends SessionConfiguration {

  /**
   * Default cookie lifetime (36 hours).
   */
  public const COOKIE_LIFETIME = 129600;

  /**
   * Constructs a new instance.
   *
   * @param array $options
   *   The options.
   */
  public function __construct(
    #[Autowire('%session.storage.options%')] array $options,
  ) {
    $options['cookie_lifetime'] = self::COOKIE_LIFETIME;

    parent::__construct($options);
  }

}
