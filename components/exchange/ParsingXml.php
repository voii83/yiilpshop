<?php

namespace app\components\exchange;

use app\components\debug\Debug;
use app\components\utils\Translation;
use app\models\OfferPhoto;
use app\models\OfferProperty;
use app\models\OfferPropertyMatching;
use app\models\OfferPropertyValue;
use app\models\OfferRests;
use app\models\Photo;
use app\models\Product;
use app\models\Offer;
use app\models\PriceType;
use app\models\ProductCategory;
use app\models\ProductCategoryMatching;
use app\models\ProductPrice;
use app\models\ProductProperty;
use app\models\ProductPropertyMatching;
use app\models\ProductPropertyValue;
use app\models\Warehouse;

class ParsingXml
{
    public static function dataTypeInFile($filePath)
    {
        if (file_exists($filePath)) {
            $xml = simplexml_load_file($filePath);

            // Файл с деревом категорий товаров, типов цен, складов
            if ($xml->Классификатор->Группы) {
                //Debug::debugInFile('parsCategories', \Yii::$app->basePath."/upload/catalog1c/debug/1.txt");
                self::parsCategories($xml);
            }

            // Файл с названиями свойств и их значениями
            if ($xml->Классификатор->Свойства) {
                //Debug::debugInFile('parsProperties', \Yii::$app->basePath."/upload/catalog1c/debug/1.txt");
                self::parsProperties($xml);
            }

            // Файл с товарами
            if ($xml->Каталог->Товары) {
                //Debug::debugInFile('parsProduct', \Yii::$app->basePath."/upload/catalog1c/debug/1.txt");
                self::parsProduct($xml);
            }

            // Файл с ценами
            if ($xml->ПакетПредложений->Предложения->Предложение->Цены) {
                //Debug::debugInFile('parsPrices', \Yii::$app->basePath."/upload/catalog1c/debug/1.txt");
                self::parsPrices($xml);
            }

            // Файл с остатками товара на складах
            if ($xml->ПакетПредложений->Предложения->Предложение->Остатки) {
                //Debug::debugInFile('parsRests', \Yii::$app->basePath."/upload/catalog1c/debug/1.txt");
                self::parsRests($xml);
            }
        }
    }

    /* Заполняются таблицы:
        - product_category
        - price_type
        - warehouse */

    private static function parsCategories($xml)
    {
        $arrCategories = self::getCategoriesRecursive($xml->Классификатор->Группы);

        foreach ($arrCategories as $item) {
            $productCategory = ProductCategory::find()
                ->where(['uid' => current($item['uid'])])
                ->one();
            ($productCategory) ? $productCategory : $productCategory = new ProductCategory();
            $productCategory->uid = current($item['uid']);
            $productCategory->name = current($item['name']);
            $productCategory->uid_parent = ($item->uidParent) ? current($item['uidParent']) : $item['uidParent'];
            $productCategory->alias = $item['alias'];
            $productCategory->is_link = $item['isLink'];
            $productCategory->save();
        }

        foreach ($xml->Классификатор->ТипыЦен->ТипЦены as $item) {
            $priceType = PriceType::find()
                ->where(['uid' => $item->Ид])
                ->one();
            ($priceType) ? $priceType : $priceType = new PriceType();
            $priceType->uid = $item->Ид;
            $priceType->name = $item->Наименование;
            $priceType->currency = $item->Валюта;
            $priceType->tax_name = $item->Налог->Наименование;
            $priceType->is_in_ammount_tax = $item->Налог->УчтеноВСумме;
            $priceType->save();
        }

        foreach ($xml->Классификатор->Склады->Склад as $item) {
            $warehouse = Warehouse::find()
                ->where(['uid' => $item->Ид])
                ->one();
            ($warehouse) ? $warehouse : $warehouse = new Warehouse();
            $warehouse->uid = $item->Ид;
            $warehouse->name = $item->Наименование;
            $warehouse->save();
        }
    }

