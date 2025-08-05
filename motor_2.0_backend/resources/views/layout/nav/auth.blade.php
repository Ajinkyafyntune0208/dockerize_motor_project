 <style>
  .brand-style{
    color:#27367f !important;
  }
 .login-text{
    color: var(--bs-primary) !important;
    font-weight: 500;
 }
  .burger-tab{
    background: #ffffff !important;
    border-radius: 10px 10px 0 0;
  }
  @media (max-width: 991px){
    .navbar .navbar-brand-wrapper {
      background: #F4F5F7 !important;
      border-radius: 0px !important;
    }
  }
  .searchbox{
    position: relative;
    transition: all 1s;
    width: 50px;
    height: 50px;
    background: white;
    box-sizing: border-box;
    border-radius: 25px;
    border: 4px solid white;
    padding: 5px;
  }

  .searchinput{
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;;
    height: 42.5px;
    line-height: 30px;
    outline: 0;
    border: 0;
    display: none;
    font-size: 1em;
    border-radius: 20px;
    padding: 0 20px;
  }

.faSearch{
  box-sizing: border-box;
  padding: 10px;
  width: 42.5px;
  height: 42.5px;
  position: absolute;
  top: 0;
  right: 0;
  border-radius: 50%;
  color: #07051a;
  text-align: center;
  font-size: 1.2em;
  transition: all 1s;
}

  .searchbox:hover,
  .searchbox:valid{
    width: 200px;
    cursor: pointer;
  }

  .searchbox:hover .searchinput,
  .searchbox:valid .searchinput{
    display: block;
  }

  .searchbox:hover .faSearch,
  .searchbox:valid .faSearch{
    background: var(--bs-primary);
    /* background: #07051a; */
    color: white;
  }
  .searchclear{
    display: none;
    position: absolute;
    top: 70px;
    bottom:0;
    left: 0;
    right: 0;
    font-size: 20px;
    color: white;
    text-align: center; 
    width: 100%;
  }

  .searchbox:valid .searchclear {
    display: block;
  }
 </style>
 <!-- partial:partials/_navbar.html -->
 <nav class="navbar default-layout col-lg-12 col-12 p-0 fixed-top d-flex align-items-top flex-row">
   <div class="burger-tab text-center navbar-brand-wrapper d-flex align-items-center justify-content-start">
     <div class="me-3">
       <button class="navbar-toggler navbar-toggler align-self-center" id="toggleButton" type="button" data-bs-toggle="minimize">
         <span class="icon-menu"></span>
       </button>
     </div>
     <div>
       <a class="navbar-brand brand-logo" href="{{route('admin.dashboard.index')}}">{{strtoupper(config('constants.motorConstant.SMS_FOLDER')) }}
       </a>
       {{-- <a class="navbar-brand brand-logo-mini" href="{{route('admin.dashboard.index')}}">
         {{ config('app.name') }}
         <!-- <img src="{{ asset('admin/images/logo-mini.svg') }}" alt="logo" /> -->
       </a> --}}
     </div>
   </div>
   <div class="navbar-menu-wrapper d-flex align-items-top">
     <ul class="navbar-nav">
       <li class="nav-item font-weight-semibold d-none d-lg-block ms-0">
         <h1 class="welcome-text"><span id="welcome-text">Good Morning,</span>  <span class="text-black fw-bold">{{ auth()->user()->name }}</span></h1>
       </li>
     </ul>
     <ul class="navbar-nav ms-auto">
      <li class="searchbox">
        <input type="search" class="searchinput" id="searchInput" placeholder="Search..." title="Search Menu link" autocomplete="off">
        <i class="fa fa-search faSearch"></i>
        <a class="searchclear" href="javascript:void(0)" id="clear-btn">Clear</a>
      </li>
       {{-- <li class="nav-item dropdown d-none d-lg-block">
            <a class="nav-link dropdown-bordered dropdown-toggle dropdown-toggle-split" id="messageDropdown" href="#" data-bs-toggle="dropdown" aria-expanded="false"> Select Category </a>
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown preview-list pb-0" aria-labelledby="messageDropdown">
              <a class="dropdown-item py-3" >
                <p class="mb-0 font-weight-medium float-left">Select category</p>
              </a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item preview-item">
                <div class="preview-item-content flex-grow py-2">
                  <p class="preview-subject ellipsis font-weight-medium text-dark">Bootstrap Bundle </p>
                  <p class="fw-light small-text mb-0">This is a Bundle featuring 16 unique dashboards</p>
                </div>
              </a>
              <a class="dropdown-item preview-item">
                <div class="preview-item-content flex-grow py-2">
                  <p class="preview-subject ellipsis font-weight-medium text-dark">Angular Bundle</p>
                  <p class="fw-light small-text mb-0">Everything you’ll ever need for your Angular projects</p>
                </div>
              </a>
              <a class="dropdown-item preview-item">
                <div class="preview-item-content flex-grow py-2">
                  <p class="preview-subject ellipsis font-weight-medium text-dark">VUE Bundle</p>
                  <p class="fw-light small-text mb-0">Bundle of 6 Premium Vue Admin Dashboard</p>
                </div>
              </a>
              <a class="dropdown-item preview-item">
                <div class="preview-item-content flex-grow py-2">
                  <p class="preview-subject ellipsis font-weight-medium text-dark">React Bundle</p>
                  <p class="fw-light small-text mb-0">Bundle of 8 Premium React Admin Dashboard</p>
                </div>
              </a>
            </div>
          </li>--}}
       {{--<li class="nav-item d-none d-lg-block">
            <div id="datepicker-popup" class="input-group date datepicker navbar-date-picker">
              <span class="input-group-addon input-group-prepend border-right">
                <span class="icon-calendar input-group-text calendar-icon"></span>
              </span>
              <input type="text" class="form-control">
            </div>
          </li>--}}
       {{-- <li class="nav-item">
            <form class="search-form" action="#">
              <i class="icon-search"></i>
              <input type="search" class="form-control" placeholder="Search Here" title="Search here">
            </form>
          </li>--}}
       {{-- <li class="nav-item dropdown">
            <a class="nav-link count-indicator" id="notificationDropdown" href="#" data-bs-toggle="dropdown">
              <i class="icon-mail icon-lg"></i>
            </a>
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown preview-list pb-0" aria-labelledby="notificationDropdown">
              <a class="dropdown-item py-3 border-bottom">
                <p class="mb-0 font-weight-medium float-left">You have 4 new notifications </p>
                <span class="badge badge-pill badge-primary float-right">View all</span>
              </a>
              <a class="dropdown-item preview-item py-3">
                <div class="preview-thumbnail">
                  <i class="mdi mdi-alert m-auto text-primary"></i>
                </div>
                <div class="preview-item-content">
                  <h6 class="preview-subject fw-normal text-dark mb-1">Application Error</h6>
                  <p class="fw-light small-text mb-0"> Just now </p>
                </div>
              </a>
              <a class="dropdown-item preview-item py-3">
                <div class="preview-thumbnail">
                  <i class="mdi mdi-settings m-auto text-primary"></i>
                </div>
                <div class="preview-item-content">
                  <h6 class="preview-subject fw-normal text-dark mb-1">Settings</h6>
                  <p class="fw-light small-text mb-0"> Private message </p>
                </div>
              </a>
              <a class="dropdown-item preview-item py-3">
                <div class="preview-thumbnail">
                  <i class="mdi mdi-airballoon m-auto text-primary"></i>
                </div>
                <div class="preview-item-content">
                  <h6 class="preview-subject fw-normal text-dark mb-1">New user registration</h6>
                  <p class="fw-light small-text mb-0"> 2 days ago </p>
                </div>
              </a>
            </div>
          </li> --}}
       {{--<li class="nav-item dropdown"> 
            <a class="nav-link count-indicator" id="countDropdown" href="#" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="icon-bell"></i>
              <span class="count"></span>
            </a>
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown preview-list pb-0" aria-labelledby="countDropdown">
              <a class="dropdown-item py-3">
                <p class="mb-0 font-weight-medium float-left">You have 7 unread mails </p>
                <span class="badge badge-pill badge-primary float-right">View all</span>
              </a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item preview-item">
                <div class="preview-thumbnail">
                  <img src="images/faces/face10.jpg" alt="image" class="img-sm profile-pic">
                </div>
                <div class="preview-item-content flex-grow py-2">
                  <p class="preview-subject ellipsis font-weight-medium text-dark">Marian Garner </p>
                  <p class="fw-light small-text mb-0"> The meeting is cancelled </p>
                </div>
              </a>
              <a class="dropdown-item preview-item">
                <div class="preview-thumbnail">
                  <img src="images/faces/face12.jpg" alt="image" class="img-sm profile-pic">
                </div>
                <div class="preview-item-content flex-grow py-2">
                  <p class="preview-subject ellipsis font-weight-medium text-dark">David Grey </p>
                  <p class="fw-light small-text mb-0"> The meeting is cancelled </p>
                </div>
              </a>
              <a class="dropdown-item preview-item">
                <div class="preview-thumbnail">
                  <img src="images/faces/face1.jpg" alt="image" class="img-sm profile-pic">
                </div>
                <div class="preview-item-content flex-grow py-2">
                  <p class="preview-subject ellipsis font-weight-medium text-dark">Travis Jenkins </p>
                  <p class="fw-light small-text mb-0"> The meeting is cancelled </p>
                </div>
              </a>
            </div>
          </li>--}}
       <li class="nav-item dropdown d-none d-lg-block user-dropdown">
         <a class="nav-link d-flex flex-column align-items-center login-text" id="UserDropdown" href="#" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fa fa-user-circle text-black"></i>
            {{ auth()->user()->name }}
         </a>
         <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="UserDropdown">
           <div class="dropdown-header text-center">
             <!-- <img class="img-md rounded-circle" src="images/faces/face8.jpg" alt="Profile image"> -->
             <p class="mb-1 mt-3 font-weight-semibold">{{ auth()->user()->name }}</p>
             <p class="fw-light text-muted mb-0">{{ auth()->user()->email }}</p>
           </div>
           <a class="dropdown-item bg-info" onclick="document.getElementById('logout').submit();"><i class="dropdown-item-icon mdi mdi-power text-primary me-2"></i>Sign Out</a>
         </div>
       </li>
     </ul>
     <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-bs-toggle="offcanvas">
       <span class="mdi mdi-menu"></span>
     </button>
   </div>
 </nav>
 <script>
  const clearInput = () => {
    const input = document.getElementsByTagName("input")[0];
    input.value = "";
  }

  const clearBtn = document.getElementById("clear-btn");
  clearBtn.addEventListener("click", clearInput);
 </script>