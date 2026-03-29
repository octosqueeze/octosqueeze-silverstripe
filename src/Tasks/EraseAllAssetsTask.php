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
        if (!$request->getVar('confirm')) {
            print_r('<p><strong>WARNING:</strong> This will permanently delete ALL asset files. This cannot be undone.</p>');
            print_r('<p>To proceed, re-run with <code>?confirm=1</code></p>');
            return;
        }

        $removedFiles = 0;

        foreach (File::get() as $file)
        {
            // skip all folders
            if ($file->ParentID === 0 && $file->FileHash == NULL && $file->FileFilename == NULL)
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
