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

You can also create new, empty PostGIS databases as well.


Create From Connection
================

To add a new Link, click the "Add New" button at top.

.. image:: add-new-postgis-store.png

Populate the required fields for your PostgreSQL connection

.. image:: postgis-create-1-qcarta.png





Under Database, click the "Load Database Names" icon

The list of available databases will appear.  Select the database you wish to connect to.

.. image:: postgis-create-2-qcarta.png



Select the Group(s) that will have permission to the Store

.. image:: postgis-create-3-qcarta.png

Click Save Connection.  Your Store has been created.

.. image:: postgis-create-4-qcarta.png


Create From File(s)
=====================

You can create a PostGIS database from most common data sources, such as ESRI Shapefiles and GeoPackages

You can also create databases from PostGIS backups.

To create a PostGIS database from a file, click the Create button at top right

.. image:: create-new-store.png

Give your database a name and click the Choose Files button.


.. image:: upload-qcarta-db-1.png

Browse to the file(s) location


.. image:: upload-qcarta-db-2.png

Click the Impprt button

.. image:: upload-qcarta-db-3.png

The import results are displayed at the bottom of the page.

.. image:: upload-qcarta-db-4.png


.. note::
   You may need to click the PostGIS tab to refresh before seeing your new database

Create Empty Database
=====================

You can create an empty PostGIS database.

An empty database is useful if you have a QGIS Project and want to connect to the database to import layers.

To create an empty PostGIS database, 

Click the Create Button

.. image:: create-new-store.png

Give your database a name

.. image:: empty-db-1.png

Check the "Create Database Only" checkbox.

.. image:: empty-db-2.png

Click the Create button.

Your database has been created and added as a PostGIS Store

.. image:: empty-db-3.png


To view the database connection information, click the Connection icon at right

.. image:: empty-db-4.png

This information can be used in your pg_service.conf file and any other location


.. image:: pg-service-connection.png


Layer Creation
=====================

If your QGIS Project uses a PostGIS backend, the PostGIS Store will be automatically detected when the QGIS Store is added.

Once you have created a PostGIS Store, it can be used to create a PostGIS Layer.



Backup, Clone, and Restore
=====================

You can Backup, Clone, and Restore your databases via the PostGIS tab.

.. image:: postgis-backup.png

postgis-backup-name.png

.. image:: postgis-backup-name.png

postgis-clone.png

.. image:: postgis-clone.png

postgis-clone-clone.png

.. image:: postgis-clone-clone.png

postgis-clone-verified.png

.. image:: postgis-clone-verified.png

postgis-restore.png

.. image:: postgis-restore.png

postgis-restore-select.png

.. image:: postgis-restore-select.png

postgis-show-connection.png

.. image:: postgis-show-connection.png

postgis-show-connection-show.png

.. image:: postgis-show-connection-show.png







