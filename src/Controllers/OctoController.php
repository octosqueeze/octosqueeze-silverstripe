<?php

namespace OctoSqueeze\Silverstripe\Controllers;

use OctoSqueeze\Silverstripe\Octo;
use SilverStripe\Core\Environment;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use Symfony\Component\Filesystem\Filesystem;
use OctoSqueeze\Silverstripe\Models\ImageConversion;
use OctoSqueeze\Silverstripe\Models\ImageCompression;

class OctoController extends Controller
{
    private static $url_handlers = [
        'POST completed' => 'completed',
    ];

    private static $allowed_actions = [
        'completed',
    ];

    /**
     * Allowed image formats for output
     */
    private static $allowed_formats = ['jpeg', 'jpg', 'png', 'webp', 'avif', 'gif'];

    protected function init()
    {
        parent::init();
    }

    /**
     * Verify HMAC signature from OctoSqueeze webhook
     *
     * @param HTTPRequest $request
     * @return bool
     */
    protected function verifyWebhookSignature(HTTPRequest $request): bool
    {
        $webhookSecret = Environment::getEnv('OCTOSQUEEZE_WEBHOOK_SECRET');

        // Secret must be configured
        if (empty($webhookSecret)) {
            return false;
        }

        // Get signature from header
        $signature = $request->getHeader('X-OctoSqueeze-Signature');
        if (empty($signature)) {
            return false;
        }

        // Get raw body for signature verification
        $body = $request->getBody();
        if (empty($body)) {
            // Fallback to reconstructing body from POST vars
            $body = http_build_query($request->postVars());
        }

        // Calculate expected signature
        $expectedSignature = hash_hmac('sha256', $body, $webhookSecret);

        // Constant-time comparison to prevent timing attacks
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Validate image format against allowlist
     *
     * @param string $format
     * @return bool
     */
    protected function isValidFormat(string $format): bool
    {
        return in_array(strtolower($format), self::$allowed_formats, true);
    }

    /**
     * Validate and sanitize file path to prevent directory traversal
     *
     * @param string $path
     * @return string|null Returns sanitized path or null if invalid
     */
    protected function sanitizePath(string $path): ?string
    {
        // Remove any directory traversal attempts
        $path = str_replace(['..', "\0"], '', $path);

        // Remove leading slashes
        $path = ltrim($path, '/\\');

        // Ensure path doesn't contain suspicious patterns
        if (preg_match('/[<>:"|?*]/', $path)) {
            return null;
        }

        // Resolve the full path and ensure it's within PUBLIC_PATH
        $fullPath = realpath(PUBLIC_PATH) . '/' . $path;
        $realPublicPath = realpath(PUBLIC_PATH);

        // Check that the resolved path is still within PUBLIC_PATH
        // Note: We can't use realpath() on the full path yet as file may not exist
        $normalizedPath = preg_replace('#/+#', '/', $fullPath);
        if (strpos($normalizedPath, $realPublicPath) !== 0) {
            return null;
        }

        return $path;
    }

    /**
     * Handle completed compression webhook
     */
    public function completed(HTTPRequest $request)
    {
        // Security: Verify webhook signature
        if (!$this->verifyWebhookSignature($request)) {
            return $this->jsonResponse(['error' => 'Invalid signature'], 401);
        }

        $images = $request->postVar('images');
        if (empty($images)) {
            return $this->jsonResponse(['error' => 'Missing images data'], 400);
        }

        $decoded = json_decode($images, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($decoded)) {
            return $this->jsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $item = current($decoded);
        if (empty($item['image_id'])) {
            return $this->jsonResponse(['error' => 'Missing image_id'], 400);
        }

        // Validate image_id is numeric
        $imageId = filter_var($item['image_id'], FILTER_VALIDATE_INT);
        if ($imageId === false) {
            return $this->jsonResponse(['error' => 'Invalid image_id'], 400);
        }

        $fs = new Filesystem();

        $conversion = ImageConversion::get()->filter('id', $imageId)->first();

        if ($conversion && $conversion->getURL())
        {
            $expl = explode('.', $conversion->getURL());
            $ext = last($expl);
            $path = current($expl);

            if (empty($item['compressions']) || !is_array($item['compressions'])) {
                return $this->jsonResponse(['error' => 'Missing compressions'], 400);
            }

            foreach ($item['compressions'] as $compression)
            {
                // Validate format
                if (empty($compression['format']) || !$this->isValidFormat($compression['format'])) {
                    continue; // Skip invalid formats
                }

                // Validate link is a proper URL
                if (empty($compression['link']) || !filter_var($compression['link'], FILTER_VALIDATE_URL)) {
                    continue;
                }

                // Only allow HTTPS URLs
                if (strpos($compression['link'], 'https://') !== 0) {
                    continue;
                }

                if (!$conversion->Compressions()->filter(['Format' => $compression['format'], 'Size' => $compression['size']])->exists())
                {
                    // Fetch image with SSL verification enabled
                    $image = file_get_contents($compression['link']);

                    if ($image === false) {
                        continue; // Failed to download, skip
                    }

                    $file = $path . '.' . $compression['format'];

                    // Security: Sanitize and validate file path
                    $sanitizedPath = $this->sanitizePath($file);
                    if ($sanitizedPath === null) {
                        continue; // Invalid path, skip
                    }

                    $fs->dumpFile(PUBLIC_PATH . '/' . $sanitizedPath, $image);

                    $record = ImageCompression::create();
                    $record->Format = $compression['format'];
                    $record->Size = $compression['size'];
                    $record->write();

                    $conversion->Compressions()->add($record);
                }
            }

            $conversion->Stage = 2;
            $conversion->write();
        }

        return $this->jsonResponse(['success' => true]);
    }

    /**
     * Helper to return JSON response
     */
    protected function jsonResponse(array $data, int $status = 200): HTTPResponse
    {
        $response = HTTPResponse::create();
        $response->setStatusCode($status);
        $response->addHeader('Content-Type', 'application/json');
        $response->setBody(json_encode($data));
        return $response;
    }
}
