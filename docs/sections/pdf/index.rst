.. This is a comment. Note how any initial comments are moved by
   transforms to after the document title, subtitle, and docinfo.

.. demo.rst from: http://docutils.sourceforge.net/docs/user/rst/demo.txt

.. |EXAMPLE| image:: static/yi_jing_01_chien.jpg
   :width: 1em

**********************
PDF Export
**********************

.. contents:: Table of Contents
Overview
==================

You can obtain PDF format of Layers using the WMS url

Preview
================

To preview a PDF, simply use the WMS url with OUTPUTFORMAT=PDF as below

Table of Contents


get pdf

.. image:: get-pdf.png

pdf layers icon

.. image:: pdf-layers-icon.png

pdf layers

.. image:: pdf-layers.png

Tree icon

.. image:: tree-icon.png

Highlight Tree 1

.. image:: tree-hightlight-1.png

Highlight Tree 2

.. image:: tree-hightlight-2.png



      https://quail-docker.webgis1.com/mproxy/service?service=WMS&version=1.1.0&request=GetMap&layers=bgsgrid&bbox=-8.476567%2C49.796537%2C2.873641%2C60.911296&width=638&styles&height=768&srs=EPSG%3A4326&FORMAT=image%2Fpng

Private Layer (Using Access Key::

  	   https://domain.com/mproxy/service?access_key=78091b92-5bcd-4306-92ad-8dce26d50a68&service=WMS&version=1.1.0&request=GetMap&layers=bgsgrid&bbox=-8.476567%2C49.796537%2C2.873641%2C60.911296&width=638&styles&height=768&srs=EPSG%3A4326&FORMAT=image%2Fpng


PNG using no Cache or Session Cache::

  https://yourdomain.com/layers/3/proxy_qgis.php?service=WMS&version=1.1.0&request=GetMap&layers=waterways%2Cparks&bbox=-87.938902%2C41.619499%2C-86.206663%2C43.21631&width=833&height=768&srs=EPSG%3A4326&FORMAT=image%2Fpng


WFS
================

GeoJson::

  https://yourdomain.com/layers/2/proxy_qgis.php?service=WFS&version=1.1.0&request=GetFeature&typeName=states&maxFeatures=500&OUTPUTFORMAT=application/geojson









