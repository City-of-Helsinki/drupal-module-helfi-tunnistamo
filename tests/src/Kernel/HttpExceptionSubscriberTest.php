<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_tunnistamo\Kernel;

use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests http exception subscriber.
 *
 * @group helfi_tunnistamo
 */
class HttpExceptionSubscriberTest extends KernelTestBase {

  /**
   * Make sure no redirect response is sent when auto-login is not enabled.
   */
  public function testAutologinDisabled() : void {
    $request = Request::create('/admin');
    $response = $this->getHttpKernelResponse($request);
    $this->assertInstanceOf(HtmlResponse::class, $response);
    $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
  }

  /**
   * Tests auto-login on 403 pages.
   */
  public function testAutologinRedirect() : void {
    $this->setupEndpoints();
    $this->setPluginConfiguration('auto_login', TRUE);
    $request = Request::create('/admin');
    $response = $this->getHttpKernelResponse($request);
    $this->assertInstanceOf(TrustedRedirectResponse::class, $response);
    $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
  }

}
