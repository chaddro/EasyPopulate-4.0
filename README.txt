EasyPopulate 4.0 Beta SBA_Stock

There is a Zen-cart.com Support Thread located here:
http://www.zen-cart.com/forum/showthread.php?t=190417

I recommend that you first enter a couple Categories and Products through the normal Zencart Admin. This will help you understand the exported file formats and how to import.

Things of Importance:

1) EP4 works only with CSV data files, and for best cross platform compatibility please use OpenOffice to Export your CSV file.
You should select the Comma as your field delimiter, and the Double Quote as your text delimiter. You may not get proper results using Excel.
At least one specific known issue is that dates must be formatted to be like: YYYY-MM-DD HH:MM:SS (or without the time at the end)

2) There are now two ways to address product that is imported.  The default as installed remains the products_model.  
   When assigned as the primary key, the products_model must be used to distinguish your products for import. Any record with a 
   blank v_products_model entry will be skipped. 
   
   Also note that if you enter the same products_model twice, the latest (last) record
   entry will over-write any previous entry. The exception here is if you enter a different category; this will result in a linked
   product. This means that if two rows of data have the same v_products_model data with only a difference of the category name,
   then the product will appear in both categories and a change to the data in one entry will appear in the other category. See the
   previous sentence about effect of two products having the same products_model identifier.
   "Duplicate" products with the same products_model number is not supported when using the v_products_model as the primary key, they will each end up with the same information.
   If you have these entries in your database, you may get unpredictable results when importing data.
   
   The second method of import is to use the products_id.  
   
   NOTE: Incorrect use of the products_id can severely corrupt your database and store.  
   
   It is always possible to export the products_id as a user defined field even if products_model is the chosen primary
   field.  Use of the products_model field allows for matching of data from perhaps an outside vendor to the data in the database.
   Use of the products_id will allow export of the store, modification of that file and then again import of the applicable row(s) of
   data to update the modified field(s) (This could include assigning a products_model to the products_id).
   When working with the products_id as a primary key, there are two settings.  One with
   products_id only, where rows with a blank products_id will be skipped like with a blank products_model entry described above, the 
   other, blank_new, will assign a new products_id (next "available") for records that have a blank for the products_id field in the
   applicable row.

3) Categories are handled differently from other versions of EP. You can now import multilingual categories and related multilingual
data (like Products Names, descriptions, etc.).
To achieve this, each v_categories_names_1, v_categories_names_2, etc. with the number corresponding to a language installed in your system. Individual category names for a product are separated by the Carat "^" symbol ($category_delimiter). 

For Example: Bar Supplies^Glass Washers^Brushes

"Bar Supplies" will be your Top level Category, with "Glass Washers" as a sub-category, and "Brushes" as a sub-category of "Glass Washers" (sub-sub-category of "Bar Supplies").

Be careful when creating your category names, the routine is case sensitive. "Bar Supplies" is not equal to "BAR Supplies" ... this
will result in TWO category entries and will effectively create a linked product entry (same product will appear in each category).

As of 4.0.17, your lowest defined language (by language id) is needed to be used when creating categories. In the future I'll change
this to the defined default language, but that will take some additional work.

4) Work flow is somewhat different than other versions of EP. With EP4 you first upload your file, then click on Import. Optionally 
after the file is uploaded, you can also Split an import file if it is too large. The number of records on which to split the file 
is controlled in the configuration settings. Default is 2000. If you have a powerful server, you can increase this significantly.
Testing on a VPS with an import file of 900,000 records, I split the file into 50,000 record segments. Import of each 50,000 records
took about 250 seconds.

Also there is NO streaming upload or download support. Sorry if you liked this feature. A lot of effort has been put into improving
the code's performance and streaming the data was a real memory hog. To download your exported file, you will need to 
"right-click, save-as". You may be able to set your browser to automatically download csv/CSV files when clicking on the download link. I'll come up with a better solution in due time. 

5) File names are important and act as a switch inside the script for Importing. This means that in order process data associated with
featured product, the filename must begin with the non-case sensitive name of Featured-EP.  Anything that follows that name can be
modified to suit but must follow the file naming "rules" of your host and software.  The filenames requiring unique file
naming are: PriceBreaks-EP, CategoryMeta-EP, Featured-EP, SBA-Stock-EP, and Attrib-Basic-EP/Attrib-Detailed-EP ... these
import files must have names that start with the applicable string.

