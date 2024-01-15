<?php

namespace OctoSqueeze\Silverstripe\Extensions;

use GuzzleHttp\Client;
use SilverStripe\Assets\File;
use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Assets\Storage\AssetStore;
use Symfony\Component\Filesystem\Filesystem;
use OctoSqueeze\Silverstripe\Models\ImageConversion;
use OctoSqueeze\Silverstripe\Models\ImageCompression;
use SilverStripe\Assets\FilenameParsing\ParsedFileID;

class ImageExtension extends Extension
{
    private static $has_many = [
        'Conversions' => ImageConversion::class,
    ];

    private static $cascade_deletes = [
        'Conversions',
    ];

    public function OctoIgnore(...$formats)
    {
        if (empty($formats))
        {
            $this->owner->escapeFormatting = true;
        }

        if (in_array('webp', $formats))
        {
            $this->owner->escapeFormattingWebp = true;
        }

        if (in_array('avif', $formats))
        {
            $this->owner->escapeFormattingAvif = true;
        }

        return $this->owner;
    }

    public function getAllVariants($withOrigin = false)
    {
        $store = Injector::inst()->get(AssetStore::class);
        $fsPublic = $store->getPublicFilesystem();
        $strategyPublic = $store->getPublicResolutionStrategy();

        $image = $this->owner;

        $parsedFileID = new ParsedFileID($image->getFilename(), $image->getHash());
        $buildFile = $strategyPublic->buildFileID($parsedFileID);

        if ($fsPublic->has($buildFile))
        {
            $swap = $strategyPublic->resolveFileID($buildFile, $fsPublic);

            $list = [];

            foreach ($strategyPublic->findVariants($swap, $fsPublic) as $parsedFileID)
            {
                if ($withOrigin || (!$withOrigin && $parsedFileID->getVariant()))
                {
                    $ext = pathinfo($parsedFileID->getFileID() ?? '', PATHINFO_EXTENSION);
                    $filename = pathinfo($parsedFileID->getFileID() ?? '', PATHINFO_FILENAME);

                    $list[] = [
                      'id' => $parsedFileID->getFileID(),
                      'variant' => $parsedFileID->getVariant(),
                      // 'conversionHash' => hash('sha256', $parsedFileID->getFileID()),
                      'name' => $filename,
                      'extension' => $ext,
                    ];
                }
            }

            return $list;
        }
    }

    // public function onBeforeDelete()
    // {
    //     $store = Injector::inst()->get(AssetStore::class);
    //     $fsPublic = $store->getPublicFilesystem();

    //     foreach ($this->Conversions() as $conversion)
    //     {
    //         if ($conversion->FileFilename)
    //         {
    //             foreach ($conversion->Compressions() as $compression)
    //             {
    //                 if ($compression->getFileID())
    //                 {
    //                     $fsPublic->delete($compression->getFileID());
    //                 }

    //                 // if ($compression->getFileID(true))
    //                 // {
    //                 //     $fsPublic->delete($compression->getFileID(true));
    //                 // }
    //             }
    //         }
    //     }

    //     parent::onBeforeDelete();
    // }

    public function onBeforeWrite()
    {
        // $changes = $this->owner->getChangedFields(['Version', 'FileFilename']);
        // $versionChanged = isset($changes['Version']) && $changes['Version']['before'] !== $changes['Version']['after'];

        // dump($changes);

        // if ($versionChanged)
        // {
        //     // dd($changes);
        // }
    }

    // Create original conversion if not exists
    private function createOriginalConversion()
    {
        if (!$this->owner->Conversions()->filter(['Variant' => null, 'FileFilename:not' => null])->exists())
        {
            $conversion = ImageConversion::create();
            $conversion->FileHash = $this->owner->FileHash;
            $conversion->FileFilename = $this->owner->FileFilename;
            $conversion->write();

            $this->owner->Conversions()->add($conversion);
        }
    }

    public function onAfterWrite()
    {
        $this->createOriginalConversion();
    }

