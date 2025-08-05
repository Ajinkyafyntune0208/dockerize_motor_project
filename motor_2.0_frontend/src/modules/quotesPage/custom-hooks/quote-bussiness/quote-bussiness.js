import _ from "lodash";

/* --- oriental --- */
//cv
export const oriental_cv = (
  quote,
  selectedAddons,
  additional,
  additionalList,
  totalAddon,
  addedNcb
) => {
  // For Oriental CV, you need to use following formula:NCB premium = (Total OD premium + Addons - discounts(anti theft)) * applicable NCB
  //for extra discounts we don't need ZeroDep hence recalc total (addon * discount %) without zd
  let discountPercentageOriental = 0;
  //additional & selected
  //Calculate total premium of Additional addons
  let totalAdditional = 0;
  selectedAddons.forEach((el) => {
    if (
      additional?.includes(el === "zeroDepreciation" ? "nomatch" : el) &&
      typeof additionalList[el] === "number"
    ) {
      totalAdditional = totalAdditional + Number(additionalList[el]);
    }
  });
  let ncbTotalAdditional = 0;
  selectedAddons.forEach((el) => {
    if (
      additional?.includes(el === "zeroDepreciation" ? el : "nomatch") &&
      typeof additionalList[el] === "number"
    ) {
      ncbTotalAdditional = ncbTotalAdditional + Number(additionalList[el]);
    }
  });

  //Inbuilt
  //Calculate total premium of Inbuilt addons
  let inbuilt = Object.keys(quote?.addOnsData?.inBuilt);
  let allAddons = [
    "roadSideAssistance",
    // "imt23",
    "keyReplace",
    "engineProtector",
    "ncbProtection",
    "consumables",
    "tyreSecure",
    "returnToInvoice",
    "lopb",
    "emergencyMedicalExpenses",
    "emiProtection",
    "additionalTowing",
    "batteryProtect",
  ];

  let inbuiltList = quote?.addOnsData?.inBuilt;
  let totalInbuilt = 0;
  allAddons.forEach((el) => {
    if (inbuilt?.includes(el) && typeof inbuiltList[el] === "number") {
      totalInbuilt = totalInbuilt + Number(inbuiltList[el]);
    }
  });
  let ncbTotalInbuilt = 0;
  ["zeroDepreciation"].forEach((el) => {
    if (inbuilt?.includes(el) && typeof inbuiltList[el] === "number") {
      ncbTotalInbuilt = ncbTotalInbuilt + Number(inbuiltList[el]);
    }
  });
  //other
  //Calculate total premium of Other addons
  let others =
    (quote?.addOnsData?.other && Object.keys(quote?.addOnsData?.other)) || [];
  let othersList = quote?.addOnsData?.other;
  let totalOther = 0;
  others.forEach((el) => {
    if (typeof othersList[el] === "number") {
      totalOther = totalOther + Number(othersList[el]);
    }
  });
  let extraOtherDiscounts2 =
    (Number(ncbTotalAdditional) + Number(ncbTotalInbuilt)) *
    discountPercentageOriental;
    
  return {
    addedNcb:
      ((totalAddon - extraOtherDiscounts2) * Number(quote?.ncbDiscount)) / 100,
    revisedNcb: Number(quote?.deductionOfNcb),
    // + Number(addedNcb),
    otherDiscounts:
      (quote?.icVehicleDiscount || 0) + Number(extraOtherDiscounts2),
    totalPremiumc:
      Number(quote?.finalTotalDiscount) +
      // Number(addedNcb) +
      Number(extraOtherDiscounts2),
  };
};
//car
export const oriental_car = (
  quote,
  selectedAddons,
  additional,
  additionalList,
  totalPremiumA
) => {
  // re-eval required addons with others

  //additional & selected
  let totalAdditional = 0;
  selectedAddons.forEach((el) => {
    if (
      additional?.includes(
        ![
          "engineProtector",
          "lopb",
          "returnToInvoice"
        ].includes(el)
          ? "nomatch"
          : el
      ) &&
      typeof additionalList[el] === "number"
    ) {
      totalAdditional = totalAdditional + Number(additionalList[el]);
    }
  });

  //Inbuilt
  let inbuilt = Object.keys(quote?.addOnsData?.inBuilt);
  let allAddons = [
    "engineProtector",
    "lopb",
    "returnToInvoice"
  ];
  let inbuiltList = quote?.addOnsData?.inBuilt;
  let totalInbuilt = 0;
  allAddons.forEach((el) => {
    if (inbuilt?.includes(el) && typeof inbuiltList[el] === "number") {
      totalInbuilt = totalInbuilt + Number(inbuiltList[el]);
    }
  });

  //other
  let others =
    (quote?.addOnsData?.other && Object.keys(quote?.addOnsData?.other)) || [];
  let othersList = quote?.addOnsData?.other;
  let totalOther = 0;
  others.forEach((el) => {
    if (typeof othersList[el] === "number") {
      totalOther = totalOther + Number(othersList[el]);
    }
  });
  let NcbTotalAddon =
    Number(totalAdditional) + Number(totalInbuilt) + Number(totalOther);

  return {
    revisedNcb: Math.round(
      ((totalPremiumA * 1 +
        NcbTotalAddon * 1 -
        (Number(quote?.finalTotalDiscount) -
          Number(quote?.deductionOfNcb) -
          (Number(quote.tppdDiscount) ? Number(quote.tppdDiscount) : 0))) *
        Number(quote?.ncbDiscount)) /
        100
    ),
    totalPremiumc:
      Number(quote?.finalTotalDiscount) -
      //deducting the ncb sent by backend
      Number(quote?.deductionOfNcb) +
      //calculating ncb and adding it to total discount
      Math.round(
        ((totalPremiumA * 1 +
          NcbTotalAddon * 1 -
          (Number(quote?.finalTotalDiscount ? quote?.finalTotalDiscount : 0) -
            Number(quote?.deductionOfNcb ? quote?.deductionOfNcb : 0) -
            Number(quote?.tppdDiscount ? quote?.tppdDiscount : 0))) *
          Number(quote?.ncbDiscount ? quote?.ncbDiscount : 0)) /
          100
      ),
  };
};
/* -x- oriental -x- */

