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

            // Step 2: Fetch entitlements
            $entitlements = $this->fetchEntitlements($userId);

            // Step 3: Parse and structure the data
            $data = $this->parseCustomerData($entitlements);

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
     * Fetch user entitlements
     */
    protected function fetchEntitlements($userId)
    {
        try {
            $response = $this->client->get("users/{$userId}/entitlements");
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Parse entitlements into structured customer information
     */
    protected function parseCustomerData($entitlements)
    {
        $data = [
            'platform' => null,
            'subscription_status' => 'None',
            'subscription_details' => []
        ];

        // Get platform from entitlements source
        if ($entitlements && isset($entitlements['data']) && count($entitlements['data']) > 0) {
            $latestEntitlement = $entitlements['data'][0];
            if (isset($latestEntitlement['source'])) {
                $data['platform'] = $this->formatPlatform($latestEntitlement['source']);
            }

            // Parse subscription status
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
