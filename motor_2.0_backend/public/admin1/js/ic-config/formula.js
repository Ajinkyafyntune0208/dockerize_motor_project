let bucketList = document.querySelector("#bucket-list");
let placeHolderList = document.querySelector("#place-holder-list");
var expressionList = document.querySelector("#expressions-list");

var selectList = document.getElementById("select-list");
var operators = document.querySelector(".operator-list");
var otherList = document.querySelector(".other-list");

onload = () => {
    labelGroups.forEach((element) => {
        new Sortable(document.getElementById(element + "-premium-list"), {
            group: {
                name: "shared",
                pull: "clone", // To clone: set pull to 'clone'
                put: false, // Allow putting items from the other list
            },
            animation: 150,
            swapThreshold: 0,
        });
    });


    new Sortable(bucketList, {
        group: {
            name: "shared",
            pull: "clone", // To clone: set pull to 'clone'
            put: false, // Allow putting items from the other list
        },
        animation: 150,
        swapThreshold: 0,
    });

    new Sortable(placeHolderList, {
        group: {
            name: "shared",
            pull: "clone", // To clone: set pull to 'clone'
            put: false, // Allow putting items from the other list
        },
        animation: 150,
        swapThreshold: 0,
    });

    new Sortable(expressionList, {
        group: {
            name: "shared",
            pull: "clone", // To clone: set pull to 'clone'
            put: false, // Allow putting items from the other list
        },
        animation: 150,
        swapThreshold: 0,
    });
    

    new Sortable(selectList, {
        group: {
            name: "shared",
            pull: false, // Do not allow items to be pulled from this list
            put: true, // Allow putting items from the other list
        },
        animation: 150,
        swapThreshold: 0,
        sort: false,
        removeOnSpill: true, // Allow removing items from this list when dropped outside
        onAdd: function (evt) {
            initializeFuntionalSortables();
            initializeConditionalSortables();

            evt.item.classList.add("moved-item", "selected");
            if (evt.item.getAttribute("data-type") == "plain-text") {
                evt.item.classList.add("plain-text");
                evt.item.classList.remove("mt-2");
                evt.item.classList.add("plain-text", "p-0", "m-0");
            } else if (evt.item.getAttribute("data-type") == "conditional") {
                //conditional item
            }
            evt.item.setAttribute("data-selected", "true");

            createFormula();
        },
        onSpill: function (evt) {
            // let nextItem = evt.item?.nextElementSibling;

            evt.item.remove();

            //if the removed element has next sibling, then remove that as well
            // if (nextItem) {
            //     nextItem.parentNode.removeChild(nextItem);
            // }

            createFormula();
        },
    });

    new Sortable(operators, {
        group: {
            name: "shared",
            pull: "clone", // To clone: set pull to 'clone'
            put: false, // Allow putting items from the other list
        },
        animation: 150,
        swapThreshold: 0,
    });

    new Sortable(otherList, {
        group: {
            name: "shared",
            pull: "clone", // To clone: set pull to 'clone'
            put: false, // Allow putting items from the other list
        },
        animation: 150,
        swapThreshold: 0,
    });

    let conditionalOperatorListAll = document.querySelectorAll(
        ".conditional-operator-list"
    );
    conditionalOperatorListAll.forEach((element) => {
        new Sortable(element, {
            group: {
                name: "shared",
                pull: "clone",
                put: true,
            },
            animation: 150,
            swapThreshold: 0,
            sort: false,
            onAdd: function (evt) {
                createFormula();
            },
            onSpill: function (evt) {
                createFormula();
            },
        });
    });


    document
        .querySelector(".submit-form")
        .addEventListener("submit", (event) => {

            event.preventDefault();
            const form = event.target;

            let formValue = document.querySelector('[name="formula"]');
            let span = document.querySelector(".formula-error");

            if (!formValue.value) {
                if (span) {
                    span.innerHTML = "";
                }
                if (!span) {
                    span = document.createElement("span");
                    span.classList.add("text-danger", "formula-error");
                }
                span.innerHTML = `Formula is empty`;
                document.querySelector(".formula-group").appendChild(span);
                return;
            } else {
                let isValidated = createFormula(true);

                if (span) {
                    span.innerHTML = "";
                }
                if (!isValidated) {
                    if (!span) {
                        span = document.createElement("span");
                        span.classList.add("text-danger", "formula-error");
                    }
                    span.innerHTML = `Invalid formula`;
                    document.querySelector(".formula-group").appendChild(span);
                    return;
                }
            }

            const matrix = formValue.value;
            const formData = new FormData();
            formData.append('matrix', matrix)
            if (action == 'UPDATE') {
                formData.append('formulaId', formulaId)
            }

            postFetch(formulaExistsUrl, formData).then((res) => {
                let data = res.response;
                if (data.formulaExists) {
                    let confirmStatement = `A formula with the name "${data.formulaName}" already exists. Do you want to proceed ?`;
                    if (action == 'CREATE') {
                        confirmStatement = `A formula with the name "${data.formulaName}" already exists. Do you want to proceed with creating a new one?`;
                    }
                    let check = confirm(confirmStatement);

                    if (!check) {
                        return; // Exit the function to prevent further processing
                    }
                }
                form.submit()
            })
        });
};

