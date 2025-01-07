# -*- coding: utf-8 -*-

"""
***************************************************************************
    ServerSimpleBrowseer.py
    ---------------------
    Date                 : August 2014
    Copyright            : (C) 2014-2015 by Alessandro Pasotti
    Email                : apasotti at gmail dot com
***************************************************************************
*                                                                         *
*   This program is free software; you can redistribute it and/or modify  *
*   it under the terms of the GNU General Public License as published by  *
*   the Free Software Foundation; either version 2 of the License, or     *
*   (at your option) any later version.                                   *
*                                                                         *
***************************************************************************
"""

__author__ = 'Alessandro Pasotti'
__date__ = 'March 2016'
__copyright__ = '(C) 2016, Alessandro Pasotti - ItOpen'

import sys
import os
import codecs
import re
import xml.etree.ElementTree as ET

# Import the PyQt and QGIS libraries

from qgis.PyQt.QtCore import *
from qgis.PyQt.QtGui import *
from qgis.core import *
from qgis.server import *
# for docker this should be DOCKER_IP:8000
BASE_URL = 'localhost'
BASE_PROTO = 'http'


def get_bbox(qgs_file):
	tree = ET.parse(qgs_file)
	root = tree.getroot()
	extent = root.find('mapcanvas/extent')
	dext = {}
	for child in extent:
		dext[child.tag] = child.text
	return '%s,%s,%s,%s' % (dext['xmin'], dext['ymin'], dext['xmax'],dext['ymax'])

class ServerSimpleFilter(QgsServerFilter):

    def get_url(self, request, params):
        #url = 'https://' if not self.serverInterface().getEnv("HTTPS") == 'on' else 'https://'
        url = BASE_PROTO + '://'
        url += params.get("SERVER_NAME")
        #url += ':' + params.get("SERVER_PORT")
        url += params.get("SCRIPT_NAME") + '?'
        #url += '?MAP=' + params.get('MAP', '')
        if params.get('ACCESS_TOKEN'):
            url += '&amp;access_token=' + params.get('ACCESS_TOKEN')
        return url


    def requestReady(self):
        request = self.serverInterface().requestHandler()
        params = request.parameterMap( )
        if params.get('SERVICE', '').lower() == 'wms' \
                and params.get('REQUEST', '').lower() == 'getmap' \
                and params.get('FORMAT', '').lower() == 'application/openlayers':
            request.setParameter('SERVICE', 'OPENLAYERS')


    def responseComplete(self):

        # TODO: error checking
        request = self.serverInterface().requestHandler()
        params = request.parameterMap( )

        mymap = params.get('MAP', '')
        store_id = mymap.split('/')[5]

        params['SERVER_NAME'] = BASE_URL
        #params['SERVER_PORT'] = BASE_PORT
        params['SCRIPT_NAME'] = '/stores/' + store_id + '/wms.php'
        qgis_url = BASE_PROTO + '://' + params.get('SERVER_NAME', '') + params.get('SCRIPT_NAME', '')

        if params.get('SERVICE', '').lower() == 'wms' \
                and params.get('REQUEST', '').lower() == 'xsl':
            request.clear()
            request.setRequestHeader('Content-type', 'text/xml; charset=utf-8')
            f = open(os.path.dirname(__file__) + '/assets/' + 'getprojectsettings.xsl')
            body = ''.join(f.readlines())

            bbox = get_bbox(mymap)
            body = body.replace('BBOX=-180,-90,180,90', 'BBOX=' + bbox)
            f.close()
            request.appendBody(body.encode('utf8'))


        if params.get('SERVICE', '').lower() == 'wms' \
                and params.get('REQUEST', '').lower() == 'getprojectsettings':
            # inject XSL code
            body = request.body()
            request.clearBody()
            url = self.get_url(request, params)
            body = body.replace(b'<?xml version="1.0" encoding="utf-8"?>', b'<?xml version="1.0" encoding="utf-8"?>\n<?xml-stylesheet type="text/xsl" href="%s&amp;SERVICE=WMS&amp;REQUEST=XSL"?>' % url.encode('utf8'))
            body = body.replace(b'http://localhost/cgi-bin/qgis_mapserv.fcgi', qgis_url.encode('utf8'))
            request.appendBody(body)

