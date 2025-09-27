.. This is a comment. Note how any initial comments are moved by
   transforms to after the document title, subtitle, and docinfo.

.. demo.rst from: http://docutils.sourceforge.net/docs/user/rst/demo.txt

.. |EXAMPLE| image:: static/yi_jing_01_chien.jpg
   :width: 1em

**********************
Dashboards
**********************

.. contents:: Table of Contents
Overview
==================

A Dashboard allows you to add a Map along with Charts, Tables, Lgends, and Text.

The data elements adjust with map pan.

.. image:: ../../_static/qcarta-dashboards.png

If your QGIS Project uses a PostGIS data source, you can create a PostGIS Store.

Create a Dashboard
================

Below, we'll create a Dashboard from the USA PostGIS demo Layer.

Click on Dashboards in the left menu:

.. image:: dashboard-create-1.png

Click the Add New button

.. image:: dashboard-create-2.png

Give your Dashboard and Name and Description.

You can also set a thumbnail here (can be done later as well)

.. image:: dashboard-create-3.png

Select the QGIS Project Layer you will use from the dropdown and set Permissions.

Click the Creat button

.. image:: dashboard-create-4.png

Your Dashboard has now been created:

.. image:: dashboard-create-5.png

To cofigure your Dashboard, click the Edit Preview button

.. image:: dashboard-create-6.png

A default Dashboard layout will appear

.. image:: dashboard-create-7.png

Click the Clear button on top to creal the layout:

.. image:: dashboard-create-8.png

Click the Map button at left to add the map canvas:

.. image:: dashboard-create-9.png

Click the Chart button at left

.. image:: dashboard-create-10.png

On the Chart element click the Configure button

.. image:: dashboard-create-11.png

Give chart a name and select layer, X, Y, and other fields.  

Click the Apply button.

.. image:: dashboard-create-12.png

Your Chart has now been configured.  You can edit the configuration at any time.


.. image:: dashboard-create-13.png

.. image:: dashboard-create-14.png

.. image:: dashboard-create-15.png

.. image:: dashboard-create-16.png

.. image:: dashboard-create-17.png

.. image:: dashboard-create-18.png

.. image:: dashboard-create-19.png

.. image:: dashboard-create-20.png

.. image:: dashboard-create-21.png

Give your Store a name.  Below we are using 'MyFirstStore'.

.. image:: qcarta-create-store-1.png




Select your QGIS project and any static sources you wish to upload.

.. image:: select-files.png

With files selected, chose if Store is Public and Access Groups (both can be changed later)

.. image:: qcarta-create-store-2.png

Your new QGIS Store has now been created:

.. image:: qcarta-create-store-created.png


Show Info
===================

To view your GetCapability urls as well as ESPG and Bounding Box, click on the "info" link at right:

.. image:: select-files-gdal.png

The Store GetCapability urls, ESPG, and Bounding Box are displayed in modal:

.. image:: qcarta-create-store--show-info.png


Clicking the link will take you to the document(s)

.. image:: select-files-7.png


Paths
===================

Be sure the path to your flat files mataches the path used on the server.

If you files are in the same directory as your QGIS Project, you can upload them along with the QGIS Project using multiselect.

If they are stored in a sub directory, zip the directory prior to upload.

Special Cases
===================

**ESRI Geodatabase** When using an ESRI Geodatabase for your project, upload a zipped copy of the *.gdb directory along with your QGIS Project.  On upload, the file will automatically be unzipped.

**ESRI Shapefile** You must upload the support files (.prg, .dbf, etc...) along with the .shp file.

**PostGIS** If you QGIS Project uses layers from a PostGIS data source, you must create a PostGIS Store for it (see next section, PostGIS Stores)




