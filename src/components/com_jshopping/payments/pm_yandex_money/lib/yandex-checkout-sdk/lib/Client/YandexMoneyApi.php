<?php

namespace YaMoney\Client;

use YaMoney\Common\Exceptions\BadApiRequestException;
use YaMoney\Common\Exceptions\ForbiddenException;
use YaMoney\Common\Exceptions\JsonException;
use YaMoney\Common\Exceptions\InternalServerError;
use YaMoney\Common\Exceptions\UnauthorizedException;
use YaMoney\Common\HttpVerb;
use YaMoney\Common\ResponseObject;
use YaMoney\Helpers\Config\ConfigurationLoader;
use YaMoney\Helpers\Config\ConfigurationLoaderInterface;
use YaMoney\Request\PaymentOptionsRequestInterface;
use YaMoney\Request\PaymentOptionsRequestSerializer;
use YaMoney\Request\PaymentOptionsResponse;
use YaMoney\Request\Payments\CreatePaymentRequestInterface;
use YaMoney\Request\Payments\CreatePaymentResponse;
use YaMoney\Request\Payments\CreatePaymentRequestSerializer;
use YaMoney\Request\Payments\Payment\CancelResponse;
use YaMoney\Request\Payments\Payment\CreateCaptureRequestInterface;
use YaMoney\Request\Payments\Payment\CreateCaptureRequestSerializer;
use YaMoney\Request\Payments\Payment\CreateCaptureResponse;
use YaMoney\Request\Payments\PaymentResponse;
use YaMoney\Request\Payments\PaymentsRequestInterface;
use YaMoney\Request\Payments\PaymentsRequestSerializer;
use YaMoney\Request\Payments\PaymentsResponse;
use YaMoney\Request\Refunds\CreateRefundRequestInterface;
use YaMoney\Request\Refunds\CreateRefundRequestSerializer;
use YaMoney\Request\Refunds\CreateRefundResponse;
use YaMoney\Request\Refunds\RefundResponse;
use YaMoney\Request\Refunds\RefundsRequestInterface;
use YaMoney\Request\Refunds\RefundsRequestSerializer;
use YaMoney\Request\Refunds\RefundsResponse;

class YandexMoneyApi
{
    /**
     * @var null|ApiClientInterface
     */
    protected $apiClient;

    /**
     * @var string
     */
    private $login;

    /**
     * @var string
     */
    private $password;

    /**
     * @var array
     */
    private $config;

    /**
     * Constructor
     *
     * @param ApiClientInterface|null $apiClient
     * @param ConfigurationLoaderInterface|null $configLoader
     * @internal-param null|ConfigurationLoader $config
     */
    public function __construct(ApiClientInterface $apiClient = null, ConfigurationLoaderInterface $configLoader = null)
    {
        if ($apiClient === null) {
            $apiClient = new CurlClient();
        }

        if ($configLoader === null) {
            $configLoader = new ConfigurationLoader();
            $config = $configLoader->load()->getConfig();
            $this->setConfig($config);
            $apiClient->setConfig($config);
        }

        $this->apiClient = $apiClient;
    }

    /**
     * @param $login
     * @param $password
     * @return YandexMoneyApi $this
     */
    public function setAuth($login, $password)
    {
        $this->login = $login;
        $this->password = $password;

        $this->apiClient
            ->setShopId($this->login)
            ->setShopPassword($this->password);

        return $this;
    }

    /**
     * @return ApiClientInterface
     */
    public function getApiClient()
    {
        return $this->apiClient;
    }

    /**
     * @param ApiClientInterface $apiClient
     *
     * @return YandexMoneyApi
     */
    public function setApiClient(ApiClientInterface $apiClient)
    {
        $this->apiClient = $apiClient;
        $this->apiClient->setConfig($this->config);

        return $this;
    }

