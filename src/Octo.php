<?php

namespace OctoSqueeze\Silverstripe;

use SilverStripe\Core\Config\Configurable;

/**
 * OctoSqueeze SilverStripe Configuration
 *
 * Configuration can be set in your project's YAML config:
 *
 * OctoSqueeze\Silverstripe\Octo:
 *   autoreplace_url: true
 *   autoreplace_url_in_admin: false
 *   required_formats: 'webp,avif'
 *   oc_compression_type: 'balanced'
 *
 * Or via environment variables:
 * - OCTOSQUEEZE_API_KEY: Your API key (required)
 * - OCTOSQUEEZE_ENDPOINT: Custom API endpoint (optional, for development)
 * - OCTOSQUEEZE_DEV_ENV: Set to 'true' to enable development mode (disables SSL verification)
 */
class Octo
{
    use Configurable;

    /**
     * Whether to automatically replace image URLs with optimized versions
     * when images are rendered on the frontend
     *
     * @config
     * @var bool
     */
    private static $autoreplace_url = true;

    /**
     * Whether to also replace image URLs in the CMS admin interface
     * Usually disabled as it can cause issues with asset management
     *
     * @config
     * @var bool
     */
    private static $autoreplace_url_in_admin = false;

    /**
     * Comma-separated list of output formats to generate
     * Supported: webp, avif
     *
     * @config
     * @var string
     */
    private static $required_formats = 'webp,avif';

    /**
     * Compression type/mode for OctoSqueeze API
     * Options: 'size' (smallest), 'balanced' (recommended), 'quality' (best quality)
     *
     * @config
     * @var string
     */
    private static $oc_compression_type = 'balanced';

    /**
     * Maximum file size in bytes that will be processed
     * Default: 50MB (50 * 1024 * 1024)
     *
     * @config
     * @var int
     */
    private static $max_file_size = 52428800;

    /**
     * Whether to process images automatically on publish
     *
     * @config
     * @var bool
     */
    private static $auto_process_on_publish = true;

    /**
     * File extensions that should be processed
     *
     * @config
     * @var array
     */
    private static $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * Get the API endpoint URL
     *
     * @return string
     */
    public static function getEndpoint(): string
    {
        $env = \SilverStripe\Core\Environment::getEnv('OCTOSQUEEZE_ENDPOINT');
        return $env ?: 'https://api.octosqueeze.com';
    }

    /**
     * Get the API key
     *
     * @return string|null
     */
    public static function getApiKey(): ?string
    {
        return \SilverStripe\Core\Environment::getEnv('OCTOSQUEEZE_API_KEY');
    }

    /**
     * Check if development mode is enabled
     *
     * @return bool
     */
    public static function isDevMode(): bool
    {
        $env = \SilverStripe\Core\Environment::getEnv('OCTOSQUEEZE_DEV_ENV');
        return $env === 'true' || $env === '1' || $env === true;
    }

    /**
     * Check if the module is properly configured
     *
     * @return bool
     */
    public static function isConfigured(): bool
    {
        return !empty(static::getApiKey());
    }

    /**
     * Get allowed extensions as array
     *
     * @return array
     */
    public static function getAllowedExtensionsArray(): array
    {
        return static::config()->get('allowed_extensions');
    }

    /**
     * Get required formats as array
     *
     * @return array
     */
    public static function getRequiredFormatsArray(): array
    {
        $formats = static::config()->get('required_formats');
        return array_map('trim', explode(',', $formats));
    }
}
