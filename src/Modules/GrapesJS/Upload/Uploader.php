<?php

namespace Vihzhuo\Modules\GrapesJS\Upload;

use Exception;
use stdClass;

/**
 * File uploader class.
 *
 * Credits: https://github.com/brunoribeiro94/php-upload
 *
 * @package Vihzhuo\Modules\GrapesJS\Upload
 */
class Uploader extends stdClass {

    /**
     * Indicates if the file has been uploaded properly.
     *
     * @var bool
     */
    public bool $was_uploaded = false;

    /**
     * Contains error message.
     *
     * @var string
     */
    public string $error;

    /**
     * Filename.
     *
     * @var string
     */
    public mixed $file_src_name;

    /**
     * File extension.
     *
     * @var string|array
     */
    public string|array $file_src_name_ext;

    /**
     * Uploaded file size, in bytes.
     *
     * @var double
     */
    public mixed $file_src_size;

    /**
     * Uploaded file size (in bytes).
     *
     * @var int
     */
    public int $file_src_errors;

    /**
     * File source temp.
     *
     * @var string
     */
    protected string $file_src_temp;

    /**
     * Uploaded file MIME type.
     *
     * @access public
     * @var string
     */
    public string $file_src_mime;

    /**
     * Final file name.
     *
     * @var string
     */
    public string $final_file_name;

    /**
     * File resolution width.
     *
     * @var int
     */
    public int $file_width;

    /**
     * File resolution height.
     *
     * @var int
     */
    public int $file_height;

    /**
     * Set this variable to change the maximum size in bytes for an uploaded file.
     * Default value is the value upload_max_filesize from php.ini.
     * Remember you can also set calling $Upload->file_max_size($value).
     *
     * @var double
     */
    protected $get_file_max_size = 25000000;

    /**
     * Set auto replace if the file already exist.
     * Remember you can also set calling $Upload->auto_replace($value_bool).
     *
     * @var bool
     */
    protected bool $get_auto_replace = true;

    /**
     * Set auto create path if the folder not exist.
     * Remember you can also set calling $Upload->auto_create_path($value_bool).
     *
     * @var bool
     */
    protected bool $get_auto_create_path = true;

    /**
     * Allowed MIME types.
     *
     * @var array
     */
    public array $MIME_allowed = [
        "application/arj",
        "application/excel",
        "application/gnutar",
        "application/msword",
        "application/mspowerpoint",
        "application/octet-stream",
        "application/pdf",
        "application/powerpoint",
        "application/postscript",
        "application/plain",
        "application/rtf",
        "application/vnd.ms-excel",
        "application/vocaltec-media-file",
        "application/wordperfect",
        "application/x-bzip",
        "application/x-bzip2",
        "application/x-compressed",
        "application/x-excel",
        "application/x-gzip",
        "application/x-latex",
        "application/x-midi",
        "application/x-msexcel",
        "application/x-rtf",
        "application/x-sit",
        "application/x-stuffit",
        "application/x-shockwave-flash",
        "application/x-troff-msvideo",
        "application/x-zip-compressed",
        "application/xml",
        "application/zip",
        "audio/aiff",
        "audio/basic",
        "audio/midi",
        "audio/mod",
        "audio/mpeg",
        "audio/mpeg3",
        "audio/wav",
        "audio/x-aiff",
        "audio/x-au",
        "audio/x-mid",
        "audio/x-midi",
        "audio/x-mod",
        "audio/x-mpeg-3",
        "audio/x-wav",
        "audio/xm",
        "image/bmp",
        "image/gif",
        "image/jpeg",
        "image/pjpeg",
        "image/x-icon",
        "image/png",
        "image/x-png",
        "image/tiff",
        "image/x-tiff",
        "image/x-windows-bmp",
        "multipart/x-zip",
        "multipart/x-gzip",
        "music/crescendo",
        "text/richtext",
        "text/plain",
        "text/xml",
        "video/avi",
        "video/mpeg",
        "video/msvideo",
        "video/quicktime",
        "video/quicktime",
        "video/x-mpeg",
        "video/x-ms-wmv",
        "video/x-ms-asf",
        "video/x-ms-asf-plugin",
        "video/x-msvideo",
        "x-music/x-midi"
    ];

