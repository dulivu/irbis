<?php
namespace Irbis\Http;
use RuntimeException;
use Irbis\File\File;
use Irbis\File\Directory;
use Irbis\Exceptions\HttpException;


class Upload 
{
    protected string $name;
    protected string $tmp_name;
    protected string $full_path;
    protected int $size;
    protected int $error;
    protected string $type;

    protected function __construct(array $file) {
        $this->name = $file['name'];
        $this->tmp_name = $file['tmp_name'];
        $this->full_path = $file['full_path'] ?? '';
        $this->size = $file['size'];
        $this->error = $file['error'];
        $this->type = $file['type'];
    }

    public static function fromGlobals(): array 
    {
        return static::normalize($_FILES);
    }

    protected static function normalize(array $files): array 
    {
        $normalized = [];

        foreach ($files as $field => $data) {

            if (is_array($data['name'])) {
                foreach ($data['name'] as $i => $name) {
                    $normalized[$field][] = new static([
                        'name' => $name,
                        'full_path' => $data['full_path'][$i] ?? '',
                        'type' => $data['type'][$i],
                        'tmp_name' => $data['tmp_name'][$i],
                        'error' => $data['error'][$i],
                        'size' => $data['size'][$i],
                    ]);
                }
            } else {
                $normalized[$field][] = new static($data);
            }
        }

        return $normalized;
    }

    public function isValid(): bool 
    {
        return $this->error === UPLOAD_ERR_OK 
            && is_uploaded_file($this->tmp_name);
    }

    public function save(string|Directory $destination): File 
    {
        if (!$this->isValid()) {
            throw $this->getError() ?: new HttpException(400, 'Invalid uploaded file.');
        }

        if ($destination instanceof Directory) {
            $file = $destination->file($this->name);
        } else {
            $destination = str_replace(['{filename}', '*'], $this->name, $destination);
            $file = new File($destination);
        }

        if (!move_uploaded_file($this->tmp_name, $file->path(true))) {
            throw new RuntimeException('Unable to move uploaded file.');
        }

        return $file;
    }

    private function getError(): ?HttpException 
    {
        switch ($this->error) {
            case UPLOAD_ERR_OK:
                return null;
            case UPLOAD_ERR_NO_FILE:
                return new HttpException(400, 'No uploaded file.');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return new HttpException(400, 'The file exceeds the size limit.');
            case UPLOAD_ERR_PARTIAL:
                return new HttpException(400, 'The file was only partially uploaded.');
            case UPLOAD_ERR_NO_TMP_DIR:
                return new HttpException(500, 'Missing temporary directory.');
            case UPLOAD_ERR_CANT_WRITE:
                return new HttpException(500, 'Failed to write file.');
            case UPLOAD_ERR_EXTENSION:
                return new HttpException(400, 'File extension blocked.');
            default:
                return new HttpException(500, 'Unknown error while uploading file.');
        }
    }
}