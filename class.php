<?
use \Bitrix\Main\Application;
use \Bitrix\Main\Loader;

class ThelhFeedbackComponent extends CBitrixComponent
{

    /**
     * Обработка параметров компонента
     */
    public function onPrepareComponentParams($params)
    {
        $result = array(
            'IBLOCK_ID'             => (int) $params['IBLOCK_ID'],
            'WEBFORM_ID'            => md5($this->getTemplateName()).'_'.$params['IBLOCK_ID'],
            'EVENT'                 => trim($params['EVENT']),
            'CACHE_TYPE'            => trim($params['CACHE_TYPE']),
            'CACHE_TIME'            => intval($params['CACHE_TIME']) > 0 ? intval($params['CACHE_TIME']) : 3600, 
            'TIME_COOKIE'           => intval($params['TIME_COOKIE']) > 0 ? intval($params['TIME_COOKIE']) : 0, 
        );
        $params = array_merge($params, $result);
        return $params;
    }

    /**
     * определяет читать данные из кеша или нет
     * @return bool
     */
    protected function readDataFromCache()
    {
        global $USER;
        if ($this->arParams['CACHE_TYPE'] == 'N') {
            return false;
        }

        if (is_array($this->cacheAddon)) {
            $this->cacheAddon[] = $USER->GetUserGroupArray();
        } else {
            $this->cacheAddon = array($USER->GetUserGroupArray());
        }

        return !($this->startResultCache(false, $this->cacheAddon, md5(serialize($this->arParams))));
    }

    /**
     * кеширует ключи массива arResult
     */
    protected function putDataToCache()
    {
        $templateCachedData = $this->GetTemplateCachedData();
        $this->SetResultCacheKeys($templateCachedData);
    }

    /**
     * Поля веб-формы
     * Данные кешируются и не будут заново вызываться при отправке формы
     * @return array 
     */
    public function getFields()
    { 
        $arFields = [];
        $resProp = \CIBlockProperty::GetList(["sort"=>"asc", "name"=>"asc"], ["ACTIVE"=>"Y", "IBLOCK_ID" => $this->arParams["IBLOCK_ID"]]);
        while ($arProp = $resProp->fetch()){   
            $arFields[$arProp['CODE']] = $arProp;

            if($arProp['PROPERTY_TYPE'] == 'L'){
                $resValuesList = \CIBlockPropertyEnum::GetList(Array("DEF"=>"DESC", "SORT"=>"ASC"), Array("IBLOCK_ID" => $this->arParams["IBLOCK_ID"], "CODE"=> $arProp['CODE']));
                while($values = $resValuesList->GetNext()){
                    $arFields[$arProp['CODE']]['VALUES'][$values['ID']] = $values;
                }
            }

            // Привзяка к элементу
            if($arProp['PROPERTY_TYPE'] == 'E'){
                $resValuesList = \CIBlockElement::GetList([], Array("IBLOCK_ID" => $arProp["LINK_IBLOCK_ID"], "ACTIVE" => "Y"), false, [], ['ID', 'IBLOCK_ID', 'NAME']); 
                while($ob = $resValuesList->GetNextElement()){ 
                    $field = $ob->GetFields();  
                    $field['PEOPERTIES'] = $ob->GetProperties();
                    $arFields[$arProp['CODE']]['VALUES'][$field['ID']] = $field;
                }
            }

            // Справочник
            if($arProp['PROPERTY_TYPE'] == 'S' && $arProp['USER_TYPE'] == 'directory' && \Bitrix\Main\Loader::includeModule("highloadblock")){
                $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getList(['filter' => ['TABLE_NAME' => $arProp['USER_TYPE_SETTINGS']['TABLE_NAME']]])->fetch(); 
                $entityHighloadBlock = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock)->getDataClass();
                $resHighload = $entityHighloadBlock::getList(["select" => ['*']]);
                while($ob = $resHighload->Fetch()){
                    if(!empty($ob['UF_FILE'])){
                        $ob['SRC'] = \CFile::GetPath($ob['UF_FILE']);
                    }
                    $arFields[$arProp['CODE']]['VALUES'][$ob['ID']] = $ob;
                }
            }
            
        }
        $this->arResult["FIELDS"] = $arFields;
    }

    /**
     * Обработка получения данных формы
     * @return mixed 
     */
    public function requestForm()
    {
        $context = Application::getInstance()->getContext();
        $request = $context->getRequest();
        $data    = $request->getPostList()->toArray();          // POST параметры

        $this->arResult["SEND"]         = false;
        $this->arResult["ERROR"]        = [];
        $this->arResult["SEND_FIELDS"]  = [];

        // Если скрытое поле reviews_iblock соответствует ID инфоблока
        if ($request->getPost("webform") == $this->arParams["WEBFORM_ID"] && check_bitrix_sessid()) {

            // Если это ajax запрос, то отдаем в arResult переменную
            if($request->isAjaxRequest()){
                $this->arResult["IS_AJAX"] = "Y";
            }

            foreach($this->arResult["FIELDS"] as $code => $arField){

                // Если поле обязательное и пришло пустым
                if($arField['IS_REQUIRED'] == 'Y' && empty($data[$code])){
                    // Записываем как ошибку
                    $this->arResult["ERROR"][$code] = "Обязятельное поле \"{$arField['NAME']}\" не заполнено!";
                }

                // Разбираем поля. Лишнее нам не надо
                if(!empty($data[$code])){
                    $this->arResult["SEND_FIELDS"][$code] = $data[$code];
                }
                
            }

            // Если были ошибки возвращаем
            if(!empty($this->arResult["ERROR"])){
                return false;
            }

            $this->arResult["SEND"] = true;

            /**
             * Сохранить элемент инфоблока
             */
            $arEventFields = array();
            if ($this->arParams["IBLOCK_ID"]) {
                $arResult = array(
                    'IBLOCK_ID' => $this->arParams["IBLOCK_ID"],
                    'ACTIVE' => 'N',
                    'NAME' => date("Y-m-d H:i:s").(!empty($this->arResult["SEND_FIELDS"]['NAME']) ? " [{$this->arResult["SEND_FIELDS"]['NAME']}]" : ""),
                    'DATE_ACTIVE_FROM' => ConvertTimeStamp(time(), "FULL"),
                    'PROPERTY_VALUES' => $this->arResult["SEND_FIELDS"]
                );
                $element = new \CIBlockElement();
                $arEventFields["ID"] = $element->Add($arResult);

                /**
                 * Отправить письмо
                 */
                if ($this->arParams["EVENT"]) {
                    foreach ($arResult as $k => $v) {
                        $arEventFields[strtoupper($k)] = $v;
                    }

                    $mailEvent = \Bitrix\Main\Mail\Event::send(array(
                        "EVENT_NAME" => $this->arParams["EVENT"],
                        "LID" => SITE_ID,
                        "C_FIELDS" => $arEventFields['PROPERTY_VALUES']
                    )); 
                }
            }

        }
    }

    /**
     * Логика компонента
     */
    public function executeComponent()
    {
        try {
            Loader::includeModule('iblock');
            
            if (!$this->readDataFromCache()) {
                $this->getFields();

                $this->putDataToCache();
                // Заканчиваем кеширование
                $this->EndResultCache();
            }
            
            // Отправка идет без кеширования
            $this->requestForm();

            // Отправляем шаблон
            $this->includeComponentTemplate();
            
        } catch (Exception $e) {
            $this->AbortResultCache();
            ShowError($e->getMessage());
        }
    }
}
?>