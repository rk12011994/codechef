<?php

$conn = new mysqli('localhost','centralsign','centralsign','dbinitialhere');
	// Check connection
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	}
	$branchqry = "SELECT branches.id as bid,branches.*,cities.city as cityName,states.name as stateName FROM branches,cities,states WHERE branches.id = 12 AND";
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
				<CustomerAddRq>
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
		
		echo "<pre>";
		print_r($branchres);
		print_r($comapnyres);
		print_r($name_string);
		print_r(simplexml_load_string($qbxml));
		die;
		