    /**
     * Доступные способы оплаты.
     * Используйте этот метод, чтобы получить способы оплаты и сценарии, доступные для вашего заказа.
     * @param PaymentOptionsRequestInterface $paymentOptionsRequest
     * @return PaymentOptionsResponse
     */
    public function getPaymentOptions(PaymentOptionsRequestInterface $paymentOptionsRequest = null)
    {
        $path = "/payment_options";

        if ($paymentOptionsRequest === null) {
            $queryParams = array();
        } else {
            $serializer = new PaymentOptionsRequestSerializer();
            $serializedData = $serializer->serialize($paymentOptionsRequest);
            $queryParams = $serializedData;
        }

        $response = $this->apiClient->call($path, HttpVerb::GET, $queryParams);

        if ($response->getCode() == 200) {
            $result = json_decode($response->getBody(), true);
            return new PaymentOptionsResponse($result);
        } else {
            $this->handleError($response);
        }
    }

    /**
     * Получить список платежей магазина.
     * @param PaymentsRequestInterface $payments
     * @return PaymentsResponse
     */
    public function getPayments(PaymentsRequestInterface $payments = null)
    {
        $path = '/payments';

        if ($payments) {
            $serializer = new PaymentsRequestSerializer();
            $serializedData = $serializer->serialize($payments);
            $queryParams = $serializedData;
        } else {
            $queryParams = array();
        }

        $response = $this->apiClient->call($path, HttpVerb::GET, $queryParams);
        if ($response->getCode() == 200) {
            $result = json_decode($response->getBody(), true);
            $paymentResponse = new PaymentsResponse($result);

            return $paymentResponse;
        } else {
            $this->handleError($response);
        }
    }

    /**
     * Проведение оплаты.
     *
     * @param CreatePaymentRequestInterface $payment
     * @param null $idempotenceKey
     *
     * @return CreatePaymentResponse
     * @throws BadApiRequestException
     * @throws ForbiddenException
     * @throws InternalServerError
     * @throws UnauthorizedException
     */
    public function createPayment(CreatePaymentRequestInterface $payment, $idempotenceKey = null)
    {
        $path = '/payments';

        $headers = array();

        if ($idempotenceKey) {
            $headers['Idempotence-Key'] = $idempotenceKey;
        }

        $serializer = new CreatePaymentRequestSerializer();
        $serializedData = $serializer->serialize($payment);
        $httpBody = $this->encodeData($serializedData);

        $response = $this->apiClient->call($path, HttpVerb::POST, null, $httpBody, $headers);
        if ($response->getCode() == 200) {
            $result = json_decode($response->getBody(), true);
            $paymentResponse = new CreatePaymentResponse($result);

            return $paymentResponse;
        } else {
            $this->handleError($response);
        }
    }

    /**
     * Получить информацию о платеже
     * @param $paymentId
     * @return PaymentResponse
     */
    public function getPaymentInfo($paymentId)
    {
        if ($paymentId === null) {
            throw new \InvalidArgumentException('Missing the required parameter $paymentId');
        }

        $path = '/payments/' . $paymentId;

        $response = $this->apiClient->call($path, HttpVerb::GET, null);
	    if ($response->getCode() == 200) {
		    $result = json_decode($response->getBody(), true);

		    return new PaymentResponse($result);
	    } else {
		    $this->handleError($response);
	    }
    }

    /**
     * Подтвердить оплату.
     * @param CreateCaptureRequestInterface $captureRequest
     * @param $paymentId
     * @param null $idempotenceKey
     * @return CreateCaptureResponse
     */
    public function capturePayment(CreateCaptureRequestInterface $captureRequest, $paymentId, $idempotenceKey = null)
    {
        if ($paymentId === null) {
            throw new \InvalidArgumentException('Missing the required parameter $paymentId');
        }

        $path = '/payments/' . $paymentId . '/capture';

        $headers = array();

        if ($idempotenceKey) {
            $headers['Idempotence-Key'] = $idempotenceKey;
        }

        $serializer = new CreateCaptureRequestSerializer();
        $serializedData = $serializer->serialize($captureRequest);
        $httpBody = $this->encodeData($serializedData);
        $response = $this->apiClient->call($path, HttpVerb::POST, null, $httpBody, $headers);

        if ($response->getCode() == 200) {
            $result = json_decode($response->getBody(), true);

            return new CreateCaptureResponse($result);
        } else {
            $this->handleError($response);
        }
    }

