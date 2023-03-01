<?php
/*
* 2010-2020 Sellermania / Froggy Commerce / 23Prod SARL
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to team@froggy-commerce.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade your module to newer
* versions in the future.
*
*  @author         Froggy Commerce <team@froggy-commerce.com>
*  @copyright      2010-2020 Sellermania / Froggy Commerce / 23Prod SARL
*  @version        1.0
*  @license        http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/

/*
 * Security
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

if (!class_exists('FroggyHelperTreeCategories'))
    require_once(dirname(__FILE__).'/../../classes/FroggyHelperTreeCategories.php');
require_once(dirname(__FILE__).'/../front/SellermaniaExport.php');
require_once(dirname(__FILE__).'/SellermaniaDisplayAdminOrder.php');

require_once(_PS_MODULE_DIR_.'sellermania'.DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.'SellermaniaHelper.php');
require_once(_PS_MODULE_DIR_.'sellermania'.DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.'SellermaniaValidator.php');
require_once(_PS_MODULE_DIR_.'sellermania'.DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.'SellermaniaTranslator.php');
require_once(_PS_MODULE_DIR_.'sellermania'.DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.'SellermaniaMarketplacesSynchronizer.php');

class SellermaniaGetContentController
{
    public $params;
    public $params_default_value;

    /**
     * Controller constructor
     */
    public function __construct($module, $dir_path, $web_path)
    {
        $this->module = $module;
        $this->web_path = $web_path;
        $this->dir_path = $dir_path;
        $this->context = Context::getContext();
    }

    public function initParams()
    {
        $this->params = array('sm_export_all', 'sm_export_invisible',
            'sm_images_checksum', 'sm_stock_sync_log',
            'sm_import_orders', 'sm_order_email', 'sm_order_token', 'sm_order_endpoint',
            'sm_confirm_order_endpoint', 'sm_inventory_endpoint',
            'sm_stock_sync_option', 'sm_stock_sync_option_1', 'sm_stock_sync_option_2',
            'sm_stock_sync_nb_char', 'sm_stock_sync_position',
            'sm_import_method', 'sm_import_default_customer_group',
            'sm_import_default_carrier', 'sm_import_default_shipping_service',
            'sm_import_default_country_code', 'sm_shipment_default_country_code',
            'sm_alert_missing_ref_option', 'sm_alert_missing_ref_mail',
            'sm_product_match',
            'sm_enable_native_refund_system', 'sm_enable_native_order_interface',
            'sm_enable_export_comb_name', 'sm_export_extra_fields',
            'sm_export_stay_nb_days',
            'sm_catch_all_mail_address', 'sm_install_date',
            'sm_order_import_past_days', 'sm_order_import_limit',
            'sm_import_orders_with_client_email', 'sm_import_orders_shop',
            'PS_OS_SM_ERR_CONF', 'PS_OS_SM_ERR_CANCEL_CUS', 'PS_OS_SM_ERR_CANCEL_SEL',
            'PS_OS_SM_AWAITING', 'PS_OS_SM_CONFIRMED', 'PS_OS_SM_TO_DISPATCH',
            'PS_OS_SM_DISPATCHED', 'PS_OS_SM_CANCEL_CUS', 'PS_OS_SM_CANCEL_SEL',
            'sm_out_of_stock_nb_days',
            'sm_product_to_include_in_feed', 'sm_last_days_to_include_in_feed',
            'sm_import_ac_orders_after_adding_tracking_number',
            'sm_import_orders_without_address','sm_optional_sellermania_look',
        );

        if (is_array($this->module->sellermania_marketplaces)) {
            foreach ($this->module->sellermania_marketplaces as $marketplace) {
                $marketplace_code = $marketplace["code"];
                $marketplace_name = str_replace('.', '_', $marketplace_code);
                $this->params[] = 'SM_MKP_'.$marketplace_name;
                $this->params[] = 'SM_MKP_'.$marketplace_name.'_DELIVERY';
                $this->params[] = 'SM_MKP_'.$marketplace_name.'_SERVICE';
                $this->params[] = 'SM_MKP_'.$marketplace_name.'_PS_MAP';
            }
        }

        $default_country = new Country(Configuration::get('PS_COUNTRY_DEFAULT'));
        $this->params_default_value = [
            'sm_import_default_country_code' => $default_country->iso_code,
            'sm_shipment_default_country_code' => $default_country->iso_code,
            'sm_product_match' => 'automatic',
            'sm_import_orders_shop' => 0,
        ];
    }

    /**
     * Test configuration
     */
    public function testConfiguration()
    {
        try
        {
            $test = new SellermaniaTestAPI();
            $test->run();
            Configuration::updateValue('SM_CREDENTIALS_CHECK', 'ok');
            $this->context->smarty->assign('sm_confirm_credentials', 'ok');
        } catch (\Exception $e) {
            Configuration::updateValue('SM_CREDENTIALS_CHECK', 'ko');
            $translator = new SellermaniaTranslator();
            SellermaniaFieldError::createFieldError(null, $translator->l("A problem was detected with your API connection. Please check your credentials"), "import-orders");
            //$this->context->smarty->assign('sm_error_credentials', $e->getMessage());
        }
    }


    /**
     * Save configuration
     * @throws Exception
     */
    public function saveConfiguration()
    {
        if (Tools::isSubmit('import_orders')) {
            $email = Configuration::get("SM_ORDER_EMAIL");
            $token = Configuration::get("SM_ORDER_TOKEN");
            $endpoint = Configuration::get("SM_ORDER_ENDPOINT");

            $new_email = Tools::getValue('sm_order_email');
            $new_token = Tools::getValue('sm_order_token');
            $new_endpoint = Tools::getValue('sm_order_endpoint');

            if ($email !== $new_email || $token !== $new_token || $endpoint !== $new_endpoint) {
                $result = SellermaniaMarketplacesSynchronizer::sync(true, $new_email, $new_token, $new_endpoint);
                $this->module->sellermania_marketplaces = $result["marketplaces"];
            }
        }

        if (Tools::isSubmit('import_orders') || Tools::isSubmit('wizard_button') || Tools::isSubmit('export_configuration')) {
            $is_good = SellermaniaValidator::validateConfiguration($_POST);
            $this->context->smarty->assign("sm_config_is_good", $is_good);
            $this->saveConfigurationData($_POST);
        }

        if (Tools::isSubmit('relaunch_wizard')) {
            Configuration::updateValue('SM_WIZARD_LAUNCHED', 0);
            return;
        }

        if (Tools::isSubmit('wizard_button')) {
            // carriers mapping initialization
            $carriers = [];
            $search_carriers = 'SM_MKP_DELIVERY_';

            // order status mapping initialization
            $status = [];
            $loaded_status = $this->module->sellermania_order_states;
            foreach ($loaded_status as $ls) {
                $status[$ls["sm_status"]] = [];
            }
            $search_status = 'SM_PS_ORDER_MAP_';

            foreach ($_POST as $k => $p) {
                if (false !== strpos($k, $search_status)) {
                    if (isset($status[$p])) {
                        $status[$p][] = str_replace($search_status, '', $k);
                    }
                } elseif (false !== strpos($k, $search_carriers)) {
                    $carriers_mapping = str_replace($search_carriers, '', $k);
                    $carriers_mapping = explode('_', $carriers_mapping);
                    $count = count($carriers_mapping);
                    if (3 === $count) {
                        $marketplace_code = $carriers_mapping[0].'_'.$carriers_mapping[1];
                        $ps_carrier_id = $carriers_mapping[2];
                    } elseif (2 === $count) {
                        $marketplace_code = $carriers_mapping[0];
                        $ps_carrier_id = $carriers_mapping[1];
                    } else {
                        throw new Exception("Error fields detected");
                    }
                    if ("" != $p) {
                        if (!isset($carriers[$marketplace_code])) {
                            $carriers[$marketplace_code] = [];
                        }
                        $carriers[$marketplace_code][] = [$ps_carrier_id => $p];
                    }
                }
            }

            // saving configuration
            foreach ($this->module->sellermania_order_states as $k => $ls) {
                Configuration::updateValue($k, json_encode($status[$ls["sm_status"]]));
            }
            foreach ($carriers as $k => $carrier) {
                Configuration::updateValue("SM_MKP_DELIVERY_".$k, json_encode($carrier));
            }
            $custom_error_order_state = SellermaniaHelper::createOrderStatus("Marketplace - Technical error", '#e74c3c', $this->module->name, (int)$this->context->language->id, ['logable' => true, 'invoice' => false, 'shipped' => true, 'paid' => false]);
            Configuration::updateValue("PS_OS_SM_ERR_CONF", json_encode([$custom_error_order_state]));
            Configuration::updateValue("PS_OS_SM_ERR_CANCEL_CUS", json_encode([$custom_error_order_state]));
            Configuration::updateValue("PS_OS_SM_ERR_CANCEL_SEL", json_encode([$custom_error_order_state]));

            Configuration::updateValue('SM_WIZARD_LAUNCHED', 1);
        }

        if (Tools::isSubmit('export_configuration')) {
            Configuration::updateValue('SM_EXPORT_CATEGORIES', '');
            if (isset($_POST['categories_to_export']) && count($_POST['categories_to_export']) > 0) {
                $categories = $_POST['categories_to_export'];
                foreach ($categories as $kc => $vc) {
                    $categories[(int)$kc] = (int)$vc;
                }
                Configuration::updateValue('SM_EXPORT_CATEGORIES', json_encode($categories));
            }
            $this->context->smarty->assign('sm_confirm_export_options', 1);
        }

        foreach ($this->params as $p) {
            if (isset($_POST[$p])) {
                Configuration::updateValue(strtoupper($p), trim($_POST[$p]));
            }
        }

        foreach ($this->params_default_value as $param => $default_value) {
            if (Configuration::get(strtoupper($param)) == '') {
                Configuration::updateValue(strtoupper($param), trim($default_value));
            }
        }

        if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
            if (Configuration::get('SM_IMPORT_ORDERS') == 'yes') {
                $this->testConfiguration();
            }
        }

        if (Tools::isSubmit('search_orders') && Tools::getValue('marketplace_order_reference') != '') {
            $orders = SellermaniaOrder::searchSellermaniaOrdersByReference(Tools::getValue('marketplace_order_reference'));
            $this->context->smarty->assign('sm_orders_found', $orders);
        }
    }

    /**
     * Assign data to Smarty
     */
    public function assignData()
    {
        // Init vars
        $languages_list = Language::getLanguages();

        // If no context we set it
        if (empty($this->context->shop->id)) {
            $this->context->shop->setContext(1);
        }

        // Multiple security check
        $module_web_path = Tools::getHttpHost(true).$this->context->shop->physical_uri.'modules/'.$this->module->name.'/';
        $export_directory_writable = 0;
        if (is_writable($this->dir_path.'/export/')) {
            $export_directory_writable = 1;
        }
        $sellermania_key = Configuration::get('SELLERMANIA_KEY');
        if (empty($sellermania_key) && version_compare(_PS_VERSION_, '1.5') >= 0) {
            $sellermania_key_tmp = Configuration::get('SELLERMANIA_KEY', null, 1, 1);
            if (!empty($sellermania_key_tmp))
                Configuration::updateValue('SELLERMANIA_KEY', $sellermania_key_tmp);
            $sellermania_key = Configuration::get('SELLERMANIA_KEY');
        }

        $smec = new SellermaniaExportController($this->module);
        $module_url = 'index.php?controller='.Tools::getValue('controller').'&tab='.Tools::getValue('tab').'&token='.Tools::getValue('token');
        $module_url .= '&configure='.Tools::getValue('configure').'&tab_module='.Tools::getValue('tab_module').'&module_name='.Tools::getValue('module_name').'';

        // Retrieve orders in error
        if (Tools::getValue('reimport') > 0)
            SellermaniaOrder::deleteSellermaniaOrderInError((int)Tools::getValue('reimport'));
        if (Tools::getValue('reimport') == 'all')
            SellermaniaOrder::deleteAllSellermaniaOrdersInError();
        $orders_in_error = SellermaniaOrder::getSellermaniaOrdersInError();
        $nb_orders_in_error = count($orders_in_error);

        // Check if file exists and retrieve the creation date
        $files_list = array();
        foreach ($languages_list as $language) {
            $iso_lang = strtolower($language['iso_code']);
            $web_path_file = $module_web_path.$smec->get_export_filename($iso_lang, true);
            $real_path_file = $smec->get_export_filename($iso_lang);
            $files_list[$iso_lang]['file'] = $web_path_file;
            if (file_exists($real_path_file))
                $files_list[$iso_lang]['generated'] = date("d/m/Y H:i:s", filectime($real_path_file));
        }

        // Retrieve carriers (last parameter "5" means "All carriers")
        $carriers = Carrier::getCarriers($this->context->language->id, true, false, false, null, 5);

        // Retrieve Sellermania Order States
        /*foreach ($this->module->sellermania_order_states as $conf_key => $value) {
            $this->module->sellermania_order_states[$conf_key]['ps_conf_value'] = Configuration::get($conf_key);
        }*/

        // Retrieve all marketplaces and values
        $sm_marketplaces = array();
        if(is_array($this->module->sellermania_marketplaces)) {
            foreach ($this->module->sellermania_marketplaces as $marketplace) {
                $marketplace_original_name = $marketplace['code'];
                $marketplace = str_replace('.', '_', $marketplace);
                $sm_marketplaces[$marketplace['code']] = array('key' => 'SM_MKP_'.$marketplace['code'], 'value' => Configuration::get('SM_MKP_'.$marketplace['code']));
                if (empty($sm_marketplaces[$marketplace['code']]['value'])) {
                    $sm_marketplaces[$marketplace['code']]['value'] = 'MANUAL';
                }
                if (isset($this->module->sellermania_marketplaces_delivery[$marketplace_original_name])) {
                    $sm_marketplaces[$marketplace['code']]['delivery'] = $this->module->sellermania_marketplaces_delivery[$marketplace_original_name];
                }

                $sm_mkp_delivery = json_decode(Configuration::get('SM_MKP_DELIVERY_'.$marketplace['code']));
                $delivery_value = [];
                if(!empty($sm_mkp_delivery)) {
                    foreach ($sm_mkp_delivery as $v) {
                        $v = (array) $v;
                        foreach ($v as $pscid => $mpc) {
                            $delivery_value[$pscid] = $mpc;
                        }
                    }
                }
                $sm_marketplaces[$marketplace['code']]['delivery_value'] = $delivery_value;
                //$sm_marketplaces[$marketplace['code']]['service_value'] = Configuration::get('SM_MKP_'.$marketplace['code'].'_SERVICE');
                $shipping_service_values = [];
                foreach ($carriers as $ps_carrier) {
                    $shipping_service_values[$ps_carrier['id_carrier']] = SellermaniaHelper::getShippingServiceForMarketplace($marketplace['code'], $ps_carrier['id_carrier']);
                }
                $sm_marketplaces[$marketplace['code']]['shipping_service_value'] = $shipping_service_values;
                $sm_marketplaces[$marketplace['code']]['service_value'] = Configuration::get('SM_MKP_'.$marketplace['code'].'_SERVICE');
            }
        }

        // Retrieve customer groups
        $customer_groups = Group::getGroups($this->context->language->id);

        // Retrieve shops
        $shops = [];
        if (version_compare(_PS_VERSION_, '1.5') >= 0) {
            $shops = Shop::getShops();
        }

        // Calcul number of tracking number to synchronize
        $sm_tracking_numbers_to_synchronize = [];
        if (version_compare(_PS_VERSION_, '1.5') >= 0) {
            $sm_tracking_numbers_to_synchronize = SellermaniaOrder::getTrackingNumbersToSynchronize();

            $orders_to_synchronize = [];
            foreach ($sm_tracking_numbers_to_synchronize as $stnts) {
                $info = json_decode($stnts['info'], true);
                if (!empty($info) && (!isset($info['OrderInfo']['Transport']['TrackingNumber']) || empty($info['OrderInfo']['Transport']['TrackingNumber'])))
                {
                    $orders_to_synchronize[] = $info;
                }
            }
            if (Tools::getIsset('synchronizeTrackingNumbers')) {
                $sdao = new SellermaniaDisplayAdminOrderController($this->module, $this->dir_path, $this->web_path);
                $orders_to_ship = $sdao->handleShippedOrders($orders_to_synchronize);

                if (!empty($orders_to_ship) && ((Tools::getIsset('ac_confirm')) || Configuration::get('SM_IMPORT_AC_ORDERS_AFTER_ADDING_TRACKING_NUMBER') == 'on')) {
                    $sdao->registerShippingData($orders_to_ship);
                }
            }
        }

        // Assign to Smarty
        if (version_compare(PHP_VERSION, '5.3.0') < 0)
        {
            $this->context->smarty->assign('php_version', PHP_VERSION);
            $this->context->smarty->assign('no_namespace_compatibility', '1');
        }

        $documentation_iso_code = 'en';
        if (isset($this->context->language->iso_code) && in_array($this->context->language->iso_code, array('fr', 'en', 'es')))
            $documentation_iso_code = $this->context->language->iso_code;
        $this->context->smarty->assign('documentation_iso_code', $documentation_iso_code);

        $this->context->smarty->assign('templates_dir', dirname(__FILE__).'/../../views/templates/hook/');

        $this->context->smarty->assign('orders_in_error', $orders_in_error);
        $this->context->smarty->assign('nb_orders_in_error', $nb_orders_in_error);

        $this->context->smarty->assign('module_url', $module_url);
        $this->context->smarty->assign('script_path', $this->dir_path);
        $this->context->smarty->assign('export_directory_writable', $export_directory_writable);
        $this->context->smarty->assign('module_web_path', $module_web_path);
        $this->context->smarty->assign('sellermania_key', $sellermania_key);
        $this->context->smarty->assign('files_list', $files_list);
        $this->context->smarty->assign('languages_list', $languages_list);
        $this->context->smarty->assign('sellermania_module_path', $this->web_path);

        $this->context->smarty->assign('customer_groups', $customer_groups);

        $this->context->smarty->assign('carriers', $carriers);
        $this->context->smarty->assign('shops', $shops);

        $this->context->smarty->assign('category_tree', $this->renderCategoriesTree());
        $this->context->smarty->assign('sm_default_product', new Product($this->module->getDefaultProductID()));
        $this->context->smarty->assign('sm_default_product_id', $this->module->getDefaultProductID());

        foreach ($this->params as $param) {
            $this->context->smarty->assign(strtolower($param), Configuration::get(strtoupper($param)));
        }

        $states = OrderState::getOrderStates($this->context->language->id);
        $this->context->smarty->assign('sm_order_states', $this->module->sellermania_order_states);
        $this->context->smarty->assign('ps_order_states', OrderState::getOrderStates($this->context->language->id));
        $this->context->smarty->assign('sm_marketplaces', $sm_marketplaces);

        $this->context->smarty->assign('sm_tracking_numbers_to_synchronize', $orders_to_synchronize);

        $this->context->smarty->assign('order_token_tab', Tools::getAdminTokenLite('AdminOrders'));

        if ($this->context->language->iso_code == 'fr')
        {
            $this->context->smarty->assign('sm_last_import', date('d/m/Y H:i:s', strtotime(Configuration::get('SM_NEXT_IMPORT').' -15 minutes')));
            $this->context->smarty->assign('sm_next_import', date('d/m/Y H:i:s', strtotime(Configuration::get('SM_NEXT_IMPORT'))));
        }
        else
        {
            $this->context->smarty->assign('sm_last_import', date('Y-m-d H:i:s', strtotime(Configuration::get('SM_NEXT_IMPORT').' -15 minutes')));
            $this->context->smarty->assign('sm_next_import', Configuration::get('SM_NEXT_IMPORT'));
        }

        $this->context->smarty->assign('sm_module_version', $this->module->version);

        $this->context->smarty->assign('sm_out_of_stock_nb_days', null);

        $this->context->smarty->assign('sm_product_to_include_in_feed', Configuration::get('SM_PRODUCT_TO_INCLUDE_IN_FEED'));
        $this->context->smarty->assign('sm_last_days_to_include_in_feed', Configuration::get('SM_LAST_DAYS_TO_INCLUDE_IN_FEED'));

        $this->context->smarty->assign('sm_status_mapping', $this->module->loader->mapOrderStates());
        $this->context->smarty->assign('sm_wizard_launched', Configuration::get('SM_WIZARD_LAUNCHED'));

        $this->context->smarty->assign("sm_mp_list", SellermaniaMarketplace::getAvailableSellermaniaMarketplaces());
        $this->context->smarty->assign("sm_mp_icon_link", $this->module->sm_mp_icon_link);

        $this->context->smarty->assign("field_errors_import_orders", SellermaniaFieldError::getAllActiveFieldErrors("import-orders"));
        $this->context->smarty->assign("field_errors_export_catalog", SellermaniaFieldError::getAllActiveFieldErrors("export-catalog"));

        $this->context->smarty->assign("module_web_path", Tools::getHttpHost(true).$this->context->shop->physical_uri.'modules/'.$this->module->name.'/');
        $this->context->smarty->assign("lang_iso", $this->context->language->iso_code);

        $this->context->smarty->assign('sm_secret_key', Configuration::get('SM_SECRET_KEY'));

        $this->context->smarty->assign("sm_credentials_check", Configuration::get("SM_CREDENTIALS_CHECK"));
    }

    /**
     * Render categories tree method
     */
    public function renderCategoriesTree()
    {
        $root = Category::getRootCategory();

        $categories = array();
        $categories_selected = Configuration::get('SM_EXPORT_CATEGORIES');
        if (!empty($categories_selected))
            foreach (json_decode($categories_selected, true) as $key => $category)
                $categories[] = $category;

        $tree = new FroggyHelperTreeCategories();
        $tree->setAttributeName('categories_to_export');
        $tree->setRootCategory($root->id);
        $tree->setLang($this->context->employee->id_lang);
        $tree->setSelectedCategories($categories);
        $tree->setContext($this->context);
        $tree->setModule($this->module);
        return $tree->render();
    }

    /**
     * Run method
     * @return string $html
     */
    public function run()
    {
        $this->initParams();
        if (Tools::getValue('see') != 'orders-error') {
            $this->saveConfiguration();
        }
        $this->assignData();
        return $this->module->compliantDisplay('displayGetContent'.(isset($this->module->bootstrap) ? '.bootstrap' : '').'.tpl');
    }

    private function saveConfigurationData ($data)
    {
        // carriers mapping initialization
        $carriers = [];
        $search_carriers = 'SM_MKP_DELIVERY_';

        // order status mapping initialization
        $status = [];
        $loaded_status = $this->module->sellermania_order_states;
        foreach ($loaded_status as $ls) {
            $status[$ls["sm_status"]] = [];
        }
        $search_status = 'SM_PS_ORDER_MAP_';

        // shipping service
        $shipping_services = [];
        $search_shipping_services = 'SM_MKP_SHIPPING_SERVICE_';

        foreach ($data as $k => $p) {
            if (false !== strpos($k, $search_status)) {
                if (isset($status[$p])) {
                    $status[$p][] = str_replace($search_status, '', $k);
                }
            } elseif (false !== strpos($k, $search_carriers)) {
                $carriers_mapping = str_replace($search_carriers, '', $k);
                $carriers_mapping = explode('_', $carriers_mapping);
                $count = count($carriers_mapping);
                if (3 === $count) {
                    $marketplace_code = $carriers_mapping[0].'_'.$carriers_mapping[1];
                    $ps_carrier_id = $carriers_mapping[2];
                } elseif (2 === $count) {
                    $marketplace_code = $carriers_mapping[0];
                    $ps_carrier_id = $carriers_mapping[1];
                } else {
                    throw new Exception("Error fields detected");
                }
                //if ("" != $p) {
                if (!isset($carriers[$marketplace_code])) {
                    $carriers[$marketplace_code] = [];
                }
                $carriers[$marketplace_code][] = [$ps_carrier_id => $p];
                //}
            } elseif (false !== strpos($k, $search_shipping_services)) {
                if ($p) {
                    $shipping_service = str_replace($search_shipping_services, '', $k);
                    $carriers_mapping = explode('_', $shipping_service);
                    $count = count($carriers_mapping);
                    if (3 === $count) {
                        $marketplace_code = $carriers_mapping[0].'_'.$carriers_mapping[1];
                        $ps_carrier_id = $carriers_mapping[2];
                        $shipping_services[$marketplace_code][] = [$ps_carrier_id => $p];
                    } else {
                        throw new Exception("Error fields detected");
                    }
                }

            }
        }

        // saving configuration
        foreach ($this->module->sellermania_order_states as $k => $ls) {
            Configuration::updateValue($k, json_encode($status[$ls["sm_status"]]));
        }
        foreach ($carriers as $k => $carrier) {
            Configuration::updateValue("SM_MKP_DELIVERY_".$k, json_encode($carrier));
        }
        foreach ($shipping_services as $k => $shipping_service) {
            Configuration::updateValue("SM_MKP_SHIPPING_SERVICE_".$k, json_encode($shipping_service));
        }
    }
}