For example, attempting to import a file to modify the featured part(s) of a product where the filename does not begin with
Featured-EP (case insensitive) data will not update the featured specific data of the product.  Files that are not named using the
identified unique filename prefix will be processed as a full product with each field processed as might be expected for a full
product import.

6) Basic Attribute Import. Please read the notes below carefully about importing attributes and how to use this feature which is still in development. Currently, you can
at Import create your products_options_name (your attribute name, ie. color), products_options_type (checkbox, dropdown, etc), and 
products_options_values_name (red, green, blue).

LASTLY but definitely not LEAST and applicable to all of EP4: Be sure to backup your data before importing your files. I have made every attempt to make this a solid bug free product, but occasionally new features require more testing. A lot of error 
trapping has been added, but I'm sure I've missed things.


IMPORTING ATTRIBUTES
Including sample attribute input file.

I am currently able to correctly import basic attributes, and assign 
the options to an associated product's model. It is possible to create
multiple sets of Option Names / Option Values and assign to a single 
product, say "Size" and "Color".

The basic attributes CSV file currently has 4 columns:
1) v_products_model
	a) The products model number must already exist, and should be
	   unique within the store as linked products have not been tested.

2) v_products_option_type
	a) this is the type of attribute you want to create and should be
	   a number between 0 - 5 (for a default store or the number associated with your added software) and are defined as follows:
		0 - Drop Down Box
		1 - Text Box
		2 - Radio Button
		3 - Check Box
		4 - File Upload
		5 - Read Only
		One way to identify the number associated with an additional option type is to navigate to Catalog->Option Names. Once there,
		note the option type assigned to the product on screen and the list of option types in the dropdown list. Now view the source of
		the page and search for one of the option types in the list that are not otherwise used on the screen.  The found html option
		values do/should match the list above and then show any new(er) option type values that can be used by the software.
		
	b) for a given option_name (say "Color"), do not change the products_option_type
	   on subsequent entries, doing so will not give the results you want.
	c) If you need a "Color" with both a drop down box and check box, you will need
	   to define two unique Options Names.

3) v_products_options_name_1
	a) The option name you want to create or use in the language associated with the number at the end.
	b) It is important to note that Zen Cart will allow you to create
	   from within the admin two identical Options names, and assign 
	   unique options values to each. For example:
		"Color" with "red,green,blue" as one option name/value pair
		"Color" with "cyan,magenta,yellow" as another option name/value pair.
	   Internally, Zen Cart knows these are two distinct options names, 
	   but this info is not available to the user. (It may have been
	   better to have a unique Options Name, and associated Options Display Name
	   which in turn could be language specific).
	c) For this reason, the products_options_name_1 must be unique, meaning
	   you CAN have "Colors" but the associated options_values set will be
	   the sum of the example above: { red,green,blue,cyan,magenta,yellow }.  
	   (This is information in the database. The individual product will still only show
	   the attributes assigned.)
	d) It is generally easier to work with and understand the attributes if there
	   is one option name that has multiple option values associated with it, but 
	   there is no requirement to setup your site this way.
	   
4) v_products_options_values_names_1
	a) these are the values names that are assigned to the products_options_name
	b) enter the values_names, delimited with a comma for each value.
	c) note that ONLY these products_options_values_names will be assigned 
	   to the given products_model

A way to reproduce attributes in one store from another using EP4:

  If store 1 has a unique model # for each product and the option names are unique (only one instance of the option name in all of the ZC option names), then the import of the attributes_basic file has populated store 2 with all of the attributes of the product from store 1. What is missing though is the detail associated with each attribute. So, to update store 2's attribute details, a file has to be generated such that each of the four primary detailed attributes related keys match an existing entry in the attributes table. So, how to accomplish this?

  Here's what I see. It is possible at this point to export the detailed attributes from both store 1 and store 2. Each of these has text versions of the option names and option values names. The model number is whatever it is and the products_attributes_id is expected to be unique to each store.

  So what I would do would be to sort the data in both detailed lists on three fields in the same "order" (either ascending or descending but make it the same on both spreadsheets) such that sorted by products_model, then option_name, and then option_value_name.

  Then pick the method/location desired, but the goal is to eliminate from the list for store 2 any product that is not in store 1. This would be by a comparison of products_model.
  For now, also eliminate from store 1 any row that doesn't have a model #. (will have to address that separately because really that product never got uploaded to store 2, but at least the list should be small.)

  Then begin moving entire rows as necessary such that the row in store 1 lines up with the row of store 2 by first comparing option names then option value names. Provided nothing "new" has been done with store 2, these should line up exactly with no editing.

  Then once all have been lined up, copy the primary field data from store 2 over the data of the same field for store 1. Once all four columns are copied, save the file as the csv file to be uploaded and then imported into store 2.

  Obviously through this process you'll want to save a backup of the file(s) to minimize any rework. Keep in mind the filenaming convention needed by the plugin.

  And with that, the new file when uploaded and imported to store 2 should cause store 2 to have the same attributes and details of attributes as store 1.

  Other attribute guidance: Currently the detailed attributes unlike the detailed product information
  for example are based on the specific database centric field record designations.  Because of this,
  to properly update the detailed information, one must first upload and import the basic information,
  then download/export the detailed attribute data and after all of this, the CSV file processed
   as a new imported file with the updated data to be stored in the database.

