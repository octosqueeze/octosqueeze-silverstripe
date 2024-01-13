<?php

namespace OctoSqueeze\Silverstripe\Tasks;

use GuzzleHttp\Client;
use SilverStripe\Dev\BuildTask;
use Symfony\Component\Filesystem\Filesystem;
use OctoSqueeze\Silverstripe\Models\ImageConversion;
use OctoSqueeze\Silverstripe\Models\ImageCompression;

class OSFetchCompressionsTask extends BuildTask
{
    private static $segment = 'OSFetchCompressionsTask';

    protected $enabled = true;

    protected $title = '';

    protected $description = 'Make an API call to OC, and fetch completed compressions that haven\'t been saved yet.';

    public function run($request)
    {
        $count = 0;

        $client = new Client([
            'verify' => false, // ! ONLY FOR DEV
        ]);

        $uri = ss_env('OCTO_IMAGE_ENDPOINT') . '/api/fetch';

        $conversions = ImageConversion::get()->filter(['Stage' => 1]);
        $conversionsArray = array_keys($conversions->map('ID')->toArray());

        if (count($conversionsArray))
        {
            $response = $client->request('POST', $uri, [
                'headers' => [
                    'Authorization' => 'Bearer ' . ss_env('OCTO_IMAGE_TOKEN'),
                    'Accept' => 'application/json',
                ],
                'form_params' => [
                    'ids' => json_encode($conversionsArray),
                ]
            ]);

            if ($response->getStatusCode() === 200)
            {
                $result = json_decode($response->getBody()->getContents(), true);

                if ($result['state'])
                {
                    $fs = new Filesystem();

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
                else
                {
                    // error $result['error']
                }
            }
        }

        print_r('
        <p>Received and saved compression files: '.$count.'</p>
        ');
    }
}
