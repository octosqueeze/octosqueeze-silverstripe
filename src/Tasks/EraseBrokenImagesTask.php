<?php

namespace OctoSqueeze\Silverstripe\Tasks;

use SilverStripe\ORM\DB;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Dev\BuildTask;
use OctoSqueeze\Silverstripe\Models\ImageConversion;

class EraseBrokenImagesTask extends BuildTask
{
    private static $segment = 'EraseBrokenImagesTask';

    protected $enabled = true;

    protected $title = '';

    protected $description = 'Delete all broken `File cannot be found` images.';

    public function run($request)
    {
        $removedFiles = 0;

        $files = File::get()->filter([
          'ClassName' => ['SilverStripe\Assets\Image', 'SilverStripe\Assets\File'],
          'FileHash' => null,
          'FileFilename' => null,
        ]);

        foreach ($files as $file)
        {
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
