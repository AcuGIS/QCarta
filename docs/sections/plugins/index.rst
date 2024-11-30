.. This is a comment. Note how any initial comments are moved by
   transforms to after the document title, subtitle, and docinfo.

.. demo.rst from: http://docutils.sourceforge.net/docs/user/rst/demo.txt

.. |EXAMPLE| image:: static/yi_jing_01_chien.jpg
   :width: 1em

**********************
QGIS Plugins
**********************

.. contents:: Table of Contents
Overview
==================

You can install QGIS Server plugins using plugin manager or manually.

The QGIS Plugin directory is located at::

   /var/www/data/qgis/plugins

Default Plugins
================

The following plugins are installed during installation

* serversimplebrowser
* wfsOutputExtension

Install Plugins
================

To install a plugin, follow below.

Connect via SSH and change to the plugins directory::

    cd /var/www/data/qgis/plugins

Start the virtualenv::


    virtualenv --python=/usr/bin/python3 --system-site-packages .venv
		source .venv/bin/activate

Use qgis-plugin-manager to install the plugin (replace PluginName below with your plugin::
		
		qgis-plugin-manager install PluginName






