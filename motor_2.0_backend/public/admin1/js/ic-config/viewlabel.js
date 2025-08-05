document.querySelectorAll('button[data-bs-toggle="tab"]').forEach((el) => {
    el.addEventListener('shown.bs.tab', () => {
        DataTable.tables({ visible: true, api: true }).columns.adjust();
    });
});

const table1 = new DataTable('#myTable1', {
    paging: true,
    scrollCollapse: true,
    scrollY: 200,
    pageLength: 10 
});

const table2 = new DataTable('#myTable2', {
    paging: true,
    scrollCollapse: true,
    scrollY: 200,
    pageLength: 10 ,
});

const table3 = new DataTable('#myTable3', {
    paging: true,
    scrollCollapse: true,
    scrollY: 200,
    pageLength: 10 
});

$('.edit-btn').click(function() {
    var formulaName = $(this).attr('formulaLabelName');
    console.log(formulaName);
    $('#showFormula').text(formulaName);
});

async function getMethod(url) {
    const response = await fetch(url);
    response.response = await response.json();
    return response;
}

function viewFormula(e) {
    document.querySelector('body').style.cursor = 'progress'
    getMethod(e.getAttribute('data-href')).then((res) => {
        $('#formulaModal').modal('show')
        document.querySelector('body').style.cursor = 'auto'
        let data = res.response;
        if(data.status === true) {
            let result = data.data;
            document.querySelector('#showFormula_1').innerHTML = result.short_formula
        }
    });
}

function closeModal(){
    $('#formulaModal').modal('hide')

}