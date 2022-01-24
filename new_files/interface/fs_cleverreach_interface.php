<?php

require_once 'fs_cleverreach_interface_rest_client.php';
require '../includes/application_top_export.php';
require '../inc/xtc_not_null.inc.php';
require '../inc/xtc_get_country_name.inc.php';
require '../inc/xtc_href_link.inc.php';
session_start();

if (!isset($_SESSION['cleacerreach_interface_counter'])) {
	$_SESSION['cleacerreach_interface_counter'] = 1;
}

if(!defined('MODULE_FS_CLEVERREACH_INTERFACE_STATUS') || MODULE_FS_CLEVERREACH_INTERFACE_STATUS != 'true')
{
	header('Location: ' . preg_replace("/[\r\n]+(.*)$/i", "", html_entity_decode($_SERVER['HTTP_REFERER'])));
	exit();
}

if(!defined('MODULE_FS_CLEVERREACH_INTERFACE_CLIENT_ID') || !defined('MODULE_FS_CLEVERREACH_INTERFACE_USERNAME') || !defined('MODULE_FS_CLEVERREACH_INTERFACE_PASSWORD'))
{
	header('Location: ' . preg_replace("/[\r\n]+(.*)$/i", "", html_entity_decode($_SERVER['HTTP_REFERER'])));
	exit();
}

$rest = new \CR\tools\rest("https://rest.cleverreach.com/v2");

if (trim(MODULE_FS_CLEVERREACH_INTERFACE_CLIENT_ID) == '' || trim(MODULE_FS_CLEVERREACH_INTERFACE_USERNAME) == '' || trim(MODULE_FS_CLEVERREACH_INTERFACE_PASSWORD) == '') {
	die('Bitte geben Sie alle Anmeldedaten für Cleverreach ein!');
}

$token = $rest->post('/login',
	array(
		"client_id"=> MODULE_FS_CLEVERREACH_INTERFACE_CLIENT_ID,
		"login"=> 	MODULE_FS_CLEVERREACH_INTERFACE_USERNAME,
		"password"=> MODULE_FS_CLEVERREACH_INTERFACE_PASSWORD
	)
);

$rest->setAuthMode("bearer", $token);

$groups = $rest->get("/groups");

$group_id = MODULE_FS_CLEVERREACH_INTERFACE_GROUP_ID;

if (!isset($group_id)) {
	die('Keine Empfänger-Gruppen gefunden! Bitte erstellen Sie eine im Cleverreach-Backend');
}

$receivers = array();

$where_limit = '';
$where_offset = '';
if (isset($_SESSION['cleacerreach_interface_counter'])) {
    $where_limit .= ' LIMIT 100  ';
    if ($_SESSION['cleacerreach_interface_counter'] > 1) {
        $where_offset .= ' OFFSET ' . ($_SESSION['cleacerreach_interface_counter'] - 1) * 100 . ' ';
    }
}

