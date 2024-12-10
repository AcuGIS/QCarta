.. This is a comment. Note how any initial comments are moved by
   transforms to after the document title, subtitle, and docinfo.

.. demo.rst from: http://docutils.sourceforge.net/docs/user/rst/demo.txt

.. |EXAMPLE| image:: static/yi_jing_01_chien.jpg
   :width: 1em

**********************
Users
**********************
.. contents:: Table of Contents
Overview
==================

QeoServer grants permissions on Stores and Layers to Groups.

You can begin by creatting Users and then Groups, or vice versa.

Pre-installed Users
==================

On installation, two users are created.

* Quail Admin (an Administrator)
   * Email: admin@admin.com
   * Password: quail
* Jane Doe (an End User)
   * Email: jane@doe.com
   * Password: quail

User Types
==================

QeoServer supports the following User types:

1. Admins.  Can create Stores, Layers, Groups, and Users.
2. Users.   Can consume Stores and Layers via Dashboard or via URL

Add New User
================

To create a new user, click on Users on the left menu.

Click the Add User button

.. image:: 1-add-new.png



Enter the required information and be sure to set the Access Level (Admin or User)

.. image:: 1-add-user-detail.png

The user has now been created:


.. image:: 3-user-added.png


Edit Group Membership
===================

You can always edit Group Membership later as well via both Users and User Groups menu.

To edit Group membership, click the Edit button as below

.. image:: 4-edit-user.png

Make any Group or access Level changes you wish to make, and then click Update

.. image:: 5-edit-user.png

Reset Secret Key
===============

To reset a user's Secret Key, click the Edit button for the user

Click the Reset Secret Key icon

.. image:: 6-edit-key.png

The user's key has now been updated.  Be sure to click the UPdate button.

.. image:: 7-edit-key-done.png


Delete User
===================
To delete a user, click the Delete icon to the right of the Users name.




