<?php

namespace MSAgeev\Components;

use Bitrix\Main\Loader;
use Bitrix\Main\UI\PageNavigation;
use Bitrix\Iblock\Elements\ElementReviewsTable;
use Bitrix\Main\Data\Cache;
use Bitrix\Iblock\PropertyTable;

/**
 * Компонент вывода списка отзывов
 *
 * Основные функции:
 * - Постраничная навигация
 * - Кэширование данных
 * - Фильтрация по активным элементам
 */
class FeedbackList extends \CBitrixComponent
{
    /**
     * Инициализация параметров
     */
    public function onPrepareComponentParams($params): array
    {
        $params['IBLOCK_ID'] = (int)$params['IBLOCK_ID'];
        $params['PAGE_SIZE'] = (int)($params['PAGE_SIZE'] ?? 10);
        $params['CACHE_TIME'] = (int)($params['CACHE_TIME'] ?? 3600);
        return parent::onPrepareComponentParams($params);
    }

    /**
     * Основной метод выполнения компонента
     */
    public function executeComponent(): void
    {

        try {
            $this->checkModules();
            $this->loadProperties();

            // Инициализация кэширования
            $cache = Cache::createInstance();
            $cacheId = md5(serialize($this->arParams).$_GET['nav-reviews']);
            $cachePath = '/feedback_list/' . $this->arParams['IBLOCK_ID'];

            if ($cache->initCache($this->arParams['CACHE_TIME'], $cacheId, $cachePath)) {
                // Получение данных из кэша
                $this->arResult = $cache->getVars();
            } elseif ($cache->startDataCache()) {
                // Получение и кэширование данных
                $this->getItems();
                $cache->endDataCache($this->arResult);
            }

            $this->includeComponentTemplate();

            $this->SetResultCacheKeys(['NAV_OBJECT']);
        } catch (\Exception $e) {
            $cache->abortDataCache();
            ShowError($e->getMessage());
        }
    }

    /**
     * Проверка необходимых модулей
     */
    private function checkModules(): void
    {
        if (!Loader::includeModule('iblock')) {
            throw new \Exception(GetMessage('FL_MODULE_ERROR'));
        }
    }

    /**
     * Получение элементов инфоблока
     */
    private function getItems(): void
    {
        $nav = $this->initNavigation();
        $fields = [
            'ID',
            'NAME',
            'DATE_CREATE'
        ];

        // Получаем свойства
        $properties = $this->getProperties();
        foreach ($properties as $prop => $arProp) {
            $fields[$prop . '_VALUE'] = $prop . '.VALUE';
            $this->arResult['TITLES_PROP'][$prop . '_VALUE'] = $arProp['NAME'];
        }

        // Формируем запрос через ORM
        $query = ElementReviewsTable::query()
            ->setSelect($fields, 'cnt')
            ->where('IBLOCK_ID', $this->arParams['IBLOCK_ID'])
            ->where('ACTIVE', 'Y')
            ->setOrder(['DATE_CREATE' => 'DESC'])
            ->setOffset($nav->getOffset())
            ->setLimit($nav->getLimit());

        // Получаем общее количество элементов (используем тот же фильтр, что и в основном запросе)
        $cnt = ElementReviewsTable::getCount([
            '=IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
            '=ACTIVE' => 'Y'
        ]);

        // Устанавливаем общее количество записей для навигации
        $nav->setRecordCount($cnt);

        // Выполняем запрос
        $result = $query->exec();
        $this->arResult['ITEMS'] = $result->fetchAll();
        // Передаем объект навигации в шаблон
        $this->arResult['NAV_OBJECT'] = $nav;
    }

    /**
     * Инициализация постраничной навигации
     */
    private function initNavigation(): PageNavigation
    {
        $nav = new \Bitrix\Main\UI\PageNavigation('nav-reviews');
        $nav->allowAllRecords(false)
            ->setPageSize($this->arParams['PAGE_SIZE'] ?: 10)
            ->initFromUri();

        return $nav;
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

    public function getProperties(): array
    {
        return $this->properties;
    }
}
