<?php

namespace OctoSqueeze\Silverstripe\Tasks;

use SilverStripe\ORM\DB;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Dev\BuildTask;
use OctoSqueeze\Silverstripe\Models\ImageConversion;

class EraseAllAssetsTask extends BuildTask
{
    private static $segment = 'EraseAllAssetsTask';

    protected $enabled = true;

    protected $title = '';

    protected $description = 'Delete all asset files. Full clean up for a fresh start.';

    public function run($request)
    {
        $removedFiles = 0;

        foreach (File::get() as $file)
        {
            // skip initially created 'Uploads' folder
            if ($file->ID == 1 && $file->Name == 'Uploads')
            {
                continue;
            }

            DB::prepared_query("DELETE FROM \"File_EditorGroups\" WHERE \"FileID\" = ?", [$file->ID]);
            DB::prepared_query("DELETE FROM \"File_EditorMembers\" WHERE \"FileID\" = ?", [$file->ID]);
            DB::prepared_query("DELETE FROM \"File_Live\" WHERE \"ID\" = ?", [$file->ID]);
            DB::prepared_query("DELETE FROM \"File_Versions\" WHERE \"RecordID\" = ?", [$file->ID]);
            DB::prepared_query("DELETE FROM \"File_ViewerGroups\" WHERE \"FileID\" = ?", [$file->ID]);
            DB::prepared_query("DELETE FROM \"File_ViewerMembers\" WHERE \"FileID\" = ?", [$file->ID]);
            DB::prepared_query("DELETE FROM \"Image\" WHERE \"ID\" = ?", [$file->ID]);
            DB::prepared_query("DELETE FROM \"Image_Live\" WHERE \"ID\" = ?", [$file->ID]);
            DB::prepared_query("DELETE FROM \"Image_Versions\" WHERE \"RecordID\" = ?", [$file->ID]);

            $file->delete();

            $removedFiles++;
        }

        print_r('
        <p>Removed files: '.$removedFiles.'</p>
        ');
    }
}
