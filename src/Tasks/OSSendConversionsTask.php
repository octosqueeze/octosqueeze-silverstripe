<?php

namespace OctoSqueeze\Silverstripe\Tasks;

use GuzzleHttp\Client;
use SilverStripe\Assets\Image;
use SilverStripe\Dev\BuildTask;
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

        // todo: if Hash missing, save it

        foreach (ImageConversion::get()->filter('Stage', 0) as $conversion)
        {
            if ($conversion->Image()->exists() && $conversion->Image()->isPublished())
            {
                $sentConversions[] = $conversion;

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
                  'id' => $conversion->ID,
                  'hash' => $conversion->Hash,
                  'link' => $conversion->getURL(true),
                  'name' => $conversion->getFilname(),
                  'size' => $conversion->getFilesize(),
                  'mime_type' => $conversion->getMimeType(),
                  'hash' => $conversion->Hash,
                  'options' => ['formats' => 'webp,avif'],
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
            $client = new Client([
                'verify' => false, // ! ONLY FOR DEV
            ]);

            $uri = ss_env('OCTO_IMAGE_ENDPOINT') . '/api/compress-all';

            $response = $client->request('POST', $uri, [
                'headers' => [
                    'Authorization' => 'Bearer ' . ss_env('OCTO_IMAGE_TOKEN'),
                    'Accept' => 'application/json',
                ],
                'form_params' => [
                    'links' => json_encode($links),
                ]
            ]);

            if ($response->getStatusCode() === 200)
            {
                $result = json_decode($response->getBody()->getContents(), true);

                if ($result['state'])
                {
                    foreach ($sentConversions as $conversion)
                    {
                        $conversion->Stage = 1;
                        $conversion->write();
                    }

                    // same as OSFetch task
                    $fs = new Filesystem();

                    if (isset($result['images']))
                    {
                        foreach($result['images'] as $item)
                        {
                            $conversion = ImageConversion::get()->filter(['ID' => $item['image_id'], 'Stage' => 1])->first();

                            if ($conversion)
                            {
                                $expl = explode('.', $conversion->getURL());
                                $ext = last($expl);
                                $path = current($expl);

                                foreach ($item['compressions'] as $compression)
                                {
                                    if (!$conversion->Compressions()->filter(['Format' => $compression['format'], 'Size' => $compression['size']])->exists())
                                    {
                                        // ! only for dev TLS verification
                                        $contextOptions = [
                                          'ssl' => [
                                            'verify_peer' => false,
                                            'verify_peer_name' => false,
                                          ]
                                        ];
                                        $image = file_get_contents($compression['link'], false, stream_context_create($contextOptions));
                                        $file = $path . '.' . $compression['format'];

                                        if ($file[0] == '/')
                                        {
                                            $file = substr($file, 1);
                                        }

                                        $fs->dumpFile($file, $image);

                                        $record = ImageCompression::create();
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

                            // $path = BASE_PATH . '/public' . $conversion->getURL(); // ASSETS_PATH

                            // dump($conversion->getURL());

                        }
                    }
                }
                else
                {
                    // error $result['error']
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
