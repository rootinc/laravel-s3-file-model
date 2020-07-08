# Laravel S3 File Model

Provides a File Model that supports direct uploads / downloads from S3 for a Laravel App.

## Installation

1. `composer require rootinc/laravel-s3-file-model`
2. Run `php artisan vendor:publish --provider="RootInc\LaravelS3FileModel\FileModelServiceProvider"` to create `File` model in `app`, `FileTest` in `tests\Unit`, `FileFactory` in `database\factories` and `2020_03_12_152841_create_files_table` in `database\migrations`
3. Run `php artisan vendor:publish  --provider="Aws\Laravel\AwsServiceProvider` which adds `aws.php` in the `config` folder
4. In the `aws.php` file, change `'region' => env('AWS_REGION', 'us-east-1'),` to use `AWS_DEFAULT_REGION`
5. In `config\filesystems.php`, add key `'directory' => '', // root dir` to `public` and add key `'directory' => env('AWS_UPLOAD_FOLDER'),` to `s3`
6. In `tests\TestCase`, add this function:
```
protected function get1x1RedPixelImage()
{
    return "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==";
}
```
7. :tada:

## Example Usage

```
<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\File;

class FileController extends Controller
{
    public function index(Request $request)
    {
        // Return 403 since ajax
        /*
        if( \Gate::forUser(Auth::user())->denies('view-org-users') )
        {
            return response()->json([
                'status' => 'error',
                'payload' => [
                    'errors' => [
                        __('auth.disallowed.ajax')
                    ]
                ]
            ], 403);
        }
        */

        $search = $request->input('search') ? $request->input('search') : "";

        $queryBuilder = File::with([
            'post' => function($q) {
                $q->withTrashed();
            },
            'content.post' => function($q) {
                $q->withTrashed();
            },
            'topic',
        ]);

        $files = $queryBuilder
        ->where(function($q) use ($search)  {
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
```

