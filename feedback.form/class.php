<?php

namespace MSAgeev\Components;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Main\Engine\ActionFilter\Csrf;
use Bitrix\Main\Engine\Contract\Controllerable;

/**
 * Компонент формы обратной связи
 *
 * Основные функции:
 * - AJAX-отправка данных
 * - Валидация полей
 * - Сохранение в инфоблок
 * - Управление кэшированием
 */
class FeedbackForm extends \CBitrixComponent implements Controllerable
{
    /**
     * Подготовка параметров компонента
     */
    public function onPrepareComponentParams($params): array
    {
        // Приведение типов и установка значений по умолчанию
        $params['IBLOCK_ID'] = (int)$params['IBLOCK_ID'];
        $params['CACHE_TIME'] = (int)($params['CACHE_TIME'] ?? 3600);
        return $params;
    }

    /**
     * Основной метод выполнения компонента
     */
    public function executeComponent(): void
    {
        try {
            $this->checkModules();

            // Запуск кэширования
            if ($this->StartResultCache()) {
                $this->loadProperties();
                $this->includeComponentTemplate();
                $this->EndResultCache();
            }
        } catch (\Exception $e) {
            $this->AbortResultCache();
            ShowError($e->getMessage());
        }
    }

    /**
     * Проверка необходимых модулей
     */
    private function checkModules(): void
    {
        if (!Loader::includeModule('iblock')) {
            throw new \Exception(GetMessage('FB_MODULE_ERROR'));
        }
    }

    private function loadProperties(): void
    {
        $rsProps = PropertyTable::getList([
            'filter' => [
                'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
                'ACTIVE' => 'Y'
            ],
            'order' => ['SORT' => 'ASC']
        ]);

        while ($prop = $rsProps->fetch()) {
            $this->properties[$prop['CODE']] = [
                'NAME' => $prop['NAME'],
                'TYPE' => $prop['PROPERTY_TYPE'],
                'REQUIRED' => $prop['IS_REQUIRED'] === 'Y',
                'USER_TYPE' => $prop['USER_TYPE']
            ];
        }

        if (empty($this->properties)) {
            throw new \Exception(GetMessage('FB_NO_PROPS'));
        }
    }

    /**
     * Валидация входных данных
     */
    private function validate(string $code, $value, array $prop): void
    {
        if ($prop['REQUIRED'] && empty(trim($value))) {
            throw new \Exception(sprintf(
                GetMessage('FB_FIELD_REQUIRED'),
                $prop['NAME']
            ));
        }

        // Дополнительные проверки по типам
        switch ($prop['USER_TYPE']) {
            case 'Email':
                if (!check_email($value)) {
                    throw new \Exception(GetMessage('FB_INVALID_EMAIL'));
                }
                break;
        }
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Очистка кэша при изменении данных
     */
    private function clearCache(): void
    {
        $taggedCache = \Bitrix\Main\Application::getInstance()->getTaggedCache();
        $taggedCache->clearByTag('iblock_id_' . $this->arParams['IBLOCK_ID']);
    }

    /**
     * Конфигурация AJAX-действий
     */
    public function configureActions(): array
    {
        return [
            'send' => [
                'prefilters' => [new Csrf()]
            ]
        ];
    }

    /**
     * AJAX-обработчик отправки формы
     */
    public function sendAction(): array
    {
        try {
            $this->checkModules();
            $el = new \CIBlockElement;
            // Сохранение элемента инфоблока
            $fields = [
                'IBLOCK_ID' => $this->request->get('IBLOCK_ID'),
                'NAME' => $this->request->get('NAME'),
                'ACTIVE' => 'N',
                'PROPERTY_VALUES' => []
            ];

            foreach ($this->properties as $code => $prop) {
                $value = $this->request->get($code);
                $this->validateField($code, $value, $prop);
                $fields['PROPERTY_VALUES'][$code] = $value;
            }

            if ($el->add($fields)) {
                return ['STATUS' => 'SUCCESS'];
            }

            throw new \Exception($el->LAST_ERROR);
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
