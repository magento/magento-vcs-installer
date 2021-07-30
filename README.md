# Magento VCS Installer

This tool provides a possibility to deploy Magento source code on environments where Composer packages are required.

## Installation

### Clone Magento Cloud project locally

1. Open Magento Cloud UI and find link to clone via Git or `magento-cloud` CLI
1. Clone repository
1. Navigate to the cloned directory

### Remove Magento dependencies

1. Remove all `magento/*` dependencies from `require` section of `composer.json`

### Add auth.json

Add an auth.hson file with credentials:

```json
{
    "http-basic": {
        "repo.magento.com": {
            "username": "<Public Key>",
            "password": "<Private Key>"
        }
    },
    "github-oauth": {
        "github.com": "<GitHub Token>"
    }
}
```

### Add dependencies

```
composer config repositories.installer git git@github.com:magento-commerce/magento-vcs-installer.git
composer config minimum-stability dev
composer require "magento/magento-vcs-installer:^1.0" --no-update
composer require "magento/ece-tools" --no-update
```

### Install dependencies

```
composer update
```

## Mock version

Create a file `.magento.env.yaml` with the version which will represent the Magento version:

```
stage:
  global:
    DEPLOYED_MAGENTO_VERSION_FROM_GIT: '2.4.2'
```

## Configuration

### Repositories

Specify a list of git repositories in root `composer.json` file of your project via `deploy -> repo` value. The name should represent the project:

```json
"extra": {
    "deploy": {
        "repo": {
            "magento/magento2ce": {
                "url": "git@github.com:magento-commerce/magento2ce.git",
                "ref": "dev-2.4-develop"
            },
            "magento/magento2ee": {
                "url": "git@github.com:magento-commerce/magento2ee.git",
                "ref": "dev-2.4-develop"
            },
            "magento/inventory": {
                "url": "git@github.com:magento-commerce/inventory.git",
                "ref": "dev-1.2-develop"
            }
        }
    }
}
```

### Copy strategy

You may specify a copying strategy, using `deploy -> strategy` value:


```json
"extra": {
    "deploy": {
        "strategy": "copy",
        "repo": {}
    }
}
```

The possible strategies are:

- **symlink**  - Defalut strategy for local developement and testing.
- **copy** - Default strategy for deployments on Cloud. Is automatially selected if Cloud enviroenmnt is detected.


## Usage

After initial installation you'll have to trigger `composer update` command to re-build `composer.lock` files with dependencies from `git` sources.

## Troubleshooting

### Composer timeout error

>   [Symfony\Component\Process\Exception\ProcessTimedOutException]                                  
  The process "git status --porcelain --untracked-files=no" exceeded the timeout of 300 seconds.  

#### Reason

Composer tries to clone a large repository and exceeds default timeout.

#### Solution

Add next configuration to the root `composer.json`:

```json
"config": {
    "process-timeout": 0
}
```
