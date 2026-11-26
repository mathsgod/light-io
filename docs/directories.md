# Directories and drives

## Directory and DirectoryInfo

`Directory` offers static methods to check and create directories, enumerate
immediate files or subdirectories, and delete empty directories.
`createDirectory()` creates parent directories as needed.

`DirectoryInfo` represents a directory path. Its enumeration methods yield
`FileInfo` or `DirectoryInfo` objects. `getDirectories()` collects matching
directories into an array. The default `SearchOption::TopDirectoryOnly`
looks at immediate children. `SearchOption::AllDirectories` walks nested
directories, skipping the contents of directory symlinks. The optional
name pattern accepts `*` and `?`.

```php
use Light\IO\DirectoryInfo;
use Light\IO\SearchOption;

$directory = new DirectoryInfo('reports');
foreach ($directory->enumerateDirectories('archive*', SearchOption::AllDirectories) as $entry) {
    echo $entry->fullName(), PHP_EOL;
}
```

## FileSystemInfo

`FileSystemInfo` is the shared base of `FileInfo` and `DirectoryInfo`.
`name()` returns the final path component, `fullName()` returns the stored
absolute path, and `lastWriteTime()` returns a Unix timestamp. Relative paths
are anchored when the object is created. Objects can represent missing paths.

## DriveInfo

Construct `DriveInfo` with an existing local directory. `getTotalSize()`
and `getAvailableFreeSpace()` return bytes as PHP floats for the filesystem
containing it. Each call queries the filesystem. Query failures throw
`RuntimeException`.

```php
use Light\IO\DriveInfo;

$drive = new DriveInfo(__DIR__);
echo $drive->getAvailableFreeSpace(), PHP_EOL;
```
