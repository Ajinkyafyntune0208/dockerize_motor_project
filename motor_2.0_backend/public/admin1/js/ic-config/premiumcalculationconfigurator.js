function handleSelectionChange(key) {
    var select = document.getElementById(key + '_type');
    var container = document.getElementById(key + '_container');
    var selectedValue = select.value;

    container.innerHTML = '';
    var hiddenFormulaValue = document.getElementById(key + '_type_formula').value;
    var hiddenCustomValue = document.getElementById(key + '_type_custom_val').value;
    if (selectedValue === 'attribute_name') {
        var attributeValue = '';
        var attributeTrail = '';

        attributes.forEach(function (attribute) {
            if (attribute.label_key === key) {
                attributeValue = attribute.attribute_name;
                attributeTrail = attribute.attribute_trail;
            }
        });

        if (attributeValue !== '') {
            container.innerHTML = '<div class="input-group" data-bs-toggle="tooltip" data-bs-placement="top" title="' + attributeValue + ' (' + attributeTrail + ')"><input type="text" name="' + key + '_attribute_name" class="form-control" value="' + attributeValue + ' (' + attributeTrail + ')" readonly required /></div>';
        }
    } else if (selectedValue === 'formula_name') {
        var formulaOptions = '<option value="" disabled selected>Select a formula</option>';
        formulaOptions += formulas.map(function (formula) {
            return '<option value="' + formula.id + '"' + (formula.id == hiddenFormulaValue ? ' selected' : '') + '>' + formula.formula_name + '</option>';
        }).join('');
        
        container.innerHTML = '<select name="' + key + '_formula_name" class="form-control selectpicker" data-live-search="true" required>' + formulaOptions + '</select>';
        $('.selectpicker').selectpicker('refresh');
    } else if (selectedValue === 'custom_val') {
        container.innerHTML = '<input type="number" name="' + key + '_custom_val" class="form-control" placeholder="Enter custom type" value="' + hiddenCustomValue + '" oninput="validateNumberInput(this)" required />';
    }

    adjustDropdownWidth(select);
}

function adjustDropdownWidth(select) {
    var width = select.clientWidth;
    select.style.width = width + 'px';
}

function validateAccordion() {
    var isValid = true;
    var allAccordions = document.querySelectorAll('.accordion-item');
    allAccordions.forEach(function (accordion) {
        var accordionBody = accordion.querySelector('.accordion-body');
        var inputs = accordionBody.querySelectorAll('input, select');
        var accordionCollapse = accordion.querySelector('.accordion-collapse');

        var accordionHasError = false;

        inputs.forEach(function (input) {
            if (input.hasAttribute('required') && !input.value) {
                accordionHasError = true;
                input.classList.add('is-invalid');
            } else {
                input.classList.remove('is-invalid');
            }
        });

        if (accordionHasError) {
            accordionCollapse.classList.add('show');
            isValid = false;
        }
    });

    return isValid;
}

document.getElementById('updateButton').addEventListener('click', function(event) {
    if (!validateAccordion()) {
        event.preventDefault();
        event.stopPropagation();
        alert('Please fill out all required fields before proceeding.');
    }
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.custom-select').forEach(function(select) {
        adjustDropdownWidth(select);
    });
});

function validateNumberInput(input) {
    input.value = input.value.replace(/[^0-9]/g, '');
}

var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl, {
        html: true
    });
});