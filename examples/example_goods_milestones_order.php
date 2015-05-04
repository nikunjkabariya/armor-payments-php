<?php
// This example file will create two new accounts - a buyer and a seller.
// It will then create an order between them, and move the order through
// the process to completion.

//////////////////////////////////////////////////////////////////////////
// WARNING - WARNING - WARNING - WARNING - WARNING - WARNING - WARNING //
////////////////////////////////////////////////////////////////////////
// 
// This file contains example code which will create accounts, users, and
// orders, and move those orders through the order process. Although they
// are not destructive, some of these actions are non-reversible.
//
// You should start with the Example.php file to ensure your API key and
// secret are correct before proceding to this example file.
// 
//////////////////////////////////////////////////////////////////////////
// WARNING - WARNING - WARNING - WARNING - WARNING - WARNING - WARNING //
////////////////////////////////////////////////////////////////////////

// Include the main library file
require_once __DIR__.'/../lib/ArmorPayments/ArmorPayments.php';

// To run this example, you will need a working Sandbox API key, stored in a file.
// Make sure you create this file. It should contain your credentials in the
// following format:
//
// <?php
// $api_key    = "ENTER_YOUR_API_KEY_HERE";
// $api_secret = "ENTER_YOUR_API_SECRET_HERE";
//
require_once __DIR__.'/../api_credentials.php';

// Create a new API client, and set the Sandbox environment flag to TRUE
$client = new \ArmorPayments\Api($api_key, $api_secret, true);

// User emails must be unique, so create a unique identifier to include
// in the email addresses. This will allow you to run this example file
// multiple times without email address collisions. The unique identifier
// will also be used in the account company names and the order summary
// in order to make it easier to tell similiar-looking test values apart.
$unique = uniqid();

// Create a buyer user and account
$buyerAccount = $client->accounts()->create(array(
	'company'    => "Example buyer company {$unique}",
	'address'    => '123 Pine Rd.',
	'city'       => 'Anytown',
	'state'      => 'MO',
	'country'    => 'us',
	'zip'        => '12345',
	'user_name'  => "Example Buyer {$unique}",
	'user_email' => "buyer_{$unique}@example.com",
	'user_phone' => '+1 1235551234',
	));
echo print_r($buyerAccount, true)."\n";
// Retrieve the newly created user
$buyerUser = $client->accounts()->users($buyerAccount->account_id)->all();
$buyerUser = $buyerUser[0];
echo print_r($buyerUser, true)."\n";

// Create a seller user and account
$sellerAccount = $client->accounts()->create(array(
	'company'    => "Example seller company {$unique}",
	'address'    => '123 Pine Rd.',
	'city'       => 'Anytown',
	'state'      => 'MO',
	'country'    => 'us',
	'zip'        => '12345',
	'user_name'  => "Example Seller {$unique}",
	'user_email' => "seller_{$unique}@example.com",
	'user_phone' => '+1 1235551234',
	));
echo print_r($sellerAccount, true)."\n";
// Retrieve the newly created user
$sellerUser = $client->accounts()->users($sellerAccount->account_id)->all();
$sellerUser = $sellerUser[0];
echo print_r($sellerUser, true)."\n";

// Ask the seller to provide bank account details for the account they'd
// like to receive payment for the order.
$response = $client->accounts()->users($buyerAccount->account_id)->authentications($buyerUser->user_id)->create(
	array(
		'uri'    => "/accounts/{$sellerAccount->account_id}/bankaccounts",
		'action' => 'create',
		));
echo print_r($response, true)."\n";

// Usually, we'd expect the seller to provide bank details using the
// iFrame URL generated in the previous step. In this case, we're going to
// create a new bank account record via the API instead, so that our example
// order doesn't have an error when funds are released because of a missing
// bank account (this would not be a fatal error, but represents a delay in
// completing the order until Armor Payments is instructed where funds are to
// be transferred).
$bankaccounts = $client->accounts()->bankaccounts($sellerAccount->account_id)->create(
	array(
		'type'     => 1, // A business checking account
		'location' => 'us', // This is a domestic, US-based account
		'bank'     => 'Example Bank',
		'routing'  => '123456789',
		'account'  => '1234567890123456',
		));