    public function onAfterPublish()
    {
        $conversions = $this->owner->Conversions();

        $fs = new Filesystem();

        $conversionsByFileHash = $conversions->filter('FileHash', $this->owner->FileHash);

        // Check, if any existing conversions have different `FileHash`, it means original file has been changed/replaced. Get rid of all compressions
        if ($conversions->Count() !== $conversionsByFileHash->Count())
        {
            foreach ($conversions as $conversion)
            {
                $conversion->delete();
            }
        }
        else
        {
            // Check if image have been moved

            $variants = $this->owner->getAllVariants(true);

            if ($variants)
            {
                foreach ($variants as $variant)
                {
                    $conversion = $conversions->filter('Variant', $variant['variant'] == '' ? null : $variant['variant'])->first();

                    if ($conversion)
                    {
                        // `FileFilename` will be null when publishing image first time
                        if ($conversion->FileFilename === null)
                        {
                            // set FileFilename for conversion
                            $conversion->FileFilename = $variant['id'];
                            $conversion->write();
                        }
                        // `FileFilename` is different compared to the variant id? the original file and its variants have been moved
                        else if ($conversion->FileFilename != $variant['id'])
                        {
                            // move all compressions accordingly
                            foreach ($conversion->Compressions() as $compression)
                            {
                                $conversionLink = $variant['id'];
                                $ext = pathinfo($conversionLink ?? '', PATHINFO_EXTENSION);
                                $extLength = strlen($ext);
                                $newPath = $conversionLink ? (substr($conversionLink, 0, -$extLength) . $compression->Format) : null;

                                $oldPath = ASSETS_DIR . '/' . $compression->getURL(true);
                                $newPath = ASSETS_DIR . '/' .$newPath;

                                if ($oldPath !== $newPath && !$fs->exists($newPath))
                                {
                                    $fs->rename($oldPath, $newPath);
                                }
                            }

                            $conversion->FileFilename = $variant['id'];
                            $conversion->write();
                        }
                    }
                    else
                    {
                        // here for unpublish/publish function, recreates conversions
                        $conversion = ImageConversion::create();
                        $conversion->FileHash = $this->owner->FileHash;
                        $conversion->FileFilename = $this->owner->FileFilename;
                        $conversion->Variant = $variant['variant'];
                        $conversion->ImageID = $this->owner->ID;
                        $conversion->write();
                    }
                }
            }
        }

        // here for Image Replacement function (in assets), adds missing origin conversion after image replacement
        $this->createOriginalConversion();

        // redirect conversionHash
        $conversionsWithoutHash = $this->owner->Conversions()->filter('Hash', null);

        if ($conversionsWithoutHash->Count())
        {
            foreach ($conversionsWithoutHash as $conversion)
            {
                $store = Injector::inst()->get(AssetStore::class);
                $fsPublic = $store->getPublicFilesystem();
                $parsedFile = $conversion->getParsedFileID();

                if ($parsedFile)
                {
                    $conversion->Hash = hash('sha256', $fsPublic->read($parsedFile->getFileID()));
                    $conversion->write();
                }
            }
        }

        // Same as ImageConversionScanTask

              // $links = [];

              // $sentConversions = [];

              // $list = $this->owner->Conversions()->filter('Stage', 0);

              // if (count($list))
              // {
              //     foreach ($list as $conversion)
              //     {
              //         $sentConversions[] = $conversion;

              //         $links[] = [
              //           'id' => $conversion->ID,
              //           'link' => $conversion->getURL(true),
              //           'name' => $conversion->getFilname(),
              //           'size' => $conversion->getFilesize(),
              //           'mime_type' => $conversion->getMimeType(),
              //           'options' => ['formats' => 'webp,avif'],
              //         ];
              //         // dump($conversion->getFileSize(), $conversion->getMimeType(), $conversion->getURL());
              //         // dump($conversion->getManipulation() .' - '. $conversion->getWidth() .' - '. $conversion->getHeight());

              //         // dump($conversion->getAttributes(), $conversion->getFocusX(), $conversion->getFocusY());

              //         // 'type' => $fs->mimeType($path),
              //         // 'size' => $fs->fileSize($path),

              //         // dump($public->fileSize($swapParsedFileID->getFileID()), $swapParsedFileID, $swapParsedFileID->getFileID(), $adapter->getPublicUrl($swapParsedFileID->getFileID()));
              //     }

              //     if (!empty($links))
              //     {
              //         $client = new Client([
              //             'verify' => false, // ! ONLY FOR DEV
              //         ]);

              //         // ! here to avoid awaiting on Guzzle request (mighe need to find another way to do that)
              //         foreach ($sentConversions as $conversion)
              //         {
              //             $conversion->Stage = 1;
              //             $conversion->write();
              //         }

              //         $uri = ss_env('OCTOSQUEEZE_ENDPOINT') . '/api/compress-all';

              //         try {
              //         $response = $client->request('POST', $uri, [
              //             'timeout' => 1,
              //             'form_params' => [
              //                 'links' => json_encode($links),
              //             ]
              //         ]);

              //         // if ($response->getStatusCode() === 200)
              //         // {
              //         //     $result = json_decode($response->getBody()->getContents(), true);

              //         //     if ($result['state'])
              //         //     {
              //         //         foreach ($sentConversions as $conversion)
              //         //         {
              //         //             $conversion->Stage = 1;
              //         //             $conversion->write();
              //         //         }
              //         //     }
              //         //     else
              //         //     {
              //         //         // error $result['error']
              //         //     }
              //         // }

              //           } catch (\GuzzleHttp\Exception\ConnectException $e) {
              //             // do nothing, the timeout exception is intended
              //         }


              //     }
              // }
    }
}
