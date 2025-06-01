<?php
// Захист від прямого доступу до файлу
if (!defined('_VALID_MOS') && !defined('_JEXEC')) die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

// Виводить ім'я поточного файлу (для відладки)
mm_showMyFileName(__FILE__);

// Підключення необхідних класів та файлів
require_once(CLASSPATH . 'p-tov_files.php');
require_once(CLASSPATH . 'imToo.class.php');
require_once(CLASSPATH . 'p-tov.php');
$p_tov = $GLOBALS['product'] = new p_tov();

require_once(CLASSPATH . 'tov_categ.php');
$p_tov_categ = new prodt_catery();

require_once(CLASSPATH . 'p-tov_attribute.php');
$p_tov_attribute = new p_tov_attribute();

require_once(CLASSPATH . 'p-tov_type.php');
$p_tov_type = new p_tov_type();
require_once(CLASSPATH . 'p-reviews.php');

// Отримуємо змінні з запиту та ініціалізуємо об'єкти
$tov_id = intval(emGet($_REQUEST, "tov_id", null));
$tov_sku = $bd->getEscaped(emGet($_REQUEST, "sku", ''));
$categ_id = emGet($_REQUEST, "categ_id", null);
$pop = (int)emGet($_REQUEST, "pop", 0);
$manuf_id = emGet($_REQUEST, "manuf_id", null);
$Itemid = $sess->getShItid();
$bd_tov = new p_BD;

// Формуємо SQL-запит для отримання товару з бази даних
$q = "SELECT * FROM #__em_tov WHERE ";
if (!empty($tov_id)) {
    $q .= "tov_id=" . $tov_id;
} elseif (!empty($tov_sku)) {
    $q .= "tov_sku='" . $tov_sku . "'";
} else {
    // Якщо товар не знайдено, перенаправлення на сторінку перегляду з повідомленням
    emRedirect(
        $sess->url(
            $_SERVER['PHP_SELF'] . "?keyword=" . urlencode($keyword) . "&categ_id={$_SESSION['session_userstate']['categ_id']}&limitstart={$_SESSION['limitstart']}&page=magaz.browse",
            false,
            false
        ),
        $EM_LANG->_('PMAGAZ_TOV_NOT_FOUND')
    );
}

// Якщо користувач не адміністратор, показуємо лише опубліковані та в наявності товари
if (!$perm->check("admin,storeadmin")) {
    $q .= " AND tov_publish='Y'";
    if (CHECK_STOCK && PMAGAZ_SHOW_OUT_OF_STOCK_TOVS != "1") {
        $q .= " AND tov_in_stock > 0 ";
    }
}
$bd_tov->query($q);

// Якщо товар не знайдено — повідомлення про помилку
if (!$bd_tov->next_record()) {
    $emLogger->err($EM_LANG->_('PMAGAZ_TOV_NOT_FOUND', false));
    return;
}
if (empty($tov_id)) {
    $tov_id = $bd_tov->f('tov_id');
}

// Якщо є батьківський товар — отримуємо його дані
$tov_prt_id = (int)$bd_tov->f("tov_prt_id");
if ($tov_prt_id != 0) {
    $bdp = new p_BD;
    $bdp->query("SELECT * FROM #__em_tov WHERE tov_id=" . $tov_prt_id);
    $bdp->next_record();
}

// Створення об'єкта шаблону (template)
$t = emTemplate::getInstance();

/*
// Пошук пов’язаних товарів (цей код закоментовано)
// $related_tovs = '';
// if ($bd->num_rows() > 0) { ... }
*/

// Змінна для збереження HTML пов’язаних товарів
$related_tovs = '';
if ($bd->num_rows() > 0) {
    $t->set('p_tov', $p_tov);
    $t->set('tovs', $bd);
    $related_tovs = $t->fetch('/common/relatedTovs.t.php');
}

