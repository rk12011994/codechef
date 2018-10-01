<?php
//ob_start();
//This is the server that QBWC talks to.

//include config
require_once 'config.php';


// Require the framework
require_once 'QuickBooks.php';

	//echo QuickBooks_Utilities::configRead(QB_QUICKBOOKS_DSN, $user, md5(__FILE__), QB_QUICKBOOKS_CONFIG_LAST . '-' . $action, $type, $opts);
	// This creates a username and password which is used by the Web Connector to authenticate
	//QuickBooks_Utilities::createUser($dsn, 'csign', 'csignpass');


// Logging level for the framework
//$log_level = QUICKBOOKS_LOG_NORMAL;
//$log_level = QUICKBOOKS_LOG_VERBOSE;
//$log_level = QUICKBOOKS_LOG_DEBUG;
$log_level = QUICKBOOKS_LOG_DEVELOP;		// Use this level until you're sure everything works!!!


//include import script, comment out when we are done synching
include('sync_functions.php');


// Map QuickBooks actions to handler functions
$map = array(
	'CompanyAdd' => array( '_quickbooks_company_add_request', '_quickbooks_company_add_response' ),
	'BranchAdd' => array( '_quickbooks_branch_add_request', '_quickbooks_branch_add_response' ),
	'CustomerAdd' => array( '_quickbooks_customer_add_request', '_quickbooks_customer_add_response' ),
	'CompanyMod' => array( '_quickbooks_company_mod_request', '_quickbooks_company_mod_response' ),
	'BranchMod' => array( '_quickbooks_branch_mod_request', '_quickbooks_branch_mod_response' ),
	'CustomerMod' => array( '_quickbooks_customer_mod_request', '_quickbooks_customer_mod_response' ),
	'VendorAdd' => array( '_quickbooks_vendor_add_request', '_quickbooks_vendor_add_response' ),
	'VendorMod' => array( '_quickbooks_vendor_mod_request', '_quickbooks_vendor_mod_response' ),
	'CompanySequence' => array( '_quickbooks_company_sequence_request', '_quickbooks_company_sequence_response' ),
	'BranchSequence' => array( '_quickbooks_branch_sequence_request', '_quickbooks_branch_sequence_response' ),
	'CustomerSequence' => array( '_quickbooks_customer_sequence_request', '_quickbooks_customer_sequence_response' ),
	'VendorSequence' => array( '_quickbooks_vendor_sequence_request', '_quickbooks_vendor_sequence_response' ),
	'InvoiceAdd' => array( '_quickbooks_invoice_add_request', '_quickbooks_invoice_add_response' ),
	'BillAdd' => array( '_quickbooks_bill_add_request', '_quickbooks_bill_add_response' ),
	'InvoiceSequence' => array( '_quickbooks_invoice_sequence_request', '_quickbooks_invoice_sequence_response' ),
	'InvoiceVoid' => array( '_quickbooks_invoice_void_request', '_quickbooks_invoice_void_response' ),
	'BillVoid' => array( '_quickbooks_bill_void_request', '_quickbooks_bill_void_response' ),
	'ImportInvoices' => array( '_quickbooks_invoice_import_request', '_quickbooks_invoice_import_response' ),
	'ImportBills' => array( '_quickbooks_bill_import_request', '_quickbooks_bill_import_response' ),
	'SyncCustomers' => array( '_quickbooks_sync_customers_request', '_quickbooks_sync_customers_response' ),
	'SyncVendors' => array( '_quickbooks_sync_vendors_request', '_quickbooks_sync_vendors_response' )
	);

// Trigger actions when an error is returned by QuickBooks
$errmap = array(
	'0x80040400' => '_quickbooks_error_handler_0x80040400', 		// Catch "your XML is invalid" errors
	3070 => '_quickbooks_error_handler_3070', 				// Catch "string is too long to fit in that field" errors
	1 => '_quickbooks_error_handler_no_error', 				// Catch "string is too long to fit in that field" errors
	"-2" => '_quickbooks_error_handler_no_error', 				// Catch "string is too long to fit in that field" errors
	500 => '_quickbooks_error_handler_no_error', 				// Catch "string is too long to fit in that field" errors
	'*' => '_quickbooks_error_handler_catchall', 			// catch-all
	);


// An array of callback hooks

$hooks = array(
	QUICKBOOKS_HANDLERS_HOOK_LOGINSUCCESS => '_quickbooks_hook_loginsuccess'	// call this whenever a successful login occurs
	);



function _quickbooks_hook_loginsuccess($requestID, $user, $hook, &$err, $hook_data, $callback_config)
{
	// For new users, we need to set up a few things

	// Fetch the queue instance
	$Queue = new QuickBooks_Queue(QB_QUICKBOOKS_DSN);
	//$Queue = QuickBooks_Queue_Singleton::getInstance();
	$date = '1983-01-02 12:01:01';

	// Set up the invoice imports
	if (!_quickbooks_get_last_run($user, 'ImportInvoices'))
	{
		// And write the initial sync time
		_quickbooks_set_last_run($user, 'ImportInvoices', $date);
	}


	if (!_quickbooks_get_last_run($user, 'ImportBills'))
	{
		// And write the initial sync time
		_quickbooks_set_last_run($user, 'ImportBills', $date);
	}


	$Queue->enqueue('ImportInvoices', 1);
	$Queue->enqueue('ImportBills', 1);

}


/*
function _quickbooks_hook_loginsuccess($requestID, $user, $hook, &$err, $hook_data, $callback_config)
{
	// Do something whenever a successful login occurs...
	// Like enqueue users who need to be imported, etc
}
*/


// Create a new server and tell it to handle the requests
// __construct($dsn_or_conn, $map, $errmap = array(), $hooks = array(), $log_level = QUICKBOOKS_LOG_NORMAL, $soap = QUICKBOOKS_SOAPSERVER_PHP, $wsdl = QUICKBOOKS_WSDL, $soap_options = array(), $handler_options = array(), $driver_options = array(), $callback_options = array()
$soapserver = QUICKBOOKS_SOAPSERVER_BUILTIN; // A pure-PHP SOAP server (no PHP ext/soap extension required, also makes debugging easier)

$Server = new QuickBooks_Server($dsn, $map, $errmap, $hooks, $log_level, $soapserver, QUICKBOOKS_WSDL, array(), array(), $driver_options, array());
$response = $Server->handle(true, true);




/*
// If you wanted, you could do something with $response here for debugging

$fp = fopen('/path/to/file.log', 'a+');
fwrite($fp, $response);
fclose($fp);
*/

/**
 * Generate a qbXML response to add a particular customer to QuickBooks
 *
 * So, you've queued up a QUICKBOOKS_ADD_CUSTOMER request with the
 * QuickBooks_Queue class like this:
 * 	$Queue = new QuickBooks_Queue('mysql://user:pass@host/database');
 * 	$Queue->enqueue(QUICKBOOKS_ADD_CUSTOMER, $primary_key_of_your_customer);
 *
 * And you're registered a request and a response function with your $map
 * parameter like this:
 * 	$map = array(
 * 		QUICKBOOKS_ADD_CUSTOMER => array( '_quickbooks_customer_add_request', '_quickbooks_customer_add_response' ),
 * 	 );
 *
 * This means that every time QuickBooks tries to process a
 * QUICKBOOKS_ADD_CUSTOMER action, it will call the
 * '_quickbooks_customer_add_request' function, expecting that function to
 * generate a valid qbXML request which can be processed. So, this function
 * will generate a qbXML CustomerAddRq which tells QuickBooks to add a
 * customer.
 *
 * Our response function will in turn receive a qbXML response from QuickBooks
 * which contains all of the data stored for that customer within QuickBooks.
 *
 * @param string $requestID					You should include this in your qbXML request (it helps with debugging later)
 * @param string $action					The QuickBooks action being performed (CustomerAdd in this case)
 * @param mixed $ID							The unique identifier for the record (maybe a customer ID number in your database or something)
 * @param array $extra						Any extra data you included with the queued item when you queued it up
 * @param string $err						An error message, assign a value to $err if you want to report an error
 * @param integer $last_action_time			A unix timestamp (seconds) indicating when the last action of this type was dequeued (i.e.: for CustomerAdd, the last time a customer was added, for CustomerQuery, the last time a CustomerQuery ran, etc.)
 * @param integer $last_actionident_time	A unix timestamp (seconds) indicating when the combination of this action and ident was dequeued (i.e.: when the last time a CustomerQuery with ident of get-new-customers was dequeued)
 * @param float $version					The max qbXML version your QuickBooks version supports
 * @param string $locale
 * @return string							A valid qbXML request
 */



 function _quickbooks_company_add_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{
	// You'd probably do some database access here to pull the record with
	//	ID = $ID from your database and build a request to add that particular
	//	customer to QuickBooks.
	//
	// So, when you implement this for your business, you'd probably do
	//	something like this...:


	// Fetch your customer record from your database
	global $cssDB;
	$company = $cssDB->query_first("SELECT * FROM companies WHERE id = " . $ID);
	// Create and return a qbXML request

	if (strlen($company['name'].' ('.$company['id'].')') > 40)
	{
		$len = 40-strlen(' ('.$company['id'].')');
		$name_string = substr($company['name'],0,$len).' ('.$company['id'].')';

	}
	else
	{
		$name_string = $company['name'].' ('.$company['id'].')';
	}

	if(strlen($company['name']) > 40)
	{
		$company['name'] = substr($company['name'],0,40);

	}


	$qbxml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="2.0"?>
		<QBXML>
			<QBXMLMsgsRq onError="continueOnError">
				<CustomerAddRq requestID="' . $requestID . '">
					<CustomerAdd>
						<Name>'.$name_string.'</Name>
						<CompanyName>'.$company['name'].'</CompanyName>
					</CustomerAdd>
				</CustomerAddRq>
			</QBXMLMsgsRq>
		</QBXML>';

file_put_contents('COMPANY-ADD-OCT17.txt', $qbxml, FILE_APPEND);

	return $qbxml;


}



