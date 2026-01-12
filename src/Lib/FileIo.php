<?php /** @noinspection PhpUnused */

namespace Timeax\FortiPlugin\Lib;

use Timeax\FortiPlugin\Lib\Utils\FileIoUtility;
use Illuminate\Support\Facades\Storage;
use Timeax\FortiPlugin\Permissions\Evaluation\Dto\FileRequest;

class FileIo extends FileIoUtility
{
    /**
     * Read file contents from the (sandboxed) storage.
     *
     * @param string $path
     * @param string|null $disk
     * @return string|null
     */
    public function get(string $path, ?string $disk = null): ?string
    {
        $disk = $this->check('read', $path, $disk);
        if (!$disk) return null;

        if (!Storage::disk($disk)->exists($path)) {
            return null;
        }

        return Storage::disk($disk)->get($path);
    }

    /**
     * Write contents to a file in storage (the plugin folder is always allowed; other storage paths require permission).
     */
    public function put(string $path, string $contents, ?string $disk = null): bool
    {
        $disk = $this->check('write', $path, $disk, true);
        if (!$disk) return false;

        return Storage::disk($disk)->put($path, $contents);
    }

    /**
     * Append data to a file in storage (enforces permission and sandbox).
     */
    public function append(string $path, string $data, ?string $disk = null): bool
    {
        $disk = $this->check('write', $path, $disk, true);
        if (!$disk) return false;

        return Storage::disk($disk)->append($path, $data);
    }

    /**
     * Prepend data to a file in storage (enforces permission and sandbox).
     */
    public function prepend(string $path, string $data, ?string $disk = null): bool
    {
        $disk = $this->check('write', $path, $disk, true);
        if (!$disk) return false;

        return Storage::disk($disk)->prepend($path, $data);
    }

    /**
     * Check if a file exists in storage (enforces sandbox restriction).
     */
    public function exists(string $path, ?string $disk = null): bool
    {
        $disk = $disk ?: $this->forcedDisk ?: 'local';

        if (!$this->isPathAllowed($path, $disk)) {
            return false;
        }

        return Storage::disk($disk)->exists($path);
    }

    /**
     * Check if a file is missing in storage (enforces sandbox restriction).
     */
    public function missing(string $path, ?string $disk = null): bool
    {
        $disk = $disk ?: $this->forcedDisk ?: 'local';

        if (!$this->isPathAllowed($path, $disk)) {
            return true;
        }

        return !Storage::disk($disk)->exists($path);
    }

    /**
     * Determine if the given path is a file (enforces sandbox restriction).
     */
    public function isFile(string $path, ?string $disk = null): bool
    {
        $disk = $disk ?: $this->forcedDisk ?: 'local';

        if (!$this->isPathAllowed($path, $disk)) {
            return false;
        }

        $absPath = realpath(Storage::disk($disk)->path($path));
        return $absPath && is_file($absPath);
    }

    /**
     * Determine if the given path is a directory (enforces sandbox restriction).
     */
    public function isDirectory(string $path, ?string $disk = null): bool
    {
        $disk = $disk ?: $this->forcedDisk ?: 'local';

        if (!$this->isPathAllowed($path, $disk)) {
            return false;
        }

        $absPath = realpath(Storage::disk($disk)->path($path));
        return $absPath && is_dir($absPath);
    }

    private function prepareCopy(string $from, string $to, ?string $disk = null): ?string
    {
        $disk = $disk ?: $this->forcedDisk ?: 'local';
        
        // Check read on source
        if (!$this->check('read', $from, $disk)) return null;
        
        // Check write on dest (parent)
        if (!$this->check('write', $to, $disk, true)) return null;

        return $disk;
    }

    /**
     * Copy a file to a new location (enforces permission and sandbox for both source and destination).
     */
    public function copy(string $from, string $to, ?string $disk = null): bool
    {
        $disk = $this->prepareCopy($from, $to, $disk);
        if (!$disk) return false;
        
        return Storage::disk($disk)->copy($from, $to);
    }

    /**
     * Move a file to a new location (enforces permission and sandbox for both source and destination).
     */
    public function move(string $from, string $to, ?string $disk = null): bool
    {
        $disk = $this->prepareCopy($from, $to, $disk);
        if (!$disk) return false;

        return Storage::disk($disk)->move($from, $to);
    }

    /**
     * Delete one or more files (enforces permission and sandbox for each path).
     */
    public function delete(string|array $paths, ?string $disk = null): bool
    {
        $disk = $disk ?: $this->forcedDisk ?: 'local';
        $paths = (array)$paths;

        foreach ($paths as $path) {
            if (!$this->check('write', $path, $disk)) {
                return false;
            }
        }

        return Storage::disk($disk)->delete($paths);
    }

    /**
     * Change the permissions of a file (enforces permission and sandbox).
     */
    public function chmod(string $path, int $mode, ?string $disk = null): bool
    {
        $disk = $this->check('write', $path, $disk);
        if (!$disk) return false;

        $absPath = realpath(Storage::disk($disk)->path($path));
        return $absPath && chmod($absPath, $mode);
    }

    /**
     * Get an array of files in a directory (enforces sandbox and 'read' permission on directory).
     */
    public function files(string $directory = '', ?string $disk = null): array
    {
        $disk = $this->check('read', $directory, $disk);
        if (!$disk) return [];

        return Storage::disk($disk)->files($directory);
    }

