<?php

namespace Modules\QonversionIntegration\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SettingsController extends Controller
{
    public function save(Request $request)
    {
        $settings = $request->input('settings', []);

        if (isset($settings['qonversionintegration.project_key'])) {
            \Option::set('qonversionintegration.project_key', $settings['qonversionintegration.project_key']);
        }

        if (isset($settings['qonversionintegration.project_id'])) {
            \Option::set('qonversionintegration.project_id', $settings['qonversionintegration.project_id']);
        }

        if (isset($settings['qonversionintegration.environment'])) {
            \Option::set('qonversionintegration.environment', $settings['qonversionintegration.environment']);
        }

        // Save mailboxes as JSON array (empty array if none selected)
        $mailboxes = $settings['qonversionintegration.mailboxes'] ?? [];
        \Option::set('qonversionintegration.mailboxes', json_encode(array_map('intval', $mailboxes)));

        \Session::flash('flash_success_floating', __('Settings saved successfully.'));

        return redirect()->route('settings', ['section' => 'qonversion']);
    }
}
