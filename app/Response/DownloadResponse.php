<?php

namespace App\Response;

use Storage;
use Response;
use App\File;

// use Chumper\Zipper\Zipper;

class DownloadResponse
{
    // private $zipper;

    private $file;

    public function __construct(File $file)
    {
        $this->file = $file;
        // $this->zipper = $zipper;
    }

    /**
     * Return download or preview response for given file.
     *
     * @param File $upload
     *
     * @return mixed
     */
    public function singleDownload($id)
    {
        $upload = $this->file->whereHash($id)->firstOrFail();

        if ($upload->type == 'folder') {
            return $this->folderDownload($upload);
        }

        $headers = [
            'Content-Type' => $upload->mime,
        ];

        $filename = str_slug(str_before($upload->name, '.'), '-');

        $filename = "$filename.$upload->extension";

        $filepath = Storage::drive('uploads_local')->path($upload->getStoragePath());

        return response()->download($filepath, $filename, $headers);
    }

    public function folderDownload($upload)
    {
        $zip_name = str_random(10);
        $zip_name = config('app.name').'-'.$zip_name;

        $this->zipper->make(storage_path("public/$zip_name.zip"));

        $this->fileRecussive($upload);
        $this->zipper->close();

        $headers = [
            'Content-Type' => 'application/zip',
        ];

        return response()->download(storage_path("public/$zip_name.zip"), "$zip_name.zip", $headers)->deleteFileAfterSend(true);
    }

    public function fileRecussive($file, $folderName = '')
    {
        if ($file->type == 'folder') {
            $folderName = "$folderName/$file->name";

            foreach ($this->file->where('parent_id', $file->id)->cursor() as $chield) {
                $this->fileRecussive($chield, $folderName);
            }
        } else {
            $filename = str_slug(str_before($file->name, '.'), '-');
            $filename = "$filename.$file->extension";

            $this->zipper->folder($folderName)->addString($filename, Storage::drive('uploads_local')->get($file->getStoragePath()));
        }
    }

    public function multipleDownload($ids)
    {
        $zip_name = str_random(10);
        $zip_name = config('app.name').'-'.$zip_name;

        $this->zipper->make(storage_path("public/$zip_name.zip"));

        if (is_array($ids)) {
            foreach ($this->file->whereIn('id', $ids)->cursor() as $chield) {
                $this->fileRecussive($chield);
            }
        }

        $this->zipper->close();

        $headers = [
            'Content-Type' => 'application/zip',
        ];

        return response()->download(storage_path("public/$zip_name.zip"), "$zip_name.zip", $headers)->deleteFileAfterSend(true);
    }
}