IMPORTANT NOTE:
When creating your CSV file, please use Open Office (it's free!). When you save 
CSV files from Open Office (OO), it will properly encapsulate all fields for import. Excel will not 
necessarily do this, depending on your data, and the export CSV option 
you pick from within Excel, also dates and date formats may be revised by Excel and not readable upon import.

In the /examples directory is a sample input file: Attrib-Basic-EP-examples.csv  You can create your own sample file by exporting the data file type of your choosing.

The "Detailed Products Attributes" shows all attribute details assigned to a given products_model,
with one line per option_name/value combination. So a product with a dropbox of 3 colors will result in 3 lines of
data exported. As you can see, there is a significant amount of data that is associated with
attributes.

The "Stock of Items with Attributes Including SBA" option is used to support performing an inventory of stock providing a list of products that 
  1) do not have any attributes, 
  2) have attributes but are not tracked by stock, and 
  3) are tracked by stock by attributes (have attributes and the quantity of items that have that attribute/set of attributes is maintained).  
  
  This functionality will only show when the appropriate version of stock by attributes (SBA) is detected to be installed to the cart.
  If SBA is not installed, then this functionality will not be presented and the remaining instruction regarding this feature can be skipped.

Below is a brief summary of the report that is generated by the Easy Populate version 4 (EP4) "Stock of Items with Attributes
Including SBA" option and the method to access it. The file prefix has been set to: SBA-Stock-EP

The two primary characteristics to consider when using EP4 are the columns (headers) across the top of the spreadsheet and the items 
  being displayed. To explain, the headers from left to right offered by this feature are:

1. Model #
2. Status (product enabled or disabled)
3. Product Name in each language entered shifting the remaining columns to the right.
4. Product Description without html tags (no <br/>, <p>, etc.) (In each language entered shifting the remaining columns to the right.)
5. If potteryhouse's version of SBA is installed (or the customid field exists in addition to some other fields) then this column will 
   have the customid, otherwise, all of the columns below will move left to replace what would be in this column.
6. Whether the item listed in the row is tracked by Stock by Attributes (SBA) using a marker if yes and leaving the row's field blank if no.
7. A unique identifier associated with the data type of item in the row.
8. The attributes associated with the item in the row (if any exist) put together in the format 
OptionName1: OptionValue1; OptionName2:  OptionValue2; etc... 
OptionName1 may be the same as OptionName2 and still will be listed as shown with a different OptionValueX at each entry.
9. The quantity of the item in the row.

On upload/import, the only field that will change in the Zen Cart database with this report is the quantity associated with the row's data.

Item 7 of the list was abbreviated to simplify the explanation. For an entry displayed that shows the total quantity associated with a 
  product, the value in that column is the products_id taken from the products table of the database. For a row that contains SBA 
  information, the value is the stock_id taken from the products_with_attributes_stock table. These values are provided to support 
  import of the data and they should not be revised for normal operation. The position of the column was chosen to not place it adjacent 
  to a field that is likely to be changed by the user. (Technically for EP4 to import this new file, the only columns important for the 
  import are the ones located in items 6, 7 and 9, all of the other columns were provided to help the individual performing the stock 
  inventory identify the product(s).) Ideally, column 7 would not exist and instead the program would determine the appropriate value for 
  that column based on other information in the table so that the spreadsheet would not be dependent on the current database but could be 
  applied to any database at any time.  This will require a revision to accomplish.

This brings us to the arrangement of the rows. Every row of data has the potential of having attributes associated with the product, but 
  every product has a quantity associated with it.  Below are two examples 1) of a product without attributes, the other with attributes as 
  described. 

