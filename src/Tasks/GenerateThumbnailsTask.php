<?php

namespace OctoSqueeze\Silverstripe\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\AssetAdmin\Helper\ImageThumbnailHelper;

class GenerateThumbnailsTask extends BuildTask
{
    private static $segment = 'GenerateThumbnailsTask';

    protected $enabled = true;

    protected $title = '';

    protected $description = 'Generate basic CMS tumbnails after Erasing all. Only those that do not exist';

    public function run($request)
    {
        $count = ImageThumbnailHelper::singleton()->run();

        print_r('
        <p>Number of thumbnails processed: '.$count.'</p>
        ');
    }
}
