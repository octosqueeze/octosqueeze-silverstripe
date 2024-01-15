<?php

namespace OctoSqueeze\Silverstripe\Models;

use GuzzleHttp\Client;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Assets\Storage\AssetStore;
use OctoSqueeze\Silverstripe\Models\ImageConversion;

class ImageCompression extends DataObject
{
    private static $table_name = 'ImageCompression';
    private static $singular_name = 'image compression';
    private static $plural_name = 'image compressions';

    private static $db = [
        'Format' => 'Varchar(16)',
        'Size' => 'Int',
        'OctoID' => 'Varchar(36)',
    ];

    private static $has_one = [
        'Conversion' => ImageConversion::class,
    ];

    private static $summary_fields = [
        'Format' => 'Format',
        'Size' => 'Size',
        'sf_link' => 'Origin Link',
        'sf_saved' => 'Compression percent',
    ];

    // private static $belongs_to = [];
    // private static $has_many = [];
    // private static $many_many = [];
    // private static $many_many_extraFields = [];
    // private static $belongs_many_many = [];

    // private static $default_sort = null;
    // private static $indexes = null;
    // private static $casting = [];
    // private static $defaults = [];

    // private static $field_labels = [];
    // private static $searchable_fields = [];

    // private static $cascade_deletes = [];
    // private static $cascade_duplicates = [];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // ..

        return $fields;
    }

    public function sf_link()
    {
        return $this->Conversion()->getURL();
    }

    public function sf_saved()
    {
        $originSize = $this->Conversion()->getFilesize();

        $str = number_format(100 - $this->Size / ($originSize / 100), 2) . '%';

        return $str;
    }

    // remove /assets/ from the begining
    public function getFileID($currentFileFilename = false, $withoutAssetsDir = true)
    {
        $url = $this->getURL($currentFileFilename);

        return $url ? ($withoutAssetsDir ? substr($url, strlen(ASSETS_DIR) + 2) : $url) : null;
    }

    public function getURL($currentFileFilename = false)
    {
        $conversionLink = $currentFileFilename ? $this->Conversion()->FileFilename : $this->Conversion()->getURL();

        $ext = pathinfo($conversionLink ?? '', PATHINFO_EXTENSION);

        $extLength = strlen($ext);

        return $conversionLink ? (substr($conversionLink, 0, -$extLength) . $this->Format) : null;
    }

    public function onBeforeDelete()
    {
        $store = Injector::inst()->get(AssetStore::class);
        $fsPublic = $store->getPublicFilesystem();

        // if ($this->getFileID())
        // {
        //     $fsPublic->delete($this->getFileID());
        // }

        if ($this->getFileID(true, false))
        {
            $fsPublic->delete($this->getFileID(true, false));
        }

        parent::onBeforeDelete();
    }

    // public function validate()
    // {
    //     $result = parent::validate();

    //     // $result->addError('Error message');

    //     return $result;
    // }

    // public function onBeforeWrite()
    // {
    //     $client = new Client([
    //         'verify' => false, // ! ONLY FOR DEV
    //     ]);

    //     $options = [
    //         'formats' => 'webp,avif',
    //     ];

    //     $uri = ss_env('OCTOSQUEEZE_ENDPOINT') . '/api/compress';

    //     $response = $client->request('POST', $uri, [
    //         'form_params' => [
    //             'image_id' => $this->ImageID,
    //             'url' => $this->Image()->getAbsoluteURL(),
    //             'mime_type' => $this->Image()->getMimeType(),
    //             'size' => $this->Image()->getAbsoluteSize(),
    //             'filename' => $this->Image()->getFilename(),
    //             'options' => json_encode($options),
    //         ]
    //     ]);

    //     if ($response->getStatusCode() === 200)
    //     {
    //         $result = json_decode($response->getBody()->getContents(), true);

    //         if ($result['state'])
    //         {
    //             //
    //         }
    //         else
    //         {
    //             // error $result['error']
    //         }
    //     }

    //     parent::onBeforeWrite();
    // }

    // public function canView($member = null)
    // {
    //     return Permission::check('CMS_ACCESS_Company\Website\MyAdmin', 'any', $member);
    // }

    // public function canEdit($member = null)
    // {
    //     return Permission::check('CMS_ACCESS_Company\Website\MyAdmin', 'any', $member);
    // }

    // public function canDelete($member = null)
    // {
    //     return Permission::check('CMS_ACCESS_Company\Website\MyAdmin', 'any', $member);
    // }

    // public function canCreate($member = null, $context = [])
    // {
    //     return Permission::check('CMS_ACCESS_Company\Website\MyAdmin', 'any', $member);
    // }
}