// Додаємо позначку "Неопубліковано" до назви, якщо потрібно
$tov_name = magazMakeHtmlSafe($bd_tov->f("tov_name"));
if ($bd_tov->f("tov_publish") == "N") {
    $tov_name .= " (" . $EM_LANG->_('CMN_UNPUBLISHED') . ")";
}

// Опис товару: якщо опису нема — беремо від батьківського товару
$tov_description = $bd_tov->f("tov_desc");
if ((str_replace("<br />", "", $tov_description) == '') && ($tov_prt_id != 0)) {
    $tov_description = $bdp->f("tov_desc");
}
$tov_description = emCommonHTML::ParseContentByPlugins($tov_description);

// Навігаційний шлях (breadcrumbs)
$navigation_pathway = "";
$navigation_chdlist = "";
$pathway_appended = false;

// Визначаємо flypage (шаблон для сторінки товару)
$flypage = emGet($_REQUEST, "flypage");

// Якщо не вказано категорію або flypage — визначаємо їх із бази
if (empty($categ_id) || empty($flypage)) {
    $q = "SELECT cx.categ_id, categ_flypage FROM #__em_categ c, #__em_tov_categ_xref cx WHERE tov_id = '$tov_id' AND c.categ_id=cx.categ_id LIMIT 0,1";
    $bd->query($q);
    $bd->next_record();
    if (!$bd->f("categ_id")) {
        $q = "SELECT tov_id FROM #__em_tov WHERE tov_id = '" . $bd_tov->f("tov_prt_id") . "' LIMIT 0,1";
        $bd->query($q);
        $bd->next_record();

        $q = "SELECT cx.categ_id, categ_flypage FROM #__em_categ c, #__em_tov_categ_xref cx WHERE tov_id = '" . $bd->f("tov_id") . "' AND c.categ_id=cx.categ_id LIMIT 0,1";
        $bd->query($q);
        $bd->next_record();
    }
    $_GET['categ_id'] = $categ_id = $bd->f("categ_id");
}

// Додаємо товар до списку нещодавно переглянутих
$p_tov->addRecentTov($tov_id, $categ_id, $t->get_cfg('showRecent', 5));

// Визначаємо flypage, якщо не заданий
if (empty($flypage)) {
    $flypage = $bd->f('categ_flypage') ? $bd->f('categ_flypage') : FLYPAGE;
}
$flypage = str_replace('magaz.', '', $flypage);
$flypage = stristr($flypage, '.t') ? $flypage : $flypage . '.t';

// Формуємо список категорій та шлях (breadcrumbs)
$categ_list = array_reverse($p_tov_categ->get_navigation_list($categ_id));
$pathway = $p_tov_categ->getPathway($categ_list);

// Додаємо сам товар у pathway (без посилання)
$item = new stdClass();
$item->name = $tov_name;
$item->link = '';
$pathway[] = $item;

// Додаємо pathway до головного фрейму
$em_mainframe->emAppendPathway($pathway);
$t->set('pathway', $pathway);

// Сусідні товари (наступний і попередній)
$t->set('tov_name', $tov_name);
$neighbors = $p_tov->get_neighbor_tovs(!empty($tov_prt_id) ? $tov_prt_id : $tov_id);
$next_tov = $neighbors['next'];
$previous_tov = $neighbors['previous'];
$next_tov_url = $previous_tov_url = '';