if (MODULE_FS_CLEVERREACH_INTERFACE_IMPORT_SUBSCRIBERS == 'true') {


	$manual_registered_customers = xtc_db_query("SELECT
										customers_id,
										customers_email_address as email,
										date_added as registered,
										customers_firstname as firstname,
										customers_lastname as lastname
									FROM " . TABLE_NEWSLETTER_RECIPIENTS . " WHERE mail_status = '1' "  . $where_limit . $where_offset);

	while ($customer = xtc_db_fetch_array($manual_registered_customers)) {
		$orders = array();
		$order_rows = xtc_db_query("SELECT o.orders_id, op.products_id, op.products_name, op.products_price, op.products_quantity from " . TABLE_ORDERS . " o JOIN " . TABLE_ORDERS_PRODUCTS . " op ON o.orders_id = op.orders_id  WHERE customers_id = '" . $customer['customers_id'] . "' ORDER BY date_purchased ");
		while ($order_row = xtc_db_fetch_array($order_rows)) {

		$orders[] = array(
				"order_id"   => $order_row["orders_id"],      //required
				"product_id" => $order_row["products_id"],    //optional
				"product"    => $order_row["products_name"],  //required
				"price"      => $order_row["products_price"],  //optional
				"currency"   => "EUR",                     //optional
				"amount"     => $order_row["products_quantity"], //optional
				"source"     => STORE_NAME          //optional
			);
		}

		$gender_query = xtc_db_query("SELECT c.customers_gender as gender, ab.entry_country_id as country, ab.entry_city as city, ab.entry_postcode as zip, ab.entry_street_address as street, ab.entry_company as company FROM " . TABLE_CUSTOMERS . " c JOIN ".TABLE_ADDRESS_BOOK." ab ON c.customers_id = ab.customers_id WHERE c.customers_id = '" . $customer['customers_id'] . "' AND c.customers_default_address_id = ab.address_book_id AND c.customers_email_address NOT LIKE '%@marketplace.amazon.de%'");
		$customers_data = xtc_db_fetch_array($gender_query);
		$country = xtc_get_country_name($customers_data['country']);

		$receivers[] = array(
					"email"			=> $customer["email"],
					"registered"	=> strtotime($customer["registered"]),
					"activated"		=> strtotime($customer["registered"]),
					"source"		=> STORE_NAME,
					"attributes" 	=> array(
							"city"		=> $customer["city"],
							"street"	=> $customer["street"],
							"zip" 		=> $customer["zip"],
							"country" 	=> $country,
							"firstname" => $customer["firstname"],
							"lastname" =>  $customer["lastname"],
							"geschlecht" =>    $customers_data["gender"],
							"company" => $customers_data["company"]
					),
					"orders" => $orders
		);
	}
}

if (MODULE_FS_CLEVERREACH_INTERFACE_IMPORT_BUYERS == 'true') {
	$where_clause = '';
	if (isset($_GET['export_filter_amazon']) && $_GET['export_filter_amazon'] == 1) {
		$where_clause .= " AND customers_email_address NOT LIKE '%@marketplace.amazon.de%'";
	}
	$order_rows = xtc_db_query("SELECT DISTINCT o.orders_id, o.customers_id, o.customers_email_address as email, o.customers_firstname as firstname, o.customers_lastname as lastname, o.customers_gender as gender, o.customers_street_address as street, o.customers_city as city, o.customers_postcode as zip, o.customers_country as country, o.date_purchased, op.products_id, op.products_name, op.products_price, op.products_quantity from " . TABLE_ORDERS . " o JOIN " . TABLE_ORDERS_PRODUCTS . " op ON o.orders_id = op.orders_id GROUP BY o.customers_id ORDER BY o.date_purchased " . $where_limit . $where_offset);
	while ($order_row = xtc_db_fetch_array($order_rows)) {

		$orders = array();

		$orders[] = array(
				"order_id"   => $order_row["orders_id"],      //required
				"product_id" => $order_row["products_id"],    //optional
				"product"    => utf8_encode($order_row["products_name"]),  //required
				"price"      => $order_row["products_price"],  //optional
				"currency"   => "EUR",                     //optional
				"amount"     => $order_row["products_quantity"], //optional
				"source"     => STORE_NAME          //optional
			);

        $flagged_customers = xtc_db_query("SELECT c.customers_date_added as registered, c.customers_gender as gender, c.customers_dob as dob FROM " . TABLE_CUSTOMERS . " c WHERE c.customers_id = '" . $order_row["customers_id"] . "' AND customers_email_address NOT LIKE '%@marketplace.amazon.de%'");

		if (xtc_db_num_rows($flagged_customers) > 0) {
			while ($customer = xtc_db_fetch_array($flagged_customers)) {

				$receivers[] = array(
					"email"			=> $order_row["email"],
					"activated"		=> strtotime($customer["registered"]),
					"registered"	=> strtotime($customer["registered"]),
					"source"		=> STORE_NAME,
					"attributes"	=> array(
						"nachname" => utf8_encode($order_row["firstname"]),
						"vorname" =>  utf8_encode($order_row["lastname"]),
						"m__nnlich_weiblich" =>    $customer["gender"],
						"geburtsdatum" => $customer['dob'],
						"ort"	=> utf8_encode($order_row["city"]),
						"street" => utf8_encode($order_row["street"]),
						"zip" => $order_row["zip"],
						"country" => utf8_encode($order_row["country"])
						),
					"orders" => $orders
				);
			}
		}
	}
}

if (count($receivers) > 0) {
	foreach ($receivers as $receiver) {
		$response = $rest->get("/groups.json/".$group_id."/receivers/", $receiver["email"]);
		if(!$response) {
			$rest->post("/groups.json/".$group_id."/receivers", $receiver);
		} else {
			$rest->put("/groups.json/".$group_id."/receivers/".$receiver["email"], json_encode($receiver));
		}
	}
} else {
	die('Keine neuen Daten gefunden');
}

if (count($receivers) == 100) {
	$_SESSION['cleacerreach_interface_counter'] = $_SESSION['cleacerreach_interface_counter'] + 1;
	header("Refresh:1;");
	exit;
}

$receivers = array();

unset($_SESSION['cleacerreach_interface_counter']);
header('Location: ' . xtc_href_link_admin((defined('DIR_ADMIN') ? DIR_ADMIN : 'admin/').'module_export.php', 'set=system&module=fs_cleverreach_interface&action=edit', 'NONSSL'));
exit();