/**
 * Receive a response from QuickBooks
 *
 * @param string $requestID					The requestID you passed to QuickBooks previously
 * @param string $action					The action that was performed (CustomerAdd in this case)
 * @param mixed $ID							The unique identifier of the record
 * @param array $extra
 * @param string $err						An error message, assign a valid to $err if you want to report an error
 * @param integer $last_action_time			A unix timestamp (seconds) indicating when the last action of this type was dequeued (i.e.: for CustomerAdd, the last time a customer was added, for CustomerQuery, the last time a CustomerQuery ran, etc.)
 * @param integer $last_actionident_time	A unix timestamp (seconds) indicating when the combination of this action and ident was dequeued (i.e.: when the last time a CustomerQuery with ident of get-new-customers was dequeued)
 * @param string $xml						The complete qbXML response
 * @param array $idents						An array of identifiers that are contained in the qbXML response
 * @return void
 */
function _quickbooks_company_add_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{
	// Great, customer $ID has been added to QuickBooks with a QuickBooks
	//	ListID value of: $idents['ListID']
	//

	global $cssDB;
	$data['qbListID'] = $idents['ListID'];
	$cssDB->query_update('companies', $data, "id = " . $ID);

	/*
	mysql_query("UPDATE your_customer_table SET quickbooks_listid = '" . mysql_escape_string($idents['ListID']) . "' WHERE your_customer_ID_field = " . (int) $ID);
	*/
}






function _quickbooks_branch_add_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{
	// You'd probably do some database access here to pull the record with
	//	ID = $ID from your database and build a request to add that particular
	//	customer to QuickBooks.
	//
	// So, when you implement this for your business, you'd probably do
	//	something like this...:


	// Fetch your customer record from your database
	global $cssDB;
	//$user = $cssDB->query_first("SELECT * FROM users WHERE id = " . $ID);

	$branch = $cssDB->query_first("SELECT * FROM branch_offices WHERE id = " . $ID);
	$company = $cssDB->query_first("SELECT * FROM companies WHERE id = " . $branch['companyID']);


		if (strlen($branch['city'].', '.$branch['state'].' ('.$branch['id'].')') > 40)
	{
		$len = 40-strlen(' ('.$branch['id'].')');
		$name_string = substr($branch['city'].', '.$branch['state'],0,$len).' ('.$branch['id'].')';

	}
	else
	{
		$name_string = $branch['city'].', '.$branch['state'].' ('.$branch['id'].')';
	}

	if(strlen($company['name']) > 40)
	{
		$company['name'] = substr($company['name'],0,40);

	}

	// Create and return a qbXML request
	$qbxml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="2.0"?>
		<QBXML>
			<QBXMLMsgsRq onError="continueOnError">
				<CustomerAddRq requestID="' . $requestID . '">
					<CustomerAdd>
						<Name>'.$name_string.'</Name>
						<ParentRef>
							<ListID>'.$company['qbListID'].'</ListID>
						</ParentRef>
						<BillAddress>
							<Addr1>'.$company['name'].'</Addr1>
							<Addr2>'.$branch['address'].'</Addr2>
							<City>'.$branch['city'].'</City>
							<State>'.$branch['state'].'</State>
							<PostalCode>'.$branch['zip_code'].'</PostalCode>
							<Country>United States</Country>
						</BillAddress>
						<Phone>'.$branch['business_number'].'</Phone>
						<Fax>'.$branch['fax_number'].'</Fax>
					</CustomerAdd>
				</CustomerAddRq>
			</QBXMLMsgsRq>
		</QBXML>';


		file_put_contents('BRANCH-ADD-OCT17.txt', $qbxml, FILE_APPEND);
	return $qbxml;


}



/**
 * Receive a response from QuickBooks
 *
 * @param string $requestID					The requestID you passed to QuickBooks previously
 * @param string $action					The action that was performed (CustomerAdd in this case)
 * @param mixed $ID							The unique identifier of the record
 * @param array $extra
 * @param string $err						An error message, assign a valid to $err if you want to report an error
 * @param integer $last_action_time			A unix timestamp (seconds) indicating when the last action of this type was dequeued (i.e.: for CustomerAdd, the last time a customer was added, for CustomerQuery, the last time a CustomerQuery ran, etc.)
 * @param integer $last_actionident_time	A unix timestamp (seconds) indicating when the combination of this action and ident was dequeued (i.e.: when the last time a CustomerQuery with ident of get-new-customers was dequeued)
 * @param string $xml						The complete qbXML response
 * @param array $idents						An array of identifiers that are contained in the qbXML response
 * @return void
 */
function _quickbooks_branch_add_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{
	// Great, customer $ID has been added to QuickBooks with a QuickBooks
	//	ListID value of: $idents['ListID']
	//

	global $cssDB;
	$data['qbListID'] = $idents['ListID'];
	$cssDB->query_update('branch_offices', $data, "id = " . $ID);

	/*
	mysql_query("UPDATE your_customer_table SET quickbooks_listid = '" . mysql_escape_string($idents['ListID']) . "' WHERE your_customer_ID_field = " . (int) $ID);
	*/
}








function _quickbooks_customer_add_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{
	// You'd probably do some database access here to pull the record with
	//	ID = $ID from your database and build a request to add that particular
	//	customer to QuickBooks.
	//
	// So, when you implement this for your business, you'd probably do
	//	something like this...:


	// Fetch your customer record from your database
	global $cssDB;
	$user = $cssDB->query_first("SELECT * FROM users WHERE id = " . $ID);
	$company = $cssDB->query_first("SELECT * FROM companies WHERE id = " . $user['companyID']);
	$branch = $cssDB->query_first("SELECT * FROM branch_offices WHERE id = " . $user['branchOfficeID']);


	if (strlen($user['last_name'].', '.$user['first_name'].' ('.$user['id'].')') > 40)
	{
		$len = 40-strlen(' ('.$user['id'].')');
		$name_string = substr($user['last_name'].', '.$user['first_name'],0,$len).' ('.$user['id'].')';

	}
	else
	{
		$name_string = $user['last_name'].', '.$user['first_name'].' ('.$user['id'].')';
	}

	if(strlen($company['name']) > 40)
	{
		$company['name'] = substr($company['name'],0,40);

	}

	// Create and return a qbXML request
	$qbxml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="2.0"?>
		<QBXML>
			<QBXMLMsgsRq onError="continueOnError">
				<CustomerAddRq requestID="' . $requestID . '">
					<CustomerAdd>
						<Name>'.$name_string.'</Name>
						<ParentRef>
							<ListID>'.$branch['qbListID'].'</ListID>
						</ParentRef>
						<FirstName>'.$user['first_name'].'</FirstName>
						<LastName>'.$user['last_name'].'</LastName>
						<BillAddress>
							<Addr1>'.$company['name'].'</Addr1>
							<Addr2>'.$branch['address'].'</Addr2>
							<City>'.$branch['city'].'</City>
							<State>'.$branch['state'].'</State>
							<PostalCode>'.$branch['zip_code'].'</PostalCode>
							<Country>United States</Country>
						</BillAddress>
						<Phone>'.$branch['business_number'].'</Phone>
						<Fax>'.$branch['fax_number'].'</Fax>
						<Email>'.$user['email'].'</Email>
						<Contact>'.$user['first_name'].' '.$user['last_name'].'</Contact>
					</CustomerAdd>
				</CustomerAddRq>
			</QBXMLMsgsRq>
		</QBXML>';


		file_put_contents('CUSTOMER-ADD-OCT17.txt', $qbxml, FILE_APPEND);
	return $qbxml;


}



/**
 * Receive a response from QuickBooks
 *
 * @param string $requestID					The requestID you passed to QuickBooks previously
 * @param string $action					The action that was performed (CustomerAdd in this case)
 * @param mixed $ID							The unique identifier of the record
 * @param array $extra
 * @param string $err						An error message, assign a valid to $err if you want to report an error
 * @param integer $last_action_time			A unix timestamp (seconds) indicating when the last action of this type was dequeued (i.e.: for CustomerAdd, the last time a customer was added, for CustomerQuery, the last time a CustomerQuery ran, etc.)
 * @param integer $last_actionident_time	A unix timestamp (seconds) indicating when the combination of this action and ident was dequeued (i.e.: when the last time a CustomerQuery with ident of get-new-customers was dequeued)
 * @param string $xml						The complete qbXML response
 * @param array $idents						An array of identifiers that are contained in the qbXML response
 * @return void
 */
function _quickbooks_customer_add_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{
	// Great, customer $ID has been added to QuickBooks with a QuickBooks
	//	ListID value of: $idents['ListID']
	//

	global $cssDB;
	$data['qbListID'] = $idents['ListID'];
	$cssDB->query_update('users', $data, "id = " . $ID);

	/*
	mysql_query("UPDATE your_customer_table SET quickbooks_listid = '" . mysql_escape_string($idents['ListID']) . "' WHERE your_customer_ID_field = " . (int) $ID);
	*/
}




/**
 * Build a request to import customers already in QuickBooks into our application
 */