On export, every product will be listed, whether an active product or not. If a product is tracked by SBA, then the product's data will 
  be provided as the first row for that product, followed by rows of the SBA associated data. So if a product has two option 
  values (green and blue) for a single option name (Color) but is not tracked by SBA then in the row for that product the attributes 
  column (item 7 of above) and quantity (item 8 of above) assuming 70 of this product will show as:

Product has no attributes:
| Attribute(s)              | Quantity
|                           | 70

Product has two attributes (2 option values, but one option name):
| Attribute(s)              | Quantity
| Color: green;Color: blue  | 70

If a second product had the same attributes with 70 total, but 40 were green and 30 were blue tracked by SBA, then the export would be 
  something like:

Product has two attributes and both are tracked by SBA:
| Attribute(s)              | Quantity
| Color: green;Color: blue; | 70
| Color: green;		   | 40
| Color: blue;			   | 30

There are two links available for import. One that imports all of the data as entered (Import) (Import of the below followed by export 
  would provide the below) it would be considered acceptable and left as is. The other imports the data, but then synchronizes the total 
  with the sum of the SBA tracked quantity. Import of the below followed by export would provide the above. 

Product has two attributes, both tracked by SBA but total quantity exceeds the sum of each:
| Attribute(s)              | Quantity
| Color: green;Color: blue; | 85
| Color: green;		   | 40
| Color: blue;			   | 30

The first example applies to the display of every product in the database:
- If a product does not have attributes, there is a total quantity of the product.
- If a product has attributes, then there are attributes associated with the product but that does not mean that quantities 
    are applied to each attribute: (for example if an attribute were a checkbox of "include or not include a generic note" with 
    the product, there is less likely a need to track a specific quantity of items with and without the note option, therefore the 
    row with an attribute would have a total number of items. The same could be said for the green and blue options if the green and 
    blue attributes had an unlimited supply, say if a marker is used to add a green stripe or a blue stripe. The number of stripes 
    is more dependent on the age of the pen, the quantity of ink and the time available of those applying the stripe than the number 
    of products that already have the stripe. So that product may not be tracked by SBA.) The data of the second example is similar to 
    this situation, or if the product has not yet been entered to be tracked by SBA.
- Lastly, if an attribute is applied to a product and the quantity of the product is tracked by SBA, then the database typically maintains 
    two sets of quantities. One is the total number of the product, the other is the number of the product that has the specific attributes 
    being tracked: ie, quantity 5 of a 10" diameter ring, made of silver, with a blue pendant as compared to quantity of 6 of a 5" diameter 
    ring, made of stainless steel, with a green pendant, and all the variations of these options. So in this case, the data provided in the 
    exported file would be similar to what was provided in the third example. The first row shows the total number of the product and all 
    possible attributes, each subsequent row shows the specific quantity of the product having the assigned attribute(s).
    
GENERAL OVERVIEW OF OPERATION:
  So with regards to the way EP4 works. As you have found/desire, entry of multiple products in a single "swoop" is easier 
    with this plugin. That said, it still "follows" the same ZC process. First the product must exist, and as part of "creating" the product, 
    it must go into a category. Then, to apply attributes, the attribute(s) must exist (attributes basic), then the product is populated 
    with the attribute(s) (attributes detailed). In each step that relates to a product, EP4 generally requires a unique model_number; however, 
    with EP4 version 4.0.32+, it is possible to relate product by the products_id when the admin configuration settings are "properly" set. 
    A word of note/caution though is that it also depends on how you plan to use/automate EP4. If your import information will be from a 
    vendor in the form of some other file, then purely using the products_model (or possibly some other field as recoded in Ep4) would be suggested. 
    If the data to be imported will be "self generated" then use of the products_id might be more suitable. For those that don't yet have a 
    products_model associated, the same feature can be used to apply one to the product.
    If you want to delete a product, then set the status of the product to the number 9.
    Understand that this will completely remove the product from the store, not just disable it (status of 0).
    To change the master_category_id for a product to be the category of the current row set the status to 7.
    If there is no master_category_id assigned, then the current category on the row will become the master_category_id.
    If there is already a master_category_id assigned to the product, then the category on the row will
    become the master_category_id and the product will be removed from the previous category represented
    by the master_category_id. The process is performed by always adding to the database before deleting anything.
    Using this operation it is therefore possible to now remove the linked product through a sequence.
    If there were three categories assigned to a product, then assigning the master_category_id to
    each category that is not the current master_category will delete the product from the other
    category(ies) and do this until the last entry for that product has the category to be the product's master_category_id.
