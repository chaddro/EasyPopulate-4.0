EasyPopulate 4.0 Beta





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


