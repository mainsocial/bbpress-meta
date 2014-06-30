bbpress-meta
============

bbPress-meta creates a bridge between slow bbPress meta queries and a fully optimize MySQL table.

**Contributors:** [Mainsocial](http://mainsocial.com/), [jonathanbardo](http://profiles.wordpress.org/jonathanbardo)  
**Requires at least:** 3.7  
**Tested up to:** 3.8  
**Stable tag:** trunk (master)  
**License:** [GPLv2 or later](http://www.gnu.org/licenses/gpl-2.0.html)  

## Description ##

This plugin aims to reduce certain slow queries when dealing with large databases of forum entries by creating an alternative read table. All bbpress post meta will still be updated and created as usual. This plugin will mostly optimize front-end queries.

N.B This plugin requires an active instance of bbpress plugin and wp-cli command line utility. This will not have any effect if those requirements are not met.

## Installation ##

1. Activate the plugin
1. Using wp-cli run the install command
```shell
wp bbpress-meta install
```

The install command will put the website in maintenance mode for a few seconds. How long depends on the speed of your database. For us, with hundreds of thousands of rows in the posts table, and a basic MySQL install on a commodity server, only a few seconds.
