.. This is a comment. Note how any initial comments are moved by
   transforms to after the document title, subtitle, and docinfo.

.. demo.rst from: http://docutils.sourceforge.net/docs/user/rst/demo.txt

.. |EXAMPLE| image:: static/yi_jing_01_chien.jpg
   :width: 1em

**********************
PostGIS Stores
**********************

.. contents:: Table of Contents
Overview
==================

PostGIS Stores are connections to PostGIS databases.

These can be existing local or remote PostGIS databases.

You can also create new PostGIS databases from GeoPackages, ESRI Shapefiles, and PostgreSQL backups.

Create From Connection
================

To add a new Link, click the "Add New" button at top.

1-add-new.png

.. image:: 1-add-new.png

Populate the required fields for your PostgreSQL connection

.. image:: 2-add-new.png

Under Database, click the "Load Database Names" icon

.. image:: 3-add-new.png

The list of available databases will appear.  Select the database you wish to connect to.

.. image:: 4-add-new.png

Select the Group(s) that will have permission to the Store

.. image:: 5-add-new.png

Click Save.  Your Store has been created.

.. image:: 6-add-new.png

Create Empty Database
=====================

You can create an empty PostGIS database.

An empty database is useful if you have a QGIS Project and want to connect to the database to import layers.

To create an empty PostGIS database, 

Click the Create Button

.. image:: create-new-store.png

Give your database a name and check the "Create Only" box.

.. image:: create-db-only.png

Your database has been created and added as a PostGIS Store

.. image:: db-connection-info.png

To view the database connection information, click the Connection icon at right

.. image:: pg-service-connection.png

.. image:: show-connection.png




Create From File(s)
=====================

You can create a PostGIS database from most common data sources, such as ESRI Shapefiles and GeoPackages, as well as from PostGIS backups.

To create a PostGIS database, click the Import button at top right

.. image:: 7-import.png

Give your database a name and click the upload button

.. image:: 8-import.png

Browse to the location of the file you wish to import

.. image:: 9-import.png

Click Import

.. image:: 10-import.png

The import results are displayed at the bottom of the page.

.. image:: 11-import.png

Click the PostGIS tab to refresh.  Your PostGIS database has now been created.

.. image:: 12-import.png




.. image:: create-store-from-geopackage.png

.. image:: create-store-from-geopackage-2.png

.. image:: create-store-from-geopackage-3.png





