<?php

namespace OctoSqueeze\Silverstripe\Tasks;

use GuzzleHttp\Client;
use SilverStripe\Dev\BuildTask;
use OctoSqueeze\Silverstripe\Octo;
use SilverStripe\Core\Environment;
use OctoSqueeze\Client\OctoSqueeze;
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

        $octo = OctoSqueeze::client(ss_env('OCTOSQUEEZE_API_KEY'));

        $config = Octo::config();

        if (Environment::hasEnv('OCTOSQUEEZE_DEV')) {
            $oc_dev_env = Environment::getEnv('OCTOSQUEEZE_DEV');
        } else {
            $oc_dev_env = false;
        }

        if ($oc_dev_env) {

          // ! ONLY FOR DEV
          $octo->setEndpointUri(ss_env('OCTOSQUEEZE_ENDPOINT'));
          $octo->setHttpClientConfig(['verify' => false]);
        }

        $octo->setOptions(['hash_check' => true]);

        $conversions = ImageConversion::get()->filter(['Stage' => 1])->limit(20); // max urls per one request (according to OctoSqueeze API)
        $conversionsArray = array_keys($conversions->map('OctoID')->toArray());

        if (count($conversionsArray))
        {
            $response = $octo->take($conversionsArray);

            $bundle = $response->getBundle();

            if ($bundle && count($bundle))
            {
                $fs = new Filesystem();

                foreach($bundle as $item)
                {
                    $conversion = ImageConversion::get()->filter(['OctoID' => $item['id'], 'Stage' => 1])->first();

                    if ($conversion)
                    {
                        $expl = explode('.', $conversion->getURL());
                        $ext = last($expl);
                        $path = current($expl);

                        foreach ($item['compressions'] as $compression)
                        {
                            if (!$conversion->Compressions()->filter(['OctoID' => $compression['id']])->exists())
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

                    // $path = BASE_PATH . '/public' . $conversion->getURL(); // ASSETS_PATH

                    // dump($conversion->getURL());

                }
            }
        }

        print_r('
        <p>Received and saved compression files: '.$count.'</p>
        ');
    }
}
