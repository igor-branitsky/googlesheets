<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("test2");

// include api goole
include $_SERVER['DOCUMENT_ROOT'].'/local/classes/ApiGoogle.php';

use FLXMD\ApiGoogle;

// initialize constructor object ApiGoogle
$objGoogle = new ApiGoogle();
// get sheet
$objGoogle->getGoogleSheet();
// get cols all lists
$objGoogle->getGoogleAllProduct();
// update element IBLOCK
$objGoogle->updateElement();
?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>