function _quickbooks_customer_sequence_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{

	global $cssDB;
	$user = $cssDB->query_first("SELECT * FROM users WHERE id = " . $ID);

	// Iterator support (break the result set into small chunks)
	$attr_iteratorID = '';
	$attr_iterator = ' iterator="Start" ';

	// Build the request
	$xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
			<QBXMLMsgsRq onError="continueOnError">
				<CustomerQueryRq>
					<ListID>'.$user['qbListID'].'</ListID>
				</CustomerQueryRq>
			</QBXMLMsgsRq>
		</QBXML>';

		//<CustomerQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . '>
		//<MaxReturned>1</MaxReturned>
					//<ListID>' . $user['qbListID'] . '</ListID>



	return $xml;
}






function _quickbooks_company_sequence_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{

	global $cssDB;
	$user = $cssDB->query_first("SELECT * FROM companies WHERE id = " . $ID);

	// Iterator support (break the result set into small chunks)
	$attr_iteratorID = '';
	$attr_iterator = ' iterator="Start" ';

	// Build the request
	$xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
			<QBXMLMsgsRq onError="continueOnError">
				<CustomerQueryRq>
					<ListID>'.$user['qbListID'].'</ListID>
				</CustomerQueryRq>
			</QBXMLMsgsRq>
		</QBXML>';

		//<CustomerQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . '>
		//<MaxReturned>1</MaxReturned>
					//<ListID>' . $user['qbListID'] . '</ListID>



	return $xml;
}





function _quickbooks_branch_sequence_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{

	global $cssDB;
	$user = $cssDB->query_first("SELECT * FROM branch_offices WHERE id = " . $ID);

	// Iterator support (break the result set into small chunks)
	$attr_iteratorID = '';
	$attr_iterator = ' iterator="Start" ';

	// Build the request
	$xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
			<QBXMLMsgsRq onError="continueOnError">
				<CustomerQueryRq>
					<ListID>'.$user['qbListID'].'</ListID>
				</CustomerQueryRq>
			</QBXMLMsgsRq>
		</QBXML>';

		//<CustomerQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . '>
		//<MaxReturned>1</MaxReturned>
					//<ListID>' . $user['qbListID'] . '</ListID>



	return $xml;
}

/**
 * Handle a response from QuickBooks
 */
function _quickbooks_customer_sequence_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{

	$errnum = 0;
	$errmsg = '';
	$Parser = new QuickBooks_XML_Parser($xml);
	if ($Doc = $Parser->parse($errnum, $errmsg))
	{
		$Root = $Doc->getRoot();
		$List = $Root->getChildAt('QBXML/QBXMLMsgsRs/CustomerQueryRs');

		foreach ($List->children() as $Customer)
		{
			$arr = array(
				'ListID' => $Customer->getChildDataAt('CustomerRet ListID'),
				'EditSequence' => $Customer->getChildDataAt('CustomerRet EditSequence'),
				);



			foreach ($arr as $key => $value)
			{
				$arr[$key] = mysql_real_escape_string($value);
			}

			// Store the invoices in MySQL
			global $cssDB;
			$data['qbEditSequence'] = $arr['EditSequence'];
			$cssDB->query_update('users', $data, "id = " . $ID);
		}
	}

	return true;
}




/**
 * Handle a response from QuickBooks
 */
function _quickbooks_company_sequence_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{

	$errnum = 0;
	$errmsg = '';
	$Parser = new QuickBooks_XML_Parser($xml);
	if ($Doc = $Parser->parse($errnum, $errmsg))
	{
		$Root = $Doc->getRoot();
		$List = $Root->getChildAt('QBXML/QBXMLMsgsRs/CustomerQueryRs');

		foreach ($List->children() as $Customer)
		{
			$arr = array(
				'ListID' => $Customer->getChildDataAt('CustomerRet ListID'),
				'EditSequence' => $Customer->getChildDataAt('CustomerRet EditSequence'),
				);



			foreach ($arr as $key => $value)
			{
				$arr[$key] = mysql_real_escape_string($value);
			}

			// Store the invoices in MySQL
			global $cssDB;
			$data['qbEditSequence'] = $arr['EditSequence'];
			$cssDB->query_update('companies', $data, "id = " . $ID);
		}
	}

	return true;
}



/**
 * Handle a response from QuickBooks
 */
function _quickbooks_branch_sequence_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{

	$errnum = 0;
	$errmsg = '';
	$Parser = new QuickBooks_XML_Parser($xml);
	if ($Doc = $Parser->parse($errnum, $errmsg))
	{
		$Root = $Doc->getRoot();
		$List = $Root->getChildAt('QBXML/QBXMLMsgsRs/CustomerQueryRs');

		foreach ($List->children() as $Customer)
		{
			$arr = array(
				'ListID' => $Customer->getChildDataAt('CustomerRet ListID'),
				'EditSequence' => $Customer->getChildDataAt('CustomerRet EditSequence'),
				);



			foreach ($arr as $key => $value)
			{
				$arr[$key] = mysql_real_escape_string($value);
			}

			// Store the invoices in MySQL
			global $cssDB;
			$data['qbEditSequence'] = $arr['EditSequence'];
			$cssDB->query_update('branch_offices', $data, "id = " . $ID);
		}
	}

	return true;
}





function _quickbooks_customer_mod_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{
	// You'd probably do some database access here to pull the record with
	//	ID = $ID from your database and build a request to add that particular
	//	customer to QuickBooks.
	//
	// So, when you implement this for your business, you'd probably do
	//	something like this...:


	// Fetch your customer record from your database
	global $cssDB;
	$user = $cssDB->query_first("SELECT * FROM users WHERE id = " . $ID);
	$company = $cssDB->query_first("SELECT * FROM companies WHERE id = " . $user['companyID']);
	$branch = $cssDB->query_first("SELECT * FROM branch_offices WHERE id = " . $user['branchOfficeID']);
	// Create and return a qbXML request


	if (strlen($user['last_name'].', '.$user['first_name'].' ('.$user['id'].')') > 40)
	{
		$len = 40-strlen(' ('.$user['id'].')');
		$name_string = substr($user['last_name'].', '.$user['first_name'],0,$len).' ('.$user['id'].')';

	}
	else
	{
		$name_string = $user['last_name'].', '.$user['first_name'].' ('.$user['id'].')';
	}
		if(strlen($company['name']) > 40)
	{
		$company['name'] = substr($company['name'],0,40);

	}


	$qbxml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="2.0"?>
		<QBXML>
			<QBXMLMsgsRq onError="continueOnError">
				<CustomerModRq requestID="' . $requestID . '">
					<CustomerMod>
						<ListID>'.$user['qbListID'].'</ListID>
						<EditSequence>'.$user['qbEditSequence'].'</EditSequence>
						<Name>'.$name_string.'</Name>
						<FirstName>'.$user['first_name'].'</FirstName>
						<LastName>'.$user['last_name'].'</LastName>
						<BillAddress>
							<Addr1>'.$company['name'].'</Addr1>
							<Addr2>'.$branch['address'].'</Addr2>
							<City>'.$branch['city'].'</City>
							<State>'.$branch['state'].'</State>
							<PostalCode>'.$branch['zip_code'].'</PostalCode>
							<Country>United States</Country>
						</BillAddress>
						<Phone>'.$branch['business_number'].'</Phone>
						<Fax>'.$branch['fax_number'].'</Fax>
						<Email>'.$user['email'].'</Email>
						<Contact>'.$user['first_name'].' '.$user['last_name'].'</Contact>
					</CustomerMod>
				</CustomerModRq>
			</QBXMLMsgsRq>
		</QBXML>';



	return $qbxml;


}



/**
 * Receive a response from QuickBooks
 *
 * @param string $requestID					The requestID you passed to QuickBooks previously
 * @param string $action					The action that was performed (CustomerAdd in this case)
 * @param mixed $ID							The unique identifier of the record
 * @param array $extra
 * @param string $err						An error message, assign a valid to $err if you want to report an error
 * @param integer $last_action_time			A unix timestamp (seconds) indicating when the last action of this type was dequeued (i.e.: for CustomerAdd, the last time a customer was added, for CustomerQuery, the last time a CustomerQuery ran, etc.)
 * @param integer $last_actionident_time	A unix timestamp (seconds) indicating when the combination of this action and ident was dequeued (i.e.: when the last time a CustomerQuery with ident of get-new-customers was dequeued)
 * @param string $xml						The complete qbXML response
 * @param array $idents						An array of identifiers that are contained in the qbXML response
 * @return void
 */
function _quickbooks_customer_mod_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{
	// Great, customer $ID has been added to QuickBooks with a QuickBooks
	//	ListID value of: $idents['ListID']
	//

	global $cssDB;
	$data['qbListID'] = $idents['ListID'];
	$cssDB->query_update('users', $data, "id = " . $ID);

	/*
	mysql_query("UPDATE your_customer_table SET quickbooks_listid = '" . mysql_escape_string($idents['ListID']) . "' WHERE your_customer_ID_field = " . (int) $ID);
	*/
}




