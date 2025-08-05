

function updateOptions()
{
    $('select.addons-select').each(function() {
        let selectedValues = $(this).val();
        let selectId = $(this).attr('id');

        $('select.addons-select').each(function() {
            let current = $(this).attr('id');;
            if (selectId != current) {
                $(this).find('option').each(function() {
                    let optionValue = $(this).val();
                    if (selectedValues && selectedValues.indexOf(optionValue) !== -1) {
                        $(this).prop('disabled', true);
                    }
                });
                // Refresh the selectpicker to update UI
                $(this).selectpicker('refresh');
            }
        })
    });
}

onload = ()=>{
    // When any of the addon selects change
    $('select.addons-select').on('changed.bs.select', function(e, clickedIndex, isSelected, previousValue) {
        let selectedValues = $(this).val(); // Get the selected values array
        let currentId = $(this).attr('id'); // Get the ID of the current select
        console.log(selectedValues)
        console.log($('select.addons-select'))
        
        // Loop through all addon selects
        $('select.addons-select').each(function() {
            let selectId = $(this).attr('id');
            
            // Skip the current select
            if (selectId !== currentId) {
                // Remove options that are selected in current select from other selects
                $(this).find('option').each(function() {
                    let optionValue = $(this).val();
                    if (selectedValues && selectedValues.indexOf(optionValue) !== -1) {
                        $(this).prop('disabled', true);
                        $(this).prop('selected', false);
                    } else {
                        $(this).prop('disabled', false);
                    }
                });
                // Refresh the selectpicker to update UI
                $(this).selectpicker('refresh');
            }
        });

        updateOptions()
    });
}