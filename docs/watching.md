# Filesystem monitoring

`FileSystemWatcher` uses Linux's optional `ext-inotify` extension. It starts
monitoring one directory on construction. Pass `includeSubdirectories: true`
to register watches for descendants. Read batches with
`readEvents($timeoutMilliseconds)`. The default timeout of zero checks for
available events immediately.

Each `FileSystemEvent` has a `type`, absolute `path`, and `isDirectory` flag.
`FileSystemChangeType` distinguishes `Created`, `Changed`, `Deleted`, and
`Renamed`. A paired rename also includes `oldPath`. A rename whose halves are
read in separate batches can appear as deletion and creation.

```php
use Light\IO\FileSystemChangeType;
use Light\IO\FileSystemWatcher;

$watcher = new FileSystemWatcher('uploads', includeSubdirectories: true);
try {
    foreach ($watcher->readEvents(1000) as $event) {
        if ($event->type === FileSystemChangeType::Created && !$event->isDirectory) {
            echo $event->path, PHP_EOL;
        }
    }
} finally {
    $watcher->close();
}
```

Call `readEvents()` repeatedly for ongoing monitoring. Recursive watches skip
directory symlinks and register new directories when processing events, so
entries created before registration can be missed. Queue overflow or loss
of the root closes the watcher and throws `RuntimeException`.
