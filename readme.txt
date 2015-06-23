=== Plugin Name ===
Contributors: jprangenberg1
Tags: thebing, management, school, agency, software
Requires at least: 3.9
Tested up to: 3.9.1
Stable tag: 1.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The plugin links the forms for Thebing Management School & Agency Software

== Description ==

The plugin allows you to link the forms of the Thebing Management School & Agency Software into your Wordpress website.

== Installation ==

1. Install plugin "Thebing Snippet"
2. Active the plugin through the "Plugins" menu in Wordpress
3. Place "[thebingsnippet]" in your sites/posts

= If you want to show form/templates with template- and combinationkey add: =
[thebingsnippet type="default" server="https://schoolname.thebing.com" combinationkey="123456789" templatekey="123456789"]

= If you want to show the feedback form add: =
[thebingsnippet type="tsFeedback" server="https://schoolname.thebing.com" key="KEY-0123456789" language="en"]

= If you want to show the placement test add: =
[thebingsnippet type="tsPlacementTest" server="https://schoolname.thebing.com" key="KEY-0123456789" language="en"]

Optional attributes are currencyid and currencyiso!

= If you want to show the registration form add: =
[thebingsnippet type="tsRegistrationForm" server="https://schoolname.thebing.com" key="1=KEY-0123456789" language="en"]