<?php

namespace OctoSqueeze\Silverstripe\Extensions;

use SilverStripe\Core\Extension;

class FileExtension extends Extension
{
    public function onBeforeWrite()
    {
        // $changes = $this->owner->getChangedFields(['Version', 'FileFilename']);
        // $versionChanged = isset($changes['Version']) && $changes['Version']['before'] !== $changes['Version']['after'];

        // if ($versionChanged)
        // {
        //     dd($changes);
        // }
    }
}
