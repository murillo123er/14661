<?php
// Захист від прямого доступу до файлу
if (!defined('_VALID_MOS') && !defined('_JEXEC')) die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

/**
 * Клас товару (Product)
 */
class p_tov extends emAbstractObject {
    var $_key = 'tov_id';
    var $_table_name = '#__em_tov';

    /**
     * Перевірка коректності даних товару при додаванні/редагуванні
     */
    function validate(&$d) {
        global $emLogger, $database, $perm, $EM_LANG;
        require_once(CLASSPATH . 'imageTools.class.php');
        $valid = true;
        $bd = new p_BD;

        // Перевірка унікальності SKU
        $q = "SELECT tov_id, tov_thumb_image, tov_full_image FROM #__em_tov WHERE tov_sku='" . $d["tov_sku"] . "'";
        $bd->setQuery($q); $bd->query();
        if ($bd->next_record() && ($bd->f("tov_id") != $d["tov_id"])) {
            $emLogger->err("A Tov with the SKU " . $d['tov_sku'] . " already exists.");
            $valid = false;
        }

        // Перевірка знижки
        if (!empty($d['tov_discount_id'])) {
            if ($d['tov_discount_id'] == "override") {
                $d['is_percent'] = "0";
                if (PAYMENT_DISCOUNT_BEFORE == '1') {
                    $d['amount'] = (float)$d['tov_price'] - (float)$d['discounted_price_override'];
                } else {
                    $d['amount'] = (float)$d['tov_price_incl_tax'] - (float)$d['discounted_price_override'];
                }
                $d['start_date'] = date('Y-m-d');
                require_once(CLASSPATH . 'p-tov_discount.php');
                $p_tov_discount = new p_tov_discount;
                $p_tov_discount->add($d);
                $d['tov_discount_id'] = $database->insertid();
                emRequest::setVar('tov_discount_id', $d['tov_discount_id']);
            }
        }

        // Якщо не вказано виробника - ставимо 1 (невідомий)
        if (empty($d['manuf_id'])) {
            $d['manuf_id'] = "1";
        }

        // Перевірка наявності SKU
        if (empty($d["tov_sku"])) {
            $emLogger->err($EM_LANG->_('EM_TOV_MISSING_SKU', false));
            $valid = false;
        }

        // Перевірка наявності назви
        if (!$d["tov_name"]) {
            $emLogger->err($EM_LANG->_('EM_TOV_MISSING_NAME', false));
            $valid = false;
        }

        // Перевірка наявності дати доступності
        if (empty($d["tov_avbl_date"])) {
            $emLogger->err($EM_LANG->_('EM_TOV_MISSING_AVAILDATE', false));
            $valid = false;
        }

        // Формування timestamp дати доступності
        $day = (int)substr($d["tov_avbl_date"], 8, 2);
        $month = (int)substr($d["tov_avbl_date"], 5, 2);
        $year = (int)substr($d["tov_avbl_date"], 0, 4);
        $d["tov_avbl_date_timestamp"] = mktime(0, 0, 0, $month, $day, $year);

        // Перевірка категорій (якщо товар не є дочірнім)
        if (!$d["tov_prt_id"]) {
            if (empty($d['tov_categories']) || !is_array(@$d['tov_categories'])) {
                $d['tov_categories'] = explode('|', $d['categ_ids']);
            }
            if (sizeof(@$d["tov_categories"]) < 1) {
                $emLogger->err($EM_LANG->_('EM_TOV_MISSING_CATEG'));
                $valid = false;
            }
        }

        // Перевірка зображень (іконки)
        if (!empty($d['tov_thumb_image_url'])) {
            if (substr($d['tov_thumb_image_url'], 0, 4) != "http") {
                $emLogger->err($EM_LANG->_('EM_TOV_IMAGEURL_MUSTBEGIN', false));
                $valid = false;
            }
            if ($bd->f("tov_thumb_image") && substr($bd->f("tov_thumb_image"), 0, 4) != "http") {
                $_REQUEST["tov_thumb_image_curr"] = $bd->f("tov_thumb_image");
                $d["tov_thumb_image_action"] = "delete";
                if (!emImageTools::validate_image($d, "tov_thumb_image", "tov")) {
                    return false;
                }
            }
            $d["tov_thumb_image"] = $d['tov_thumb_image_url'];
        } else {
            if (!emImageTools::validate_image($d, "tov_thumb_image", "tov")) {
                $valid = false;
            }
        }

        // Перевірка зображень (велике зображення)
        if (!empty($d['tov_full_image_url'])) {
            if (substr($d['tov_full_image_url'], 0, 4) != "http") {
                $emLogger->err($EM_LANG->_('EM_TOV_IMAGEURL_MUSTBEGIN', false));
                return false;
            }
            if ($bd->f("tov_full_image") && substr($bd->f("tov_full_image"), 0, 4) != "http") {
                $_REQUEST["tov_full_image_curr"] = $bd->f("tov_full_image");
                $d["tov_full_image_action"] = "delete";
                if (!emImageTools::validate_image($d, "tov_full_image", "tov")) {
                    return false;
                }
            }
            $d["tov_full_image"] = $d['tov_full_image_url'];
        } else {
            if (!emImageTools::validate_image($d, "tov_full_image", "tov")) {
                $valid = false;
            }
        }

        // Обрізаємо зайві ; у атрибутах
        if (isset($d["tov_advanced_attribute"])) {
            if (';' == substr($d["tov_advanced_attribute"], -1)) {
                $d["tov_advanced_attribute"] = substr($d["tov_advanced_attribute"], 0, -1);
            }
        }
        if (isset($d["tov_custom_attribute"])) {
            if (';' == substr($d["tov_custom_attribute"], -1)) {
                $d["tov_custom_attribute"] = substr($d["tov_custom_attribute"], 0, -1);
            }
        }

        // Дефолтні значення для прапорців
        $d["clone_tov"] = empty($d["clone_tov"]) ? "N" : "Y";
        $d["tov_publish"] = empty($d["tov_publish"]) ? "N" : "Y";
        $d["tov_special"] = empty($d["tov_special"]) ? "N" : "Y";

        // Відображення різних опцій у товарі
        $d['display_headers'] = emGet($d, 'display_headers', 'Y') == 'Y' ? 'Y' : 'N';
        $d['tov_list_chd'] = emGet($d, 'tov_list_chd', 'Y') == 'Y' ? 'Y' : 'N';
        $d['display_use_prt'] = emGet($d, 'display_use_prt', 'Y') == 'Y' ? 'Y' : 'N';
        $d['tov_list_type'] = emGet($d, 'tov_list_type', 'Y') == 'Y' ? 'Y' : 'N';
        $d['display_desc'] = emGet($d, 'display_desc', 'Y') == 'Y' ? 'Y' : 'N';

        // Опції для списків
        if (@$d['tov_list'] == "Y") {
            if ($d['list_style'] == "one")
                $d['tov_list'] = "Y";
            else
                $d['tov_list'] = "YM";
        } else {
            $d['tov_list'] = "N";
        }

        // Додаткові опції (кількість, дочірні елементи)
        $d['quantity_opt'] = p_tov::set_quantity_opt($d);
        $d['chd_opt'] = p_tov::set_chd_opt($d);

        // Рівні мінімального/максимального замовлення
        $d['order_levels'] = emRequest::getInt('min_order_level') . "," . emRequest::getInt('max_order_level');

        return $valid;
    }

