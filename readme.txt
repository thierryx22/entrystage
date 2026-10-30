=== EntryStage ===
Contributors: thierryx_22
Tags: gravity forms, entries, front-end, list, edit
Requires at least: 6.2
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show Gravity Forms entries on your site as a responsive list: your users view, edit, duplicate and archive their own entries.

== Description ==

EntryStage is an add-on for Gravity Forms. It displays the entries of a form on any page of your site, in a table that becomes a stack of cards on phones.

Everything is set up inside Gravity Forms, in one settings tab per form laid out as a workshop, step by step. No code is needed.

**For the people who fill in your forms**

* See the entries they submitted, sorted and split into pages, with picture thumbnails and file links.
* Open an entry to read it.
* Edit an entry in the original form: the form opens already filled in, and the changes are saved into the same entry.
* Duplicate an entry: the form opens filled in, and a new entry is created.
* Archive an entry (moved to the Gravity Forms trash, or deleted permanently if you prefer).
* Search the list.

**For you**

* Choose the columns, their order, width and display, and who sees the list: everyone, logged-in users or some roles; their own entries or all entries.
* A "My entries" page with the shortcode `[entrystage form="12" user="current"]`.
* An "EntryStage list" block for the block editor, with a live preview, as well as the shortcode.
* A personal space: several forms on one page, one tab per form with its number of entries, with `[entrystage forms="12,15" user="current"]`.
* A Print button: a clean printable page of the entries matching the filters.
* Three looks: the EntryStage style with your colours, a minimal style that takes the look of your theme, or no style at all.
* Optional protection of the file links of a form: only people who may see the entry can download its files.

**EntryStage Pro**

Follow every entry from start to finish. Requests, registrations, applications, orders: with EntryStage Pro, each entry gets a status of your own (for example Received, In progress, Done), shown as a coloured label. One click moves it to the next step, one tab per status counts what is waiting at each step, and every change is written in the notes of the entry. Your team sees at a glance what is left to do.

Also included: More details under each row, bulk actions, CSV export, drop-down and date filters, PDF links with Gravity PDF, custom icons, shortcode conditions and priority support.
EntryStage Pro will be available soon. It will replace this plugin and keep all your settings.

**Requirements**

* WordPress 6.2 or later, PHP 7.4 or later.
* Gravity Forms 2.5 or later (sold separately by Rocketgenius).

== Installation ==

1. In WordPress, go to Plugins > Add New, search for EntryStage, then click Install Now and Activate. Gravity Forms must be installed and active.

Then, for each form:

1. Open the form, then Settings > EntryStage.
2. Tick "Enable the entry list for this form".
3. Access: choose who can see the list, which entries they see, the list managers and the page where the form is displayed. The Edit and Duplicate buttons open the entry on that page. Left on "Automatic", the first page displaying the form (Gravity Forms shortcode or block) is used; if none is found, Edit and Duplicate are hidden and list managers see a message saying so.
4. Columns: add fields as columns, put them in order, and choose how each one is shown.
5. Actions and Display: the other settings, step by step. The Shortcode step builds the shortcode with its options and gives examples taken from the form. Each step shows "Ready", "To check" or "Optional".
6. Save, then open the page of the list ("View the page" link at the top of the tab) to see it with your theme.
7. Add the "EntryStage list" block to any page and choose the form in the block settings, or add the shortcode `[entrystage form="12"]` (replace 12 with the ID of your form). The shortcode is shown at the top of the settings tab.

== Frequently Asked Questions ==

= Where is the full documentation? =

In the plugin folder: DOCUMENTATION.txt (English) and LISEZMOI.txt (French). It covers columns, pictures and files, shortcode options, rights, editing, look and feel (CSS variables and classes) and the hooks for developers. Each settings step of a form also explains its options.

= What does EntryStage Pro add? =

Above all, follow-up of the entries step by step: statuses of your own with coloured labels, a next-step button, one tab per status with its counter, and the history of the changes in the notes of each entry. Also More details, bulk actions, CSV export, drop-down and date filters, PDF links, custom icons, shortcode conditions and priority support.
EntryStage Pro will be available soon. It will replace this plugin: when it is activated, this plugin will be deactivated and every setting kept.

= Can several lists be shown on the same page? =

Yes, lists of different forms. Sorting, pages and filters apply only to the list you are using.

= Can users edit entries of other users? =

No, unless they are list managers.

= Are payments recalculated when an entry is edited? =

No. Editing an entry updates the field values only. Payments, subscriptions and feeds of payment add-ons are not run again.

= Is my data sent anywhere? =

No. EntryStage does not contact any external server: no data, no statistics and no entry ever leaves your site. Its scripts and styles are all included in the plugin.

== Screenshots ==

1. A list on the site, with the search box and the actions of each row.
2. The same list on a phone: each row becomes a card.
3. Settings of a form, step "Columns": available fields and columns of the list.
4. Settings of a form, step "Shortcode": the shortcode composer and examples taken from the form.
5. Plugin settings: style of the lists and colours.

== Changelog ==

= 1.0.0 =
* First public release.
