<?php

namespace OctoSqueeze\Silverstripe\Tasks;

use SilverStripe\ORM\DB;
use SilverStripe\Dev\BuildTask;
use OctoSqueeze\Silverstripe\Octo;
use SilverStripe\Core\Environment;
use OctoSqueeze\Client\OctoSqueeze;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Assets\Storage\AssetStore;
use Symfony\Component\Filesystem\Filesystem;
use OctoSqueeze\Silverstripe\Models\ImageConversion;
use OctoSqueeze\Silverstripe\Models\ImageCompression;

class OSSendConversionsTask extends BuildTask
{
    private static $segment = 'OSSendConversionsTask';

    protected $enabled = true;

    protected $title = '';

    protected $description = 'Make an API call to OC, and send all existing conversions that haven\'t been compressed yet.';

    public function run($request)
    {
        $count = 0;
        $links = [];

        $store = Injector::inst()->get(AssetStore::class);
        $fsPublic = $store->getPublicFilesystem();

        $sentConversions = [];

        $config = Octo::config();

        if (Environment::hasEnv('OCTOSQUEEZE_DEV_ENV')) {
            $oc_dev_env = Environment::getEnv('OCTOSQUEEZE_DEV_ENV');
        } else {
            $oc_dev_env = false;
        }

        // Collect Stage=0 conversions and atomically mark them as Stage=-1
        // to prevent overlapping cron runs from picking up the same records
        DB::get_conn()->transactionStart();

        try {
            foreach (ImageConversion::get()->filter('Stage', 0) as $conversion)
            {
                // OC limit per request
                if ($count >= 100) {
                  break;
                }

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

                    if ($conversion->getURL(true) && $conversion->getFilename()) {
                      $links[] = [
                        'image_id' => $conversion->ID,
                        'hash' => $conversion->Hash,
                        'url' => $conversion->getURL(true),
                        'name' => $conversion->getFilename(),
                        'options' => [
                          'formats' => $config->get('required_formats'),
                          'type' => $config->get('oc_compression_type'),
                        ],
                      ];

                      $count++;
                    }
                }

            }

            DB::get_conn()->transactionEnd();
        } catch (\Exception $e) {
            DB::get_conn()->transactionRollback();
            throw $e;
        }

        if (!empty($links))
        {
            $octo = OctoSqueeze::client(Environment::getEnv('OCTOSQUEEZE_API_KEY'));

            if ($oc_dev_env) {
              // ! ONLY FOR DEV
              $octo->setEndpointUri(Environment::getEnv('OCTOSQUEEZE_ENDPOINT'));
              $octo->setHttpClientConfig(['verify' => false]);
            }

            $octo->setOptions([
              'hash_check' => true,
              'type' => $config->get('oc_compression_type'),
            ]);

            $response = $octo->squeezeUrl($links);

            if ($response && $response['state'])
            {
                $fs = new Filesystem();

                if (isset($response['items']))
                {
                    foreach($response['items'] as $item)
                    {
                        $conversion = isset($sentConversions[$item['image_id']]) ? $sentConversions[$item['image_id']] : null;

                        if ($conversion)
                        {
                            if ($item['compressed'])
                            {
                                if (isset($item['compressions']) && is_array($item['compressions']) && count($item['compressions']))
                                {
                                    $expl = explode('.', $conversion->getURL());
                                    $ext = end($expl);
                                    $path = current($expl);

                                    foreach ($item['compressions'] as $compression)
                                    {
                                        // only if this compression does not exists - add it
                                        if (!$conversion->Compressions()->filter('OctoID', $compression['id'])->exists())
                                        {
                                            $contextOptions = [
                                                'http' => ['timeout' => 30],
                                            ];
                                            if ($oc_dev_env) {
                                              $contextOptions['ssl'] = [
                                                  'verify_peer' => false,
                                                  'verify_peer_name' => false,
                                              ];
                                            }
                                            $image = file_get_contents($compression['link'], false, stream_context_create($contextOptions));

                                            $file = $path . '.' . $compression['format'];

                                            if ($file[0] == '/')
                                            {
                                                $file = substr($file, 1);
                                            }

                                            $fs->dumpFile(PUBLIC_PATH . '/' . $file, $image);

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
                                    $conversion->OctoID = $item['id'];
                                    $conversion->write();
                                }
                            }
                            else
                            {
                                $conversion->OctoID = $item['id'];
                                $conversion->Stage = 1;
                                $conversion->write();
                            }
                        }

                    }
                }
            }
        }

        print_r('
        <p>Sent files for compressions: '.$count.'</p>
        ');
    }
}
