<?php

class quickbook_hook
{
	function quickbooks_hook($action, $id, $add_if_missing = true)
	{
		$conn = new mysqli('localhost','centralsign','centralsign','dbinitialhere');
		// Check connection
		if ($conn->connect_error) {
			die("Connection failed: " . $conn->connect_error);
		}

		$stop = 0;
		$priority = NULL;
		if(!empty($action))
		{
			
			if(!empty($id))
			{	
		
				// Queue it up!
				if($action == 'ClientClearBranch')
				{
					$qry = "UPDATE users SET qbListID = 'NULL' WHERE id = $id";
					$res = $conn->query($qry);
					$stop = 1;
				}
				elseif($action == 'ClientUpdate')
				{
					//check if they are in the QB system yet, if not add them instead of mod, if so add the GetSequence call and then Mod.
					$qry = "SELECT * FROM users WHERE id = $id";
					$res = $conn->query($qry);
					$user = $res->fetch_assoc();
					
					$qry = "SELECT * FROM quickbooks_queue WHERE qb_status = 'q' AND ident = $id AND qb_action = 'CustomerAdd'";
					$res = $conn->query($qry);
					$CustomerDup = $res->fetch_assoc();
					
					if(empty($user['qbListID']) && empty($CustomerDup))
					{
						if($add_if_missing == true)
						{
							$qry = "SELECT * FROM contacts WHERE contact_id = $id"
							$res = $conn->query($qry);
							$contact = $res->fetch_assoc();
							
							$qry = "SELECT * FROM branch_offices WHERE id = $contact['branch_id']";
							$res = $conn->query($qry);
							$branch = $res->fetch_assoc();
						
							if (empty($branch['qbListID']))
							{
								//Update/Add Branch First
								quickbooks_hook('BranchUpdate',$branch['id']);
							}
							$action = 'CustomerAdd';
							$priority = 12;
						}
						else
						{
							$stop = 1;
						}
					}
					else
					{
						quickbooks_enqueue('CustomerSequence', $id, 11);
						$priority = 10;
						$action = 'CustomerMod';
					}
				}
				elseif($action == 'BranchUpdate')
				{
					//check if they are in the QB system yet, if not add them instead of mod, if so add the GetSequence call and then Mod. 
					$qry = "SELECT * FROM branch_offices WHERE id = $id";
					$res = $conn->query($qry);
					$branch = $res->fetch_assoc();
					
					$qry = "SELECT * FROM quickbooks_queue WHERE qb_status = 'q' AND ident = $id AND qb_action = 'BranchAdd'";
					$res = $conn->query($qry);
					$CustomerDup = $res->fetch_assoc();
					
					if(empty($branch['qbListID']) && empty($CustomerDup))
					{
						if($add_if_missing == true)
						{
							$qry = "SELECT * FROM companies WHERE id = $branch['company_id']";
							$res = $conn->query($qry);
							$company = $res->fetch_assoc();
							
							if (empty($company['qbListID']))
							{
								//Update/Add Company First
								quickbooks_hook('CompanyUpdate',$company['id']);
							}
							$action = 'BranchAdd';
							$priority = 15;
						}
						else
						{
							$stop = 1;
						}
					}
					else
					{
						quickbooks_enqueue('BranchSequence', $id, 14);
						$priority = 13;
						$action = 'BranchMod';
						
						//update Customer in case address changed
						$qry = "SELECT contact_id FROM contacts WHERE branch_id = $id";
						$res = $conn->query($qry);
						$users = $res->fetch_assoc();
						
						foreach ($users as $user)
						{
							quickbooks_hook('ClientUpdate',$user['id'], false);
						}
					
					}
				
				}
				elseif($action == 'CompanyUpdate')
				{
					//check if they are in the QB system yet, if not add them instead of mod, if so add the GetSequence call and then Mod. 
					$qry = "SELECT * FROM companies WHERE id = $id";
					$res = $conn->query($qry);
					$company = $res->fetch_assoc();
					
					$qry = "SELECT * FROM quickbooks_queue WHERE qb_status = 'q' AND ident = $id AND qb_action = 'CompanyAdd'";
					$res = $conn->query($qry);
					$CustomerDup = $res->fetch_assoc();
					
					if(empty($company['qbListID']) && empty($CustomerDup))
					{
						if($add_if_missing == true)
						{
							$action = 'CompanyAdd';
							$priority = 18;
						}
						else
						{
							$stop = 1;
						}
					}
					else
					{
						quickbooks_enqueue('CompanySequence', $id, 17);
						$priority = 16;
						$action = 'CompanyMod';
					}
				}
				elseif($action == 'AgentUpdate')
				{
					//check if they are in the QB system yet, if not add them instead of mod, if so add the GetSequence call and then Mod.
					$qry = "SELECT * FROM users WHERE id = $id";
					$res = $conn->query($qry);
					$user = $res->fetch_assoc();
					
					$qry = "SELECT * FROM quickbooks_queue WHERE qb_status = 'q' AND ident = $id AND qb_action = 'VendorAdd'";
					$res = $conn->query($qry);
					$VendorDup = $res->fetch_assoc();
					
					if(empty($user['qbListID']))
					{
						if($add_if_missing == true && empty($VendorDup))
						{
							$action = 'VendorAdd';
							$priority = 15;
						}
						else
						{
							$stop = 1;
						}
					}
					else
					{
						quickbooks_enqueue('VendorSequence', $id, 14);
						$priority = 10;
						$action = 'VendorMod';
					}
				}
				elseif($action == 'InvoiceAdd')
				{
				
					//make sure we aren't making a dup
					$priority = 5;
					$qry = "SELECT * FROM signing_requests WHERE id = $id";
					$res = $conn->query($qry);
					$order = $res->fetch_assoc();
					
					$qry = "SELECT * FROM contacts WHERE id = $order['contact_id']";
					$res = $conn->query($qry);
					$contact = $res->fetch_assoc();
					
					$qry = "SELECT * FROM users WHERE id = $contact['contact_id']";
					$res = $conn->query($qry);
					$current_client = $res->fetch_assoc();
					
					if (empty($order['qbInvoiceTxnID']) || empty($current_client['qbListID']))
					{
						//Update client first
						quickbooks_hook('ClientUpdate',$contact['contact_id']);
					}
					else
					{
						$stop = 1;
					}
				}
				elseif($action == 'BillAdd')
				{
					//make sure not a dup
					$priority = 5;
					$qry = "SELECT * FROM signing_requests WHERE id = $id";
					$res = $conn->query($qry);
					$order = $res->fetch_assoc();
					
					if (empty($order['qbVendorTxnID']))
					{
						//update agent
						quickbooks_hook('AgentUpdate',$order['notaryID']);
					}
					else
					{
						$stop = 1;
					}
				}
				elseif($action == 'InvoiceVoid')
				{
				
					//make sure we aren't making a dup
					$priority = 4;
					$qry = "SELECT * FROM signing_requests WHERE id = $id";
					$res = $conn->query($qry);
					$order = $res->fetch_assoc();
					
					$qry = "SELECT * FROM quickbooks_queue WHERE qb_status = 'q' AND ident = $id AND qb_action = 'InvoiceAdd'";
					$res = $conn->query($qry);
					$InvoiceDup = $res->fetch_assoc();
					
					if(!empty($InvoiceDup))
					{
						$stop = 1;
						$qry = "DELETE FROM quickbooks_queue WHERE qb_status = 'q' AND ident = $id AND qb_action = 'InvoiceAdd'";
						$res = $conn->query($qry);
					}
					elseif (empty($order['qbInvoiceTxnID']))
					{
						$stop = 1;
					}
				}
				elseif($action == 'BillVoid')
				{
				
					//make sure we aren't making a dup
					$priority = 4;
					$qry = "SELECT * FROM signing_requests WHERE id = $id";
					$res = $conn->query($qry);
					$order = $res->fetch_assoc();
					
					$qry = "SELECT * FROM quickbooks_queue WHERE qb_status = 'q' AND ident = $id AND qb_action = 'BillAdd'";
					$res = $conn->query($qry);
					$BillDup = $res->fetch_assoc();
					
					if(!empty($BillDup))
					{
						$stop = 1;
						$qry = "DELETE FROM quickbooks_queue WHERE qb_status = 'q' AND ident = $id AND qb_action = 'BillAdd'";
						$res = $conn->query($qry);
					}
					elseif (empty($order['qbVendorTxnID']))
					{
						$stop = 1;
					}
				}
				
				if ($stop != 1)
				{
					//check for dups
					$qry = "SELECT * FROM quickbooks_queue WHERE qb_status = 'q' AND ident = $id AND qb_action = $action";
					$res = $conn->query($qry);
					$dup = $res->fetch_assoc();
					
					if(empty($dup))
					{
						quickbooks_enqueue($action, $id, $priority);
					}
				}
			}
		}
	}

	function quickbooks_enqueue($action,$id=0,$priority=0)
	{
		$conn = new mysqli('localhost','centralsign','centralsign','dbinitialhere');
		// Check connection
		if ($conn->connect_error) {
			die("Connection failed: " . $conn->connect_error);
		}
		
		$qry = "INSERT INTO quickbooks_queue VALUES(NULL, NULL, 'quickbooks', $action, $id, NULL, NULL, $priority, 'q', NULL, 'SYSDATE()', NULL)";
		$res = $conn->query($qry);
	}
}
