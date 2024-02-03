<?php

namespace OctoSqueeze\Silverstripe\Assets\Flysystem;

use GuzzleHttp\Client;
use SilverStripe\Assets\File;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Assets\Storage\AssetStore;
use Symfony\Component\Filesystem\Filesystem;
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
                    // $conversion->ImageID = $file->ID;
                    $conversion->write();

                    $parsedFile = $conversion->getParsedFileID();
                    // $conversion->MimeType = $fsPublic->mimeType($parsedFile->getFileID());
                    // $conversion->Size = $fsPublic->fileSize($parsedFile->getFileID());
                    // $conversion->write();

                    $file->Conversions()->add($conversion);

                    // $refreshedConversion = ImageConversion::get()->byID($conversion->ID);

                    // $this->sendToCompress($refreshedConversion, $file);

                    // $fs = new Filesystem();
                    // $fs->appendToFile('test.txt', 'ID:' . $conversion->ID .' ImageID:' . $conversion->ImageID  .' Stage:'.$conversion->Stage.' ISPublish:'. ($conversion->Image()->isPublished() ? 'yes':'no') . ' ' . ($conversion->isInDB() ? 'in' : 'out') . 'FileID:' . $file->ID .  ' Variant:' .$variant . '...' . PHP_EOL);
                }
            }
            else
            {
                // origin (writes in ImageExtension)
            }
        }




        return $return;
    }

    // ! NOT in used currently
    private function sendToCompress($conversion, $file)
    {
        if ($conversion->Image()->isPublished() && $conversion->Stage === 0)
        {
            $clientSet = [];

            if (Environment::hasEnv('OCTOSQUEEZE_DEV_ENV')) {
                $oc_dev_env = Environment::getEnv('OCTOSQUEEZE_DEV_ENV');

                if ($oc_dev_env) {
                  $clientSet = [
                    'verify' => false, // ! ONLY FOR DEV
                  ];
                }
            }

            $client = new Client($clientSet);

            // $fs = new Filesystem();
            // $fs->appendToFile('test.txt', PHP_EOL . $conversion->Variant . ' - ' . json_encode([
            //   'id' => $conversion->ID,
            //   'link' => $conversion->getURL(true),
            //   'name' => $conversion->getFilname(),
            //   'size' => $conversion->getFilesize(),
            //   'mime_type' => $conversion->getMimeType(),
            //   'options' => ['formats' => 'webp,avif'],
            // ]));

            $uri = ss_env('OCTOSQUEEZE_ENDPOINT') . '/api/compress';

            $conversion->Stage = 1;
            $conversion->write();

            try {

            $response = $client->request('POST', $uri, [
                'timeout' => 1,
                'headers' => [
                    'Authorization' => 'Bearer ' . ss_env('OCTOSQUEEZE_API_KEY'),
                    'Accept' => 'application/json',
                ],
                'form_params' => [
                    'id' => $conversion->ID,
                    'hash' => $conversion->Hash,
                    'link' => $conversion->getURL(true),
                    'name' => $conversion->getFilname(),
                    'size' => $conversion->getFilesize(),
                    'mime_type' => $conversion->getMimeType(),
                    'hash' => $conversion->Hash,
                    'options' => ['formats' => 'webp,avif'],
                ]
            ]);

            // if ($response->getStatusCode() === 200)
            // {
            //     $result = json_decode($response->getBody()->getContents(), true);

            //     if ($result['state'])
            //     {
            //         $conversion->Stage = 1;
            //         $conversion->write();
            //     }
            //     else
            //     {
            //         // error $result['error']
            //     }
            // }

              } catch (\GuzzleHttp\Exception\ConnectException $e) {
                // do nothing, the timeout exception is intended
            }
        }
    }
}