// Формуємо URL для переходу до наступного і попереднього товару
if (!empty($next_tov)) {
    $url_params = 'page=magaz.tov_details&tov_id=' . $next_tov['tov_id'] . '&flypage=' . $p_tov->get_flypage($next_tov['tov_id']) . '&pop=' . $pop;
    if ($manuf_id) {
        $url_params .= "&amp;manuf_id=" . $manuf_id;
    }
    if ($keyword != '') {
        $url_params .= "&amp;keyword=" . urlencode($keyword);
    }
    if ($pop == 1) {
        $next_tov_url = $sess->url($_SERVER['PHP_SELF'] . '?' . $url_params);
    } else {
        $next_tov_url = str_replace("index2", "index", $sess->url($url_params));
    }
}
if (!empty($previous_tov)) {
    $url_params = 'page=magaz.tov_details&tov_id=' . $previous_tov['tov_id'] . '&flypage=' . $p_tov->get_flypage($previous_tov['tov_id']) . '&pop=' . $pop;
    if ($manuf_id) {
        $url_params .= "&amp;manuf_id=" . $manuf_id;
    }
    if ($keyword != '') {
        $url_params .= "&amp;keyword=" . urlencode($keyword);
    }
    if ($pop == 1) {
        $previous_tov_url = $sess->url($_SERVER['PHP_SELF'] . '?' . $url_params);
    } else {
        $previous_tov_url = str_replace("index2", "index", $sess->url($url_params));
    }
}

// Передаємо дані у шаблон про сусідні товари
$t->set('next_tov', $next_tov);
$t->set('next_tov_url', $next_tov_url);
$t->set('previous_tov', $previous_tov);
$t->set('previous_tov_url', $previous_tov_url);

// Формуємо лінк для повернення до батьківського товару (якщо є)
$prt_id_link = $bd_tov->f("tov_prt_id");
$return_link = "";
if ($prt_id_link != 0) {
    $q = "SELECT tov_name FROM #__em_tov WHERE tov_id = '$prt_id_link' LIMIT 0,1";
    $bd->query($q);
    $bd->next_record();
    $tov_prt_name = $bd->f("tov_name");
    $return_link = "&nbsp;<a class=\"pathway\" href=\"";
    $return_link .= $sess->url($_SERVER['PHP_SELF'] . "?page=magaz.tov_details&tov_id=$prt_id_link");
    $return_link .= "\">";
    $return_link .= $tov_prt_name;
    $return_link .= "</a>";
    $return_link .= " " . emCommonHTML::pathway_separator() . " ";
}
$t->set('return_link', $return_link);
$navigation_pathway = $t->fetch('common/pathway.t.php');

// Якщо у категорії є дочірні категорії — показуємо їх
if ($p_tov_categ->has_chds($categ_id)) {
    $categ_chds = $p_tov_categ->get_chd_list($categ_id);
    $t->set('categories', $categ_chds);
    $navigation_chdlist = $t->fetch('common/categChdlist.t.php');
}

// Встановлюємо заголовок сторінки і мета-опис
$em_mainframe->setPageTitle(html_entity_decode(substr($tov_name, 0, 60), ENT_QUOTES));
if (emIs) {
    $document = JFactory::getDocument();
    $document->setDescription(strip_tags($bd_tov->f("tov_s_desc")));
} else {
    $mainframe->prependMetaTag("description", strip_tags($bd_tov->f("tov_s_desc")));
}

// Формуємо посилання для редагування товару (тільки для адміністраторів)
if ($perm->check("admin,storeadmin")) {
    $edit_link = '<a href="' . $sess->url('index2.php?page=tov.tov_form&next_page=magaz.tov_details&tov_id=' . $tov_id) . '">
        <img src="imag/M_imag/edit.png" width="16" height="16" alt="' . $EM_LANG->_('PMAGAZ_TOV_FORM_EDIT_TOV') . '" border="0" /></a>';
} else {
    $edit_link = "";
}

// Отримуємо інформацію про виробника
$manuf_id = $p_tov->get_manuf_id($tov_id);
$manuf_name = $p_tov->get_mf_name($tov_id);
$manuf_link = "";
if ($manuf_id && !empty($manuf_name)) {
    $link = "$mosConfig_live_site/index2.php?page=magaz.manuf_page&amp;manuf_id=$manuf_id&amp;output=lite&amp;option=com_virtuemart&amp;Itemid=" . $Itemid;
    $text = '( ' . $manuf_name . ' )';
    $manuf_link .= emPopupLink($link, $text);
    if (@$_REQUEST['output'] == "pdf") {
        $manuf_link = "<a href=\"$link\" target=\"_blank\" title=\"$text\">$text</a>";
    }
}