function _quickbooks_branch_mod_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{
	// You'd probably do some database access here to pull the record with
	//	ID = $ID from your database and build a request to add that particular
	//	customer to QuickBooks.
	//
	// So, when you implement this for your business, you'd probably do
	//	something like this...:


	// Fetch your customer record from your database
	global $cssDB;
	//$user = $cssDB->query_first("SELECT * FROM users WHERE id = " . $ID);

	$branch = $cssDB->query_first("SELECT * FROM branch_offices WHERE id = " . $ID);
	$company = $cssDB->query_first("SELECT * FROM companies WHERE id = " . $branch['companyID']);
	// Create and return a qbXML request


			if (strlen($branch['city'].', '.$branch['state'].' ('.$branch['id'].')') > 40)
	{
		$len = 40-strlen(' ('.$branch['id'].')');
		$name_string = substr($branch['city'].', '.$branch['state'],0,$len).' ('.$branch['id'].')';

	}
	else
	{
		$name_string = $branch['city'].', '.$branch['state'].' ('.$branch['id'].')';
	}

		if(strlen($company['name']) > 40)
	{
		$company['name'] = substr($company['name'],0,40);

	}

	$qbxml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="2.0"?>
		<QBXML>
			<QBXMLMsgsRq onError="continueOnError">
				<CustomerModRq requestID="' . $requestID . '">
					<CustomerMod>
						<ListID>'.$branch['qbListID'].'</ListID>
						<EditSequence>'.$branch['qbEditSequence'].'</EditSequence>
						<Name>'.$name_string.'</Name>
						<BillAddress>
							<Addr1>'.$company['name'].'</Addr1>
							<Addr2>'.$branch['address'].'</Addr2>
							<City>'.$branch['city'].'</City>
							<State>'.$branch['state'].'</State>
							<PostalCode>'.$branch['zip_code'].'</PostalCode>
							<Country>United States</Country>
						</BillAddress>
						<Phone>'.$branch['business_number'].'</Phone>
						<Fax>'.$branch['fax_number'].'</Fax>
					</CustomerMod>
				</CustomerModRq>
			</QBXMLMsgsRq>
		</QBXML>';



	return $qbxml;


}



/**
 * Receive a response from QuickBooks
 *
 * @param string $requestID					The requestID you passed to QuickBooks previously
 * @param string $action					The action that was performed (CustomerAdd in this case)
 * @param mixed $ID							The unique identifier of the record
 * @param array $extra
 * @param string $err						An error message, assign a valid to $err if you want to report an error
 * @param integer $last_action_time			A unix timestamp (seconds) indicating when the last action of this type was dequeued (i.e.: for CustomerAdd, the last time a customer was added, for CustomerQuery, the last time a CustomerQuery ran, etc.)
 * @param integer $last_actionident_time	A unix timestamp (seconds) indicating when the combination of this action and ident was dequeued (i.e.: when the last time a CustomerQuery with ident of get-new-customers was dequeued)
 * @param string $xml						The complete qbXML response
 * @param array $idents						An array of identifiers that are contained in the qbXML response
 * @return void
 */
function _quickbooks_branch_mod_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{
	// Great, customer $ID has been added to QuickBooks with a QuickBooks
	//	ListID value of: $idents['ListID']
	//

	global $cssDB;
	$data['qbListID'] = $idents['ListID'];
	$cssDB->query_update('branch_offices', $data, "id = " . $ID);

	/*
	mysql_query("UPDATE your_customer_table SET quickbooks_listid = '" . mysql_escape_string($idents['ListID']) . "' WHERE your_customer_ID_field = " . (int) $ID);
	*/
}




function _quickbooks_company_mod_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{
	// You'd probably do some database access here to pull the record with
	//	ID = $ID from your database and build a request to add that particular
	//	customer to QuickBooks.
	//
	// So, when you implement this for your business, you'd probably do
	//	something like this...:


	// Fetch your customer record from your database
	global $cssDB;
	//$user = $cssDB->query_first("SELECT * FROM users WHERE id = " . $ID);
	$company = $cssDB->query_first("SELECT * FROM companies WHERE id = " . $ID);
	//$branch = $cssDB->query_first("SELECT * FROM branch_offices WHERE id = " . $user['branchOfficeID']);
	// Create and return a qbXML request
		if (strlen($company['name'].' ('.$company['id'].')') > 40)
	{
		$len = 40-strlen(' ('.$company['id'].')');
		$name_string = substr($company['name'],0,$len).' ('.$company['id'].')';

	}
	else
	{
		$name_string = $company['name'].' ('.$company['id'].')';
	}

		if(strlen($company['name']) > 40)
	{
		$company['name'] = substr($company['name'],0,40);

	}

	$qbxml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="2.0"?>
		<QBXML>
			<QBXMLMsgsRq onError="continueOnError">
				<CustomerModRq requestID="' . $requestID . '">
					<CustomerMod>
						<ListID>'.$company['qbListID'].'</ListID>
						<EditSequence>'.$company['qbEditSequence'].'</EditSequence>
						<Name>'.$name_string.'</Name>
						<CompanyName>'.$company['name'].'</CompanyName>
					</CustomerMod>
				</CustomerModRq>
			</QBXMLMsgsRq>
		</QBXML>';



	return $qbxml;


}



/**
 * Receive a response from QuickBooks
 *
 * @param string $requestID					The requestID you passed to QuickBooks previously
 * @param string $action					The action that was performed (CustomerAdd in this case)
 * @param mixed $ID							The unique identifier of the record
 * @param array $extra
 * @param string $err						An error message, assign a valid to $err if you want to report an error
 * @param integer $last_action_time			A unix timestamp (seconds) indicating when the last action of this type was dequeued (i.e.: for CustomerAdd, the last time a customer was added, for CustomerQuery, the last time a CustomerQuery ran, etc.)
 * @param integer $last_actionident_time	A unix timestamp (seconds) indicating when the combination of this action and ident was dequeued (i.e.: when the last time a CustomerQuery with ident of get-new-customers was dequeued)
 * @param string $xml						The complete qbXML response
 * @param array $idents						An array of identifiers that are contained in the qbXML response
 * @return void
 */
function _quickbooks_company_mod_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{
	// Great, customer $ID has been added to QuickBooks with a QuickBooks
	//	ListID value of: $idents['ListID']
	//

	global $cssDB;
	$data['qbListID'] = $idents['ListID'];
	$cssDB->query_update('companies', $data, "id = " . $ID);

	/*
	mysql_query("UPDATE your_customer_table SET quickbooks_listid = '" . mysql_escape_string($idents['ListID']) . "' WHERE your_customer_ID_field = " . (int) $ID);
	*/
}


/*
 *
 * @param string $requestID					You should include this in your qbXML request (it helps with debugging later)
 * @param string $action					The QuickBooks action being performed (CustomerAdd in this case)
 * @param mixed $ID							The unique identifier for the record (maybe a customer ID number in your database or something)
 * @param array $extra						Any extra data you included with the queued item when you queued it up
 * @param string $err						An error message, assign a value to $err if you want to report an error
 * @param integer $last_action_time			A unix timestamp (seconds) indicating when the last action of this type was dequeued (i.e.: for CustomerAdd, the last time a customer was added, for CustomerQuery, the last time a CustomerQuery ran, etc.)
 * @param integer $last_actionident_time	A unix timestamp (seconds) indicating when the combination of this action and ident was dequeued (i.e.: when the last time a CustomerQuery with ident of get-new-customers was dequeued)
 * @param float $version					The max qbXML version your QuickBooks version supports
 * @param string $locale
 * @return string							A valid qbXML request
 */
function _quickbooks_vendor_add_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{
	// You'd probably do some database access here to pull the record with
	//	ID = $ID from your database and build a request to add that particular
	//	customer to QuickBooks.
	//
	// So, when you implement this for your business, you'd probably do
	//	something like this...:


	// Fetch your customer record from your database
	global $cssDB;
	$user = $cssDB->query_first("SELECT * FROM users WHERE id = " . $ID);


	if(empty($user['company_name']))
	{
		$company = $user['first_name'].' '.$user['last_name'];
	}
	else
		$company = $user['company_name'];


	if (strlen($user['first_name'].' '.$user['last_name'].' ('.$user['id'].')') > 40)
	{
		$len = 40-strlen(' ('.$user['id'].')');
		$name_string = substr($user['first_name'].' '.$user['last_name'],0,$len).' ('.$user['id'].')';

	}
	else
	{
		$name_string = $user['first_name'].' '.$user['last_name'].' ('.$user['id'].')';
	}

	if (strlen($company) > 40)
	{
		$company_name = substr($company,0,40);
	}
	else
	{
		$company_name = $company;
	}



	if (!empty($user['office_phone']) && !empty($user['home_phone']))
	{

			$phone = '<Phone>'.$user['office_phone'].'</Phone>
						<AltPhone>'.$user['home_phone'].'</AltPhone>';

	}
	elseif(!empty($user['office_phone']))
	{

			$phone = '<Phone>'.$user['office_phone'].'</Phone>';

	}
	elseif (!empty($user['home_phone']))
	{

			$phone = '<Phone>'.$user['home_phone'].'</Phone>';

	}
	else
	{
		$phone = '';
	}




		$name = $user['first_name'].' '.$user['last_name'];
	if (strlen($name) > 40)
	{
		$name = substr($name,0,40);
	}



	// Create and return a qbXML request
	$qbxml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="2.0"?>
		<QBXML>
			<QBXMLMsgsRq onError="continueOnError">
				<VendorAddRq requestID="' . $requestID . '">
					<VendorAdd>
						<Name>'.$name_string.'</Name>
						<CompanyName>'.$company_name.'</CompanyName>
						<FirstName>'.$user['first_name'].'</FirstName>
						<LastName>'.$user['last_name'].'</LastName>
						<VendorAddress>
							<Addr1>'.$company_name.'</Addr1>
							<Addr2>'.$user['payment_address'].'</Addr2>
							<City>'.$user['payment_city'].'</City>
							<State>'.$user['payment_state'].'</State>
							<PostalCode>'.$user['payment_zip_code'].'</PostalCode>
							<Country>United States</Country>
						</VendorAddress>
						'.$phone.'
						<Fax>'.$user['direct_fax'].'</Fax>
						<Email>'.$user['email'].'</Email>';
if(!empty($company_name)){
	$qbxml .= '						<Contact>'.$company_name.'</Contact>
							<NameOnCheck>'.$company_name.'</NameOnCheck>';
						}
else{
	$qbxml .= '						<Contact>'.$name.'</Contact>
							<NameOnCheck>'.$name.'</NameOnCheck>';
						}

$qbxml .= '						<VendorTypeRef>
							<FullName>Signer</FullName>
						</VendorTypeRef>
					</VendorAdd>
				</VendorAddRq>
			</QBXMLMsgsRq>
		</QBXML>';


		//file_put_contents('xml.txt', $qbxml);
		file_put_contents('VENDOR-ADD-OCT17.txt', $qbxml, FILE_APPEND);
	return $qbxml;


}



