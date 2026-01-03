<form class="form-horizontal margin-top" method="POST" action="{{ route('qonversion.save_settings') }}">
    {{ csrf_field() }}

<div class="form-group row" style="margin-top: 20px;">
    <label class="col-sm-2 control-label">Mailboxes</label>
    <div class="col-sm-6">
        @php
            $mailboxes = \App\Mailbox::orderBy('name')->get();
            $selectedMailboxes = $settings['qonversionintegration.mailboxes'] ?? [];
            if (is_string($selectedMailboxes)) {
                $selectedMailboxes = json_decode($selectedMailboxes, true) ?: [];
            }
        @endphp
        @foreach($mailboxes as $mailbox)
            <div class="checkbox">
                <label>
                    <input type="checkbox"
                           name="settings[qonversionintegration.mailboxes][]"
                           value="{{ $mailbox->id }}"
                           {{ in_array($mailbox->id, $selectedMailboxes) ? 'checked' : '' }}>
                    {{ $mailbox->name }}
                </label>
            </div>
        @endforeach
        <p class="form-help">
            Select which mailboxes should display the Qonversion sidebar. If none selected, sidebar will show in all mailboxes.
        </p>
    </div>
</div>

<div class="form-group row" style="margin-top: 20px;">
    <label for="qonversion_project_key" class="col-sm-2 control-label">Qonversion Project Key</label>
    <div class="col-sm-6">
        <input type="password"
               class="form-control input-sized-lg"
               name="settings[qonversionintegration.project_key]"
               id="qonversion_project_key"
               value="{{ old('settings') ? old('settings')['qonversionintegration.project_key'] : ($settings['qonversionintegration.project_key'] ?? '') }}"
               autocomplete="off"
               required>
        <p class="form-help">
            Find this in <a href="https://dash.qonversion.io" target="_blank">Qonversion Dashboard</a> → select your project → <strong>Project Settings</strong> (gear icon) → <strong>General</strong> → copy the <strong>Project Key</strong> (not the Secret Key)
        </p>
    </div>
</div>

<div class="form-group row" style="margin-top: 20px;">
    <label for="qonversion_project_id" class="col-sm-2 control-label">Qonversion Project ID</label>
    <div class="col-sm-6">
        <input type="text"
               class="form-control"
               name="settings[qonversionintegration.project_id]"
               id="qonversion_project_id"
               value="{{ old('settings') ? old('settings')['qonversionintegration.project_id'] : $settings['qonversionintegration.project_id'] }}"
               placeholder="e.g., G7zv7LAb"
               required>
        <p class="form-help">
            Find this in your Qonversion dashboard URL. For example, if your URL is <code>https://dash.qonversion.io/?project=<strong>G7zv7LAb</strong></code>, enter <strong>G7zv7LAb</strong>
        </p>
    </div>
</div>

<div class="form-group row" style="margin-top: 20px;">
    <label for="qonversion_environment" class="col-sm-2 control-label">Environment</label>
    <div class="col-sm-6">
        @php
            $envValue = old('settings') ? old('settings')['qonversionintegration.environment'] : $settings['qonversionintegration.environment'];
        @endphp
        <select class="form-control"
                name="settings[qonversionintegration.environment]"
                id="qonversion_environment">
            <option value="0" {{ ($envValue ?? '0') == '0' ? 'selected' : '' }}>
                Production
            </option>
            <option value="1" {{ ($envValue ?? '0') == '1' ? 'selected' : '' }}>
                Sandbox
            </option>
        </select>
        <p class="form-help">
            Select which Qonversion environment to link to (usually Production)
        </p>
    </div>
</div>

<div class="form-group row" style="margin-top: 20px;">
    <div class="col-sm-6 col-sm-offset-2">
        <button type="submit" class="btn btn-primary">
            {{ __('Save') }}
        </button>
    </div>
</div>

</form>