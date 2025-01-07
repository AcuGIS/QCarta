# Quail

[![Documentation Status](https://readthedocs.org/projects/quailserver/badge/?version=latest)](https://quail.docs.acugis.com/en/latest/?badge=latest)

## QGIS Server Adminstration and Layer Server 

Publish your QGIS Projects as OGC Web Services.  

Group level permissions and integration MapProxy caching.

![QuartzMap](QuartzMap-Main.png)
   
## Requirements

- Ubuntu 24
- 2 GB RAM
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
