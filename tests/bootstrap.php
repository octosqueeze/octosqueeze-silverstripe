<?php

/**
 * Bootstrap for pure PHP unit tests without the full SilverStripe framework.
 *
 * Provides minimal stubs for SilverStripe classes so that plugin source files
 * can be loaded and tested in isolation.
 */

// ---------------------------------------------------------------------------
// SilverStripe Environment + Convert stubs
// ---------------------------------------------------------------------------
namespace SilverStripe\Core {

    class Environment
    {
        private static array $vars = [];

        public static function setEnv(string $name, $value): void
        {
            self::$vars[$name] = $value;
        }

        public static function getEnv(string $name)
        {
            return self::$vars[$name] ?? null;
        }

        public static function reset(): void
        {
            self::$vars = [];
        }
    }

    class Convert
    {
        public static function base64url_decode(string $input)
        {
            $decoded = base64_decode(strtr($input, '-_', '+/'), true);
            return $decoded !== false ? json_decode($decoded, true) : null;
        }
    }
}

// ---------------------------------------------------------------------------
// SilverStripe Configurable trait stub
// ---------------------------------------------------------------------------
namespace SilverStripe\Core\Config {

    trait Configurable
    {
        public static function config(): ConfigAccessor
        {
            return new ConfigAccessor(static::class);
        }
    }

    class ConfigAccessor
    {
        private string $class;
        private static array $overrides = [];

        public function __construct(string $class)
        {
            $this->class = $class;
        }

        public function get(string $name)
        {
            $key = $this->class . '::' . $name;

            if (array_key_exists($key, self::$overrides)) {
                return self::$overrides[$key];
            }

            $rc = new \ReflectionClass($this->class);
            if ($rc->hasProperty($name)) {
                $prop = $rc->getProperty($name);
                $prop->setAccessible(true);
                return $prop->getValue(null);
            }

            return null;
        }

        public function set(string $name, $value): self
        {
            self::$overrides[$this->class . '::' . $name] = $value;
            return $this;
        }

        public static function resetOverrides(): void
        {
            self::$overrides = [];
        }
    }
}

// ---------------------------------------------------------------------------
// SilverStripe Injectable trait stub
// ---------------------------------------------------------------------------
namespace SilverStripe\Core\Injector {

    trait Injectable
    {
        public static function create(...$args)
        {
            return new static(...$args);
        }

        public static function singleton(): static
        {
            return new static();
        }
    }

    class Injector
    {
        private static ?self $instance = null;

        public static function inst(): self
        {
            return self::$instance ??= new self();
        }

        public function get(string $class)
        {
            return new $class();
        }
    }
}

// ---------------------------------------------------------------------------
// SilverStripe Control stubs
// ---------------------------------------------------------------------------
namespace SilverStripe\Control {

    class HTTPRequest
    {
        private string $method;
        private string $url;
        private array $headers = [];
        private array $postVars = [];
        private ?string $body = null;

        public function __construct(string $method, string $url, array $getVars = [], array $postVars = [])
        {
            $this->method = $method;
            $this->url = $url;
            $this->postVars = $postVars;
        }

        public function addHeader(string $name, string $value): self
        {
            $this->headers[$name] = $value;
            return $this;
        }

        public function getHeader(string $name): ?string
        {
            return $this->headers[$name] ?? null;
        }

        public function setBody(string $body): self
        {
            $this->body = $body;
            return $this;
        }

        public function getBody(): ?string
        {
            return $this->body;
        }

        public function postVars(): array
        {
            return $this->postVars;
        }

        public function postVar(string $name)
        {
            return $this->postVars[$name] ?? null;
        }
    }

    class HTTPResponse
    {
        private int $statusCode = 200;
        private string $body = '';
        private array $headers = [];

        public static function create(): self
        {
            return new self();
        }

        public function setStatusCode(int $code): self
        {
            $this->statusCode = $code;
            return $this;
        }

        public function getStatusCode(): int
        {
            return $this->statusCode;
        }

        public function addHeader(string $name, string $value): self
        {
            $this->headers[$name] = $value;
            return $this;
        }

        public function getHeader(string $name): ?string
        {
            return $this->headers[$name] ?? null;
        }

        public function setBody(string $body): self
        {
            $this->body = $body;
            return $this;
        }

        public function getBody(): string
        {
            return $this->body;
        }
    }

    class Controller
    {
        protected function init() {}
    }

    class Director
    {
        public static function absoluteURL(string $url): string
        {
            return 'https://example.com' . $url;
        }
    }
}

// ---------------------------------------------------------------------------
// SilverStripe ORM stubs
// ---------------------------------------------------------------------------
namespace SilverStripe\ORM {

    class DataObject
    {
        public function __construct() {}

        public static function create()
        {
            return new static();
        }

        public static function get()
        {
            return new DataList(static::class);
        }

