<?php
/**
 * JSON API v2 Controller - handles /api/v2/* dispatch
 *
 * Uses __call() to route requests to the appropriate API Model based on the URL path.
 *
 * @category   Community
 * @package    XFE_OAuth2
 */
class XFE_OAuth2_ApiController extends Mage_Core_Controller_Front_Action
{
    /**
     * Override preDispatch to not use standard Magento auth
     */
    public function preDispatch()
    {
        parent::preDispatch();
    }

    /**
     * Magic call handler - routes /api/v2/{resource}/{id} to Model/Api/{Resource}
     *
     * @param string $method
     * @param array $args
     * @return void
     */
    public function __call($method, $args)
    {
        // Parse the URL path after /api/v2/
        $pathInfo = $this->getRequest()->getPathInfo();
        $pathInfo = ltrim($pathInfo, '/');

        // Remove the /api/v2 prefix
        if (strpos($pathInfo, 'api/v2/') === 0) {
            $pathInfo = substr($pathInfo, 7);
        } elseif (strpos($pathInfo, 'api/') === 0) {
            $pathInfo = substr($pathInfo, 4);
        } else {
            $this->_sendError(404, 'not_found', 'Endpoint not found');
            return;
        }

        $segments = explode('/', trim($pathInfo, '/'));
        $resource = isset($segments[0]) ? ucfirst($segments[0]) : '';

        if (!$resource) {
            $this->_sendError(404, 'not_found', 'Endpoint not found');
            return;
        }

        $params = array_slice($segments, 1);

        // Map resource name to model class
        $modelClass = 'xfeoauth2/api_' . strtolower($resource);
        $model = Mage::getModel($modelClass);

        if (!$model || !$model instanceof XFE_OAuth2_Model_Api_Abstract) {
            $this->_sendError(404, 'not_found', 'API resource not found: ' . $resource);
            return;
        }

        $httpMethod = $this->getRequest()->getMethod();
        $model->dispatch($httpMethod, $params);
    }

    /**
     * Send JSON error response
     *
     * @param int $code
     * @param string $error
     * @param string $message
     */
    protected function _sendError($code, $error, $message)
    {
        $helper = Mage::helper('xfeoauth2');

        if ($helper) {
            $helper->sendJsonError($code, $error, $message);
        } else {
            Mage::app()->getResponse()
                ->clearHeaders()
                ->setHeader('Content-Type', 'application/json')
                ->setHttpResponseCode($code)
                ->setBody(json_encode(array(
                    'code' => $code,
                    'error' => $error,
                    'message' => $message,
                )))
                ->sendResponse();
            exit;
        }
    }
}