function formula(
    selectedElementsArray,
    ifCountObj = { count: 0 },
    functionObj = { ceil: 0, floor: 0, round: 0 }
) {
    let validate = "";
    let matrix = "";
    selectedElementsArray.forEach((element) => {
        let type = element.getAttribute("data-type");
        if (type == "premium") {
            validate += ` 1`;
            matrix += ` |L:${element.getAttribute("data-id")}|`;
        } else if (type == "operator") {
            let val = element.getAttribute("data-value");
            if (["union", "intersection"].includes(val)) {
                val = "+";
            }
            validate += `${val}`;
            matrix += ` ${element.getAttribute("data-value")}`;
        } else if (type == "plain-text") {
            validate += ` ${element.children[0].value}`;
            matrix += ` |PT:${element.children[0].value}|`;
        } else if (type == "expression") {
            validate += ` 1`;
            matrix += ` |F:${element.getAttribute("data-id")}|`;
        } else if (type == "conditional") {
            ifCountObj.count++;
            let currentIfCount = ifCountObj.count;

            let conditionalPart = Array.from(element.children[0].children);
            let trueStatement = Array.from(element.children[1].children);
            let falseStatement = Array.from(element.children[2].children);

            matrix += ` [IF:${currentIfCount}] ${
                formula(conditionalPart, currentIfCount).matrix
            } `;
            matrix += ` ? ${formula(trueStatement, ifCountObj).matrix}`;
            matrix += ` : ${
                formula(falseStatement, ifCountObj).matrix
            } [ENDIF:${currentIfCount}]`;

            validate += ` ( (${formula(conditionalPart).validate} )`;
            validate += ` ? ${formula(trueStatement).validate}`;
            validate += ` : ${formula(falseStatement).validate} )`;
        } else if (type == "function") {
            let val = element.getAttribute("data-value");
            if (val == "round") {
                functionObj.round++;
                let currentFunctionCount = functionObj.round;

                let functionElements = Array.from(element.children[1].children);
                matrix += ` [ROUND:${currentFunctionCount}] ${
                    formula(functionElements, ifCountObj, functionObj).matrix
                } `;
                matrix += ` [ENDROUND:${currentFunctionCount}]`;

                validate += ` (${formula(functionElements).validate} )`;
            } else if (val == "ceil") {
                functionObj.ceil++;
                let currentFunctionCount = functionObj.ceil;

                let functionElements = Array.from(element.children[1].children);
                matrix += ` [CEIL:${currentFunctionCount}] ${
                    formula(functionElements, ifCountObj, functionObj).matrix
                } `;
                matrix += ` [ENDCEIL:${currentFunctionCount}]`;

                validate += ` (${formula(functionElements).validate} )`;
            } else if (val == "floor") {
                functionObj.floor++;
                let currentFunctionCount = functionObj.floor;

                let functionElements = Array.from(element.children[1].children);
                matrix += ` [FLOOR:${currentFunctionCount}] ${
                    formula(functionElements, ifCountObj, functionObj).matrix
                } `;
                matrix += ` [ENDFLOOR:${currentFunctionCount}]`;

                validate += ` (${formula(functionElements).validate} )`;
            }
        } else if (type == 'bucket') {
            validate += ` 1`;
            matrix += ` |B:${element.getAttribute("data-id")}|`; 
        } else if (type == 'place-holder') {
            validate += ` 1`;
            matrix += ` |PL:${element.getAttribute("data-id")}|`; 
        }
    });

    return {
        validate,
        matrix,
    };
}

