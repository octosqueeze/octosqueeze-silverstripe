<?php

namespace OctoSqueeze\Silverstripe\Controllers;

use OctoSqueeze\Silverstripe\Octo;
use SilverStripe\Core\Environment;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use Symfony\Component\Filesystem\Filesystem;
use OctoSqueeze\Silverstripe\Models\ImageConversion;
use OctoSqueeze\Silverstripe\Models\ImageCompression;

class OctoController extends Controller
{
    private static $url_handlers = [
        'POST completed' => 'completed', // TODO: available in GET? prefix POST not working
    ];

    private static $allowed_actions = [
        'completed',
    ];

    protected function init()
    {
        parent::init();

        // ..
    }

    public function completed(HTTPRequest $request)
    {
        $images = $request->postVar('images');
        $item = current(json_decode($images, true));

        $config = Octo::config();

        if (Environment::hasEnv('OCTOSQUEEZE_DEV_ENV')) {
            $oc_dev_env = Environment::getEnv('OCTOSQUEEZE_DEV_ENV');
        } else {
            $oc_dev_env = false;
        }

        $fs = new Filesystem();
        // $fs->dumpFile('test.txt', $item['image_id']);

        $conversion = ImageConversion::get()->filter('id', $item['image_id'])->first();

        if ($conversion && $conversion->getURL())
        {
            $expl = explode('.', $conversion->getURL());
            $ext = last($expl);
            $path = current($expl);

            foreach ($item['compressions'] as $compression)
            {
                if (!$conversion->Compressions()->filter(['Format' => $compression['format'], 'Size' => $compression['size']])->exists())
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
                    $record->Format = $compression['format'];
                    $record->Size = $compression['size'];
                    $record->write();

                    $conversion->Compressions()->add($record);
                }
            }

            $conversion->Stage = 2;
            $conversion->write();
        }
    }
}