/* --- magma --- */
//IMT 23
//Magma
export const imt_magma = (
  quote,
  additional,
  selectedAddons,
  additionalList,
  inbuiltList,
  totalPremiumA
) => {
  let otherDiscounts =
    quote?.icVehicleDiscount * 1
      ? quote?.icVehicleDiscount * 1
      : 0 + quote?.imt23Discount * 1;
  let revisedNcb =
    ((totalPremiumA +
      (selectedAddons?.includes("imt23") &&
      additional?.includes("imt23") &&
      additionalList["imt23"] * 1
        ? additionalList["imt23"] * 1
        : inbuiltList["imt23"] * 1) -
      otherDiscounts) *
      Number(quote?.ncbDiscount)) /
    100;
  return {
    otherDiscounts:
      quote?.icVehicleDiscount * 1
        ? quote?.icVehicleDiscount * 1
        : 0 + quote?.imt23Discount * 1,
    totalPremiumc:
      revisedNcb +
      otherDiscounts +
      (quote?.tppdDiscount * 1 ? quote?.tppdDiscount * 1 : 0),
    revisedNcb: revisedNcb,
  };
};

//IMT
//Magma -  No IMT 23 discount
export const imt_magma_nodiscount = (
  quote,
  additional,
  selectedAddons,
  additionalList,
  inbuiltList,
  totalPremiumA
) => {
  let otherDiscounts = quote?.icVehicleDiscount || 0;
  let revisedNcb =
    ((totalPremiumA +
      (selectedAddons?.includes("imt23") &&
      additional?.includes("imt23") &&
      additionalList["imt23"] * 1
        ? additionalList["imt23"] * 1
        : inbuiltList["imt23"] * 1) -
      otherDiscounts) *
      Number(quote?.ncbDiscount)) /
    100;
  return {
    otherDiscounts: quote?.icVehicleDiscount || 0,
    revisedNcb: revisedNcb,
    totalPremiumc:
      revisedNcb +
      otherDiscounts +
      (quote?.tppdDiscount * 1 ? quote?.tppdDiscount * 1 : 0),
  };
};
/* -x- magma -x- */