/**
 * Receive a response from QuickBooks
 *
 * @param string $requestID					The requestID you passed to QuickBooks previously
 * @param string $action					The action that was performed (CustomerAdd in this case)
 * @param mixed $ID							The unique identifier of the record
 * @param array $extra
 * @param string $err						An error message, assign a valid to $err if you want to report an error
 * @param integer $last_action_time			A unix timestamp (seconds) indicating when the last action of this type was dequeued (i.e.: for CustomerAdd, the last time a customer was added, for CustomerQuery, the last time a CustomerQuery ran, etc.)
 * @param integer $last_actionident_time	A unix timestamp (seconds) indicating when the combination of this action and ident was dequeued (i.e.: when the last time a CustomerQuery with ident of get-new-customers was dequeued)
 * @param string $xml						The complete qbXML response
 * @param array $idents						An array of identifiers that are contained in the qbXML response
 * @return void
 */
function _quickbooks_vendor_add_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{
	// Great, customer $ID has been added to QuickBooks with a QuickBooks
	//	ListID value of: $idents['ListID']
	//

	global $cssDB;
	$data['qbListID'] = $idents['ListID'];
	$cssDB->query_update('users', $data, "id = " . $ID);

	/*
	mysql_query("UPDATE your_customer_table SET quickbooks_listid = '" . mysql_escape_string($idents['ListID']) . "' WHERE your_customer_ID_field = " . (int) $ID);
	*/
}



function _quickbooks_vendor_mod_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{
	// You'd probably do some database access here to pull the record with
	//	ID = $ID from your database and build a request to add that particular
	//	customer to QuickBooks.
	//
	// So, when you implement this for your business, you'd probably do
	//	something like this...:


	// Fetch your customer record from your database
	global $cssDB;
	$user = $cssDB->query_first("SELECT * FROM users WHERE id = " . $ID);


	if(empty($user['company_name']))
	{
		$company = $user['first_name'].' '.$user['last_name'];
	}
	else
		$company = $user['company_name'];


	if (strlen($user['first_name'].' '.$user['last_name'].' ('.$user['id'].')') > 40)
	{
		$len = 40-strlen(' ('.$user['id'].')');
		$name_string = substr($user['first_name'].' '.$user['last_name'],0,$len).' ('.$user['id'].')';

	}
	else
	{
		$name_string = $user['first_name'].' '.$user['last_name'].' ('.$user['id'].')';
	}

	if (strlen($company) > 40)
	{
		$company_name = substr($company,0,40);
	}
	else
	{
		$company_name = $company;
	}



	if (!empty($user['office_phone']) && !empty($user['home_phone']))
	{

			$phone = '<Phone>'.$user['office_phone'].'</Phone>
						<AltPhone>'.$user['home_phone'].'</AltPhone>';

	}
	elseif(!empty($user['office_phone']))
	{

			$phone = '<Phone>'.$user['office_phone'].'</Phone>';

	}
	elseif (!empty($user['home_phone']))
	{

			$phone = '<Phone>'.$user['home_phone'].'</Phone>';

	}
	else
	{
		$phone = '';
	}


	$name = $user['first_name'].' '.$user['last_name'];
	if (strlen($name) > 40)
	{
		$name = substr($name,0,40);
	}



	// Create and return a qbXML request
	$qbxml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="2.0"?>
		<QBXML>
			<QBXMLMsgsRq onError="continueOnError">
				<VendorModRq requestID="' . $requestID . '">
					<VendorMod>
						<ListID>'.$user['qbListID'].'</ListID>
						<EditSequence>'.$user['qbEditSequence'].'</EditSequence>
						<Name>'.$name_string.'</Name>
						<CompanyName>'.$company_name.'</CompanyName>
						<FirstName>'.$user['first_name'].'</FirstName>
						<LastName>'.$user['last_name'].'</LastName>
						<VendorAddress>
							<Addr1>'.$company_name.'</Addr1>
							<Addr2>'.$user['payment_address'].'</Addr2>
							<City>'.$user['payment_city'].'</City>
							<State>'.$user['payment_state'].'</State>
							<PostalCode>'.$user['payment_zip_code'].'</PostalCode>
							<Country>United States</Country>
						</VendorAddress>
						'.$phone.'
						<Fax>'.$user['direct_fax'].'</Fax>
						<Email>'.$user['email'].'</Email>';
if(!empty($company_name)){
	$qbxml .= '						<Contact>'.$company_name.'</Contact>
							<NameOnCheck>'.$company_name.'</NameOnCheck>';
						}
else{
	$qbxml .= '						<Contact>'.$name.'</Contact>
							<NameOnCheck>'.$name.'</NameOnCheck>';
						}

$qbxml .= '					</VendorMod>
			</VendorModRq>
			</QBXMLMsgsRq>
		</QBXML>';

file_put_contents('VENDOR-MOD-OCT17.txt', $qbxml, FILE_APPEND);
		//file_put_contents('xml.txt', $qbxml);
	return $qbxml;


}



/**
 * Receive a response from QuickBooks
 *
 * @param string $requestID					The requestID you passed to QuickBooks previously
 * @param string $action					The action that was performed (CustomerAdd in this case)
 * @param mixed $ID							The unique identifier of the record
 * @param array $extra
 * @param string $err						An error message, assign a valid to $err if you want to report an error
 * @param integer $last_action_time			A unix timestamp (seconds) indicating when the last action of this type was dequeued (i.e.: for CustomerAdd, the last time a customer was added, for CustomerQuery, the last time a CustomerQuery ran, etc.)
 * @param integer $last_actionident_time	A unix timestamp (seconds) indicating when the combination of this action and ident was dequeued (i.e.: when the last time a CustomerQuery with ident of get-new-customers was dequeued)
 * @param string $xml						The complete qbXML response
 * @param array $idents						An array of identifiers that are contained in the qbXML response
 * @return void
 */
function _quickbooks_vendor_mod_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{
	// Great, customer $ID has been added to QuickBooks with a QuickBooks
	//	ListID value of: $idents['ListID']
	//

	global $cssDB;
	$data['qbListID'] = $idents['ListID'];
	$cssDB->query_update('users', $data, "id = " . $ID);

	/*
	mysql_query("UPDATE your_customer_table SET quickbooks_listid = '" . mysql_escape_string($idents['ListID']) . "' WHERE your_customer_ID_field = " . (int) $ID);
	*/
}



/**
 * Build a request to import customers already in QuickBooks into our application
 */
function _quickbooks_vendor_sequence_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{

	global $cssDB;
	$user = $cssDB->query_first("SELECT * FROM users WHERE id = " . $ID);

	// Iterator support (break the result set into small chunks)
	$attr_iteratorID = '';
	$attr_iterator = ' iterator="Start" ';

	// Build the request
	$xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
			<QBXMLMsgsRq onError="continueOnError">
				<VendorQueryRq>
					<ListID>'.$user['qbListID'].'</ListID>
				</VendorQueryRq>
			</QBXMLMsgsRq>
		</QBXML>';




		file_put_contents('VENDOR-SEQ-OCT17.txt', $xml, FILE_APPEND);
	return $xml;
}

/**
 * Handle a response from QuickBooks
 */
function _quickbooks_vendor_sequence_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{

	$errnum = 0;
	$errmsg = '';
	$Parser = new QuickBooks_XML_Parser($xml);
	if ($Doc = $Parser->parse($errnum, $errmsg))
	{
		$Root = $Doc->getRoot();
		$List = $Root->getChildAt('QBXML/QBXMLMsgsRs/VendorQueryRs');

		foreach ($List->children() as $Vendor)
		{
			$arr = array(
				'ListID' => $Vendor->getChildDataAt('VendorRet ListID'),
				'EditSequence' => $Vendor->getChildDataAt('VendorRet EditSequence'),
				);



			foreach ($arr as $key => $value)
			{
				$arr[$key] = mysql_real_escape_string($value);
			}

			// Store the invoices in MySQL
			global $cssDB;
			$data['qbEditSequence'] = $arr['EditSequence'];
			$cssDB->query_update('users', $data, "id = " . $ID);
		}
	}

	return true;
}




/*
 *
 * @param string $requestID					You should include this in your qbXML request (it helps with debugging later)
 * @param string $action					The QuickBooks action being performed (CustomerAdd in this case)
 * @param mixed $ID							The unique identifier for the record (maybe a customer ID number in your database or something)
 * @param array $extra						Any extra data you included with the queued item when you queued it up
 * @param string $err						An error message, assign a value to $err if you want to report an error
 * @param integer $last_action_time			A unix timestamp (seconds) indicating when the last action of this type was dequeued (i.e.: for CustomerAdd, the last time a customer was added, for CustomerQuery, the last time a CustomerQuery ran, etc.)
 * @param integer $last_actionident_time	A unix timestamp (seconds) indicating when the combination of this action and ident was dequeued (i.e.: when the last time a CustomerQuery with ident of get-new-customers was dequeued)
 * @param float $version					The max qbXML version your QuickBooks version supports
 * @param string $locale
 * @return string							A valid qbXML request
 */
