<?php
/**
 * Шаблон вывода формы обратной связи
 *
 * Особенности:
 * - Поддержка AJAX и обычной отправки
 * - Валидация на клиенте и сервере
 * - Адаптивный дизайн
 */

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/** @var array $arResult */
/** @var array $arParams */
/** @var MyProject\Components\FeedbackForm $component */

$this->setFrameMode(true);

CJSCore::Init(array('ajax'));

$isAjax = ($arParams['AJAX_MODE'] == 'Y') ? true : false;
$formId = 'feedback-form-' . $component->randString();
$properties = $component->getProperties();
?>

<?php if (!$isAjax): ?>
    <!-- Контейнер формы для AJAX-обновления -->
    <div id="<?= $formId ?>">
<?php endif; ?>

    <div id="success" class="alert success d-none"><?= GetMessage('FB_SUCCESS_MESSAGE') ?></div>
<?php if ($arResult['status'] === 'success'): ?>
    <div class="alert success"><?= GetMessage('FB_SUCCESS_MESSAGE') ?></div>
<?php else: ?>
<?php if ($arResult['STATUS'] === 'ERROR'): ?>
    <div class="alert error"><?= htmlspecialcharsbx($arResult['MESSAGE']) ?></div>
<?php endif; ?>

    <form maction="" name="reviews" data-ajax="<?= $isAjax ? 'true' : 'false' ?>"
          onsubmit="submitFeedbackForm(event)">
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="action" value="send">
        <input type="hidden" name="IBLOCK_ID" value="<?= $arParams['IBLOCK_ID'] ?>">

        <!-- Основное поле NAME -->
        <div class="form-field">
            <label>
                <?= GetMessage('FB_NAME_LABEL') ?>*
                <input type="text" name="NAME" required>
            </label>
        </div>

        <!-- Динамические поля из свойств -->
        <?php foreach ($properties as $code => $prop): ?>
            <div class="form-field">
                <label>
                    <?= htmlspecialcharsbx($prop['NAME']) ?>
                    <?php if ($prop['REQUIRED']): ?>*<?php endif; ?>

                    <?php switch ($prop['USER_TYPE']):
                        case 'HTML': ?>
                            <textarea
                                    name="<?= $code ?>"
                                <?= $prop['REQUIRED'] ? 'required' : '' ?>
                                rows="5"
                            ></textarea>
                            <?php break;

                        case 'DateTime': ?>
                            <input
                                    type="datetime-local"
                                    name="<?= $code ?>"
                                <?= $prop['REQUIRED'] ? 'required' : '' ?>
                            >
                            <?php break;

                        default: ?>
                            <input
                                    type="text"
                                    name="<?= $code ?>"
                                <?= $prop['REQUIRED'] ? 'required' : '' ?>
                            >
                        <?php endswitch; ?>
                </label>
            </div>
        <?php endforeach; ?>

        <button type="submit"><?= GetMessage('FB_SUBMIT_BTN') ?></button>
    </form>

    <!-- JavaScript обработчик -->
    <script>
        function submitFeedbackForm(e) {
            e.preventDefault();
            const form = e.target;

            <?php if ($isAjax):?>
            // AJAX-отправка через BX.ajax
            BX.ajax.runComponentAction(
                '<?=$component->getName()?>',
                'send',
                {
                    mode: 'class',
                    data: new FormData(form),
                    signedParameters: '<?=$component->getSignedParameters()?>'
                }
            ).then(res => {
                BX.removeClass(
                    BX('success'),
                    'd-none'
                );
                BX.closeWait();
                //BX.replace(form.parentNode, res.data.html);
            }).catch(err => {
                alert(err.errors.join('\n'));
            });
            <?php else:?>
            // Обычная отправка формы
            form.submit();
            <?endif;?>
        }
    </script>
<?php endif; ?>

<?php if (!$isAjax): ?>
    </div>
<?php endif; ?>