    /**
     * Перевірка перед видаленням товару
     */
    function validate_delete($tov_id, &$d) {
        global $emLogger, $EM_LANG;
        require_once(CLASSPATH . 'imageTools.class.php');

        if (empty($tov_id)) {
            $emLogger->err($EM_LANG->_('EM_TOV_SPECIFY_DELETE', false));
            return false;
        }
        // Одержати імена картинок з бази
        $bd = new p_BD;
        $q = "SELECT tov_thumb_image, tov_full_image FROM #__em_tov WHERE tov_id='$tov_id'";
        $bd->setQuery($q); $bd->query();
        $bd->next_record();

        // Перевірка і видалення іконки
        if (!stristr($bd->f("tov_thumb_image"), "http")) {
            $_REQUEST["tov_thumb_image_curr"] = $bd->f("tov_thumb_image");
            $d["tov_thumb_image_action"] = "delete";
            if (!emImageTools::validate_image($d, "tov_thumb_image", "tov")) {
                $emLogger->err($EM_LANG->_('EM_TOV_IMGDEL_FAILED', false));
                return false;
            }
        }
        // Перевірка і видалення великого зображення
        if (!stristr($bd->f("tov_full_image"), "http")) {
            $_REQUEST["tov_full_image_curr"] = $bd->f("tov_full_image");
            $d["tov_full_image_action"] = "delete";
            if (!emImageTools::validate_image($d, "tov_full_image", "tov")) {
                return false;
            }
        }
        return true;
    }

    // Тут можуть бути додаткові методи класу...
}
?>
