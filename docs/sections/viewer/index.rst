.. This is a comment. Note how any initial comments are moved by
   transforms to after the document title, subtitle, and docinfo.

.. demo.rst from: http://docutils.sourceforge.net/docs/user/rst/demo.txt

.. |EXAMPLE| image:: static/yi_jing_01_chien.jpg
   :width: 1em

**********************
Map Viewer
**********************

.. contents:: Table of Contents
Overview
==================

You can view Project capabilities via Stores:

To view your GetCapability urls as well as ESPG and Bounding Box, click on the "info" link at right:

.. image:: show-gdal.png

The Store GetCapability urls, ESPG, and Bounding Box are displayed in modal:

.. image:: getCapabilities.png

Clicking the link will take you to the document(s)

.. image:: show-cap.png


Layer WMS URLs
====================

Layer Urls can be viewed via Layers

Click on the info link at right:


.. image:: show-wms-info.png

The information is displayed:

.. image:: show-wms.png

To get the url for a WMS or WFS url, simply select it from the appropriate dropdown.

For example, the WMS PNG url:

.. image:: show-png.png

You can now copy the full url from the browser as below:

.. image:: show-wms-url.png

  

WFS URLs
================

An examples of a WFS layer for adding featurs to layers::
  
  https://domain.com/layers/<layerid>/proxy_qgis.php?service=WFS&version=1.1.0&request=GetFeature&typeName=<layername>&maxFeatures=500&OUTPUTFORMAT=application/geo json
  
Where

    * <layerid> is the id for your layer
    * <layername> is the name of your layer

As an example the WFS url for the demo States layer would be::

  https://domain.com/layers/2/proxy_qgis.php?service=WFS&version=1.1.0&request=GetFeature&typeName=states&maxFeatures=500&OUTPUTFORMAT=application/geo json

  





Show Info
===================





