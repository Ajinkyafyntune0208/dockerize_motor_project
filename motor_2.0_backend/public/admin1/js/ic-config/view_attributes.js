$(document).ready(function() {
    var table = initializeDataTable();

    $('#selectIC').on('change', function() {
        resetDropdowns(['#integrationType', '#segment', '#businessType']);
        clearTable(table);

        var selectIC = $(this).val();
        if (selectIC) {
            $.ajax({
                url: viewAttributeRoute,
                type: "GET",
                data: { ic_alias: selectIC },
                success: function(response) {
                    populateDropdown('#integrationType', response.attributes, 'integration_type');
                }
            });
        }
    });

    $('#integrationType').on('change', function() {
        resetDropdowns(['#segment', '#businessType']);
        clearTable(table);

        var selectedIcAlias = $('#selectIC').val();
        var selectedIntegrationType = $(this).val();

        if (selectedIntegrationType) {
            $.ajax({
                url: viewAttributeRoute,
                type: "GET",
                data: {
                    ic_alias: selectedIcAlias,
                    integration_type: selectedIntegrationType
                },
                success: function(response) {
                    populateDropdown('#segment', response.attributes, 'segment');
                }
            });
        }
    });

    $('#segment').on('change', function() {
        $('#businessType').prop('disabled', false);
        clearTable(table);

        var selectedIcAlias = $('#selectIC').val();
        var selectedIntegrationType = $('#integrationType').val();
        var selectedSegment = $(this).val();

        $.ajax({
            url: viewAttributeRoute,
            type: "GET",
            data: {
                ic_alias: selectedIcAlias,
                integration_type: selectedIntegrationType,
                segment: selectedSegment
            },
            success: function(response) {
                populateDropdown('#businessType', response.attributes, 'business_type');
            }
        });
    });

    $('#save-btn').on('click', function() {
        var selectedIcAlias = $('#selectIC').val();
        var selectedIntegrationType = $('#integrationType').val();
        var selectedSegment = $('#segment').val();
        var selectedBusinessType = $('#businessType').val();

        if (selectedIcAlias && selectedIntegrationType && selectedSegment && selectedBusinessType) {
            $.ajax({
                url: viewAttributeRoute,
                type: "GET",
                data: {
                    ic_alias: selectedIcAlias,
                    integration_type: selectedIntegrationType,
                    segment: selectedSegment,
                    business_type: selectedBusinessType
                },
                success: function(response) {
                    if (response.attributes.length > 0) {
                        updateTable(table, response.attributes);
                        $('#attributesTable').show();
                    } else {
                        clearTable(table);
                        $('#attributesTable').hide();
                    }
                }
            });
        }
    });

    function initializeDataTable() {
        return $('#attributesTable').DataTable({
            "responsive": true,
            "lengthChange": true,
            "autoWidth": false,
            "scrollX": false,
        });
    }

    function resetDropdowns(dropdowns) {
        dropdowns.forEach(function(dropdown) {
            $(dropdown).empty().append('<option value="">Select</option>').prop('disabled', true);
        });
    }

    function clearTable(table) {
        table.clear().draw();
        $('#attributesTable thead tr').not(':first').remove();
        $('#attributesTable').hide();
    }

    function populateDropdown(dropdown, attributes, key) {
        var uniqueValues = [];
        $(dropdown).empty().append('<option value="">Select</option>').prop('disabled', false);
        attributes.forEach(function(attribute) {
            if (attribute[key] && !uniqueValues.includes(attribute[key])) {
                uniqueValues.push(attribute[key]);
                $(dropdown).append('<option value="' + attribute[key] + '">' + attribute[key] + '</option>');
            }
        });
    }

    function updateTable(table, attributes) {
        table.clear();
        attributes.forEach(function(attribute, index) {
            var attributeTrail = '<div class="tooltip-wrapper" title="' + attribute.attribute_trail + '">' +
                '<span class="text-truncate">' + attribute.attribute_trail + '</span>' +
                '</div>';
            table.row.add([
                index + 1, // Sr. No.
                attribute.attribute_name,
                attributeTrail,
                attribute.sample_value,
                attribute.sample_type
            ]).draw(false);
        });
        initializeTooltips();
    }

    function initializeTooltips() {
        $('.tooltip-wrapper').tooltip({
            container: 'body',
            html: true
        });
    }
});