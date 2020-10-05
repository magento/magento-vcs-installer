# Magento VCS Installer

This tool provides a possibility to deploy Magento source code on environments where Composer packages are required.

## Installation

```
composer config repositories.installer git git@github.com:magento/magento-vcs-installer.git
composer config minimum-stability dev
composer require "magento/magento-vcs-installer:dev-master" --no-update
```

## Mocking version

Create a file `.magento.env.yaml` with the version which will represent the Magento version:

```
stage:
  global:
    DEPLOYED_MAGENTO_VERSION_FROM_GIT: '2.4.0'
```

## Configuration

```
"extra": {
    "deploy": {
        "version": "2.4.0",
        "repo": {
            "ce": {
                "url": "git@github.com:magento-commerce/magento2ce.git",
                "ref": "2.4-develop",
                "base": true
            },
            "ee": {
                "url": "git@github.com:magento-commerce/magento2ee.git",
                "ref": "2.4-develop",
                "base": true
            }
        }
    }
}
```

## Usage

After initial installation you'll have to trigger `composer update` command to re-build `composer.lock` files with dependencies from `git` sources.
