.. This is a comment. Note how any initial comments are moved by
   transforms to after the document title, subtitle, and docinfo.

.. demo.rst from: http://docutils.sourceforge.net/docs/user/rst/demo.txt

.. |EXAMPLE| image:: static/yi_jing_01_chien.jpg
   :width: 1em

************
API
************

.. contents:: Table of Contents

Quail offers a basic REST API that supports GET requests.

The request returns json.

The /workspaces uri was added to allow compatibility for tools that query GeoServer.

GET
=======================
  
Get Query using Username and Password:

.. code-block:: console

  https://admin@admin.com:quail@domain.com/rest/workspaces

Get Query using Access Key:

.. code-block:: console

    https://access_key:06e3c5ff-e84c-415c-bb7f-57f710c8307c@domain.com/rest/workspaces

Get Query Without Authentication (Public):

.. code-block:: console

  https://domain.com/rest/workspaces

Examples
=========================

Get Layers using Access Key:

.. code-block:: console

  https://access_key:06e3c5ff-e84c-415c-bb7f-57f710c8307c@domain.com/rest/layers

Get Workspace by Type:

.. code-block:: console

  https://domain.com/rest/workspaces/pg
  https://domain.com/rest/workspaces/qgs

.. note::
    Seeding and tile generation can be CPU intensive for larger data sets.  Plan accordingly.


Sample Output
====================

Below is sample json output for /workspaces

.. code-block:: console

   {
     "success": true,
     "workspaces": {
       "workspace": [
         {
           "id": "7",
           "name": "Monarch-ESRI-Geodatabase",
           "type": "qgs",
           "owner_id": "1",
           "public": "t",
           "wms_url": "https://domain.com/stores/7/wms.php",
           "wfs_url": "https://domain.com/stores/7/wfs.php",
           "wmts_url": "https://domain.com/stores/7/wmts.php"
         },
         {
           "id": "6",
           "name": "Gebco-WMS",
           "type": "qgs",
           "owner_id": "1",
           "public": "f",
           "wms_url": "https://domain.com/stores/6/wms.php",
           "wfs_url": "https://domain.com/stores/6/wfs.php",
           "wmts_url": "https://domain.com/stores/6/wmts.php"
         },
         {
           "id": "5",
           "name": "NASA-GeoTIFF",
           "type": "qgs",
           "owner_id": "1",
           "public": "f",
           "wms_url": "https://domain.com/stores/5/wms.php",
           "wfs_url": "https://domain.com/stores/5/wfs.php",
           "wmts_url": "https://domain.com/stores/5/wmts.php"
         },
         {
           "id": "4",
           "name": "BGS-GeoPackage",
           "type": "qgs",
           "owner_id": "1",
           "public": "t",
           "wms_url": "https://domain.com/stores/4/wms.php",
           "wfs_url": "https://domain.com/stores/4/wfs.php",
           "wmts_url": "https://domain.com/stores/4/wmts.php"
         },
         {
           "id": "3",
           "name": "Chicago-ESRI",
           "type": "qgs",
           "owner_id": "1",
           "public": "f",
           "wms_url": "https://domain.com/stores/3/wms.php",
           "wfs_url": "https://domain.com/stores/3/wfs.php",
           "wmts_url": "https://domain.com/stores/3/wmts.php"
        },
         {
           "id": "2",
           "name": "USA-PostGIS",
           "type": "qgs",
           "owner_id": "1",
           "public": "f",
           "wms_url": "https://domain.com/stores/2/wms.php",
           "wfs_url": "https://domain.com/stores/2/wfs.php",
           "wmts_url": "https://domain.com/stores/2/wmts.php"
         }
       ]
     }
   }


REST API File
=======================

If you wish to update the API, the code is located at::

   /var/www/html/admin/action/rest.php









