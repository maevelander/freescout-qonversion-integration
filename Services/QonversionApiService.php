<?php

namespace Modules\QonversionIntegration\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class QonversionApiService
{
    protected $client;
    protected $projectKey;
    protected $baseUrl = 'https://api.qonversion.io/v3/';

    public function __construct()
    {
        $this->projectKey = \Option::get('qonversionintegration.project_key');

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->projectKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ]);
    }

    /**
     * Get complete customer data by email
     * Returns null if customer not found in Qonversion
     */
    public function getCustomerData($email)
    {
        try {
            // Step 1: Get Qonversion User ID from email identity
            $identity = $this->getIdentity($email);

            if (!$identity || !isset($identity['user_id'])) {
                // Customer not found in Qonversion
                return null;
            }

            $userId = $identity['user_id'];

            // Step 2: Get all customer data concurrently
            $results = $this->fetchUserDataConcurrently($userId);
            $user = $results['user'];
            $properties = $results['properties'];
            $entitlements = $results['entitlements'];

            // Step 3: Parse and structure the data
            $data = $this->parseCustomerData($user, $properties, $entitlements);

            // Add the Qonversion User ID to the returned data
            $data['qonversion_user_id'] = $userId;

            return $data;

        } catch (\Exception $e) {
            \Log::error("Qonversion getCustomerData failed for {$email}: " . $e->getMessage());
            return null;
        }
    }

    protected function getIdentity($email)
    {
        try {
            // Use rawurlencode for proper URL encoding of email (@ becomes %40)
            $encodedEmail = rawurlencode($email);
            $response = $this->client->get("identities/" . $encodedEmail);
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            if ($e->hasResponse() && $e->getResponse()->getStatusCode() == 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Fetch user, properties, and entitlements sequentially
     */
    protected function fetchUserDataConcurrently($userId)
    {
        $results = [
            'user' => null,
            'properties' => null,
            'entitlements' => [],
        ];

        // Fetch user data
        try {
            $response = $this->client->get("users/{$userId}");
            $results['user'] = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            // User data fetch failed, continue with other data
        }

        // Fetch properties
        try {
            $response = $this->client->get("users/{$userId}/properties");
            $results['properties'] = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            // Properties fetch failed, continue with other data
        }

        // Fetch entitlements
        try {
            $response = $this->client->get("users/{$userId}/entitlements");
            $results['entitlements'] = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            // Entitlements fetch failed, continue with other data
        }

        return $results;
    }

    /**
     * Parse raw API data into structured customer information
     */
    protected function parseCustomerData($user, $properties, $entitlements)
    {
        $data = [
            'country' => null,
            'platform' => null,
            'platform_version' => null,
            'device_make' => null,
            'device_model' => null,
            'app_version' => null,
            'subscription_status' => 'None',
            'subscription_details' => []
        ];

        // Parse user properties for device/app info
        // Qonversion SDK stores these as standard properties
        if ($properties && isset($properties['properties'])) {
            foreach ($properties['properties'] as $property) {
                $key = $property['key'] ?? '';
                $value = $property['value'] ?? '';

                switch (strtolower($key)) {
                    case '_q_country':
                    case 'country':
                        $data['country'] = $value;
                        break;
                    case '_q_platform':
                    case 'platform':
                        $data['platform'] = $this->formatPlatform($value);
                        break;
                    case '_q_os_version':
                    case 'os_version':
                    case 'osversion':
                        $data['platform_version'] = $value;
                        break;
                    case '_q_device':
                    case 'device':
                    case 'device_model':
                        $data['device_model'] = $value;
                        break;
                    case '_q_device_manufacturer':
                    case 'device_manufacturer':
                    case 'manufacturer':
                        $data['device_make'] = $value;
                        break;
                    case '_q_app_version':
                    case 'app_version':
                    case 'version':
                        $data['app_version'] = $value;
                        break;
                }
            }
        }

        // If we still don't have platform from properties, try to detect from entitlements source
        if (!$data['platform'] && $entitlements && isset($entitlements['data']) && count($entitlements['data']) > 0) {
            // Get platform from the most recent entitlement's source
            $latestEntitlement = $entitlements['data'][0];
            if (isset($latestEntitlement['source'])) {
                $data['platform'] = $this->formatPlatform($latestEntitlement['source']);
            }
        }

        // Parse subscription status from entitlements
        if ($entitlements && isset($entitlements['data']) && count($entitlements['data']) > 0) {
            $activeEntitlements = array_filter($entitlements['data'], function($ent) {
                return isset($ent['active']) && $ent['active'] === true;
            });

            if (count($activeEntitlements) > 0) {
                $data['subscription_status'] = 'Active';
                $data['subscription_details'] = $this->parseSubscriptionDetails($activeEntitlements);
            } else {
                $data['subscription_status'] = 'Expired';
                $data['subscription_details'] = $this->parseSubscriptionDetails($entitlements['data']);
            }
        }

        return $data;
    }

    protected function formatPlatform($platform)
    {
        $platform = strtolower($platform);
        if (strpos($platform, 'ios') !== false || strpos($platform, 'appstore') !== false || strpos($platform, 'app_store') !== false) {
            return 'iOS';
        } elseif (strpos($platform, 'android') !== false || strpos($platform, 'playstore') !== false || strpos($platform, 'play_store') !== false) {
            return 'Android';
        } elseif (strpos($platform, 'stripe') !== false || strpos($platform, 'web') !== false) {
            return 'Web';
        } elseif (strpos($platform, 'prod') !== false) {
            // "prod" or "production" is an environment, not a platform - return null
            return null;
        }
        return ucfirst($platform);
    }

    protected function parseSubscriptionDetails($entitlements)
    {
        $details = [];
        foreach ($entitlements as $entitlement) {
            // Product ID is nested under product.product_id
            $productId = 'Unknown';
            if (isset($entitlement['product']['product_id'])) {
                $productId = $entitlement['product']['product_id'];
            } elseif (isset($entitlement['id'])) {
                $productId = $entitlement['id'];
            }

            $detail = [
                'id' => $entitlement['id'] ?? '',
                'active' => $entitlement['active'] ?? false,
                'product_id' => $productId,
                'source' => $this->formatPlatform($entitlement['source'] ?? ''),
            ];

            // Qonversion uses Unix timestamps for started/expires
            if (isset($entitlement['started'])) {
                $detail['started_at'] = $entitlement['started'];
                $detail['started_at_formatted'] = \Carbon\Carbon::createFromTimestamp($entitlement['started'])->format('M d, Y');
            }

            if (isset($entitlement['expires'])) {
                $detail['expires_at'] = $entitlement['expires'];
                $detail['expires_at_formatted'] = \Carbon\Carbon::createFromTimestamp($entitlement['expires'])->format('M d, Y');
            }

            // Renew state is nested under product.subscription.renew_state
            if (isset($entitlement['product']['subscription']['renew_state'])) {
                $detail['will_renew'] = $entitlement['product']['subscription']['renew_state'] === 'will_renew';
            }

            $details[] = $detail;
        }
        return $details;
    }
}
