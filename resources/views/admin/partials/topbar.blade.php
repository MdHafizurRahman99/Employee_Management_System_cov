<nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">


    @php
        $user = auth()->user();
        $roleId = null;
        $notifications = collect();
        $unreadNotificationsCount = 0;

        if ($user) {
            $roleId = $user->roles()->first();
        }

        if ($user) {
            $notificationScope = function ($query) use ($user, $roleId) {
                $query->where(function ($userQuery) use ($user) {
                    $userQuery->where('notifiable_type', $user->getMorphClass())
                        ->where('notifiable_id', $user->getKey());
                });
                if ($roleId) {
                    $query->orWhere(function ($roleQuery) use ($roleId) {
                        $roleQuery->where('notifiable_type', $roleId->getMorphClass())
                            ->where('notifiable_id', $roleId->getKey());
                    });
                }
            };

            $notifications = DB::table('notifications')->where($notificationScope)
                ->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END,created_at DESC')
                ->limit(5)
                ->get();

            $unreadNotificationsCount = DB::table('notifications')->where($notificationScope)
                ->whereNull('read_at')
                ->count();
        }
    @endphp
    <!-- Sidebar Toggle (Topbar) -->
    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
        <i class="fa fa-bars"></i>
    </button>

    <!-- Topbar Search -->
    {{-- <form class="d-none d-sm-inline-block form-inline mr-auto ml-md-3 my-2 my-md-0 mw-100 navbar-search">
        <div class="input-group">
            <input type="text" class="form-control bg-light border-0 small" placeholder="Search for..."
                aria-label="Search" aria-describedby="basic-addon2">
            <div class="input-group-append">
                <button class="btn btn-primary" type="button">
                    <i class="fas fa-search fa-sm"></i>
                </button>
            </div>
        </div>
    </form> --}}

    <!-- Topbar Navbar -->
    <ul class="navbar-nav ml-auto">

        <!-- Nav Item - Search Dropdown (Visible Only XS) -->
        {{-- <li class="nav-item dropdown no-arrow d-sm-none">
            <a class="nav-link dropdown-toggle" href="#" id="searchDropdown" role="button" data-toggle="dropdown"
                aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-search fa-fw"></i>
            </a>
            <!-- Dropdown - Messages -->
            <div class="dropdown-menu dropdown-menu-right p-3 shadow animated--grow-in"
                aria-labelledby="searchDropdown">
                <form class="form-inline mr-auto w-100 navbar-search">
                    <div class="input-group">
                        <input type="text" class="form-control bg-light border-0 small" placeholder="Search for..."
                            aria-label="Search" aria-describedby="basic-addon2">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="button">
                                <i class="fas fa-search fa-sm"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </li> --}}

        <!-- Nav Item - Alerts -->
        <li class="nav-item dropdown no-arrow mx-1">
            <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" data-toggle="dropdown"
                aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-bell fa-fw"></i>
                <!-- Counter - Alerts -->

                <span class="badge badge-danger badge-counter">
                    {{ $unreadNotificationsCount }}
                    {{-- {{ auth()->user()->Notifications->count() }} --}}
                </span>
            </a>
            {{-- @foreach ($notifications as $notification)
                @php
                    $data = json_decode($notification->data, true);
                    $clientId = isset($data['client_id']) ? $data['client_id'] : null;
                @endphp
                {{ $clientId }}
                @dd($notification->created_at)
            @endforeach --}}

            <!-- Dropdown - Alerts -->
            <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in"
                aria-labelledby="alertsDropdown">
                <h6 class="dropdown-header">
                    Notifications
                </h6>
                {{-- @dd($notifications) --}}

                @foreach ($notifications as $notification)
                    @php
                        $data = json_decode($notification->data, true);
                        $notificationType = $data['type'] ?? (isset($data['client_id']) ? 'client_request' : 'general');
                        $notificationUrl = '#';
                        $notificationLabel = 'You have a new notification.';
                        $notificationIcon = 'fa-bell';
                        $notificationTone = 'bg-primary';

                        if (in_array($notificationType, ['schedule_published', 'shift_confirmation_reminder'], true)) {
                            $notificationUrl = route('employee.shifts.index', array_filter(['month' => isset($data['date']) ? substr($data['date'], 0, 7) : null, 'day' => $data['date'] ?? null]));
                            $notificationLabel = $notificationType === 'shift_confirmation_reminder' ? 'A shift is waiting for your confirmation.' : 'A shift was published to your schedule.';
                            $notificationIcon = $notificationType === 'shift_confirmation_reminder' ? 'fa-bell' : 'fa-calendar-check';
                            $notificationTone = $notificationType === 'shift_confirmation_reminder' ? 'bg-warning' : 'bg-success';
                        } elseif ($notificationType === 'shift_confirmation_response') {
                            $notificationUrl = route('manager.shifts.confirmations');
                            $notificationLabel = ($data['employee'] ?? 'An employee').' '.($data['status'] ?? 'responded to').' a shift.';
                            $notificationIcon = 'fa-clipboard-check';
                            $notificationTone = ($data['status'] ?? '') === 'declined' ? 'bg-danger' : 'bg-success';
                        } elseif (isset($data['leave_request_id'])) {
                            $notificationUrl = route('employee.leave.index');
                            $notificationLabel = 'Your leave request is now '.strtolower($data['status'] ?? 'updated').'.';
                            $notificationIcon = 'fa-plane-departure';
                        } elseif (isset($data['client_id'])) {
                            $notificationUrl = route('client.edit', ['client' => $data['client_id']]);
                            $notificationLabel = 'A new client request has been submitted.';
                            $notificationIcon = 'fa-file-alt';
                        }
                    @endphp
                    {{-- @dd($notifications); --}}
                    <a class="dropdown-item d-flex align-items-center"
                        href="{{ $notificationUrl }}">
                        <div class="mr-3">
                            <div class="icon-circle {{ $notificationTone }}">
                                <i class="fas {{ $notificationIcon }} text-white"></i>
                            </div>
                        </div>
                        <div>
                            <div class="small text-gray-500">
                                {{ date('F j, Y \a\t h:i A', strtotime($notification->created_at)) }}
                            </div>
                            <span class="font-weight-bold">{{ $notificationLabel }}</span>
                        </div>
                    </a>
                @endforeach
                {{-- <a class="dropdown-item d-flex align-items-center" href="#">
                    <div class="mr-3">
                        <div class="icon-circle bg-success">
                            <i class="fas fa-donate text-white"></i>
                        </div>
                    </div>
                    <div>
                        <div class="small text-gray-500">December 7, 2019</div>
                        $290.29 has been deposited into your account!
                    </div>
                </a>
                <a class="dropdown-item d-flex align-items-center" href="#">
                    <div class="mr-3">
                        <div class="icon-circle bg-warning">
                            <i class="fas fa-exclamation-triangle text-white"></i>
                        </div>
                    </div>
                    <div>
                        <div class="small text-gray-500">December 2, 2019</div>
                        Spending Alert: We've noticed unusually high spending for your account.
                    </div>
                </a> --}}
                <a class="dropdown-item text-center small text-gray-500" href="#">View all
                    notifications</a>
            </div>
        </li>

        <!-- Nav Item - Messages -->
        {{-- <li class="nav-item dropdown no-arrow mx-1">
            <a class="nav-link dropdown-toggle" href="#" id="messagesDropdown" role="button"
                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-envelope fa-fw"></i>
                <!-- Counter - Messages -->
                <span class="badge badge-danger badge-counter">7</span>
            </a>
            <!-- Dropdown - Messages -->
            <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in"
                aria-labelledby="messagesDropdown">
                <h6 class="dropdown-header">
                    Message Center
                </h6>
                <a class="dropdown-item d-flex align-items-center" href="#">
                    <div class="dropdown-list-image mr-3">
                        <img class="rounded-circle" src="img/undraw_profile_1.svg" alt="...">
                        <div class="status-indicator bg-success"></div>
                    </div>
                    <div class="font-weight-bold">
                        <div class="text-truncate">Hi there! I am wondering if you can help me with a
                            problem I've been having.</div>
                        <div class="small text-gray-500">Emily Fowler · 58m</div>
                    </div>
                </a>
                <a class="dropdown-item d-flex align-items-center" href="#">
                    <div class="dropdown-list-image mr-3">
                        <img class="rounded-circle" src="img/undraw_profile_2.svg" alt="...">
                        <div class="status-indicator"></div>
                    </div>
                    <div>
                        <div class="text-truncate">I have the photos that you ordered last month, how
                            would you like them sent to you?</div>
                        <div class="small text-gray-500">Jae Chun · 1d</div>
                    </div>
                </a>
                <a class="dropdown-item d-flex align-items-center" href="#">
                    <div class="dropdown-list-image mr-3">
                        <img class="rounded-circle" src="img/undraw_profile_3.svg" alt="...">
                        <div class="status-indicator bg-warning"></div>
                    </div>
                    <div>
                        <div class="text-truncate">Last month's report looks great, I am very happy
                            with
                            the progress so far, keep up the good work!</div>
                        <div class="small text-gray-500">Morgan Alvarez · 2d</div>
                    </div>
                </a>
                <a class="dropdown-item d-flex align-items-center" href="#">
                    <div class="dropdown-list-image mr-3">
                        <img class="rounded-circle" src="https://source.unsplash.com/Mv9hjnEUHR4/60x60"
                            alt="...">
                        <div class="status-indicator bg-success"></div>
                    </div>
                    <div>
                        <div class="text-truncate">Am I a good boy? The reason I ask is because someone
                            told me that people say this to all dogs, even if they aren't good...</div>
                        <div class="small text-gray-500">Chicken the Dog · 2w</div>
                    </div>
                </a>
                <a class="dropdown-item text-center small text-gray-500" href="#">Read More
                    Messages</a>
            </div>
        </li> --}}

        <div class="topbar-divider d-none d-sm-block"></div>

        <!-- Nav Item - User Information -->
        <li class="nav-item dropdown no-arrow">
            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown"
                aria-haspopup="true" aria-expanded="false">
                <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                    @auth
                        {{ auth()->user()->name }}
                    @endauth

                </span>
                <img class="img-profile rounded-circle" src="{{ asset('img/undraw_profile.svg') }}">
            </a>
            <!-- Dropdown - User Information -->
            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                <a class="dropdown-item" href="#">
                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                    Profile
                </a>
                {{-- <a class="dropdown-item" href="#">
                    <i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i>
                    Settings
                </a>
                <a class="dropdown-item" href="#">
                    <i class="fas fa-list fa-sm fa-fw mr-2 text-gray-400"></i>
                    Activity Log
                </a> --}}
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="{{ route('logout') }}" data-toggle="modal" data-target="#logoutModal">
                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                    Logout
                </a>
            </div>
        </li>

    </ul>

</nav>
