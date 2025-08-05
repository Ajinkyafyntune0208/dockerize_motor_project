$(document).ready(function() {
    var activeTable = $("#active-data-table").DataTable({
        "responsive": true,
        "lengthChange": true,
        "autoWidth": false,
        "scrollX": false,
    });

    var inactiveTable = $("#inactive-data-table").DataTable({
        "responsive": true,
        "lengthChange": true,
        "autoWidth": false,
        "scrollX": false,
    });

    $('#active-data-table tbody, #inactive-data-table tbody').on('click', '.view-ic', function () {
        const icId = $(this).data('id');
        let requestUrl = appUrl + '/admin/ic-configuration/premium-calculation-configurator/' + icId;
        $.ajax({
            url: requestUrl,
            method: 'GET',
            success: function(data) { 
                let accordionContent = '';
                const validLabelGroups = data.labelGroups;

                if (typeof data.results === 'object' && !Array.isArray(data.results)) {
                    $.each(data.results, function(labelGroup, items) {
                        if (validLabelGroups.includes(labelGroup)) {
                            if (Array.isArray(items)) {
                                accordionContent += `
                                <div class="card">
                                    <div class="card-header p-0 pl-2" id="heading-${labelGroup}">
                                        <h5 class="mb-0">
                                            <button class="btn btn-link pt-1 pb-1" data-toggle="collapse" data-target="#collapse-${labelGroup}" aria-expanded="false" aria-controls="collapse-${labelGroup}" style="width: 100%; text-align: left;">
                                                ${labelGroup}
                                            </button>
                                        </h5>
                                    </div>

                                    <div id="collapse-${labelGroup}" class="collapse" aria-labelledby="heading-${labelGroup}" data-parent="#accordionIC">
                                        <div class="card-body">
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>Label Name</th>
                                                        <th>Calculation Type</th>
                                                        <th>Value</th>
                                                    </tr>
                                                </thead>
                                                <tbody>`;

                                if (items.length > 0) {
                                    $.each(items, function(index, item) {
                                        let type = '';
                                        let value = '';

                                        if (item.attribute_name) {
                                            type = 'Attribute';
                                            value = `<span class="text-ellipses" data-toggle="tooltip" title="${item.attribute_trail || ''}">${item.attribute_name}${item.attribute_trail ? ` (${item.attribute_trail})` : ''}</span>`;

                                        } else if (item.formula_name) {
                                            type = 'Formula';
                                            value = item.formula_name;
                                        } else if (item.custom_val) {
                                            type = 'Custom Value';
                                            value = item.custom_val;
                                        } else {
                                            type = 'Not Applicable';
                                            value = '-';
                                        }

                                        accordionContent += `
                                        <tr>
                                            <td>${item.label_name}</td>
                                            <td>${type}</td>
                                            <td>${value}</td>
                                        </tr>`;
                                    });
                                } else {
                                    accordionContent += `
                                    <tr>
                                        <td colspan="3">N/A</td>
                                    </tr>`;
                                }

                                accordionContent += `
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>`;
                            } else {
                                console.error(`Expected items to be an array but got:`, items);
                            }
                        }
                    });
                    $('#accordionIC').html(accordionContent);
                    $('#icModal').modal('show');

                    $('[data-toggle="tooltip"]').tooltip();
                } else {
                    console.error('Expected data to be an object:', data);
                }
            },
            error: function(xhr) {
                console.error('Error fetching data:', xhr);
            }
        });
    });

    $('#modal-close').on('click', function () {
        $('#icModal').modal('hide');
    });
    $('#integrationType, #segment, #businessType, #overrideexists').prop('disabled', true);

    $('#active-data-table tbody, #inactive-data-table tbody').on('click', '.clone-ic', function () {
        const icId = $(this).data('id');
        $('#cloneICForm').data('id', icId);
        $('#icCloneModal').modal('show');
    });
    $('#cloneICButton').on('click', function () {
        const icSlug = $('#cloneICForm').data('id');
        const icAlias = $('#combinedICIntegration').val();
        const integrationType = $('#integrationType').val();
        const segment = $('#segment').val();
        const businessType = $('#businessType').val();
        const overrideExists = $('#overrideexists').val();
        const token = $("[name='_token']").val();     
        if (!icAlias || !integrationType || !segment || !businessType || !overrideExists) {
            alert('Please fill all the fields.');
            return;
        }
    
        $.ajax({
            url: cloneUrl,
            method: 'POST',           
            data: {
                _token: token,
                ic_slug: icSlug,
                ic_alias: icAlias,
                integration_type: integrationType,
                segment: segment,
                business_type: businessType,
                override_exists: overrideExists
            },
            success: function(response) {
                if (response.status) {
                    alert("Data Successfully Cloned");
                    $('#icCloneModal').modal('hide');
                    // location.reload();
                } else {
                    alert('Cloning failed: ' + response.message);
                }
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred while cloning the IC.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                alert(errorMessage);
            }
        });
    });
    $('#combinedICIntegration').change(function () {
        const selectedAlias = $(this).val();

        $('#integrationType, #segment, #businessType, #overrideexists').prop('disabled', true).val('');

        $.ajax({
            url: cloneUrl,
            method: 'POST',
            data: {
                _token: $("[name='_token']").val(),
                ic_alias: selectedAlias,
                dynamicData: 'fetch_data'
            },
            success: function (response) {
                $('#integrationType').prop('disabled', false).empty();
                $('#integrationType').append('<option value="" disabled selected>Select Integration Type</option>');

                $.each(response.integrationTypes, function (index, type) {
                    $('#integrationType').append('<option value="' + index + '">' + type + '</option>');
                });

                $('#segment, #businessType, #overrideexists').prop('disabled', true).val('');
            }
        });
    });

    $('#integrationType').change(function () {
        const selectedAlias = $('#combinedICIntegration').val();
        const selectedType = $(this).val();

        $.ajax({
            url: cloneUrl,
            method: 'POST',
            data: {
                _token: $("[name='_token']").val(),
                ic_alias: selectedAlias,
                integration_type: selectedType,
                dynamicData: 'fetch_data'
            },
            success: function (response) {
                $('#segment').prop('disabled', false).empty();
                $('#segment').append('<option value="" disabled selected>Select Segment</option>');

                $.each(response.segments, function (index, segment) {
                    $('#segment').append('<option value="' + segment + '">' + segment + '</option>');
                });

                $('#businessType, #overrideexists').prop('disabled', true).val('');
            }
        });
    });

    $('#segment').change(function () {
        const selectedAlias = $('#combinedICIntegration').val();
        const selectedType = $('#integrationType').val();
        const selectedSegment = $(this).val();

        $.ajax({
            url: cloneUrl,
            method: 'POST',
            data: {
                _token: $("[name='_token']").val(),
                ic_alias: selectedAlias,
                integration_type: selectedType,
                segment: selectedSegment,
                dynamicData: 'fetch_data'
            },
            success: function (response) {
                $('#businessType').prop('disabled', false).empty();
                $('#businessType').append('<option value="" disabled selected>Select Business Type</option>');

                $.each(response.businessTypes, function (index, businessType) {
                    $('#businessType').append('<option value="' + businessType + '">' + businessType + '</option>');
                });

                $('#overrideexists').prop('disabled', true).val('');
            }
        });
    });

    $('#businessType').change(function () {
        $('#overrideexists').prop('disabled', false);
    });


    $('#icCloneModal').on('hidden.bs.modal', function () {
        $('#cloneICForm')[0].reset();
        $('#integrationType, #segment, #businessType, #overrideexists').prop('disabled', true);
    });

    document.querySelector('.import-form').addEventListener('submit', (form) => {
        form.preventDefault();

        const formData = new FormData(form.currentTarget);

        postFetch(importUrl, formData).then((res) => {
            let result = res.response;
            alert(result.message)
            if (result.status == true) {
                location.reload()
            }
        })
    })
});

async function postFetch(url, data) {
    const response = await fetch(url, {
        body: data,
        method: 'POST',
        headers:{
            'X-CSRF-TOKEN' : document.querySelector('[name="csrf-token"]').getAttribute('content')
        }
    });
    response.response = await response.json();
    return response;
}