

function showFullScript(key) {
    // Decode the script content
    const scriptContent = atob(brokerScripts[key].scripts);

    // Set the content in the modal
    document.getElementById('scriptContent').textContent = scriptContent;

    // Show the modal
    new bootstrap.Modal(document.getElementById('scriptViewModal')).show();
}

onload = ()=> {
    const scriptForm = document.querySelector(".scripts-add-form");
    document
        .querySelector(".add-script-btn")
        .addEventListener("click", (event) => {
            scriptForm.reset();
            document.querySelector('.existing-form-data').innerHTML = document.querySelector('.existing-scripts-section').innerHTML
            document.querySelector('[name="operationType"]').setAttribute('value', 'create');
            $("#scriptsModal").modal("show");
        });

    scriptForm.addEventListener("submit", (form) => {
        form.preventDefault();
        let formData = new FormData(form.target)
        postFetch(scriptCreationUrl, formData).then((res) => {
            let response = res.response
            alert(response.message)
            if (response.status) {
                location.reload();
            }
        })
    });

    const scriptTypeRadio = document.querySelectorAll('.scriptType-radio')

    scriptTypeRadio.forEach(radio => {
        radio.addEventListener('change', function() {
            document.querySelector('.code-script').innerHTML = ''
            document.querySelector('.url-script').innerHTML = ''
            document.querySelector('.url-script-attributes').innerHTML = ''
            if (this.value == 'code') {
                document.querySelector('.code-script').innerHTML = `
                <label for="brokerScript" class="required">Script:</label>
                <textarea name="brokerScript[]" id="brokerScript"  rows="5" class="w-100" required></textarea>`
            } else {
                document.querySelector('.url-script').innerHTML = `
                <label for="brokerScript" class="required">Script:</label>
                <input type="url" name="brokerScript[]" id="brokerScript" required class="form-control">
                `
                document.querySelector('.url-script-attributes').innerHTML = `
                <label for="brokerScriptAttributes">Script Attributes:</label>
                <input type="text" name="brokerScriptAttributes[]" id="brokerScriptAttributes" class="form-control">`

            }
        });
    });
}

async function postFetch(url, data)
{
    const response = await fetch(url, {
        body : data,
        method : 'POST',
        headers : {
            'X-CSRF-TOKEN' : document.querySelector('[name="csrf-token"]').getAttribute('content')
        }
        
    });
    response.response = await response.json();
    return response;
}


function closeScriptsModal()
{
    $('#scriptsModal').modal('hide');
}

function editBtnClicked(key) {
    document.querySelector(".scripts-add-form").reset();
    let existingData = document.querySelector('.existing-scripts-section').children;
    let formDataContainer = document.querySelector('.existing-form-data');

    // Create a document fragment to temporarily hold the elements to move
    let fragment = document.createDocumentFragment();

    // Loop through existingData to find elements that do not match the key
    for (let i = 0; i < existingData.length; i++) {
        let inputName = existingData[i].name;
        if (inputName) {
            var scriptType = null
            // Check if the element should be excluded
            if (inputName.includes(`scriptType[${key}]`)) {
                scriptType = existingData[i].value;
                if (scriptType == 'url') {
                    document.querySelector('#scriptType2').checked = true
                } else {
                    document.querySelector('#scriptType1').checked = true
                }
            } else if (inputName.includes(`brokerScript[${key}]`)) {
                scriptType = document.querySelector(`[name="scriptType[${key}]"`).value
                document.querySelector('.code-script').innerHTML = ''
                document.querySelector('.url-script').innerHTML = ''
                document.querySelector('.url-script-attributes').innerHTML = ''
                if (scriptType == 'code') {
                    document.querySelector('.code-script').innerHTML = `
                    <label for="brokerScript" class="required">Script:</label>
                    <textarea name="brokerScript[]" id="brokerScript"  rows="5" class="w-100" required></textarea>`
                } else {
                    document.querySelector('.url-script').innerHTML = `
                    <label for="brokerScript" class="required">Script:</label>
                    <input type="url" name="brokerScript[]" id="brokerScript" required class="form-control">
                    `
                    document.querySelector('.url-script-attributes').innerHTML = `
                    <label for="brokerScriptAttributes">Script Attributes:</label>
                    <input type="text" name="brokerScriptAttributes[]" id="brokerScriptAttributes" class="form-control">`

                }
                document.querySelector('#brokerScript').value = existingData[i].value;
            } else if (inputName.includes(`brokerPage[${key}]`)) {
                document.querySelector('#brokerPage').value = existingData[i].value;
            } else if (inputName.includes(`scriptPriority[${key}]`)) {
                document.querySelector('#scriptPriority').value = existingData[i].value;
            } else if (inputName.includes(`brokerScriptAttributes[${key}]`)) {
                if (document.querySelector(`[name="scriptType[${key}]"`).value == 'url') {
                    document.querySelector('#brokerScriptAttributes').value = existingData[i].value;
                }
            } else {
                // Append elements that do not match the key
                fragment.appendChild(existingData[i].cloneNode(true));
            }
        }
    }

    // Append the collected elements to .existing-form-data
    formDataContainer.appendChild(fragment);

    // Show the modal
    $("#scriptsModal").modal("show");
}

function deleteBtnClicked(key) {
    let check = confirm('Are you sure ?');
    if (!check) {
        return;
    }
    document.querySelector('.delete-script-form-content').innerHTML = '';
    let existingData = document.querySelector('.existing-scripts-section').children;
    let formDataContainer = document.querySelector('.delete-script-form-content');

    // Create a document fragment to temporarily hold the elements to move
    let fragment = document.createDocumentFragment();

    // Loop through existingData to find elements that do not match the key
    for (let i = 0; i < existingData.length; i++) {
        let inputName = existingData[i].name;

        if (inputName) {
            // Check if the element should be excluded
            if (inputName.includes(`brokerScript[${key}]`)) {
                
            } else if (inputName.includes(`brokerPage[${key}]`)) {
                
            } else if (inputName.includes(`scriptPriority[${key}]`)) {
                
            } else {
                // Append elements that do not match the key
                fragment.appendChild(existingData[i].cloneNode(true));
            }
        }
    }

    formDataContainer.appendChild(fragment);
    let form = document.querySelector(".delete-script-form");
    let formData = new FormData(form);
    postFetch(scriptCreationUrl, formData).then((res) => {
        let response = res.response;
        alert(response.message);
        if (response.status) {
            location.reload();
        }
    });
    
}

