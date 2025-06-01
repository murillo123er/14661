<?php
// Захист від прямого доступу до файлу
if (!defined('_VALID_MOS') && !defined('_JEXEC')) die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');
mm_showMyFileName(__FILE__);

// Підключення основних класів і модулів
require_once(CLASSPATH . "p-tov.php");
$p_tov = new p_tov();
require_once(CLASSPATH . "p-tov_categ.php");
$p_tov_categ = new p_tov_categ();
require_once(CLASSPATH . "p-tov_files.php");
require_once(CLASSPATH . "p-reviews.php");
require_once(CLASSPATH . "imageTools.class.php");
require_once(CLASSPATH . "PEAR/Table.php");
require_once(CLASSPATH . 'p-tov_attribute.php');
$p_tov_attribute = new p_tov_attribute();

$Itemid = $sess->getShItid();

// Вхідні дані фільтрів пошуку
$keyword1 = $emInputFilter->safeSQL(urldecode(emGet($_REQUEST, 'keyword1', null)));
$keyword2 = $emInputFilter->safeSQL(urldecode(emGet($_REQUEST, 'keyword2', null)));
$src_op = $emInputFilter->safeSQL(emGet($_REQUEST, 'src_op', null));
$src_limiter = $emInputFilter->safeSQL(emGet($_REQUEST, 'src_limiter', null));
if (empty($categ_id)) $categ_id = $src_categ;
$default['categ_flypage'] = FLYPAGE;

// Об'єкти для роботи з БД
$bd_browse = new p_BD;
$bdp = new p_BD;

require_once(PAGEPATH . "magaz_browse_queries.php");

// Основний запит для підрахунку кількості товарів
$bd_browse->query($count);

$num_rows = $bd_browse->f("num_rows");
if ($limitstart > 0 && $limit >= $num_rows) {
    $list = str_replace('LIMIT ' . $limitstart, 'LIMIT 0', $list);
}

// Вивід назви категорії, якщо категорія вибрана
if ($categ_id) {
    $bd->query("SELECT categ_id, categ_name FROM #__em_categ WHERE categ_id='$categ_id'");
    $bd->next_record();
    $categ_name = magazMakeHtmlSafe($bd->f('categ_name'));
    $mainframe->setPageTitle($bd->f("categ_name"));

    $desc = $p_tov_categ->get_description($categ_id);
    $desc = emCommonHTML::ParseContentByPlugins($desc);
    $mainframe->prependMetaTag("description", substr(strip_tags($desc), 0, 255));
}

