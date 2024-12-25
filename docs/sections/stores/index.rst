.. This is a comment. Note how any initial comments are moved by
   transforms to after the document title, subtitle, and docinfo.

.. demo.rst from: http://docutils.sourceforge.net/docs/user/rst/demo.txt

.. |EXAMPLE| image:: static/yi_jing_01_chien.jpg
   :width: 1em

**********************
Stores
**********************

.. contents:: Table of Contents
Overview
==================

Quail divides Stores into two types:

* File Store
* PostGIS Store

These Stores can be used to support virtually all GIS data formats.

File Store
================

A File Store can be created from any of the following:

 - A QGIS Project File and any associated Raster, Vector, and static files.
 - Spatial Data File Only.  Supported types are TIFF, GeoTIFF, Shapefile, or GeoJSON
 - A PostGIS Store

Examples of static data sources include:

* GeoTiff
* shapefile
* GeoPackage
* GeoJson
* etc....

.. note:: 
      If your QGIS Project uses PostGIS as a data source, create a PostGIS Store.


PostGIS Store
=====================

A PostGIS Store is used when your QGIS Project is used when

1. Your QGIS Project uses data from a PostGIS Layer
2. You wish to convert your static data to PostGIS

If you wish to convert a static data source to PostGIS, you can upload the following

* Esri Shapefile
* GeoPackage
* PostGIS dump



