
const showCreds = (load = true)=> {

    document.querySelector(".pos-config-detail").innerHTML = '';

    let posValue = $("#pos").val();
    let sectionValue = $("#section").val();
    let icValue = $("#insuranceCompany").val();

    let row = document.querySelector(".fields-section");
    if (load) {
        row.innerHTML = "";
    }

    if (posValue.length && sectionValue.length && icValue) {
        let field = fields[icValue];

        if (field) {
            for (const key in field) {
                let div = document.createElement("div");
                div.classList.add("col-md-4");
                div.innerHTML = `
                <div class="form-group">
                    <label class="required">${field[key]}</label>
                    <input id="${key}" name="creds[${icValue}][${key}]" type="text" class="form-control">
                </div>`;
                row.appendChild(div);
            }
            let submit = document.createElement("div");
            submit.classList.add("col-12");
            submit.innerHTML = `
            <button type="submit" class="btn btn-success">Submit</button>
            `;
            row.appendChild(submit);
        }
    }

    showPoscredentials(posValue, sectionValue, icValue);
}

let fields = JSON.parse(credsFields);

document.querySelector("#section").addEventListener("change", ()=> showCreds()); 
document.querySelector("#pos").addEventListener("change", ()=> showCreds());
document.querySelector("#insuranceCompany").addEventListener("change", ()=> showCreds());

const showPoscredentials = (posValue, sectionValue, icValue) => {
    let row = document.querySelector(".pos-config-detail");
    let div = document.createElement('div');
    div.classList.add('col-12');

    let table = document.createElement('table');
    table.classList.add('table', 'table-bordered')

    let formData = new FormData();

    if (icValue) {
        formData.append('insuranceCompany[]', icValue)
    }
    sectionValue.forEach(value => formData.append('section[]', value));
    posValue.forEach(value => formData.append('pos[]', value));

    fetchPoscreds(formData).then((res) => {
        let data = res.response;
        if(data.status == true && data.data.length > 0) {
            let tableContent = `
            <thead>
                <tr>
                    <th>Pos</th>
                    <th>Section</th>
                    <th>Insurance Company</th>
                    <th>Credentials</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>`;
            data.data.forEach(element => {
                tableContent += `
                <tr>
                    <td>${element.agent_detail?.agent_name}</td>
                    <td>${element.product_sub_type?.product_sub_type_code}</td>
                    <td>${element.insurance_company?.company_alias}</td>
                    <td>${JSON.stringify(element.credentials)}</td>
                    <td>
                        <div class="btn-group">
                        <a class="btn btn-sm btn-outline-danger" onclick="deleteConfig(${element.id})" style="padding-left: 6px; padding-right: 10px;" data-id="${element.id}">
                        <i class="fa fa-trash"></i>
                        </a>
                        </div>
                    </td>
                </tr>`
            });
            tableContent += `</tbody>`;

            table.innerHTML = tableContent
            let header = document.createElement('h6')
            header.classList.add('mt-5', 'mb-3')
            header.innerHTML = 'Pos Imd Credentials';

            div.appendChild(header)

            div.appendChild(table)
            row.appendChild(div)
        }
    })
}

async function fetchPoscreds(data) {
    const response = await fetch(fetchCredsUrl, {
        body: data,
        method: 'POST',
    });
    response.response = await response.json();
    return response;
}


function deleteConfig(id)
{
    if (confirm('Are you sure..?')) {
        document.querySelector('[name="deleteId"]').value = id;
        document.querySelector('.deleteForm').submit();
    }
}


onload = ()=>{
    showCreds(false);
}