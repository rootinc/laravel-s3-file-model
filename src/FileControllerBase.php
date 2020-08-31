<?php

namespace RootInc\LaravelS3FileModel;

use App\Http\Controllers\Controller;
use App\File;

use Illuminate\Http\Request;

class FileControllerBase extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search') ? $request->input('search') : "";

        $files = File::where(function($q) use ($search)  {
            $q->where('file_name', 'ILIKE', "%$search%")
            ->orWhere('title', 'ILIKE', "%$search%");
        })
        ->orderBy('file_name')
        ->paginate();

        return response()->json([
            'status' => "success",
            'payload' => [
                'files' => $files,
            ]
        ]);
    }

    /**
     * Creates and uploads a file
     *
     * @param Illuminate\Http\Request $request
     * @return Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $file_name = $request->input('file_name');
        $file_type = $request->input('file_type');
        $file_data = $request->input('file_data');

        //this is set up with two distinct routes so that the FileUploader component can still call the `store / post` method on the file object
        //in the cloud route, we are doing a direct upload to s3
        //in the other route, we are doing a an upload to the server, then to s3
        if (!$file_data)
        {
            $data = File::s3CreateUpload(new File, $file_name, $file_type);
            $file = $data['file'];
            $upload_url = $data['upload_url'];

            $file->refresh();

            return response()->json([
                'status' => "success",
                'payload' => [
                    'file' => $file,
                    'upload_url' => $upload_url,
                ]
            ]);
        }
        else
        {
            $file = File::uploadAndCreateFileFromDataURI($file_name, $file_type, $file_data);
            $file->refresh();

            return response()->json([
                'status' => "success",
                'payload' => [
                    'file' => $file
                ]
            ]);
        }
    }

    /**
     * Updates and uploads replacement file
     *
     * @param Illuminate\Http\Request $request
     * @return Illuminate\Http\Response
     */
    public function update(Request $request, File $file)
    {
        //this is set up with two distinct routes so that the FileUploader component can still call the `update / put` method on the file object
        //in the title route, we only need to update the title of the file
        //in the other route, we are doing a replacement file
        if ($request->input('title'))
        {
            $file->title = $request->input('title');
            $file->save();

            return response()->json([
                'status' => "success",
                'payload' => [
                    'file' => $file
                ]
            ]);
        }
        else
        {
            File::deleteUpload($file->location);

            $file_name = $request->input('file_name');
            $file_type = $request->input('file_type');
            $file_data = $request->input('file_data');

            //this is set up with two distinct routes so that the FileUploader component can still call the `update / put` method on the file object
            //in the cloud route, we are doing a direct upload to s3
            //in the other route, we are doing a an upload to the server, then to s3
            if (!$file_data)
            {
                $data = File::s3CreateUpload($file, $file_name, $file_type);
                $file = $data['file'];
                $upload_url = $data['upload_url'];

                $file->refresh();

                return response()->json([
                    'status' => "success",
                    'payload' => [
                        'file' => $file,
                        'upload_url' => $upload_url,
                    ]
                ]);
            }
            else
            {
                $uploadedFile = File::makeUploadFileFromDataURI($file_name, $file_type, $file_data);
                $upload_location = File::upload($uploadedFile, null, true);

                $file->file_name = $file_name;
                $file->file_type = $uploadedFile->getClientMimeType();
                $file->location = $upload_location;

                $file->save();

                return response()->json([
                    'status' => "success",
                    'payload' => [
                        'file' => $file
                    ]
                ]);
            }
        }
    }

    /**
     * Deletes and deletes uploaded file
     *
     * @param Illuminate\Http\Request $request
     * @return Illuminate\Http\Response
     */
    public function destroy(Request $request, File $file)
    {
        File::deleteUpload($file->location);

        $file->delete();

        return response()->json([
            'status' => "success",
            'payload' => []
        ]);
    }
}