echo print_r($bankaccounts, true)."\n";

// Create an order between the buyer and seller
// We will create a goods order with milestone payments for this example
$order = $client->accounts()->orders($sellerAccount->account_id)->create(array(
	'order_type'  => 1, // An escrow order for goods
	'seller_id'   => $sellerUser->user_id,
	'buyer_id'    => $buyerUser->user_id,
	'amount'      => 10000,
	'summary'     => "Test goods milestones order {$unique}",
	'description' => "A test goods milestones order generated by the armor-payments-php example script ({$unique})",
	'inspection'  => true, // This order includes a goods inspection step prior to shipping
	'goodsmilestones' => array(
		array(
			'amount' => 3000,
			'escrow' => 4000,
			),
		array(
			'amount' => 1000,
			'escrow' => 6000,
			),
		array(
			'amount' => 0,
			'escrow' => 0,
			),
		array(
			'amount' => 6000,
			'escrow' => 0,
			),
		),
	'invoice_num'        => '12345',
	'purchase_order_num' => '67890',
	));
echo print_r($order, true)."\n";

// Display payment instructions to the buyer
$response = $client->accounts()->users($buyerAccount->account_id)->authentications($buyerUser->user_id)->create(
	array(
		'uri'    => "/accounts/{$order->account_id}/orders/{$order->order_id}/paymentinstructions",
		'action' => 'view',
		));
echo print_r($response, true)."\n";
// Use the returned URL to display an iFrame to the user with the payment
// instructions. For the purposes of this example, we will imagine that the
// user has viewed those instructions as we move on to the next step...

// Create a payment on the order.
//
// This function is for testing only, and is only exposed in the Sandbox
// environment. No actual funds will be transferred.
$response = $client->accounts()->orders($sellerAccount->account_id)->update(
	$order->order_id,
	array(
		'action'            => 'add_payment',
		'confirm'           => true,
		'source_account_id' => $buyerAccount->account_id,
		'amount'            => 4000,
		));
echo print_r($response, true)."\n";

// Once the first payment is made, the first milestone is automatically
// released to the seller.
//
// Prior to the goods being inspected, payment for the second milestone
// must be placed in escrow. So, we'll add another payment.
$response = $client->accounts()->orders($sellerAccount->account_id)->update(
	$order->order_id,
	array(
		'action'            => 'add_payment',
		'confirm'           => true,
		'source_account_id' => $buyerAccount->account_id,
		'amount'            => 6000,
		));
echo print_r($response, true)."\n";

// The next step is to indicate once the goods have been inspected.
$response = $client->accounts()->orders($order->account_id)->update(
	$order->order_id,
	array(
		'action' => 'completeinspection',
		'confirm' => true,
		));
echo print_r($response, true)."\n";

// Get a list of shipement carriers
$response = $client->shipmentcarriers()->all();
echo print_r($response, true)."\n";

// Once the goods have been inspected, they need to be shipped to the buyer.
$response = $client->accounts()->orders($order->account_id)->shipments($order->order_id)->create(
	array(
		'user_id'     => $sellerUser->user_id,
		'carrier_id'  => 8, // UPS
		'tracking_id' => 'z1234567890123456',
		'description' => 'Shipped via UPS ground in a protective box.',
		));
echo print_r($response, true)."\n";

// Check the status of the various milestones on the order.
$response = $client->accounts()->orders($order->account_id)->milestones($order->order_id)->all();
echo print_r($response, true)."\n";

// Once the buyer has received the goods and reviewed them, the last step
// is to release the remaining payment from escrow.
//
// Note that this action usually requires the buyer to release the funds
// from escrow themselves, either by visiting the Armor Payments site or
// via an Armor Payments interface displayed in an iFrame on a partner's
// site. Partners are authorized to take this action on a user's behalf in
// the Armor Payments Sandbox environment for testing purposes, but will
// not be able to do so in Production.
$response = $client->accounts()->orders($order->account_id)->update(
	$order->order_id,
	array(
		'action' => 'release',
		'confirm' => true,
		));
echo print_r($response, true)."\n";