// Вивід повідомлення, якщо результати не знайдено
if ($num_rows == 0 && (!empty($keyword) || !empty($keyword1))) {
    echo $EM_LANG->_('PMAGAZ_NO_SRC_RESULT');
} elseif ($num_rows == 0 && empty($tov_type_id) && !empty($chd_list)) {
    echo $EM_LANG->_('EMPTY_CATEG');
}
// Якщо знайдено лише один товар - редірект на сторінку товару
elseif ($num_rows == 1 && (!empty($keyword) || !empty($keyword1))) {
    $bd_browse->query($list);
    $bd_browse->next_record();
    $flypage = $bd_browse->sf("categ_flypage") ? $bd_browse->sf("categ_flypage") : FLYPAGE;
    $url_params = "page=magaz.tov_details&amp;flypage=$flypage&amp;tov_id=" .
        $bd_browse->f("tov_id") . "&amp;categ_id=" . $bd_browse->f("categ_id");
    emRedirect($sess->url($url_params, true, false));
} else {
    // Початок виводу списку категорій чи результатів пошуку
    $t = emTemplate::getInstance();

    // Формуємо заголовок і опис сторінки для категорії/виробника/пошуку/всі товари
    if ($categ_id) {
        $browsepage_lbl = $categ_name;
        $t->set('browsepage_lbl', $browsepage_lbl);
        $t->set('desc', $desc);

        $categ_chds = $p_tov_categ->get_chd_list($categ_id);
        $t->set('categories', $categ_chds);
        $navigation_chdlist = $t->fetch('common/categChdlist.t.php');
        $t->set('navigation_chdlist', $navigation_chdlist);

        $categ_list = array_reverse($p_tov_categ->get_navigation_list($categ_id));
        $pathway = $p_tov_categ->getPathway($categ_list);
        $em_mainframe->emAppendPathway($pathway);

        $t->set('categ_id', $categ_id);
        $t->set('categ_name', $categ_name);

        $browsepage_header = $t->fetch('browse/includes/browse_header_categ.t.php');
    } elseif ($manuf_id) {
        $bd->query("SELECT manuf_id, mf_name, mf_desc FROM #__em_manuf WHERE manuf_id='$manuf_id'");
        $bd->next_record();
        $mainframe->setPageTitle($bd->f("mf_name"));

        $browsepage_lbl = magazMakeHtmlSafe($bd->f("mf_name"));
        $t->set('browsepage_lbl', $browsepage_lbl);
        $browsepage_lbltext = $bd->f("mf_desc");
        $t->set('browsepage_lbltext', $browsepage_lbltext);
        $browsepage_header = $t->fetch('browse/includes/browse_header_manuf.t.php');
    } elseif ($keyword) {
        $mainframe->setPageTitle($EM_LANG->_('PMAGAZ_SRC_TITLE', false));
        $browsepage_lbl = $EM_LANG->_('PMAGAZ_SRC_TITLE') . ':' . magazMakeHtmlSafe($keyword);
        $t->set('browsepage_lbl', $browsepage_lbl);
        $browsepage_header = $t->fetch('browse/includes/browse_header_keyword.t.php');
    } else {
        $mainframe->setPageTitle($EM_LANG->_('PMAGAZ_BROWSE_LBL', false));
        $browsepage_lbl = $EM_LANG->_('PMAGAZ_BROWSE_LBL');
        $t->set('browsepage_lbl', $browsepage_lbl);
        $browsepage_header = $t->fetch('browse/includes/browse_header_all.t.php');
    }

    $t->set('browsepage_header', $browsepage_header);

    // Якщо пошук за параметрами
    if (!empty($tov_type_id) && @$_REQUEST['output'] != "pdf") {
        $t->set('p_tov_type', $p_tov_type);
        $t->set('tov_type_id', $tov_type_id);
        $parameter_form = $t->fetch('browse/includes/browse_srcparameter_form.t.php');
    } else {
        $parameter_form = '';
    }
    $t->set('parameter_form', $parameter_form);

    // Відображення лімітбокса та верхньої навігації
    $show_limitbox = ($num_rows > 5 && @$_REQUEST['output'] != "pdf");
    $t->set('show_limitbox', $show_limitbox);

    $show_top_navigation = (PMAGAZ_SHOW_TOP_PAGENAV == '1' && $num_rows > $limit);
    $t->set('show_top_navigation', $show_top_navigation);

    // Підключення навігації сторінки
    require_once(CLASSPATH . 'pageNavigation.class.php');
    $pagenav = new emPageNav($num_rows, $limitstart, $limit);
    $t->set('pagenav', $pagenav);

    // Формування рядка параметрів пошуку (src_string)
    $src_string = '';
    if ($num_rows > 1 && @$_REQUEST['output'] != "pdf") {
        if ($num_rows > 5) {
            $src_string =
                $mm_action_url . "index.php?option=com_virtuemart&amp;Itemid=$Itemid&amp;categ_id=$categ_id&amp;page=$modulename.browse";
            $src_string .= empty($manuf_id) ? '' : "&amp;manuf_id=$manuf_id";
            $src_string .= empty($keyword) ? '' : '&amp;keyword=' . urlencode($keyword);

            if (!empty($keyword1)) {
                $src_string .= "&amp;keyword1=" . urlencode($keyword1);
                $src_string .= "&amp;src_categ=" . urlencode($src_categ);
                $src_string .= "&amp;src_limiter=$src_limiter";
                if (!empty($keyword2)) {
                    $src_string .= "&amp;keyword2=" . urlencode($keyword2);
                    $src_string .= "&amp;src_op=" . urlencode($src_op);
                }
            }

            if (!empty($tov_type_id)) {
                foreach ($_REQUEST as $key => $value) {
                    if (substr($key, 0, 13) == "tov_type_") {
                        $val = emGet($_REQUEST, $key);
                        if (is_array($val)) {
                            foreach ($val as $var) {
                                $src_string .= "&" . $key . "[]=" . urlencode($var);
                            }
                        } else {
                            $src_string .= "&" . $key . "=" . urlencode($val);
                        }
                    }
                }
            }
        }
    }

    // Поля сортування
    $t->set('EM_BROWSE_ORDERBY_FIELDS', $EM_BROWSE_ORDERBY_FIELDS);
    if ($DescOrderBy == "DESC") {
        $icon = "sort_desc.png";
        $selected = array("selected=\"selected\"", "");
        $asc_desc = array("DESC", "ASC");
    } else {
        $icon = "sort_asc.png";
        $selected = array("", "selected=\"selected\"");
        $asc_desc = array("ASC", "DESC");
    }
    $t->set('orderby', $orderby);
    $t->set('icon', $icon);
    $t->set('selected', $selected);
    $t->set('asc_desc', $asc_desc);
    $t->set('categ_id', $categ_id);
    $t->set('manuf_id', $manuf_id);
    $t->set('keyword', urlencode($keyword));
    $t->set('keyword1', urlencode($keyword1));
    $t->set('keyword2', urlencode($keyword2));
    $t->set('Itemid', $Itemid);

    if ($show_top_navigation) {
        $t->set('src_string', $src_string);
    }

    $orderby_form = $t->fetch('browse/includes/browse_orderbyform.t.php');
    $t->set('orderby_form', $orderby_form);
} else {
    $t->set('orderby_form', '');
}

