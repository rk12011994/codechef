<?php

//include_once 'config.php';

/**
 * Example QuickBooks Web Connector web service with custom authentication
 * 
 * This example shows how to use a custom authentication function to 
 * 
 * @author Keith Palmer <keith@consolibyte.com>
 * 
 * @package QuickBooks
 * @subpackage Documentation
 */

// I always program in E_STRICT error mode... 
error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', true);

// We need to make sure the correct timezone is set, or some PHP installations will complain
if (function_exists('date_default_timezone_set'))
{
	// List of valid timezones is here: http://us3.php.net/manual/en/timezones.php
	date_default_timezone_set('America/New_York');
}

// Require the framework
//require_once '../QuickBooks.php';
require_once 'QuickBooks.php';

// A username and password you'll use in: 
//	a) Your .QWC file
//	b) The Web Connector
//	c) The QuickBooks framework
$user = 'quickbooks';
$pass = 'admin@123#';

// Map QuickBooks actions to handler functions
$map = array(
	'CompanyAdd' => array( '_quickbooks_company_add_request', '_quickbooks_company_add_response' ),
	'BranchAdd' => array( '_quickbooks_branch_add_request', '_quickbooks_branch_add_response' ),
	/*'CustomerAdd' => array( '_quickbooks_customer_add_request', '_quickbooks_customer_add_response' ),
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
	'SyncVendors' => array( '_quickbooks_sync_vendors_request', '_quickbooks_sync_vendors_response' )*/
	// ... more action handlers here ...
	);

// This is entirely optional, use it to trigger actions when an error is returned by QuickBooks
$errmap = array();

// An array of callback hooks
$hooks = array();

// Logging level
$log_level = QUICKBOOKS_LOG_DEVELOP;		// Use this level until you're sure everything works!!!

// SOAP backend
$soap = QUICKBOOKS_SOAPSERVER_BUILTIN;

// SOAP options
$soap_options = array();

// * MAKE SURE YOU CHANGE THE DATABASE CONNECTION STRING BELOW TO A VALID MYSQL USERNAME/PASSWORD/HOSTNAME *
$dsn = 'mysqli://centralsign:centralsign@localhost/dbinitialhere';

// Handler options
$handler_options = array(
	'authenticate' => '_quickbooks_custom_auth', 
	//'authenticate' => '_QuickBooksClass::theStaticMethod',
	'deny_concurrent_logins' => false, 
	);

if (!QuickBooks_Utilities::initialized($dsn))
{
	// Initialize creates the neccessary database schema for queueing up requests and logging
	QuickBooks_Utilities::initialize($dsn);
	
	// This creates a username and password which is used by the Web Connector to authenticate
	QuickBooks_Utilities::createUser($dsn, $user, $pass);
	
	// Queueing up a test request
	$primary_key_of_your_customer = 5;
	$Queue = new QuickBooks_WebConnector_Queue($dsn);
	$Queue->enqueue('CompanyAdd', $primary_key_of_your_customer);
}
//$primary_key_of_your_customer = 5;
	//$Queue = new QuickBooks_WebConnector_Queue($dsn);
	//$Queue->enqueue('CompanyAdd', $primary_key_of_your_customer);
QuickBooks_WebConnector_Queue_Singleton::initialize($dsn);
// Create a new server and tell it to handle the requests
// __construct($dsn_or_conn, $map, $errmap = array(), $hooks = array(), $log_level = QUICKBOOKS_LOG_NORMAL, $soap = QUICKBOOKS_SOAPSERVER_PHP, $wsdl = QUICKBOOKS_WSDL, $soap_options = array(), $handler_options = array(), $driver_options = array(), $callback_options = array()
$Server = new QuickBooks_WebConnector_Server($dsn, $map, $errmap, $hooks, $log_level, $soap, QUICKBOOKS_WSDL, $soap_options, $handler_options);
$response = $Server->handle(true, true);

/**
 * Authenticate a Web Connector session
 */
function _quickbooks_custom_auth($username, $password, &$qb_company_file)
{
	if ($username == 'quickbooks' and 
		$password == 'admin@123#')
	{
		// Use this company file and auth successfully
		$qb_company_file = 'C:\Users\Public\Documents\Intuit\QuickBooks\Company Files\Central Signing Services, Inc..QBW';
		
		return true;
	}
	
	// Login failure
	return false;
}

/**
 * Authenticate a Web Connector session
 */
/*
class _QuickBooksClass
{
	static public function theStaticMethod($username, $password, &$qb_company_file)
	{
		//print('username [' . $username . '] [' . $password . ']');
		
		if ($username == 'keith' and 
			$password == 'rocks')
		{
			// Use this company file and auth successfully
			$qb_company_file = 'C:\path\to\the\file-staticmethod.QBW';
			
			//print('returning true...');
			
			return true;
		}
		
		// Login failure
		return false;
	}
}
*/

/**
 * Generate a qbXML response to add a particular customer to QuickBooks
 */
function _quickbooks_customer_add_request($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $version, $locale)
{
	// We're just testing, so we'll just use a static test request:
	 
	$xml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="2.0"?>
		<QBXML>
			<QBXMLMsgsRq onError="stopOnError">
				<CustomerAddRq requestID="' . $requestID . '">
					<CustomerAdd>
						<Name>ConsoliBYTE, LLC (' . mt_rand() . ')</Name>
						<CompanyName>ConsoliBYTE, LLC</CompanyName>
						<FirstName>Rick</FirstName>
						<LastName>Palmer</LastName>
						<BillAddress>
							<Addr1>ConsoliBYTE, LLC</Addr1>
							<Addr2>134 Stonemill Road</Addr2>
							<City>Mansfield</City>
							<State>CT</State>
							<PostalCode>06268</PostalCode>
							<Country>United States</Country>
						</BillAddress>
						<Phone>860-634-1602</Phone>
						<AltPhone>860-429-0021</AltPhone>
						<Fax>860-429-5183</Fax>
						<Email>Keith@ConsoliBYTE.com</Email>
						<Contact>Keith Palmer</Contact>
					</CustomerAdd>
				</CustomerAddRq>
			</QBXMLMsgsRq>
		</QBXML>';
	
	return $xml;
}

