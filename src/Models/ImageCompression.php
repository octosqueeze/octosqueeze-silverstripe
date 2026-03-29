<?php

namespace OctoSqueeze\Silverstripe\Models;

use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Assets\Storage\AssetStore;
use OctoSqueeze\Silverstripe\Models\ImageConversion;

class ImageCompression extends DataObject
{
    private static $table_name = 'ImageCompression';
    private static $singular_name = 'image compression';
    private static $plural_name = 'image compressions';

    private static $db = [
        'Format' => 'Varchar(16)',
        'Size' => 'Int',
        'OctoID' => 'Varchar(36)',
    ];

    private static $has_one = [
        'Conversion' => ImageConversion::class,
    ];

    private static $summary_fields = [
        'Format' => 'Format',
        'Size' => 'Size',
        'sf_link' => 'Origin Link',
        'sf_saved' => 'Compression percent',
    ];

    public function sf_link()
    {
        return $this->Conversion()->getURL();
    }

    public function sf_saved()
    {
        $originSize = $this->Conversion()->getFilesize();

        if (!$originSize) {
            return 'N/A';
        }

        $str = number_format(100 - $this->Size / ($originSize / 100), 2) . '%';

        return $str;
    }

    // remove /assets/ from the begining
    public function getFileID($currentFileFilename = false, $withoutAssetsDir = true)
    {
        $url = $this->getURL($currentFileFilename);

        return $url ? ($withoutAssetsDir ? substr($url, strlen(ASSETS_DIR) + 2) : $url) : null;
    }

    public function getURL($currentFileFilename = false)
    {
        $conversionLink = $currentFileFilename ? $this->Conversion()->FileFilename : $this->Conversion()->getURL();

        $ext = pathinfo($conversionLink ?? '', PATHINFO_EXTENSION);

        $extLength = strlen($ext);

        return $conversionLink ? (substr($conversionLink, 0, -$extLength) . $this->Format) : null;
    }

    public function onBeforeDelete()
    {
        $store = Injector::inst()->get(AssetStore::class);
        $fsPublic = $store->getPublicFilesystem();

        if ($this->getFileID(true, false))
        {
            $fsPublic->delete($this->getFileID(true, false));
        }

        parent::onBeforeDelete();
    }
}
