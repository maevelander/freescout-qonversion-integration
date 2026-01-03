<div class="sidebar-block qonversion-block">
    <div class="sidebar-block-header">
        <h3>
            Qonversion Profile
        </h3>
    </div>
    <div class="sidebar-block-body">
        @if($customer_data === null)
            {{-- User not found in Qonversion - never subscribed --}}
            <div class="qonv-field">
                <div class="qonv-label">Subscription Status</div>
                <div class="qonv-value text-muted">None</div>
            </div>
        @else
            {{-- Display subscription status --}}
            <div class="qonv-field">
                <div class="qonv-label">Subscription Status</div>
                <div class="qonv-value">
                    @if($customer_data['subscription_status'] === 'Active')
                        <span class="label label-success">Active</span>
                    @elseif($customer_data['subscription_status'] === 'Expired')
                        <span class="label label-warning">Expired</span>
                    @else
                        <span class="label label-default">{{ $customer_data['subscription_status'] }}</span>
                    @endif
                </div>
            </div>

            {{-- Display subscription details if available --}}
            @if(!empty($customer_data['subscription_details']))
                @foreach($customer_data['subscription_details'] as $subscription)
                    <div class="qonv-subscription-detail">
                        <div class="text-muted small">
                            {{ $subscription['product_id'] }}
                            @if(isset($subscription['expires_at_formatted']))
                                <br>Expires: {{ $subscription['expires_at_formatted'] }}
                            @endif
                            @if(isset($subscription['will_renew']))
                                <br>{{ $subscription['will_renew'] ? 'Will renew' : 'Will not renew' }}
                            @endif
                        </div>
                    </div>
                @endforeach
            @endif

            {{-- Display platform --}}
            @if($customer_data['platform'])
                <div class="qonv-field" style="margin-top: 10px;">
                    <div class="qonv-label">Platform</div>
                    <div class="qonv-value">{{ $customer_data['platform'] }}</div>
                </div>
            @endif
        @endif

        {{-- Link to Qonversion dashboard --}}
        <a href="{{ $qonversion_url }}" target="_blank" class="btn btn-default btn-sm btn-block qonv-dashboard-link">
            <i class="glyphicon glyphicon-new-window"></i>
            @if($customer_data && isset($customer_data['qonversion_user_id']))
                View in Qonversion
            @else
                Search in Qonversion
            @endif
        </a>
    </div>
</div>
