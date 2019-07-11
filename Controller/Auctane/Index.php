<?php
namespace Auctane\Api\Controller\Auctane;

use Exception;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class Index extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{

    /**
     * Temporary fix to circumvent the token validation on POST request made to Shipstation
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Mandatory method to implement the CsrfAwareActionInterface
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        $response = $this->redirectFactory->create()
            ->setRefererOrBaseUrl()
            ->setHttpResponseCode(302);
        $messages = [
            new Phrase('Invalid Form Key. Please refresh the page.'),
        ];
        $exception = new InvalidRequestException($response, $messages);

        return $exception;
    }

    /**
     * Default function
     *
     * @return void
     */
    public function execute()
    {
        $authUser = $this->getRequest()->getParam('SS-UserName');
        $authPassword = $this->getRequest()->getParam('SS-Password');

        // \Magento\Store\Model\StoreManagerInterface $storeManager
        $storeManager = $this->_objectManager->get(
            'Magento\Store\Model\StoreManagerInterface'
        );
        $storeId = $storeManager->getStore()->getId();
        
        $storageInterface = $this->_objectManager->get(
            '\Magento\Backend\Model\Auth\Credential\StorageInterface'
        );

        $scopeConfig = $this->_objectManager->get(
            '\Magento\Framework\App\Config\ScopeConfigInterface'
        );

        // $apiKey = $scopeConfig->getValue(
        //     'shipstation_general/shipstation/ship_api_key'
        // );

        //$headers = getallheaders();
        // $shipApiKey = '';
        
        // if(isset($headers['ShipStation-Access-Token'])) {
        //     $shipApiKey = $headers['ShipStation-Access-Token']; 
        // }

        // if(($shipApiKey == $apiKey)  && $shipApiKey && $apiKey) {
        //     $userAuthentication = 1;
        // } else {
        //     $userAuthentication = $storageInterface->authenticate(
        //         $authUser,
        //         $authPassword
        //     );    
        // }

        $userAuthentication = $storageInterface->authenticate(
            $authUser,
            $authPassword
        );

        $dataHelper = $this->_objectManager->get('Auctane\Api\Helper\Data');
        if (!$userAuthentication) {
            header(sprintf('WWW-Authenticate: Basic realm=ShipStation'));
            $result = $dataHelper->fault(401, 'Authentication failed');
            header('Content-Type: text/xml; charset=UTF-8');
            $this->getResponse()->setBody($result);
            return false;
        }

        //Get the requested action
        $action = $this->getRequest()->getParam('action');
        try {
            switch ($action) {
                case 'export':
                    $export = $this->_objectManager->get(
                        'Auctane\Api\Model\Action\Export'
                    );
                    $result = $export->process($this->getRequest(), $storeId);
                    break; 

                case 'shipnotify':
                    $shipNotify = $this->_objectManager->get(
                        'Auctane\Api\Model\Action\ShipNotify'
                    );
                    $result = $shipNotify->process($this->getRequest());
                    // if there hasn't been an error then "200 OK" is given
                    break;
            }

        } catch (Exception $fault) {
            $result = $dataHelper->fault($fault->getCode(), $fault->getMessage());
        }
        
        $this->getResponse()->setBody($result);
    }
}