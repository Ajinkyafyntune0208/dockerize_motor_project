<nav class="sidebar sidebar-offcanvas" id="sidebar">
    <ul class="nav">
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#ui-basic" aria-expanded="false" aria-controls="ui-basic">
                <i class="menu-icon mdi mdi-floor-plan"></i>
                <span class="menu-title">Admin</span>
                <i class="menu-arrow"></i>
            </a>
            <div class="collapse" id="ui-basic">
                <ul class="nav flex-column sub-menu">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.vahan_service.index') }}">Vahan Service</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.vahan-service-credentials.index') }}">Vahan Service credentials</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.vahan-service-stage.stageIndex') }}">Vahan Service Configuration</a>
                    </li>
                </ul>
            </div>
        </li>
    </ul>
</nav>
