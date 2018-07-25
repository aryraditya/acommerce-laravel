<?php

namespace aryraditya\aCommerceLaravel;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

class aCommerce
{
    const DATETIME_FORMAT   = 'Y-m-d\TH:i:s.v\Z';

    /**
     * aCommerce Environment
     *
     * @var bool
     */
    protected $production   = true;

    /**
     * aCommerce API Username
     *
     * @var string
     */
    private $username;

    /**
     * aCommerce API Key
     *
     * @var string
     */
    private $key;

    /**
     * aCommerce constructor.
     *
     * @param null $username
     * @param null $key
     * @param bool $production
     */
    public function __construct($username = null, $key = null, $production = true)
    {
        $this->username     = $username ?: config('acommerce.username');
        $this->key          = $key ?: config('acommerce.api_key');
        $this->production   = $production;
    }

    /**
     * Get Unique ID
     *
     * @return string
     */
    public function getId()
    {
        return 'acommerce-'.$this->username . $this->key;
    }

    /**
     * Base URL for authentication
     *
     * @return string
     */
    public function baseAuthUrl()
    {
        return $this->production ? 'https://api.acommerce.asia' : 'https://api.acommercedev.com';
    }

    /**
     * Base URL for fulfillment endpoint api
     *
     * @return string
     */
    public function baseFulfillmentUrl()
    {
        return $this->production ? 'https://fulfillment.api.acommerce.asia' : 'https://fulfillment.api.acommercedev.com';
    }

    /**
     * Base URL for shipping endpoint api
     *
     * @return string
     */
    public function baseShippingUrl()
    {
        return $this->production ? 'https://shipping.api.acommerce.asia' : 'https://shipping.api.acommercedev.com';
    }

    /**
     * Get aCommerce Username
     *
     * @return mixed
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Set aCommerce Username
     *
     * @param $value
     *
     * @return $this
     */
    public function setUsername($value)
    {
        $this->username = $value;
        return $this;
    }

    /**
     * Get aCommerce API KEY
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Set aCommerce API Key
     *
     * @param string|integer $value
     *
     * @return $this
     */
    public function setKey($value)
    {
        $this->key = $value;
        return $this;
    }

    /**
     * Get auth token
     * @return string
     */
    public function token()
    {
        $token      = \Cache::get($this->getId());

        if(!$token) {
            $auth       = $this->auth();
            $token      = @$auth->token->token_id;
            $duration   = config('acommerce.cache.duration', 60 * 60 * 2);

            \Cache::put($this->getId(), $token, Carbon::now()->addSeconds($duration));
        }

        return $token;
    }

    /**
     * Authentication username and api key
     *
     * @return mixed|boolean
     */
    public function auth()
    {
        $client     = new Client([
            'base_uri'  => $this->baseAuthUrl()
        ]);

        try {
            $request    = $client->post('identity/token', [
                'json'  => [
                    'auth'  => [
                        'apiKeyCredentials' => [
                            'username'  => $this->username,
                            'apiKey'    => $this->key
                        ]
                    ]
                ]
            ]);

            $response   = json_decode($request->getBody()->getContents());

        } catch (ClientException $ex) {
            $response   = false;
        }

        return $response;
    }


    /**
     * Get merchant list on channel
     * @param $channelId
     *
     * @return mixed
     */
    public function merchants($channelId)
    {
        $client     = $this->client();
        $request    = $client->get('channel/'.$channelId.'/merchants');

        return json_decode($request->getBody()->getContents());
    }

    /**
     * Get inventory list by partner
     * https://acommerce.atlassian.net/wiki/spaces/PA/pages/23429382/Inventory+Allocation
     *
     * @param string      $channelId Channel ID
     * @param string      $partnerId Partner ID
     * @param string|null $since     to specify the earliest update datetime of the allocation record to be return. All
     *                               records that have an updated updatedDateTime later than this value will be
     *                               returned in the result set. ISO 8601 datetime format
     * @param string|null $page      the page number of list
     *
     * @return \stdClass
     */
    public function inventory($channelId, $partnerId, $since = null, $page = 1)
    {
        $url        = strtr('channel/:channelId/allocation/merchant/:merchantId', [
            ':channelId'    => $channelId,
            ':merchantId'   => $partnerId
        ]);
        $page       = $page === 0 || $page === false ? null : $page;

        $data       = [
            'query' => [
                'since' => $since,
                'page'  => $page,
            ]
        ];
        $request    = $this->request('GET', $url, $data);

        $link               = $request->getHeaderLine('Link');
        $response           = new \stdClass();
        $response->next     = strpos($link, 'next') ? ((int) $page) + 1 : false;
        $response->prev     = strpos($link, 'prev') ? ((int) $page) - 1 : false;
        $response->items    = json_decode($request->getBody()->getContents());

        return $response;
    }

