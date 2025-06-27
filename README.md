# QCarta

[![Documentation Status](https://readthedocs.org/projects/quailserver/badge/?version=latest)](https://quail.docs.acugis.com/en/latest/?badge=latest)

## QGIS Map Portal 

Publish your QGIS Projects quickly and easily.  

Group level permissions and integration MapProxy caching.

![Quail](docs/_static/QCarta-Main.png)

  
## Requirements

- Ubuntu 24
- 2 GB RAM
- 5 GB Disk

## Install

Be sure to set the hostname prior to installation if you plan to provision SSL using certbot.

```bash
hostnamectl set-hostname <yourhostname>
```

### Installer

```bash
   git clone https://github.com/AcuGIS/qcarta.git
   cd qcarta
   ./installer/postgres.sh
   ./installer/app-install.sh
```

Optionally, provision and SSL certificate using:

```bash
 apt-get -y install python3-certbot-apache
 certbot --apache --agree-tos --email hostmaster@${HNAME} --no-eff-email -d ${HNAME}
```

Default credentials

   - Email: admin@admin.com
   - Password: quail

### Docker (Not for Production Use)

```bash
git clone https://github.com/AcuGIS/qcarta.git
$ cd qcarta
$ ./installer/docker-install.sh
$ docker-compose pull

Before calling up set docker/public.env with values used on your machine!
$ docker-compose up

If you want to build from source, run next command.
$ docker-compose build
```

URL: http://yourdomain.com:8000

## Features and Demos

QCarta includes sample projects to get you started

### Features

- Layer level permissions
- Maps
- Documents
- Links
- GeoStories
- SQL Views
- SQL Reports
- Plotlt Support
- SQL Workshop
- OGC Web Services (WMS, WFS, and WMTS)
- Topics and Keywords
- Metadata

<p>&nbsp;</p>

### Demo Projects

![QuailMap](docs/_static/QCarta-Github-Readme.png)

### Plotly Chart Support:

![QuailMap](docs/_static/QCarta-Github-Readme-2.png)

### Saved Queries

![QuailMap](docs/_static/QCarta-Github-Readme-Query.png)

### SQL Reports

![QuailMap](docs/_static/QCarta-Github-Readme-SQL.png)

### GeoStories

![QuailMap](docs/_static/GeoStories.png)

## Documentation

Quail Docs [Documentation](https://quail.docs.acugis.com).



## License
Version: MPL 2.0