/**
 * Receive a response from QuickBooks 
 */
function _quickbooks_customer_add_response($requestID, $user, $action, $ID, $extra, &$err, $last_action_time, $last_actionident_time, $xml, $idents)
{
	return;	
}
/**
 * Generate a qbXML response to add a particular Company to QuickBooks
 *
 * So, you've queued up a QUICKBOOKS_ADD_COMPANY request with the
 * QuickBooks_Queue class like this:
 * 	$Queue = new QuickBooks_Queue('mysql://user:pass@host/database');
 * 	$Queue->enqueue(QUICKBOOKS_ADD_CUSTOMER, $primary_key_of_your_customer);
 *
 * And you're registered a request and a response function with your $map
 * parameter like this:
 * 	$map = array(
 * 		QUICKBOOKS_ADD_COMPANY => array( '_quickbooks_customer_add_request', '_quickbooks_customer_add_response' ),
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
	//$link = mysqli_connect ('localhost','centralsign','centralsign','initialhere');
	$conn = new mysqli('localhost','centralsign','centralsign','dbinitialhere');
	// Check connection
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	}
	$querry = "SELECT * FROM companies WHERE id = " . $ID;
	$result = $conn->query($querry);
	if ($result->num_rows > 0) {
    // output data of each row
    $res = $result->fetch_assoc(); 
        if (strlen($res['company'].' ('.$res['id'].')') > 40)
			{
				$len = 40-strlen(' ('.$res['id'].')');
				$name_string = substr($res['company'],0,$len).' ('.$res['id'].')';

			}
			else
			{
				$name_string = $res['company'].' ('.$res['id'].')';
			}

			if(strlen($res['company']) > 40)
			{
				$res['company'] = substr($res['company'],0,40);

			}
	}		
	$qbxml = '<?xml version="1.0" encoding="utf-8"?>
		<?qbxml version="2.0"?>
		<QBXML>
			<QBXMLMsgsRq onError="continueOnError">
				<CustomerAddRq requestID="' . $requestID . '">
					<CustomerAdd>
						<Name>'.$name_string.'</Name>
						<CompanyName>'.$res['company'].'</CompanyName>
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

	 $conn = new mysqli('localhost','centralsign','centralsign','dbinitialhere');
	// Check connection
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	}
	$data['qbListID'] = $idents['ListID'];
	 $sql = "UPDATE companies SET qbListID='".$idents['ListID']."' WHERE id=$ID";

	if ($conn->query($sql) === TRUE) {
		return true;
	}
	

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
	
	//$user = $cssDB->query_first("SELECT * FROM users WHERE id = " . $ID);
$conn = new mysqli('localhost','centralsign','centralsign','dbinitialhere');
	// Check connection
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	}
	$branchqry = "SELECT branches.id as bid,branches.*,cities.city as cityName,states.name as stateName FROM branches,cities,states WHERE branches.id = ".$ID." AND";
	$branchqry .= " branches.state = states.id AND branches.city = cities.id";
	$branchResult = $conn->query($branchqry);
	if ($branchResult->num_rows > 0) {
		$branchres = $branchResult->fetch_assoc();
		$companyqry = 'SELECT * FROM companies WHERE id = ' . $branchres['company_id'];
		$companyResult = $conn->query($companyqry);
		if ($companyResult->num_rows > 0) {
			$comapnyres = $companyResult->fetch_assoc();
		}
	}


		if (strlen($branchres['cityName'].', '.$branchres['stateName'].' ('.$branchres['bid'].')') > 40)
	{
		$len = 40-strlen(' ('.$branchres['bid'].')');
		$name_string = substr($branchres['cityName'].', '.$branchres['stateName'],0,$len).' ('.$branchres['bid'].')';

	}
	else
	{
		$name_string = $branchres['cityName'].', '.$branchres['stateName'].' ('.$branchres['bid'].')';
	}

	if(strlen($comapnyres['name']) > 40)
	{
		$comapnyres['company'] = substr($comapnyres['company'],0,40);

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
							<ListID>'.$comapnyres['qbListID'].'</ListID>
						</ParentRef>
						<BillAddress>
							<Addr1>'.$comapnyres['company'].'</Addr1>
							<Addr2>'.$branchres['address'].'</Addr2>
							<City>'.$branchres['cityName'].'</City>
							<State>'.$branchres['stateName'].'</State>
							<PostalCode>'.$branchres['zip'].'</PostalCode>
							<Country>United States</Country>
						</BillAddress>
						<Phone>'.$branchres['business_no'].'</Phone>
						<Fax>'.$branchres['fax_no'].'</Fax>
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

	 $conn = new mysqli('localhost','centralsign','centralsign','dbinitialhere');
	// Check connection
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	}
	$data['qbListID'] = $idents['ListID'];
	 
	 $sql = "UPDATE branches SET qbListID='".$idents['ListID']."' WHERE id=$ID";

	if ($conn->query($sql) === TRUE) {
		return true;
	}
	/*
	mysql_query("UPDATE your_customer_table SET quickbooks_listid = '" . mysql_escape_string($idents['ListID']) . "' WHERE your_customer_ID_field = " . (int) $ID);
	*/
}
