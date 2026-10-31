<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Url;
use Drupal\helfi_tunnistamo\Controller\ResumeController;
use Drupal\helfi_tunnistamo\Reauthenticator;
use Drupal\helfi_tunnistamo\ReauthenticatorInterface;
use Drupal\helfi_tunnistamo\ReauthenticationFailedException;
use Drupal\Tests\helfi_tunnistamo\ResumeTestForm;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests logging the user in again and resuming the action.
 */
#[Group('helfi_tunnistamo')]
#[RunTestsInSeparateProcesses]
class ReauthenticatorTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('openid_connect');
    $this->setPluginConfiguration('environment_url', 'https://localhost');
  }

  /**
   * Sets up a logged in user with a Tunnistamo session.
   */
  private function setupTunnistamoUser(): int {
    $this->setupSession(['access' => 'access-token']);
    return (int) $this->setUpCurrentUser()->id();
  }

  /**
   * Gets the reauthenticator.
   */
  private function sut(): ReauthenticatorInterface {
    return $this->container->get(ReauthenticatorInterface::class);
  }

  /**
   * Runs the resume controller.
   */
  private function resume(): Response {
    $request = $this->container->get('request_stack')->getCurrentRequest();
    return ResumeController::create($this->container)->resume($request);
  }

  /**
   * Asserts the user is sent to log in with the connect operation.
   */
  private function assertLoginRedirect(Response $response, int $uid): void {
    $this->assertStringStartsWith('https://localhost/authorization?', (string) $response->headers->get('Location'));

    $oidcSession = $this->container->get('openid_connect.session');
    $this->assertSame(['op' => 'connect', 'uid' => $uid], $oidcSession->retrieveOp(FALSE));
    $this->assertSame(
      ltrim(Url::fromRoute('helfi_tunnistamo.resume')->toString(), '/'),
      $oidcSession->retrieveDestination(FALSE)['destination'],
    );
  }

  /**
   * Tests users who can't log in again.
   */
  public function testRequirements(): void {
    // Anonymous user.
    $this->setupSession(['access' => 'access-token']);
    $this->assertLogicException(fn () => $this->sut()->reauthenticate(), 'Only logged in users');

    // User did not log in with Tunnistamo.
    $this->setupSession();
    $this->setUpCurrentUser();
    $this->assertLogicException(fn () => $this->sut()->reauthenticate(), 'did not log in with Tunnistamo');

    // Client is disabled.
    $this->setupTunnistamoUser();
    $this->getPluginConfiguration()->set('status', FALSE)->save();
    $this->assertLogicException(fn () => $this->sut()->reauthenticate(), 'not enabled');

    $this->assertNull($this->session->get(Reauthenticator::RESUME_SESSION_KEY));
  }

  /**
   * Tests redirecting the user after the login.
   */
  public function testReauthenticate(): void {
    $uid = $this->setupTunnistamoUser();

    $this->assertLoginRedirect($this->sut()->reauthenticate(Url::fromUri('internal:/some/path')), $uid);

    $response = $this->resume();
    $this->assertSame('/some/path', $response->headers->get('Location'));

    // Each login resumes once.
    $response = $this->resume();
    $this->assertSame(Url::fromRoute('<front>')->toString(), $response->headers->get('Location'));
  }

  /**
   * Tests resubmitting a form.
   */
  public function testReauthenticateAndResubmit(): void {
    $uid = $this->setupTunnistamoUser();
    $formBuilder = $this->container->get('form_builder');
    $state = $this->container->get('state');

    // The access token is expired, the user is sent to log in.
    $formState = (new FormState())->setValues(['value' => 'expired']);
    $formBuilder->submitForm(ResumeTestForm::class, $formState, 'arg');
    $this->assertLoginRedirect($formState->getResponse(), $uid);

    // The form is submitted again, and the access token is still expired.
    // The user is not sent to log in again.
    try {
      $this->resume();
      $this->fail('Expected exception was not thrown.');
    }
    catch (ReauthenticationFailedException) {
    }
    $this->assertSame(['button' => 'submit', 'value' => 'expired', 'args' => ['arg']], $state->get('helfi_tunnistamo_test.submitted'));

    // The form is submitted with the same button and values.
    $this->session->remove(Reauthenticator::REAUTHENTICATED_SESSION_KEY);
    $formState = (new FormState())->setValues(['value' => 'ok', 'op' => 'Other']);
    $formState->setFormObject(ResumeTestForm::create($this->container));
    $formState->addBuildInfo('args', ['arg']);
    $this->sut()->reauthenticateAndResubmit($formState);
    $state->delete('helfi_tunnistamo_test.submitted');
    $fallback = $this->container->get(RedirectDestinationInterface::class)->get();
    // The form does not set a response, so the user is sent back to the page
    // the form was submitted on.
    $this->assertSame($fallback, $this->resume()->headers->get('Location'));
    $this->assertSame(['button' => 'other', 'value' => 'ok', 'args' => ['arg']], $state->get('helfi_tunnistamo_test.submitted'));

    // The response of the form is returned.
    $this->session->remove(Reauthenticator::REAUTHENTICATED_SESSION_KEY);
    $formState = (new FormState())->setValues(['value' => 'done']);
    $formState->setFormObject(ResumeTestForm::create($this->container));
    $this->sut()->reauthenticateAndResubmit($formState);
    $this->assertSame('https://example.com/done', $this->resume()->headers->get('Location'));
  }

  /**
   * Tests that the user is not logged in again right after a login.
   */
  public function testLoopGuard(): void {
    $this->setupTunnistamoUser();

    $this->sut()->reauthenticate();
    $this->resume();

    $this->expectException(ReauthenticationFailedException::class);
    $this->sut()->reauthenticate();
  }

  /**
   * Tests that the connected message of openid_connect is removed.
   */
  public function testConnectedMessageIsRemoved(): void {
    $this->setupTunnistamoUser();
    $messenger = $this->container->get('messenger');

    $this->sut()->reauthenticate();
    $messenger->addStatus('Account successfully connected with tunnistamo.');
    $messenger->addStatus('Other message');
    $this->resume();

    $this->assertEquals(['Other message'], array_map('strval', $messenger->messagesByType(MessengerInterface::TYPE_STATUS)));
  }

  /**
   * Asserts that the callback throws \LogicException.
   */
  private function assertLogicException(callable $callback, string $message): void {
    try {
      $callback();
      $this->fail('Expected exception was not thrown.');
    }
    catch (\LogicException $e) {
      $this->assertStringContainsString($message, $e->getMessage());
    }
  }

}
