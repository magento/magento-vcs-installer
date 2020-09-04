# magento-vcs-installer

## Instalaltion

```
composer config repositories.installer git git@github.com:shiftedreality/magento-vcs-installer.git
composer config minimum-stability dev
composer require "shiftedreality/magento-vcs-installer:dev-master" --no-update
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
                }
            }
        },
    }
```
