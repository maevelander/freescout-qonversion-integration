<?php

namespace Modules\QonversionIntegration\Providers;

use Illuminate\Support\ServiceProvider;

if (!defined('QONVERSION_INTEGRATION_MODULE')) {
    define('QONVERSION_INTEGRATION_MODULE', 'qonversionintegration');
}

class QonversionIntegrationServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerConfig();
        $this->registerViews();
        $this->registerRoutes();
        $this->hooks();
    }

    protected function registerRoutes()
    {
        $this->loadRoutesFrom(__DIR__.'/../Http/routes.php');
    }

    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('qonversionintegration.php'),
        ], 'config');
        
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'qonversionintegration'
        );
    }

    protected function registerViews()
    {
        $viewPath = resource_path('views/modules/qonversionintegration');
        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ],'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/qonversionintegration';
        }, \Config::get('view.paths')), [$sourcePath]), 'qonversionintegration');
    }

    protected function hooks()
    {
        // Add CSS to conversation page
        \Eventy::addFilter('stylesheets', function($styles) {
            $styles[] = \Module::getPublicPath(QONVERSION_INTEGRATION_MODULE).'/css/module.css';
            return $styles;
        });
        
        // Add Qonversion sidebar to conversation view
        \Eventy::addAction('conversation.after_prev_convs', function($customer, $conversation, $mailbox) {
            // Only show if module is configured
            $projectKey = \Option::get('qonversionintegration.project_key');
            $projectId = \Option::get('qonversionintegration.project_id');

            if (!$projectKey || !$projectId) {
                return;
            }

            // Check if this mailbox should show the sidebar
            $mailboxSetting = \Option::get('qonversionintegration.mailboxes', '[]');
            $allowedMailboxes = is_array($mailboxSetting) ? $mailboxSetting : (json_decode($mailboxSetting, true) ?: []);
            if (!empty($allowedMailboxes) && !in_array($mailbox->id, $allowedMailboxes)) {
                return;
            }

            $customer_email = $customer->getMainEmail();

            // Fetch fresh data from Qonversion API
            try {
                $apiService = new \Modules\QonversionIntegration\Services\QonversionApiService();
                $customerData = $apiService->getCustomerData($customer_email);
            } catch (\Exception $e) {
                \Log::error('Qonversion API error', ['error' => $e->getMessage()]);
                $customerData = null;
            }

            // Build Qonversion dashboard URL
            $environment = \Option::get('qonversionintegration.environment', '0');

            if ($customerData && isset($customerData['qonversion_user_id'])) {
                // Direct link to customer profile
                $qonversionUrl = sprintf(
                    'https://dash.qonversion.io/customers/details/%s?project=%s&page=1&environment=%s',
                    $customerData['qonversion_user_id'],
                    $projectId,
                    $environment
                );
            } else {
                // Fallback to customers list with project context (user can search manually)
                $qonversionUrl = sprintf(
                    'https://dash.qonversion.io/customers?project=%s&environment=%s',
                    $projectId,
                    $environment
                );
            }

            echo view('qonversionintegration::sidebar', [
                'customer_email' => $customer_email,
                'customer_data' => $customerData,
                'qonversion_url' => $qonversionUrl
            ])->render();

        }, 20, 3);
        
        // Add settings section
        \Eventy::addFilter('settings.sections', function($sections) {
            $sections['qonversion'] = ['title' => __('Qonversion'), 'icon' => 'credit-card', 'order' => 600];
            return $sections;
        });

        \Eventy::addFilter('settings.section_settings', function($settings, $section) {
            if ($section !== 'qonversion') {
                return $settings;
            }

            return [
                'qonversionintegration.project_key' => \Option::get('qonversionintegration.project_key', ''),
                'qonversionintegration.project_id' => \Option::get('qonversionintegration.project_id', ''),
                'qonversionintegration.environment' => \Option::get('qonversionintegration.environment', '0'),
                'qonversionintegration.mailboxes' => \Option::get('qonversionintegration.mailboxes', '[]'),
            ];
        }, 20, 2);

        \Eventy::addFilter('settings.section_params', function($params, $section) {
            if ($section !== 'qonversion') {
                return $params;
            }

            $params = [
                'template_vars' => [],
                'validator_rules' => [
                    'settings.qonversionintegration.project_key' => 'required',
                    'settings.qonversionintegration.project_id' => 'required',
                ],
                'settings' => [
                    'qonversionintegration.project_key' => \Option::get('qonversionintegration.project_key', ''),
                    'qonversionintegration.project_id' => \Option::get('qonversionintegration.project_id', ''),
                    'qonversionintegration.environment' => \Option::get('qonversionintegration.environment', '0'),
                    'qonversionintegration.mailboxes' => \Option::get('qonversionintegration.mailboxes', '[]'),
                ]
            ];

            return $params;
        }, 20, 2);

        \Eventy::addFilter('settings.view', function($view, $section) {
            if ($section !== 'qonversion') {
                return $view;
            }
            return 'qonversionintegration::settings';
        }, 20, 2);

        // Handle settings save
        \Eventy::addFilter('settings.before_save', function($request, $section, $settings) {
            if ($section !== 'qonversion') {
                return $request;
            }
            \Log::info('Qonversion settings before_save triggered', [
                'section' => $section,
                'settings_keys' => array_keys($settings),
                'request_settings' => $request->settings ?? []
            ]);
            return $request;
        }, 20, 3);
    }

    public function register()
    {
        //
    }
}