/* --- icici lombard --- */
//IMT-23
//ICICI Lombard
export const imt_icici = (
  quote,
  selectedAddons,
  additional,
  additionalList,
  inbuiltList,
  totalPremiumA
) => {
  return {
    othrDiscount:
      quote?.icVehicleDiscount * 1 ? quote?.icVehicleDiscount * 1 : 0,
    otherDiscounts:
      quote?.icVehicleDiscount * 1 ? quote?.icVehicleDiscount * 1 : 0,
    revisedNcb:
      ((totalPremiumA +
        (selectedAddons?.includes("imt23") &&
        additional?.includes("imt23") &&
        additionalList["imt23"] * 1
          ? additionalList["imt23"] * 1
          : inbuiltList["imt23"] * 1)) *
        Number(quote?.ncbDiscount)) /
      100,
    totalPremiumc:
      ((totalPremiumA +
        (selectedAddons?.includes("imt23") &&
        additional?.includes("imt23") &&
        additionalList["imt23"] * 1
          ? additionalList["imt23"] * 1
          : inbuiltList["imt23"] * 1)) *
        Number(quote?.ncbDiscount)) /
      100 +
      (quote?.icVehicleDiscount * 1 ? quote?.icVehicleDiscount * 1 : 0) +
      (quote?.tppdDiscount * 1 ? quote?.tppdDiscount * 1 : 0),
  };
};
/* -x- icici lombard -x- */

/* --- bajaj allianz --- */
//IMT
//BAJAJ - GCV
export const imt_bajaj_gcv = (
  quote,
  additional,
  selectedAddons,
  additionalList,
  inbuiltList,
  totalPremiumA
) => {
  return {
    otherDiscounts:
      (quote?.icVehicleDiscount * 1 ? quote?.icVehicleDiscount * 1 : 0) * 1.15,
    revisedNcb:
      ((totalPremiumA +
        (selectedAddons?.includes("imt23") &&
        additional?.includes("imt23") &&
        additionalList["imt23"] * 1
          ? additionalList["imt23"] * 1
          : inbuiltList["imt23"] * 1)) *
        Number(quote?.ncbDiscount)) /
      100,
    totalPremiumc:
      ((selectedAddons?.includes("imt23") &&
      additional?.includes("imt23") &&
      additionalList["imt23"] * 1
        ? additionalList["imt23"] * 1
        : inbuiltList["imt23"] * 1) *
        Number(quote?.ncbDiscount)) /
        100 +
      (quote?.icVehicleDiscount * 1 ? quote?.icVehicleDiscount * 1 : 0) * 1.15 +
      (quote?.tppdDiscount * 1 ? quote?.tppdDiscount * 1 : 0),
  };
};
/* -x- bajaj allianz -x- */

/* --- universal sompo --- */
//IMT
//Universal Sompo - MISC
export const imt_universal_sompo_misc = (
  quote,
  selectedAddons,
  additional,
  additionalList,
  inbuiltList,
  totalPremiumA
) => {
  let recalcNcb =
    (totalPremiumA +
      (selectedAddons?.includes("imt23") &&
      additional?.includes("imt23") &&
      additionalList["imt23"] * 1
        ? additionalList["imt23"] * 1
        : inbuiltList["imt23"] * 1) -
      (Number(quote?.finalTotalDiscount) -
        Number(quote?.deductionOfNcb) -
        (quote?.tppdDiscount * 1 ? quote?.tppdDiscount * 1 : 0))) *
    0.5;
  //Ic vehicle discount  + 15% IC vehicle discount if imt-23 is selected
  let othrDiscount =
    (quote?.icVehicleDiscount * 1 ? quote?.icVehicleDiscount * 1 : 0) * 1.15;

  return {
    revisedNcb: recalcNcb,
    otherDiscounts: othrDiscount,
    totalPremiumc:
      Number(quote?.finalTotalDiscount) -
      Number(quote?.deductionOfNcb) +
      recalcNcb +
      othrDiscount,
  };
};

//IMT
//Universal Sompo - GCV
export const imt_universal_sompo_gcv = (quote) => {
  return {
    otherDiscounts:
      (quote?.icVehicleDiscount * 1 ? quote?.icVehicleDiscount * 1 : 0) * 1.15,
    revisedNcb: Number(quote?.deductionOfNcb) * 1.15,
    totalPremiumc:
      Number(quote?.deductionOfNcb) * 1.15 +
      (quote?.icVehicleDiscount * 1 ? quote?.icVehicleDiscount * 1 : 0) * 1.15 +
      (quote?.tppdDiscount * 1 ? quote?.tppdDiscount * 1 : 0),
  };
};
/* -x- universal sompo -x- */

