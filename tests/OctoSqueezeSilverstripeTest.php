<?php

namespace OctoSqueeze\Silverstripe\Tests;

use PHPUnit\Framework\TestCase;
use OctoSqueeze\Silverstripe\Octo;
use OctoSqueeze\Silverstripe\Imaginator;
use OctoSqueeze\Silverstripe\Controllers\OctoController;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Config\ConfigAccessor;
use SilverStripe\Control\HTTPRequest;
use ReflectionMethod;

class OctoSqueezeSilverstripeTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Shared setup / teardown
    // -----------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        // Reset all stubbed state between tests
        Environment::reset();
        ConfigAccessor::resetOverrides();

        // Clear any user-agent set by previous tests
        unset($_SERVER['HTTP_USER_AGENT']);
    }

    // =======================================================================
    //  Octo configuration tests
    // =======================================================================

    public function testDefaultCompressionTypeIsBalanced(): void
    {
        $this->assertSame('balanced', Octo::config()->get('oc_compression_type'));
    }

    public function testDefaultMaxFileSizeIs50MB(): void
    {
        $expected = 50 * 1024 * 1024; // 52428800
        $this->assertSame($expected, Octo::config()->get('max_file_size'));
    }

    public function testDefaultAllowedExtensions(): void
    {
        $expected = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $this->assertSame($expected, Octo::config()->get('allowed_extensions'));
    }

    public function testGetRequiredFormatsArrayParsesCommaSeparatedString(): void
    {
        // Default value is 'webp,avif'
        $this->assertSame(['webp', 'avif'], Octo::getRequiredFormatsArray());
    }

    public function testGetRequiredFormatsArrayTrimsWhitespace(): void
    {
        Octo::config()->set('required_formats', ' webp , avif , png ');
        $this->assertSame(['webp', 'avif', 'png'], Octo::getRequiredFormatsArray());
    }

    public function testGetRequiredFormatsArrayAcceptsArrayInput(): void
    {
        Octo::config()->set('required_formats', ['webp', 'avif']);
        $this->assertSame(['webp', 'avif'], Octo::getRequiredFormatsArray());
    }

    public function testGetAllowedExtensionsArrayReturnsDefaultList(): void
    {
        $expected = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $this->assertSame($expected, Octo::getAllowedExtensionsArray());
    }

    public function testGetAllowedExtensionsArrayReflectsOverride(): void
    {
        Octo::config()->set('allowed_extensions', ['png', 'webp']);
        $this->assertSame(['png', 'webp'], Octo::getAllowedExtensionsArray());
    }

    public function testIsConfiguredReturnsFalseWithNoApiKey(): void
    {
        // No env var set => getApiKey() returns null
        $this->assertFalse(Octo::isConfigured());
    }

    public function testIsConfiguredReturnsTrueWithApiKey(): void
    {
        Environment::setEnv('OCTOSQUEEZE_API_KEY', 'test-key-123');
        $this->assertTrue(Octo::isConfigured());
    }

    public function testIsConfiguredReturnsFalseWithEmptyApiKey(): void
    {
        Environment::setEnv('OCTOSQUEEZE_API_KEY', '');
        $this->assertFalse(Octo::isConfigured());
    }

    public function testGetEndpointReturnsDefaultWhenNoEnv(): void
    {
        $this->assertSame('https://api.octosqueeze.com', Octo::getEndpoint());
    }

    public function testGetEndpointReturnsCustomWhenEnvSet(): void
    {
        Environment::setEnv('OCTOSQUEEZE_ENDPOINT', 'https://custom.example.com');
        $this->assertSame('https://custom.example.com', Octo::getEndpoint());
    }

    public function testGetApiKeyReturnsNullWhenUnset(): void
    {
        $this->assertNull(Octo::getApiKey());
    }

    public function testGetApiKeyReturnsValue(): void
    {
        Environment::setEnv('OCTOSQUEEZE_API_KEY', 'sk-12345');
        $this->assertSame('sk-12345', Octo::getApiKey());
    }

    public function testIsDevModeReturnsFalseByDefault(): void
    {
        $this->assertFalse(Octo::isDevMode());
    }

    public function testIsDevModeReturnsTrueForStringTrue(): void
    {
        Environment::setEnv('OCTOSQUEEZE_DEV_ENV', 'true');
        $this->assertTrue(Octo::isDevMode());
    }

    public function testIsDevModeReturnsTrueForStringOne(): void
    {
        Environment::setEnv('OCTOSQUEEZE_DEV_ENV', '1');
        $this->assertTrue(Octo::isDevMode());
    }

    public function testIsDevModeReturnsTrueForBoolTrue(): void
    {
        Environment::setEnv('OCTOSQUEEZE_DEV_ENV', true);
        $this->assertTrue(Octo::isDevMode());
    }

    public function testIsDevModeReturnsFalseForArbitraryString(): void
    {
        Environment::setEnv('OCTOSQUEEZE_DEV_ENV', 'yes');
        $this->assertFalse(Octo::isDevMode());
    }

    public function testDefaultAutoReplaceUrlIsTrue(): void
    {
        $this->assertTrue(Octo::config()->get('autoreplace_url'));
    }

    public function testDefaultAutoReplaceUrlInAdminIsFalse(): void
    {
        $this->assertFalse(Octo::config()->get('autoreplace_url_in_admin'));
    }

    public function testDefaultAutoProcessOnPublishIsTrue(): void
    {
        $this->assertTrue(Octo::config()->get('auto_process_on_publish'));
    }

    // =======================================================================
    //  Imaginator browser-detection tests
    // =======================================================================

    /**
     * Helper: set a fake user agent string.
     */
    private function setUserAgent(string $ua): void
    {
        $_SERVER['HTTP_USER_AGENT'] = $ua;
    }

    // -- Chrome ---------------------------------------------------------------

    public function testChromeSupportsWebP(): void
    {
        // Chrome 90 >= 32 (webp threshold)
        $this->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/90.0.4430.212 Safari/537.36');
        $this->assertTrue(Imaginator::browserCheck('webp'));
    }

    public function testChromeSupportsAvif(): void
    {
        // Chrome 90 >= 85 (avif threshold)
        $this->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/90.0.4430.212 Safari/537.36');
        $this->assertTrue(Imaginator::browserCheck('avif'));
    }

    public function testOldChromeDoesNotSupportAvif(): void
    {
        // Chrome 80 < 85 (avif threshold)
        $this->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/80.0.3987.149 Safari/537.36');
        $this->assertFalse(Imaginator::browserCheck('avif'));
    }

    public function testOldChromeDoesNotSupportWebP(): void
    {
        // Chrome 30 < 32 (webp threshold)
        $this->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/30.0.1599.101 Safari/537.36');
        $this->assertFalse(Imaginator::browserCheck('webp'));
    }

    // -- Safari ---------------------------------------------------------------

    public function testSafari16SupportsWebP(): void
    {
        $this->setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 13_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.1 Safari/605.1.15');
        $this->assertTrue(Imaginator::browserCheck('webp'));
    }

    public function testSafari16_4SupportsAvif(): void
    {
        // Safari 16.4 >= 16.4 (avif threshold)
        $this->setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 13_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.4 Safari/605.1.15');
        $this->assertTrue(Imaginator::browserCheck('avif'));
    }

    public function testSafari15DoesNotSupportAvif(): void
    {
        $this->setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 12_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.0 Safari/605.1.15');
        $this->assertFalse(Imaginator::browserCheck('avif'));
    }

    public function testSafari14DoesNotSupportWebP(): void
    {
        // Safari 14 < 16 (webp threshold)
        $this->setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Safari/605.1.15');
        $this->assertFalse(Imaginator::browserCheck('webp'));
    }

    // -- Firefox ---------------------------------------------------------------

    public function testFirefox93SupportsAvif(): void
    {
        $this->setUserAgent('Mozilla/5.0 (X11; Linux x86_64; rv:93.0) Gecko/20100101 Firefox/93.0');
        $this->assertTrue(Imaginator::browserCheck('avif'));
    }

    public function testFirefox65SupportsWebP(): void
    {
        $this->setUserAgent('Mozilla/5.0 (X11; Linux x86_64; rv:65.0) Gecko/20100101 Firefox/65.0');
        $this->assertTrue(Imaginator::browserCheck('webp'));
    }

    public function testFirefox60DoesNotSupportWebP(): void
    {
        // Firefox 60 < 65 (webp threshold)
        $this->setUserAgent('Mozilla/5.0 (X11; Linux x86_64; rv:60.0) Gecko/20100101 Firefox/60.0');
        $this->assertFalse(Imaginator::browserCheck('webp'));
    }

    public function testFirefox90DoesNotSupportAvif(): void
    {
        // Firefox 90 < 93 (avif threshold)
        $this->setUserAgent('Mozilla/5.0 (X11; Linux x86_64; rv:90.0) Gecko/20100101 Firefox/90.0');
        $this->assertFalse(Imaginator::browserCheck('avif'));
    }

    // -- Edge ------------------------------------------------------------------

    public function testEdge121SupportsAvif(): void
    {
        $this->setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0 Safari/537.36 Edg/121.0');
        $this->assertTrue(Imaginator::browserCheck('avif'));
    }

    public function testEdge18SupportsWebP(): void
    {
        $this->setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0 Safari/537.36 Edge/18.0');
        $this->assertTrue(Imaginator::browserCheck('webp'));
    }

    public function testEdge100DoesNotSupportAvif(): void
    {
        // Edge 100 < 121 (avif threshold)
        $this->setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0 Safari/537.36 Edg/100.0');
        $this->assertFalse(Imaginator::browserCheck('avif'));
    }

    // -- IE --------------------------------------------------------------------

    public function testIEDoesNotSupportWebP(): void
    {
        $this->setUserAgent('Mozilla/5.0 (Windows NT 10.0; Trident/7.0; rv:11.0) like Gecko');
        $this->assertFalse(Imaginator::browserCheck('webp'));
    }

    public function testIEDoesNotSupportAvif(): void
    {
        $this->setUserAgent('Mozilla/5.0 (Windows NT 10.0; Trident/7.0; rv:11.0) like Gecko');
        $this->assertFalse(Imaginator::browserCheck('avif'));
    }

    // -- Opera -----------------------------------------------------------------

    public function testOperaSupportsWebP(): void
    {
        // Opera 71 >= 19 (webp threshold)
        $this->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0 Safari/537.36 OPR/71.0');
        $this->assertTrue(Imaginator::browserCheck('webp'));
    }

    public function testOperaSupportsAvif(): void
    {
        // Opera 71 >= 71 (avif threshold)
        $this->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0 Safari/537.36 OPR/71.0');
        $this->assertTrue(Imaginator::browserCheck('avif'));
    }

    public function testOldOperaDoesNotSupportAvif(): void
    {
        // Opera 60 < 71 (avif threshold)
        $this->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0 Safari/537.36 OPR/60.0');
        $this->assertFalse(Imaginator::browserCheck('avif'));
    }

    // -- No user agent ---------------------------------------------------------

    public function testBrowserCheckReturnsFalseWithNoUserAgent(): void
    {
        // $_SERVER['HTTP_USER_AGENT'] is unset in setUp()
        $this->assertFalse(Imaginator::browserCheck('webp'));
    }

    public function testBrowserCheckReturnsFalseForUnknownBrowser(): void
    {
        $this->setUserAgent('SomeUnknownBot/1.0');
        $this->assertFalse(Imaginator::browserCheck('webp'));
    }

    public function testBrowserCheckReturnsFalseForUnknownFormat(): void
    {
        $this->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/90.0.4430.212 Safari/537.36');
        $this->assertFalse(Imaginator::browserCheck('bmp'));
    }

    // -- imageSupport() -------------------------------------------------------

    public function testImageSupportReturnsEmptyWithNoUserAgent(): void
    {
        $this->assertSame([], Imaginator::imageSupport());
    }

    public function testImageSupportReturnsAvifAndWebPForModernChrome(): void
    {
        $this->setUserAgent('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/100.0 Safari/537.36');
        $support = Imaginator::imageSupport();
        $this->assertContains('avif', $support);
        $this->assertContains('webp', $support);
    }

    public function testImageSupportDetectsFormatFromAcceptHeader(): void
    {
        // A UA that doesn't match any known browser, but contains image/webp
        $this->setUserAgent('SomeBot/1.0 image/webp');
        $support = Imaginator::imageSupport();
        $this->assertContains('webp', $support);
    }

    public function testImageSupportDetectsAvifFromAcceptHeader(): void
    {
        $this->setUserAgent('SomeBot/1.0 image/avif');
        $support = Imaginator::imageSupport();
        $this->assertContains('avif', $support);
    }

    public function testImageSupportReturnsOnlyWebPForOldFirefox(): void
    {
        // Firefox 65 supports webp (>=65) but not avif (<93)
        $this->setUserAgent('Mozilla/5.0 (X11; Linux x86_64; rv:65.0) Gecko/20100101 Firefox/65.0');
        $support = Imaginator::imageSupport();
        $this->assertContains('webp', $support);
        $this->assertNotContains('avif', $support);
    }

    public function testImageSupportReturnsEmptyForIE(): void
    {
        $this->setUserAgent('Mozilla/5.0 (Windows NT 10.0; Trident/7.0; rv:11.0) like Gecko');
        $this->assertSame([], Imaginator::imageSupport());
    }

    // =======================================================================
    //  OctoController security tests (protected methods via reflection)
    // =======================================================================

    /**
     * Get an OctoController instance for testing.
     * Since it extends our stub Controller, no framework init is needed.
     */
    private function makeController(): OctoController
    {
        return new OctoController();
    }

    /**
     * Invoke a protected/private method via reflection.
     */
    private function invokeMethod(object $obj, string $method, array $args = [])
    {
        $ref = new ReflectionMethod($obj, $method);
        $ref->setAccessible(true);
        return $ref->invoke($obj, ...$args);
    }

    // -- isValidFormat() ------------------------------------------------------

    public function testIsValidFormatAcceptsWebP(): void
    {
        $ctrl = $this->makeController();
        $this->assertTrue($this->invokeMethod($ctrl, 'isValidFormat', ['webp']));
    }

    public function testIsValidFormatAcceptsAvif(): void
    {
        $ctrl = $this->makeController();
        $this->assertTrue($this->invokeMethod($ctrl, 'isValidFormat', ['avif']));
    }

    public function testIsValidFormatAcceptsJpeg(): void
    {
        $ctrl = $this->makeController();
        $this->assertTrue($this->invokeMethod($ctrl, 'isValidFormat', ['jpeg']));
    }

    public function testIsValidFormatAcceptsJpg(): void
    {
        $ctrl = $this->makeController();
        $this->assertTrue($this->invokeMethod($ctrl, 'isValidFormat', ['jpg']));
    }

    public function testIsValidFormatAcceptsPng(): void
    {
        $ctrl = $this->makeController();
        $this->assertTrue($this->invokeMethod($ctrl, 'isValidFormat', ['png']));
    }

    public function testIsValidFormatAcceptsGif(): void
    {
        $ctrl = $this->makeController();
        $this->assertTrue($this->invokeMethod($ctrl, 'isValidFormat', ['gif']));
    }

    public function testIsValidFormatIsCaseInsensitive(): void
    {
        $ctrl = $this->makeController();
        $this->assertTrue($this->invokeMethod($ctrl, 'isValidFormat', ['WEBP']));
        $this->assertTrue($this->invokeMethod($ctrl, 'isValidFormat', ['Avif']));
    }

    public function testIsValidFormatRejectsPhp(): void
    {
        $ctrl = $this->makeController();
        $this->assertFalse($this->invokeMethod($ctrl, 'isValidFormat', ['php']));
    }

    public function testIsValidFormatRejectsHtml(): void
    {
        $ctrl = $this->makeController();
        $this->assertFalse($this->invokeMethod($ctrl, 'isValidFormat', ['html']));
    }

    public function testIsValidFormatRejectsExe(): void
    {
        $ctrl = $this->makeController();
        $this->assertFalse($this->invokeMethod($ctrl, 'isValidFormat', ['exe']));
    }

    public function testIsValidFormatRejectsSvg(): void
    {
        $ctrl = $this->makeController();
        $this->assertFalse($this->invokeMethod($ctrl, 'isValidFormat', ['svg']));
    }

    public function testIsValidFormatRejectsEmptyString(): void
    {
        $ctrl = $this->makeController();
        $this->assertFalse($this->invokeMethod($ctrl, 'isValidFormat', ['']));
    }

    // -- sanitizePath() -------------------------------------------------------

    public function testSanitizePathRemovesDirectoryTraversal(): void
    {
        $ctrl = $this->makeController();
        $result = $this->invokeMethod($ctrl, 'sanitizePath', ['uploads/../../../etc/passwd']);
        // '..' should be stripped, leaving 'uploads/etc/passwd' (no traversal)
        $this->assertNotNull($result);
        $this->assertStringNotContainsString('..', $result);
    }

    public function testSanitizePathRemovesLeadingSlashes(): void
    {
        $ctrl = $this->makeController();
        $result = $this->invokeMethod($ctrl, 'sanitizePath', ['/assets/image.webp']);
        $this->assertNotNull($result);
        $this->assertStringStartsNotWith('/', $result);
    }

    public function testSanitizePathRemovesLeadingBackslashes(): void
    {
        $ctrl = $this->makeController();
        $result = $this->invokeMethod($ctrl, 'sanitizePath', ['\\assets\\image.webp']);
        $this->assertNotNull($result);
        $this->assertStringStartsNotWith('\\', $result);
    }

    public function testSanitizePathRemovesNullBytes(): void
    {
        $ctrl = $this->makeController();
        $result = $this->invokeMethod($ctrl, 'sanitizePath', ["assets/image\0.webp"]);
        if ($result !== null) {
            $this->assertStringNotContainsString("\0", $result);
        }
    }

    public function testSanitizePathRejectsSuspiciousCharacters(): void
    {
        $ctrl = $this->makeController();
        // Angle brackets, quotes, pipes, question marks, asterisks should be rejected
        $this->assertNull($this->invokeMethod($ctrl, 'sanitizePath', ['assets/<script>.webp']));
        $this->assertNull($this->invokeMethod($ctrl, 'sanitizePath', ['assets/image|test.webp']));
        $this->assertNull($this->invokeMethod($ctrl, 'sanitizePath', ['assets/image?.webp']));
        $this->assertNull($this->invokeMethod($ctrl, 'sanitizePath', ['assets/image*.webp']));
        $this->assertNull($this->invokeMethod($ctrl, 'sanitizePath', ['assets/"test".webp']));
    }

    public function testSanitizePathAcceptsValidPath(): void
    {
        $ctrl = $this->makeController();
        $result = $this->invokeMethod($ctrl, 'sanitizePath', ['assets/Uploads/photo.webp']);
        $this->assertSame('assets/Uploads/photo.webp', $result);
    }

    public function testSanitizePathAcceptsNestedValidPath(): void
    {
        $ctrl = $this->makeController();
        $result = $this->invokeMethod($ctrl, 'sanitizePath', ['assets/Uploads/2024/01/photo.avif']);
        $this->assertSame('assets/Uploads/2024/01/photo.avif', $result);
    }

    // -- verifyWebhookSignature() ---------------------------------------------

    public function testVerifyWebhookSignatureReturnsFalseWithNoSecret(): void
    {
        $ctrl = $this->makeController();
        $request = new HTTPRequest('POST', '/octosqueeze/completed');
        $this->assertFalse($this->invokeMethod($ctrl, 'verifyWebhookSignature', [$request]));
    }

    public function testVerifyWebhookSignatureReturnsFalseWithNoHeader(): void
    {
        Environment::setEnv('OCTOSQUEEZE_WEBHOOK_SECRET', 'test-secret');
        $ctrl = $this->makeController();
        $request = new HTTPRequest('POST', '/octosqueeze/completed');
        $request->setBody('{"test": true}');
        $this->assertFalse($this->invokeMethod($ctrl, 'verifyWebhookSignature', [$request]));
    }

    public function testVerifyWebhookSignatureAcceptsValidSignature(): void
    {
        $secret = 'webhook-secret-key';
        $body = '{"images": "[{\\"image_id\\": 1}]"}';

        Environment::setEnv('OCTOSQUEEZE_WEBHOOK_SECRET', $secret);

        $expectedSignature = hash_hmac('sha256', $body, $secret);

        $request = new HTTPRequest('POST', '/octosqueeze/completed');
        $request->setBody($body);
        $request->addHeader('X-OctoSqueeze-Signature', $expectedSignature);

        $ctrl = $this->makeController();
        $this->assertTrue($this->invokeMethod($ctrl, 'verifyWebhookSignature', [$request]));
    }

    public function testVerifyWebhookSignatureRejectsTamperedPayload(): void
    {
        $secret = 'webhook-secret-key';
        $originalBody = '{"images": "[{\\"image_id\\": 1}]"}';
        $tamperedBody = '{"images": "[{\\"image_id\\": 999}]"}';

        Environment::setEnv('OCTOSQUEEZE_WEBHOOK_SECRET', $secret);

        // Sign the original body
        $signature = hash_hmac('sha256', $originalBody, $secret);

        // Send the tampered body with the original signature
        $request = new HTTPRequest('POST', '/octosqueeze/completed');
        $request->setBody($tamperedBody);
        $request->addHeader('X-OctoSqueeze-Signature', $signature);

        $ctrl = $this->makeController();
        $this->assertFalse($this->invokeMethod($ctrl, 'verifyWebhookSignature', [$request]));
    }

    public function testVerifyWebhookSignatureRejectsWrongSecret(): void
    {
        $body = '{"data": "test"}';

        Environment::setEnv('OCTOSQUEEZE_WEBHOOK_SECRET', 'correct-secret');

        // Sign with a different secret
        $wrongSignature = hash_hmac('sha256', $body, 'wrong-secret');

        $request = new HTTPRequest('POST', '/octosqueeze/completed');
        $request->setBody($body);
        $request->addHeader('X-OctoSqueeze-Signature', $wrongSignature);

        $ctrl = $this->makeController();
        $this->assertFalse($this->invokeMethod($ctrl, 'verifyWebhookSignature', [$request]));
    }

    public function testVerifyWebhookSignatureRejectsEmptyBody(): void
    {
        Environment::setEnv('OCTOSQUEEZE_WEBHOOK_SECRET', 'test-secret');

        $request = new HTTPRequest('POST', '/octosqueeze/completed');
        // Body is null/empty, and no postVars either => http_build_query returns ''
        $request->addHeader('X-OctoSqueeze-Signature', 'some-signature');

        $ctrl = $this->makeController();
        // Empty body => falls through to postVars fallback => http_build_query([]) => ''
        // HMAC of '' with the secret will not match 'some-signature'
        $this->assertFalse($this->invokeMethod($ctrl, 'verifyWebhookSignature', [$request]));
    }

    public function testVerifyWebhookSignatureFallsBackToPostVars(): void
    {
        $secret = 'test-secret';
        $postVars = ['images' => '{"test":true}'];
        $body = http_build_query($postVars);

        Environment::setEnv('OCTOSQUEEZE_WEBHOOK_SECRET', $secret);
        $expectedSignature = hash_hmac('sha256', $body, $secret);

        $request = new HTTPRequest('POST', '/octosqueeze/completed', [], $postVars);
        // Do NOT set body explicitly, so getBody() returns null => fallback to postVars
        $request->addHeader('X-OctoSqueeze-Signature', $expectedSignature);

        $ctrl = $this->makeController();
        $this->assertTrue($this->invokeMethod($ctrl, 'verifyWebhookSignature', [$request]));
    }

    // -- jsonResponse() -------------------------------------------------------

    public function testJsonResponseSetsCorrectStatusCode(): void
    {
        $ctrl = $this->makeController();
        $response = $this->invokeMethod($ctrl, 'jsonResponse', [['error' => 'test'], 401]);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testJsonResponseSetsContentTypeHeader(): void
    {
        $ctrl = $this->makeController();
        $response = $this->invokeMethod($ctrl, 'jsonResponse', [['success' => true]]);
        $this->assertSame('application/json', $response->getHeader('Content-Type'));
    }

    public function testJsonResponseEncodesBody(): void
    {
        $ctrl = $this->makeController();
        $data = ['success' => true, 'count' => 3];
        $response = $this->invokeMethod($ctrl, 'jsonResponse', [$data]);
        $this->assertSame(json_encode($data), $response->getBody());
    }

    public function testJsonResponseDefaultStatusIs200(): void
    {
        $ctrl = $this->makeController();
        $response = $this->invokeMethod($ctrl, 'jsonResponse', [['ok' => true]]);
        $this->assertSame(200, $response->getStatusCode());
    }
}
