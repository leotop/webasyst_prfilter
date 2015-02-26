<?php


$DebugMode = false;
// -------------------------INITIALIZATION-----------------------------//
define('DIR_ROOT', str_replace("\\","/",realpath(dirname(__FILE__))).'/published/SC/html/scripts');
include(DIR_ROOT.'/includes/init.php');
include_once(DIR_CFG.'/connect.inc.wa.php');
include_once(DIR_FUNC.'/setting_functions.php' );
require_once(DIR_FUNC.'/product_functions.php');
require_once(DIR_FUNC.'/reg_fields_functions.php' );
require_once(DIR_FUNC.'/order_status_functions.php' );
require_once(DIR_FUNC.'/cart_functions.php');
require_once(DIR_FUNC.'/order_functions.php' );
if(!defined('WBS_DIR')){
	define('WBS_DIR',realpath(dirname(__FILE__)));
}


$DB_tree = new DataBase();
db_connect(SystemSettings::get('DB_HOST'),SystemSettings::get('DB_USER'),SystemSettings::get('DB_PASS')) or die (db_error());
db_select_db(SystemSettings::get('DB_NAME')) or die (db_error());
$DB_tree->connect(SystemSettings::get('DB_HOST'), SystemSettings::get('DB_USER'), SystemSettings::get('DB_PASS'));
$DB_tree->query("SET character_set_client='".MYSQL_CHARSET."'");
$DB_tree->query("SET character_set_connection='".MYSQL_CHARSET."'");
$DB_tree->query("SET character_set_results='".MYSQL_CHARSET."'");
$DB_tree->selectDB(SystemSettings::get('DB_NAME'));
define('VAR_DBHANDLER','DBHandler');
$Register = &Register::getInstance();
$Register->set(VAR_DBHANDLER, $DB_tree);
settingDefineConstants();
$admin_mode = false;
if(isset($_SESSION['__WBS_SC_DATA'])&&isset($_SESSION['__WBS_SC_DATA']["U_ID"])||isset($_SESSION['wbs_username'])){
	$admin_mode = true;
}
session_write_close();