function createFormula(isSubmit = false) {
    let rightSideElementsArray = Array.from(selectList.children);
    let formElement = document.querySelector(".formula-form");
    formElement.innerHTML = "";

    let formulaResult = formula(rightSideElementsArray);
    let matrix = formulaResult.matrix;
    let validate = formulaResult.validate;

    let check = true;
    if (isSubmit) {
        check = false;
        try {
            // Attempt to evaluate the code
            let result = eval(validate);
            check = true;
        } catch (error) {
            // Handle any errors that occur during evaluation
            // console.error(error);
        }
    }

    document.querySelector("[name=formula]").value = matrix;
    return check;
}

function validatePlainText(event) {
    if (event.key === "e" || event.key === "E") {
        event.preventDefault();
    }

    createFormula();
}

function initializeConditionalSortables() {
    let conditionalPartList = document.querySelectorAll(".condition-part");

    conditionalPartList.forEach((element) => {
        new Sortable(element, {
            group: {
                name: "shared",
                pull: false, // Do not allow items to be pulled from this list
                put: true, // Allow putting items from the other list
            },
            animation: 150,
            swapThreshold: 0,
            sort: false,
            removeOnSpill: true,
            onAdd: function (evt) {
                createFormula();
            },
            onSpill: function (evt) {
                createFormula();
            },
        });
    });

    let trueConditionList = document.querySelectorAll(".true-condition-part");

    trueConditionList.forEach((element) => {
        new Sortable(element, {
            group: {
                name: "shared",
                pull: false, // Do not allow items to be pulled from this list
                put: true, // Allow putting items from the other list
            },
            animation: 150,
            swapThreshold: 0,
            sort: false,
            removeOnSpill: true,
            onAdd: function (evt) {
                createFormula();
            },
            onSpill: function (evt) {
                createFormula();
            },
        });
    });

    let falseConditionList = document.querySelectorAll(".false-condition-part");
    falseConditionList.forEach((element) => {
        new Sortable(element, {
            group: {
                name: "shared",
                pull: false, // Do not allow items to be pulled from this list
                put: true, // Allow putting items from the other list
            },
            animation: 150,
            swapThreshold: 0,
            sort: false,
            removeOnSpill: true,
            onAdd: function (evt) {
                createFormula();
            },
            onSpill: function (evt) {
                createFormula();
            },
        });
    });
}

function initializeFuntionalSortables() {
    let functionList = document.querySelectorAll(".function-item");
    functionList.forEach((element) => {
        new Sortable(element, {
            group: {
                name: "shared",
                pull: "clone",
                put: true,
            },
            animation: 150,
            swapThreshold: 0,
            sort: false,
            onAdd: function (evt) {
                createFormula();
            },
            onSpill: function (evt) {
                createFormula();
            },
        });
    });

    functionList = document.querySelectorAll(".functional-list");
    functionList.forEach((element) => {
        new Sortable(element, {
            group: {
                name: "shared",
                pull: false, // Do not allow items to be pulled from this list
                put: true, // Allow putting items from the other list
            },
            animation: 150,
            swapThreshold: 0,
            sort: false,
            removeOnSpill: true,
            onAdd: function (evt) {
                createFormula();
            },
            onSpill: function (evt) {
                createFormula();
            },
        });
    });
}

initializeFuntionalSortables();
initializeConditionalSortables();

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
