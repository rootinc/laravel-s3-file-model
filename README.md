# Laravel S3 File Model

Provides a File Model that supports direct uploads / downloads from S3 for a Laravel App.

## Installation

1. `composer require rootinc/laravel-s3-file-model`
2. Run `php artisan vendor:publish --provider="RootInc\LaravelS3FileModel\FileModelServiceProvider"` to create `File` model in `app`, `FileTest` in `tests\Unit`, `FileFactory` in `database\factories`, `2020_03_12_152841_create_files_table` in `database\migrations`, and `FileController` in `app\Http\Controllers`
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
7. Update routing.  We can use this as an example: `Route::apiResource('files', 'FileController')->only(['index', 'store', 'update', 'destroy']);`
8. :tada:

## Example React FileUploader

```
import React, { useState, useEffect, useRef } from 'react';
import ReactDOM from 'react-dom';
import PropTypes from 'prop-types';
import api from '../../helpers/api';

const propTypes = {
  afterSuccess: PropTypes.func,
  file: PropTypes.object,
  cloudUpload: PropTypes.bool,
  style: PropTypes.object,
};

const defaultProps = {
  afterSuccess: () => {},
  cloudUpload: false,
  style: {},
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

  const handleChange = (blob) => {
    const reader = new FileReader();

    reader.addEventListener("load", () => {
      if (props.cloudUpload)
      {
        pingUpload({
          file_name: blob.name,
          file_type: blob.type,
        }, blob); //XMLHttpRequest can take a raw file blob, which works better for streaming the file
      }
      else
      {
        upload({
          file_name: blob.name,
          file_type: blob.type,
          file_data: reader.result
        });
      }
    }, false);

    reader.readAsDataURL(blob);
  };

  const pingUpload = async (data, blob) => {
    const response = file
      ? await api.putFile(file.id, data)
      : await api.postFile(data)

    response.ok
      ? cloudUpload(response, blob)
      : error(response)
  }

  const cloudUpload = async (response, blob) => {
    const putCloudObject = () => {
      return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open("PUT", response.data.payload.upload_url);
        xhr.setRequestHeader("Content-Type", response.data.payload.file.file_type);

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

        //thankfully blobs can be sent up, and this works better https://developer.mozilla.org/en-US/docs/Web/API/XMLHttpRequest/send
        xhr.send(blob);
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

  const style = Object.assign({
    border: "2px dashed black",
    borderRadius: "10px",
    backgroundColor: draggingState ? "white" : "lightgray",
    height: "250px",
    display: "flex",
    flexDirection: "column",
    justifyContent: "center",
    alignItems: "center",
  }, props.style);

  return (
    <div
      style={style}
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
