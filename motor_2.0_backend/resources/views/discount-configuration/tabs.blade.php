<nav>
    <div class="nav nav-tabs" id="nav-tab" role="tablist">
        <a href="{{route('admin.discount-configurations.config-setting')}}" class="nav-link {{request()->routeIs('admin.discount-configurations.config-setting') ? 'active' : ''}}">Configuration Setting</a>
        <a class="nav-link {{request()->routeIs('admin.discount-configurations.global-config') ? 'active' : ''}}" href="{{route('admin.discount-configurations.global-config')}}">Global Configuration</a>
        <a class="nav-link {{request()->routeIs('admin.discount-configurations.vehicle-config') ? 'active' : ''}}" href="{{route('admin.discount-configurations.vehicle-config')}}">Vehicle wise Configuration</a>
        <a class="nav-link {{request()->routeIs('admin.discount-configurations.ic-config') ? 'active' : ''}}" href="{{route('admin.discount-configurations.ic-config')}}">Vehicle and IC wise Configuration</a>
        <a class="nav-link {{request()->routeIs('admin.discount-configurations.active-config') ? 'active' : ''}}" href="{{route('admin.discount-configurations.active-config')}}">Activation Setting</a>
    </div>
</nav>