    /**
     * @param integer     $channelId Channel ID
     * @param integer     $partnerId Partner ID
     * @param string|null $since     To specify the earliest update datetime of the allocation record to be return. All
     *                               records that have an updated updatedDateTime later than this value will be
     *                               returned in the result set. ISO 8601 datetime format
     * @param integer     $startPage starting page
     *
     * @return array
     */
    public function allInventory($channelId, $partnerId, $since = null, $startPage = 1)
    {
        $inventory = [];
        $response  = $this->inventory($channelId, $partnerId, $since, $startPage);

        if(isset($response->items) && $response->items) {
            array_push($inventory, ...$response->items);

            if($response->next) {
                array_push($inventory, ...$this->allInventory($channelId, $partnerId, $since, $response->next));
            }
        }

        return $inventory;
    }

    /**
     * Sales Order Creation
     *
     * @param $channelId
     * @param $orderId
     * @param $data
     *
     * @return \stdClass
     */
    public function salesOrderCreation($channelId, $orderId, $data)
    {
        $url        = 'channel/' . $channelId . '/order/' . $orderId;

        $request    = $this->request('PUT', $url, [
            'json'  => $data
        ]);

        return json_decode($request->getBody()->getContents());
    }

    /**
     * Get sales order's detail
     *
     * @param string $channelId
     * @param string $orderId
     *
     * @return mixed
     */
    public function getSalesOrder($channelId, $orderId)
    {
        $url        = 'channel/' . $channelId . '/order/' . $orderId;
        $request    = $this->request('GET', $url);

        return json_decode($request->getBody()->getContents());
    }


    /**
     * Shipping Order Retrieval
     * https://acommerce.atlassian.net/wiki/spaces/PA/pages/16515568/Shipping+Orders
     *
     * @param string $shippingPartnerId
     * @param string $shippingOrderId
     *
     * @return mixed
     */
    public function getShippingOrder($shippingPartnerId, $shippingOrderId)
    {
        $url        = strtr('partner/:shippingPartnerID/order/:shippingOrderId', [
            ':shippingPartnerID'    => $shippingPartnerId,
            ':shippingOrderId'      => $shippingOrderId,
        ]);

        $request    = $this->request('GET', $url, [], $this->baseShippingUrl());

        return json_decode($request->getBody()->getContents());
    }


    /**
     * Shipping Order Creation
     * https://acommerce.atlassian.net/wiki/spaces/PA/pages/16515568/Shipping+Orders
     *
     * @param string $shippingPartnerId
     * @param string $shippingOrderId
     * @param array  $data
     *
     * @return mixed
     */
    public function shippingOrderCreation($shippingPartnerId, $shippingOrderId, $data = [])
    {
        $url        = strtr('partner/:shippingPartnerID/order/:shippingOrderId', [
            ':shippingPartnerID'    => $shippingPartnerId,
            ':shippingOrderId'      => $shippingOrderId,
        ]);

        $request    = $this->request('PUT', $url, [
            'json'  => $data
        ], $this->baseShippingUrl());

        return json_decode($request->getBody()->getContents());
    }

    /**
     * Request to end point
     *
     * @param string $method
     * @param string $url
     * @param array $data
     * @param string $baseUrl
     *
     * @return mixed|null|\Psr\Http\Message\ResponseInterface
     */
    public function request($method, $url, $data = [], $baseUrl = null)
    {
        try {
            return $this->client($baseUrl)
                ->request($method, $url, $data);
        } catch (ServerException $ex) {
            return $ex->getResponse();
        } catch (ClientException $ex) {
            return $ex->getResponse();
        }
    }

    /**
     * Guzzle Client config
     * @param string $baseUrl Base URL for client
     *
     * @return Client
     */
    protected function client($baseUrl = null)
    {
        $client         = new Client([
            'base_uri'  => $baseUrl ?: $this->baseFulfillmentUrl(),
            'verify'    => false,
            'headers'   => [
                'X-Subject-Token'   => $this->token(),
                'User-Agent'        => config('app.name'),
            ]
        ]);

        return $client;
    }
}