        public function write(): int
        {
            return 1;
        }

        public function getCMSFields()
        {
            return null;
        }
    }

    class DataList
    {
        private string $class;

        public function __construct(string $class)
        {
            $this->class = $class;
        }

        public function filter(...$args): self
        {
            return $this;
        }

        public function first()
        {
            return null;
        }

        public function exists(): bool
        {
            return false;
        }
    }

    class ArrayList
    {
        private array $items = [];

        public function add($item): void
        {
            $this->items[] = $item;
        }
    }
}

namespace SilverStripe\ORM\FieldType {

    class DBHTMLText
    {
        private string $value = '';

        public static function create(): self
        {
            return new self();
        }

        public function setValue(string $value): self
        {
            $this->value = $value;
            return $this;
        }
    }
}

// ---------------------------------------------------------------------------
// SilverStripe Assets stubs
// ---------------------------------------------------------------------------
namespace SilverStripe\Assets {

    use SilverStripe\ORM\DataObject;

    class File extends DataObject
    {
        public static function format_size($bytes): string
        {
            return round($bytes / 1024, 2) . ' KB';
        }
    }

    class Image extends File
    {
        public function getFilename(): ?string { return null; }
        public function getHash(): ?string { return null; }
        public function getURL(): ?string { return null; }
        public function getWidth(): ?int { return null; }
        public function getHeight(): ?int { return null; }
        public function FitMax($w, $h) { return null; }
        public function allMethodNames(): array { return []; }
    }
}

namespace SilverStripe\Assets\Storage {

    class AssetStore {}
}

namespace SilverStripe\Assets\FilenameParsing {

    class ParsedFileID
    {
        public function __construct(string $filename, ?string $hash, ?string $variant) {}
        public function getFileID(): string { return ''; }
    }
}

// ---------------------------------------------------------------------------
// SilverStripe Admin stubs
// ---------------------------------------------------------------------------
namespace SilverStripe\AssetAdmin\Controller {

    class AssetAdmin
    {
        public static function config()
        {
            return new class {
                public function uninherited($k) { return 300; }
            };
        }
    }
}

// ---------------------------------------------------------------------------
// Symfony Filesystem stub (used by OctoController)
// ---------------------------------------------------------------------------
namespace Symfony\Component\Filesystem {

    class Filesystem
    {
        public function dumpFile(string $path, string $content): void {}
    }
}

// ---------------------------------------------------------------------------
// foroco BrowserDetection stub
// ---------------------------------------------------------------------------
namespace foroco {

    class BrowserDetection
    {
        /**
         * Simulated user-agent parsing. Returns an associative array with
         * browser_name and browser_version extracted from common UA strings.
         */
        public function getAll(string $ua): ?array
        {
            $patterns = [
                // Order matters -- Edge/Opera must be checked before Chrome
                'Edge'    => '/Edg(?:e|A|iOS)?\/(\d+[\.\d]*)/',
                'Opera'   => '/(?:OPR|Opera)\/(\d+[\.\d]*)/',
                'Chrome'  => '/Chrome\/(\d+[\.\d]*)/',
                'Safari'  => '/Version\/(\d+[\.\d]*).*Safari/',
                'Firefox' => '/Firefox\/(\d+[\.\d]*)/',
                'IE'      => '/(?:MSIE |Trident\/.*rv:)(\d+[\.\d]*)/',
            ];

            foreach ($patterns as $name => $regex) {
                if (preg_match($regex, $ua, $m)) {
                    return [
                        'browser_name'    => $name,
                        'browser_version' => (float) $m[1],
                    ];
                }
            }

            return null;
        }
    }
}

// ---------------------------------------------------------------------------
// Global namespace: constants, temp dirs, and PSR-4 autoloader
// ---------------------------------------------------------------------------
namespace {

    if (!defined('PUBLIC_PATH')) {
        define('PUBLIC_PATH', sys_get_temp_dir() . '/octosqueeze-test-public');
    }
    if (!defined('ASSETS_DIR')) {
        define('ASSETS_DIR', 'assets');
    }

    // Ensure the directory exists so realpath() works.
    if (!is_dir(PUBLIC_PATH)) {
        mkdir(PUBLIC_PATH, 0755, true);
    }

    // PSR-4 autoloader for plugin source classes
    spl_autoload_register(function (string $class): void {
        $map = [
            'OctoSqueeze\\Silverstripe\\Tests\\' => __DIR__ . '/',
            'OctoSqueeze\\Silverstripe\\'        => dirname(__DIR__) . '/src/',
        ];

        foreach ($map as $prefix => $baseDir) {
            $len = strlen($prefix);
            if (strncmp($class, $prefix, $len) === 0) {
                $relativeClass = substr($class, $len);
                $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
                if (file_exists($file)) {
                    require_once $file;
                    return;
                }
            }
        }
    });
}
