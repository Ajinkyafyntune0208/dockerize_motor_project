   <!-- partial:partials/_footer.html -->
   <footer class="footer">
       <div class="d-sm-flex justify-content-center justify-content-sm-between">
           <!-- <span class="text-muted text-center text-sm-left d-block d-sm-inline-block"><a href="https://www.bootstrapdash.com/" target="_blank">{{-- config('app.name') --}}</a>.</span> -->
           <span class="float-none float-sm-left d-block mt-1 mt-sm-0 text-center">Latest Build Timestamp : 
           @if (file_exists('./../.git/FETCH_HEAD'))
                {{ date('dS F Y h:i:s A', filemtime('./../.git/FETCH_HEAD')) }} ( Branch : {{ trim(str_replace("'","",explode(' ',file('./../.git/FETCH_HEAD')[0])[1])) }} )
            @else
                Time Stamp Not Found
            @endif
            </span>
           <span class="float-none float-sm-right d-block mt-1 mt-sm-0 text-center">Copyright © {{ date('Y') }}. All rights reserved.</span>
       </div>
   </footer>
   <!-- partial -->