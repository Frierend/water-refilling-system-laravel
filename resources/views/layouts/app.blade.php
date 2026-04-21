<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Mi-Gail Water') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Theme CSS -->
    <link href="{{ asset('css/mi-gail-theme.css') }}" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="{{ asset('css/custom.css') }}" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #3490dc;
            --primary-light: #6cb2eb;
            --primary-dark: #2779bd;
            --secondary-color: #38c172;
        }
        
        /* Live Clock Styles */
        .navbar-clock {
            font-size: 0.85rem;
            white-space: nowrap;
            margin-right: 15px;
            line-height: 1;
            padding: 4px 10px;
            border-radius: 12px;
            background-color: rgba(255, 255, 255, 0.15);
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            color: white;
        }
        
        /* Water drop animation */
        .water-drop {
            position: relative;
            width: 24px;
            height: 24px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 50% 50% 50% 0;
            transform: rotate(45deg);
            animation: dropPulse 2s infinite;
        }

        @keyframes dropPulse {
            0% { transform: rotate(45deg) scale(1); }
            50% { transform: rotate(45deg) scale(1.1); }
            100% { transform: rotate(45deg) scale(1); }
        }
        
        /* Navbar custom styles */
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .navbar-custom .nav-link {
            color: rgba(255, 255, 255, 0.8);
            font-weight: 600;
            padding: 0.5rem 1rem;
            transition: all 0.2s;
        }
        
        .navbar-custom .nav-link:hover {
            color: white;
            transform: translateY(-1px);
        }
        
        .navbar-custom .nav-link.active {
            color: white;
            position: relative;
        }
        
        .navbar-custom .nav-link.active:after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 1rem;
            right: 1rem;
            height: 3px;
            background-color: white;
            border-radius: 3px;
        }
        
        /* Avatar circle */
        .avatar-circle {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-weight: bold;
            letter-spacing: -0.05em;
        }
        
        @media (max-width: 991px) {
            .navbar-clock {
                margin-right: 10px;
                padding: 3px 8px;
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 767px) {
            .navbar-clock {
                display: none;
            }
        }
        
        /* Footer styles */
        footer {
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
        }
    </style>

    @yield('styles')
</head>
<body>
    <div id="app">
        <!-- Navbar -->
        <nav class="navbar navbar-expand-md navbar-custom navbar-dark sticky-top">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center" href="{{ url('/dashboard') }}">
                    <div class="water-drop me-2"></div>
                    <span>Mi-Gail Water</span>
                </a>
                
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- Left Side Of Navbar -->
                    <ul class="navbar-nav me-auto">
                        @auth
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                                    <i class="bi bi-speedometer2 me-1"></i> Dashboard
                                </a>
                            </li>
                            
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('orders.*') ? 'active' : '' }}" href="{{ route('orders.index') }}">
                                    <i class="bi bi-receipt me-1"></i> Sales
                                </a>
                            </li>
                            
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('deliveries.*') ? 'active' : '' }}" href="{{ route('deliveries.index') }}">
                                    <i class="bi bi-truck me-1"></i> Deliveries
                                </a>
                            </li>
                            
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('inventory.*') ? 'active' : '' }}" href="{{ route('inventory.index') }}">
                                    <i class="bi bi-box-seam me-1"></i> Inventory
                                </a>
                            </li>
                            
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('customers.*') ? 'active' : '' }}" href="{{ route('customers.index') }}">
                                    <i class="bi bi-people me-1"></i> Customers
                                </a>
                            </li>
                            
                            @if(auth()->user()->isOwner() || auth()->user()->isDelivery() || auth()->user()->isHelper())
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-bar-chart me-1"></i> Reports
                                </a>
                                <ul class="dropdown-menu shadow-sm">
                                    <li><a class="dropdown-item" href="{{ route('reports.sales') }}">
                                        <i class="bi bi-cash me-2"></i>Sales Report
                                        
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('reports.delivery') }}">
                                        <i class="bi bi-truck me-2"></i>Delivery Report
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('reports.customer') }}">
                                        <i class="bi bi-people me-2"></i>Customer Report
                                    </a></li>
                                    <li><a class="dropdown-item" href="{{ route('reports.inventory') }}">
                                        <i class="bi bi-box-seam me-2"></i>Inventory Report
                                    </a></li>
                                </ul>
                            </li>
                            @endif
                        @endauth
                    </ul>

                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ms-auto d-flex align-items-center">
                        <!-- Live Clock - Manila Time (Compact Version) -->
                        <li class="navbar-clock d-flex align-items-center">
                            <i class="bi bi-clock me-1"></i>
                            <span id="live-clock"></span>
                        </li>
                        
                        <!-- Authentication Links -->
                        @guest
                            @if (Route::has('login'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('login') }}">
                                        <i class="bi bi-box-arrow-in-right me-1"></i> {{ __('Login') }}
                                    </a>
                                </li>
                            @endif
                        @else
                            <li class="nav-item dropdown">
                                <a id="navbarDropdown" class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                    <div class="avatar-circle bg-white text-primary me-2">
                                        {{ substr(Auth::user()->name, 0, 1) }}
                                    </div>
                                    <span>{{ Auth::user()->name }}</span>
                                </a>

                                <div class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="navbarDropdown">
                                    <a class="dropdown-item" href="#">
                                        <i class="bi bi-person me-2"></i> My Profile
                                    </a>
                                    
                                    <a class="dropdown-item" href="{{ route('logout') }}"
                                       onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                                        <i class="bi bi-box-arrow-right me-2"></i> {{ __('Logout') }}
                                    </a>

                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </div>
                            </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Flash Messages -->
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <div class="container">
                <i class="bi bi-check-circle me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        @endif

        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <div class="container">
                <i class="bi bi-exclamation-triangle me-2"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        @endif

        <!-- Main Content -->
        <main class="py-4">
            <div class="container">
                @yield('content')
            </div>
        </main>
        
        <!-- Footer -->
        <footer class="py-4 mt-4 border-top bg-white">
            <div class="container">
                <div class="row">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <div class="water-drop me-2"></div>
                            <h5 class="mb-0">Mi-Gail Water Refilling Station</h5>
                        </div>
                        <p class="text-muted mb-0 mt-2">Providing clean, safe water since 2020</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p class="mb-0 text-muted">© {{ date('Y') }} Mi-Gail Water System</p>
                        <p class="mb-0 text-muted">All rights reserved</p>
                    </div>
                </div>
            </div>
        </footer>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap dropdowns
            var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
            dropdownElementList.map(function (dropdownToggleEl) {
                return new bootstrap.Dropdown(dropdownToggleEl);
            });
            
            // Handle custom dropdowns for tables
            const customDropdownToggles = document.querySelectorAll('.custom-dropdown-toggle');
            
            customDropdownToggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    
                    // Close all other open dropdowns first
                    document.querySelectorAll('.custom-dropdown-menu.show').forEach(menu => {
                        if (menu !== this.nextElementSibling) {
                            menu.classList.remove('show');
                        }
                    });
                    
                    // Toggle current dropdown
                    const menu = this.nextElementSibling;
                    menu.classList.toggle('show');
                });
            });
            
            // Close dropdowns when clicking elsewhere
            document.addEventListener('click', function(e) {
                if (!e.target.matches('.custom-dropdown-toggle') && !e.target.closest('.custom-dropdown-menu')) {
                    document.querySelectorAll('.custom-dropdown-menu.show').forEach(menu => {
                        menu.classList.remove('show');
                    });
                }
            });
            
            // Handle delete confirmations
            const deleteForms = document.querySelectorAll('.delete-form');
            if (deleteForms) {
                deleteForms.forEach(form => {
                    const deleteBtn = form.querySelector('.delete-btn');
                    if (deleteBtn) {
                        deleteBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            if (confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                                form.submit();
                            }
                        });
                    }
                });
            }
            
            // Live clock functionality - Manila Time (UTC+8) - Compact Version
            function updateClock() {
                const options = { 
                    timeZone: 'Asia/Manila',
                    hour: '2-digit', 
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                };
                
                const now = new Date();
                const clockElement = document.getElementById('live-clock');
                
                if (clockElement) {
                    clockElement.textContent = now.toLocaleTimeString('en-PH', options);
                }
            }
            
            // Initialize and start clock if element exists
            if (document.getElementById('live-clock')) {
                updateClock();
                setInterval(updateClock, 1000);
            }
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>
    
    @yield('scripts')
</body>
</html>