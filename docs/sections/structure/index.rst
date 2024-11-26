.. This is a comment. Note how any initial comments are moved by
   transforms to after the document title, subtitle, and docinfo.

.. demo.rst from: http://docutils.sourceforge.net/docs/user/rst/demo.txt

.. |EXAMPLE| image:: static/yi_jing_01_chien.jpg
   :width: 1em

**********************
Structure
**********************

.. contents:: Table of Contents
Overview
==================

Quail is a lightweight Administration tool for serving QGIS Layers. 

Quail allows you to create and share both Public and Private layers as well as set Store and Layer level permissions.

Quail is Written in pure PHP to make customization and easy and accessible as possible.

The workflow is similar to GeoServer in that you created Stores and, from Stores, create Layers.

Stores cane be created from both databases as well as flat files (e.g. ESRI Shapefiles, GeoPackages, etc...).

Creation of Stores is simplified by providing only two Store types: QGIS and PostGIS

**QGIS Store**:

These consists of your QGIS Project and any flat files required.  

Flat files are Raster files, Vector files, image files, etc...

**PostGIS Stores**:

These consist of any local or remote PostGIS connections, as well as the ability to create PostGIS databases from a variety of formats.

Once a Store is created it can be either accessed directly or used to create Layers.

You can create any number of Layers from a single Store.

**QGIS Layer**:

Layers created from QGIS Stores

These layers also render any data and flat files used.

**PostGIS Layers**:

These Layers are created from PostGIS Stores. They can be served with QGIS Stores (e.g. Feature info) or independently.

   /usr/local/bin/
   /home/tomcat/tomcat-VERIONS/jasper_reports

Below is the structure and function of each.

bin
==================

The /usr/local/bin/ contains three executable files::

   gen_jri_report.sh
   svc_ctl.sh
   chown_ctl.sh

The gen_jri_report.sh file is responsible for executing reports.

It uses inputs from the configuration, schedule, and environment files.

If you change the default webapp name or port, you would update it here::


   #!/bin/bash -e

   source /etc/environment

   JRI_HOME="${CATALINA_HOME}/jasper_reports/"

   source "${JRI_HOME}/schedules/${1}_env.sh"

   DONT_MAIL="${2}"
   export EMAIL='root@localhost'
   #set who is sending the mail
   if [ "${EMAIL_TEMPLATE}" ]; then
     EMAIL_BODY=$(cat $JRI_HOME/email_tmpl/${EMAIL_TEMPLATE})
   fi
   REPORT_FOLDER=$(dirname ${REP_ID})

   #encode the / in report id
   REP_ID=$(echo "${REP_ID}" | sed 's/\//%2F/g')

   if [ "${OPT_PARAMS}" ]; then
     OPT_PARAMS="&${OPT_PARAMS}"
   fi

   URL="http://localhost:8080/JasperReportsIntegration/report?_repName=${REP_ID}&_repFormat=${REP_FORMAT}&_dataSource=${REP_DATASOURCE}&_outFilename=${REP_FILE}${OPT_PARAMS}"

   TSTAMP=$(date '+%Y%m%d_%H%M%S')
   REP_FILEPATH="${JRI_HOME}/reports/${REPORT_FOLDER}/${TSTAMP}_${REP_FILE}"

   wget -O"${REP_FILEPATH}" "${URL}"
   if [ $? -ne 0 ]; then
     rm -f "${REP_FILEPATH}"
   fi

   if [ -z "${DONT_MAIL}" ]; then
     echo "${EMAIL_BODY}" | mutt -F /var/www/.muttrc -e "set content_type=text/html" -s "${EMAIL_SUBJ}" -a "${REP_FILEPATH}" -- ${RECP_EMAIL}
   fi

   exit 0

The svc_ctl.sh file is used for starting and stopping Tomcat via the application.  You can also stop/start Tomcat via the command line.

The chown_ctl.sh updates permission for uploaded reports to user tomcat.

jasper_reports
====================

The /home/tomcat/tomcat-VERIONS/jasper_reports directories looks as below on installation (this includes Demo Data)::

   ├── conf
   │   ├── application.properties
   │   ├── application.properties.save
   │   └── log4j2.xml
   ├── email_tmpl
   │   └── email_template.html
   ├── jri_schedule.crontab
   ├── logs
   │   └── JasperReportsIntegration.log
   ├── reports
   │   ├── Cherry.jrxml
   │   ├── SimpleBees.jasper
   │   ├── SimpleBees.jrxml
   │   ├── cherry.jpg
   │   ├── demo
   │   │   ├── charts.jrxml
   │   │   ├── encrypt-pdf.jrxml
   │   │   ├── flower1.png
   │   │   ├── issue-with-query.jrxml
   │   │   ├── leaf_banner_red.png
   │   │   ├── long-running-report.jrxml
   │   │   ├── master_detail.jrxml
   │   │   ├── master_detail_subreport1.jrxml
   │   │   ├── opal_logo_50px_hoch.jpg
   │   │   ├── opal_logo_50px_hoch_2.jpg
   │   │   ├── order.jrxml
   │   │   ├── orders-test.jrxml
   │   │   ├── orders.jrxml
   │   │   ├── qr.jrxml
   │   │   ├── test_images.jrxml
   │   │   └── top_orders.jrxml
   │   ├── lov-parameter.jasper
   │   ├── lov-parameter.jrxml
   │   ├── query-parameter.jasper
   │   ├── query-parameter.jrxml
   │   └── test.jrxml
   └── schedules
    ├── 1_env.sh
    ├── 2_env.sh
    └── 3_env.sh


conf
================

The conf directory contains the application.properties file and is used directly from JasperReportsIntegration.

This stores general configuration information as well as Data Source information.

The file can be edited manually, but a backup should be taken prior to doing so.


email_tmpl
================

The email_tmpl directory is used to store email HTML templates.

On installation, a started template, email_template.html, is included.

You can add additional templates in this location.


jri_schedule.crontab
================

This file should not be modified

logs
================

Contains log files for JasperReportsIntegration.

reports
================

The reports directory is where reports are stored.

You can create sub directories in this location as well.

When selecting reports via Schedule, the reports and directories are listed in the dropdown.


schedules
================

The schedules directory contains configuration for reports Schedules.

An example file is below::

   schid=3
   REP_ID=SimpleBees
   REP_FORMAT=pdf
   REP_DATASOURCE=beedatabase
   REP_FILE=SimpleBees.pdf
   OPT_PARAMS=
   RECP_EMAIL=
   EMAIL_SUBJ=
   EMAIL_BODY=
   EMAIL_TEMPLATE=

The schedule files are created and updated automatically via Schedules.

While they can be edited by hand, you should create a backup before doing so.