class ServerSimpleService(QgsService):

    def __init__(self, debug: bool = False) -> None:
        super().__init__()
        _ = debug

    # QgsService inherited

    def name(self) -> str:
        """ Service name
        """
        return 'OPENLAYERS'

    def version(self) -> str:
        """ Service version
        """
        return "1.3.0"

    # noinspection PyMethodMayBeStatic
    def allowMethod(self, method: QgsServerRequest.Method) -> bool:
        """ Check supported HTTP methods
        """
        return method in (QgsServerRequest.GetMethod)
	
    def get_url(self, request, params):
        mymap = params.get('MAP', '')
        store_id = mymap.split('/')[5]

        url = BASE_PROTO + '://' + BASE_URL + '/stores/' + store_id + '/wms.php?'
        return url
		
    def executeRequest(self, request: QgsServerRequest, response: QgsServerResponse, project: QgsProject) -> None:
        """ Execute a 'OpenLayer' request
        """

        params = request.parameters()
        #minx, miny, maxx, maxy = params.get('BBOX', '0,0,0,0').split(',')
        url = self.get_url(request, params)

        #QgsMessageLog.logMessage("OpenLayersFilter.responseComplete URL %s" % url, 'ServerSimple', QgsMessageLog.INFO)
        try:
            f = codecs.open(os.path.dirname(__file__) + '/assets/' + 'map_template.html', encoding='utf-8')
            body = ''.join(f.readlines())
            f.close()
            body = body % {
	            'extent' : params.get('BBOX', ''),
	            'url' : url.replace('&amp;', '&'),
	            'layers': params.get('LAYERS', ''),
	            'center': center,
	            'zoom': params.get('ZOOM', '12'),
	            'height': params.get('HEIGHT', '80%'),
	            'width': params.get('WIDTH', '100%'),
	            'projection' : params.get('CRS', 'EPSG:4326'),
            }
            #QgsMessageLog.logMessage("OpenLayersFilter.responseComplete BODY %s" % body, 'plugin', QgsMessageLog.INFO)

            response.setStatusCode(200)
            response.setHeader('Content-type', 'text/html; charset=utf-8')
            response.write(body.encode('utf8'))
        except Exception:
            QgsMessageLog.logMessage("Unhandled exception:\n{}".format(traceback.format_exc()), 'ServerSimple', QgsMessaging.CRITICAL)
            err = AtlasPrintError(500, "Internal 'atlasprint' service error")
            err.formatResponse(response)

class ServerSimpleBrowser:
    """Plugin for QGIS server"""

    def __init__(self, serverIface):
        # Save reference to the QGIS server interface
        self.serverIface = serverIface
        try:
            self.serverIface.registerFilter(ServerSimpleFilter(serverIface), 1000)
            reg = self.serverIface.serviceRegistry()
            reg.registerService(ServerSimpleService())
        except Exception as e:
            QgsLogger.debug("ServerSimpleBrowser- Error loading filter %s", e)



class SimpleBrowser:
    def __init__(self, iface):
        # Save reference to the QGIS interface
        self.iface = iface
        self.canvas = iface.mapCanvas()


    def initGui(self):
        # Create action that will start plugin
        self.action = QAction(QIcon(":/plugins/"), "About Server SimpleBrowser", self.iface.mainWindow())
        # Add toolbar button and menu item
        self.iface.addPluginToMenu("Server SimpleBrowser", self.action)
        # connect the action to the run method
        QObject.connect(self.action, SIGNAL("activated()"), self.about)

    def unload(self):
        # Remove the plugin menu item and icon
        self.iface.removePluginMenu("Server SimpleBrowser", self.action)

    # run
    def about(self):
        QMessageBox.information(self.iface.mainWindow(), QCoreApplication.translate('SimpleBrowser', "Server SimpleBrowser"), QCoreApplication.translate('SimpleBrowser', "Server SimpleBrowser is a simple browser plugin for QGIS Server, it does just nothing in QGIS Desktop. See: <a href=\"http://www.itopen.it/qgis-server-simple-browser-plugin/\">plugin's homepage</a>"))



if __name__ == "__main__":
    pass