// Формування блоку ціни
$tov_price_lbl = '';
$tov_price = '';
if (_SHOW_PRICES == '1') {
    if ($bd_tov->f("tov_unit") && EM_PRICE_SHOW_PACKAGING_PRICELABEL) {
        $tov_price_lbl = "<strong>" . $EM_LANG->_('PMAGAZ_KORZ_PRICE_PER_UNIT') . ' (' . $bd_tov->f("tov_unit") . "):</strong>";
    } else {
        $tov_price_lbl = "<strong>" . $EM_LANG->_('PMAGAZ_KORZ_PRICE') . ": </strong>";
    }
    $tov_price = $p_tov->show_price($tov_id);
}

// Якщо ціна незвичної форми — отримаємо "сиру" ціну
$tov_price_raw = $p_tov->get_adjusted_attribute_price($tov_id);

// Формування блоку пакування товару
if ($bd_tov->f("tov_packaging")) {
    $packaging = $bd_tov->f("tov_packaging") & 0xFFFF;
    $box = ($bd_tov->f("tov_packaging") >> 16) & 0xFFFF;
    $tov_packaging = "";
    if ($packaging) {
        $tov_packaging .= $EM_LANG->_('PMAGAZ_TOV_PACKAGING1') . $packaging;
        if ($box) $tov_packaging .= "<br/>";
    }
    if ($box) {
        $tov_packaging .= $EM_LANG->_('PMAGAZ_TOV_PACKAGING2') . $box;
    }
    $tov_packaging = str_replace("{unit}", $bd_tov->f("tov_unit") ? $bd_tov->f("tov_unit") : $EM_LANG->_('PMAGAZ_TOV_FORM_UNIT_DEFAULT'), $tov_packaging);
} else {
    $tov_packaging = "";
}

// Вибір зображення товару (якщо немає — від батьківського)
$tov_full_image = $tov_prt_id != 0 && !$bd_tov->f("tov_full_image") ? $bdp->f("tov_full_image") : $bd_tov->f("tov_full_image");
$tov_thumb_image = $tov_prt_id != 0 && !$bd_tov->f("tov_thumb_image") ? $bdp->f("tov_thumb_image") : $bd_tov->f("tov_thumb_image");

// Додаткові зображення
$files = p_tov_files::getFilesForTov($tov_id);
$more_imag = "";
if (!empty($files['imag'])) {
    $more_imag = $t->emMoreImagLink($files['imag']);
}

// Додаткові файли/документи по товару
$file_list = p_tov_files::get_file_list($tov_id);

// Блок доступності товару
$tov_availability = '';
if (@$_REQUEST['output'] != "pdf") {
    $t->set('option', $option);
    $t->set('categ_id', $categ_id);
    $t->set('tov_id', $tov_id);
    $buttons_header = $t->fetch('common/buttons.t.php');
    $t->set('buttons_header', $buttons_header);
    $tov_availability = $p_tov->get_availability($tov_id);
}
$tov_availability_data = $p_tov->get_availability_data($tov_id);

// Посилання "Задати питання продавцю"
$ask_seller_href = $sess->url($_SERVER['PHP_SELF'] . '?page=magaz.ask&amp;flypage=' . @$_REQUEST['flypage'] . "&amp;tov_id=$tov_id&amp;categ_id=$categ_id");
$ask_seller_text = $EM_LANG->_('EM_TOV_ENQUIRY_LBL');
$ask_seller = '<a class="button" href="' . $ask_seller_href . '">' . $ask_seller_text . '</a>';

// Рейтинг товару
$tov_rating = "";
if (PMAGAZ_ALLOW_REVIEWS == '1') {
    $tov_rating = p_reviews::allvotes($tov_id);
}

