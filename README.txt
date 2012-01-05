EasyPopulate 4.0 Beta

There is a Zen-cart.com Support Thread located here:
http://www.zen-cart.com/forum/showthread.php?t=190417

I recommend that you first enter a couple Categories and Products through the normal Zencart Admin. This will help you understand the exported file formats.

Things of Import:

1) EP4 works only with CSV data files, and for best cross platform compatibility please use OpenOffice to Export your CSV file.
You should select the Comma as your field delimiter, and the Double Quote as your text delimiter. You may not get proper results using Excel.

2) You MUST use products_models to distinguish your products for import. Any record with a blank v_products_model entry will be skipped.
   Also note that is you enter the same products_model twice, the latest record entry will over-write any previous entries. The exception
   here is if you enter a different category; this will result in a linked product. "Duplicate" products with the same products_model number
   is not supported. If you have these entries in your database, you may get unpredictable results.

3) Categories are handled differently from other versions of EP. You can now import multilingual categories (Like Products Names, descriptions, etc.).
To achieve this, each v_categories_names_1, v_categories_names_2, etc. correspond to a language installed in your system, and individual category 
names are separated by the Carat "^" symbol. For Example: Bar Supplies^Glass Washers^Brushes

"Bar Supplies" will be your Top level Category, with "Glass Washers" as a sub-directory, and "Brushes" as a sub-directory of "Glass Washers".

Be careful when creating your category names. "Bar Supplies" is not equal to "BAR Supplies" ... this will result in TWO category entries.

As of 4.0.17, your lowest defined language (by language id) is needed to when creating categories. In the future I'll change this to the defined default language, but that
will take some additional work.

4) Work flow is somewhat different than other version of EP. With EP4 you first upload your file, then click on Import. Optionally you can also Split an import file if it is too large. You can set the number of records to split on in the configuration settings. Default is 2000. If you have a powerful server, you can up this significantly. Testing on a VPS with an import file of 900,000 records, I broke into 50,000 record segments. Import of each 50,000 records took about 250 seconds.

Also NO streaming upload or download support. Sorry if you liked this feature. A lot of effort has been put into improving the code's performance and streaming the data was a real memory hog. To download your exported file, you will need to "right-click, save-as". You may be able to set your browser to automatically download CSV files. I'll come up with a better solution in due time. 

5) File names act as a switch inside the script for Importing. Namely: PriceBreaks-EP, CategoryMeta-EP, and Attrib-EP ... these import files must have names that start with these string.

6) Basic Attribute Import. Please read the notes below carefully for how to use this feature which is still in development. Currently, you can at Import create your products_options_name (your attribute name, ie. color) , products_options_type (checkbox, dropdown, etc) and, products_options_values_name (red, green, blue).

Be sure to backup you data before importing your files. Remember, this is still "beta". I have made every attempt to make this a solid bug free product, but it does require more testing. I have added a lot of error trapping, but I'm sure I've missed things.


IMPORTING ATTRIBUTES
Including sample attribute input file.

I am currently able to correctly import basic attributes, and assign 
the options to an associated products model. It is possible to create
multiple sets of Option Names / Option Values and assign to a single 
product, say "Size" and "Color".

CSV file currently has 4 column:
1) v_products_model
	a) The products model number must already exist, and should be
	   unique to the store as linked products have not been tested.
2) v_products_options_name
	a) The option name you want to create
	b) It is important to note that Zen Cart will allow you to create
	   from within the admin two identical Options names, and assign 
	   uniques options values to each. For example:
		"Color" with "red,green,blue" 
		"Color" with "cyan,magenta,yellow"
	   Internally, Zen Cart knows these are two distinct options, 
	   but this info is not available to the user. (It would have been
	   better to have a unique Options Name, and associated Options Display Name
	   which in turn could be language specific).
	c) For this reason, the products_options_name must be unique, meaning
	   you CAN have "Colors" but the assiated options_values set will be
	   the sum of the example above: { red,green,blue,cyan,magenta,yellow }.

3) v_products_option_type
	a) this is the type of attribute you want to create and must be
	   a number between 0 - 5 and are defined as follows:
		0 - Drop Down Box
		1 - Text Box
		2 - Radio Button
		3 - Check Box
		4 - File Upload
		5 - Read Only
	b) for a giving option_name (say "Color"), do not change the products_option_type
	   on subsequent entries, doing so will not give the results you want.
	c) If you need a "Color" with both a drop down box and check box, you will need
	   to define two unique Options Names.

4) v_products_options_values_names
	a) these are the values names that are assigned to the products_options_name
	b) enter the values_names, delimited with a comma
	c) note that ONLY these products_options_values_names will be assigned 
	   to the given products_model

IMPORTANT NOTE:
When creating your CSV file, please use Open Office (it's free!). When you save 
CSV files from OO, it will properly encapsulate all fields. Excel will not 
necessarily do this, depending on your data, and the export CSV option 
you pick from within Excel.

In the /examples directory are some sample input files.

Attrib-EP-colors.csv
In this example, we are creating a new drop down attribute "Color" with values
{ red, green, blue, cyan, magenta, yellow, black }

Developer's Note: I am still working on the "Basic Products Attributes (single-line)" download options.
This file is supposed to be in the same format as the current example files, but as yet I have
not worked out the sql/code to do so. 

The "Detailed Products Attributes" shows all attribute details assigned to a given products_model,
with one line per option_name. So a product with a dropbox of 3 colors will result in 3 lines of
data exported. As you can see, there is a significant amount of data that is associated with
attributes.


