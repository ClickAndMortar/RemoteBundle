# Remote - Click And Mortar

The Remote bundle by Click And Mortar is designed to facilitate files transfer from remote server.

Made by :heart: by C&M

## Installation

Add package with composer:
```bash
composer require clickandmortar/remote-bundle "^1.0"
```

Add bundle in your **`app/AppKernel.php`** file:
```php
$bundles = array(
            ...
            new ClickAndMortar\RemoteBundle\ClickAndMortarRemoteBundle(),
        );
```

## Usage

### Download

To download files from a remote server, you can use bundle command:

```
php bin/console candm:remote:get -t <type> -w <password> -x <newExtension> -d <server> <user> <distantFilePaths> <localDirectory>
```

### Upload

To upload files to a remote server, you can use bundle command:

```
php bin/console candm:remote:put -t <type> -w <password> -d <server> <user> <localFilePath> <distantFilePath>
```