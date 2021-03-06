<?php

namespace OpenApi\Controller\Front;

use Doctrine\Common\Annotations\AnnotationRegistry;
use OpenApi\Exception\OpenApiException;
use OpenApi\Model\Api\Error;
use OpenApi\Model\Api\ModelFactory;
use OpenApi\OpenApi;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\Translation\Translator;

abstract class BaseFrontOpenApiController extends BaseFrontController
{
    const GROUP_CREATE = 'create';

    const GROUP_READ = 'read';

    const GROUP_UPDATE = 'update';

    const GROUP_DELETE = 'delete';

    /** @var ModelFactory */
    private $modelFactory;

    protected $requestData = null;

    public function __construct()
    {
        $loader = require THELIA_VENDOR.'autoload.php';
        AnnotationRegistry::registerLoader([$loader, 'loadClass']);
    }

    public function setContainer(ContainerInterface $container = null)
    {
        parent::setContainer($container);

        // Used to identify all routes as "OPEN API ROUTES" (e.g for json exception)
        $this->getRequest()->attributes->set(OpenApi::OPEN_API_ROUTE_REQUEST_KEY, true);
        $this->requestData = json_decode($this->getRequest()->getContent(), true);
        $this->modelFactory = $container->get('open_api.model.factory');
    }

    public function jsonResponse($data, $code = 200)
    {
        $response = (new JsonResponse())
            ->setContent(json_encode($data));

        // TODO : Add more flexibility to CORS check
        $response->headers->set('Access-Control-Allow-Origin', '*');
        return $response;
    }

    /**
     * @param bool $throwExceptionIfNull
     *
     * @return \Thelia\Model\Customer
     * @throws OpenApiException
     */
    protected function getCurrentCustomer($throwExceptionIfNull = true)
    {
        $currentCustomer = $this->getSecurityContext()->getCustomerUser();

        if (null === $currentCustomer && $throwExceptionIfNull) {
            /** @var Error $error */
            $error = $this->modelFactory->buildModel(
                'Error',
                [
                    'title' => Translator::getInstance()->trans('Invalid data', [], OpenApi::DOMAIN_NAME),
                    'description' => Translator::getInstance()->trans("No customer found", [], OpenApi::DOMAIN_NAME),
                ]
            );
            throw new OpenApiException($error);
        }

        return $currentCustomer;
    }

    /**
     * @param bool $throwExceptionIfNull
     *
     * @return \Thelia\Model\Cart
     * @throws OpenApiException
     */
    protected function getSessionCart($throwExceptionIfNull = true)
    {
        $cart = $this->getRequest()->getSession()->getSessionCart($this->getDispatcher());

        if (null === $cart && $throwExceptionIfNull) {
            /** @var Error $error */
            $error = $this->modelFactory->buildModel(
                'Error',
                [
                    'title' => Translator::getInstance()->trans('Invalid data', [], OpenApi::DOMAIN_NAME),
                    'description' => Translator::getInstance()->trans("No cart found", [], OpenApi::DOMAIN_NAME),
                ]
            );
            throw new OpenApiException($error);
        }

        return $cart;
    }

    protected function getModelFactory()
    {
        if (null === $this->modelFactory) {
            $this->modelFactory = $this->getContainer()->get('open_api.model.factory');
        }

        return $this->modelFactory;
    }

    protected function getRequestValue($key, $default = null)
    {
        if (!isset($this->requestData[$key]) || null === $this->requestData[$key]) {
            return $default;
        }

        return $this->requestData[$key];
    }
}