function _quickbooks_invoice_add_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{
	// You'd probably do some database access here to pull the record with
	//	ID = $ID from your database and build a request to add that particular
	//	customer to QuickBooks.
	//
	// So, when you implement this for your business, you'd probably do
	//	something like this...:


	// Fetch your customer record from your database
	global $cssDB;
	$order = $cssDB->query_first("SELECT * FROM orders WHERE id = " . $ID);
	$client = $cssDB->query_first("SELECT * FROM users WHERE id = " . $order['clientID']);
	//$notary = $cssDB->query_first("SELECT * FROM users WHERE id = " . $order['notaryID']);




	$line_items = '';

	if($order['client_double_pkg_fee_used'] != 'NO')
	{
		$line_items .= '		<InvoiceLineAdd>
							<ItemRef>
								<FullName>Double Package Fee</FullName>
							</ItemRef>
							<Desc>Double Package Fee</Desc>
							<Quantity>1.00000</Quantity>
							<Rate>'.$order['client_double_pkg_fee'].'000</Rate>
						</InvoiceLineAdd>';

	}

	if($order['client_email_fax_fee_used'] != 'NO')
	{
		$line_items .= '		<InvoiceLineAdd>
							<ItemRef>
								<FullName>Email/Fax Fee</FullName>
							</ItemRef>
							<Desc>Email/Fax Fee</Desc>
							<Quantity>1.00000</Quantity>
							<Rate>'.$order['client_email_fax_fee'].'000</Rate>
						</InvoiceLineAdd>';

	}

	if($order['client_cancellation_trip_fee_used'] != 'NO')
	{
		$line_items .= '		<InvoiceLineAdd>
							<ItemRef>
								<FullName>Cancellation Trip Fee</FullName>
							</ItemRef>
							<Desc>Cancellation Trip Fee</Desc>
							<Quantity>1.00000</Quantity>
							<Rate>'.$order['client_cancellation_trip_fee'].'000</Rate>
						</InvoiceLineAdd>';

	}


	if($order['client_other_fee_used'] != 'NO')
	{
		$line_items .= '		<InvoiceLineAdd>
							<ItemRef>
								<FullName>Other Fees</FullName>
							</ItemRef>
							<Desc>'.$order['client_other_fee_description'].'</Desc>
							<Quantity>1.00000</Quantity>
							<Rate>'.$order['client_other_fee'].'000</Rate>
						</InvoiceLineAdd>';

	}


	// Create and return a qbXML request
	$qbxml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="2.0"?>
		<QBXML>
			<QBXMLMsgsRq onError="continueOnError">
				<InvoiceAddRq requestID="' . $requestID . '">
					<InvoiceAdd>
						<CustomerRef>
							<ListID>'.$client['qbListID'].'</ListID>
						</CustomerRef>
						<TxnDate>'.substr($order['signing_completed_date'],0,10).'</TxnDate>
						<RefNumber>'.$order['id'].'</RefNumber>
						<BillAddress>
							<Addr1>'.$order['billing_party_company'].'</Addr1>
							<Addr2>'.$order['billing_party_address'].'</Addr2>
							<City>'.$order['billing_party_city'].'</City>
							<State>'.$order['billing_party_state'].'</State>
							<PostalCode>'.$order['billing_party_zip_code'].'</PostalCode>
							<Country>United States</Country>
						</BillAddress>
						<PONumber>'.$order['client_reference_number'].'</PONumber>
						<TermsRef>
							<FullName>NET Due</FullName>
						</TermsRef>
						<Memo>Signee: '.$order['signer_1_first_name'].' '.$order['signer_1_last_name'].'</Memo>
						<InvoiceLineAdd>
							<ItemRef>
								<FullName>Base Fee</FullName>
							</ItemRef>
							<Desc>Base Fee</Desc>
							<Quantity>1.00000</Quantity>
							<Rate>'.$order['client_fee_base'].'000</Rate>
						</InvoiceLineAdd>
						'.$line_items.'
					</InvoiceAdd>
				</InvoiceAddRq>
			</QBXMLMsgsRq>
		</QBXML>';

	//file_put_contents('xml.txt', $qbxml);
	file_put_contents('INVOICE-ADD-OCT17.txt', $qbxml, FILE_APPEND);
	return $qbxml;


}



/**
 * Receive a response from QuickBooks
 *
 * @param string $requestID					The requestID you passed to QuickBooks previously
 * @param string $action					The action that was performed (CustomerAdd in this case)
 * @param mixed $ID							The unique identifier of the record
 * @param array $extra
 * @param string $err						An error message, assign a valid to $err if you want to report an error
 * @param integer $last_action_time			A unix timestamp (seconds) indicating when the last action of this type was dequeued (i.e.: for CustomerAdd, the last time a customer was added, for CustomerQuery, the last time a CustomerQuery ran, etc.)
 * @param integer $last_actionident_time	A unix timestamp (seconds) indicating when the combination of this action and ident was dequeued (i.e.: when the last time a CustomerQuery with ident of get-new-customers was dequeued)
 * @param string $xml						The complete qbXML response
 * @param array $idents						An array of identifiers that are contained in the qbXML response
 * @return void
 */
function _quickbooks_invoice_add_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{
	// Great, customer $ID has been added to QuickBooks with a QuickBooks
	//	ListID value of: $idents['ListID']
	//
	global $cssDB;
	$data['qbInvoiceListID'] = $idents['ListID'];
	$data['qbInvoiceTxnID'] = $idents['TxnID'];
	$cssDB->query_update('orders', $data, "id = " . $ID);


	/*
	mysql_query("UPDATE your_customer_table SET quickbooks_listid = '" . mysql_escape_string($idents['ListID']) . "' WHERE your_customer_ID_field = " . (int) $ID);
	*/
}



function _quickbooks_invoice_void_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{

	global $cssDB;
	$order = $cssDB->query_first("SELECT * FROM orders WHERE id = " . $ID);

	// Iterator support (break the result set into small chunks)
	$attr_iteratorID = '';
	$attr_iterator = ' iterator="Start" ';

	// Build the request
	$xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
			<QBXMLMsgsRq onError="continueOnError">
				<TxnVoidRq>
					<TxnVoidType>Invoice</TxnVoidType>
					<TxnID>'.$order['qbInvoiceTxnID'].'</TxnID>
				</TxnVoidRq>
			</QBXMLMsgsRq>
		</QBXML>';




	//file_put_contents('xml.txt', $xml);
	return $xml;
}

function _quickbooks_invoice_void_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{

}

function _quickbooks_bill_void_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{

	global $cssDB;
	$order = $cssDB->query_first("SELECT * FROM orders WHERE id = " . $ID);

	// Iterator support (break the result set into small chunks)
	$attr_iteratorID = '';
	$attr_iterator = ' iterator="Start" ';

	// Build the request
	$xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
			<QBXMLMsgsRq onError="continueOnError">
				<TxnVoidRq>
					<TxnVoidType>Bill</TxnVoidType>
					<TxnID>'.$order['qbVendorTxnID'].'</TxnID>
				</TxnVoidRq>
			</QBXMLMsgsRq>
		</QBXML>';




	//file_put_contents('xml.txt', $xml);
	return $xml;
}

function _quickbooks_bill_void_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{

}






/*
 *
 * @param string $requestID					You should include this in your qbXML request (it helps with debugging later)
 * @param string $action					The QuickBooks action being performed (CustomerAdd in this case)
 * @param mixed $ID							The unique identifier for the record (maybe a customer ID number in your database or something)
 * @param array $extra						Any extra data you included with the queued item when you queued it up
 * @param string $err						An error message, assign a value to $err if you want to report an error
 * @param integer $last_action_time			A unix timestamp (seconds) indicating when the last action of this type was dequeued (i.e.: for CustomerAdd, the last time a customer was added, for CustomerQuery, the last time a CustomerQuery ran, etc.)
 * @param integer $last_actionident_time	A unix timestamp (seconds) indicating when the combination of this action and ident was dequeued (i.e.: when the last time a CustomerQuery with ident of get-new-customers was dequeued)
 * @param float $version					The max qbXML version your QuickBooks version supports
 * @param string $locale
 * @return string							A valid qbXML request
 */
function _quickbooks_bill_add_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{
	// You'd probably do some database access here to pull the record with
	//	ID = $ID from your database and build a request to add that particular
	//	customer to QuickBooks.
	//
	// So, when you implement this for your business, you'd probably do
	//	something like this...:

	if (!$line_items) {
		$line_items = '';
	}

	// Fetch your customer record from your database
	global $cssDB;
	$order = $cssDB->query_first("SELECT * FROM orders WHERE id = " . $ID);
	//$client = $cssDB->query_first("SELECT * FROM users WHERE id = " . $order['clientID']);
	$notary = $cssDB->query_first("SELECT * FROM users WHERE id = " . $order['notaryID']);


	// Create and return a qbXML request
	$qbxml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="2.0"?>
		<QBXML>
			<QBXMLMsgsRq onError="continueOnError">
				<BillAddRq requestID="' . $requestID . '">
					<BillAdd>
						<VendorRef>
							<ListID>'.$notary['qbListID'].'</ListID>
						</VendorRef>
						<APAccountRef>
							<FullName>Accounts Payable</FullName>
						</APAccountRef>
						<TxnDate>'.substr($order['signing_completed_date'],0,10).'</TxnDate>
						<RefNumber>'.$order['id'].'</RefNumber>
						<TermsRef>
							<FullName>NET Due</FullName>
						</TermsRef>
						<ItemLineAdd>
							<ItemRef>
								<FullName>Agent Payment</FullName>
							</ItemRef>
							<Desc>Agent Payment</Desc>
							<Quantity>1.00000</Quantity>
							<Cost>'.$order['agent_payment'].'000</Cost>
							<Amount>'.$order['agent_payment'].'</Amount>
						</ItemLineAdd>
						'.$line_items.'
					</BillAdd>
				</BillAddRq>
			</QBXMLMsgsRq>
		</QBXML>';

	//file_put_contents('xml.txt', $qbxml);
	file_put_contents('BILL-ADD-OCT17.txt', $qbxml, FILE_APPEND);

	return $qbxml;


}



