# Bankart Payment Gateway Magento Plugin

## Preinstallation

1. Download .zip from GitHub repository.
1. Rename folder `bankart-magento-master` inside .zip file to `Pgc` 


### Plugin installation

The plugin's, source code must be copied (unzipped) in the `app/code` directory.
Please ensure, the proper file permissions and ownership, according to your
server's setup.

```bash
bin/magento module:enable Pgc_Pgc
bin/magento setup:upgrade
bin/magento setup:di:compile
```

### Plugin configuration

Goto: `Stores -> Configuration -> Sales -> Payment Methods ->Pgc`

