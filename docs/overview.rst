Overview
==================

Quail is a lightweight Administration tool for serving QGIS Layers. 

Quail allows you to create and share both Public and Private layers as well as set Store and Layer level permissions.

Quail is Written in pure PHP to make customization and easy and accessible as possible.

The workflow is similar to GeoServer in that you created Stores and, from Stores, create Layers.

Stores cane be created from both databases as well as flat files (e.g. ESRI Shapefiles, GeoPackages, etc...).

Creation of Stores is simplified by providing only two Store types: QGIS and PostGIS

**QGIS Store**:

These consists of your QGIS Project and any flat files required.  

Flat files are Raster files, Vector files, image files, etc...

**PostGIS Stores**:

These consist of any local or remote PostGIS connections, as well as the ability to create PostGIS databases from a variety of formats.

Once a Store is created it can be either accessed directly or used to create Layers.

You can create any number of Layers from a single Store.

**QGIS Layer**:

Layers created from QGIS Stores

These layers also render any data and flat files used.

**PostGIS Layers**:

These Layers are created from PostGIS Stores. They can be served with QGIS Stores (e.g. Feature info) or independently.

   /usr/local/bin/
   /home/tomcat/tomcat-VERIONS/jasper_reports

Below is the structure and function of each.