// Запит на отримання списку товарів
$bd_browse->query($list);
$bd_browse->next_record();
$tovs_per_row = (!empty($categ_id)) ? $bd_browse->f("tovs_per_row") : TOVS_PER_ROW;
if ($tovs_per_row < 1) {
    $tovs_per_row = 1;
}
$buttons_header = '';

// Початок кешування інформації про товари
if (@$_REQUEST['output'] != "pdf") {
    $t->set('option', $option);
    $t->set('categ_id', $categ_id);
    $t->set('tov_id', $tov_id);
    $buttons_header = $t->fetch('common/buttons.t.php');

    // Вибір шаблону для списку товарів
    $templatefile = (!empty($categ_id)) ? $bd_browse->f("categ_browsepage") : CATEG_TEMPLATE;
    if ($templatefile == 'managed') {
        // Якщо шаблон managed — обрати за кількістю товарів у ряді
        $templatefile = file_exists(EM_THEMEPATH . 'templates/browse/browse_' . $tovs_per_row . '.php')
            ? 'browse_' . $tovs_per_row
            : 'browse_5';
    } elseif (!file_exists(EM_THEMEPATH . 'templates/browse/' . $templatefile . '.php')) {
        $templatefile = 'browse_5';
    }
    // Якщо PDF — інший шаблон
    if (@$_REQUEST['output'] == "pdf") {
        $templatefile = "browse_lite_pdf";
    }

    $t->set('buttons_header', $buttons_header);
    $t->set('tovs_per_row', $tovs_per_row);
    $t->set('templatefile', $templatefile);
}

$bd_browse->reset();

