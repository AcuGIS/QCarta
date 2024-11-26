
************
Overview
************

Quail Overview
==================

Quail is a lightweight Administration tool and layer server for QGIS Server. 

You can create and share both Public and Private layers as well as set Layer level permissions.

Quail is Written in pure PHP to make customization as easy and accessible as possible.

The workflow is similar to GeoServer in that you create Stores and, from Stores, create Layers.
Stores cane be created from both databases as well as flat files (e.g. ESRI Shapefiles, GeoPackages, etc...).
Creation of Stores is simplified by providing only two Store types: QGIS and PostGIS

**QGIS Store**:

These consists of your QGIS Project and any flat files required.  
Flat files are Raster files, Vector files, image files, etc...

**PostGIS Stores**:

These consist of any local or remote PostGIS connections, as well as the ability to create PostGIS databases from a variety of formats.
Once a Store is created it can be either accessed directly or used to create Layers.
You can create any number of Layers from a single Store.

**Layers**

Layers, in turn, can be created from QGIS and PostGIS Stores.

**MapProxy**

Quail also installs MapProxy, for caching.  Quail Authentication is integrated with MapProxy.
