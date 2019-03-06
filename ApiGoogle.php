<?
namespace FLXMD;

use Bitrix\Main\Loader,
	Bitrix\Main\Config\Option,
	Bitrix\Main\SystemException;

class ApiGoogle {

	private $sUrl = '';

	private $sSheetID = '';

	private $sKeyApi = '';

	private $arCodesSites = [];

	private $arListGoogleSheet = [];

	private $arProductMap = [];

	private $IBLOCK_ID = null;

	function __construct() {

		try
		{
			if (Loader::includeSharewareModule("grain.customsettings")) {

				$this->sUrl = Option::get("grain.customsettings", "url_api") ;

				$this->sSheetID = Option::get("grain.customsettings", "sheet_id");

				$this->sKeyApi = Option::get("grain.customsettings", "key_api");

				$this->arCodesSites = explode(',',  Option::get("grain.customsettings", "code_sites"));

				$this->IBLOCK_ID = Option::get("grain.customsettings", "iblock_id");

				if ( empty($this->sUrl) || empty($this->sSheetID) || empty($this->sKeyApi) || empty($this->arCodesSites) || empty($this->IBLOCK_ID)) {
					throw new SystemException("Error, not all parameters are defined !");
				}

			} else {
				throw new SystemException("Error, module grain.customsettings not found !");
			}

		}
		catch (SystemException $exception)
		{
			echo $exception->getMessage();
		}

	}

	public function sendRequest($sUrl) {

		try
		{

			if (empty($sUrl)) {
				throw new SystemException("Error, \$sUrl empty");
			}

			$objCurl = curl_init();

			curl_setopt_array(
				$objCurl,
				[
					CURLOPT_URL => $sUrl,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_ENCODING => '',
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => 30,
					CURLOPT_HTTP_VERSION => 'CURL_HTTP_VERSION_1_1',
					CURLOPT_CUSTOMREQUEST => 'GET'
				]
			);

			$objResponse = curl_exec($objCurl);
			$sError = curl_error($objCurl);

			curl_close($objCurl);

			if (!empty($sError)) {
				throw new SystemException("Error curl send - $sError");
			} else {
				return json_decode($objResponse);
			}

		}
		catch (SystemException $exception)
		{
			echo $exception->getMessage();
		}

	}

	public function getGoogleSheet() {

		$sUrlGoogleSheet = $this->sUrl.$this->sSheetID.'?key='.$this->sKeyApi;

		$objResponse = $this->sendRequest($sUrlGoogleSheet);

		if (!empty($objResponse->sheets)) {

			foreach ($objResponse->sheets as $objSheet) {

				$this->arListGoogleSheet[] = $objSheet->properties->title;

			}

		}

	}

	public function getGoogleAllProduct() {

		if (!empty($this->arListGoogleSheet)) {

			$arResponseAll = [];

			foreach ($this->arListGoogleSheet as $sListName) {

				$sUrlGoogleSheet = $this->sUrl.$this->sSheetID.'/values/'.urlencode($sListName.'!').'A:ALL?key='.$this->sKeyApi.'&majorDimension=COLUMNS';

				$objResponse = $this->sendRequest($sUrlGoogleSheet);

				$arResponseAll[] = $objResponse->values;

			}

			foreach ($arResponseAll as $arItemList) {

				$arID = [];
				$arPrice = [];
				$sFirst = null;

				foreach ($arItemList as $key => $arItem) {

					if($arItem['0'] === 'ID') {
						$sFirst = array_shift($arItem);
						$arID[$sFirst] = $arItem;
					}

					if(in_array($arItem['0'], $this->arCodesSites)) {
						$sFirst = array_shift($arItem);
						$arPrice[$sFirst] = $arItem;
					}
				}

				foreach ($arPrice as $sKey => $Price) {
					foreach ($Price as $sKeyPrice => $sVal) {
						if (!empty($arID['ID'][$sKeyPrice])) {
							$this->arProductMap[$arID['ID'][$sKeyPrice]][$sKey] = $sVal;
						}
					}
				}

			}

		}

	}

	public function updateElement() {

		if (!empty($this->arProductMap) && Loader::includeSharewareModule("iblock")) {

			foreach ($this->arProductMap as $sID => $arPrices) {
				if (!is_numeric($sID)) {
					continue;
				}
				foreach ($arPrices as $sPropCode => $sPrice) {

					if (!empty($sPropCode)) {

						$dbProp = \CIBlockProperty::GetList([], Array("CODE" => $sPropCode, "IBLOCK_ID" => $this->IBLOCK_ID));
						$arProp = $dbProp->Fetch();

						if (empty($arProp['ID'])) {
							$arFields = Array(
								"NAME" => $sPropCode,
								"ACTIVE" => "Y",
								"SORT" => "100",
								"CODE" => $sPropCode,
								"FILTRABLE" => "Y",
								"PROPERTY_TYPE" => "S",
								"IBLOCK_ID" => $this->IBLOCK_ID
							);
							$objProperty = new\ CIBlockProperty;
							$idProps = $objProperty->Add($arFields);
						}

						if ((!empty($arProp['ID']) || !empty($idProps)) && !empty($sID)) {

							\CIBlockElement::SetPropertyValuesEx($sID, $this->IBLOCK_ID, array($sPropCode => $sPrice));

						}

					}

				}
			}

		}

	}

}