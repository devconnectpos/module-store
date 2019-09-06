<?php
/**
 * Created by mr.vjcspy@gmail.com - khoild@smartosc.com.
 * Date: 28/10/2016
 * Time: 10:24
 */

namespace SM\Store\Repositories;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Locale\Format;
use Magento\Store\Model\ResourceModel\Store\CollectionFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreFactory;
use Magento\Store\Model\StoreManagerInterface;
use SM\XRetail\Helper\DataConfig;
use SM\XRetail\Repositories\Contract\ServiceAbstract;
use SM\Core\Api\Data\Store as XStore;

/**
 * Class StoreManagement
 *
 * @package SM\Store\Repositories
 */
class StoreManagement extends ServiceAbstract
{

    /**
     * @var \Magento\Store\Model\ResourceModel\Store\CollectionFactory
     */
    protected $storeCollectionFactory;
    /**
     * @var \Magento\Store\Model\StoreFactory
     */
    protected $storeFactory;
    /**
     * @var \Magento\Framework\Locale\Format
     */
    protected $localFormat;

    /**
     * @var \Magento\Config\Model\Config\Loader
     */
    private $configLoader;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;
    /**

    /**
     * StoreManagement constructor.
     *
     * @param \Magento\Framework\App\RequestInterface                    $requestInterface
     * @param \SM\XRetail\Helper\DataConfig                              $dataConfig
     * @param \Magento\Store\Model\StoreManagerInterface                 $storeManager
     * @param \Magento\Store\Model\ResourceModel\Store\CollectionFactory $storeCollectionFactory
     */
    public function __construct(
        RequestInterface $requestInterface,
        DataConfig $dataConfig,
        StoreManagerInterface $storeManager,
        CollectionFactory $storeCollectionFactory,
        StoreFactory $storeFactory,
        Format $format,
        \Magento\Config\Model\Config\Loader $loader,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig            = $scopeConfig;
        $this->localFormat            = $format;
        $this->storeCollectionFactory = $storeCollectionFactory;
        $this->storeFactory           = $storeFactory;
        $this->configLoader  = $loader;
        $this->storeManager = $storeManager;
        parent::__construct($requestInterface, $dataConfig, $storeManager);
    }

    /**
     * @return array
     */
    public function getStoreData()
    {
        return $this->loadStore($this->getSearchCriteria())->getOutput();
    }

    /**
     * @param \Magento\Framework\DataObject $searchCriteria
     *
     * @return \SM\Core\Api\SearchResult
     */
    public function loadStore(DataObject $searchCriteria)
    {
        if (is_null($searchCriteria) || !$searchCriteria)
            $searchCriteria = $this->getSearchCriteria();

        $this->getSearchResult()->setSearchCriteria($searchCriteria);
        $collection = $this->getStoreCollection($searchCriteria);

        $items = [];
        if ($collection->getLastPageNumber() < $searchCriteria->getData('currentPage')) {
        } else {
            foreach ($collection as $store) {
                $xStore = new XStore();

                $xStore->addData($store->getData());

                $baseCurrency = $store->getBaseCurrency();
                $xStore->setData('base_currency', $baseCurrency->getData());

                $currentCurrency = $this->getCurrentCurrencyBaseOnStore($store);
                $xStore->setData('current_currency', ["currency_code" => $currentCurrency]);

                $rate = $baseCurrency->getRate($currentCurrency);
                $xStore->setData('rate', $rate);
                $xStore->setData('price_format', $this->localFormat->getPriceFormat(null, $currentCurrency));

                if($searchCriteria->getData('isPWA')=='1') {
                    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                    $fileName = $objectManager->get('Magento\Store\Model\StoreManagerInterface')
                            ->getStore()
                            ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'pwa_logo/';

                    $xStore->setData('logo', $fileName.$this->scopeConfig->getValue('pwa/logo/pwa_logo', 'stores', $searchCriteria->getData('storeId')));
                    $xStore->setData('brand_name', $this->scopeConfig->getValue('pwa/brand_name/pwa_brand_active', 'stores', $searchCriteria->getData('storeId')));
                    $xStore->setData('theme_color', $this->scopeConfig->getValue('pwa/color_picker/pwa_theme_color', 'stores', $searchCriteria->getData('storeId')));
                    // add integrate data to store data
                    $xStore->setData('is_integrate_gc', $this->scopeConfig->getValue("pwa/integrate/pwa_integrate_gift_card",'stores', $searchCriteria->getData('storeId')));
                    $xStore->setData('is_integrate_rp', $this->scopeConfig->getValue("pwa/integrate/pwa_integrate_reward_points",'stores', $searchCriteria->getData('storeId')));
                    $xStore->setData('out_of_stock', $this->scopeConfig->getValue("pwa/product_category/pwa_show_out_of_stock_products", 'stores', $searchCriteria->getData('storeId')));
                    $xStore->setData('visibility', $this->scopeConfig->getValue("pwa/product_category/pwa_show_product_visibility", 'stores', $searchCriteria->getData('storeId')));
                }
                $items[] = $xStore;
            }
        }

        return $this->getSearchResult()
                    ->setItems($items)
                    ->setLastPageNumber($collection->getLastPageNumber())
                    ->setTotalCount($collection->getSize());
    }

    /**
     * @param \Magento\Framework\DataObject $searchCriteria
     *
     * @return \Magento\Store\Model\ResourceModel\Store\Collection
     */
    protected function getStoreCollection(DataObject $searchCriteria)
    {
        /** @var \Magento\Store\Model\ResourceModel\Store\Collection $collection */
        $collection = $this->storeCollectionFactory->create();
        // for PWA only
        if ($searchCriteria->getData('storeId')) {
            $collection->addFieldToFilter('store_id', $searchCriteria->getData('storeId'));
        }
        $collection->setLoadDefault(true);
        if (is_nan($searchCriteria->getData('currentPage'))) {
            $collection->setCurPage(1);
        } else {
            $collection->setCurPage($searchCriteria->getData('currentPage'));
        }
        if (is_nan($searchCriteria->getData('pageSize'))) {
            $collection->setPageSize(
                DataConfig::PAGE_SIZE_LOAD_DATA
            );
        } else {
            $collection->setPageSize(
                $searchCriteria->getData('pageSize')
            );
        }

        return $collection;
    }

    /**
     * @return \Magento\Store\Model\ResourceModel\Store
     */
    protected function getStoreModel()
    {
        return $this->storeFactory->create();
    }


    /**
     * @param \Magento\Store\Model\Store $store
     *
     * @return mixed|string
     */
    protected function getCurrentCurrencyBaseOnStore(Store $store)
    {
        // try to get currently set code among allowed
        $code = $store->getDefaultCurrencyCode();
        if (in_array($code, $store->getAvailableCurrencyCodes(true))) {
            return $code;
        }

        // take first one of allowed codes
        $codes = array_values($store->getAvailableCurrencyCodes(true));
        if (empty($codes)) {
            // return default code, if no codes specified at all
            return $store->getDefaultCurrencyCode();
        }

        return array_shift($codes);
    }
}
