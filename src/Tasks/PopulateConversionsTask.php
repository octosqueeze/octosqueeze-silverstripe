<?php

namespace OctoSqueeze\Silverstripe\Tasks;

use SilverStripe\Assets\Image;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Assets\Storage\AssetStore;
use OctoSqueeze\Silverstripe\Models\ImageConversion;

class PopulateConversionsTask extends BuildTask
{
    private static $segment = 'PopulateConversionsTask';

    protected $enabled = true;

    protected $title = '';

    protected $description = 'Scan existing assets for images and populate Conversions table for further compressions. Handy when you already have assets uploaded that need to be compressed.';

    public function run($request)
    {
        $addedConversions = 0;

        $store = Injector::inst()->get(AssetStore::class);
        $fsPublic = $store->getPublicFilesystem();

        foreach (Image::get() as $image)
        {
            // 1) Populate conversions
            $variants = $image->getAllVariants(true);

            if ($variants)
            {
                foreach ($variants as $variant)
                {
                    if (!ImageConversion::get()->filter(['Variant' => $variant['variant'] != '' ? $variant['variant'] : null, 'ImageID' => $image->ID])->exists())
                    {
                        $conversion = ImageConversion::create();
                        $conversion->FileHash = $image->FileHash;
                        $conversion->FileFilename = $variant['id'];
                        $conversion->Variant = $variant['variant'];
                        $conversion->ImageID = $image->ID;
                        $conversion->write();

                        $parsedFile = $conversion->getParsedFileID();
                        $conversion->Hash = hash('sha256', $fsPublic->read($parsedFile->getFileID()));
                        $conversion->write();

                        $addedConversions++;
                    }
                }
            }
        }

        print_r('
        <p>Added conversion records: '.$addedConversions.'</p>
        ');
    }
}