    private static function getCategoriesRecursive($categories, $uidParent = '', $nameParent='', &$arrOutCategories=[])
    {
        foreach ($categories->children() as $item) {

            $uid = $item->Ид;
            $isLink = false;

            ($nameParent == '') ? $name = $item->Наименование : $name = $nameParent.'-'.$item->Наименование;

            if (!isset($categories->Группа->Группы)) {
                $uid = '';
                $name = '';
                $isLink = true;
            }

            $arrOutCategories[] = [
                'uid' => $item->Ид,
                'name' => $item->Наименование,
                'uidParent' => $uidParent,
                'alias' => Translation::lineTranslation($nameParent.'-'.$item->Наименование),
                'isLink' => $isLink,
            ];

            if ($item->Группы) {
                self::getCategoriesRecursive($item->Группы, $uid, $name,$arrOutCategories);
            }
        }
        return $arrOutCategories;
    }

    /* Заполняются таблицы:
	    - product_property
	    - product_property_value
	    - offer_property
	    - offer_property_value */

    private static function parsProperties($xml)
    {
        foreach ($xml->Классификатор->Свойства->Свойство as $item) {

            if ($item->ТипЗначений == 'Строка') {

                $propertyName = ProductProperty::find()
                    ->where(['uid' => $item->Ид])
                    ->one();
                ($propertyName) ? $propertyName : $propertyName = new ProductProperty();
                $propertyName->uid = $item->Ид;
                $propertyName->name = $item->Наименование;
                $propertyName->alias = Translation::lineTranslation($item->Наименование);
                $propertyName->save();

            }

            if ($item->ТипЗначений == 'Справочник') {

                if (in_array($item->Наименование, self::behaviorsProperty()['productPropertyName'])) {
                    $modelPropertyName = 'app\models\ProductProperty';
                    $modelPropertyValue = 'app\models\ProductPropertyValue';
                }
                if (in_array($item->Наименование, self::behaviorsProperty()['offersPropertyName'])) {
                    $modelPropertyName = 'app\models\OfferProperty';
                    $modelPropertyValue = 'app\models\OfferPropertyValue';
                }

                // Запись названия свойств в БД
                $propertyName = $modelPropertyName::find()
                    ->where(['uid' => $item->Ид])
                    ->one();

                ($propertyName) ? $propertyName : $propertyName = new $modelPropertyName();

                $propertyName->uid = $item->Ид;
                $propertyName->name = $item->Наименование;
                $propertyName->alias = Translation::lineTranslation($item->Наименование);
                $propertyName->save();


                foreach ($item->ВариантыЗначений->Справочник as $value) {

                    // Запись значений свойств в БД
                    $propertyName = $modelPropertyName::find()
                        ->where(['uid' => $item->Ид])
                        ->one();

                    $propertyValue = $modelPropertyValue::find()
                        ->where(['id_property_name' => $propertyName->id])
                        ->andWhere(['uid' => $value->ИдЗначения])
                        ->one();

                    ($propertyValue) ? $propertyValue : $propertyValue = new $modelPropertyValue;
                    $propertyValue->uid = $value->ИдЗначения;
                    $propertyValue->value = $value->Значение;
                    if ($propertyName->id) {
                        $propertyValue->id_property_name = $propertyName->id;
                    }
                    $propertyValue->save();
                }
            }
        }
//        Debug::debugInFile($productPropertyName, \Yii::$app->basePath."/upload/catalog1c/debug/productPropertyName.txt");
    }

    private static function behaviorsProperty ()
    {
        return [
            'productPropertyName' => [
                'Модель',
                'Состав',
                'Материал',
                'Пол',
                'Возраст',
                'Коллекция',
                'Подкладка',
            ],
            'offersPropertyName' => [
                'Цвет для сайта',
                'Новинка',
                'Цвет',
                'Размер',
                'Использовать на сайте',
            ],
        ];
    }

    /* Заполняются таблицы:
	    - product
	    - product_property_matching
	    - offer
	    - offer_property_matching
	    - product_category_matching
	    - photo
	    - offer_photo */

