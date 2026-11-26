# Path strings

`Path` uses the host platform's separators and root rules.

| Method | Use |
|---|---|
| `combine()` | Join components. A rooted component replaces the accumulated path. |
| `getFileName()` | Return the final component. |
| `getExtension()` | Return the suffix including its dot, or an empty string. |
| `changeExtension()` | Replace the suffix. Pass `null` to remove it. |
| `isPathRooted()` | Check for a root or, on Windows, a drive prefix. |
| `getFullPath()` | Resolve against an absolute base or the working directory, collapsing `.` and `..`. |
| `getRelativePath()` | Compute the path from a base directory. A different root yields an absolute path. |

`getFullPath()` works with missing paths and leaves symlinks unresolved.
Windows drive-relative paths require a base on the same drive. Windows device
paths are outside the supported scope.

```php
use Light\IO\Path;

$csv = Path::combine('uploads', 'report.csv');
$json = Path::changeExtension($csv, 'json');
$absolute = Path::getFullPath($json, __DIR__);
```
