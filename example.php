/*

        moniKey.php
        Feb 2012
        AGPL
        Ronaldo Barbachano

        Shows a few examples of various ways moniKey can be called.

*/

require('moniKey.php');

// Usage

//Example data
$data = array( "title" => "Calvin and Hobbes", "author" => "Bill Wattersoz" );
//Example 'update' data
$data_2 = array("title"=> "hmm");

//moniKey::_();
// work inside the name of a  collection

// store data to container named 'test'
print_r(moniKey::_test($data));

// search for all records in collection 'test'
print_r(moniKey::_test());

// work inside the name of a database and collection
//print_r(moniKey::_myDatabase_myCollection());

// retreive record with _id equaling value below
//print_r(moniKey::_test('4f3c294abafe5b6a0700000a'));

// update record of id with data - todo send copy of record back on success ??
//print_r(moniKey::_test('4f3c294abafe5b6a0700000a',$data_2));
