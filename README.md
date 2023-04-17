# thesebas/artifact-install

Plugin that allows to install prebuilt artifacts instead of sourcecode of a package. 

## Instalation

Install this plugin inside the host app you want to use artifacts in.

```bash
composer require thesebas/artifact-install
```

In the package that offers artifacts add `extra.artifacts` key:

```json
{
    "extra": {
        "artifacts": {
            "url": "https://example.com/{name}/{version}.zip",
            "type": "zip"
        }
    }
}
```

Then the `composer install` command will fetch `composer.json` from the package for metadata and then will download configured artifact. 