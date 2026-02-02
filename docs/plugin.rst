.. This is a comment. Note how any initial comments are moved by
   transforms to after the document title, subtitle, and docinfo.

.. demo.rst from: http://docutils.sourceforge.net/docs/user/rst/demo.txt

.. |EXAMPLE| image:: static/yi_jing_01_chien.jpg
   :width: 1em

************
QGIS Plugin
************

.. contents:: Table of Contents


Installation
==================

The plugin is available via the QGIS Plugin Repository

This is the recommended way to install it.

.. image:: _static/qcarta-plugin-0.png

.. note::
    The plugin is NOT a requirement for publishing to QCarta, you can publish directly via QCarta admin interface as well.

Usage
==================
  
Once installed, go to Web > QCarta > QCarta Console:

.. image:: _static/qcarta-plugin-1.png


==================
1. Configure
==================

Click Configure to add your QCarta server(s)

.. image:: _static/QCarta-Configure-Screenshot.png

Enter your QCarta server details and click Test Connection

When Connection test passes, click Save

.. image:: _static/qcarta-plugin-3-b.png

If you are using more than one QCarta installation, you must set the server as below:

.. image:: _static/Select-Server.png


==================
2. Publish Map
==================

Click Publish Map.

.. image:: _static/qcarta-qgis-plugin.png

Select the options you wish to use and click Create

  ..Note::
   Options can be updated via UI or plugin later

.. image:: _static/Publish-Layers.png


You'll see a message that your Store has now been Published

.. image:: _static/qcarta-plugin-11.png

View the map

.. image:: _static/qcarta-plugin-12.png

.. note::
    Just as you can do all above without using the Plugin, you can also edit Maps you have published directly in QCarta as well.


==================
Advanced: Create Store
==================

While the neccessity to create a Store prior to publishing maps was removed in QCarta 6, you can still do so if you wish to.

This can be for backwards-compatibility or if you simply want to select particular Store options

.. image:: _static/Advanced-Create-Store.png

Enter the Store title and click Create

.. image:: _static/Create-Store.png

==================
Advanced: Update Store
==================

You can update an existing Store (QGIS Project Files) using the Update Store tab.

.. image:: _static/Advanced-Update-Store.png


.. image:: _static/Update-Store.png




Advanced-Create-Store.png

Give your Store a name and click Create

qcarta-plugin-5.png

This will upload the QGIS project directory and create the Store

qcarta-plugin-6.png

Click the Publish tab

qcarta-plugin-7.png

Select the Store you wish to Publish from the dropdown

qcarta-plugin-8.png

Select your map options and will in the required fields.

Click Create

qcarta-plugin-10.png


Upcoming Version
==================

.. image:: _static/Advanced-Create-Store.png

.. image:: _static/Advanced-Update-Store.png

.. image:: _static/Create-Store.png

.. image:: _static/Publish-Layers.png

.. image:: _static/qcarta-qgis-plugin.png

.. image:: _static/Select-Server.png

.. image:: _static/Test-Server.png

.. image:: _static/Update-Store.png



.. image:: _static/qcarta-plugin-4.png

qcarta-plugin-2.png