    /**
     * Отменить незавершенную оплату заказа.
     * @param $paymentId
     * @param null $idempotenceKey
     * @return CancelResponse
     */
    public function cancelPayment($paymentId, $idempotenceKey = null)
    {
        if ($paymentId === null) {
            throw new \InvalidArgumentException('Missing the required parameter $paymentId');
        }

        $path = '/payments/' . $paymentId . '/cancel';

        $headers = array();

        if ($idempotenceKey) {
            $headers['Idempotence-Key'] = $idempotenceKey;
        }

        $response = $this->apiClient->call($path, HttpVerb::POST, null, null, $headers);

        if ($response->getCode() == 200) {
            $result = json_decode($response->getBody(), true);

            return new CancelResponse($result);
        } else {
            $this->handleError($response);
        }
    }

    /**
     * Получить список возвратов платежей
     * @param RefundsRequestInterface $refundsRequest
     * @return RefundsResponse
     */
    public function getRefunds(RefundsRequestInterface $refundsRequest = null)
    {
        $path = '/refunds';

        if ($refundsRequest) {
            $serializer = new RefundsRequestSerializer();
            $serializedData = $serializer->serialize($refundsRequest);

            $queryParams = $serializedData;
        } else {
            $queryParams = array();
        }

        $response = $this->apiClient->call($path, HttpVerb::GET, $queryParams);

        if ($response->getCode() == 200) {
            $result = json_decode($response->getBody(), true);
            $refundsResponse = new RefundsResponse($result);

            return $refundsResponse;
        } else {
            $this->handleError($response);
        }
    }

    /**
     * Проведение возврата платежа
     * @param CreateRefundRequestInterface $refundsRequest
     * @param null $idempotenceKey
     * @return CreateRefundResponse
     */
    public function createRefund(CreateRefundRequestInterface $refundsRequest, $idempotenceKey = null)
    {
        $path = '/refunds';

        $headers = array();

        if ($idempotenceKey) {
            $headers['Idempotence-Key'] = $idempotenceKey;
        }

        $serializer = new CreateRefundRequestSerializer();
        $serializedData = $serializer->serialize($refundsRequest);
        $httpBody = $this->encodeData($serializedData);
        $response = $this->apiClient->call($path, HttpVerb::POST, null, $httpBody, $headers);

        if ($response->getCode() == 200) {
            $result = json_decode($response->getBody(), true);

            return new CreateRefundResponse($result);
        } else {
            $this->handleError($response);
        }
    }

    /**
     * Получить информацию о возврате
     * @param $refundId
     * @return RefundResponse
     */
    public function getRefundInfo($refundId)
    {
        if ($refundId === null) {
            throw new \InvalidArgumentException('Missing the required parameter $refundId');
        }

        $path = '/refunds/' . $refundId;
        $response = $this->apiClient->call($path, HttpVerb::GET, null);

        if ($response->getCode() == 200) {
            $result = json_decode($response->getBody(), true);

            return new RefundResponse($result);
        } else {
            $this->handleError($response);
        }
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param array $config
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * @param $serializedData
     * @return string
     * @throws \Exception
     */
    private function encodeData($serializedData)
    {
        $result = json_encode($serializedData);
        if ($result === false) {
            $errorCode = json_last_error();
            throw new JsonException("Failed serialize json.", $errorCode);
        }

        return $result;
    }

    /**
     * @param ResponseObject $response
     * @throws BadApiRequestException
     * @throws ForbiddenException
     * @throws InternalServerError
     * @throws UnauthorizedException
     */
    private function handleError(ResponseObject $response)
    {
        switch ($response->getCode()) {
            case BadApiRequestException::HTTP_CODE:
                throw new BadApiRequestException($response->getHeaders(), $response->getBody());
                break;
            case ForbiddenException::HTTP_CODE:
                throw new ForbiddenException($response->getHeaders(), $response->getBody());
                break;
            case UnauthorizedException::HTTP_CODE:
                throw new UnauthorizedException($response->getHeaders(), $response->getBody());
                break;
            case InternalServerError::HTTP_CODE:
                throw new InternalServerError($response->getHeaders(), $response->getBody());
                break;
        }
    }
}