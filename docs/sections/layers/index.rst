**********************
Layers
**********************

.. contents:: Table of Contents

Overview
==================

Layers are layers from your QGIS project.

Layers are created from Stores.  Stores contain all Layers.

The Layer section allows you to select how these Layers are served.

The Layer table is show below.

.. image:: layer-1.png

The fields are below

* **Name**. The Layer name. Clicking the Layer name will open the Layer in a Leafletjs map preview.	
* **Layers**. The Layers used fromt the QGIS Project.  You can select which Layers to include.
* **Public**. Public access allows anyone to view the Layer	 (Yes/No)
* **Cached**. Session Caching enabled (Yes/No)
* **MapProxy**. This indicates if MapProxy is enabled (Yes/No)
* **Customized**. This inidicates if Layer is a Custom Leaflet map
* **Store**. The Store used for the Layer	
* **Access Group**. The Group(s) with access to the Layer.
* **Actions**.  Layer actions

Add Layer
==================

To create a new Layer, click the Add New button at top right.

.. image:: layer-add-new.png

Give your Layer a name:

.. image:: create-layer-1.png

Select the Store from the dropdown

.. image:: create-layer-2.png

Select the Layer(s) from the Store to include

.. image:: create-layer-3.png

The Select options are explained below

* **Public**. The Layer will be Public, with no authentication required.
* **Cache**. Session Cache.  This is distinct from MapProxy Cache.
* **MapProxy**. This will enable MapProxy for the Layer
* **Custom**. This option is to signify that this Layer does not use the default map template for Preview   
   

Click the Create button.

.. image:: create-layer-4.png

Your Layer has now been created.

No, click on the Layer name to preview the Layer you just created using Leafletjs

.. image:: create-layer-5.png

The Layer shows the two QGIS project layers we selected, Parks and Waterways

.. image:: create-layer-6.png

Edit Layer
==================

To edit a Layer, click the Edit button at right as shown below

.. image:: layer-edit-1.png

The Layer information is displayed. Make any changes you wish to make and click the Update button

.. image:: show-layer-edit.png


Edit Preview
==================

To edit the Leaflet Preview for a Layer, click the Edit Preview button

.. image:: show-layer-preview.png

Make any edits you wish to and then click Submit

.. image:: layer-show-preview-edit.png


Clear Cache
==================

To clear Session cache, click the Clear Cache button as shown below

.. image:: layer-clear-cache.png

Note: This does not clear MapProxy cache.  Clearing MapProxy cache is done via the MapProxy page.


Show Layer Info
==================

To display information on a layer, click the Show Info button at right

.. image:: layer-show-info.png

The information is displayed below

.. image:: layer-show-info-2.png

* **L.tileLayer.wms URL**	This is the WMS tile layer

* **BBox[min(x,y); max(x,y)]**	Bounding Box 

* **WMS URL**.  This opens the Layer in the following WMS formats
   * PNG
   * PDF
   * WebP
   * JPEG
   * PNG 1 Bit
   * PNG 8 Bit
   * PNG 16 Bit


* **WFS URL**	This opens the Layer in the following formats
   * GML2
   * GML2.1.2
   * GML3.1
   * GML3.1.1
   * GeoJson
   * VND Geo+Json
   * Geo+Json
   * Geo JSON
  

* **MapProxy URL**

layer-show-preview.png

.. image:: layer-show-preview.png

layer-show-preview-edit.png

.. image:: layer-show-preview-edit.png

show-layer-edit.png

.. image:: show-layer-edit.png

show-layer-preview.png

.. image:: show-layer-preview.png

The top section includes required fields: 