/* --- royal sundaram --- */
//Royal Sundaram | discounting logic | CAR
export const royal_sundaram_car = (quote, addOnsAndOthers) => {
  //Addon Discount group | This ;ogic only works if zero dep is also selected.
  // 10 % on final addon premium
  const g1 = [
    "zeroDepreciation",
    "returnToInvoice",
    "ncbProtection",
    "engineProtector",
    "windShield",
    "consumables",
  ];
  //15% on final addon premium
  const g2 = [
    "zeroDepreciation",
    "returnToInvoice",
    "ncbProtection",
    "lopb",
    "engineProtector",
    "windShield",
    "consumables",
  ];
  // 20% on final addon premium
  const g3 = [
    "zeroDepreciation",
    "returnToInvoice",
    "ncbProtection",
    "lopb",
    "tyreSecure",
    "keyReplace",
    "engineProtector",
    "windShield",
    "consumables",
  ];

  let addonDiscountPercentage;
  let addonsSelectedKeys = addOnsAndOthers?.selectedAddons;
  let addonsSelected = _.compact(
    addonsSelectedKeys?.map((v) =>
      Object.keys(quote?.addOnsData?.inBuilt).includes(v) ||
      quote?.addOnsData?.additional[v] * 1
        ? v
        : false
    )
  );

  //Addon Discount Calculation
  if (addOnsAndOthers?.selectedAddons?.includes("zeroDepreciation")) {
    if (_.intersection(g3, addonsSelected)?.length >= 4) {
      addonDiscountPercentage = 20;
    } else if (_.intersection(g2, addonsSelected)?.length === 3) {
      addonDiscountPercentage = 15;
    } else if (_.intersection(g1, addonsSelected)?.length === 2) {
      addonDiscountPercentage = 10;
    } else {
      addonDiscountPercentage = 0;
    }
  } else {
    addonDiscountPercentage = 0;
  }

  return {
    addonDiscountPercentage: addonDiscountPercentage,
    revisedNcb: Number(quote?.deductionOfNcb),
    totalPremiumc: Number(quote?.finalTotalDiscount),
  };
};

//Royal Sundaram | CV
export const royal_sundaram_cv = (
  quote,
  selectedAddons,
  additional,
  additionalList,
  totalPremiumA
) => {
  // re-eval required addons with others
  //additional & selected
  let totalAdditional = 0;

  //additional addons total
  selectedAddons.forEach((el) => {
    if (
      additional?.includes(!["imt23"].includes(el) ? "nomatch" : el) &&
      typeof additionalList[el] === "number"
    ) {
      totalAdditional = totalAdditional + Number(additionalList[el]);
    }
  });

  //Inbuilt
  let inbuilt = Object.keys(quote?.addOnsData?.inBuilt);
  let allAddons = ["imt23"];
  let inbuiltList = quote?.addOnsData?.inBuilt;
  let totalInbuilt = 0;
  //inbuilt addon total
  allAddons.forEach((el) => {
    if (inbuilt?.includes(el) && typeof inbuiltList[el] === "number") {
      totalInbuilt = totalInbuilt + Number(inbuiltList[el]);
    }
  });

  //other
  let others =
    (quote?.addOnsData?.other && Object.keys(quote?.addOnsData?.other)) || [];
  let othersList = quote?.addOnsData?.other;
  let totalOther = 0;
  others.forEach((el) => {
    if (typeof othersList[el] === "number") {
      totalOther = totalOther + Number(othersList[el]);
    }
  });
  //NCB calculations based on specific addons
  let NcbTotalAddon =
    Number(totalAdditional) + Number(totalInbuilt) + Number(totalOther);

  return {
    revisedNcb: Math.round(
      ((totalPremiumA * 1 +
        NcbTotalAddon * 1 -
        (Number(quote?.finalTotalDiscount) -
          Number(quote?.deductionOfNcb) -
          (Number(quote.tppdDiscount) ? Number(quote.tppdDiscount) : 0))) *
        Number(quote?.ncbDiscount)) /
        100
    ),
    totalPremiumc:
      Number(quote?.finalTotalDiscount ? quote?.finalTotalDiscount : 0) -
      //deducting the ncb sent by backend
      Number(quote?.deductionOfNcb ? quote?.deductionOfNcb : 0) +
      //calculating ncb and adding it to total discount
      Math.round(
        ((totalPremiumA * 1 +
          NcbTotalAddon * 1 -
          (Number(quote?.finalTotalDiscount ? quote?.finalTotalDiscount : 0) -
            Number(quote?.deductionOfNcb ? quote?.deductionOfNcb : 0) -
            Number(quote?.tppdDiscount ? quote?.tppdDiscount : 0))) *
          Number(quote?.ncbDiscount ? quote?.ncbDiscount : 0)) /
          100
      ),
  };
};
/* -x- royal sundaram -x- */

