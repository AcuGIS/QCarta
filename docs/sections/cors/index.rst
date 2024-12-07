.. This is a comment. Note how any initial comments are moved by
   transforms to after the document title, subtitle, and docinfo.

.. demo.rst from: http://docutils.sourceforge.net/docs/user/rst/demo.txt

.. |EXAMPLE| image:: static/yi_jing_01_chien.jpg
   :width: 1em

**********************
CORS
**********************

.. contents:: Table of Contents
Overview
==================

In order to serve remote layers, Cross-Origin Resource Sharing (CORS) needs to be enabled in Apache.

By default it is not enabled.

Enable CORS
================

To enable CORS, add below to your apache2/apache2.conf file::

	LoadModule headers_module /usr/lib/apache2/modules/mod_headers.so
	<IfModule mod_headers.c>
	Header add Access-Control-Allow-Origin "*"
	Header add Access-Control-Allow-Headers "*"
	Header add Access-Control-Allow-Methods "GET"
	</IfModule>


Important - the above is permissive, allowing GET requests from all origins and headers. In production, you should update to targetted origins