```
import React, { useState, useEffect, useRef } from 'react';
import ReactDOM from 'react-dom';
import PropTypes from 'prop-types';
import api from '../../helpers/api';

const propTypes = {
  afterSuccess: PropTypes.func,
  file: PropTypes.object,
  cloudUpload: PropTypes.bool,
};

const defaultProps = {
  afterSuccess: () => {},
  cloudUpload: false,
};

function FileUploader(props){
  const elInput = useRef(null);

  const [file, setFile] = useState(null);
  const [draggingState, setDraggingState] = useState(false);
  const [percentCompleted, setPercentCompleted] = useState(null);

  // Use dependency on props.file for when we load an existing file
  useEffect(() => {
    setFile(props.file)
  }, [props.file]);

  const dragOver = () => {
    if (percentCompleted === null)
    {
      setDraggingState(true)
    }
  };

  const dragEnd = () => {
    setDraggingState(false)
  };

  const nullImportValue = () => {
    ReactDOM.findDOMNode(elInput.current).value = null;
  };

  const handleChange = (file) => {
    const reader = new FileReader();

    reader.addEventListener("load", () => {
      if (props.cloudUpload)
      {
        pingUpload({
          file_name: file.name,
          file_type: file.type,
        }, reader.result);
      }
      else
      {
        upload({
          file_name: file.name,
          file_type: file.type,
          file_data: reader.result
        });
      }
    }, false);

    reader.readAsDataURL(file);
  };

  const pingUpload = async (data, file_data) => {
    const response = file
      ? await api.putFile(file.id, data)
      : await api.postFile(data)

    response.ok
      ? cloudUpload(response, file_data)
      : error(response)
  }

  const cloudUpload = async (response, file_data) => {
    const putCloudObject = () => {
      return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open("PUT", response.data.payload.upload_url);

        xhr.onload = () => {
          resolve(xhr);
        };

        xhr.onerror = () => {
          reject(new Error(xhr.statusText));
        };

        xhr.upload.onprogress = (e) => {
          const percentCompleted = Math.round( (e.loaded / e.total) * 100 );
          setPercentCompleted(percentCompleted);
        };

        xhr.send(file_data);
      });
    }

    const cloudResponse = await putCloudObject();

    cloudResponse.status === 200
      ? success(response)
      : error(cloudResponse.response)
  }

  const upload = async (data) => {
    const config = {
      onUploadProgress: (e) => {
        const percentCompleted = Math.round( (e.loaded / e.total) * 100 );
        setPercentCompleted(percentCompleted);
      }
    };

    const response = file
      ? await api.putFile(file.id, data, config)
      : await api.postFile(data, config)

    response.ok
      ? success(response)
      : error(response)
  };

  const success = (response) => {
    nullImportValue();

    // @todo: unwrap API response and check `response.ok`
    if (response.data.status === "success")
    {
      setFile(response.data.payload.file)
    }
    else
    {
      alert(response.data.payload.errors[0]);
    }
    setPercentCompleted(null)
    props.afterSuccess(response.data.payload.file)
  };

  const error = (error) => {
    nullImportValue();
    setPercentCompleted(null)

    alert(window._genericErrorMessage);
  };

  const renderInstructions = () => {
    if (percentCompleted === null)
    {
      return (
        <p
          style={{
            cursor: "pointer"
          }}
          onClick={() => {
            ReactDOM.findDOMNode(elInput.current).click();
          }}
        >
          <strong>{file ? "Replace" : "Choose"} File</strong> or drag it here.
        </p>
      );
    }
    else if (percentCompleted < 100)
    {
      return (
        <progress
          value={percentCompleted}
          max="100"
        >
          {percentCompleted}%
        </progress>
      );
    }
    else
    {
      return <i className="fa fa-cog fa-spin fa-3x fa-fw" aria-hidden="true" />;
    }
  };

  const renderFileInfo = () => {
    if (file)
    {
      return (
        <div>
          <p
            style={{
              marginBottom: 0
            }}
          >
            Current File:&nbsp;
            <a
              href={file.fullUrl}
              target="_blank"
              style={{
                wordBreak: "break-all"
              }}
            >
              {file.title}
            </a>
            <button
              style={{
                marginLeft: "10px",
                backgroundColor: "gray",
                padding: ".45rem .5rem .3rem .5rem"
              }}
              onClick={async () => {
                const result = prompt("New Title?", file.title);
                if (result)
                {
                  const response = await api.putFile(file.id, {title: result});

                  response.ok
                    ? success(response)
                    : error(response)
                }
              }}
            >
              Rename
            </button>
          </p>
          <p
            style={{
              marginTop: 0,
              wordBreak: "break-all"
            }}
          >
            Original Name: {file.file_name}
          </p>
        </div>
      );
    }
    else
    {
      return null;
    }
  }

  return (
    <div
      style={{
        border: "2px dashed black",
        borderRadius: "10px",
        backgroundColor: draggingState ? "white" : "lightgray",
        height: "250px",
        display: "flex",
        flexDirection: "column",
        justifyContent: "center",
        alignItems: "center",
      }}
      onClick={(e) => {e.stopPropagation();}}
      onDrag={(e) => {e.preventDefault();}}
      onDragStart={(e) => {e.preventDefault();}}
      onDragEnd={(e) => {e.preventDefault(); dragEnd();}}
      onDragOver={(e) => {e.preventDefault(); dragOver();}}
      onDragEnter={(e) => {e.preventDefault(); dragOver();}}
      onDragLeave={(e) => {e.preventDefault(); dragEnd();}}
      onDrop={(e) => {
        e.preventDefault();
        dragEnd();

        if (percentCompleted === null)
        {
          const droppedFiles = e.dataTransfer.files;
          handleChange(droppedFiles[0]);
        }
      }}
    >
      <i className="fa fa-upload" aria-hidden="true" />
      {
        renderInstructions()
      }
      {
        renderFileInfo()
      }
      <input
        ref={elInput}
        className="file-uploader"
        type="file"
        style={{
          position: "fixed",
          top: "-100em"
        }}
        onChange={(e) => {
          handleChange(e.target.files[0]);
        }}
      />
    </div>
  );
}

FileUploader.propTypes = propTypes;
FileUploader.defaultProps = defaultProps;

export default FileUploader;
```

## Contributing

Thank you for considering contributing to the Laravel S3 File Model! To encourage active collaboration, we encourage pull requests, not just issues.

If you file an issue, the issue should contain a title and a clear description of the issue. You should also include as much relevant information as possible and a code sample that demonstrates the issue. The goal of a issue is to make it easy for yourself - and others - to replicate the bug and develop a fix.

## License

The Laravel S3 File Model is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).
