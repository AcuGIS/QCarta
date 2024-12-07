.. This is a comment. Note how any initial comments are moved by
   transforms to after the document title, subtitle, and docinfo.

.. demo.rst from: http://docutils.sourceforge.net/docs/user/rst/demo.txt

.. |EXAMPLE| image:: static/yi_jing_01_chien.jpg
   :width: 1em

**********************
WMS, WFS, and WMTS
**********************

.. contents:: Table of Contents
Overview
==================

You can view Project capabilities via Stores:

  To view your GetCapability urls as well as ESPG and Bounding Box, click on the "info" link at right:

.. image:: select-files-gdal.png

The Store GetCapability urls, ESPG, and Bounding Box are displayed in modal:

.. image:: select-files-6.png

Clicking the link will take you to the document(s)

.. image:: select-files-7.png

  

WFS URLs
================

An examples of a WFS layer for adding featurs to layers::
  
  https://domain.com/layers/<layerid>/proxy_qgis.php?service=WFS&version=1.1.0&request=GetFeature&typeName=<layername>&maxFeatures=500&OUTPUTFORMAT=application/geo json
  
Where

    * <layerid> is the id for your layer
    * <layername> is the name of your layer

As an example the WFS url for the demo States layer would be::

  https://domain.com/layers/2/proxy_qgis.php?service=WFS&version=1.1.0&request=GetFeature&typeName=states&maxFeatures=500&OUTPUTFORMAT=application/geo json

  

* GeoTiff
* shapefile
* GeoPackage
* GeoJson
* etc....



.. image:: select-files.png

With files selected, chose if Store is Public and Access Groups (both can be changed later)

.. image:: select-files-2.png

Your new QGIS Store has now been created:

.. image:: select-files-4.png



Show Info
===================





