<?php

namespace OctoSqueeze\Silverstripe\Models;

use GuzzleHttp\Client;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataObject;
use SilverStripe\Control\Director;
use SilverStripe\Security\Permission;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Assets\Storage\AssetStore;
use Symfony\Component\Filesystem\Filesystem;
use OctoSqueeze\Silverstripe\Models\ImageCompression;
use SilverStripe\AssetAdmin\Controller\AssetAdmin;
use SilverStripe\Assets\FilenameParsing\ParsedFileID;

class ImageConversion extends DataObject
{
    private static $table_name = 'ImageConversion';
    private static $singular_name = 'image conversion';
    private static $plural_name = 'image conversions';

    private static $db = [
        'FileHash' => 'Varchar',
        'FileFilename' => 'Varchar',
        'Variant' => 'Varchar',
        'Hash' => 'Varchar',
        'Stage' => 'Int', // 0 - null, 1 - in progress (sent to compress), 2 - compressed (all requested compressions are saved)
    ];

    private static $defaults = [
        'Variant' => null,
        'Stage' => 0,
    ];

    private static $has_one = [
        'Image' => Image::class,
    ];

    private static $owns = [
        'Image',
    ];

    private static $has_many = [
       'Compressions' => ImageCompression::class,
    ];

    private static $cascade_deletes = [
        'Compressions',
    ];

    // private static $belongs_to = [];
    // private static $many_many = [];
    // private static $many_many_extraFields = [];
    // private static $belongs_many_many = [];

    // private static $default_sort = null;
    // private static $indexes = null;
    // private static $casting = [];
    // private static $defaults = [];

    private static $summary_fields = [
        'sf_URL' => 'Preview',
        'getWidth' => 'Width',
        'getHeight' => 'Height',
        'sf_manipulation' => 'Manipulation',
        'sf_filesize' => 'Size',
        'sf_Compressions' => 'Compressions',
    ];
    // private static $field_labels = [];
    // private static $searchable_fields = [];

    // private static $cascade_duplicates = [];

    public function getParsedFileID()
    {
        $store = Injector::inst()->get(AssetStore::class);
        $fsPublic = $store->getPublicFilesystem();
        $strategyPublic = $store->getPublicResolutionStrategy();

        $image = $this->Image();

        $parsedFileID = new ParsedFileID($image->getFilename(), $image->getHash(), $this->Variant);
        $buildFile = $strategyPublic->buildFileID($parsedFileID);

        if ($fsPublic->has($buildFile))
        {
            return $strategyPublic->resolveFileID($buildFile, $fsPublic);
        }

        return null;
    }

    public function getURL($absoluteUrl = false)
    {
        $parsedFile = $this->getParsedFileID();

        if ($parsedFile)
        {
            $store = Injector::inst()->get(AssetStore::class);
            $fsPublic = $store->getPublicFilesystem();
            $adapter = $fsPublic->getAdapter();

            $link = $adapter->getPublicUrl($parsedFile->getFileID());

            if ($absoluteUrl)
            {
                return Director::absoluteURL($link);
            }
            else
            {
                return $link;
            }
        }
        else if (!$this->Variant)
        {
            return $this->Image()->getURL();
        }
    }

    public function getFilname()
    {
        return $this->getURL() ? last(explode('/', $this->getURL())) : null;
    }

    public function getMimeType()
    {
        $parsedFile = $this->getParsedFileID();

        if ($parsedFile)
        {
            $store = Injector::inst()->get(AssetStore::class);
            $fsPublic = $store->getPublicFilesystem();

            return $fsPublic->mimeType($parsedFile->getFileID());
        }

        return null;
    }

    public function getFileSize()
    {
        $parsedFile = $this->getParsedFileID();

        if ($parsedFile)
        {
            $store = Injector::inst()->get(AssetStore::class);
            $fsPublic = $store->getPublicFilesystem();

            return $fsPublic->fileSize($parsedFile->getFileID());
        }

        return null;
    }

    public function getAttributes()
    {
        if ($this->Variant)
        {
            $variantName = $this->Variant;

            $methods = array_map('preg_quote', singleton(Image::class)->allMethodNames() ?? []);

            $focustPointMethods = [
              'focusfillmax' => 'FocusFillMax',
              'focuscropwidth' => 'FocusCropWidth',
              'focuscropheight' => 'FocusCropHeight',
              'focusfill' => 'FocusFill',
            ];

            // add focus point extra methods
            $methods = array_merge($methods, $focustPointMethods);

            // fixing core issue, methods should be sorted by the length of the name, otherwise preg_match will return first matched one (eg: on FitMax without the sort Fit will be returned which is incorrect)
            $keys = array_map('strlen', array_keys($methods));
            array_multisort($keys, SORT_DESC, $methods);

            // Regex needs to be case insensitive since allMethodNames() is all lowercased
            $regex = '#^(?<format>(' . implode('|', $methods) . '))(?<encodedargs>(.*))#i';
            preg_match($regex ?? '', $variantName ?? '', $matches);

            if ($matches && isset($matches['encodedargs']) && isset($matches['format'])) {

              $args = Convert::base64url_decode($matches['encodedargs']);

              if ($args) {

                return array_merge([$matches['format']], $args);
              }
            }
        }
    }

