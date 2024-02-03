<?php

namespace OctoSqueeze\Silverstripe\Tasks;

use GuzzleHttp\Client;
use SilverStripe\Assets\Image;
use SilverStripe\Dev\BuildTask;
use OctoSqueeze\Silverstripe\Octo;
use SilverStripe\Core\Environment;
use OctoSqueeze\Client\OctoSqueeze;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Assets\Storage\AssetStore;
use Symfony\Component\Filesystem\Filesystem;
use OctoSqueeze\Silverstripe\Models\ImageConversion;
use OctoSqueeze\Silverstripe\Models\ImageCompression;
use SilverStripe\Assets\FilenameParsing\ParsedFileID;

class OSSendConversionsTask extends BuildTask
{
    private static $segment = 'OSSendConversionsTask';

    protected $enabled = true;

    protected $title = '';

    protected $description = 'Make an API call to OC, and send all existing conversions that haven\'t been compressed yet.';

    public function run($request)
    {
        $count = 0;

        $filesystemType = 'local';

        // if (method_exists($this, 'getFilesystemFor'))
        // {
        //     $filesystemType = 's3';
        // }

        // if ($filesystemType == 's3')
        // {
        //     $fs = $this->getFilesystemFor($flyID);
        // }
        // else
        // {
        //     $fs = $this->getFilesystemForLocal($flyID);
        // }

        // dd($adapter);
        // $adapter->getPublicUrl($variantParsedFileID->getFileID()),

        $links = [];

        $store = Injector::inst()->get(AssetStore::class);
        $fsPublic = $store->getPublicFilesystem();

        $sentConversions = [];

        $config = Octo::config();

        if (Environment::hasEnv('OCTOSQUEEZE_DEV')) {
            $oc_dev_env = Environment::getEnv('OCTOSQUEEZE_DEV');
        } else {
            $oc_dev_env = false;
        }

        // todo: if Hash missing, save it

        foreach (ImageConversion::get()->filter('Stage', 0) as $conversion)
        {
            if ($conversion->Image()->exists() && $conversion->Image()->isPublished())
            {
                $sentConversions[$conversion->ID] = $conversion;

                if (!$conversion->Hash)
                {
                    $parsedFile = $conversion->getParsedFileID();

                    if ($parsedFile)
                    {
                        $conversion->Hash = hash('sha256', $fsPublic->read($parsedFile->getFileID()));
                        $conversion->write();
                    }
                }

                $links[] = [
                  'image_id' => $conversion->ID,
                  'hash' => $conversion->Hash,
                  'url' => $conversion->getURL(true),
                  'name' => $conversion->getFilname(),
                  // 'size' => $conversion->getFilesize(),
                  // 'mime_type' => $conversion->getMimeType(),
                  'options' => [
                    'formats' => $config->get('required_formats'),
                    'type' => $config->get('oc_compression_type'),
                  ],
                ];

                $count++;
            }

            // dump($conversion->getFileSize(), $conversion->getMimeType(), $conversion->getURL());
            // dump($conversion->getManipulation() .' - '. $conversion->getWidth() .' - '. $conversion->getHeight());

            // dump($conversion->getAttributes(), $conversion->getFocusX(), $conversion->getFocusY());

            // 'type' => $fs->mimeType($path),
            // 'size' => $fs->fileSize($path),

            // dump($public->fileSize($swapParsedFileID->getFileID()), $swapParsedFileID, $swapParsedFileID->getFileID(), $adapter->getPublicUrl($swapParsedFileID->getFileID()));
        }

        if (!empty($links))
        {
            $octo = OctoSqueeze::client(ss_env('OCTOSQUEEZE_API_KEY'));

            if ($oc_dev_env) {
              // ! ONLY FOR DEV
              $octo->setEndpointUri(ss_env('OCTOSQUEEZE_ENDPOINT'));
              $octo->setHttpClientConfig(['verify' => false]);
            }

            $octo->setOptions(['hash_check' => true]);

            $response = $octo->squeezeUrl($links);
            // dd($response);

            if ($response && $response['state'])
            {
                foreach ($sentConversions as $key => $conversion)
                {
                    $conversion->Stage = 1;
                    $conversion->write();

                    $sentConversions[$key] = $conversion;
                }

                // same as OSFetch task
                $fs = new Filesystem();

                if (isset($response['items']))
                {
                    foreach($response['items'] as $item)
                    {
                        $conversion = isset($sentConversions[$item['image_id']]) ? $sentConversions[$item['image_id']] : null;
                        // $conversion = ImageConversion::get()->filter(['ID' => $item['image_id'], 'Stage' => 1])->first();

                        if ($conversion)
                        {
                            if ($item['compressed'])
                            {
                                if (isset($item['compressions']) && is_array($item['compressions']) && count($item['compressions']))
                                {
                                    // checking compressed compressions, and add them if not exists

                                    // SAME CODE AS in OSFetch

                                    $expl = explode('.', $conversion->getURL());
                                    $ext = last($expl);
                                    $path = current($expl);

                                    foreach ($item['compressions'] as $compression)
                                    {
                                        // only if this compression does not exists - add it
                                        if (!$conversion->Compressions()->filter('OctoID', $compression['id'])->exists())
                                        {
                                            if ($oc_dev_env) {
                                              // ! only for dev TLS verification
                                              $contextOptions = [
                                                'ssl' => [
                                                  'verify_peer' => false,
                                                  'verify_peer_name' => false,
                                                ]
                                              ];
                                              $image = file_get_contents($compression['link'], false, stream_context_create($contextOptions));
                                            } else {
                                              $image = file_get_contents($compression['link']);
                                            }

                                            $file = $path . '.' . $compression['format'];

                                            if ($file[0] == '/')
                                            {
                                                $file = substr($file, 1);
                                            }

                                            $fs->dumpFile($file, $image);

                                            $record = ImageCompression::create();
                                            $record->OctoID = $compression['id'];
                                            $record->Format = $compression['format'];
                                            $record->Size = $compression['size'];
                                            $record->write();

                                            $conversion->Compressions()->add($record);

                                            $count++;
                                        }
                                    }

                                    $conversion->Stage = 2;
                                    $conversion->write();
                                }
                            }
                            else
                            {
                                $conversion->OctoID = $item['id'];
                                $conversion->write();
                            }
                        }



                        // $path = BASE_PATH . '/public' . $conversion->getURL(); // ASSETS_PATH

                        // dump($conversion->getURL());

                    }
                }
            }
        }

        // $adapter->getPublicUrl($variantParsedFileID->getFileID())

        // foreach (Image::get() as $image)
        // {
        //     $parsedFileID = new ParsedFileID($image->getFilename(), $image->getHash());
        //     $protected = $assetStore->getProtectedFilesystem();
        //     $public = $assetStore->getPublicFilesystem();
        //     $protectedStrategy = $assetStore->getProtectedResolutionStrategy();
        //     $publicStrategy = $assetStore->getPublicResolutionStrategy();

        //     $swapFileIDStr = $publicStrategy->buildFileID($parsedFileID);

        //     if ($public->has($swapFileIDStr))
        //     {
        //         $swapParsedFileID = $publicStrategy->resolveFileID($swapFileIDStr, $public);

        //         foreach ($publicStrategy->findVariants($swapParsedFileID, $public) as $variantParsedFileID)
        //         {
        //             dump($variantParsedFileID);
        //             // $store = Injector::inst()->get(AssetStore::class);
        //             // dd($store->getMetadata($this->Filename, $this->Hash, $this->Variant);
        //             // if ($variantParsedFileID->getVariant() == $variant) {
        //             // }
        //         }
        //     }
        // }

        // $variants = $strategy->findVariants($parsedFileID, $fs);


        print_r('
        <p>Sent files for compressions: '.$count.'</p>
        ');
    }
}
