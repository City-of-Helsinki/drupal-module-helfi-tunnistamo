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
   * Run given response through the http kernel.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The handled response.
   */
  private function getHttpKernelResponse(Request $request) : Response {
    $http_kernel = $this->container->get('http_kernel');
    return $http_kernel->handle($request);
  }

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
    $this->setPluginConfiguration('auto_login', TRUE);
    $request = Request::create('/admin');
    $response = $this->getHttpKernelResponse($request);
    $this->assertInstanceOf(TrustedRedirectResponse::class, $response);
    $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
    $this->assertStringNotContainsString('prompt=none', $response->getTargetUrl());
  }

  /**
   * Tests auto-login when 403 page is served via iframe.
   */
  public function testAutologinRedirectIframe() : void {
    $this->setPluginConfiguration('auto_login', TRUE);
    // Make sure prompt=none is set.
    $request = Request::create('/admin/content');
    $request->headers->set('Sec-Fetch-Dest', 'iframe');
    $response = $this->getHttpKernelResponse($request);
    $this->assertInstanceOf(TrustedRedirectResponse::class, $response);
    $this->assertEquals(Response::HTTP_FOUND, $response->getStatusCode());
    $this->assertStringContainsString('prompt=none', $response->getTargetUrl());
  }

}