    private static function parsProduct ($xml)
    {
        $propertyName = ProductProperty::find()
            ->where(['alias' => self::behaviorsGroupProduct()['propGroupProduct']])
            ->one();
        $groupPropertyUid = $propertyName['uid'];

        $propertyName = ProductProperty::find()
            ->where(['alias' => self::behaviorsGroupProduct()['propNameOfSite']])
            ->one();
        $nameOfSitePropertyUid = $propertyName['uid'];

        //$nameOfSite = '';

        foreach ($xml->Каталог->Товары->Товар as $item) {
            foreach ($item->ЗначенияСвойств->ЗначенияСвойства as $itemProperty) {
                if ($itemProperty->Ид == $nameOfSitePropertyUid) {
                    $nameOfSite = trim($itemProperty->Значение);
                }

                if ($itemProperty->Ид == $groupPropertyUid) {
                    if (empty($itemProperty->Значение)) { break; }

                    /* Заполняем таблицу product */
                    $product = Product::find()
                        ->where(['article_site' => trim($itemProperty->Значение)])
                        ->one();

                    ($product) ? $product : $product = new Product();
                    $product->article_site = trim($itemProperty->Значение);
                    $product->name = $nameOfSite;
                    $product->alias = Translation::lineTranslation(trim($itemProperty->Значение));
                    $product->save();



                    /* Заполняем таблицу offer */
//                    $product = Product::find()
//                        ->where(['article_site' => trim($itemProperty->Значение)])
//                        ->one();

                    $offer = Offer::find()
                        ->where(['uid' => $item->Ид])
                        ->one();

                    ($offer) ? $offer : $offer = new Offer();

                    $offer->uid = $item->Ид;
                    $offer->name = $item->Наименование;
                    $offer->id_product = $product->id;
                    $offer->save();

                    /* Заполняем таблицу photo и offer_photo*/
                    if (!empty($item->Картинка)) {
                        foreach ($item->Картинка as $itemPath) {

                            /* таблица photo */
                            $photo = Photo::find()
                            ->where(['path' => current($itemPath)])
                            ->one();

                            ($photo) ? $photo : $photo = new Photo();
                            $photo->path = $itemPath;
                            $photo->save();

                            /* таблица offer_photo */
                            $offerPhoto = OfferPhoto::find()
                                ->where(['id_offer' => $offer->id])
                                ->andWhere(['id_photo' => $photo->id])
                                ->one();

                            ($offerPhoto) ? $offerPhoto : $offerPhoto = new OfferPhoto();
                            $offerPhoto->id_offer = $offer->id;
                            $offerPhoto->id_photo = $photo->id;
                            $offerPhoto->save();
                        }
                    }

                    /* Заполняем таблицу product_category_matching */
                    if (!empty($item->Группы->Ид)) {
                        foreach ($item->Группы->Ид as $itemGroupUid) {

                            $productCategory = ProductCategory::find()
                                ->where(['uid' => current($itemGroupUid)])
                                ->one();

                            $productCategoryMatching = ProductCategoryMatching::find()
                                ->where(['id_category' => $productCategory->id])
                                ->one();

                            ($productCategoryMatching) ? $productCategoryMatching : $productCategoryMatching = new ProductCategoryMatching();
                            $productCategoryMatching->id_category = $productCategory->id;
                            $productCategoryMatching->id_product = $product->id;
                            $productCategoryMatching->save();
                        }

                    }
                }

                /* заполняем таблицу offer_property_matching и product_property_matching */
                $productPropery = ProductProperty::find()
                    ->where(['uid' => $itemProperty->Ид])
                    ->one();
                $offerProperty = OfferProperty::find()
                    ->where(['uid' => $itemProperty->Ид])
                    ->one();

                /* Таблица product_property_matching */
                if ($productPropery) {

                    $offer = Offer::find()
                        ->where(['uid' => $item->Ид])
                        ->one();

                    $productPropertyValue = ProductPropertyValue::find()
                        ->where(['uid' => $itemProperty->Значение])
                        ->one();

                    if ($offer && $productPropertyValue) {

                        $productPropertyMatching = ProductPropertyMatching::find()
                            ->where(['id_product' => $offer->id_product])
                            ->andWhere(['id_property' => $productPropery->id])
                            ->one();

                        ($productPropertyMatching) ? $productPropertyMatching : $productPropertyMatching = new ProductPropertyMatching();
                        $productPropertyMatching->id_product = $offer->id_product;
                        $productPropertyMatching->id_property = $productPropery->id;
                        $productPropertyMatching->id_property_value = $productPropertyValue->id;
                        $productPropertyMatching->save();
                    }
                }

                /* Таблица offer_property_matching */
                if ($offerProperty) {

                    $offer = Offer::find()
                        ->where(['uid' => $item->Ид])
                        ->one();

                    /* Если передаётся 'false', то 1с вообще не записывает в файл ни какого значения,
                       ставим принудительно false */
                    (empty($itemProperty->Значение)) ? $propertyValue = 'false' : $propertyValue = $itemProperty->Значение;

                    $offerPropertyValue = OfferPropertyValue::find()
                        ->where(['uid' => $propertyValue])
                        ->one();
                    if ($offer && $offerPropertyValue) {

                        $offerPropertyMatching = OfferPropertyMatching::find()
                            ->where(['id_offer' => $offer->id])
                            ->andWhere(['id_property' => $offerProperty->id])
                            ->one();

                        ($offerPropertyMatching) ? $offerPropertyMatching : $offerPropertyMatching = new OfferPropertyMatching();
                        $offerPropertyMatching->id_offer = $offer->id;
                        $offerPropertyMatching->id_property = $offerProperty->id;
                        $offerPropertyMatching->id_property_value = $offerPropertyValue->id;
                        $offerPropertyMatching->save();
                    }
                }
            }
        }
        //Debug::debugInFile($articleOfSite, \Yii::$app->basePath."/upload/catalog1c/debug/Products.txt");
    }

