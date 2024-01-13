<?php

namespace OctoSqueeze\Silverstripe\Tasks;

use SilverStripe\Assets\Image;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Assets\Storage\AssetStore;

class EraseOSTask extends BuildTask
{
    private static $segment = 'EraseOSTask';

    protected $enabled = true;

    protected $title = '';

    protected $description = 'Delete all compression files and wipe out Conversions and Compressions tables. All originally uploaded images and created variants not touched.';

    public function run($request)
    {
        $store = Injector::inst()->get(AssetStore::class);
        $fsPublic = $store->getPublicFilesystem();

        $removedCompressions = 0;
        $removedConversions = 0;

        foreach (Image::get() as $image)
        {
            // 1) Remove all conversions & compressions records with compression files

            foreach ($image->Conversions() as $conversion)
            {
                foreach ($conversion->Compressions() as $compression)
                {
                    $fsPublic->delete($compression->getFileID());
                    $removedCompressions++;
                }

                $conversion->delete();
                $removedConversions++;
            }
        }


        print_r('
        <p>Removed compression files: '.$removedCompressions.'</p>
        <p>Removed conversion records: '.$removedConversions.'</p>
        ');
    }
}