$tovs = array();
$counter = 0;
// Основний цикл по товарах
while ($bd_browse->next_record()) {
    $tov_prt_id = $bd_browse->f("tov_prt_id");
    if ($tov_prt_id != 0) {
        $bdp->query("SELECT tov_full_image,tov_thumb_image,tov_name,tov_s_desc FROM #__em_tov WHERE tov_id='$tov_prt_id'");
        $bdp->next_record();
    }

    $flypage = $bd_browse->sf("categ_flypage");
    if (empty($flypage)) {
        $flypage = FLYPAGE;
    }
    $url_params = "page=magaz.tov_details&amp;flypage=$flypage&amp;tov_id=" .
        $bd_browse->f("tov_id") . "&amp;categ_id=" . $bd_browse->f("categ_id");
    if ($manuf_id) {
        $url_params .= "&amp;manuf_id=" . $manuf_id;
    }
    if ($keyword != '') {
        $url_params .= "&amp;keyword=" . urlencode($keyword);
    }
    $url = $sess->url($url_params);

    // Обробка ціни товару
    if (_SHOW_PRICES == '1' && $auth['show_prices']) {
        $tov_price = $p_tov->show_price($bd_browse->f("tov_id"));
    } else {
        $tov_price = "";
    }
    $tov_price_raw = $p_tov->get_adjusted_attribute_price($bd_browse->f('tov_id'));

    // Індекс для масиву товарів (для сортування)
    $i = $tov_price_raw['tov_price'] . '_' . ++$counter;

    // Вибір зображення товару (іконка)
    if ($bd_browse->f("tov_thumb_image")) {
        $tov_thumb_image = $bd_browse->f("tov_thumb_image");
    } elseif ($tov_prt_id != 0) {
        $tov_thumb_image = $bdp->f("tov_thumb_image");
    } else {
        $tov_thumb_image = 0;
    }

    if ($tov_thumb_image) {
        if (substr($tov_thumb_image, 0, 4) != "http") {
            if (PMAGAZ_IMG_RESIZE_ENABLE == '1') {
                $tov_thumb_image =
                    $mosConfig_live_site . "/components/com_virtuemart/show_image_in_imgtag.php?filename=" . urlencode($tov_thumb_image) . "&amp;newxsize=" . PMAGAZ_IMG_WIDTH . "&amp;newysize=" . PMAGAZ_IMG_HEIGHT . "&amp;fileout=";
            } elseif (!file_exists(IMAGEPATH . "tov/" . $tov_thumb_image)) {
                $tov_thumb_image = EM_THEMEURL . 'imag/' . NO_IMAGE;
            }
        }
    } else {
        $tov_thumb_image = EM_THEMEURL . 'imag/' . NO_IMAGE;
    }

    // Вибір повного зображення товару
    if ($bd_browse->f("tov_full_image")) {
        $tov_full_image = $bd_browse->f("tov_full_image");
    } elseif ($tov_prt_id != 0) {
        $tov_full_image = $bdp->f("tov_full_image");
    } else {
        $tov_full_image = EM_THEMEURL . 'imag/' . NO_IMAGE;
        // Дізнатись розмір no_image
        if (file_exists(EM_THEMEPATH . 'imag/' . NO_IMAGE)) {
            $full_image_info = getimagize(EM_THEMEPATH . 'imag/' . NO_IMAGE);
            $full_image_width = $full_image_info[0] + 40;
            $full_image_height = $full_image_info[1] + 40;
        }
    }
    // Якщо зображення не http і існує файл — отримати розміри
    if (substr($tov_full_image, 0, 4) != 'http') {
        if (file_exists(IMAGEPATH . 'tov/' . $tov_full_image)) {
            $full_image_info = getimagize(IMAGEPATH . 'tov/' . $tov_full_image);
            $full_image_width = $full_image_info[0] + 40;
            $full_image_height = $full_image_info[1] + 40;
            $tov_full_image = IMAGEURL . 'tov/' . $tov_full_image;
        } elseif (!isset($full_image_width) || !isset($full_image_height)) {
            $full_image_info = getimagize($tov_full_image);
            $full_image_width = $full_image_info[0] + 40;
            $full_image_height = $full_image_info[1] + 40;
        }
    }

    // Додаткові файли зображень для товару
    $files = p_tov_files::getFilesForTov($bd_browse->f('tov_id'));
    $tovs[$i]['files'] = $files['files'];
    $tovs[$i]['imag'] = $files['imag'];

    // Назва, опис, короткий опис товару
    $tov_name = $bd_browse->f("tov_name");
    if ($bd_browse->f("tov_publish") == "N") {
        $tov_name .= " (" . $EM_LANG->_('CMN_UNPUBLISHED', false) . ")";
    }
    if (empty($tov_name) && $tov_prt_id != 0) {
        $tov_name = $bdp->f("tov_name");
    }
    $tov_s_desc = $bd_browse->f("tov_s_desc");
    if (empty($tov_s_desc) && $tov_prt_id != 0) {
        $tov_s_desc = $bdp->f("tov_s_desc");
    }
    $tov_details = $EM_LANG->_('PMAGAZ_FLYPAGE_LBL');

    // Рейтинг товару
    if (PMAGAZ_ALLOW_REVIEWS == '1' && @$_REQUEST['output'] != "pdf") {
        $tov_rating = p_reviews::allvotes($bd_browse->f("tov_id"));
    } else {
        $tov_rating = "";
    }

    // Форма додавання в кошик (якщо можна купити цей товар)
    if (USE_AS_CATALOGUE != '1' && $tov_price != "" && !stristr($tov_price, $EM_LANG->_('PMAGAZ_TOV_CALL')) && !p_tov::tov_has_attributes($bd_browse->f('tov_id'), true) && $t->get_cfg('showAddtokorzButtonOnTovList')) {
        $t->set('i', $i);
        $t->set('tov_id', $bd_browse->f('tov_id'));
        $t->set('tov_in_stock', $bd_browse->f('tov_in_stock'));
        $t->set('p_tov_attribute', $p_tov_attribute);
        $tovs[$i]['form_addtokorz'] = $t->fetch('browse/includes/addtokorz_form.t.php');
        $tovs[$i]['has_addtokorz'] = true;
    } else {
        $tovs[$i]['form_addtokorz'] = '';
        $tovs[$i]['has_addtokorz'] = false;
    }

    // Додаємо дані по товару в масив для шаблону
    $tovs[$i]['tov_flypage'] = $url;
    $tovs[$i]['tov_thumb_image'] = $tov_thumb_image;
    $tovs[$i]['tov_full_image'] = $tov_full_image;
    $tovs[$i]['full_image_width'] = $full_image_width ?? null;
    $tovs[$i]['full_image_height'] = $full_image_height ?? null;

    unset($full_image_width);
    unset($full_image_height);

    $tovs[$i]['tov_name'] = magazMakeHtmlSafe($tov_name);
    $tovs[$i]['tov_s_desc'] = $tov_s_desc;
    $tovs[$i]['tov_details'] = $tov_details;
    $tovs[$i]['tov_rating'] = $tov_rating;
    $tovs[$i]['tov_price'] = $tov_price;
    $tovs[$i]['tov_price_raw'] = $tov_price_raw;
    $tovs[$i]['tov_sku'] = $bd_browse->f("tov_sku");
    $tovs[$i]['tov_weight'] = $bd_browse->f("tov_weight");
    $tovs[$i]['tov_weight_uom'] = $bd_browse->f("tov_weight_uom");
    $tovs[$i]['tov_length'] = $bd_browse->f("tov_length");
    $tovs[$i]['tov_width'] = $bd_browse->f("tov_width");
    $tovs[$i]['tov_height'] = $bd_browse->f("tov_height");
    $tovs[$i]['tov_lwh_uom'] = $bd_browse->f("tov_lwh_uom");
    $tovs[$i]['tov_in_stock'] = $bd_browse->f("tov_in_stock");
    $tovs[$i]['tov_avbl_date'] = $EM_LANG->convert(emFormatDate($bd_browse->f("tov_avbl_date"), $EM_LANG->_('DATE_FORMAT_LC')));
    $tovs[$i]['tov_availability'] = $bd_browse->f("tov_availability");
    $tovs[$i]['cdate'] = $EM_LANG->convert(emFormatDate($bd_browse->f("cdate"), $EM_LANG->_('DATE_FORMAT_LC')));
    $tovs[$i]['mdate'] = $EM_LANG->convert(emFormatDate($bd_browse->f("mdate"), $EM_LANG->_('DATE_FORMAT_LC')));
    $tovs[$i]['tov_url'] = $bd_browse->f("tov_url");
}

// Сортування за ціною, якщо потрібно
if ($orderby == 'tov_price') {
    if ($DescOrderBy == "DESC") {
        krsort($tovs, SORT_NUMERIC);
    } else {
        ksort($tovs, SORT_NUMERIC);
    }
}

$t->set('tovs', $tovs);
$t->set('src_string', $src_string);

// Нижня навігація сторінки, якщо більше одного товару
if ($num_rows > 1) {
    $browsepage_footer = $t->fetch('browse/includes/browse_pagenav.t.php');
    $t->set('browsepage_footer', $browsepage_footer);
} else {
    $t->set('browsepage_footer', '');
}

// Список нещодавно переглянутих товарів
$recent_tovs = $p_tov->recentTovs(null, $t->get_cfg('showRecent', 5));
$t->set('recent_tovs', $recent_tovs);

// Передаємо об'єкт p_tov у шаблон
$t->set('p_tov', $p_tov);

// Вивід фінального шаблону списку товарів
echo $t->fetch($t->config->get('tovListStyle'));
?>
