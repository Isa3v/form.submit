<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();?>

<?
// .start AJAX response
if($arResult["IS_AJAX"] == "Y"){

    if($arResult['SEND'] == true){
        $responseText = '
            <div class="p-5">
                <h3>Спасибо!</h3>
                <p>Наш менеджер обязательно с вами свяжется.</p>
            </div>
        ';
    }

    global $APPLICATION;
    $APPLICATION->RestartBuffer();
    $response = [];
    if(!empty($arResult['ERROR'])){
        $response['send'] = false;
        $response['error'] = $arResult['ERROR'];
    }
    if($arResult['SEND'] == true){
        $response['send'] = true;
        $response['content'] = $responseText;
    }
    echo \Bitrix\Main\Web\Json::encode($response, JSON_UNESCAPED_SLASHES);
    die();
}
// .end AJAX response
?>

<section class="callback-wrap">
        <div class="container">
            <div class="short-title-wrap">
                <h2 class="short-title">
                    ОБРАТНЫЙ ЗВОНОК
                </h2>
            </div>
        </div>
        <div class="container callback-container">
            <form method="post" class="callback-wrap-nomodal">
                <div class="row">
                    <?if(isset($arResult['FIELDS']['SALON_LIST']['VALUES'])){?>
                        <div class="col-md-6 col-lg-4">
                            <div class="callback-title"><?=$arResult['FIELDS']['SALON_LIST']['NAME']?></div>
                            <?foreach ($arResult['FIELDS']['SALON_LIST']['VALUES'] as $key => $arValue) {?>
                                <div class="radio">
                                    <label>
                                        <input name="SALON_LIST" type="radio" value="<?=$arValue['ID']?>" required /> <?=$arValue['VALUE']?>
                                    </label>
                                </div>
                            <?}?>
                        </div>
                    <?}?>

                    <div class="col-md-6 col-lg-4">
                        <div class="callback-form">
                            <input type="hidden" name="webform" value="<?=$arParams['WEBFORM_ID']?>">
                            <?=bitrix_sessid_post()?>
                                <?foreach ($arResult['FIELDS'] as $key => $field) {?>
                                    <?if($field['CODE'] != "SALON_LIST"){?>

                                        <?if($field['CODE'] == "EMAIL"){?>
                                            <input type="text" <?if($field['IS_REQUIRED']){?>required<?}?> name="<?=$field['CODE']?>" pattern="^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}$" placeholder="<?=$field['NAME']?>">
                                        <?}elseif($field['CODE'] == "PHONE"){?> 
                                            <input
                                                type="text" <?if($field['IS_REQUIRED']){?>required<?}?> 
                                                name="<?=$field['CODE']?>"
                                                class="vf-phoneDash"
                                                data-type="phone"
                                                data-visit="false"
                                                data-inputmask="'mask':'+7 (999) 999-99-99'"
                                                placeholder="+7 (999) 999-99-99">
                                        <?}else{?>
                                            <input type="text" <?if($field['IS_REQUIRED']){?>required<?}?> name="<?=$field['CODE']?>" placeholder="<?=$field['NAME']?>">
                                        <?}?>

                                    <?}?>
                                <?}?>
                            
                            <button type="submit" class="subscribe-send-btn vf-submit"><?=(!empty($arParams['BTN_NAME']) ? $arParams['BTN_NAME'] :'Подписаться')?></button>
                            <span>Нажимая на кнопку «Отправить», вы даете согласие на обработку персональных данных. Подробнее.</span>
                        </div>
                    </div>
                </div>
            </form>
        </div>

</section>


<script>
    $("form.callback-wrap-nomodal").submit(function(e) {
        e.preventDefault(); 
        var form = this,
        url = $(form).attr('action');
        $.ajax({
            type: "POST",
            url: url,
            dataType: "json",
            data: $(form).serialize(),
            success: function(data){

                if(data.send == true){
                    $(form).parent().html(data.content);
                }else{
                    if(data.error){
                        $.each(data.error, function(field, value){
                            console.log(data.error);
                            $(form).find('[name='+field+']')[0].setCustomValidity(value);
                            $(form).find('[name='+field+']')[0].reportValidity();
                        });
                    }

                }
            }
        });
    });
</script>