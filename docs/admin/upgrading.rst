Upgrading Elgg
##############

Switch a live site to a new version of Elgg.

If you've written custom plugins, you should also read the developer guides for
:doc:`information on upgrading plugin code </guides/upgrading>` for the latest version of Elgg.

Advice
======

* **Back up your database** and code
* Mind any version-specific comments below
* Upgrade only one minor version at a time (1.6 => 1.7, then 1.7 => 1.8)
* Try out the new version on a test site before doing an upgrade
* Report any problems in plugins to the plugin authors
* If you are a plugin author you can `report any backwards-compatibility issues to GitHub <issues_>`_

.. _issues: https://github.com/Elgg/Elgg/issues

Basic instructions
==================

#. Log in as an admin to your site
#. Disable caching in Advanced Settings
#. **Back up your database, data directory, and code**
#. Download the new version of Elgg from http://elgg.org
#. Update the files
    * If doing a patch upgrade (1.9.x), overwrite your existing files with the new version of Elgg
    * If doing a minor upgrade (1.x), replace the existing core files completely
#. Merge any new changes to the rewrite rules
    * For Apache from ``install/config/htaccess.dist`` into ``.htaccess``
    * For Nginx from ``install/config/nginx.dist`` into your server configuration (usually inside ``/etc/nginx/sites-enabled``)
#. Merge any new changes from ``settings.example.php`` into ``settings.php``
#. Visit http://your-elgg-site.com/upgrade.php

.. note::

   Any modifications should have been written within plugins, so that they are not lost on overwriting.
   If this is not the case, take care to maintain your modifications. 

From 2.3 to 3.0
===============

Update ``settings.php``
-----------------------

On your working 2.3 installation:

1. Open your ``settings.php`` file.
2. In the browser, open the site's Advanced Settings page in the admin area
3. Copy the **data directory** path into your settings file as ``$CONFIG->dataroot``.
4. Copy the **site URL** into your settings file as ``$CONFIG->wwwroot``.

.. warning:: Elgg 3.0 **will not operate** at all without ``$CONFIG->dataroot`` set in ``settings.php``.

Update ``.htaccess``
--------------------

Find the line:

.. code::

	RewriteRule ^(.*)$ index.php?__elgg_uri=$1 [QSA,L]

And replace it with:

.. code::

	RewriteRule ^(.*)$ index.php [QSA,L]

Removed / changed language keys
-------------------------------

 * The language keys related to comment notifications have changed. Check the ``generic_comment:notification:owner:`` language keys

New MySQL schema features are not applied
-----------------------------------------

New 3.0 installations require MySQL 5.5.3 and use the utf8mb4 character set and LONGTEXT content columns (notably allowing storing longer content and extended characters like emoji).

Miscellaneous changes
---------------------

The settings "Allow visitors to register" and "Restrict pages to logged-in users" now appear on the Basic Settings admin page.

Twitter API plugin
------------------

The ``twitter_api`` plugin no longer comes bundled with Elgg.

Testing
-------

Simpletests can no longer be executed from the admin interface of the developers plugin.
Use Elgg cli command: ``elgg-cli simpletest``


From 2.2 to 2.3
===============

PHP Version
-----------

PHP 5.5 has reached end of life in July 2016. To ensure that Elgg sites are secure, we now require PHP 5.6 for new installations.

Existing installations can continue using PHP 5.5 until Elgg 3.0.

In order to upgrade Elgg to 2.3 using composer while using PHP 5.5, you may need to use ``--ignore-platform-reqs`` flag.

Tests
-----

 * PHPUnit bootstrap is deprecated by composer autoloader: Tests should no longer bootstrap themselves using ``/engine/tests/phpunit/bootstrap.php``. Instead, tests should extend ``\Elgg\TestCase``.
 * Some core files now sniff if	``PHPUNIT_ELGG_TESTING_APPLICATION`` constant is set to determine whether Elgg is being bootstrapped for PHPUnit tests. ``phpunit.xml`` configuration needs to updated to include this constant definition.
 * PHPUnit bootstrap no longer sets global ``$CONFIG``. Tests should use ``_elgg_config()`` instead.
 * Core and tests no longer use private global values in ``$_ELGG->view_path`` and ``$_ELGG->allowed_ajax_views``

Schema
------

 * The database GUID columns need to be aligned. In the admin section an upgrade is available to handle this. Please make sure you have a backup available

From 2.3 to 3.0
===============

Data removal
------------

Be aware the 3.0 upgrade process will remove any remaining "legacy" password hashes. This will affect users who have never logged in under an Elgg 1.10 or later system. These users will be politely asked to reset their password.

Deprecations in 2.x
===================

2.2
---