    /**
     * Uploader constructor.
     *
     * @param array|string $source      image source
     * @param bool $fromFiles        read from $_FILE
     * @return bool
     */
    public function __construct(array|string $source, bool $fromFiles = true) {
        $file = $fromFiles ? $_FILES[$source] : $source;
        if (! isset($file)) {
            return false;
        }

        // extract info from uploaded file
        $this->file_src_name = $file["name"];
        $this->file_src_temp = $file["tmp_name"];
        $this->file_src_size = $file["size"];
        $this->file_src_errors = $file['error'];
        $this->file_src_mime = $file['type'];
        $this->file_src_name_ext = pathinfo($file["name"], PATHINFO_EXTENSION);

        $this->was_uploaded = true;
        return true;
    }

    /**
     * Run the uploader.
     *
     * @return bool
     * @throws Exception
     */
    public function run(): bool
    {
        // check whether the final name is in random mode
        if ($this->file_name !== true) {
            // preserve file extension if provided
            if (! pathinfo($this->file_name, PATHINFO_EXTENSION)) {
                $file = $this->file_name . '.' . $this->file_src_name_ext;
            } else {
                $file = $this->file_name;
            }
            $path = $this->upload_to . $file;
            $this->upload_to = dirname($path);
        } else {
            $hash = md5(uniqid(rand(), true));
            $file = $hash . '.' . $this->file_src_name_ext;
            $path = $this->upload_to . $file;
        }

        $get_mime = isset($this->mime_check);

        // check MIME type which are allowed
        if ($get_mime && empty($this->file_src_mime)) {
            $this->was_uploaded = false;
            $this->error = "MIME type can't be detected!";
        } elseif ($get_mime && !empty($this->file_src_mime) && !in_array($this->file_src_mime, $this->MIME_allowed)) {
            $this->was_uploaded = false;
            $this->error = "Incorrect type of file";
        }

        // check file maximum size
        if ($this->file_src_size > $this->get_file_max_size) {
            $this->error = 'File too big Original Size : ' . $this->file_src_size . ' File size limit : ' . $this->get_file_max_size;
            $this->was_uploaded = false;
            return false;
        }

        // check whether destination directory exists or attempt to create it
        if ($this->get_auto_create_path) {
            if (! $this->r_mkdir($this->upload_to)) {
                $this->was_uploaded = false;
                $this->error = "Destination directory can't be created. Can't carry on a process";
                return false;
            }
        } elseif (! is_dir($this->upload_to)) {
            $this->error = "Destination directory doesn't exist. Can't carry on a process";
            return false;
        }

        // check whether file already exists or if are replacing it
        if (file_exists($path)) {
            if (! $this->get_auto_replace) {
                $this->was_uploaded = false;
                $this->error = $this->file_src_name . ' already exists. Please change the file name';
            }
            return false;
        }

        // check common error types
        switch ($this->file_src_errors) {
            case 0:
                // all is OK
                break;
            case 1:
                $this->was_uploaded = false;
                $this->error = "File upload error (the uploaded file exceeds the upload_max_filesize directive in php.ini)";
                break;
            case 2:
                $this->was_uploaded = false;
                $this->error = "File upload error (the uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the html form)";
                break;
            case 3:
                $this->was_uploaded = false;
                $this->error = "File upload error (the uploaded file was only partially uploaded)";
                break;
            case 4:
                $this->was_uploaded = false;
                $this->error = "File upload error (no file was uploaded)";
                break;
            default:
                $this->was_uploaded = false;
                $this->error = "File upload error (unknown error code)";
        }

        // move file if successfully uploaded
        if ($this->was_uploaded) {
            if (move_uploaded_file($this->file_src_temp, $path)) {
                $this->final_file_name = $file;

                // extract image dimensions
                list($w, $h) = getimagesize($path);
                $this->file_width = $w;
                $this->file_height = $h;

                // resize image if necessary
                if (isset($this->resize)) {
                    $resize = new ResizeImage($path);
                    $resize->resizeTo($this->image_x, $this->image_y, $this->resize_option);
                    $resize->saveImage($path);
                }
                return true;
            }
            $this->was_uploaded = false;
            $this->error = 'Moving uploaded file failed.';
        }

        return false;
    }

