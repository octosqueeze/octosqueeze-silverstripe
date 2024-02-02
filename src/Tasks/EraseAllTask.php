<?php

namespace OctoSqueeze\Silverstripe\Tasks;

use SilverStripe\Assets\Image;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Assets\Storage\AssetStore;

class EraseAllTask extends BuildTask
{
    private static $segment = 'EraseAllTask';

    protected $enabled = true;

    protected $title = '';

    protected $description = 'Delete all variant and compression files. Wipe out Conversions and Compressions tables. This task make a full clean up, leaving only originally uploaded images. Handy when need to get rid of mess and recreate all variants, and be able to compress it again.';

    public function run($request)
    {
        $store = Injector::inst()->get(AssetStore::class);
        $fsPublic = $store->getPublicFilesystem();

        $removedVariants = 0;
        $removedCompressions = 0;
        $removedConversions = 0;

        foreach (Image::get() as $image)
        {
            // 1) Remove all conversions & compressions records with compression files

            foreach ($image->Conversions() as $conversion)
            {
                foreach ($conversion->Compressions() as $compression)
                {
                    if ($compression && $compression->getFileID()) {
                      $fsPublic->delete($compression->getFileID());
                      $removedCompressions++;
                    }
                }

                $conversion->delete();
                $removedConversions++;
            }

            // 2) Remove all variant files

            $variants = $image->getAllVariants();

            if ($variants)
            {
                foreach ($variants as $variant)
                {
                    $fsPublic->delete($variant['id']);
                    $removedVariants++;
                }
            }
        }


        print_r('
        <p>Removed variant files: '.$removedVariants.'</p>
        <p>Removed compression files: '.$removedCompressions.'</p>
        <p>Removed conversion records: '.$removedConversions.'</p>
        ');
    }
}
