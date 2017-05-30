<?
if(!defined("B_PROLOG_INCLUDED")||B_PROLOG_INCLUDED!==true)die();

/**
 * Bitrix vars
 *
 * @var array $arParams
 * @var array $arResult
 * @var CBitrixComponent $this
 * @global CMain $APPLICATION
 * @global CUser $USER
 */
global $CACHE_MANAGER;
if(!isset($arParams["CACHE_TIME"]))
	$arParams["CACHE_TIME"] = 36000000;
if(!\Bitrix\Main\Loader::includeModule('iblock'))
	return;

$arParams["DISPLAY_TOP_PAGER"] = $arParams["DISPLAY_TOP_PAGER"]=="Y";
$arParams["DISPLAY_BOTTOM_PAGER"] = $arParams["DISPLAY_BOTTOM_PAGER"]!="N";
$arParams["PAGER_TITLE"] = trim($arParams["PAGER_TITLE"]);
$arParams["PAGER_SHOW_ALWAYS"] = $arParams["PAGER_SHOW_ALWAYS"]=="Y";
$arParams["PAGER_TEMPLATE"] = trim($arParams["PAGER_TEMPLATE"]);
$arParams["PAGER_DESC_NUMBERING"] = $arParams["PAGER_DESC_NUMBERING"]=="Y";
$arParams["PAGER_DESC_NUMBERING_CACHE_TIME"] = intval($arParams["PAGER_DESC_NUMBERING_CACHE_TIME"]);
$arParams["PAGER_SHOW_ALL"] = $arParams["PAGER_SHOW_ALL"]=="Y";

if ($arParams['DISPLAY_TOP_PAGER'] || $arParams['DISPLAY_BOTTOM_PAGER'])
{
	$arNavParams = array(
		"nPageSize" => $arParams["PAGE_ELEMENT_COUNT"],
		"bDescPageNumbering" => $arParams["PAGER_DESC_NUMBERING"],
		"bShowAll" => $arParams["PAGER_SHOW_ALL"],
	);
	$arNavigation = CDBResult::GetNavParams($arNavParams);
	if($arNavigation["PAGEN"]==0 && $arParams["PAGER_DESC_NUMBERING_CACHE_TIME"]>0)
		$arParams["CACHE_TIME"] = $arParams["PAGER_DESC_NUMBERING_CACHE_TIME"];
}
else
{
	$arNavParams = array(
		"nTopCount" => $arParams["PAGE_ELEMENT_COUNT"],
		"bDescPageNumbering" => $arParams["PAGER_DESC_NUMBERING"],
	);
	$arNavigation = false;
}

$arParams['CACHE_GROUPS'] = trim($arParams['CACHE_GROUPS']);
if ('N' != $arParams['CACHE_GROUPS'])
	$arParams['CACHE_GROUPS'] = 'Y';

$arParams["CACHE_FILTER"]=$arParams["CACHE_FILTER"]=="Y";
if(!$arParams["CACHE_FILTER"] && count($arrFilter)>0)
	$arParams["CACHE_TIME"] = 0;

if($this->startResultCache(false, array($arrFilter, ($arParams["CACHE_GROUPS"]==="N"? false: $USER->GetGroups()), $arNavigation, $pagerParameters)))
{

	$arResult = array();
	$res = CIBlockElement::GetList(
		array(),
		array(
			'IBLOCK_ID' => $arParams['IBLOCK_ID'],
			'ACTIVE' => 'Y',
			'!PROPERTY_ELEMENTS_CHAIN' => false,
			'!PROPERTY_USERS_CHAIN' => false,
		),
		false,
		$arNavParams,
		array(
			'ID',
			'IBLOCK_ID',
			'NAME',
			'TIMESTAMP_X',
			'IBLOCK_SECTION_ID'
		)
	);
	while($arRes = $res->GetNext()){
		$elRes = CIBlockElement::GetProperty($arParams['IBLOCK_ID'], $arRes["ID"], array("sort" => "asc"), array("CODE" => "ELEMENTS_CHAIN"));
		while ($ob = $elRes->GetNext()) {
			$arRes['VALUE_ELEMENTS_CHAIN'][] = $ob['VALUE'];
		}
		$elRes2 = CIBlockElement::GetProperty($arParams['IBLOCK_ID'], $arRes["ID"], array("sort" => "asc"), array("CODE" => "USERS_CHAIN"));
		while ($ob2 = $elRes2->GetNext()) {
			$rsUser = CUser::GetByID($ob2['VALUE']);
			$arUser = $rsUser->Fetch();
			$userNames = $arUser['LAST_NAME'].' '.$arUser['NAME'].' '.$arUser['SECOND_NAME'];
			$arRes['VALUE_USERS_CHAIN'][] = $userNames;
		}
		$elRes3 = CIBlockElement::GetProperty($arParams['IBLOCK_ID'], $arRes["ID"], array("sort" => "asc"), array("CODE" => "LIST_NAME"));
		if ($ob3 = $elRes3->GetNext()) {
			$arRes['VALUE_LIST_NAME'] = $ob3['VALUE'];
		}
		$arResult['ELEMENTS'][] = $arRes;
	}
	$secRes = CIBlockSection::GetByID($arResult['ELEMENTS'][0]['VALUE_LIST_NAME']);
	if($ar_res = $secRes->GetNext()) {
		$arResult['LIST_NAME'] = $ar_res['NAME'];
	}


	$navComponentParameters = array();
	if ($arParams["PAGER_BASE_LINK_ENABLE"] === "Y")
	{
		$pagerBaseLink = trim($arParams["PAGER_BASE_LINK"]);
		if ($pagerBaseLink === "")
			$pagerBaseLink = $arResult["SECTION_PAGE_URL"];

		if ($pagerParameters && isset($pagerParameters["BASE_LINK"]))
		{
			$pagerBaseLink = $pagerParameters["BASE_LINK"];
			unset($pagerParameters["BASE_LINK"]);
		}

		$navComponentParameters["BASE_LINK"] = CHTTP::urlAddParams($pagerBaseLink, $pagerParameters, array("encode"=>true));
	}
	else
	{
		$uri = new \Bitrix\Main\Web\Uri($this->request->getRequestUri());
		$uri->deleteParams(
			array_merge(
				array(
					"PAGEN_".$rsElements->NavNum,
					"SIZEN_".$rsElements->NavNum,
					"SHOWALL_".$rsElements->NavNum,
					"PHPSESSID",
					"clear_cache",
					"bitrix_include_areas"
				),
				\Bitrix\Main\HttpRequest::getSystemParameters()
			)
		);
		$navComponentParameters["BASE_LINK"] = $uri->getUri();
	}

	$arResult["NAV_STRING"] = $res->GetPageNavStringEx(
		$navComponentObject,
		$arParams["PAGER_TITLE"],
		$arParams["PAGER_TEMPLATE"],
		$arParams["PAGER_SHOW_ALWAYS"],
		$this,
		$navComponentParameters
	);


	$this->IncludeComponentTemplate();
}
?>