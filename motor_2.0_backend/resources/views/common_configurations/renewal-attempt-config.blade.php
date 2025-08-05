<div class="card mt-3">
    <div class="card-body">
        <h5 class="card-title">Renewal Attempts Configurator</h5>
        @if (session('status') && session('formType') == 'renewalAttemptConfig')
            <div class="alert alert-{{ session('class') }}">
                {{ session('status') }}
            </div>
        @endif
        <form action="{{ route('admin.common-config-save') }}" method="POST" class="mt-3 renewalAttemptConfig">
            @csrf @method('POST')
            <input type="hidden" value="renewalAttemptConfig" name="formType">
            <div class="row">
                <div class="col-md-8 col-lg-7 col-xl-6">
                    <div class="input-group">
                        <span class="input-group-text">Number of Attempts for IC Fetch Api :</span>
                        <input class="form-control fetchtotalAttempts" type="number" name="data[global.renewal.icFetch.totalAttempts][value]" value="{{ $allData['global.renewal.icFetch.totalAttempts'] ?? '' }}">
                        <input type="hidden" name="data[global.renewal.icFetch.totalAttempts][key]" value="global.renewal.icFetch.totalAttempts">
                        <input type="hidden" name="data[global.renewal.icFetch.totalAttempts][label]" value ="Number of Attempts for IC Fetch Api">
                    </div>
                </div>
                <div class="col-12 mt-3">
                    <h6 class=""><u>Interval configuration : </u></h6>
                    <table class="table table-striped col-sm-6 apiConfigClass mt-1 fetchIterationTable">
                        <thead>
                            <tr>
                                <th>Iterations</th>
                                <th>Intervals (in minutes)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @for($i = 1; $i<=($allData['global.renewal.icFetch.totalAttempts'] ?? 0); $i++)
                                <tr>
                                    <td>
                                        <span class="ml-2">Iteration - {{$i}}</span>
                                    </td>
                                    <td>
                                        <input type="hidden" name="data[global.renewal.icFetch.interval.iteration_{{$i}}][key]"
                                            value="global.renewal.icFetch.interval.iteration_{{$i}}">
                                        <input type="hidden" name="data[global.renewal.icFetch.interval.iteration_{{$i}}][label]"
                                            value="IC fetch Timeout for Iteration {{$i}}">
                                        <input required type="number" name="data[global.renewal.icFetch.interval.iteration_{{$i}}][value]" value="{{($allData['global.renewal.icFetch.interval.iteration_'.$i] ?? 0)}}" {{$i == 1 ? 'readonly' : ''}}>
                                    </td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            </div>


            <div class="row mt-5">
                <div class="col-md-8 col-lg-7 col-xl-6">
                    <div class="input-group">
                        <span class="input-group-text">Number of Attempts for Vahan Api :</span>
                        <input class="form-control vahantotalAttempts" type="number" name="data[global.renewal.vahan.totalAttempts][value]" value="{{ $allData['global.renewal.vahan.totalAttempts'] ?? '' }}">
                        <input type="hidden" name="data[global.renewal.vahan.totalAttempts][key]" value="global.renewal.vahan.totalAttempts">
                        <input type="hidden" name="data[global.renewal.vahan.totalAttempts][label]" value ="Number of Attempts for Vahan Api">
                    </div>
                </div>
                <div class="col-12 mt-3">
                    <h6 class=""><u>Interval configuration : </u></h6>
                    <table class="table table-striped col-sm-6 apiConfigClass mt-1 vahanIterationTable">
                        <thead>
                            <tr>
                                <th>Iterations</th>
                                <th>Intervals (in minutes)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @for($i = 1; $i<=($allData['global.renewal.vahan.totalAttempts'] ?? 0); $i++)
                                <tr>
                                    <td>
                                        <span class="ml-2">Iteration - {{$i}}</span>
                                    </td>
                                    <td>
                                        <input type="hidden" name="data[global.renewal.vahan.interval.iteration_{{$i}}][key]"
                                            value="global.renewal.vahan.interval.iteration_{{$i}}">
                                        <input type="hidden" name="data[global.renewal.vahan.interval.iteration_{{$i}}][label]"
                                            value="Vahan fetch Timeout for Iteration {{$i}}">
                                        <input required type="number" name="data[global.renewal.vahan.interval.iteration_{{$i}}][value]" value="{{($allData['global.renewal.vahan.interval.iteration_'.$i] ?? 0)}}" {{$i == 1 ? 'readonly' : ''}}>
                                    </td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-sm-12">
                <div class="form-group">
                    <label></label>
                    <div class="d-flex flex-row-reverse">
                        <button type="submit" class="btn btn-outline-primary">Submit</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
    document.querySelector('.fetchtotalAttempts').addEventListener('keyup', (e) => {
        let table = document.querySelector('.fetchIterationTable');
        let tableBody = document.querySelector('.fetchIterationTable tbody');
        let allTr = document.querySelectorAll('.fetchIterationTable tbody tr')
        if (e.target.value != allTr.length) {
            // Get the desired number of rows from the input value
            let desiredRowCount = parseInt(e.target.value);
            if (desiredRowCount < allTr.length) {
               
                for (let i = allTr.length - 1; i >= desiredRowCount; i--) {
                    tableBody.removeChild(allTr[i]);
                }
            } else if (desiredRowCount > allTr.length) {
                for (let i = allTr.length; i < desiredRowCount; i++) {
                    let newRow = tableBody.insertRow();
                    let cell1 = newRow.insertCell();
                    let cell2 = newRow.insertCell();

                    // Customize the content of the new cells if needed
                    cell1.innerHTML = `<span class="ml-2">Iteration - ${i+1}</span>`;

                    let cell2Data = `<input type="hidden" name="data[global.renewal.icFetch.interval.iteration_${i+1}][key]" value="global.renewal.icFetch.interval.iteration_${i+1}">
                    <input type="hidden" name="data[global.renewal.icFetch.interval.iteration_${i+1}][label]" value="IC fetch Timeout for Iteration ${i+1}">
                    <input required type="number" name="data[global.renewal.icFetch.interval.iteration_${i+1}][value]"`;
                    if (i == 0) {
                        cell2Data+=`readonly>`;
                    } else {
                        cell2Data+=`>`;
                    }
                    cell2.innerHTML = cell2Data;

                    cell2.innerHTML = cell2Data;
                }
            }
        }
        if (!e.target.value) {
            for (let i = 0; i < allTr.length; i++) {
                tableBody.removeChild(allTr[i]);
            }
        }
        
    })

    document.querySelector('.vahantotalAttempts').addEventListener('keyup', (e) => {
        let table = document.querySelector('.vahanIterationTable');
        let tableBody = document.querySelector('.vahanIterationTable tbody');
        let allTr = document.querySelectorAll('.vahanIterationTable tbody tr')
        if (e.target.value != allTr.length) {
            // Get the desired number of rows from the input value
            let desiredRowCount = parseInt(e.target.value);
            if (desiredRowCount < allTr.length) {
               
                for (let i = allTr.length - 1; i >= desiredRowCount; i--) {
                    tableBody.removeChild(allTr[i]);
                }
            } else if (desiredRowCount > allTr.length) {
                for (let i = allTr.length; i < desiredRowCount; i++) {
                    let newRow = tableBody.insertRow();
                    let cell1 = newRow.insertCell();
                    let cell2 = newRow.insertCell();
                    // Customize the content of the new cells if needed
                    cell1.innerHTML = `<span class="ml-2">Iteration - ${i+1}</span>`;
                    let cell2Data = `<input type="hidden" name="data[global.renewal.vahan.interval.iteration_${i+1}][key]" value="global.renewal.vahan.interval.iteration_${i+1}">
                    <input type="hidden" name="data[global.renewal.vahan.interval.iteration_${i+1}][label]" value="IC fetch Timeout for Iteration ${i+1}">
                    <input required type="number" name="data[global.renewal.vahan.interval.iteration_${i+1}][value]"`;

                    if (i == 0) {
                        cell2Data+=`readonly>`;
                    } else {
                        cell2Data+=`>`;
                    }
                    cell2.innerHTML = cell2Data;
                }
            }
        }
        if (!e.target.value) {
            for (let i = 0; i < allTr.length; i++) {
                tableBody.removeChild(allTr[i]);
            }
        }
        
    })
</script>