EasyPopulate 4.0 Beta SBA_Stock

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

5) File names act as a switch inside the script for Importing. Namely: PriceBreaks-EP, CategoryMeta-EP, Featured-EP, and Attrib-Basic-EP/Attrib-Detailed-EP ... these import files must have names that start with these string.

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

2) v_products_option_type
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

3) v_products_options_name_1
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
	c) For this reason, the products_options_name_1 must be unique, meaning
	   you CAN have "Colors" but the assiated options_values set will be
	   the sum of the example above: { red,green,blue,cyan,magenta,yellow }.

4) v_products_options_values_names_1
	a) these are the values names that are assigned to the products_options_name
	b) enter the values_names, delimited with a comma
	c) note that ONLY these products_options_values_names will be assigned 
	   to the given products_model

IMPORTANT NOTE:
When creating your CSV file, please use Open Office (it's free!). When you save 
CSV files from OO, it will properly encapsulate all fields. Excel will not 
necessarily do this, depending on your data, and the export CSV option 
you pick from within Excel.

In the /examples directory is an sample input files: Attrib-Basic-EP-examples.csv

The "Detailed Products Attributes" shows all attribute details assigned to a given products_model,
with one line per option_name. So a product with a dropbox of 3 colors will result in 3 lines of
data exported. As you can see, there is a significant amount of data that is associated with
attributes.

The "Stock of Items with Attributes Including SBA" option is used to support performing an inventory of stock that includes products that 1) do not have any attributes, 2) have attributes but are not tracked by stock, and 3) are tracked by stock by attributes (have attributes and the quantity of items that have that attribute/set of attributes is maintained).  This functionality will only show when the appropriate version of stock by attributes (SBA) is installed to the cart.  If SBA is not installed, then this functionality will not be presented and the remaining instruction regarding this feature can be skipped.

Below is a brief summary of the report that is generated by the Easy Populate version 4 (EP4) "Stock of Items with Attributes Including SBA" option and the method to access it. The file prefix has been set to: SBA-Stock-EP

The two primary characteristics to consider when using EP4 are the columns (headers) across the top of the spreadsheet and the items being displayed. To explain, the headers from left to right offered by this feature are:

1. Model #
2. Status (product enabled or disabled)
3. Product Name in each language entered shifting the remaining columns to the right.
4. Product Description without html tags (no <br/>, <p>, etc.) (In each language entered shifting the remaining columns to the right.)
5. Whether the item listed in the row is tracked by Stock by Attributes (SBA) using a marker if yes and leaving the row's field blank if no.
6. A unique identifier associated with the data type of item in the row.
7. The attributes associated with the item in the row (if any exist) put together in the format OptionName1: OptionValue1; OptionName2: OptionValue2; etc... OptionName1 may be the same as OptionName2 and still will be listed as shown.
8. The quantity of the item in the row.

On upload/import, the only field that will change in the Zen Cart database with this report is the quantity associated with the row's data.

Item 6 of the list was abbreviated to simplify the explanation. For an entry displayed that shows the total quantity associated with a product, the value in that column is the products_id taken from the products table of the database. For a row that contains SBA information, the value is the stock_id taken from the products_with_attributes_stock table. These values are provided to support import of the data and for normal operation, they should not be revised. The position of the column was chosen to not place it adjacent to a field that is likely to be changed by the user. (Technically for EP4 to import this new file, the only two columns important for the import are the ones located in items 5, 6 and 8, all of the other columns were provided to help the individual performing the stock inventory identify the product(s).) Ideally, column 6 would not exist and instead the program would determine the appropriate value for that column based on other information in the table so that the spreadsheet would not be dependent on the current database but could be applied to any database at any time.  This will require a revision to accomplish.
This brings us to the arrangement of the rows. Every row of data has the potential of having attributes associated with the product, but every product has a quantity associated with it.  On export, every product will be listed, whether an active product or not. If a product is tracked by SBA, then the product's data will be provided first, followed by the SBA associated data. So if a product has two option values (green and blue) for a single option name (Color) but is not tracked by SBA then in the row for that product the attributes column (item 7 of above) and if there were 70 of this product (item 8 of above) will show as:

Product has no attributes:
| Attribute(s)              | Quantity
|                           | 70

Product has two attributes:
| Attribute(s)              | Quantity
| Color: green;Color: blue  | 70

If a second product had the same attributes with 70 total, but 40 were green and 30 were blue tracked by SBA, then the export would be something like:

Product has two attributes and both are tracked by SBA:
| Attribute(s)              | Quantity
| Color: green;Color: blue; | 70
| Color: green;		   | 40
| Color: blue;			   | 30

There are two links available for import. One that imports all of the data as entered (Import) (Import of the below followed by export would provide the below) it would be considered acceptable and left as is. The other imports the data, but then synchronizes the total with the sum of the SBA tracked quantity. Import of the below followed by export would provide the above. 

Product has two attributes, both tracked by SBA but total quantity exceeds the sum of each:
| Attribute(s)              | Quantity
| Color: green;Color: blue; | 85
| Color: green;		   | 40
| Color: blue;			   | 30

The first example applies to the display of every product in the database:
- If a product does not have attributes, there is a total quantity of the product.
- If a product has attributes, then there are attributes associated with the product but that does not mean that quantities are applied to each attribute: (for example if an attribute were a checkbox of "include or not include a generic note" with the product, there is less likely a need to track a specific quantity of items with and without the note option, therefore the row with an attribute would have a total number of items. The same could be said for the green and blue options if the green and blue attributes had an unlimited supply, say if a marker is used to add a green stripe or a blue stripe. The number of stripes is more dependent on the age of the pen, the quantity of ink and the time available of those applying the stripe than the number of products that already have the stripe. So that product may not be tracked by SBA.) The data of the second example is similar to this situation, or if the product has not yet been entered to be tracked by SBA.
- Lastly, if an attribute is applied to a product and the quantity of the product is tracked by SBA, then the database typically maintains two sets of quantities. One is the total number of the product, the other is the number of the product that has the specific attributes being tracked: ie, quantity 5 of a 10" diameter ring, made of silver, with a blue pendant as compared to quantity of 6 of a 5" diameter ring, made of stainless steel, with a green pendant, and all the variations of these options. So in this case, the data provided in the exported file would be similar to what was provided in the third example. The first row shows the total number of the product and all possible attributes, each subsequent row shows the specific quantity of the product having the assigned attribute(s).