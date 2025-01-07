# Quail

[![Documentation Status](https://readthedocs.org/projects/quailserver/badge/?version=latest)](https://quail.docs.acugis.com/en/latest/?badge=latest)

## Transform your qgis2web maps into secure, dyanmic maps.  

QuartzMap allows you to transform your qgis2web maps into secure, dynamic maps.  

QuartzMap now also includes support for R apps as well.

![QuartzMap](QuartzMap-Main.png)
   
## Requirements

- Ubuntu 24
- 2 GB RAM (4 GB RAM if installing Demo Data)
- 15 GB Disk

## Install

Be sure to set the hostname prior to installation if you plan to provision SSL using certbot.

```bash
hostnamectl set-hostname <yourhostname>
```

Download quail-2.1.0.zip

```bash
$ unzip -q quail-2.1.0.zip
$ cd quail-2.1.0
$ ./installer/postgres.sh
$ ./installer/app-install.sh
```

Optionally, provision and SSL certificate using:

```bash
 certbot --apache --agree-tos --email hostmaster@${HNAME} --no-eff-email -d ${HNAME}
```

Once installation completes, go to https://<yourdomain.com>/admin/setup.php to complete installation
 
## Documentation

Quail Docs [Documentation](https://quail.docs.acugis.com).


## License
Version: MPL 2.0