if(!$admin_mode){
	header('location: /published/index.php');
}else{
	set_time_limit (0);
	
		function clean_Cache(){
			require_once('/published/wbsadmin/classes/class.diagnostictools.php');
			$tools = new DiagnosticTools(WBS_DIR);
			$res = true;
				$res = $res&$tools->cleanCache('temp',$errorStr,'/^\.cache\.|^\.settings\./');
				$SCFolders = scandir(WBS_DIR.'/data');
				$applications = array();
				foreach($SCFolders as $SCFolder){
					if(($SCFolder == '.')
						||($SCFolder == '..')
						||!is_dir(WBS_DIR.'/data/'.$SCFolder)
					){
						continue;		
					}
					if(realpath(WBS_DIR.'/data/'.$SCFolder.'/attachments/SC/temp/')){
						$applications[] = 'data/'.$SCFolder.'/attachments/SC/temp/';
					}
				}
				if(count($applications)){
					$res = $res&$tools->cleanCache($applications,$errorStr,'/^\.cache\.|^\.settings\./');
				}
			
			$res = $res&$tools->cleanCache('kernel/includes/smarty/compiled',$errorStr,'/\.php$/');
				$applications = array('wbsadmin/localization');
				$applicationFolders = scandir(WBS_DIR.'/published');
				foreach($applicationFolders as $applicationFolder){
					if(preg_match('/^\w{2}$/',$applicationFolder)){
						$applications[] = 'published/'.$applicationFolder.'/localization/';
						$applications[] = 'published/'.$applicationFolder.'/2.0/localization/';
					}
				}
				$applications[] = 'published/wbsadmin/localization/';
				$SCFolders = scandir(WBS_DIR.'/data');
				foreach($SCFolders as $SCFolder){
					if(($SCFolder == '.')
						||($SCFolder == '..')
						||!is_dir(WBS_DIR.'/data/'.$SCFolder)
					){
						continue;		
					}
					if(realpath(WBS_DIR.'/data/'.$SCFolder.'/attachments/SC/temp/loc_cache/')){
						$applications[] = 'data/'.$SCFolder.'/attachments/SC/temp/loc_cache/';
					}
				}
				$res = $res&$tools->cleanCache($applications,$errorStr,'/(^\.cache\.)|(^serlang.+\.cch$)/',null,false);
		}
		
		function cleanDirectory($dirname){
			$dirname = realpath($dirname);
			if(file_exists($dirname)&&is_dir($dirname)&&($dir = opendir($dirname))){
				while ($name = readdir($dir)){
					if($name == '.'|| $name == '..')continue;

					$path = $dirname.'/'.$name;
					if(is_dir($path)){
						cleanDirectory($path);
					}elseif (file_exists($path)){
						@unlink($path);
					}
				}
				closedir($dir);
			}
		}
		
		
		function sql(){	
			$languages = &LanguagesManager::getLanguages();
			foreach($languages as $language){
				$category_name[]	= 	' `category_name_'.$language->iso2.'` text NOT NULL';
			}
			
			$product_options_categoryes = "
			CREATE TABLE IF NOT EXISTS `".DBTABLE_PREFIX."product_options_categoryes` (
			  `categoryID` int(10) unsigned NOT NULL AUTO_INCREMENT,
			  ".implode(',',$category_name).",
			  `sort` int(11) unsigned NOT NULL DEFAULT '0',
			  PRIMARY KEY (`categoryID`)
			) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=cp1251 ROW_FORMAT=DYNAMIC;";
			
			$product_options_templates = "
			CREATE TABLE IF NOT EXISTS `".DBTABLE_PREFIX."product_options_templates` (
			  `templateID` int(10) unsigned NOT NULL AUTO_INCREMENT,
			  `templateName` text NOT NULL,
			  `template` text NOT NULL,
			  `templatePriority` int(10) unsigned NOT NULL DEFAULT '0',
			  `templateEnable` int(1) unsigned NOT NULL DEFAULT '1',
			  PRIMARY KEY (`templateID`)
			) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=cp1251;";
			
			$product_options_templates_categoryes = "
			CREATE TABLE IF NOT EXISTS `".DBTABLE_PREFIX."product_options_templates_categoryes` (
			  `templateID` varchar(255) NOT NULL,
			  `categoryID` varchar(255) NOT NULL
			) ENGINE=InnoDB DEFAULT CHARSET=cp1251;";
			
			
			$sqls = array();
			$sqls[] = array('sql'=>"DROP TABLE IF EXISTS `".DBTABLE_PREFIX."product_options_categoryes`",'success_msg'=>"Удаляем таблицу ".DBTABLE_PREFIX."product_options_categoryes");
			$sqls[] = array('sql'=>"DROP TABLE IF EXISTS `".DBTABLE_PREFIX."product_options_templates`",'success_msg'=>"Удаляем таблицу ".DBTABLE_PREFIX."product_options_templates");
			$sqls[] = array('sql'=>"DROP TABLE IF EXISTS `".DBTABLE_PREFIX."product_options_templates_categoryes`",'success_msg'=>"Удаляем таблицу ".DBTABLE_PREFIX."product_options_templates_categoryes");
			
		//SC_product_options
			$sqls[] = array('sql'=>"db_delete_column(PRODUCT_OPTIONS_TABLE, 'is_slider', true );",'success_msg'=>"Удаляем колонку is_slider в таблице ".PRODUCT_OPTIONS_TABLE.", если она существует",'eval'=>true);
			$sqls[] = array('sql'=>"db_delete_column(PRODUCT_OPTIONS_TABLE, 'colomns', true );",'success_msg'=>"Удаляем колонку colomns в таблице ".PRODUCT_OPTIONS_TABLE.", если она существует",'eval'=>true);
			$sqls[] = array('sql'=>"db_delete_column(PRODUCT_OPTIONS_TABLE, 'single_value', true );",'success_msg'=>"Удаляем колонку single_value в таблице ".PRODUCT_OPTIONS_TABLE.", если она существует",'eval'=>true);
			$sqls[] = array('sql'=>"db_delete_column(PRODUCT_OPTIONS_TABLE, 'default_hide', true );",'success_msg'=>"Удаляем колонку default_hide в таблице ".PRODUCT_OPTIONS_TABLE.", если она существует",'eval'=>true);
			
			$sqls[] = array('sql'=>"if(!db_getColumn(PRODUCT_OPTIONS_TABLE, 'slider_step')){ db_add_column(PRODUCT_OPTIONS_TABLE, 'slider_step', 'varchar(45)', 1, true ); }",'success_msg'=>"Добавляем колонку slider_step в таблице ".PRODUCT_OPTIONS_TABLE."",'eval'=>true);
			$sqls[] = array('sql'=>"if(!db_getColumn(PRODUCT_OPTIONS_TABLE, 'optionType')){ db_add_column(PRODUCT_OPTIONS_TABLE, 'optionType', 'varchar(45)', null, false ); }",'success_msg'=>"Добавляем колонку optionType в таблице ".PRODUCT_OPTIONS_TABLE."",'eval'=>true);
			$sqls[] = array('sql'=>"if(!db_getColumn(PRODUCT_OPTIONS_TABLE, 'optionCategory')){ db_add_column(PRODUCT_OPTIONS_TABLE, 'optionCategory', 'varchar(45)', null, false ); }",'success_msg'=>"Добавляем колонку optionCategory в таблице ".PRODUCT_OPTIONS_TABLE."",'eval'=>true);

			foreach($languages as $language){
				$sqls[] = array('sql'=>"if(!db_getColumn(PRODUCT_OPTIONS_TABLE, 'description_title_".$language->iso2."')){ db_add_column(PRODUCT_OPTIONS_TABLE, 'description_title_".$language->iso2."', 'text', null, false ); }",'success_msg'=>"Добавляем колонку description_title_".$language->iso2." в таблице ".PRODUCT_OPTIONS_TABLE."",'eval'=>true);
				$sqls[] = array('sql'=>"if(!db_getColumn(PRODUCT_OPTIONS_TABLE, 'description_text_".$language->iso2."')){ db_add_column(PRODUCT_OPTIONS_TABLE, 'description_text_".$language->iso2."', 'text', null, false ); }",'success_msg'=>"Добавляем колонку description_text_".$language->iso2." в таблице ".PRODUCT_OPTIONS_TABLE."",'eval'=>true);
				$sqls[] = array('sql'=>"if(!db_getColumn(PRODUCT_OPTIONS_TABLE, 'slider_prefix_".$language->iso2."')){ db_add_column(PRODUCT_OPTIONS_TABLE, 'slider_prefix_".$language->iso2."', 'text', null, false ); }",'success_msg'=>"Добавляем колонку slider_prefix_".$language->iso2." в таблице ".PRODUCT_OPTIONS_TABLE."",'eval'=>true);
			}
			

			$sqls[] = array('sql'=>"db_delete_column(PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE, 'blockF', true );",'success_msg'=>"Удаляем колонку is_slider в таблице ".PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE.", если она существует",'eval'=>true);
			
			$sqls[] = array('sql'=>"if(!db_getColumn(PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE, 'recomended')){ db_add_column(PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE, 'recomended', 'int(1)', 1, true ); }",'success_msg'=>"Добавляем колонку recomended в таблице ".PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE."",'eval'=>true);
			$sqls[] = array('sql'=>"if(!db_getColumn(PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE, 'excluded')){ db_add_column(PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE, 'excluded', 'text', null, false ); }",'success_msg'=>"Добавляем колонку excluded в таблице ".PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE."",'eval'=>true);
			$sqls[] = array('sql'=>"if(!db_getColumn(PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE, 'picture')){ db_add_column(PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE, 'picture', 'text', null, false ); }",'success_msg'=>"Добавляем колонку picture в таблице ".PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE."",'eval'=>true);
			foreach($languages as $language){
				$sqls[] = array('sql'=>"if(!db_getColumn(PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE, 'description_title_".$language->iso2."')){ db_add_column(PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE, 'description_title_".$language->	iso2."', 'text', null, false ); }",'success_msg'=>"Добавляем колонку description_title_".$language->iso2." в таблице ".PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE."",'eval'=>true);
				$sqls[] = array('sql'=>"if(!db_getColumn(PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE, 'description_text_".$language->iso2."')){ db_add_column(PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE, 'description_text_".$language->iso2."', 'text', null, false ); }",'success_msg'=>"Добавляем колонку description_text_".$language->iso2." в таблице ".PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE."",'eval'=>true);
			}

			
		//tables	
			$sqls[] = array('sql'=>$product_options_categoryes,'success_msg'=>"Создаем таблицу ".DBTABLE_PREFIX."product_options_categoryes");
			$sqls[] = array('sql'=>$product_options_templates,'success_msg'=>"Создаем таблицу ".DBTABLE_PREFIX."product_options_templates");
			$sqls[] = array('sql'=>$product_options_templates_categoryes,'success_msg'=>"Создаем таблицу ".DBTABLE_PREFIX."product_options_templates_categoryes");	
		//modules
			$sqls[] = array('sql'=>"DELETE FROM `".DBTABLE_PREFIX."modules` WHERE `ModuleClassName` = 'prfilter'",'success_msg'=>"Удаляем запись в ".DBTABLE_PREFIX."modules, если она существует");	
			$sqls[] = array('sql'=>"INSERT INTO `".DBTABLE_PREFIX."modules` (`ModuleVersion`, `ModuleClassName`, `ModuleClassFile`) VALUES(1, 'prfilter', '/prfilter/class.prfilter.php');",'success_msg'=>"Запись в ".DBTABLE_PREFIX."modules успешно добавлена",'returnArgument'=>'ModuleID');
		//module_configs	
			$sqls[] = array('sql'=>"DELETE FROM `".DBTABLE_PREFIX."module_configs` WHERE `ConfigKey` = 'prfilter'",'success_msg'=>"Удаляем запись в ".DBTABLE_PREFIX."module_configs, если она существует");
			$sqls[] = array('sql'=>"INSERT INTO `".DBTABLE_PREFIX."module_configs` (`ModuleID`, `ConfigKey`, `ConfigTitle`, `ConfigDescr`, `ConfigInit`, `ConfigEnabled`) VALUES(?, 'prfilter', 'Модуль дополнительные характеристики (расширенная)', '', 1002, 1);",'success_msg'=>"Запись в ".DBTABLE_PREFIX."module_configs успешно добавлена",'inputValue'=>'ModuleID','returnArgument'=>'ModuleConfigID');	
		//settings
			$sqls[] = array('sql'=>"DELETE FROM `".DBTABLE_PREFIX."settings` WHERE `settings_constant_name` = 'CONF_PRFILTER_COLOMNS_ON_PSEARCH'",'success_msg'=>"Удаляем запись в ".DBTABLE_PREFIX."settings, если она существует");
			$sqls[] = array('sql'=>"INSERT INTO `".DBTABLE_PREFIX."settings` (`settings_groupID`, `settings_constant_name`, `settings_value`, `settings_title`, `settings_description`, `settings_html_function`, `sort_order`) VALUES(4, 'CONF_PRFILTER_COLOMNS_ON_PSEARCH', '3', 'conf_prfilter_colomns_on_psearch_title', 'conf_prfilter_colomns_on_psearch_description', 'setting_TEXT_BOX(0,', 200);",'success_msg'=>"Запись в ".DBTABLE_PREFIX."settings успешно добавлена");
		//divisions		
			$sqls[] = array('sql'=>"DELETE FROM `".DBTABLE_PREFIX."divisions` WHERE `xName` = 'pgn_prfilter_admin'",'success_msg'=>"Удаляем запись в ".DBTABLE_PREFIX."divisions, если она существует");
			$sqls[] = array('sql'=>"INSERT INTO `".DBTABLE_PREFIX."divisions` (`xName`, `xKey`, `xUnicKey`, `xParentID`, `xEnabled`, `xPriority`, `xTemplate`, `xLinkDivisionUKey`) VALUES('pgn_prfilter_admin', '', 'prfilter_admin', 9, 1, 10, '', '');",'success_msg'=>"Запись в ".DBTABLE_PREFIX."divisions успешно добавлена",'returnArgument'=>'DV_pgn_prfilter_admin');
			
			$sqls[] = array('sql'=>"DELETE FROM `".DBTABLE_PREFIX."divisions` WHERE `xName` = 'pgn_prfilter_templates'",'success_msg'=>"Удаляем запись в ".DBTABLE_PREFIX."divisions, если она существует");
			$sqls[] = array('sql'=>"INSERT INTO `".DBTABLE_PREFIX."divisions` (`xName`, `xKey`, `xUnicKey`, `xParentID`, `xEnabled`, `xPriority`, `xTemplate`, `xLinkDivisionUKey`) VALUES('pgn_prfilter_templates', '', 'prfilter_templates', ?, 1, 0, '', '');",'success_msg'=>"Запись в ".DBTABLE_PREFIX."divisions успешно добавлена",'returnArgument'=>'DV_pgn_prfilter_templates','inputValue'=>'DV_pgn_prfilter_admin');	
			
			$sqls[] = array('sql'=>"DELETE FROM `".DBTABLE_PREFIX."divisions` WHERE `xName` = 'png_prfilter'",'success_msg'=>"Удаляем запись в ".DBTABLE_PREFIX."divisions, если она существует");
			$sqls[] = array('sql'=>"INSERT INTO `".DBTABLE_PREFIX."divisions` (`xName`, `xKey`, `xUnicKey`, `xParentID`, `xEnabled`, `xPriority`, `xTemplate`, `xLinkDivisionUKey`) VALUES('png_prfilter', '', 'psearch', 1, 1, 0, '', '');",'success_msg'=>"Запись в ".DBTABLE_PREFIX."divisions успешно добавлена",'returnArgument'=>'DV_png_prfilter');	
			
			$sqls[] = array('sql'=>"DELETE FROM `".DBTABLE_PREFIX."divisions` WHERE `xName` = 'pgn_prfilter_import_excel'",'success_msg'=>"Удаляем запись в ".DBTABLE_PREFIX."divisions, если она существует");
			$sqls[] = array('sql'=>"INSERT INTO `".DBTABLE_PREFIX."divisions` (`xName`, `xKey`, `xUnicKey`, `xParentID`, `xEnabled`, `xPriority`, `xTemplate`, `xLinkDivisionUKey`) VALUES('pgn_prfilter_import_excel', '', 'prfilter_import_excel', ?, 1, 2, '', '');",'success_msg'=>"Запись в ".DBTABLE_PREFIX."divisions успешно добавлена",'returnArgument'=>'DV_pgn_prfilter_import_excel','inputValue'=>'DV_pgn_prfilter_admin');	
			
			$sqls[] = array('sql'=>"DELETE FROM `".DBTABLE_PREFIX."divisions` WHERE `xName` = 'pgn_prfilter_export_excel'",'success_msg'=>"Удаляем запись в ".DBTABLE_PREFIX."divisions, если она существует");
			$sqls[] = array('sql'=>"INSERT INTO `".DBTABLE_PREFIX."divisions` (`xName`, `xKey`, `xUnicKey`, `xParentID`, `xEnabled`, `xPriority`, `xTemplate`, `xLinkDivisionUKey`) VALUES('pgn_prfilter_export_excel', '', 'prfilter_export_excel', ?, 1, 3, '', '');",'success_msg'=>"Запись в ".DBTABLE_PREFIX."divisions успешно добавлена",'returnArgument'=>'DV_pgn_prfilter_export_excel','inputValue'=>'DV_pgn_prfilter_admin');	
		
		//division_interface			
			$sqls[] = array('sql'=>"DELETE FROM `".DBTABLE_PREFIX."division_interface` WHERE `xInterface` like('%prfilter%')",'success_msg'=>"Удаляем записи в ".DBTABLE_PREFIX."division_interface, если они существует");
			
			$sqls[] = array('sql'=>"INSERT INTO `".DBTABLE_PREFIX."division_interface` (`xDivisionID`, `xInterface`, `xPriority`, `xInheritable`) VALUES(?, ?, 0, 0);",'success_msg'=>"Запись в ".DBTABLE_PREFIX."division_interface успешно добавлена",'inputValue'=>'DV_pgn_prfilter_admin','inputValue2'=>'_prfilter_admin');	
			$sqls[] = array('sql'=>"INSERT INTO `".DBTABLE_PREFIX."division_interface` (`xDivisionID`, `xInterface`, `xPriority`, `xInheritable`) VALUES(?, ?, 0, 0);",'success_msg'=>"Запись в ".DBTABLE_PREFIX."division_interface успешно добавлена",'inputValue'=>'DV_pgn_prfilter_templates','inputValue2'=>'_prfilter_templates');	
			$sqls[] = array('sql'=>"INSERT INTO `".DBTABLE_PREFIX."division_interface` (`xDivisionID`, `xInterface`, `xPriority`, `xInheritable`) VALUES(?, ?, 0, 0);",'success_msg'=>"Запись в ".DBTABLE_PREFIX."division_interface успешно добавлена",'inputValue'=>'DV_pgn_prfilter_templates','inputValue2'=>'_prfilter_templates');	
			$sqls[] = array('sql'=>"INSERT INTO `".DBTABLE_PREFIX."division_interface` (`xDivisionID`, `xInterface`, `xPriority`, `xInheritable`) VALUES(?, ?, 0, 0);",'success_msg'=>"Запись в ".DBTABLE_PREFIX."division_interface успешно добавлена",'inputValue'=>'DV_png_prfilter','inputValue2'=>'_prfilter');	
			$sqls[] = array('sql'=>"INSERT INTO `".DBTABLE_PREFIX."division_interface` (`xDivisionID`, `xInterface`, `xPriority`, `xInheritable`) VALUES(?, ?, 0, 0);",'success_msg'=>"Запись в ".DBTABLE_PREFIX."division_interface успешно добавлена",'inputValue'=>'DV_pgn_prfilter_import_excel','inputValue2'=>'_prfilter_import_excel');	
			$sqls[] = array('sql'=>"INSERT INTO `".DBTABLE_PREFIX."division_interface` (`xDivisionID`, `xInterface`, `xPriority`, `xInheritable`) VALUES(?, ?, 0, 0);",'success_msg'=>"Запись в ".DBTABLE_PREFIX."division_interface успешно добавлена",'inputValue'=>'DV_pgn_prfilter_export_excel','inputValue2'=>'_prfilter_export_excel');	

		//interface_interfaces
			$sqls[] = array('sql'=>"DELETE FROM `".DBTABLE_PREFIX."interface_interfaces` WHERE `xInterfaceCalled` like('%prfilter%')",'success_msg'=>"Удаляем записи в ".DBTABLE_PREFIX."interface_interfaces, если они существует");
			
			$sqls[] = array('sql'=>"INSERT INTO `".DBTABLE_PREFIX."interface_interfaces` (`xInterfaceCaller`, `xInterfaceCalled`, `xPriority`) VALUES('51_cpt_connector', ?, 0);",'success_msg'=>"Запись в ".DBTABLE_PREFIX."interface_interfaces успешно добавлена",'inputValue3'=>'_prfilter_block');	
			$sqls[] = array('sql'=>"INSERT INTO `".DBTABLE_PREFIX."interface_interfaces` (`xInterfaceCaller`, `xInterfaceCalled`, `xPriority`) VALUES('51_cpt_connector', ?, 0);",'success_msg'=>"Запись в ".DBTABLE_PREFIX."interface_interfaces успешно добавлена",'inputValue3'=>'_prfilter_index_block');	
		
		//local
			$sqls[] = array('sql'=>"DELETE FROM `".DBTABLE_PREFIX."local` WHERE id like('%prfilter%')",'success_msg'=>"Удаляем записи в ".DBTABLE_PREFIX."local, если они существует");
			
			$locals = array();
			$locals[] = array('id'=>'prfilter_block_settings_position','ru'=>'Позиция','en'=>'Position');
			$locals[] = array('id'=>'prfilter_block_settings_position_left','ru'=>'Слева','en'=>'Left');
			$locals[] = array('id'=>'prfilter_block_settings_position_right','ru'=>'Справа','en'=>'Right');
			$locals[] = array('id'=>'prfilter_block_settings_position_center','ru'=>'По центру','en'=>'Center');
			$locals[] = array('id'=>'prfilter_block_settings_colomns','ru'=>'Колонок','en'=>'Columns');
			$locals[] = array('id'=>'pgn_prfilter_admin','ru'=>'Характеристики (расширенная версия)','en'=>'Custom parameters (extended version)');
			$locals[] = array('id'=>'pgn_prfilter_import_excel','ru'=>'Импорт из Excel','en'=>'Import from Excel');
			$locals[] = array('id'=>'pgn_prfilter_export_excel','ru'=>'Экспорт в Excel','en'=>'Export to Excel');
			$locals[] = array('id'=>'pgn_prfilter_templates','ru'=>'Редактор шаблонов','en'=>'Template editor');
			$locals[] = array('id'=>'pgn_prfilter_block','ru'=>'Характеристики','en'=>'Custom parameters');
			$locals[] = array('id'=>'prfilter_export_saved','ru'=>'Параметры успешно сохранены в файл','en'=>'Parameters are successfully stored in the file');
			$locals[] = array('id'=>'prfilter_noncategory_name','ru'=>'Без названия','en'=>'Untitled');
			$locals[] = array('id'=>'prfilter_excel_id','ru'=>'Идентификатор','en'=>'Identifier');
			$locals[] = array('id'=>'prfilter_excel_name','ru'=>'Название','en'=>'Name');
			$locals[] = array('id'=>'prfilter_excel_sort_order','ru'=>'Сортировка','en'=>'Sorting');
			$locals[] = array('id'=>'prfilter_excel_description_title','ru'=>'Заголовок описания','en'=>'Title description');
			$locals[] = array('id'=>'prfilter_excel_description_text','ru'=>'Текст описания','en'=>'Description text');
			$locals[] = array('id'=>'prfilter_excel_optiontype','ru'=>'Тип','en'=>'Type');
			$locals[] = array('id'=>'prfilter_excel_slider_step','ru'=>'Шаг для слайдера','en'=>'Step for the slider');
			$locals[] = array('id'=>'prfilter_excel_slider_prefix','ru'=>'Префикс для слайдера','en'=>'The prefix for the slider');
			$locals[] = array('id'=>'prfilter_excel_recomended','ru'=>'Рекомендуемый','en'=>'Recommended');
			$locals[] = array('id'=>'prfilter_noncategory','ru'=>'Без категории','en'=>'Uncategorized');
			$locals[] = array('id'=>'prfilter_addtotemplate','ru'=>'Добавить в шаблон','en'=>'Add to template');
			$locals[] = array('id'=>'prfilter_editname','ru'=>'Редактировать название','en'=>'Edit name');
			$locals[] = array('id'=>'prfilter_visibledefault','ru'=>'Видимость по умолчанию','en'=>'The default visibility');
			$locals[] = array('id'=>'prfilter_expand','ru'=>'Развернуто','en'=>'Deployed');
			$locals[] = array('id'=>'prfilter_collapse','ru'=>'Свернуто','en'=>'Collapsed');
			$locals[] = array('id'=>'prfilter_showallcategoryies','ru'=>'Показать все категории','en'=>'Show all categories');
			$locals[] = array('id'=>'prfilter_hideallcategoryies','ru'=>'Скрыть категории','en'=>'Hide categories');
			$locals[] = array('id'=>'prfilter_remove','ru'=>'Убрать','en'=>'Remove');
			$locals[] = array('id'=>'prfilter_empty','ru'=>'Список пуст','en'=>'The list is empty');
			$locals[] = array('id'=>'prfilter_prfilter','ru'=>'Доп. Характеристики','en'=>'Custom parameters');
			$locals[] = array('id'=>'prfilter_path_params','ru'=>'Возможные варианты значений для характеристики "%OPTION_NAME%"','en'=>'Possible options for custom parameters ​​of "%OPTION_NAME%"');
			$locals[] = array('id'=>'prfilter_path_params_excluded','ru'=>'Исключенные характеристики для "%VARIANT_NAME%" ','en'=>'Excluded parameters for "%VARIANT_NAME%"');
			$locals[] = array('id'=>'prfilter_removeall','ru'=>'Удалить все','en'=>'Remove all');
			$locals[] = array('id'=>'prfilter_removeallQ','ru'=>'Удалить все (характеристики и шаблоны)?','en'=>'Delete all (parameters and templates)?');
			$locals[] = array('id'=>'prfilter_menu_options','ru'=>'Характеристики','en'=>'Custom parameters');
			$locals[] = array('id'=>'prfilter_menu_templates','ru'=>'Редактор шаблонов','en'=>'Template editor');
			$locals[] = array('id'=>'prfilter_menu_import','ru'=>'Импорт из Excel','en'=>'Import from Excel');
			$locals[] = array('id'=>'prfilter_menu_export','ru'=>'Экспорт в Excel','en'=>'Export to Excel');
			$locals[] = array('id'=>'prfilter_excluded_description','ru'=>'Ниже представлены исключенные характеристики для характеристики \"%OPTION_NAME%\".<br>Исключенные характеристики используются в подборе товаров. При выборе характеристики \"%OPTION_NAME%\" выбранные ниже характеристики будут заблокированы.<br>Тип "слайдер" не может иметь исключенные характеристики и не может быть таковой.<br><i>Пример использования: Для характеристики "Нетбук" можно заблокировать такие характеристики как "Blu-Ray", "CD-RW", "DVD", "DVD-RW", "DVD/CD-RW" и т.д., так как нетбуки не могут иметь дисковода (за исключением некоторых моделей, но это скорее исключение из правил).</i>','en'=>'Below are the specifications for the characteristics of the excluded \"%OPTION_NAME%\". <br> Excluded characteristics are used in the selection of goods. When selecting features \"%OPTION_NAME%\" selected following features will be disabled. <br> Type "slider" can not be excluded characteristics and can not be so. <br> <i> Example: To characterize the "netbook" can be blocked characteristics such as "Blu-Ray", "CD-RW", "DVD", "DVD-RW", "DVD / CD-RW", etc., as may be netbooks drive (except for some models but this is an exception to the rule). </i>');
			$locals[] = array('id'=>'prfilter_excluded_notempty','ru'=>'(есть выбранные %EXIST_PARAMS% из %COUNT%)','en'=>'(Have selected %EXIST_PARAMS% of %COUNT%)');
			$locals[] = array('id'=>'prfilter_selectall','ru'=>'Выбрать все','en'=>'Select all');
			$locals[] = array('id'=>'prfilter_save','ru'=>'Сохранить','en'=>'Save');
			$locals[] = array('id'=>'prfilter_options_description','ru'=>'Здесь вы можете создать совершенно произвольные параметры, которые подходят продуктам вашего интернет-магазина - от цвета и размера, до мощности двигателя и тарифного плана. После добавления параметра здесь вы можете заполнить ее значение для каждого вашего продукта. В отличии от стандартного модуля, здесь параметры и его характеристики имеют расширенные настройки. Все эти новые параметры используются для настройки подбора товаров.<br>С подробной инструкцией по модулю Вы можете ознакомиться <a href="http://jorange.ru/webasyst/modules/6/instruction/" target="_blank">здесь</a> (откроется в новом окне).','en'=>'Here you can create a completely arbitrary parameters that are suitable products in your online store - from color and size to the power of the engine and the service plan. After adding a parameter here you can fill it with a value for each of your product. Unlike standard module here parameters and characteristics are advanced settings. All these new parameters are used to configure the selection of goods. <br> The detailed instructions for the module you can see <a href="http://jorange.ru/webasyst/modules/6/instruction/" target="_blank" here</a> (opens in new window ).');
			$locals[] = array('id'=>'prfilter_info_notsaved','ru'=>' - не сохраненные изменения','en'=>' - Unsaved changes');
			$locals[] = array('id'=>'prfilter_ecallcategories','ru'=>'Развернуть/свернуть все категории','en'=>'Expand / collapse all categories');
			$locals[] = array('id'=>'prfilter_addcategory','ru'=>'Добавить категорию','en'=>'Add category');
			$locals[] = array('id'=>'prfilter_empty2','ru'=>'(пусто)','en'=>'(Empty)');
			$locals[] = array('id'=>'prfilter_cancel','ru'=>'Отмена','en'=>'Cancel');
			$locals[] = array('id'=>'prfilter_captionnewcategoryname','ru'=>'Название новой характеристики','en'=>'The name of the new parameter');
			$locals[] = array('id'=>'prfilter_sortC','ru'=>'Сортировка','en'=>'Sorting');
			$locals[] = array('id'=>'prfilter_additionally','ru'=>'Дополнительно','en'=>'Additionally');
			$locals[] = array('id'=>'prfilter_descrition_title','ru'=>'Заголовок описания','en'=>'Title description');
			$locals[] = array('id'=>'prfilter_descrition_text','ru'=>'Описание','en'=>'Description');
			$locals[] = array('id'=>'prfilter_category','ru'=>'Категория','en'=>'Category');
			$locals[] = array('id'=>'prfilter_optiontype','ru'=>'Тип характеристики','en'=>'Type of parameter');
			$locals[] = array('id'=>'prfilter_optiontype_simple','ru'=>'Обычный','en'=>'Normal');
			$locals[] = array('id'=>'prfilter_optiontype_slider','ru'=>'Слайдер','en'=>'Slider');
			$locals[] = array('id'=>'prfilter_optiontype_single','ru'=>'Единичное','en'=>'Single');
			$locals[] = array('id'=>'prfilter_optionstep','ru'=>'Шаг слайдера','en'=>'Step Slider');
			$locals[] = array('id'=>'prfilter_optionstepprefix','ru'=>'Обозначение слайдера','en'=>'Designation slider');
			$locals[] = array('id'=>'prfilter_closewindow','ru'=>'Закрыть окно','en'=>'Close the window');
			$locals[] = array('id'=>'prfilter_prfilter_save','ru'=>'Сохранить и/или добавить','en'=>'Save and/or add');
			$locals[] = array('id'=>'prfilter_params_description','ru'=>'Ниже представлены все характеристики для параметра \"%OPTION_NAME%\" (тип: %OPTION_TYPE%).<br>Обратите внимание, что для типа "<b>слайдер</b>" нельзя прикрепить <u>изображение</u>, указать является ли характеристика <u>рекомендуемой</u> и добавить <u>исключающие</u> характеристики.<br>Для типа "<b>единичный</b>" нельзя указать является ли характеристика <u>рекомендуемой</u>.','en'=>'Below are all the characteristics of the parameter \"%OPTION_NAME%\" (type:%OPTION_TYPE%). <br> Please note that for the type of "<b> slider </b>" can not be attached <u> image </u> to indicate whether the response <u> recommended </u> and add <u> exclude </u> characteristics. <br> For type "<b> unit </b>" You can not specify whether the recommended response <u> </u>.');
			$locals[] = array('id'=>'prfilter_remomended','ru'=>'Рекомендуемый','en'=>'Recommended');
			$locals[] = array('id'=>'prfilter_yes','ru'=>'Да','en'=>'Yes');
			$locals[] = array('id'=>'prfilter_no','ru'=>'Нет','en'=>'No');
			$locals[] = array('id'=>'prfilter_image','ru'=>'Изображение','en'=>'Image');
			$locals[] = array('id'=>'prfilter_image_remove','ru'=>'Удалить изображение','en'=>'Remove image');
			$locals[] = array('id'=>'prfilter_image_description','ru'=>'Доступные форматы: <b>png</b>, <b>jpeg</b>.<br>Изображение не сжимается, и не уменьшается <br>в размерах при загрузке.','en'=>'Available formats: <b>png</b>, <b>jpeg</b>. <br>The image is not compressed, and is not reduced in size <br> at startup.');
			$locals[] = array('id'=>'prfilter_excluded_params','ru'=>'Исключенные параметры','en'=>'Excluded options');
			$locals[] = array('id'=>'prfilter_noimage','ru'=>'Нет изображения','en'=>'No picture');
			$locals[] = array('id'=>'prfilter_yesimage','ru'=>'Есть изображение','en'=>'There is a picture');
			$locals[] = array('id'=>'prfilter_description_titleyes','ru'=>'Есть заголовок','en'=>'A title');
			$locals[] = array('id'=>'prfilter_description_textyes','ru'=>'Есть описание','en'=>'There is a description of');
			$locals[] = array('id'=>'prfilter_captionnewparam','ru'=>'Название нового параметра','en'=>'The name of the new parameter');
			$locals[] = array('id'=>'prfilter_optiontype_simpleT','ru'=>'Тип обычный','en'=>'Type of normal');
			$locals[] = array('id'=>'prfilter_optiontype_sliderT','ru'=>'Тип слайдер','en'=>'Type Slider');
			$locals[] = array('id'=>'prfilter_optiontype_singleT','ru'=>'Тип единичный','en'=>'Type of unit');
			$locals[] = array('id'=>'prfilter_templates','ru'=>'Редактор шаблонов','en'=>'Template Editor');
			$locals[] = array('id'=>'prfilter_template_edit','ru'=>'Редактировать шаблон "%TEMPLATENAME%"','en'=>'Edit the template "%TEMPLATENAME%"');
			$locals[] = array('id'=>'prfilter_template_add','ru'=>'Добавить шаблон','en'=>'Add Template');
			$locals[] = array('id'=>'prfilter_templates_desc','ru'=>'Ниже представлены все шаблоны характеристик продуктов. Шаблоны позволяют быстро и легко настроить дополнительные характеристики сразу для нескольких категорий. Если одна категория применена сразу к нескольким шаблонам, то будет отображаться шаблон который включен и выше по приоритету.<br>Для изменения порядка приоритета шаблонов просто перетаскивайте их с помощью мышки.','en'=>'Here are all the templates filter products. Templates allow you to quickly and easily configure advanced features for multiple categories. If one category is applied to multiple templates, the template that will be displayed and included higher priority. <br>To change the priority order of patterns simply drag them with the mouse. ');
			$locals[] = array('id'=>'prfilter_templates_name','ru'=>'Название шаблона','en'=>'Template name');
			$locals[] = array('id'=>'prfilter_templates_enabled','ru'=>'Включен','en'=>'Included');
			$locals[] = array('id'=>'prfilter_templates_edit','ru'=>'Редактировать','en'=>'Edit');
			$locals[] = array('id'=>'prfilter_templates_empty','ru'=>'Ещё нет не одного шаблона','en'=>'Empty list');
			$locals[] = array('id'=>'prfilter_templates_add','ru'=>'Добавить шаблон','en'=>'Add Template');
			$locals[] = array('id'=>'prfilter_templateitem_notuse','ru'=>'НЕ используемые параметры','en'=>'DO NOT used options');
			$locals[] = array('id'=>'prfilter_templateitem_groupbycategory','ru'=>'Группировать по категориям','en'=>'Group by category');
			$locals[] = array('id'=>'prfilter_templateitem_addtotemplete','ru'=>'Добавить в шаблон','en'=>'Add to Template');
			$locals[] = array('id'=>'prfilter_templateitem_productname','ru'=>'Название продукта','en'=>'Product Name');
			$locals[] = array('id'=>'prfilter_templateitem_additionally','ru'=>'Дополнительные настройки…','en'=>'More settings ...');
			$locals[] = array('id'=>'prfilter_templateitem_visibility','ru'=>'Видимость по умолчанию','en'=>'The default visibility');
			$locals[] = array('id'=>'prfilter_templateitem_price','ru'=>'Цена','en'=>'Price');
			$locals[] = array('id'=>'prfilter_templateitem_limits','ru'=>'Показывать лимиты','en'=>'Show limits');
			$locals[] = array('id'=>'prfilter_templateitem_labels','ru'=>'Показывать лейблы','en'=>'Show labels');
			$locals[] = array('id'=>'prfilter_templateitem_instock','ru'=>'В наличии','en'=>'Available');
			$locals[] = array('id'=>'prfilter_templates_enabled_desc','ru'=>'Активирует шаблон для категорий.','en'=>'Activates template for categories.');
			$locals[] = array('id'=>'prfilter_templateitem_expandcategoryes','ru'=>'Свернуть все категории','en'=>'Hide all categories');
			$locals[] = array('id'=>'prfilter_templateitem_expandcategoryes_desc','ru'=>'Активна, если включена группировка по категориям. Игнорирует индивидуальную настройку','en'=>'Active if enabled grouping by category. Ignores customization');
			$locals[] = array('id'=>'prfilter_templateitem_expandoptions','ru'=>'Свернуть все параметры','en'=>'Hide all options');
			$locals[] = array('id'=>'prfilter_templateitem_expandoptions_desc','ru'=>'Игнорирует индивидуальную настройку','en'=>'Ignores customization');
			$locals[] = array('id'=>'prfilter_templateitem_applyedcount','ru'=>'Применить шаблон к категориям (<span>0</span> категорий)','en'=>'Apply a template to a category (<span>0</span> categories)');
			$locals[] = array('id'=>'prfilter_addallcategoryes','ru'=>'Добавить все категории','en'=>'Add all categories');
			$locals[] = array('id'=>'prfilter_removeallcategoryes','ru'=>'Удалить все','en'=>'Remove all');
			$locals[] = array('id'=>'prfilter_templateitem_use','ru'=>'Используемые параметры','en'=>'The parameters used');
			$locals[] = array('id'=>'prfilter_templateitem_groupbycategory_inuse','ru'=>'Группировать по категориям <span>(отображается в пользовательской части)</span>','en'=>'Group by category <span> (displayed in the user part) </span>');
			$locals[] = array('id'=>'prfilter_templateitem_save','ru'=>'Сохранить шаблон','en'=>'Save Template');
			$locals[] = array('id'=>'prfilter_add','ru'=>'Добавить','en'=>'Add');
			$locals[] = array('id'=>'prfilter_templateitem_type','ru'=>'Тип отображения','en'=>'Type of Display');
			$locals[] = array('id'=>'prfilter_templateitem_colomns','ru'=>'Колонки (шт.)','en'=>'Columns (pcs)');
			$locals[] = array('id'=>'prfilter_templateitem_images','ru'=>'Отображение изображений','en'=>'Displaying Images');
			$locals[] = array('id'=>'prfilter_templateitem_images_text','ru'=>'Без изображений','en'=>'No images');
			$locals[] = array('id'=>'prfilter_templateitem_images_imagetext','ru'=>'Изображения и текст','en'=>'Images and text');
			$locals[] = array('id'=>'prfilter_templateitem_images_image','ru'=>'Только изображения','en'=>'Only Images');
			$locals[] = array('id'=>'prfilter_templateitem_imageuse','ru'=>'Использовать изображение','en'=>'Use Image');
			$locals[] = array('id'=>'prfilter_export','ru'=>'Экспорт в Excel','en'=>'Export to Excel');
			$locals[] = array('id'=>'prfilter_export_desc','ru'=>'В этом разделе вы можете экспортировать характеристики и параметры характеристик магазина в файл CSV (Comma Separated Values; файл с разделителями-запятыми).<br>Экспортированный файл может быть загружен для редактирования в Microsoft Excel или OpenOffice, а также импортирован в ваш интернет-магазин в разделе "Импорт из Excel".<br><br>Пожалуйста, выберите разделитель в CSV файле, категории, которые вы хотели бы экспортировать, и нажмите кнопку "Экспортировать продукты".<br>Инструменты экспорта и импорта продуктов удобно использовать, например, для создания резервной характеристик и параметров характеристик магазина, или же, для быстрого редактирования характеристик и параметров характеристик в одной таблице с помощью Microsoft Excel или OpenOffice.','en'=>'In this section, you can export the characteristics and parameters of the characteristics of the store in a file CSV (Comma Separated Values; comma-delimited). <br>  The exported file can be downloaded for editing in Microsoft Excel or OpenOffice, and imported into your online store in "Import from Excel".<br><br>Please choose a separator in a CSV file, which categories you would like to export and click on the "Export products". <br>Tools export and import of products is useful, for example, to create a backup of characteristics and performance parameters store or, for quick editing features and performance parameters in one table using Microsoft Excel or OpenOffice. ');
			$locals[] = array('id'=>'prfilter_delimiter','ru'=>'Разделитель в импортируемом CSV файле:<br>(задается в настройках Windows;<br>по умолчанию - точка с запятой).<br>В случае списка номенклатуры 1С<br>выберите разделитель "Табуляция".','en'=>'Separator in the imported CSV file: <br>(Specified in the settings of Windows; <br>default - the semicolon). <br>For a list of nomenclature 1C <br>choose a separator "Tab".');
			$locals[] = array('id'=>'prfilter_charset','ru'=>'Кодировка файла','en'=>'File Encoding');
			$locals[] = array('id'=>'prfilter_export_save','ru'=>'Экспортировать характеристики','en'=>'Export performance');
			$locals[] = array('id'=>'prfilter_import','ru'=>'Импорт из Excel','en'=>'Import from Excel');
			$locals[] = array('id'=>'prfilter_import_step2','ru'=>'Шаг 2/2','en'=>'Step 2/2');
			$locals[] = array('id'=>'prfilter_import_step1','ru'=>'Шаг 1/2','en'=>'Step 1/2');
			$locals[] = array('id'=>'prfilter_import_desc','ru'=>'<p>В этом разделе вы можете импортировать характеристики и параметры характеристик в ваш магазин из файла CSV (Comma Separated Values; файл с разделителями-запятыми). CSV файлы вы можете создать и редактировать с помощью Microsoft Excel или OpenOffice.</p><p>Например, если вы хотите импортировать характеристики и параметры характеристик в интернет-магазин из вашего документа в Excel, нужно сохранить документ в формате CSV (пункт в меню "Сохранить как..." - затем выберите CSV в выборе формата файла).<br>Далее, выберите сохраненный файл в следующей форме, и укажите кодировку файла (наиболее вероятно, это будет кодировка cp1251).<br>ВАЖНО: Система может производить загрузку данных только из CSV файлов только с определенной организацией (структурой) строк и столбцов. Это значит, что вам необходимо привести ваш прайс-лист к такой структуре для того, чтобы загрузить файл.<br>.<a href="http://jorange.ru/webasyst/modules/6/instruction/" target="_blank">Посмотрите подробное описание структуры файла</a> (откроется в новом окне).</p>','en'=>'<p>In this section, you can import the characteristics and performance parameters to your store from a file CSV (Comma Separated Values; comma-delimited). CSV files you can create and edit with Microsoft Excel or OpenOffice.</P><p>For example, if you want to import the characteristics and performance parameters in the online store of your document to Excel, you need to save a document in CSV (menu item "Save As ..." - and then select the CSV file format is selected.) <br>Next, select the file in the following form, and specify the file encoding (most likely, it will be encoding cp1251). <br>IMPORTANT: The system can load data only from CSV files only with a specific organization (structure) of rows and columns. This means that you need to bring is your price list to the structure in order to download the file. <br>. <a Href="http://jorange.ru/webasyst/modules/6/instruction/" target="_blank"> See a detailed description of the file </a> (opens in new window).</P>');
			$locals[] = array('id'=>'prfilter_import_file','ru'=>'Выберите CSV файл, из которого вы хотели бы загрузить данные','en'=>'Select the CSV file that you want to download the data');
			$locals[] = array('id'=>'prfilter_import_load','ru'=>'Загрузить','en'=>'Download');
			$locals[] = array('id'=>'prfilter_continue','ru'=>'Продолжить','en'=>'Proceed');
			$locals[] = array('id'=>'prfilter_import_addcategory','ru'=>'Добавлено категорий','en'=>'Posted categories');
			$locals[] = array('id'=>'prfilter_import_updatecategory','ru'=>'Обновлено категорий','en'=>'Updated categories');
			$locals[] = array('id'=>'prfilter_import_addoptions','ru'=>'Добавлено характеристик','en'=>'Added features');
			$locals[] = array('id'=>'prfilter_import_updateoptions','ru'=>'Обновлено характеристик','en'=>'Updated features');
			$locals[] = array('id'=>'prfilter_import_addparams','ru'=>'Добавлено значений характеристик','en'=>'Added value features');
			$locals[] = array('id'=>'prfilter_import_updateparams','ru'=>'Обновлено значений характеристик','en'=>'Updated property values');
			$locals[] = array('id'=>'prfilter_import_desc2','ru'=>'<p>В закачанном файле обнаружены следующие колонки.<br>Соотнесите каждую из этих колонок с полем в базе данных.<br>В левой колонке указаны названия столбцов.</p><p>Если характеристика или параметр характеристики есть и в базе, и в файле (ищется совпадение по колонке идентификации), то обновить информацию о нем.<br>Если же характеристика или параметр характеристики найден только в файле, то добавить его в базу данных.<br>Иначе (если характеристика или параметр характеристики есть только в базе данных) оставить его без изменений.</p>','en'=>'<p>Upload a file found in the following columns. <br>Relate each of these columns in a database field. <br>The left column contains the column names. </P><p>If the characteristic or characteristics of the parameter exists in the database, and the file (looked up match for column identification), then update the information about it. <br>If the characteristic parameter or characteristic is found only in the file, then add it to the database. <br>	Otherwise (if the feature or option features are only available in the database) to leave it unchanged.</P>');
			$locals[] = array('id'=>'prfilter_import_addedinfo','ru'=>'Отчет об импорте характеристик','en'=>'Report on the import performance');
			$locals[] = array('id'=>'prfilter_delete','ru'=>'Удалить','en'=>'Remove');
			$locals[] = array('id'=>'prfilter_founded','ru'=>'Найдено предложений: <b>%d</b> <a href="javascript:void(0);" onclick="$(%blockID%).submit();">Показать</a>','en'=>'We have found: <b>%d</b> <a href="javascript:void(0);" onclick="$(%blockID%).submit();"> Show</a>');
			$locals[] = array('id'=>'prfilter_nofounded','ru'=>'К сожалению, ничего не найдено.','en'=>'Sorry, no matches found.');
			$locals[] = array('id'=>'prfilter_unckeck','ru'=>'Отменить все параметры характеристики','en'=>'Cancel all settings features');
			$locals[] = array('id'=>'prfilter_close','ru'=>'Закрыть','en'=>'Close');
			$locals[] = array('id'=>'prfilter_apply','ru'=>'Найти продукты','en'=>'Search products');
			$locals[] = array('id'=>'prfilter_unckeck_all','ru'=>'Отменить все','en'=>'Cancel all');
			$locals[] = array('id'=>'prfilter_byname','ru'=>'По названию','en'=>'By name');
			$locals[] = array('id'=>'prfilter_byprice','ru'=>'По цене','en'=>'At the price of');
			$locals[] = array('id'=>'prfilter_from','ru'=>'от','en'=>'from');
			$locals[] = array('id'=>'prfilter_to','ru'=>'до','en'=>'to');
			$locals[] = array('id'=>'prfilter_byinstock','ru'=>'В наличии','en'=>'Available');
			$locals[] = array('id'=>'prfilter_all','ru'=>'все','en'=>'all');
			$locals[] = array('id'=>'prfilter_nomatter','ru'=>'не важно','en'=>'not important');
			$locals[] = array('id'=>'prfilter_psearch','ru'=>'Расширенный поиск продуктов','en'=>'Advanced Product Search');
			$locals[] = array('id'=>'prfilter_psearch_category','ru'=>'Категория для поиска','en'=>'The category for search');
			$locals[] = array('id'=>'prfilter_category_choose','ru'=>'Выберите категорию','en'=>'Select a category');
			$locals[] = array('id'=>'prfilter_recomended','ru'=>'популярные','en'=>'Popular');
			$locals[] = array('id'=>'prfilter_params_description_sim','ru'=>'обычный','en'=>'simple');
			$locals[] = array('id'=>'prfilter_params_description_sli','ru'=>'слайдер','en'=>'slider');
			$locals[] = array('id'=>'prfilter_params_description_sin','ru'=>'единичный','en'=>'single');
			$locals[] = array('id'=>'prfilter_template_addcategory','ru'=>'Добавить категорию','en'=>'Add category');
			$locals[] = array('id'=>'prfilter_block_settings_category','ru'=>'Категории','en'=>'Categories');
			$locals[] = array('id'=>'prfilter_block_settings_template','ru'=>'Шаблон','en'=>'Template');
			$locals[] = array('id'=>'prfilter_block_settings_showselected','ru'=>'Отметить выбранное','en'=>'Mark selected');
			$locals[] = array('id'=>'prfilter_index_block','ru'=>'Характеристики (Ajax)','en'=>'Custom parameters (ajax)');
			$locals[] = array('id'=>'prfilter_block_settings_default','ru'=>'По умолчанию','en'=>'By default');
			$locals[] = array('id'=>'prfilter_block_settings_allcategories','ru'=>'Все категории','en'=>'All Categories');
			$locals[] = array('id'=>'prfilter_block','ru'=>'Характеристики для категории (ajax)','en'=>'Custom parameters for the category (ajax)');
			$locals[] = array('id'=>'png_prfilter','ru'=>'Расширенный поиск продуктов','en'=>'Advanced Product Search');
			$locals[] = array('id'=>'conf_prfilter_colomns_on_psearch_title','ru'=>'Колонок с характеристиками','en'=>'Columns with filters');
			$locals[] = array('id'=>'conf_prfilter_colomns_on_psearch_description','ru'=>'Количество колонок характеристик на странице расширенного поиска товаров. (http://ВАШ_МАГАЗИН/psearch)','en'=>'The number of columns with the filters on the advanced search page catalog. (Http://your_store/psearch)');

					
			foreach($languages as $language){			
				if($language->iso2 == 'ru'){
					foreach($locals as $local){	
						$sqls[] = array('sql'=>"INSERT INTO `".DBTABLE_PREFIX."local` VALUES ('".$local['id']."', ".$language->id.", '".$local['ru']."', 'general', 'gen')",'success_msg'=>"Запись в ".DBTABLE_PREFIX."local успешно добавлена");  
					}
				}else{
					foreach($locals as $local){	
						$sqls[] = array('sql'=>"INSERT INTO `".DBTABLE_PREFIX."local` VALUES ('".$local['id']."', ".$language->id.", '".$local['en']."', 'general', 'gen')",'success_msg'=>"Запись в ".DBTABLE_PREFIX."local успешно добавлена");  
					}
				}
			} 
					
			$i = 1;	
			$returnValues = array();	
			foreach($sqls as $key => $sql){
				$percent = intval($i/count($sqls) * 100)."%";		
				if($sql['eval'] == true){
					eval($sql['sql']);
				}else if($sql['inputValue2']){
					db_phquery($sql['sql'],$returnValues[$sql['inputValue']],$returnValues['ModuleConfigID'].$sql['inputValue2']);
				}else if($sql['inputValue']){
					db_phquery($sql['sql'],$returnValues[$sql['inputValue']]);
				}else if($sql['inputValue3']){
					db_phquery($sql['sql'],$returnValues['ModuleConfigID'].$sql['inputValue3']);
				}else{
					db_phquery($sql['sql']);
				}				
				if($sql['returnArgument']) $returnValues[$sql['returnArgument']] = db_insert_id();
						 
				echo '<script language="javascript">
				document.getElementById("progress").innerHTML="<div style=\"width:'.$percent.';\">'.$percent.'</div>";
				</script>';
				echo '<script language="javascript">
					document.getElementById("information").innerHTML=document.getElementById("information").innerHTML+"<div class=\"check_file\"><div class=\"check_file_success\">Успешно</div>'.$sqls[$key]['success_msg'].'</div>";
					</script>';
					
				echo str_repeat(' ',1024*64);
				flush();
				usleep(100000);
				$i++;
			}
			return true;
		}
	
		function delete_sql(){
			
			$languages = &LanguagesManager::getLanguages();
	
			$sqls = array();
			$sqls[] = array('sql'=>"DROP TABLE IF EXISTS `".DBTABLE_PREFIX."product_options_categoryes`",'success_msg'=>"Удаляем таблицу ".DBTABLE_PREFIX."product_options_categoryes");
			$sqls[] = array('sql'=>"DROP TABLE IF EXISTS `".DBTABLE_PREFIX."product_options_templates`",'success_msg'=>"Удаляем таблицу ".DBTABLE_PREFIX."product_options_templates");
			$sqls[] = array('sql'=>"DROP TABLE IF EXISTS `".DBTABLE_PREFIX."product_options_templates_categoryes`",'success_msg'=>"Удаляем таблицу ".DBTABLE_PREFIX."product_options_templates_categoryes");
			
			
		//SC_product_options
			$sqls[] = array('sql'=>"db_delete_column(PRODUCT_OPTIONS_TABLE, 'slider_step', true );",'success_msg'=>"Удаляем колонку slider_step в таблице ".PRODUCT_OPTIONS_TABLE.", если она существует",'eval'=>true);
			$sqls[] = array('sql'=>"db_delete_column(PRODUCT_OPTIONS_TABLE, 'optionType', true );",'success_msg'=>"Удаляем колонку optionType в таблице ".PRODUCT_OPTIONS_TABLE.", если она существует",'eval'=>true);
			$sqls[] = array('sql'=>"db_delete_column(PRODUCT_OPTIONS_TABLE, 'optionCategory', true );",'success_msg'=>"Удаляем колонку optionCategory в таблице ".PRODUCT_OPTIONS_TABLE.", если она существует",'eval'=>true);
			
			foreach($languages as $language){	
				$sqls[] = array('sql'=>"db_delete_column(PRODUCT_OPTIONS_TABLE, 'description_title_".$language->iso2."', true );",'success_msg'=>"Удаляем колонку description_title_".$language->iso2." в таблице ".PRODUCT_OPTIONS_TABLE.", если она существует",'eval'=>true);
				$sqls[] = array('sql'=>"db_delete_column(PRODUCT_OPTIONS_TABLE, 'description_text_".$language->iso2."', true );",'success_msg'=>"Удаляем колонку description_text_".$language->iso2." в таблице ".PRODUCT_OPTIONS_TABLE.", если она существует",'eval'=>true);
				$sqls[] = array('sql'=>"db_delete_column(PRODUCT_OPTIONS_TABLE, 'slider_prefix_".$language->iso2."', true );",'success_msg'=>"Удаляем колонку slider_prefix_".$language->iso2." в таблице ".PRODUCT_OPTIONS_TABLE.", если она существует",'eval'=>true);
			}
			
			$sqls[] = array('sql'=>"db_delete_column(PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE, 'recomended', true );",'success_msg'=>"Удаляем колонку recomended в таблице ".PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE.", если она существует",'eval'=>true);
			$sqls[] = array('sql'=>"db_delete_column(PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE, 'excluded', true );",'success_msg'=>"Удаляем колонку excluded в таблице ".PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE.", если она существует",'eval'=>true);
			$sqls[] = array('sql'=>"db_delete_column(PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE, 'picture', true );",'success_msg'=>"Удаляем колонку picture в таблице ".PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE.", если она существует",'eval'=>true);
			
			foreach($languages as $language){
				$sqls[] = array('sql'=>"db_delete_column(PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE, 'description_title_".$language->iso2."', true );",'success_msg'=>"Удаляем колонку description_title_".$language->iso2." в таблице ".PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE.", если она существует",'eval'=>true);
				$sqls[] = array('sql'=>"db_delete_column(PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE, 'description_text_".$language->iso2."', true );",'success_msg'=>"Удаляем колонку description_text_".$language->iso2." в таблице ".PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE.", если она существует",'eval'=>true);
			}
		//modules
			$sqls[] = array('sql'=>"DELETE FROM `".DBTABLE_PREFIX."modules` WHERE `ModuleClassName` = 'prfilter'",'success_msg'=>"Удаляем запись в ".DBTABLE_PREFIX."modules, если она существует");	
		//module_configs	
			$sqls[] = array('sql'=>"DELETE FROM `".DBTABLE_PREFIX."module_configs` WHERE `ConfigKey` = 'prfilter'",'success_msg'=>"Удаляем запись в ".DBTABLE_PREFIX."module_configs, если она существует");
		//settings
			$sqls[] = array('sql'=>"DELETE FROM `".DBTABLE_PREFIX."settings` WHERE `settings_constant_name` = 'CONF_PRFILTER_COLOMNS_ON_PSEARCH'",'success_msg'=>"Удаляем запись в ".DBTABLE_PREFIX."settings, если она существует");
		//divisions		
			$sqls[] = array('sql'=>"DELETE FROM `".DBTABLE_PREFIX."divisions` WHERE `xName` = 'pgn_prfilter_admin'",'success_msg'=>"Удаляем запись в ".DBTABLE_PREFIX."divisions, если она существует");
			$sqls[] = array('sql'=>"DELETE FROM `".DBTABLE_PREFIX."divisions` WHERE `xName` = 'pgn_prfilter_templates'",'success_msg'=>"Удаляем запись в ".DBTABLE_PREFIX."divisions, если она существует");
			$sqls[] = array('sql'=>"DELETE FROM `".DBTABLE_PREFIX."divisions` WHERE `xName` = 'png_prfilter'",'success_msg'=>"Удаляем запись в ".DBTABLE_PREFIX."divisions, если она существует");
			$sqls[] = array('sql'=>"DELETE FROM `".DBTABLE_PREFIX."divisions` WHERE `xName` = 'pgn_prfilter_import_excel'",'success_msg'=>"Удаляем запись в ".DBTABLE_PREFIX."divisions, если она существует");
			$sqls[] = array('sql'=>"DELETE FROM `".DBTABLE_PREFIX."divisions` WHERE `xName` = 'pgn_prfilter_export_excel'",'success_msg'=>"Удаляем запись в ".DBTABLE_PREFIX."divisions, если она существует");	
		//division_interface			
			$sqls[] = array('sql'=>"DELETE FROM `".DBTABLE_PREFIX."division_interface` WHERE `xInterface` like('%prfilter%')",'success_msg'=>"Удаляем записи в ".DBTABLE_PREFIX."division_interface, если они существует");	
		//interface_interfaces
			$sqls[] = array('sql'=>"DELETE FROM `".DBTABLE_PREFIX."interface_interfaces` WHERE `xInterfaceCalled` like('%prfilter%')",'success_msg'=>"Удаляем записи в ".DBTABLE_PREFIX."interface_interfaces, если они существует");
		//local
			$sqls[] = array('sql'=>"DELETE FROM `".DBTABLE_PREFIX."local` WHERE id like('%prfilter%')",'success_msg'=>"Удаляем записи в ".DBTABLE_PREFIX."local, если они существует");
			
			$i = 1;	
			$returnValues = array();	
			foreach($sqls as $key => $sql){
				$percent = intval($i/count($sqls) * 100)."%";	
				if($sql['eval'] == true){
					eval($sql['sql']);
				}else{
					db_phquery($sql['sql']);	
				}
				echo '<script language="javascript">
				document.getElementById("progress").innerHTML="<div style=\"width:'.$percent.';\">'.$percent.'</div>";
				</script>';
				echo '<script language="javascript">
					document.getElementById("information").innerHTML=document.getElementById("information").innerHTML+"<div class=\"check_file\"><div class=\"check_file_success\">Успешно</div>'.$sqls[$key]['success_msg'].'</div>";
					</script>';
					
				echo str_repeat(' ',1024*64);
				flush();
				usleep(100000);
				$i++;
			}
			return true;
		}
	

		function update_files(){
		
			//tables
			$tables_input = 'define(\'PRODUCTS_OPTIONS_SET_TABLE\', DBTABLE_PREFIX.\'product_options_set\');'; 					
			$tables_output = 'define(\'PRODUCTS_OPTIONS_SET_TABLE\', DBTABLE_PREFIX.\'product_options_set\');	
define(\'PRODUCT_OPTIONS_CATEGORYES_TABLE\', DBTABLE_PREFIX.\'product_options_categoryes\');
define(\'PRODUCT_OPTIONS_TEMPLATES_TABLE\', DBTABLE_PREFIX.\'product_options_templates\');
define(\'PRODUCT_OPTIONS_TEMPLATES_CATEGORYES_TABLE\', DBTABLE_PREFIX.\'product_options_templates_categoryes\');'; 
			$UpdateFiles[0]['file'] = WBS_DIR."published/SC/html/scripts/cfg/tables.inc.wa.php";
			$UpdateFiles[0]['data'] = file_get_contents($UpdateFiles[0]['file']); 
			$UpdateFiles[0]['data'] = str_replace($tables_output,$tables_input,$UpdateFiles[0]['data']);
			$UpdateFiles[0]['data'] = str_replace($tables_input,$tables_output,$UpdateFiles[0]['data']);
			$UpdateFiles[0]['msg'] = "/published/SC/html/scripts/cfg/tables.inc.wa.php";	
			$UpdateFiles[0]['check'][0] = $tables_output;
			
			
			//class.languagesmanager.php
			$languagesmanager_input1 = '				CURRENCY_TYPES_TABLE => array(
					\'Name\', \'display_template\'
				),'; 					
			$languagesmanager_output1 = '				CURRENCY_TYPES_TABLE => array(
					\'Name\', \'display_template\'
				),
				PRODUCT_OPTIONS_CATEGORYES_TABLE => array(
                    \'category_name\'
                ),'; 
			$languagesmanager_input2 = '				PRODUCT_OPTIONS_TABLE => array(
					\'name\',
				),'; 					
			$languagesmanager_output2 = '				PRODUCT_OPTIONS_TABLE => array(
					\'name\', \'description_title\', \'description_text\', \'slider_prefix\'
                ),'; 
			$languagesmanager_input3 = '				PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE => array(
					\'option_value\'
				),'; 					
			$languagesmanager_output3 = '				PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE => array(
                    \'option_value\', \'description_title\', \'description_text\'
                ),'; 
				
			$UpdateFiles[1]['file'] = WBS_DIR."published/SC/html/scripts/classes/class.languagesmanager.php";
			$UpdateFiles[1]['data'] = file_get_contents($UpdateFiles[1]['file']); 
			$UpdateFiles[1]['data'] = str_replace($languagesmanager_output1,$languagesmanager_input1,$UpdateFiles[1]['data']);
			$UpdateFiles[1]['data'] = str_replace($languagesmanager_input1,$languagesmanager_output1,$UpdateFiles[1]['data']);
			$UpdateFiles[1]['data'] = str_replace($languagesmanager_output2,$languagesmanager_input2,$UpdateFiles[1]['data']);
			$UpdateFiles[1]['data'] = str_replace($languagesmanager_input2,$languagesmanager_output2,$UpdateFiles[1]['data']);
			$UpdateFiles[1]['data'] = str_replace($languagesmanager_output3,$languagesmanager_input3,$UpdateFiles[1]['data']);
			$UpdateFiles[1]['data'] = str_replace($languagesmanager_input3,$languagesmanager_output3,$UpdateFiles[1]['data']);
			$UpdateFiles[1]['msg'] = "/published/SC/html/scripts/classes/class.languagesmanager.php";	
			$UpdateFiles[1]['check'][0] = $languagesmanager_output1;
			$UpdateFiles[1]['check'][1] = $languagesmanager_output2;
			$UpdateFiles[1]['check'][2] = $languagesmanager_output3;
			
			
			//category.php
			$category_input = '	$smarty->assign( \'categoryID\', $categoryID);'; 					
			$category_output = '	$smarty->assign( \'categoryID\', $categoryID);
	if(isset($_GET[\'psearch\'])){
		ProductFilter_ShowProducts($_GET, $callBackParam, $smarty);	
	}'; 
				
			$UpdateFiles[2]['file'] = WBS_DIR."published/SC/html/scripts/includes/category.php";
			$UpdateFiles[2]['data'] = file_get_contents($UpdateFiles[2]['file']); 
			$UpdateFiles[2]['data'] = str_replace($category_output,$category_input,$UpdateFiles[2]['data']);
			$UpdateFiles[2]['data'] = str_replace($category_input,$category_output,$UpdateFiles[2]['data']);
			$UpdateFiles[2]['msg'] = "/published/SC/html/scripts/includes/category.php";	
			$UpdateFiles[2]['check'][0] = $category_output;
			
			
			//index.php
			$index_input = '	require_once(DIR_FUNC.\'/option_functions.php\' );'; 					
			$index_output = '	//require_once(DIR_FUNC.\'/option_functions.php\' );
	require_once(DIR_FUNC.\'/prfilter_functions.php\');'; 
				
			$UpdateFiles[3]['file'] = WBS_DIR."published/SC/html/scripts/index.php";
			$UpdateFiles[3]['data'] = file_get_contents($UpdateFiles[3]['file']); 
			$UpdateFiles[3]['data'] = str_replace($index_output,$index_input,$UpdateFiles[3]['data']);
			$UpdateFiles[3]['data'] = str_replace($index_input,$index_output,$UpdateFiles[3]['data']);
			$UpdateFiles[3]['msg'] = "/published/SC/html/scripts/index.php";	
			$UpdateFiles[3]['check'][0] = $index_output;
			
			
			$i = 1;	
			$error = 0;
			$returnValues = array();	
			foreach($UpdateFiles as $File){
				$percent = intval($i/count($UpdateFiles) * 100)."%";	
				$Abort = false;
				if (is_writable($File['file'])) {
					if (!$handle = fopen($File['file'], 'w')) {
						$error = '<script language="javascript">
						document.getElementById("information").innerHTML=document.getElementById("information").innerHTML+"<div class=\"check_file_problem\">Файл не найден</div><div class=\"check_file\">'.$File['msg'].'</div>";
						</script>';
						$Abort = true;
					}
					if (fwrite($handle, $File['data']) === FALSE) {
						$error = '<script language="javascript">
						document.getElementById("information").innerHTML=document.getElementById("information").innerHTML+"<div class=\"check_file_problem\">Ошибка записи</div><div class=\"check_file\">'.$File['msg'].'</div>";
						</script>';
						$Abort = true;
					}    
					fclose($handle);
				} else {
					$error = '<script language="javascript">
					document.getElementById("information").innerHTML=document.getElementById("information").innerHTML+"<div class=\"check_file_problem\">Ошибка прав записи</div><div class=\"check_file\">'.$File['msg'].'</div>";
					</script>';
					$Abort = true;
				} 
				
				foreach($File['check'] as $check){
					$filecontent = file_get_contents($File['file']); 
					$pos = strpos($filecontent, $check);
					if ($pos === false) {
						$error = '<script language="javascript">
						document.getElementById("information").innerHTML=document.getElementById("information").innerHTML+"<div class=\"check_file_problem\">Не получилось записать данные в файл.</div><div class=\"check_file\">'.$File['msg'].'</div>";
						</script>';
						$Abort = true;
						break;
					}
				}
			
				echo '<script language="javascript">
				document.getElementById("progress").innerHTML="<div style=\"width:'.$percent.';\">'.$percent.'</div>";
				</script>';
				
				if($File['msg'] && !$Abort ){
					echo '<script language="javascript">
					document.getElementById("information").innerHTML=document.getElementById("information").innerHTML+"<div class=\"check_file_success\">Успешно</div><div class=\"check_file\">'.$File['msg'].'</div>";
					</script>';
				}
					
				echo str_repeat(' ',1024*64);
				flush();
				usleep(500000);
				$i++;
			}
			if(!$error){
				return false;
			}else{
				echo $error;
				return true;
			}
		}
		
		function delete_update_files(){
		
			//tables
			$tables_input = 'define(\'PRODUCTS_OPTIONS_SET_TABLE\', DBTABLE_PREFIX.\'product_options_set\');'; 					
			$tables_output = 'define(\'PRODUCTS_OPTIONS_SET_TABLE\', DBTABLE_PREFIX.\'product_options_set\');	
define(\'PRODUCT_OPTIONS_CATEGORYES_TABLE\', DBTABLE_PREFIX.\'product_options_categoryes\');
define(\'PRODUCT_OPTIONS_TEMPLATES_TABLE\', DBTABLE_PREFIX.\'product_options_templates\');
define(\'PRODUCT_OPTIONS_TEMPLATES_CATEGORYES_TABLE\', DBTABLE_PREFIX.\'product_options_templates_categoryes\');'; 
			$UpdateFiles[0]['file'] = WBS_DIR."published/SC/html/scripts/cfg/tables.inc.wa.php";
			$UpdateFiles[0]['data'] = file_get_contents($UpdateFiles[0]['file']); 
			$UpdateFiles[0]['data'] = str_replace($tables_output,$tables_input,$UpdateFiles[0]['data']);
			$UpdateFiles[0]['msg'] = "/published/SC/html/scripts/cfg/tables.inc.wa.php";	
			
			
			//class.languagesmanager.php
			$languagesmanager_input1 = '				CURRENCY_TYPES_TABLE => array(
					\'Name\', \'display_template\'
				),'; 					
			$languagesmanager_output1 = '				CURRENCY_TYPES_TABLE => array(
					\'Name\', \'display_template\'
				),
				PRODUCT_OPTIONS_CATEGORYES_TABLE => array(
                    \'category_name\'
                ),'; 
			$languagesmanager_input2 = '				PRODUCT_OPTIONS_TABLE => array(
					\'name\',
				),'; 					
			$languagesmanager_output2 = '				PRODUCT_OPTIONS_TABLE => array(
                    \'name\', \'description_title\', \'description_text\', \'slider_prefix\'
                ),'; 
			$languagesmanager_input3 = '				PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE => array(
					\'option_value\'
				),'; 					
			$languagesmanager_output3 = '				PRODUCTS_OPTIONS_VALUES_VARIANTS_TABLE => array(
                    \'option_value\', \'description_title\', \'description_text\'
                ),'; 
				
			$UpdateFiles[1]['file'] = WBS_DIR."published/SC/html/scripts/classes/class.languagesmanager.php";
			$UpdateFiles[1]['data'] = file_get_contents($UpdateFiles[1]['file']); 
			$UpdateFiles[1]['data'] = str_replace($languagesmanager_output1,$languagesmanager_input1,$UpdateFiles[1]['data']);
			$UpdateFiles[1]['data'] = str_replace($languagesmanager_output2,$languagesmanager_input2,$UpdateFiles[1]['data']);
			$UpdateFiles[1]['data'] = str_replace($languagesmanager_output3,$languagesmanager_input3,$UpdateFiles[1]['data']);
			$UpdateFiles[1]['msg'] = "/published/SC/html/scripts/classes/class.languagesmanager.php";	
			
			
			//category.php
			$category_input = '	$smarty->assign( \'categoryID\', $categoryID);'; 					
			$category_output = '	$smarty->assign( \'categoryID\', $categoryID);
	if(isset($_GET[\'psearch\'])){
		ProductFilter_ShowProducts($_GET, $callBackParam, $smarty);	
	}'; 
				
			$UpdateFiles[2]['file'] = WBS_DIR."published/SC/html/scripts/includes/category.php";
			$UpdateFiles[2]['data'] = file_get_contents($UpdateFiles[2]['file']); 
			$UpdateFiles[2]['data'] = str_replace($category_output,$category_input,$UpdateFiles[2]['data']);
			$UpdateFiles[2]['msg'] = "/published/SC/html/scripts/includes/category.php";	
		
			//index.php
			$index_input = '	require_once(DIR_FUNC.\'/option_functions.php\' );'; 					
			$index_output = '	//require_once(DIR_FUNC.\'/option_functions.php\' );
	require_once(DIR_FUNC.\'/prfilter_functions.php\');'; 
				
			$UpdateFiles[3]['file'] = WBS_DIR."published/SC/html/scripts/index.php";
			$UpdateFiles[3]['data'] = file_get_contents($UpdateFiles[3]['file']); 
			$UpdateFiles[3]['data'] = str_replace($index_output,$index_input,$UpdateFiles[3]['data']);
			$UpdateFiles[3]['msg'] = "/published/SC/html/scripts/index.php";	
			
			$i = 1;	
			$error = 0;
			$returnValues = array();	
			foreach($UpdateFiles as $File){
				$percent = intval($i/count($UpdateFiles) * 100)."%";	
				
				if (is_writable($File['file'])) {
					if (!$handle = fopen($File['file'], 'w')) {
						$error = '<script language="javascript">
						document.getElementById("information").innerHTML=document.getElementById("information").innerHTML+"<div class=\"check_file_problem\">Файл не найден</div><div class=\"check_file\">'.$File['msg'].'</div>";
						</script>';
						break;
					}
					if (fwrite($handle, $File['data']) === FALSE) {
						$error = '<script language="javascript">
						document.getElementById("information").innerHTML=document.getElementById("information").innerHTML+"<div class=\"check_file_problem\">Ошибка записи</div><div class=\"check_file\">'.$File['msg'].'</div>";
						</script>';
						break;
					}    
					fclose($handle);
				} else {
					$error = '<script language="javascript">
					document.getElementById("information").innerHTML=document.getElementById("information").innerHTML+"<div class=\"check_file_problem\">Ошибка прав записи</div><div class=\"check_file\">'.$File['msg'].'</div>";
					</script>';
					break;
				} 
				
				echo '<script language="javascript">
				document.getElementById("progress").innerHTML="<div style=\"width:'.$percent.';\">'.$percent.'</div>";
				</script>';
				if($File['msg']){
					echo '<script language="javascript">
					document.getElementById("information").innerHTML=document.getElementById("information").innerHTML+"<div class=\"check_file_success\">Успешно</div><div class=\"check_file\">'.$File['msg'].'</div>";
					</script>';
				}
				echo str_repeat(' ',1024*64);
				flush();
				//sleep(1);
				usleep(500000);
				$i++;
			}
			if(!$error){
				return false;
			}else{
				echo $error;
				return true;
			}
		}
		
		
		function step0(){
			?>
				<h2 class="sub_title">Модуль дополнительные характеристики (расширенная) (Версия 1)</h2>
				<div class="block_title">
				<a href="javascript:void(0);" onclick="$('#polzovatelskoe').slideToggle();">Прочитать пользовательское соглашение</a>
				</div>
				<div class="message_info" id="polzovatelskoe" style="display:none">
				<p><b>Условия предоставления скриптов (программных продуктов).</b></p>
				<p>Настоящие Условия являются договором между вами (далее Пользователь) и «JOrange.ru» (далее, Автор). Условия относятся ко всем распространяемым версиям и модификациям программных продуктов с сайта http://www.jorange.ru.</p>
				 <ol>
					<li>Программные продукты JOrange.ru (далее, Продукты) представляют собой исходные коды программ , воспроизведенные в файлах или на бумаге, включая электронную или распечатанную документацию, а также текст данного лицензионного соглашения (далее, Соглашение).</li>
					<li>Скачивание Продуктов свидетельствует о том, что Пользователь ознакомился с содержанием Соглашения, принимает его положения и будет использовать Продукты на условиях данного Соглашения.</li>
					<li>Соглашение вступает в законную силу непосредственно в момент получения Продуктов посредством электронных средств передачи данных.</li>
					<li>Все авторские права на Продукты принадлежат Автору. Продукт в целом или по отдельности является объектом авторского права и подлежит защите согласно российскому и международному законодательству. Использование Продуктов с нарушением условий данного Соглашения, является нарушением законов об авторском праве, и будет преследоваться в соответствии с действующим законодательством.</li>
					<li>Продукты поставляются на условиях «КАК ЕСТЬ» («AS IS») без предоставления гарантий производительности, покупательной способности, сохранности данных, а также иных явно выраженных или предполагаемых гарантий. Автор не несет какой-либо ответственности за причинение или возможность причинения вреда Пользователю, его информации или бизнесу вследствие использования или невозможности использования Продуктов.</li>
					<li>Любое распространение Продукта без предварительного согласия Автора, включая некоммерческое, является нарушением данного Соглашения и влечет за собой ответственность согласно действующему законодательству. </li>
					<li>Пользователь вправе вносить любые изменения в исходный код Продуктов по своему усмотрению. При этом последующее использование Продуктов должно осуществляться в соответствии с данным Соглашением и при условии сохранения всех авторских прав. В случае внесения каких бы то ни было изменений, Автор не несет ответственности за работоспособность Продуктов.</li>
					<li>Автор не несет ответственность, в случае привлечения Пользователя к административной или уголовной ответственности за использование Продуктов в противозаконных целях.</li>
					<li>Прекращение действия данного Соглашения допускается в случае удаления всех полученных файлов и документации, а также их копий. Прекращение действия данного Соглашения не обязывает Автора возвратить средства, потраченные Пользователем на приобретение Продуктов.</li>
			  </ol>
				</div>
				<div class="message_info">	
					Всю необходимую информацию о модуле Вы можете найти на <a href="http://jorange.ru">JOrange.ru</a><br><br>
					<b>Устанавливая данный модуль Вы принимаете условия <a href="javascript:void(0);" onclick="$('#polzovatelskoe').slideToggle();">предоставления скриптов</a></b>
				</div>
				<center>
				<form method=POST>
					<div class="blue-button-small"><input type="submit" name="install_step1" value="Установить"></div>&nbsp;&nbsp;&nbsp;&nbsp;
					<div class="orange-button-small"><input type="submit" name="delete_step1" value="Удалить"></div>
				</form>
				</center>
			<?php
		}
		
		
		
		function install_step1(){
			?>
				<h2  class="sub_title">Установка модуля (Шаг 1)</h2>
				<div class="block_title">Проверка файлов:</div>
				<div class="check_files">
					<?php
						$files = array();
						$files[0]['file'] = "published/SC/html/scripts/classes/class.importoptions.php";	
						$files[0]['size'] = 7388;		
						$files[1]['file'] = "published/SC/html/scripts/core_functions/prfilter_functions.php";	
						$files[1]['size'] = 71494;
						$files[2]['file'] = "published/SC/html/scripts/css/prfilter.css";	
						$files[2]['size'] = 15318;
						$files[3]['file'] = "published/SC/html/scripts/css/prfilter_admin.css";			
						$files[3]['size'] = 21115;			
						$files[4]['file'] = "published/SC/html/scripts/images/prfilter/icons.png";	
						$files[4]['size'] = 7846;
						$files[5]['file'] = "published/SC/html/scripts/images/prfilter/icons-loading.gif";	
						$files[5]['size'] = 570;
						$files[6]['file'] = "published/SC/html/scripts/images/prfilter/inputs.png";	
						$files[6]['size'] = 3321;
						$files[7]['file'] = "published/SC/html/scripts/images/prfilter/admin/arrow-left.png";	
						$files[7]['size'] = 2856;
						$files[8]['file'] = "published/SC/html/scripts/images/prfilter/admin/arrow-right.png";	
						$files[8]['size'] = 2856;
						$files[9]['file'] = "published/SC/html/scripts/images/prfilter/admin/icons.png";	
						$files[9]['size'] = 3461;
						$files[10]['file'] = "published/SC/html/scripts/js/prfilter/jquery.base64.min.js";	
						$files[10]['size'] = 1736;
						$files[11]['file'] = "published/SC/html/scripts/js/prfilter/jquery.poshytip.js";	
						$files[11]['size'] = 19113;
						$files[12]['file'] = "published/SC/html/scripts/js/prfilter/jquery.slider.js";	
						$files[12]['size'] = 40685;
						$files[13]['file'] = "published/SC/html/scripts/js/prfilter/prfilter.js";	
						$files[13]['size'] = 26216;
						$files[14]['file'] = "published/SC/html/scripts/js/prfilter/prfilter_admin.js";	
						$files[14]['size'] = 55710;
						$files[15]['file'] = "published/SC/html/scripts/modules/prfilter/class.prfilter.php";		
						$files[15]['size'] = 21031;
						$files[16]['file'] = "published/SC/html/scripts/templates/backend/prfilter/prfilter.html";	
						$files[16]['size'] = 19483;
						$files[17]['file'] = "published/SC/html/scripts/templates/backend/prfilter/prfilter_item.html";	
						$files[17]['size'] = 4723;
						$files[18]['file'] = "published/SC/html/scripts/templates/backend/prfilter/prfilter_excel_export.html";	
						$files[18]['size'] = 2869;
						$files[19]['file'] = "published/SC/html/scripts/templates/backend/prfilter/prfilter_excel_import.html";	
						$files[19]['size'] = 5068;
						$files[20]['file'] = "published/SC/html/scripts/templates/backend/prfilter/prfilter_templates.html";	
						$files[20]['size'] = 17010;
						$files[21]['file'] = "published/SC/html/scripts/templates/backend/prfilter/prfilter_template_item.html";	
						$files[21]['size'] = 6262;	
						$files[22]['file'] = "published/SC/html/scripts/templates/frontend/prfilter/lnrside.html";	
						$files[22]['size'] = 3879;
						$files[23]['file'] = "published/SC/html/scripts/templates/frontend/prfilter/option.html";	
						$files[23]['size'] = 6519;
						$files[24]['file'] = "published/SC/html/scripts/templates/frontend/prfilter/psearch.html";	
						$files[24]['size'] = 2856;
						
						$error = false;
						foreach($files as $file){
							if(file_exists(WBS_DIR.$file['file'])){
								/* if(filesize($file['file'])!=$file['size']){
									$error = true;
									echo "<div class=\"check_file_problem\">Неверный размер файла</div>";
								}else{ */
									echo "<div class=\"check_file_success\">OK</div>";
								//}
							}else{
								$error = true;
								echo "<div class=\"check_file_problem\">Файл не найден</div>";
							}
							echo "<div class=\"check_file\">{$file['file']}</div>";
						}
					?>
				</div>
			<?php if(!$error){ ?>
				<form method=POST>
					<div class="orange-button-small"><input type="submit" name="install_step2" value="Продолжить" ></div>
				</form>
			<?php }else{ ?>
				<div class="message_error">Пожалуйста, устраните ошибки для продолжения установки.</div>
			<?php
			}
		}
		
		function install_step2(){
			?>
				<h2  class="sub_title">Установка модуля (Шаг 2)</h2>
				<div class="block_title">Корректировка файлов:</div>
				<div class="message_info">	
					Будут добавлены новые записи и удалены старые в файлах webasyst shop-script.<br>
				</div>
				<div id="progress" >
					<div style="width:0px;">&nbsp;</div>
				</div>
				<div id="information"  class="check_files"></div>	
				<form method=POST>
					<div class="orange-button-small"><input type="submit" name="install_step3" value="Продолжить" ></div>
				</form>
			<?php
		}
		
		function install_step3(){
			?>
				<h2  class="sub_title">Установка модуля (Шаг 2)</h2>
				<div class="block_title">Корректировка файлов:</div>
				<div class="message_info">	
					Идет процесс обновления файлов webasyst shop-script. Пожалуйста, дождитесь окончания процесса.<br>
				</div>
				<div id="progress" >
					<div style="width:0px;">&nbsp;</div>
				</div>
				<div id="information"  class="check_files"></div>	
				
				<?php  if( !update_files()){ ?>
					<form method=POST>
						<div class="orange-button-small"><input type="submit" name="install_step4" value="Продолжить" ></div>
					</form>
				<?php  }else{ ?>
					<div class="message_error">
						При возникновении проблем с корректировкой файлов для начала проверьте права на запись файлов. <br>
						Также Вы можете отредактировать данные файлы вручную, используя инструкцию на сайте.
					</div>
					<form method=POST>
						<div class="orange-button-small"><input type="submit" name="install_step4" value="Продолжить" ></div>
					</form>
			<?php
				}
		}
		
		
		function install_step4(){
			?>
				<h2  class="sub_title">Установка модуля (Шаг 3)</h2>
				<div class="block_title">Записи в БД:</div>
				<div class="message_info">	
					Будут внесены новые записи в таблицы. Если будут обнаружены записи модуля, которые были добавлены ранее, то они будут удалены и записаны заного.<br>
				</div>
				<div id="progress" >
					<div style="width:0px;">&nbsp;</div>
				</div>
				<div id="information"  class="check_files"></div>	
				<form method=POST>
					<div class="orange-button-small"><input type="submit" name="install_step5" value="Продолжить" ></div>
				</form>
			<?php
		}
		
		function install_step5(){
			?>
				<h2  class="sub_title">Установка модуля (Шаг 3)</h2>
				<div class="block_title">Записи в БД:</div>
				<div class="message_info">	
					Идет процесс добавления записей в таблицы. Пожалуйста, дождитесь окончания процесса.<br>
				</div>
				<div id="progress" >
					<div style="width:0px;">&nbsp;</div>
				</div>
				<div id="information"  class="check_files"></div>	
				<?php  if( sql()){ ?>
					<script>$(function() { goToByScroll("install_step6"); })</script>
					<form method=POST>
						<div class="orange-button-small" id="install_step6"><input type="submit" name="install_step6" value="Завершить установку" ></div>
						<br>
						<br>
					</form>
				<?php  }else{ ?>
					<div class="message_error">Пожалуйста, устраните ошибки для продолжения установки.</div>
			<?php
			}
		}

		function install_step6(){
			?>
				<h2  class="sub_title">Установка модуля (Завершение)</h2>
				<div class="block_title">Настройка и завершение.</div>
				<div class="message_info">	
					<b>Шаг 1.</b><br>
					Зайдите в <a href="/login">админ панель</a> в раздел "Дизайн" -> "Языки и перевод" -> Выберите ваш язык и нажмите "Редактировать перевод". 
					Опуститесь в самый низ и нажмите "сохранить".
					<br><br>
					<b>Шаг 2.</b><br>
					Удалите файл установки:<br>
					install.php.
					<br><br>
					<b>Шаг 3.</b><br>
					В редакторе дизайна, в head вставте следующую строчку<br><b>
					&lt;script type=&quot;text/javascript&quot; src=&quot;http://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js&quot;&gt;&lt;/script&gt;
					</b>
					<br><br>
				</div>

				<?php  
					clean_Cache();
				?>
					
			<?php
			
		}
		
		
		
		function delete_step1(){
			?>
				<h2  class="sub_title">Удаление модуля</h2>
				<div class="block_title">Подтверждение удаления:</div>
				<div class="message_info">	
					Удаление модуля состоит из 3 этапов.<br>
					1 Шаг - Удаление файлов модуля.<br>
					2 Шаг - Удаление корректировок в файлах webasyst shop-script.<br>
					3 Шаг - Удаление записей и таблиц в БД.<br>
				</div>
				<form method=POST>
					<div class="orange-button-small"><input type="submit" name="delete_step2" value="Продолжить" ></div>
				</form>
			<?php
			
		}
	
		function delete_step2(){
			?>
				<h2  class="sub_title">Удаление модуля (Шаг 1)</h2>
				<div class="block_title">Удаление файлов модуля:</div>
				<div class="check_files">
					<?php
						cleanDirectory('published/SC/html/scripts/images/prfilter');
						rmdir('published/SC/html/scripts/images/prfilter');
						cleanDirectory('published/SC/html/scripts/js/prfilter');
						rmdir('published/SC/html/scripts/js/prfilter');
						cleanDirectory('published/SC/html/scripts/modules/prfilter');
						rmdir('published/SC/html/scripts/modules/prfilter');
						cleanDirectory('published/SC/html/scripts/templates/backend/prfilter');
						rmdir('published/SC/html/scripts/templates/backend/prfilter');
						cleanDirectory('published/SC/html/scripts/templates/frontend/prfilter');
						rmdir('published/SC/html/scripts/templates/frontend/prfilter');
						$files = array();
						$files[] = "published/SC/html/scripts/classes/class.importoptions.php";		
						$files[] = "published/SC/html/scripts/core_functions/prfilter_functions.php";
						$files[] = "published/SC/html/scripts/css/prfilter.css";
						$files[] = "published/SC/html/scripts/css/prfilter_admin.css";
						
						$error = false;
						foreach($files as $file){
							if(is_dir(WBS_DIR.$file)){
								rmdir(WBS_DIR.$file);
							}else{
								@unlink(WBS_DIR.$file);
							}
							if(file_exists(WBS_DIR.$file)){
								echo "<div class=\"check_file_problem\">Файл не удален</div>";
								$error = true;
							}else{	
								echo "<div class=\"check_file_success\">OK</div>";
							}
							echo "<div class=\"check_file\">{$file}</div>";
						}
					?>
				</div>
			<?php if(!$error){ ?>
				<form method=POST>
					<div class="orange-button-small"><input type="submit" name="delete_step3" value="Продолжить" ></div>
				</form>
			<?php }else{ ?>
				<div class="message_error">Пожалуйста, устраните ошибки для продолжения установки.</div>
			
			<?php
			}
		}

		function delete_step3(){
			?>
				<h2  class="sub_title">Удаление модуля (Шаг 2)</h2>
				<div class="block_title">Удаление корректировок:</div>
				<div class="message_info">	
					Будут удалены внесенные записи в файлах webasyst shop-script
				</div>
				<div id="progress" >
					<div style="width:0px;">&nbsp;</div>
				</div>
				<div id="information"  class="check_files"></div>	
				<form method=POST>
					<div class="orange-button-small"><input type="submit" name="delete_step4" value="Продолжить" ></div>
				</form>
			<?php
			
		}
		function delete_step4(){
			?>
				<h2  class="sub_title">Удаление модуля (Шаг 2)</h2>
				<div class="block_title">Удаление корректировок:</div>
				<div class="message_info">	
					Будут удалены внесенные записи в файлах webasyst shop-script
				</div>
				<div id="progress" >
					<div style="width:0px;">&nbsp;</div>
				</div>
				<div id="information"  class="check_files"></div>	
				
				<?php  if( !delete_update_files()){ ?>
					<form method=POST>
						<div class="orange-button-small"><input type="submit" name="delete_step5" value="Продолжить" ></div>
					</form>
				<?php  }else{ ?>
					<div class="message_error">Пожалуйста, устраните ошибки для продолжения установки.</div>
				
			<?php
				}
		}
		

		function delete_step5(){
			?>
				<h2  class="sub_title">Удаление модуля (Шаг 3)</h2>
				<div class="block_title">Удаление записей и таблиц в БД:</div>
				<div class="message_info">	
					Будут удалены все записи относящиеся к модулю.
				</div>
				<div id="progress" >
					<div style="width:0px;">&nbsp;</div>
				</div>
				<div id="information"  class="check_files"></div>	
			
				<form method=POST>
					<div class="orange-button-small"><input type="submit" name="delete_step6" value="Продолжить" ></div>
				</form>
				
			<?php
				
		}
		function delete_step6(){
			?>
				<h2  class="sub_title">Удаление модуля (Шаг 3)</h2>
				<div class="block_title">Удаление записей и таблиц в БД:</div>
				<div class="message_info">	
					Будут удалены все записи и таблицы относящиеся к модулю.
				</div>
				<div id="progress" >
					<div style="width:0px;">&nbsp;</div>
				</div>
				<div id="information"  class="check_files"></div>	
			
				<?php  if( delete_sql()){ ?>
					<script>$(function() { goToByScroll("delete_step7"); })</script>
					<form method=POST>
						<div class="blue-button-small" id="delete_step7"><input type="submit" name="delete_step7" value="Завершить удаление" ></div>
						<br>
						<br>
					</form>
				<?php  }else{ ?>
					<div class="message_error">Пожалуйста, устраните ошибки для продолжения установки.</div>
				
			<?php
				}
		}	
		function delete_step7(){
			?>
				<h2  class="sub_title">Удаление модуля (Завершение)</h2>
				<div class="block_title">Модуль успешно удален</div>
				<div class="message_info">	
					Все файлы и записи были успешно удалены из Вашего магазина.<br><br>
					Удалите файл установки:<br>
					<b>install.php</b>
				</div>

				<?php  
					clean_Cache();
				?>
					
			<?php
			
		}
	
	
	?>
	<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN">
	<html lang="ru">
	<head>
		<title>Модуль дополнительные характеристики (расширенная) (Версия 1) - JOrange.ru</title>
		<link rel="stylesheet" type="text/css" href="http://www.jorange.ru/templates/jorange/user/install.css">
		<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
		<script>
		function goToByScroll(id){
			$('html,body').animate({scrollTop: $("#"+id).offset().top},'slow');
		}
		</script>
	</head>
	<body>
		<div class="divGreyBlackTop"></div>
		<div class="divGreyBlackTop2"></div>
		<div class="iefix"><div class="mainWidth">
		<?php
			if(isset($_POST['install_step1'])){
				install_step1();
			}else if(isset($_POST['install_step2'])){
				install_step2();
			}else if(isset($_POST['install_step3'])){
				install_step3();
			}else if(isset($_POST['install_step4'])){
				install_step4();
			}else if(isset($_POST['install_step5'])){
				install_step5();
			}else if(isset($_POST['install_step6'])){
				install_step6();
			}else if(isset($_POST['delete_step1'])){
				delete_step1();
			}else if(isset($_POST['delete_step2'])){
				delete_step2();
			}else if(isset($_POST['delete_step3'])){
				delete_step3();
			}else if(isset($_POST['delete_step4'])){
				delete_step4();
			}else if(isset($_POST['delete_step5'])){
				delete_step5();
			}else if(isset($_POST['delete_step6'])){
				delete_step6();
			}else if(isset($_POST['delete_step7'])){
				delete_step7();
			}else{
				step0();
			}
	
		?>
		</div></div>
	</body>
	</html>
	<?php
}
?>