    public function getWidth()
    {
        $attrs = $this->getAttributes();

        if ($attrs)
        {
            return count($attrs) === 3 ? $attrs[1] : $attrs[3];
        }
        else
        {
            return $this->Image()->getWidth();
        }
    }

    public function getHeight()
    {
        $attrs = $this->getAttributes();

        if ($attrs)
        {
            return count($attrs) === 3 ? $attrs[2] : $attrs[4];
        }
        else
        {
            return $this->Image()->getWidth();
        }
    }

    public function getManipulation()
    {
        $attrs = $this->getAttributes();

        if ($attrs)
        {
            return $attrs[0];
        }
    }

    public function getFocusX()
    {
        $attrs = $this->getAttributes();

        if ($attrs && count($attrs) === 5)
        {
            return (float) $attrs[1];
        }
    }

    public function getFocusY()
    {
        $attrs = $this->getAttributes();

        if ($attrs && count($attrs) === 5)
        {
            return (float) $attrs[2];
        }
    }

    public function sf_Compressions()
    {
        $str = '';

        $str .= '<ul>';

        foreach ($this->Compressions() as $compression)
        {
            $percent = 100 - ($compression->Size / ($this->getFilesize() / 100));
            $str .= '<li><span>' . $compression->Format . '</span> ' . File::format_size($compression->Size) . ' (-'. number_format($percent, 2) .'%)</li>';
        }

        $str .= '</ul>';

        $html = DBHTMLText::create();
        $html->setValue($str);

        return $html;
    }

    public function sf_manipulation()
    {
        $m = $this->getManipulation();

        return $m ?? '[origin]';
    }

    public function sf_filesize()
    {
        return File::format_size($this->getFilesize());
    }

    public function sf_URL()
    {
        $assetsAdminCfg = AssetAdmin::config();

        $img = $this->Image()->FitMax($assetsAdminCfg->uninherited('thumbnail_width'), $assetsAdminCfg->uninherited('thumbnail_height'));

        if ($img)
        {
            $width = $this->getWidth();
            $height = $this->getHeight();

            $pWH = $width / $height;

            $thWidth = 60;
            $thHeight = 60 / $pWH;

            $str = '<a href="' . $this->getURL(true) . '" target="_blank"><img src="' . $img->getURL() . '" width="' . $thWidth . '" height="' . $thHeight . '" style="object-fit: cover"></a>';

            $html = DBHTMLText::create();
            $html->setValue($str);

            return $html;
        }
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // ..

        return $fields;
    }

    // public function validate()
    // {
    //     $result = parent::validate();

    //     // $result->addError('Error message');

    //     return $result;
    // }

    public function onBeforeWrite()
    {
        // $fs = new Filesystem();
        // $fs->appendToFile('test.txt', 'ID:' . $this->ID .' ImageID:' . $this->ImageID  .' Stage:'.$this->Stage.' ISPublish:'. ($this->Image()->isPublished() ? 'yes':'no') . ' ' . ($this->isInDB() ? 'in' : 'out'));

                // if (!$this->isInDB())
                // {
                //     // sending conversion to compress only when image is published
                //     if ($this->Image()->isPublished() && $this->Stage === 0)
                //     {
                //         $client = new Client([
                //             'verify' => false, // ! ONLY FOR DEV
                //         ]);

                //         $uri = ss_env('OCTO_IMAGE_ENDPOINT') . '/api/compress';

                //         $this->Stage = 1;

                //         try {
                //             $response = $client->request('POST', $uri, [
                //                 'timeout' => 1,
                //                 'form_params' => [
                //                     'id' => $this->ID,
                //                     'link' => $this->getURL(true),
                //                     'name' => $this->getFilname(),
                //                     'size' => $this->getFilesize(),
                //                     'mime_type' => $this->getMimeType(),
                //                     'options' => ['formats' => 'webp,avif'],
                //                 ]
                //             ]);

                //             // if ($response->getStatusCode() === 200)
                //             // {
                //             //     $result = json_decode($response->getBody()->getContents(), true);

                //             //     if ($result['state'])
                //             //     {
                //             //         $this->Stage = 1;
                //             //         // $this->write();
                //             //     }
                //             //     else
                //             //     {
                //             //         // error $result['error']
                //             //     }
                //             // }

                //           } catch (\GuzzleHttp\Exception\ConnectException $e) {
                //             // do nothing, the timeout exception is intended
                //         }
                //     }
                // }

        parent::onBeforeWrite();
    }

    // public function onBeforeDelete()
    // {
    //     $store = Injector::inst()->get(AssetStore::class);
    //     $fsPublic = $store->getPublicFilesystem();

    //     if ($this->FileFilename)
    //     {
    //         foreach ($this->Compressions() as $compression)
    //         {
    //             if ($compression->getFileID())
    //             {
    //                 $fsPublic->delete($compression->getFileID());
    //             }

    //             if ($compression->getFileID(true))
    //             {
    //                 $fsPublic->delete($compression->getFileID(true));
    //             }
    //         }
    //     }

    //     parent::onBeforeDelete();
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