User avatars are now served via ``serve-file`` handler. Plugins should start using ``elgg_get_inline_url()`` and note that:

 * ``/avatar/view`` page handler and resource view have been deprecated
 * ``/mod/profile/icondirect.php`` file has been deprecated
 * ``profile_set_icon_url()`` is no longer registered as a callback for ``"entity:icon:url","user"`` plugin hook

Group avatars are now served via ``serve-file`` handler. Plugins should start using ``elgg_get_inline_url()`` and note that:

 * ``groupicon`` page handler (``groups_icon_handler()``) has been deprecated
 * ``/mod/groups/icon.php`` file has been deprecated


File entity thumbs and downloads are now served via ``serve-file`` handler. Plugins should start using ``elgg_get_inline_url()`` and ``elgg_get_download_url()`` and note that:

 * ``file/download`` page handler and resource view have been deprecated
 * ``mod/file/thumbnail.php`` file has been deprecated
 * Several views have been updated to use new download URLs, including:

   - ``mod/file/views/default/file/specialcontent/audio/default.php``
   - ``mod/file/views/default/file/specialcontent/image/default.php``
   - ``mod/file/views/default/resources/file/view.php``
   - ``mod/file/views/rss/file/enclosure.php``


From 1.x to 2.0
===============

Removed plugins
---------------

The following plugins are no longer bundled with Elgg core:

 * categories (https://github.com/elgg/categories)
 * zaudio (https://github.com/elgg/zaudio)

IE-specific workarounds have been dropped
-----------------------------------------

Several views (``css/ie``, ``css/ie7``, ``css/ie8``, etc.) as well as conditional
comments have been discarded now that IE10+ browsers are more standards-compliant.
If you need browser support farther back than that, you will need to find or build
a plugin that introduces its own compatibility layer or polyfills.

Update your webserver config
----------------------------

URL paths like ``cache/*`` and ``rewrite.php`` now use the main front controller
script. You **must** remove these rewrite rules from your webserver config (e.g. ``.htaccess``).

Also remove the rules for paths like ``export/*``; these endpoints have been removed.

Settings location
-----------------

After upgrading, move your ``settings.php`` file from ``engine/`` to ``elgg-config/``.

From 1.10 to 1.11
=================

Breaking changes
----------------
In versions 1.9 and 1.10, names and values for metadata and annotations were not correctly trimmed
for whitespace. Elgg 1.11 correctly trims these strings and updates the database to correct
existing strings. If your plugin uses metadata or annotations with leading or trailing whitespace,
you will need to update the plugin to trim the names and values. This is especially important if
you are using custom SQL clauses or have hard-coded metastring IDs, since the update might change
metastring IDs.

From 1.8 to 1.9
===============
Elgg 1.9 is a much lighter upgrade than 1.8 was.

Breaking changes
----------------
Plugins and themes written for 1.8 are expected to be compatible with 1.9
except as it pertains to comments, discussion replies, and notifications.
Please `report any backwards compatibility issues <issues_>`_ besides those just listed.

Upgrade steps
-------------
There are several data migrations involved, so it is especially important that you
**back up your database and data directory** before performing the upgrade.

Download the new version and copy these files from the existing 1.8 site:

 * ``.htaccess``
 * ``engine/settings.php``
 * any 3rd-party plugin folders in the ``mod`` directory

Then replace the old installation directory with the new one. This way you are
guaranteed to get rid of obsolete files which might cause problems if left behind.

Follow the basic instructions listed above.

After you've visited ``upgrade.php``, go to the admin area of your site.
You should see a notification that you have pending upgrades.
Click the link in the notification bar to view and run the upgrades.

The new notifications system delivers messages via a minutely cron handler.
If you haven't done so yet, you will need to :doc:`install and configure crontab </admin/cron>`
on your server. If cron jobs are already configured, note that the scope of
available cron periods may have changed and you may need to update your current crontab
to reflect these changes.

Time commitment
---------------
Running all of the listed upgrades `took about 1 hour and 15 minutes`__
on the Elgg community site which at the time had to migrate:

 * ~75,000 discussion replies
 * ~75,000 comments
 * ~75,000 data directories
 
__ https://community.elgg.org/discussion/view/1819798/community-site-upgraded

You should take this only as a ballpark estimate for your own upgrade.
How long it takes will depend on how large your site is and how powerful your servers are.

From 1.7 to 1.8
===============
Elgg 1.8 is the biggest leap forward in the development of Elgg since version 1.0.
As such, there is more work to update core and plugins than with previous upgrades.

Updating core
-------------
Delete the following core directories (same level as _graphics and engine):

* _css
* account
* admin
* dashboard
* entities
* friends
* search
* settings
* simplecache
* views

.. warning::

   If you do not delete these directories before an upgrade, you will have problems!
