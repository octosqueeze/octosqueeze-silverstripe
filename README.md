# OctoSqueeze for SilverStripe

Automatic image compression and WebP/AVIF conversion for SilverStripe CMS.

## Features

- Automatic compression of uploaded images
- WebP and AVIF format conversion
- Browser-based format detection (serves best format based on browser support)
- Tracks all image variants (thumbnails, resized images)
- Admin panel for viewing compression statistics
- Queue-based compression via scheduled tasks

## Requirements

- PHP 8.0+
- SilverStripe Framework 5.0+
- OctoSqueeze API key ([Get one free](https://octosqueeze.com))

## Installation

```bash
composer require octosqueeze/octosqueeze-silverstripe
```

## Configuration

Add your API key to your `.env` file:

```env
OCTOSQUEEZE_API_KEY="your-api-key-here"

# Optional: For local development
OCTOSQUEEZE_DEV_ENV=true
OCTOSQUEEZE_ENDPOINT="https://your-local-api.test"
```

### Module Configuration

Create or edit `app/_config/octosqueeze.yml`:

```yaml
OctoSqueeze\Silverstripe\Octo:
  # Auto-replace image URLs with WebP/AVIF versions (recommended)
  autoreplace_url: true

  # Also replace URLs in admin panel
  autoreplace_url_in_admin: false

  # Formats to generate (in addition to original)
  required_formats:
    - avif
    - webp

  # Compression mode: 'size' (smallest), 'balanced', or 'quality' (best quality)
  oc_compression_type: balanced
```

## Usage

### Automatic Compression

Once installed and configured, OctoSqueeze automatically:

1. Tracks all image uploads and their variants
2. Compresses images via scheduled tasks
3. Serves optimized formats based on browser support

### Running Compression Tasks

Add these to your cron jobs:

```bash
# Send images for compression (run frequently)
php vendor/silverstripe/framework/cli-script.php dev/tasks/OSSendConversionsTask

# Fetch completed compressions
php vendor/silverstripe/framework/cli-script.php dev/tasks/OSFetchCompressionsTask
```

Or run manually from `/dev/tasks` in your browser.

### Template Usage

Images automatically serve the best format. No template changes needed:

```html
<!-- Automatically serves WebP/AVIF if browser supports -->
$Image.ScaleWidth(800)
```

To prevent format conversion for specific images:

```html
$Image.OctoIgnore.ScaleWidth(800)
$Image.OctoIgnore('webp').ScaleWidth(800)  <!-- Only skip WebP -->
$Image.OctoIgnore('avif').ScaleWidth(800)  <!-- Only skip AVIF -->
```

### Admin Panel

View compression statistics in the CMS at **OctoSqueeze** in the main menu.

## Available Tasks

| Task | Description |
|------|-------------|
| `OSSendConversionsTask` | Send pending images to OctoSqueeze for compression |
| `OSFetchCompressionsTask` | Download completed compressions |
| `PopulateConversionsTask` | Scan existing images and create conversion records |
| `GenerateThumbnailsTask` | Pre-generate common thumbnail sizes |
| `EraseOSTask` | Clear all OctoSqueeze compression data |
| `EraseBrokenImagesTask` | Clean up orphaned compression records |

## How It Works

1. **Upload**: When an image is uploaded, OctoSqueeze creates a conversion record
2. **Publish**: On publish, all image variants are tracked
3. **Compress**: Scheduled task sends images to OctoSqueeze API
4. **Store**: Compressed images (WebP, AVIF) are saved alongside originals
5. **Serve**: Browser detection serves the best supported format

## License

MIT License. See [LICENSE.md](LICENSE.md) for details.
