<?php

namespace OctoSqueeze\Silverstripe\Assets\Flysystem;

use SilverStripe\Assets\File;
use OctoSqueeze\Silverstripe\Models\ImageConversion;
use SilverStripe\Assets\Flysystem\FlysystemAssetStore;

class OctosystemAssetStore extends FlysystemAssetStore
{
    protected function writeWithCallback($callback, $filename, $hash, $variant = null, $config = [])
    {
        $return = parent::writeWithCallback($callback, $filename, $hash, $variant, $config);


        $file = File::get()->filter(['FileFilename' => $filename])->first();

        if ($file && $file->getIsImage())
        {
            if ($variant)
            {
                // variant
                if (!$file->Conversions()->filter('Variant', $variant)->exists())
                {
                    $variants = $file->getAllVariants(true);
                    $variantID = $variant ? null : $file->FileFilename;

                    // in some cases (when image is already published, variants can exists so we need that here)
                    if ($variants)
                    {
                        foreach ($variants as $v)
                        {
                            if ($v['variant'] == $variant)
                            {
                                $variantID = $v['id'];
                                break;
                            }
                        }
                    }

                    $conversion = ImageConversion::create();
                    $conversion->FileHash = $file->FileHash;
                    $conversion->FileFilename = $variantID;
                    $conversion->Variant = $variant;
                    $conversion->write();

                    $file->Conversions()->add($conversion);
                }
            }
            else
            {
                // origin (writes in ImageExtension)
            }
        }

        return $return;
    }
}
