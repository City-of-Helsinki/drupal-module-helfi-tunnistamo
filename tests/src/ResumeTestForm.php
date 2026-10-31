<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tunnistamo;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\State\StateInterface;
use Drupal\helfi_tunnistamo\ReauthenticatorInterface;

/**
 * Form for testing the reauthenticator.
 */
final class ResumeTestForm extends FormBase {

  use AutowireTrait;

  public function __construct(
    protected readonly StateInterface $state,
    protected readonly ReauthenticatorInterface $reauthenticator,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'helfi_tunnistamo_resume_test';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $argument
   *   A build argument.
   *
   * @return array<string, mixed>
   *   The form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $argument = ''): array {
    $form['value'] = ['#type' => 'textfield'];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Submit',
    ];
    $form['actions']['other'] = [
      '#type' => 'submit',
      '#value' => 'Other',
      '#submit' => ['::submitOther'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->state->set('helfi_tunnistamo_test.submitted', [
      'button' => 'submit',
      'value' => $form_state->getValue('value'),
      'args' => $form_state->getBuildInfo()['args'],
    ]);

    // Pretend the access token is expired.
    if ($form_state->getValue('value') === 'expired') {
      $form_state->setResponse($this->reauthenticator->reauthenticateAndResubmit($form_state));
      return;
    }

    $form_state->setResponse(new TrustedRedirectResponse('https://example.com/' . $form_state->getValue('value')));
  }

  /**
   * Submit handler of the other button.
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitOther(array &$form, FormStateInterface $form_state): void {
    $this->state->set('helfi_tunnistamo_test.submitted', [
      'button' => 'other',
      'value' => $form_state->getValue('value'),
      'args' => $form_state->getBuildInfo()['args'],
    ]);
  }

}