//IMT 23
//Applicable for "godigit", "shriram", "reliance", "sbi", "hdfc"
export const imt_exception = (
  selectedAddons,
  additional,
  additionalList,
  quote,
  inbuilt,
  inbuiltList
) => {
  let addedNcb = 0;
  let revisedNcb = 0;
  let totalPremiumc = 0;

  if (
    selectedAddons?.includes("imt23") &&
    additional?.includes("imt23") &&
    typeof additionalList["imt23"] === "number"
  ) {
    addedNcb =
      (Number(additionalList["imt23"]) * Number(quote?.ncbDiscount)) / 100;
  } else if (
    inbuilt?.includes("imt23") &&
    typeof inbuiltList["imt23"] === "number"
  ) {
    addedNcb = Number(
      (inbuiltList["imt23"] * Number(quote?.ncbDiscount)) / 100
    );
  }

  revisedNcb = Number(quote?.deductionOfNcb) + Number(addedNcb);
  totalPremiumc = Number(quote?.finalTotalDiscount) + Number(addedNcb);

  return { addedNcb, revisedNcb, totalPremiumc };
};

/* --- united india --- */
//United India | CAR
export const united_india_car = (
  quote,
  selectedAddons,
  additional,
  additionalList,
  totalPremiumA
) => {
  // re-eval required addons with others

  //additional & selected
  let totalAdditional = 0;

  selectedAddons.forEach((el) => {
    if (
      additional?.includes(
        !["zeroDepreciation", "returnToInvoice", "lopb"].includes(el)
          ? "nomatch"
          : el
      ) &&
      typeof additionalList[el] === "number"
    ) {
      totalAdditional = totalAdditional + Number(additionalList[el]);
    }
  });

  //Inbuilt
  let inbuilt = Object.keys(quote?.addOnsData?.inBuilt);
  let allAddons = ["zeroDepreciation", "returnToInvoice", "lopb"];
  let inbuiltList = quote?.addOnsData?.inBuilt;
  let totalInbuilt = 0;
  allAddons.forEach((el) => {
    if (inbuilt?.includes(el) && typeof inbuiltList[el] === "number") {
      totalInbuilt = totalInbuilt + Number(inbuiltList[el]);
    }
  });

  //other
  let others =
    (quote?.addOnsData?.other && Object.keys(quote?.addOnsData?.other)) || [];
  let othersList = quote?.addOnsData?.other;
  let totalOther = 0;
  others.forEach((el) => {
    if (typeof othersList[el] === "number") {
      totalOther = totalOther + Number(othersList[el]);
    }
  });

  let NcbTotalAddon =
    Number(totalAdditional) + Number(totalInbuilt) + Number(totalOther);

  return {
    revisedNcb: Math.round(
      ((totalPremiumA * 1 +
        NcbTotalAddon * 1 -
        (Number(quote?.finalTotalDiscount) -
          Number(quote?.deductionOfNcb) -
          (Number(quote.tppdDiscount) ? Number(quote.tppdDiscount) : 0))) *
        Number(quote?.ncbDiscount)) /
        100
    ),
    totalPremiumc:
      Number(quote?.finalTotalDiscount) -
      //deducting the ncb sent by backend
      Number(quote?.deductionOfNcb) +
      //calculating ncb and adding it to total discount
      Math.round(
        ((totalPremiumA * 1 +
          NcbTotalAddon * 1 -
          (Number(quote?.finalTotalDiscount ? quote?.finalTotalDiscount : 0) -
            Number(quote?.deductionOfNcb ? quote?.deductionOfNcb : 0) -
            Number(quote?.tppdDiscount ? quote?.tppdDiscount : 0))) *
          Number(quote?.ncbDiscount ? quote?.ncbDiscount : 0)) /
          100
      ),
  };
};
/* -x- united india -x- */