    /**
     * Get all files (recursively) in a directory (enforces sandbox and 'read' permission on directory).
     */
    public function allFiles(string $directory = '', ?string $disk = null): array
    {
        $disk = $this->check('read', $directory, $disk);
        if (!$disk) return [];

        return Storage::disk($disk)->allFiles($directory);
    }

    /**
     * Get all directories within a directory (enforces sandbox and 'read' permission).
     */
    public function directories(string $directory = '', ?string $disk = null): array
    {
        $disk = $this->check('read', $directory, $disk);
        if (!$disk) return [];

        return Storage::disk($disk)->directories($directory);
    }

    /**
     * Create a directory (enforces sandbox and 'write' permission).
     */
    public function makeDirectory(string $path, ?string $disk = null): bool
    {
        $disk = $this->check('write', $path, $disk, true);
        if (!$disk) return false;

        return Storage::disk($disk)->makeDirectory($path);
    }

    /**
     * Delete a directory and its contents (enforces sandbox and 'write' permission).
     */
    public function deleteDirectory(string $directory, ?string $disk = null): bool
    {
        $disk = $this->check('write', $directory, $disk);
        if (!$disk) return false;

        return Storage::disk($disk)->deleteDirectory($directory);
    }

    /**
     * Get the file size (enforces sandbox and 'read' permission).
     */
    public function size(string $path, ?string $disk = null): int|false
    {
        $disk = $this->check('read', $path, $disk);
        if (!$disk) return false;

        return Storage::disk($disk)->size($path);
    }

    /**
     * Get the last modified time (enforces sandbox and 'read' permission).
     */
    public function lastModified(string $path, ?string $disk = null): int|false
    {
        $disk = $this->check('read', $path, $disk);
        if (!$disk) return false;

        return Storage::disk($disk)->lastModified($path);
    }

    /**
     * Get the file's MIME type (enforces sandbox and 'read' permission).
     */
    public function mimeType(string $path, ?string $disk = null): string|false
    {
        $disk = $this->check('read', $path, $disk);
        if (!$disk) return false;

        return Storage::disk($disk)->mimeType($path);
    }

    /**
     * Get the file's extension (enforces sandbox).
     */
    public function extension(string $path, ?string $disk = null): string|false
    {
        $disk = $disk ?: $this->forcedDisk ?: 'local';

        if (!$this->isPathAllowed($path, $disk)) {
            return false;
        }

        $absPath = realpath(Storage::disk($disk)->path($path));
        return $absPath ? pathinfo($absPath, PATHINFO_EXTENSION) : false;
    }

    /**
     * Get the basename of a file path (no permission required).
     */
    public function basename(string $path): string
    {
        return basename($path);
    }

    /**
     * Get the directory name of a file path (no permission required).
     */
    public function dirname(string $path): string
    {
        return dirname($path);
    }

    /**
     * Get the URL for a file (enforces sandbox and 'read' permission).
     */
    public function url(string $path, ?string $disk = null): string
    {
        $disk = $this->check('read', $path, $disk);
        if (!$disk) return '';

        return Storage::disk($disk)->url($path);
    }

    /**
     * Get a temporary URL for a file (enforces sandbox and 'read' permission).
     */
    public function temporaryUrl(string $path, $expiration, ?string $disk = null): string
    {
        $disk = $this->check('read', $path, $disk);
        if (!$disk) return '';

        return Storage::disk($disk)->temporaryUrl($path, $expiration);
    }

    /**
     * Get the full storage path for a given file or directory.
     */
    public function storagePath(string $path = ''): string
    {
        return storage_path($path);
    }

    /**
     * Get the absolute path for a file on a specific disk.
     */
    public function path(string $path = '', ?string $disk = null): string
    {
        $disk = $disk ?: $this->forcedDisk ?: 'local';
        return Storage::disk($disk)->path($path);
    }

    /**
     * Get a read stream for a file (enforces sandbox and 'read' permission).
     */
    public function readStream(string $path, ?string $disk = null)
    {
        $disk = $this->check('read', $path, $disk);
        if (!$disk) return false;

        return Storage::disk($disk)->readStream($path);
    }

    /**
     * Write to a file using a stream (enforces sandbox and 'write' permission).
     */
    public function writeStream(string $path, $resource, ?string $disk = null): bool
    {
        $disk = $this->check('write', $path, $disk, true);
        if (!$disk) return false;

        return Storage::disk($disk)->writeStream($path, $resource);
    }

    /**
     * Get a FileIo instance for a different disk (keeps permission enforcement).
     */
    protected ?string $forcedDisk = "local";

    public function disk(string $name): static
    {
        return new self(["forcedDisk" => $name]);
    }

    /**
     * Helper to validate path and check permission.
     *
     * @param string $action
     * @param string $path
     * @param string|null $disk
     * @param bool $parent If true, checks permission on the parent directory (for creating files).
     * @return string|null The resolved disk name if allowed, null otherwise.
     */
    private function check(string $action, string $path, ?string $disk, bool $parent = false): ?string
    {
        $disk = $disk ?: $this->forcedDisk ?: 'local';

        if (!$this->isPathAllowed($path, $disk)) {
            return null;
        }

        $fullPath = Storage::disk($disk)->path($path);
        $absPath = $parent
            ? (realpath(dirname($fullPath)) ?: $fullPath)
            : (realpath($fullPath) ?: $fullPath);

        $this->checkModulePermission(new FileRequest(
            action: $action,
            baseDir: $disk,
            path: (string)$absPath
        ));

        return $disk;
    }
}