    /**
     * Create directories recursively.
     *
     * @param string $path        Path to create
     * @param int $mode        Optional permissions
     * @return bool Success
     */
    protected function r_mkdir(string $path, int $mode = 0777): bool
    {
        return is_dir($path) || ( $this->r_mkdir(dirname($path), $mode) && $this->_mkdir($path, $mode) );
    }

    /**
     * Create directory.
     *
     * @param string $path        Path to create
     * @param int $mode        Optional permissions
     * @return bool Success
     */
    protected function _mkdir(string $path, int $mode = 0777): bool
    {
        $old = umask(0);
        $res = @mkdir($path, $mode);
        umask($old);
        return $res;
    }

    /**
     * Set the variable this function to define final name of the uploaded file.
     * Use the value true for generate unique name (random name).
     *
     * @param bool|string $file_name     Final name of the file uploaded
     * @return Uploader
     */
    public function file_name(bool|string $file_name): static
    {
        $this->file_name = $file_name;
        return $this;
    }

    /**
     * Set the variable this function to define auto replacement if the file already exist.
     *
     * @param bool $bool
     * @return Uploader
     */
    public function auto_replace(bool $bool): static
    {
        $this->get_auto_replace = $bool;
        return $this;
    }

    /**
     * Set the variable this function to define max size file is allowed.
     *
     * @param int $size         Maximum size in Bytes (1 000 000 bytes is equal to 1 MB)
     * @return Uploader
     */
    public function file_max_size(int $size): static
    {
        $this->get_file_max_size = (int) $size;
        return $this;
    }

    /**
     * Set the variable this function to false if you don't want to check the MIME against the allowed list.
     * This variable is set to true by default for security reason.
     *
     * @param bool $bool         Set to false to not check
     * @return Uploader
     */
    public function mime_check(bool $bool = true): static
    {
        $this->mime_check = $bool;
        return $this;
    }

    /**
     * Set the variable this function to choose a new path location for the image.
     *
     * @param string $path          Path location of the uploaded file, with an ending slash
     * @return Uploader
     */
    public function upload_to(string $path): static
    {
        $this->upload_to = $path;
        return $this;
    }

    /**
     * Set the variable this function to true to resize the file if it is an image.
     * You will probably want to set {@link image_x()} and {@link image_y()}, and maybe one of the ratio variables.
     *
     * @param bool $bool         Default value is false (no resizing)
     * @return Uploader
     */
    public function resize(bool $bool = false): static
    {
        $this->resize = $bool;
        return $this;
    }

    /**
     * Set the variable this function to the wanted (or maximum/minimum) width for the processed image, in pixels.
     *
     * @param int $width        Default value is 150
     * @return Uploader
     */
    public function image_x(int $width): static
    {
        $this->image_x = $width;
        return $this;
    }

    /**
     * Set the variable this function to the wanted (or maximum/minimum) height for the processed image, in pixels.
     *
     * @param int $height       Default value is 150
     * @return Uploader
     */
    public function image_y(int $height = 150): static
    {
        $this->image_y = $height;
        return $this;
    }

    /**
     * Set the variable this function to choose the scale option for the image.
     *
     * @param string $option        Default value is default, but you can use: exact, maxwidth, maxheight or proportional
     * @return Uploader
     */
    public function resize_option(string $option = 'default'): static
    {
        $this->resize_option = $option;
        return $this;
    }

    /**
     * Alias for {@link resize()} instead of using both {@link image_x()} && {@link image_y()} && {@link resize_option()}.
     *
     * @param int $width            Max width of the image
     * @param int $height           Max height of the image
     * @param string $resizeOption      Scale option for the image
     * @return Uploader
     */
    public function resize_to(int $width = 150, int $height = 150, string $resizeOption = 'default'): static
    {
        self::resize(true);
        self::image_x($width);
        self::image_y($height);
        self::resize_option($resizeOption);
        return $this;
    }

}
