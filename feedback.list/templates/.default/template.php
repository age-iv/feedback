<?php
/**
 * Шаблон вывода списка отзывов
 *
 * Особенности:
 * - Адаптивная карточка для каждого отзыва
 * - Форматирование даты
 * - Поддержка постраничной навигации
 */

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/** @var array $arResult */
/** @var \MyProject\Components\FeedbackList $component */
if (empty($arResult['ITEMS'])): ?>
    <!-- Сообщение при отсутствии отзывов -->
    <div class="alert alert-info"><?= GetMessage('FL_NO_REVIEWS') ?></div>
<?php else: ?>
    <div class="reviews-list">
        <?php foreach ($arResult['ITEMS'] as $item): ?>
            <!-- Карточка отзыва -->
            <div class="review-item card mb-3">
                <div class="card-body">
                    <!-- Заголовок с именем -->
                    <h5 class="card-title"><?= htmlspecialcharsbx($item['NAME']) ?></h5>

                    <!-- Дата создания -->
                    <div class="text-muted small mb-2">
                        <?= FormatDate('j F Y', MakeTimeStamp($item['DATE_CREATE'])) ?>
                    </div>

                    <!-- Блок достоинств -->
                    <?php foreach ($item as $code => $field): ?>
                        <?php if (strstr($code, '_VALUE') !== false && $field): ?>
                            <div class="review-plus">
                                <strong><?= $arResult['TITLES_PROP'][$code] ?>:</strong>
                                <div><?= nl2br(htmlspecialcharsbx($field)) ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Постраничная навигация -->
    <?
    $APPLICATION->IncludeComponent(
        "bitrix:main.pagenavigation",
        "",
        array(
            // передаем объект
            "NAV_OBJECT" => $arResult['NAV_OBJECT'],
            // включение/отключение ЧПУ или GET
            "SEF_MODE" => "N",
        ),
        false
    );
    ?>
<?php endif; ?>