/**
 * Receive a response from QuickBooks
 *
 * @param string $requestID					The requestID you passed to QuickBooks previously
 * @param string $action					The action that was performed (CustomerAdd in this case)
 * @param mixed $ID							The unique identifier of the record
 * @param array $extra
 * @param string $err						An error message, assign a valid to $err if you want to report an error
 * @param integer $last_action_time			A unix timestamp (seconds) indicating when the last action of this type was dequeued (i.e.: for CustomerAdd, the last time a customer was added, for CustomerQuery, the last time a CustomerQuery ran, etc.)
 * @param integer $last_actionident_time	A unix timestamp (seconds) indicating when the combination of this action and ident was dequeued (i.e.: when the last time a CustomerQuery with ident of get-new-customers was dequeued)
 * @param string $xml						The complete qbXML response
 * @param array $idents						An array of identifiers that are contained in the qbXML response
 * @return void
 */
function _quickbooks_bill_add_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{
	// Great, customer $ID has been added to QuickBooks with a QuickBooks
	//	ListID value of: $idents['ListID']
	//
	global $cssDB;
	//$data['qbVendorListID'] = $idents['ListID'];
	$data['qbVendorTxnID'] = $idents['TxnID'];
	$cssDB->query_update('orders', $data, "id = " . $ID);


	/*
	mysql_query("UPDATE your_customer_table SET quickbooks_listid = '" . mysql_escape_string($idents['ListID']) . "' WHERE your_customer_ID_field = " . (int) $ID);
	*/
}







/**
 * Get the last date/time the QuickBooks sync ran
 *
 * @param string $user		The web connector username
 * @return string			A date/time in this format: "yyyy-mm-dd hh:ii:ss"
 */
function _quickbooks_get_last_run($user, $action)
{
	$type = null;
	$opts = null;
	return QuickBooks_Utilities::configRead(QB_QUICKBOOKS_DSN, $user, md5(__FILE__), QB_QUICKBOOKS_CONFIG_LAST . '-' . $action, $type, $opts);
}

/**
 * Set the last date/time the QuickBooks sync ran to NOW
 *
 * @param string $user
 * @return boolean
 */
function _quickbooks_set_last_run($user, $action, $force = null)
{
	$value = date('Y-m-d') . 'T' . date('H:i:s');

	if ($force)
	{
		$value = date('Y-m-d', strtotime($force)) . 'T' . date('H:i:s', strtotime($force));
	}

	return QuickBooks_Utilities::configWrite(QB_QUICKBOOKS_DSN, $user, md5(__FILE__), QB_QUICKBOOKS_CONFIG_LAST . '-' . $action, $value);
}

/**
 *
 *
 */
function _quickbooks_get_current_run($user, $action)
{
	$type = null;
	$opts = null;
	return QuickBooks_Utilities::configRead(QB_QUICKBOOKS_DSN, $user, md5(__FILE__), QB_QUICKBOOKS_CONFIG_CURR . '-' . $action, $type, $opts);
}

/**
 *
 *
 */
function _quickbooks_set_current_run($user, $action, $force = null)
{
	$value = date('Y-m-d') . 'T' . date('H:i:s');

	if ($force)
	{
		$value = date('Y-m-d', strtotime($force)) . 'T' . date('H:i:s', strtotime($force));
	}

	return QuickBooks_Utilities::configWrite(QB_QUICKBOOKS_DSN, $user, md5(__FILE__), QB_QUICKBOOKS_CONFIG_CURR . '-' . $action, $value);
}

/**
 * Build a request to import invoices already in QuickBooks into our application
 */
function _quickbooks_invoice_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{
	// Iterator support (break the result set into small chunks)
	$attr_iteratorID = '';
	$attr_iterator = ' iterator="Start" ';
	if (empty($extra['iteratorID']))
	{
		// This is the first request in a new batch
		$last = _quickbooks_get_last_run($user, $action);
		_quickbooks_set_last_run($user, $action);			// Update the last run time to NOW()

		// Set the current run to $last
		_quickbooks_set_current_run($user, $action, $last);
	}
	else
	{
		// This is a continuation of a batch
		$attr_iteratorID = ' iteratorID="' . $extra['iteratorID'] . '" ';
		$attr_iterator = ' iterator="Continue" ';

		$last = _quickbooks_get_current_run($user, $action);
	}

	// Build the request
	$xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
			<QBXMLMsgsRq onError="continueOnError">
				<InvoiceQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . '>
					<MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
					<ModifiedDateRangeFilter>
						<FromModifiedDate>' . $last . '</FromModifiedDate>
					</ModifiedDateRangeFilter>
					<IncludeLineItems>true</IncludeLineItems>
					<OwnerID>0</OwnerID>
				</InvoiceQueryRq>
			</QBXMLMsgsRq>
		</QBXML>';
		file_put_contents('invoice-xml.txt', $xml, FILE_APPEND);
	return $xml;
}

/**
 * Handle a response from QuickBooks
 */
function _quickbooks_invoice_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{
	if (!empty($idents['iteratorRemainingCount']))
	{
		// Queue up another request

		$Queue = QuickBooks_Queue_Singleton::getInstance();
		$Queue->enqueue('ImportInvoices', null, QB_PRIORITY_INVOICE, array( 'iteratorID' => $idents['iteratorID'] ));
	}

	// This piece of the response from QuickBooks is now stored in $xml. You
	//	can process the qbXML response in $xml in any way you like. Save it to
	//	a file, stuff it in a database, parse it and stuff the records in a
	//	database, etc. etc. etc.
	//
	// The following example shows how to use the built-in XML parser to parse
	//	the response and stuff it into a database.

	// Import all of the records
	$errnum = 0;
	$errmsg = '';
	$Parser = new QuickBooks_XML_Parser($xml);
	if ($Doc = $Parser->parse($errnum, $errmsg))
	{
		$Root = $Doc->getRoot();
		$List = $Root->getChildAt('QBXML/QBXMLMsgsRs/InvoiceQueryRs');

		foreach ($List->children() as $Invoice)
		{
			$arr = array(
				'TxnID' => $Invoice->getChildDataAt('InvoiceRet TxnID'),
				'TimeCreated' => $Invoice->getChildDataAt('InvoiceRet TimeCreated'),
				'TimeModified' => $Invoice->getChildDataAt('InvoiceRet TimeModified'),
				'RefNumber' => $Invoice->getChildDataAt('InvoiceRet RefNumber'),
				'Customer_ListID' => $Invoice->getChildDataAt('InvoiceRet CustomerRef ListID'),
				'Customer_FullName' => $Invoice->getChildDataAt('InvoiceRet CustomerRef FullName'),
				'ShipAddress_Addr1' => $Invoice->getChildDataAt('InvoiceRet ShipAddress Addr1'),
				'ShipAddress_Addr2' => $Invoice->getChildDataAt('InvoiceRet ShipAddress Addr2'),
				'ShipAddress_City' => $Invoice->getChildDataAt('InvoiceRet ShipAddress City'),
				'ShipAddress_State' => $Invoice->getChildDataAt('InvoiceRet ShipAddress State'),
				'ShipAddress_PostalCode' => $Invoice->getChildDataAt('InvoiceRet ShipAddress PostalCode'),
				'BalanceRemaining' => $Invoice->getChildDataAt('InvoiceRet BalanceRemaining'),
				);

			//QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Importing invoice #' . $arr['RefNumber'] . ': ' . print_r($arr, true));

			foreach ($arr as $key => $value)
			{
				$arr[$key] = mysql_real_escape_string($value);
				//$export .= $key . ' => ' . $value ."\r\n";
			}
			//file_put_contents('invoices.txt', $export);

			// Store the invoices in MySQL
			global $cssDB;


			$order = $cssDB->query_first("SELECT * FROM orders WHERE qbInvoiceTxnID = '" . $arr['TxnID'] . "'");


			if(!empty($order))
			{



				$fee = $order['client_fee_base'];

				if($order['client_double_pkg_fee_used'] != 'NO')
					$fee = $fee+$order['client_double_pkg_fee'];
				if($order['client_email_fax_fee_used'] != 'NO')
					$fee = $fee+$order['client_email_fax_fee'];
				if($order['client_cancellation_trip_fee_used'] != 'NO')
					$fee = $fee+$order['client_cancellation_trip_fee'];
				if($order['client_other_fee_used'] != 'NO')
					$fee = $fee+$order['client_other_fee'];

				$data['total_client_invoice_payment'] = $fee - $arr['BalanceRemaining'];

				//$export = $fee .'-'. $arr['BalanceRemaining'];
				//file_put_contents('fee.txt', $export);


				if($order['total_client_invoice_payment'] != $data['total_client_invoice_payment'])
				{
				//payment has been applied
					$log_data = array();
					$log_data['is_public'] = 'NO';
					$log_data['orderID'] = $order['id'];
					$log_data['userID'] = '1';
					$log_data['comments'] = 'Order updated by Quickbooks';
					$log_data['status_changed_from'] = $order['status'];
					if ($arr['BalanceRemaining'] > 0)
						{$log_data['status_changed_to'] = '256';
						$data['status'] = '256';
						}
					else
						{$log_data['status_changed_to'] = '512';
						$data['status'] = '512';
						}

					$cssDB->query_update('orders', $data, "qbInvoiceTxnID = '" . $arr['TxnID'] . "'");
					$cssDB->query_insert('order_log', $log_data);
				}



			}


			// Process the line items
			/*foreach ($Invoice->children() as $Child)
			{
				if ($Child->name() == 'InvoiceLineRet')
				{
					$InvoiceLine = $Child;

					$lineitem = array(
						'TxnID' => $arr['TxnID'],
						'TxnLineID' => $InvoiceLine->getChildDataAt('InvoiceLineRet TxnLineID'),
						'Item_ListID' => $InvoiceLine->getChildDataAt('InvoiceLineRet ItemRef ListID'),
						'Item_FullName' => $InvoiceLine->getChildDataAt('InvoiceLineRet ItemRef FullName'),
						'Descrip' => $InvoiceLine->getChildDataAt('InvoiceLineRet Desc'),
						'Quantity' => $InvoiceLine->getChildDataAt('InvoiceLineRet Quantity'),
						'Rate' => $InvoiceLine->getChildDataAt('InvoiceLineRet Rate'),
						);

					foreach ($lineitem as $key => $value)
					{
						$lineitem[$key] = mysql_real_escape_string($value);
					}

					// Store the lineitems in MySQL
					mysql_query("
						INSERT INTO
							qb_example_invoice_lineitem
						(
							" . implode(", ", array_keys($lineitem)) . "
						) VALUES (
							'" . implode("', '", array_values($lineitem)) . "'
						) ") or die(trigger_error(mysql_error()));
				}
			}*/
		}
	}

	return true;
}




