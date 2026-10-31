<?php

declare(strict_types=1);

namespace Drupal\helfi_tunnistamo\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Utility\CallableResolver;
use Drupal\helfi_tunnistamo\Reauthenticator;
use Drupal\helfi_tunnistamo\TokenManager;
use Drupal\openid_connect\OpenIDConnectClientEntityInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resumes the action that needed the user to log in again.
 *
 * @see \Drupal\helfi_tunnistamo\ReauthenticatorInterface
 */
final class ResumeController extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'callable_resolver')]
    private readonly CallableResolver $callableResolver,
    private readonly TimeInterface $time,
  ) {
  }

  /**
   * Resumes the action.
   */
  public function resume(Request $request) : Response {
    $session = $request->getSession();

    // The reauthenticator sets the entry, so nothing can be resumed with a
    // link. Each login resumes at most once.
    $entry = $session->remove(Reauthenticator::RESUME_SESSION_KEY);
    if (!is_array($entry)) {
      return $this->noCache(new LocalRedirectResponse(Url::fromRoute('<front>')->toString()));
    }

    $this->removeConnectedMessage();

    // Start the loop guard before anything else can start a new login.
    $session->set(Reauthenticator::REAUTHENTICATED_SESSION_KEY, (int) $this->time->getCurrentTime());

    if (is_string($entry['callback'] ?? NULL)) {
      $callback = $this->callableResolver->getCallableFromDefinition($entry['callback']);
      $response = $callback(...($entry['arguments'] ?? []));

      if ($response instanceof Response) {
        return $response;
      }
    }

    $fallback = is_string($entry['fallback'] ?? NULL) ? $entry['fallback'] : Url::fromRoute('<front>')->toString();
    return $this->noCache(new LocalRedirectResponse($fallback));
  }

  /**
   * Removes the message openid_connect shows after the connect operation.
   *
   * The user did not ask to connect anything, so the message is confusing.
   *
   * @see \Drupal\openid_connect\Controller\OpenIDConnectRedirectController::authenticate()
   */
  private function removeConnectedMessage() : void {
    $client = $this->entityTypeManager()
      ->getStorage('openid_connect_client')
      ->load(TokenManager::CLIENT_ID);

    if (!$client instanceof OpenIDConnectClientEntityInterface) {
      return;
    }

    $plugin = $client->getPlugin();
    // Messenger stores messages as rendered markup.
    $connected = (string) $this->t('Account successfully connected with @provider.', [
      '@provider' => $plugin->getProviderSlug() ?: $plugin->getParentEntityId(),
    ]);

    $messages = $this->messenger()->deleteByType(MessengerInterface::TYPE_STATUS);
    foreach ($messages as $message) {
      if ((string) $message !== $connected) {
        $this->messenger()->addStatus($message);
      }
    }
  }

  /**
   * Prevents caching the response.
   */
  private function noCache(LocalRedirectResponse $response) : LocalRedirectResponse {
    $response->getCacheableMetadata()->setCacheMaxAge(0);
    return $response;
  }

}
