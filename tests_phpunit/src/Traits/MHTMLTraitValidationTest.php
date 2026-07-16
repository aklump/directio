<?php

namespace AKlump\Directio\Tests\Traits;

use AKlump\Directio\Exception\AuthenticationRequiredException;
use AKlump\Directio\Traits\MHTMLTrait;
use AKlump\FixtureFramework\Exception\FixtureException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \AKlump\Directio\Exception\AuthenticationRequiredException
 * @covers \AKlump\Directio\Traits\MHTMLTrait
 */
class MHTMLTraitValidationTest extends TestCase {

  private function getTraitInstance() {
    return new class {
      use MHTMLTrait {
        validateMainResponseForMhtml as public;
      }
    };
  }

  public function testAuthenticationRequiredException() {
    $e = new AuthenticationRequiredException('https://example.com/admin', 'https://example.com/user/login', 302, 'Redirected');
    $this->assertEquals('https://example.com/admin', $e->getUrl());
    $this->assertEquals('https://example.com/user/login', $e->getFinalUrl());
    $this->assertEquals(302, $e->getStatusCode());
    $this->assertEquals('Redirected', $e->getReason());
    $this->assertStringContainsString('https://example.com/admin', $e->getMessage());
    $this->assertStringContainsString('Redirected', $e->getMessage());
  }

  public function testValidateMainResponseThrowsOn403() {
    $this->expectException(FixtureException::class);
    $this->expectExceptionMessage('Access denied (403)');
    $this->getTraitInstance()->validateMainResponseForMhtml('https://example.com/admin', [
      'status_code' => 403,
      'final_url' => 'https://example.com/admin',
      'body' => 'Access Denied',
    ]);
  }

  public function testValidateMainResponseThrowsAuthRequiredOn403WithLoginForm() {
    $this->expectException(AuthenticationRequiredException::class);
    $this->expectExceptionMessage('looks like a login page');
    $this->getTraitInstance()->validateMainResponseForMhtml('https://example.com/admin', [
      'status_code' => 403,
      'final_url' => 'https://example.com/admin',
      'body' => '<html><form id="user-login-form"></form></html>',
    ]);
  }

  public function testValidateMainResponseThrowsAuthRequiredOn401() {
    $this->expectException(AuthenticationRequiredException::class);
    $this->expectExceptionMessage('HTTP 401');
    $this->getTraitInstance()->validateMainResponseForMhtml('https://example.com/admin', [
      'status_code' => 401,
      'final_url' => 'https://example.com/admin',
    ]);
  }

  public function testValidateMainResponseThrowsAuthRequiredOnLoginRedirect() {
    $this->expectException(AuthenticationRequiredException::class);
    $this->expectExceptionMessage('redirected to the login page');
    $this->getTraitInstance()->validateMainResponseForMhtml('https://example.com/admin/reports', [
      'status_code' => 200,
      'final_url' => 'https://example.com/user/login?destination=/admin/reports',
      'body' => 'Login Page',
    ]);
  }

  public function testValidateMainResponseThrowsAuthRequiredOnFailedLoginRequest() {
    $this->expectException(AuthenticationRequiredException::class);
    $this->expectExceptionMessage('login page was returned instead of the requested destination');
    $this->getTraitInstance()->validateMainResponseForMhtml('https://example.com/user/login?destination=/admin', [
      'status_code' => 200,
      'final_url' => 'https://example.com/user/login?destination=/admin',
      'body' => '<html><form id="user-login-form"></form></html>',
    ]);
  }

  public function testValidateMainResponsePassesOnSuccessfulLogin() {
    $this->getTraitInstance()->validateMainResponseForMhtml('https://example.com/user/login?destination=/admin', [
      'status_code' => 200,
      'final_url' => 'https://example.com/admin',
      'body' => 'Admin Dashboard',
    ]);
    $this->addToAssertionCount(1);
  }

  public function testValidateMainResponsePassesOnNormalRequest() {
    $this->getTraitInstance()->validateMainResponseForMhtml('https://example.com/page', [
      'status_code' => 200,
      'final_url' => 'https://example.com/page',
      'body' => 'Normal Page',
    ]);
    $this->addToAssertionCount(1);
  }

  public function testValidateMainResponseThrowsFixtureExceptionOn500() {
    $this->expectException(FixtureException::class);
    $this->expectExceptionMessage('HTTP 500');
    $this->getTraitInstance()->validateMainResponseForMhtml('https://example.com/broken', [
      'status_code' => 500,
      'final_url' => 'https://example.com/broken',
    ]);
  }
}
