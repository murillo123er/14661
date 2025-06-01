<?php
if (!defined('_VALID_MOS') && !defined('_JEXEC')) die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

/**
 * Клас для роботи з категоріями товарів
 */
class p_tov_categ extends emAbstractObject {

    /**
     * Валідація перед додаванням категорії
     * @param array $d - дані категорії
     * @return bool
     */
    function validate_add(&$d) {
        global $emLogger, $EM_LANG;
        require_once(CLASSPATH . 'imageTools.class.php');
        $valid = true;

        // Перевірка: чи задано назву категорії
        if (empty($d["categ_name"])) {
            $emLogger->err($EM_LANG->_('EM_TOV_CATEG_ERR_NAME'));
            $valid = false;
        }

        // Перевірка іконки категорії (міні-зображення)
        if (!empty($d['categ_thumb_image_url'])) {
            if (substr($d['categ_thumb_image_url'], 0, 4) != "http") {
                $emLogger->err($EM_LANG->_('EM_TOV_IMAGEURL_MUSTBEGIN'));
                $valid = false;
            }
            $d["categ_thumb_image"] = $d['categ_thumb_image_url'];
        } else {
            // Якщо посилання не задане, перевірити локальний файл
            if (!emImageTools::validate_image($d, "categ_thumb_image", "categ")) {
                $valid = false;
            }
        }

        // Перевірка великого зображення категорії
        if (!empty($d['categ_full_image_url'])) {
            if (substr($d['categ_full_image_url'], 0, 4) != "http") {
                $emLogger->err($EM_LANG->_('EM_TOV_IMAGEURL_MUSTBEGIN'));
                return false;
            }
            $d["categ_full_image"] = $d['categ_full_image_url'];
        } else {
            if (!emImageTools::validate_image($d, "categ_full_image", "categ")) {
                $valid = false;
            }
        }

        return $valid;
    }

    /**
     * Валідація перед оновленням категорії
     * @param array $d - дані категорії
     * @return bool
     */
    function validate_update(&$d) {
        global $emLogger, $EM_LANG;
        require_once(CLASSPATH . 'imageTools.class.php');
        $valid = true;

        // Назва не може бути порожньою
        if (empty($d["categ_name"])) {
            $emLogger->err($EM_LANG->_('EM_TOV_CATEG_ERR_NAME'));
            $valid = false;
        }
        // Категорія не може бути власним батьком
        elseif ($d["categ_id"] == $d["categ_prt_id"]) {
            $emLogger->err($EM_LANG->_('EM_TOV_CATEG_ERR_PRT'));
            $valid = false;
        }
        $bd = new p_BD;
        $q = "SELECT categ_thumb_image, categ_full_image FROM #__em_categ WHERE categ_id=" . (int)$d["categ_id"];
        $bd->query($q); $bd->next_record();

        // Валідація іконки
        if (!empty($d['categ_thumb_image_url'])) {
            if (substr($d['categ_thumb_image_url'], 0, 4) != "http") {
                $emLogger->err($EM_LANG->_('EM_TOV_IMAGEURL_MUSTBEGIN'));
                $valid = false;
            }
            if ($bd->f("categ_thumb_image") && substr($bd->f("categ_thumb_image"), 0, 4) != "http") {
                $_REQUEST["categ_thumb_image_curr"] = $bd->f("categ_thumb_image");
                $d["categ_thumb_image_action"] = "delete";
                if (!emImageTools::validate_image($d, "categ_thumb_image", "categ")) {
                    return false;
                }
            }
            $d["categ_thumb_image"] = $d['categ_thumb_image_url'];
        } else {
            if (!emImageTools::validate_image($d, "categ_thumb_image", "categ")) {
                $valid = false;
            }
        }

        // Валідація великого зображення
        if (!empty($d['categ_full_image_url'])) {
            if (substr($d['categ_full_image_url'], 0, 4) != "http") {
                $emLogger->err($EM_LANG->_('EM_TOV_IMAGEURL_MUSTBEGIN'));
                return false;
            }
            if ($bd->f("categ_full_image") && substr($bd->f("categ_full_image"), 0, 4) != "http") {
                $_REQUEST["categ_full_image_curr"] = $bd->f("categ_full_image");
                $d["categ_full_image_action"] = "delete";
                if (!emImageTools::validate_image($d, "categ_full_image", "categ")) {
                    return false;
                }
            }
            $d["categ_full_image"] = $d['categ_full_image_url'];
        } else {
            if (!emImageTools::validate_image($d, "categ_full_image", "categ")) {
                $valid = false;
            }
        }
        return $valid;
    }

    /**
     * Валідація перед видаленням категорії
     * @param int $categ_id - ID категорії
     * @param array $d - дані категорії
     * @return bool
     */
    function validate_delete($categ_id, &$d) {
        global $emLogger, $EM_LANG;
        $bd = new p_BD;
        require_once(CLASSPATH . 'imageTools.class.php');

        // Перевірка чи передано ID
        if (empty($categ_id)) {
            $emLogger->err($EM_LANG->_('EM_TOV_CATEG_ERR_DELETE_SELECT'));
            return false;
        }

        // Перевірка чи у категорії є підкатегорії
        $q = "SELECT * FROM #__em_categ_xref WHERE categ_prt_id='$categ_id'";
        $bd->setQuery($q); $bd->query();
        if ($bd->next_record()) {
            $emLogger->err($EM_LANG->_('EM_TOV_CATEG_ERR_DELETE_CHDREN'));
            return false;
        }

        // Перевірка зображень категорії
        $q = "SELECT categ_thumb_image, categ_full_image FROM #__em_categ WHERE categ_id='$categ_id'";
        $bd->query($q); $bd->next_record();

        if (!stristr($bd->f("categ_thumb_image"), "http")) {
            $_REQUEST["categ_thumb_image_curr"] = $bd->f("categ_thumb_image");
            $d["categ_thumb_image_action"] = "delete";
            if (!emImageTools::validate_image($d, "categ_thumb_image", "categ")) {
                $emLogger->err($EM_LANG->_('EM_TOV_CATEG_ERR_DELETE_IMAG'));
                return false;
            }
        }

        if (!stristr($bd->f("categ_full_image"), "http")) {
            $_REQUEST["categ_full_image_curr"] = $bd->f("categ_full_image");
            $d["categ_full_image_action"] = "delete";
            if (!emImageTools::validate_image($d, "categ_full_image", "categ")) {
                return false;
            }
        }
        return true;
    }

}
?>