// Відгуки та форма для залишення відгуку
$tov_reviews = $tov_reviewform = "";
if (PMAGAZ_ALLOW_REVIEWS == '1') {
    $tov_reviews = p_reviews::tov_reviews($tov_id);
    if ($auth['user_id'] > 0) {
        $tov_reviewform = p_reviews::reviewform($tov_id);
    }
}

// Дані про продавця (vendor)
$vend_id = $p_tov->get_vendor_id($tov_id);
$vend_name = $p_tov->get_vendorname($tov_id);

$link = "$mosConfig_live_site/index2.php?page=magaz.infopage&amp;vendor_id=$vend_id&amp;output=lite&amp;option=com_virtuemart&amp;Itemid=" . $Itemid;
$text = $EM_LANG->_('PMAGAZ_VENDOR_FORM_INFO_LBL');
$vendor_link = emPopupLink($link, $text);
if (@$_REQUEST['output'] == "pdf") {
    $vendor_link = "<a href=\"$link\" target=\"_blank\" title=\"$text\">$text</a>";
}

// Тип товару (з урахуванням батьківського)
if ($tov_prt_id != 0 && !$p_tov_type->tov_in_tov_type($tov_id)) {
    $tov_type = $p_tov_type->list_tov_type($tov_prt_id);
} else {
    $tov_type = $p_tov_type->list_tov_type($tov_id);
}

// Останні переглянуті товари
$recent_tovs = $p_tov->recentTovs($tov_id, $t->get_cfg('showRecent', 5));

// Масив з усіма даними товару (для шаблону)
$tovData = $bd_tov->get_row();
$tovArray = get_object_vars($tovData);
$tovArray["tov_id"] = $tov_id;
$tovArray["tov_full_image"] = $tov_full_image;
$tovArray["tov_thumb_image"] = $tov_thumb_image;
$tovArray["tov_name"] = magazMakeHtmlSafe($tovArray["tov_name"]);

$t->set('tovArray', $tovArray);
foreach ($tovArray as $property => $value) {
    $t->set($property, $value);
}

// Формування міні-зображення товару
$tov_image = $t->emBuildFullImageLink($tovArray);
$t->set("tov_id", $tov_id);
$t->set("tov_name", $tov_name);
$t->set("tov_image", $tov_image);
$t->set("more_imag", $more_imag);
$t->set("imag", $files['imag']);
$t->set("files", $files['files']);
$t->set("file_list", $file_list);
$t->set("edit_link", $edit_link);
$t->set("manuf_link", $manuf_link);
$t->set("tov_price", $tov_price);
$t->set("tov_price_lbl", $tov_price_lbl);
$t->set('tov_price_raw', $tov_price_raw);
$t->set("tov_description", $tov_description);

$t->set('manuf_id', $manuf_id);
$t->set('flypage', $flypage);
$t->set('p_tov_attribute', $p_tov_attribute);

// Генеруємо HTML форму додавання товару у кошик
$addtokorz = $t->fetch('tov_details/includes/addtokorz_form.t.php');

$t->set("addtokorz", $addtokorz);
$t->set("navigation_pathway", $navigation_pathway);
$t->set("navigation_chdlist", $navigation_chdlist);
$t->set("tov_reviews", $tov_reviews);
$t->set("tov_reviewform", $tov_reviewform);
$t->set("tov_availability", $tov_availability);
$t->set("tov_availability_data", $tov_availability_data);
$t->set("related_tovs", $related_tovs);
$t->set("vendor_link", $vendor_link);
$t->set("tov_type", $tov_type);
$t->set("tov_packaging", $tov_packaging);
$t->set("ask_seller_href", $ask_seller_href);
$t->set("ask_seller_text", $ask_seller_text);
$t->set("ask_seller", $ask_seller);
$t->set("recent_tovs", $recent_tovs);

// Виводимо фінальний шаблон сторінки товару
echo $t->fetch('/tov_details/' . $flypage . '.php');
?>
