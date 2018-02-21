# Remote - Click And Mortar

The Remote bundle by Click And Mortar is designed to facilitate files transfer from remote server.

## Installation

Add package your **`composer.json`** file:
```javascript
"require": {
    ...
    "clickandmortar/remote-bundle": "^1.0"
    ...
}
```

Launch `composer update` to add bundle to your project:
```bash
composer update clickandmortar/remote-bundle
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