/**
 * Build a request to import invoices already in QuickBooks into our application
 */
function _quickbooks_bill_import_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{
	// Iterator support (break the result set into small chunks)
	$attr_iteratorID = '';
	$attr_iterator = ' iterator="Start" ';
	if (empty($extra['iteratorID']))
	{
		// This is the first request in a new batch
		$last = _quickbooks_get_last_run($user, $action);
		_quickbooks_set_last_run($user, $action);			// Update the last run time to NOW()

		// Set the current run to $last
		_quickbooks_set_current_run($user, $action, $last);
	}
	else
	{
		// This is a continuation of a batch
		$attr_iteratorID = ' iteratorID="' . $extra['iteratorID'] . '" ';
		$attr_iterator = ' iterator="Continue" ';

		$last = _quickbooks_get_current_run($user, $action);
	}

	// Build the request
	$xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="' . $version . '"?>
		<QBXML>
			<QBXMLMsgsRq onError="continueOnError">
				<BillQueryRq ' . $attr_iterator . ' ' . $attr_iteratorID . '>
					<MaxReturned>' . QB_QUICKBOOKS_MAX_RETURNED . '</MaxReturned>
					<ModifiedDateRangeFilter>
						<FromModifiedDate>' . $last . '</FromModifiedDate>
					</ModifiedDateRangeFilter>
					<IncludeLineItems>true</IncludeLineItems>
					<OwnerID>0</OwnerID>
				</BillQueryRq>
			</QBXMLMsgsRq>
		</QBXML>';
		//file_put_contents('xml.txt', $xml);

	return $xml;
}

/**
 * Handle a response from QuickBooks
 */
function _quickbooks_bill_import_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{
	if (!empty($idents['iteratorRemainingCount']))
	{
		// Queue up another request

		$Queue = QuickBooks_Queue_Singleton::getInstance();
		$Queue->enqueue('ImportBills', null, QB_PRIORITY_INVOICE, array( 'iteratorID' => $idents['iteratorID'] ));
	}

	// This piece of the response from QuickBooks is now stored in $xml. You
	//	can process the qbXML response in $xml in any way you like. Save it to
	//	a file, stuff it in a database, parse it and stuff the records in a
	//	database, etc. etc. etc.
	//
	// The following example shows how to use the built-in XML parser to parse
	//	the response and stuff it into a database.

	// Import all of the records
	$errnum = 0;
	$errmsg = '';
	$Parser = new QuickBooks_XML_Parser($xml);
	if ($Doc = $Parser->parse($errnum, $errmsg))
	{
		$Root = $Doc->getRoot();
		$List = $Root->getChildAt('QBXML/QBXMLMsgsRs/BillQueryRs');

		foreach ($List->children() as $Bill)
		{
			$arr = array(
				'TxnID' => $Bill->getChildDataAt('BillRet TxnID'),
				'AmountDue' => $Bill->getChildDataAt('BillRet AmountDue'),
				'IsPaid' => $Bill->getChildDataAt('BillRet IsPaid')
				);

			//QuickBooks_Utilities::log(QB_QUICKBOOKS_DSN, 'Importing bill #' . $arr['TxnID'] . ': ' . print_r($arr, true));

			foreach ($arr as $key => $value)
			{
				$arr[$key] = mysql_real_escape_string($value);
				//$export .= $key . ' => ' . $value ."\r\n";
			}
			//file_put_contents('bills.txt', $export);

			// Store the invoices in MySQL
			global $cssDB;


			$order = $cssDB->query_first("SELECT * FROM orders WHERE qbVendorTxnID = '" . $arr['TxnID'] . "'");


			if(!empty($order))
			{



				if($order['notary_payment_status'] != 16)
				{
					if($arr['IsPaid'] == 'true')
					{
						//payment has been applied
						/*$log_data = array();
						$log_data['is_public'] = 'NO';
						$log_data['orderID'] = $order['id'];
						$log_data['userID'] = '1';
						$log_data['comments'] = 'Order updated by Quickbooks';
						$log_data['status_changed_from'] = $order['status'];
						if ($arr['BalanceRemaining'] > 0)
							{$log_data['status_changed_to'] = '256';
							$data['status'] = '256';
							}
						else
							{$log_data['status_changed_to'] = '512';
							$data['status'] = '512';
							}*/

						$data['notary_payment_status'] = '16';
						$data['dateNotaryPaid'] = "NOW()";
						$cssDB->query_update('orders', $data, "qbVendorTxnID = '" . $arr['TxnID'] . "'");
						//$cssDB->query_insert('order_log', $log_data);
					}
				}



			}


			// Process the line items
			/*foreach ($Invoice->children() as $Child)
			{
				if ($Child->name() == 'InvoiceLineRet')
				{
					$InvoiceLine = $Child;

					$lineitem = array(
						'TxnID' => $arr['TxnID'],
						'TxnLineID' => $InvoiceLine->getChildDataAt('InvoiceLineRet TxnLineID'),
						'Item_ListID' => $InvoiceLine->getChildDataAt('InvoiceLineRet ItemRef ListID'),
						'Item_FullName' => $InvoiceLine->getChildDataAt('InvoiceLineRet ItemRef FullName'),
						'Descrip' => $InvoiceLine->getChildDataAt('InvoiceLineRet Desc'),
						'Quantity' => $InvoiceLine->getChildDataAt('InvoiceLineRet Quantity'),
						'Rate' => $InvoiceLine->getChildDataAt('InvoiceLineRet Rate'),
						);

					foreach ($lineitem as $key => $value)
					{
						$lineitem[$key] = mysql_real_escape_string($value);
					}

					// Store the lineitems in MySQL
					mysql_query("
						INSERT INTO
							qb_example_invoice_lineitem
						(
							" . implode(", ", array_keys($lineitem)) . "
						) VALUES (
							'" . implode("', '", array_values($lineitem)) . "'
						) ") or die(trigger_error(mysql_error()));
				}
			}*/
		}
	}

	return true;
}














function _quickbooks_error_handler_0x80040400($requestID, $user, $action, $ident, $extra, &$err, $xml, $errnum, $errmsg)
{
	return _quickbooks_error_handler(__FUNCTION__, $requestID, $user, $action, $ident, $extra, $err, $xml, $errnum, $errmsg);
}

function _quickbooks_error_handler_3070($requestID, $user, $action, $ident, $extra, &$err, $xml, $errnum, $errmsg)
{
	return _quickbooks_error_handler(__FUNCTION__, $requestID, $user, $action, $ident, $extra, $err, $xml, $errnum, $errmsg);
}

function _quickbooks_error_handler_catchall($requestID, $user, $action, $ident, $extra, &$err, $xml, $errnum, $errmsg)
{
	return _quickbooks_error_handler(__FUNCTION__, $requestID, $user, $action, $ident, $extra, $err, $xml, $errnum, $errmsg);
}

function _quickbooks_error_handler_no_error($requestID, $user, $action, $ident, $extra, &$err, $xml, $errnum, $errmsg)
{
	file_put_contents('FULL-LOG-OCT17.txt', $xml, FILE_APPEND);
	return true;
}

function _quickbooks_error_handler($handler, $user, $requestID, $action, $ident, $extra, &$err, $xml, $errnum, $errmsg)
{

	$message = '';
	$message .= 'The error handler callback function that caught this error is: ' . $handler . "\n";
	$message .= 'Action: ' . $action . "\n";
	$message .= 'Ident: ' . $ident . "\n";
	$message .= 'Date/Time: ' . date('Y-m-d H:i:s') . "\n";
	$message .= 'Error Num.: ' . $errnum . "\n";
	$message .= 'Error Message: ' . $errmsg . "\n";
	$message .= "\n";
	$message .= $xml;
	file_put_contents('errors.txt', $message);


	return true;
}

//$out = ob_get_contents();

//file_put_contents('output.txt', $out, FILE_APPEND);