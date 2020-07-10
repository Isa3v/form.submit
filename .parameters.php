<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) {
    die();
}

if(!CModule::IncludeModule("iblock"))
	return;

$arTypesEx = CIBlockParameters::GetIBlockTypes(array("-"=>" "));

$arIBlocks = [];
$db_iblock = CIBlock::GetList(["SORT"=>"ASC"], ["SITE_ID" => $_REQUEST["site"], "TYPE" => ($arCurrentValues["IBLOCK_TYPE"]!="-"?$arCurrentValues["IBLOCK_TYPE"]:"")]);
while($arRes = $db_iblock->Fetch()){
	$arIBlocks[$arRes["ID"]] = "[".$arRes["ID"]."] ".$arRes["NAME"];
}


$arComponentParameters = array(
    'GROUPS' => array(
    ),
    'PARAMETERS' => array(
        "IBLOCK_TYPE" => array(
			"PARENT" => "BASE",
			"NAME" => "Тип инфоблока",
			"TYPE" => "LIST",
			"VALUES" => $arTypesEx,
			"DEFAULT" => "news",
			"REFRESH" => "Y",
        ),
        "IBLOCK_ID" => array(
			"PARENT" => "BASE",
			"NAME" => "ID инфоблока веб-формы",
			"TYPE" => "LIST",
			"VALUES" => $arIBlocks,
			"DEFAULT" => "",
			"ADDITIONAL_VALUES" => "Y",
			"REFRESH" => "Y",
		),
		'BTN_NAME' => array(
            'PARENT' => 'BASE',
            'NAME' => "Имя кнопки отправки формы",
            'TYPE' => 'STRING',
            'DEFAULT' => ''
		),
        'EVENT' => array(
            'PARENT' => 'BASE',
            'NAME' => "Название эвента для отправки письма",
            'TYPE' => 'STRING',
            'DEFAULT' => ''
		),
        'CACHE_TIME' => array(
            'DEFAULT' => 3600
        )
    )
);
