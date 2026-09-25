# Filesystem — 0.3 development

SedoPHP provides a small filesystem contract with a dependency-free local driver.

The default root is:

```text
storage/app
```

It can be changed with:

```dotenv
FILESYSTEM_PATH=storage/app
```

## Basic usage

```php
storage()->put('reports/daily.txt', 'content');

$content = storage()->get('reports/daily.txt');

storage()->exists('reports/daily.txt');
storage()->size('reports/daily.txt');
storage()->delete('reports/daily.txt');
```

Create a directory:

```php
storage()->makeDirectory('exports');
```

List files directly inside one directory:

```php
$files = storage()->files('reports');
```

## Path safety

The local driver only accepts relative paths inside its configured root. Absolute paths, null bytes, empty segments and `..` traversal are rejected.

## Custom drivers

Custom storage implementations can implement:

```php
SedoPHP\Filesystem\FilesystemDriverInterface
```

and be installed with:

```php
SedoPHP\Filesystem\Filesystem::useDriver($driver);
```

Cloud SDKs are intentionally not included in SedoPHP core. Applications can provide an S3 or other adapter without changing the framework runtime requirements.
