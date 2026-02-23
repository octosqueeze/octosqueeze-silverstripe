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

        if (Environment::hasEnv('OCTOSQUEEZE_DEV_ENV')) {
            $oc_dev_env = Environment::getEnv('OCTOSQUEEZE_DEV_ENV');
        } else {
            $oc_dev_env = false;
        }

        if ($oc_dev_env) {

          // ! ONLY FOR DEV
          $octo->setEndpointUri(ss_env('OCTOSQUEEZE_ENDPOINT'));
          $octo->setHttpClientConfig(['verify' => false]);
        }

        $octo->setOptions([
          'hash_check' => true,
          'type' => $config->get('oc_compression_type'),
        ]);

        $conversions = ImageConversion::get()->filter(['Stage' => 1])->limit(500);

        if ($conversions->count())
        {
            $fs = new Filesystem();

            foreach ($conversions as $conversion)
            {
                if (!$conversion->OctoID) {
                    continue;
                }

                $status = $octo->getStatus($conversion->OctoID);

                if (!$status['state'] || ($status['data']['status'] ?? '') !== 'completed') {
                    continue;
                }

                $data = $status['data'];
                $downloadUrl = $data['download_url'] ?? null;

                if (!$downloadUrl) {
                    continue;
                }

                $format = $data['format'] ?? 'webp';

                if (!$conversion->Compressions()->filter(['OctoID' => $conversion->OctoID])->exists())
                {
                    if ($oc_dev_env) {
                        $contextOptions = [
                            'ssl' => [
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                            ]
                        ];
                        $image = file_get_contents($downloadUrl, false, stream_context_create($contextOptions));
                    } else {
                        $image = $octo->download($downloadUrl);
                    }

                    if ($image) {
                        $expl = explode('.', $conversion->getURL());
                        $path = current($expl);
                        $file = $path . '.' . $format;

                        if ($file[0] == '/') {
                            $file = substr($file, 1);
                        }

                        $fs->dumpFile(PUBLIC_PATH . '/' . $file, $image);

                        $record = ImageCompression::create();
                        $record->OctoID = $conversion->OctoID;
                        $record->Format = $format;
                        $record->Size = $data['compressed_size'] ?? 0;
                        $record->write();

                        $conversion->Compressions()->add($record);

                        $count++;
                    }
                }

                $conversion->Stage = 2;
                $conversion->write();
            }
        }

        print_r('
        <p>Received and saved compression files: '.$count.'</p>
        ');
    }
}
