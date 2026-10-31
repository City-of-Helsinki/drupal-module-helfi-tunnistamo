<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;

/**
 * Submits a form again after the user has logged in again.
 *
 * @see \Drupal\helfi_tunnistamo\ReauthenticatorInterface::reauthenticateAndResubmit()
 */
final readonly class FormResubmitter {

  public function __construct(
    private FormBuilderInterface $formBuilder,
  ) {
  }

  /**
   * Submits the form programmatically.
   *
   * @param class-string<\Drupal\Core\Form\FormInterface> $formClass
   *   The form class.
   * @param array<string, mixed> $values
   *   The submitted values.
   * @param array<mixed> $buildArgs
   *   Build arguments of the form.
   *
   * @return \Symfony\Component\HttpFoundation\Response|null
   *   The response the form set, if any.
   */
  public function resubmit(string $formClass, array $values, array $buildArgs) : ?Response {
    $formState = (new FormState())
      ->setValues($values)
      ->addBuildInfo('args', $buildArgs);

    $this->formBuilder->submitForm($formClass, $formState);

    if ($response = $formState->getResponse()) {
      return $response;
    }

    $redirect = $formState->getRedirect();
    if ($redirect instanceof Url) {
      return new LocalRedirectResponse($redirect->setAbsolute()->toString());
    }

    return $redirect instanceof Response ? $redirect : NULL;
  }

}
