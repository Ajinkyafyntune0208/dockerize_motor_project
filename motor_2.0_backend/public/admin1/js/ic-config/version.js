$(document).ready(function() {
    function initDataTableWithFilters(tableId) {
        if ($.fn.DataTable.isDataTable(tableId)) {
            $(tableId).DataTable().destroy();
        }

        var table = $(tableId).DataTable({
            info: true,
            dom: 'lPfBrtip',
            pageLength: 10,
            buttons: [],
            columnDefs: [{
                    orderable: true,
                    // targets: [0, 2]
                }, // Keep these columns sortable
                {
                    orderable: false,
                    targets: [1,2,3,4,5,6,7 , 8 , 9]
                } // Disable sorting for columns 5 and 6
            ],
            order: [
                [0, 'asc']
            ],
            language: {
                paginate: {
                    next: '<i class="fas fa-angle-right"></i>',
                    previous: '<i class="fas fa-angle-left"></i>'
                },
                search: '<i class="fas fa-search"></i>',
            }
        });

        var header = $(tableId + ' thead tr');
        header.find('th').each(function(index) {
            if (index != 0 && index != 8 && index != 9 ) {
                var column = table.column(index);
                if (index === 1 || index === 2 || index === 3 || index === 4 || index === 5 || index === 6 || index === 7 ) {
                    var input = $('<input type="text" class="form-control form-control-sm" placeholder="Search"/>')
                        .appendTo($(this))
                        .on('keyup', function(event) {
                            var value = this.value.trim();
                            if (event.key === 'Enter') {
                                if (value === '') {
                                    var value = this.value.trim();
                                    column.search('').draw();
                                } else {
                                    column.search(value).draw();
                                    table.order([]).draw();
                                }
                            }
                        })
                        .on('click', function(event) {
                            event.stopPropagation();
                        });
                } else {
                    var select = $('<select class="form-control form-control-sm" style="width: 100%"><option value="">Select</option></select>')
                        .appendTo($(this))
                        .on('change', function() {
                            var val = $.fn.dataTable.util.escapeRegex($(this).val());
                            column.search(val ? '^' + val + '$' : '', true, false).draw();
                        });
                }
            }
        });
    }
    initDataTableWithFilters('.version-data-table');

    $('#editForm').submit(function (formData) {
        let form = new FormData(formData.target);
        if (form.get('status') == 1) {
            let check = confirm(`Are you sure to proceed ,The product will get In-Active for ${form.get('company')} | ${form.get('segment')} | ${form.get('version')} | ${form.get('business_type')}`)
            if (!check) {
                return false;
            }
        }
        var status = $('#editForm').serialize();
        $.ajax({
            url: saveVersion,
            method: 'GET',
            data: status,
            success: function (response) {

                alert(response.message);
                if (response.status) {
                    location.reload();
                }

            },
            error: function (error) {
                console.log(error);
            }
        });
    });

    

    $(document).on('click', '.editModal', function() {
        var company = $(this).attr('company');
        var segment = $(this).attr('segment');
        var version = $(this).attr('versionValueData');
        var kit = $(this).attr('kitType');
        var slug = $(this).attr('slug');
        var integrationType = $(this).attr('integrationType');
        var businessType = $(this).attr('businessType');

        $('#editModal #company').val(company);
        $('#editModal #segment').val(segment);
        $('#editModal #version').val(version);
        $('#editModal #kit').val(kit);
        $('#editModal #slug').val(slug);
        $('#editModal #integration_type').val(integrationType);
        $('#editModal #business_type').val(businessType);
    });

});