    private static function behaviorsGroupProduct ()
    {
        return [
            'propGroupProduct' => 'artikul-dlya-sayta',
            'propNameOfSite' => 'nazvanie-dlya-sayta',
        ];
    }

    /* Заполняются таблицы:
	    - product_price */

    private static function parsPrices($xml)
    {
        foreach ($xml->ПакетПредложений->Предложения->Предложение as $item) {
            foreach ($item->Цены->Цена as $itemPrice) {

                $priceType = PriceType::find()
                    ->where(['uid' => $itemPrice->ИдТипаЦены])
                    ->one();

                $offer = Offer::find()
                    ->where(['uid' => $item->Ид])
                    ->one();

                $productPrice = ProductPrice::find()
                    ->where(['id_product' => $offer->id_product])
                    ->andWhere(['id_price_type' => $priceType->id])
                    ->one();

                ($productPrice) ? $productPrice : $productPrice = new ProductPrice();
                $productPrice->id_product = $offer->id_product;
                $productPrice->id_price_type = $priceType->id;
                $productPrice->present_price = $itemPrice->Представление;
                $productPrice->unit_price = $itemPrice->ЦенаЗаЕдиницу;
                $productPrice->save();
            }
        }
    }

    /* Заполняются таблицы:
	    - offer_rests */

    private static function parsRests ($xml)
    {
        foreach ($xml->ПакетПредложений->Предложения->Предложение as $item) {
            foreach ($item->Остатки->Остаток->Склад as $itemRests) {

                $offer = Offer::find()
                    ->where(['uid' => $item->Ид])
                    ->one();

                $warehouse = Warehouse::find()
                    ->where(['uid' => $itemRests->Ид])
                    ->one();

                $offerRests = OfferRests::find()
                    ->where(['id_offer' => $offer->id])
                    ->andWhere(['id_warehouse' => $warehouse->id])
                    ->one();

                ($offerRests) ? $offerRests : $offerRests = new OfferRests();
                $offerRests->id_offer = $offer->id;
                $offerRests->id_warehouse = $warehouse->id;
                $offerRests->quantity = $itemRests->Количество;
                $offerRests->save();
            }
        }
    }

}