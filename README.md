# Remote Bundle - C&M

> Remote Bundle is designed to facilitate files transfer from remote server.

Made with :blue_heart: by C&M

## Installation

### Download the Bundle

```console
$ composer require clickandmortar/remote-bundle
```

### Enable the Bundle

Enable the bundle by adding it to the list of registered bundles
in the `app/AppKernel.php` file of your project:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            // ...
            new ClickAndMortar\RemoteBundle\ClickAndMortarRemoteBundle(),
        ];

        // ...
    }

    // ...
}
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