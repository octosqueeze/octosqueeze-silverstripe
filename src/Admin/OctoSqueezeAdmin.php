<?php

namespace OctoSqueeze\Silverstripe\Admin;

use SilverStripe\Admin\ModelAdmin;
use OctoSqueeze\Silverstripe\Models\ImageConversion;
use OctoSqueeze\Silverstripe\Models\ImageCompression;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldPrintButton;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldImportButton;

class OctoSqueezeAdmin extends ModelAdmin
{
    private static $url_segment = 'octo';
    private static $menu_title = 'OctoSqueeze Image';
    private static $menu_icon_class = 'font-icon-circle-star';
    // private static $menu_priority = -0.5;

    private static $managed_models = [
       'compressions' => [
           'dataClass' => ImageCompression::class,
           'title' => 'Compressions',
       ],
       'conversions' => [
            'dataClass' => ImageConversion::class,
            'title' => 'Conversions',
        ],
    ];

    // public $showImportForm = true;
    // public $showSearchForm = true;
    // private static $page_length = 30;

    public function getList()
    {
        $list = parent::getList();

        // ..

        return $list;
    }

    protected function getGridFieldConfig(): GridFieldConfig
    {
        $config = parent::getGridFieldConfig();

        $config->removeComponentsByType(GridFieldExportButton::class);
        $config->removeComponentsByType(GridFieldDeleteAction::class);
        $config->removeComponentsByType(GridFieldPrintButton::class);
        $config->removeComponentsByType(GridFieldImportButton::class);
        $config->removeComponentsByType(GridFieldAddNewButton::class);
        $config->removeComponentsByType(GridFieldEditButton::class);

        return $config;
    }

    public function getSearchContext()
    {
        $context = parent::getSearchContext();

        // ..

        return $context;
    }

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        // ..

        return $form;
    }

    // public function getExportFields()
    // {
    //     return [
    //         // 'Name' => 'Name',
    //         // 'Category.Title' => 'Category'
    //     ];
    // }
}
