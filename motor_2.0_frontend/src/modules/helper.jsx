import _ from "lodash";
import React from "react";
import { Badge } from "react-bootstrap";
import { currencyFormater } from "utils";
import { getAddonName } from "./quotesPage/quoteUtil";
import { TypeReturn } from "./type";
import { _discount } from "modules/quotesPage/quote-logic";

export const ElectricalValue = (quote, addOnsAndOthers) => {
  let val = 0;
  if (addOnsAndOthers?.selectedAddons?.includes("zeroDepreciation")) {
    val =
      val +
      (quote?.accessoriesAddons?.electrical?.elecZDPremium
        ? quote?.accessoriesAddons?.electrical?.elecZDPremium * 1
        : 0);
  }
  if (addOnsAndOthers?.selectedAddons?.includes("engineProtector")) {
    val =
      val +
      (quote?.accessoriesAddons?.electrical?.elecENGPremium
        ? quote?.accessoriesAddons?.electrical?.elecENGPremium * 1
        : 0);
  }
  if (addOnsAndOthers?.selectedAddons?.includes("returnToInvoice")) {
    val =
      val +
      (quote?.accessoriesAddons?.electrical?.elecRTIPremium
        ? quote?.accessoriesAddons?.electrical?.elecRTIPremium * 1
        : 0);
  }
  if (addOnsAndOthers?.selectedAddons?.includes("ncbProtection")) {
    val =
      val +
      (quote?.accessoriesAddons?.electrical?.elecNCBPremium
        ? quote?.accessoriesAddons?.electrical?.elecNCBPremium * 1
        : 0);
  }
  if (addOnsAndOthers?.selectedAddons?.includes("consumables")) {
    val =
      val +
      (quote?.accessoriesAddons?.electrical?.elecCOCPremium
        ? quote?.accessoriesAddons?.electrical?.elecCOCPremium * 1
        : 0);
  }
  return val;
};

export const NonElectricalValue = (quote, addOnsAndOthers) => {
  let val = 0;
  if (addOnsAndOthers?.selectedAddons?.includes("zeroDepreciation")) {
    val =
      val +
      (quote?.accessoriesAddons?.nonElectrical?.nonElecZDPremium
        ? quote?.accessoriesAddons?.nonElectrical?.nonElecZDPremium * 1
        : 0);
  }
  if (addOnsAndOthers?.selectedAddons?.includes("engineProtector")) {
    val =
      val +
      (quote?.accessoriesAddons?.nonElectrical?.nonElecENGPremium
        ? quote?.accessoriesAddons?.nonElectrical?.nonElecENGPremium * 1
        : 0);
  }
  if (addOnsAndOthers?.selectedAddons?.includes("returnToInvoice")) {
    val =
      val +
      (quote?.accessoriesAddons?.nonElectrical?.elecRTIPremium
        ? quote?.accessoriesAddons?.nonElectrical?.elecRTIPremium * 1
        : 0);
  }
  if (addOnsAndOthers?.selectedAddons?.includes("ncbProtection")) {
    val =
      val +
      (quote?.accessoriesAddons?.nonElectrical?.nonElecNCBPremium
        ? quote?.accessoriesAddons?.nonElectrical?.nonElecNCBPremium * 1
        : 0);
  }
  if (addOnsAndOthers?.selectedAddons?.includes("consumables")) {
    val =
      val +
      (quote?.accessoriesAddons?.nonElectrical?.nonElecCOCPremium
        ? quote?.accessoriesAddons?.nonElectrical?.nonElecCOCPremium * 1
        : 0);
  }
  return val;
};

export const BiFuelValue = (quote, addOnsAndOthers) => {
  let val = 0;
  if (addOnsAndOthers?.selectedAddons?.includes("zeroDepreciation")) {
    val =
      val +
      (quote?.accessoriesAddons?.lpgCngKit?.bifuelZDPremium
        ? quote?.accessoriesAddons?.lpgCngKit?.bifuelZDPremium * 1
        : 0);
  }
  if (addOnsAndOthers?.selectedAddons?.includes("engineProtector")) {
    val =
      val +
      (quote?.accessoriesAddons?.lpgCngKit?.bifuelENGPremium
        ? quote?.accessoriesAddons?.lpgCngKit?.bifuelENGPremium * 1
        : 0);
  }
  if (addOnsAndOthers?.selectedAddons?.includes("returnToInvoice")) {
    val =
      val +
      (quote?.accessoriesAddons?.lpgCngKit?.bifuelRTIPremium
        ? quote?.accessoriesAddons?.lpgCngKit?.bifuelRTIPremium * 1
        : 0);
  }
  if (addOnsAndOthers?.selectedAddons?.includes("ncbProtection")) {
    val =
      val +
      (quote?.accessoriesAddons?.lpgCngKit?.bifuelNCBPremium
        ? quote?.accessoriesAddons?.lpgCngKit?.bifuelNCBPremium * 1
        : 0);
  }
  if (addOnsAndOthers?.selectedAddons?.includes("consumables")) {
    val =
      val +
      (quote?.accessoriesAddons?.lpgCngKit?.bifuelCOCPremium
        ? quote?.accessoriesAddons?.lpgCngKit?.bifuelCOCPremium * 1
        : 0);
  }
  return val;
};

export const Calculation = ({
  quotes,
  addOnsAndOthers,
  type,
  temp_data,
  setQuoteComprehesiveGrouped1,
  addonDiscountPercentage,
  setAddonDiscountPercentage,
}) => {
  if (quotes) {
    let sortedAndGrouped = quotes.map((quote) => {
      let additional = quote?.addOnsData?.additional
        ? Object.keys(quote?.addOnsData?.additional)
        : [];
      let additionalList = quote?.addOnsData?.additional;
      let selectedAddons = addOnsAndOthers?.selectedAddons || [];
      let totalAdditional = 0;
      let totalPayableAmountWithAddon = 0;
      let totalPremiumA =
        quote?.finalOdPremium * 1 +
        ElectricalValue(quote) +
        NonElectricalValue(quote) +
        BiFuelValue(quote);

      //ncb calculation / discount part
      let totalPremiumc = quote?.finalTotalDiscount;
      let revisedNcb = quote?.deductionOfNcb;
      let otherDiscounts = quote?.icVehicleDiscount || 0;
      let addedNcb = 0;

      //addon calculation

      selectedAddons.forEach((el) => {
        if (
          !_.isEmpty(additional) &&
          additional?.includes(el) &&
          typeof additionalList[el] === "number"
        ) {
          totalAdditional =
            totalAdditional +
            _discount(
              additionalList[el],
              addonDiscountPercentage,
              quote?.companyAlias,
              el
            );
        }
      });

      let inbuilt = quote?.addOnsData?.inBuilt
        ? Object.keys(quote?.addOnsData?.inBuilt)
        : [];
      let allAddons = [
        "zeroDepreciation",
        "roadSideAssistance",
        "imt23",
        "keyReplace",
        "engineProtector",
        "ncbProtection",
        "consumables",
        "tyreSecure",
        "returnToInvoice",
        "lopb",
        "emergencyMedicalExpenses",
        "windShield",
        "emiProtection",
        "additionalTowing",
        "batteryProtect",
      ];
      let inbuiltList = quote?.addOnsData?.inBuilt;
      let totalInbuilt = 0;
      allAddons.forEach((el) => {
        if (
          !_.isEmpty(inbuilt) &&
          inbuilt?.includes(el) &&
          typeof inbuiltList[el] === "number"
        ) {
          totalInbuilt =
            totalInbuilt +
            _discount(
              inbuiltList[el],
              addonDiscountPercentage,
              quote?.companyAlias,
              el
            );
        }
      });

      let others =
        (quote?.addOnsData?.other && Object.keys(quote?.addOnsData?.other)) ||
        [];

      let othersList = quote?.addOnsData?.other;

      let totalOther = 0;
      others.forEach((el) => {
        if (typeof othersList[el] === "number") {
          totalOther = totalOther + Number(othersList[el]);
        }
      });
      let totalAddon =
        Number(totalAdditional) + Number(totalInbuilt) + Number(totalOther);

      if (quote?.company_alias === "oriental" && TypeReturn(type) === "cv") {
        // For Oriental CV, you need to use following formula:NCB premium = (Total OD premium + Addons - discounts(anti theft)) * applicable NCB
        let extraOtherDiscounts = 0;
        let discountPercentageOriental = 0.7;
        //for ncb zd is included.
        extraOtherDiscounts = totalAddon * discountPercentageOriental;
        //for extradiscounts we don't need ZeroDep hence recalc total (addon * discount %) without zd ------
        //additional & selected
        let totalAdditional = 0;
        selectedAddons.forEach((el) => {
          if (
            additional?.includes(el === "zeroDepreciation" ? "nomatch" : el) &&
            typeof additionalList[el] === "number"
          ) {
            totalAdditional = totalAdditional + Number(additionalList[el]);
          }
        });
        //Inbuilt
        let inbuilt = Object.keys(quote?.addOnsData?.inBuilt);
        let allAddons = [
          "roadSideAssistance",
          "imt23",
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
        //other
        let others =
          (quote?.addOnsData?.other && Object.keys(quote?.addOnsData?.other)) ||
          [];
        let othersList = quote?.addOnsData?.other;
        let totalOther = 0;
        others.forEach((el) => {
          if (typeof othersList[el] === "number") {
            totalOther = totalOther + Number(othersList[el]);
          }
        });

        let extraOtherDiscounts2 =
          (Number(totalAdditional) +
            Number(totalInbuilt) +
            Number(totalOther)) *
          discountPercentageOriental;
        addedNcb =
          ((totalAddon - extraOtherDiscounts2) * Number(quote?.ncbDiscount)) /
          100;

        revisedNcb = Number(quote?.deductionOfNcb) + Number(addedNcb);
        otherDiscounts =
          (quote?.icVehicleDiscount || 0) + Number(extraOtherDiscounts2);

        totalPremiumc =
          Number(quote?.finalTotalDiscount) +
          Number(addedNcb) +
          Number(extraOtherDiscounts2);
      } else if (
        (((selectedAddons?.includes("imt23") &&
          additional?.includes("imt23") &&
          typeof additionalList["imt23"] === "number") ||
          inbuilt?.includes("imt23")) &&
          quote?.company_alias === "hdfc_ergo") ||
        quote?.company_alias === "godigit" ||
        quote?.company_alias === "shriram" ||
        quote?.company_alias === "reliance" ||
        quote?.company_alias === "sbi"
      ) {
        if (
          selectedAddons?.includes("imt23") &&
          additional?.includes("imt23") &&
          typeof additionalList["imt23"] === "number"
        ) {
          addedNcb =
            (Number(additionalList["imt23"]) * Number(quote?.ncbDiscount)) /
            100;
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
      } else if (
        ((selectedAddons?.includes("imt23") &&
          additional?.includes("imt23") &&
          typeof additionalList["imt23"] === "number") ||
          inbuilt?.includes("imt23")) &&
        quote?.company_alias === "icici_lombard"
      ) {
        let othrDiscount =
          quote?.icVehicleDiscount * 1 ? quote?.icVehicleDiscount * 1 : 0;

        otherDiscounts = othrDiscount;
        revisedNcb =
          ((totalPremiumA +
            (selectedAddons?.includes("imt23") &&
            additional?.includes("imt23") &&
            additionalList["imt23"] * 1
              ? additionalList["imt23"] * 1
              : inbuiltList["imt23"] * 1)) *
            Number(quote?.ncbDiscount)) /
          100;
        totalPremiumc =
          ((selectedAddons?.includes("imt23") &&
          additional?.includes("imt23") &&
          additionalList["imt23"] * 1
            ? additionalList["imt23"] * 1
            : inbuiltList["imt23"] * 1) *
            Number(quote?.ncbDiscount)) /
            100 +
          (quote?.icVehicleDiscount * 1 ? quote?.icVehicleDiscount * 1 : 0) +
          (quote?.tppdDiscount * 1 ? quote?.tppdDiscount * 1 : 0);
      } else if (
        TypeReturn(type) === "cv" &&
        quote?.company_alias === "magma"
      ) {
        if (
          ((selectedAddons?.includes("imt23") &&
            additional?.includes("imt23") &&
            typeof additionalList["imt23"] === "number") ||
            inbuilt?.includes("imt23")) &&
          quote?.company_alias === "magma"
        ) {
          if (quote?.imt23Discount * 1) {
            let otherDiscounts =
              quote?.icVehicleDiscount * 1
                ? quote?.icVehicleDiscount * 1
                : 0 + quote?.imt23Discount * 1;
            revisedNcb =
              ((totalPremiumA +
                (selectedAddons?.includes("imt23") &&
                additional?.includes("imt23") &&
                additionalList["imt23"] * 1
                  ? additionalList["imt23"] * 1
                  : inbuiltList["imt23"] * 1) -
                otherDiscounts) *
                Number(quote?.ncbDiscount)) /
              100;
            totalPremiumc =
              revisedNcb +
              otherDiscounts +
              (quote?.tppdDiscount * 1 ? quote?.tppdDiscount * 1 : 0);
          } else {
            otherDiscounts = quote?.icVehicleDiscount || 0;
            revisedNcb =
              ((totalPremiumA +
                (selectedAddons?.includes("imt23") &&
                additional?.includes("imt23") &&
                additionalList["imt23"] * 1
                  ? additionalList["imt23"] * 1
                  : inbuiltList["imt23"] * 1) -
                otherDiscounts) *
                Number(quote?.ncbDiscount)) /
              100;
            totalPremiumc =
              revisedNcb +
              otherDiscounts +
              (quote?.tppdDiscount * 1 ? quote?.tppdDiscount * 1 : 0);
          }
        } else {
          otherDiscounts = quote?.icVehicleDiscount || 0;
          revisedNcb =
            ((totalPremiumA - otherDiscounts) * Number(quote?.ncbDiscount)) /
            100;
          totalPremiumc =
            revisedNcb +
            otherDiscounts +
            (quote?.tppdDiscount * 1 ? quote?.tppdDiscount * 1 : 0);
        }
      } else if (
        ((selectedAddons?.includes("imt23") &&
          additional?.includes("imt23") &&
          typeof additionalList["imt23"] === "number") ||
          inbuilt?.includes("imt23")) &&
        quote?.company_alias === "bajaj_allianz" &&
        temp_data?.journeyCategory === "GCV" &&
        quote?.isCvJsonKit
      ) {
        if (
          (selectedAddons?.includes("imt23") &&
            additional?.includes("imt23") &&
            typeof additionalList["imt23"] === "number") ||
          (inbuilt?.includes("imt23") &&
            typeof inbuiltList["imt23"] === "number")
        ) {
          let othrDiscount =
            (quote?.icVehicleDiscount * 1 ? quote?.icVehicleDiscount * 1 : 0) *
            1.15;

          otherDiscounts = othrDiscount;
          revisedNcb =
            ((totalPremiumA +
              (selectedAddons?.includes("imt23") &&
              additional?.includes("imt23") &&
              additionalList["imt23"] * 1
                ? additionalList["imt23"] * 1
                : inbuiltList["imt23"] * 1)) *
              Number(quote?.ncbDiscount)) /
            100;
          totalPremiumc =
            ((selectedAddons?.includes("imt23") &&
            additional?.includes("imt23") &&
            additionalList["imt23"] * 1
              ? additionalList["imt23"] * 1
              : inbuiltList["imt23"] * 1) *
              Number(quote?.ncbDiscount)) /
              100 +
            (quote?.icVehicleDiscount * 1 ? quote?.icVehicleDiscount * 1 : 0) *
              1.15 +
            (quote?.tppdDiscount * 1 ? quote?.tppdDiscount * 1 : 0);
        }
      } else if (
        ((selectedAddons?.includes("imt23") &&
          additional?.includes("imt23") &&
          typeof additionalList["imt23"] === "number") ||
          inbuilt?.includes("imt23")) &&
        quote?.company_alias === "universal_sompo" &&
        temp_data?.journeyCategory === "GCV" &&
        quote?.isCvJsonKit
      ) {
        if (
          (selectedAddons?.includes("imt23") &&
            additional?.includes("imt23") &&
            typeof additionalList["imt23"] === "number") ||
          (inbuilt?.includes("imt23") &&
            typeof inbuiltList["imt23"] === "number")
        ) {
          let othrDiscount =
            (quote?.icVehicleDiscount * 1 ? quote?.icVehicleDiscount * 1 : 0) *
            1.15;

          otherDiscounts = othrDiscount;
          revisedNcb = Number(quote?.deductionOfNcb) * 1.15;
          totalPremiumc =
            Number(quote?.deductionOfNcb) * 1.15 +
            (quote?.icVehicleDiscount * 1 ? quote?.icVehicleDiscount * 1 : 0) *
              1.15 +
            (quote?.tppdDiscount * 1 ? quote?.tppdDiscount * 1 : 0);
        }
      } else if (
        quote?.company_alias === "royal_sundaram" &&
        TypeReturn(type) === "car" &&
        addOnsAndOthers?.selectedAddons?.includes("zeroDepreciation")
      ) {
        const g1 = [
          "zeroDepreciation",
          "returnToInvoice",
          "ncbProtection",
          "engineProtector",
          "consumables",
          "windShield",
        ]; // 10 % on final addons
        const g2 = [
          "zeroDepreciation",
          "returnToInvoice",
          "ncbProtection",
          "lopb",
          "engineProtector",
          "consumables",
          "windShield",
        ]; //15% on finaladdons
        const g3 = [
          "zeroDepreciation",
          "returnToInvoice",
          "ncbProtection",
          "lopb",
          "tyreSecure",
          "keyReplace",
          "engineProtector",
          "consumables",
          "windShield",
        ]; // 20 % on final addons
        let addonsSelectedKeys = addOnsAndOthers?.selectedAddons;
        let addonsSelected = _.compact(
          addonsSelectedKeys.map((v) =>
            Object.keys(quote?.addOnsData?.inBuilt).includes(v) ||
            quote?.addOnsData?.additional[v] * 1
              ? v
              : false
          )
        );

        if (_.intersection(g3, addonsSelected)?.length >= 4) {
          setAddonDiscountPercentage(20);
          revisedNcb = Number(quote?.deductionOfNcb);
          totalPremiumc = Number(quote?.finalTotalDiscount);
        } else if (_.intersection(g2, addonsSelected)?.length === 3) {
          setAddonDiscountPercentage(15);
          revisedNcb = Number(quote?.deductionOfNcb);
          totalPremiumc = Number(quote?.finalTotalDiscount);
        } else if (_.intersection(g1, addonsSelected)?.length === 2) {
          setAddonDiscountPercentage(10);
          revisedNcb = Number(quote?.deductionOfNcb);
          totalPremiumc = Number(quote?.finalTotalDiscount);
        } else {
          setAddonDiscountPercentage(0);
          revisedNcb = Number(quote?.deductionOfNcb);
          totalPremiumc = Number(quote?.finalTotalDiscount);
        }
        otherDiscounts = quote?.icVehicleDiscount || 0;
      } else if (
        quote?.company_alias === "royal_sundaram" &&
        TypeReturn(type) === "car" &&
        !addOnsAndOthers?.selectedAddons?.includes("zeroDepreciation")
      ) {
        setAddonDiscountPercentage(0);
        revisedNcb = Number(quote?.deductionOfNcb);
        totalPremiumc = Number(quote?.finalTotalDiscount);
        otherDiscounts = quote?.icVehicleDiscount || 0;
      }
      // else if (
      //   quote?.company_alias === "hdfc_ergo" &&
      //   temp_data?.journeyCategory !== "GCV"
      // ) {
      //   revisedNcb = Number(
      //     (totalPremiumA * 1 * Number(quote?.ncbDiscount)) / 100
      //   );
      //   totalPremiumc =
      //     Number(quote?.finalTotalDiscount) +
      //     Number((totalPremiumA * 1 * Number(quote?.ncbDiscount)) / 100) -
      //     Number(quote?.deductionOfNcb);
      // }
      else if (
        quote?.company_alias === "oriental" &&
        TypeReturn(type) === "car"
      ) {
        // re-eval required addons with others
        //additional & selected
        let totalAdditional = 0;
        selectedAddons.forEach((el) => {
          if (
            additional?.includes(
              ![
                "zeroDepreciation",
                "engineProtector",
                "returnToInvoice",
                "lopb",
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
          "zeroDepreciation",
          "engineProtector",
          "returnToInvoice",
          "lopb",
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
          (quote?.addOnsData?.other && Object.keys(quote?.addOnsData?.other)) ||
          [];
        let othersList = quote?.addOnsData?.other;
        let totalOther = 0;
        others.forEach((el) => {
          if (typeof othersList[el] === "number") {
            totalOther = totalOther + Number(othersList[el]);
          }
        });
        let NcbTotalAddon =
          Number(totalAdditional) + Number(totalInbuilt) + Number(totalOther);
        revisedNcb = Math.round(
          ((totalPremiumA * 1 +
            NcbTotalAddon * 1 -
            (Number(quote?.finalTotalDiscount) -
              Number(quote?.deductionOfNcb) -
              (Number(quote.tppdDiscount) ? Number(quote.tppdDiscount) : 0))) *
            Number(quote?.ncbDiscount)) /
            100
        );
        totalPremiumc =
          Number(quote?.finalTotalDiscount) -
          //deducting the ncb sent by backend
          Number(quote?.deductionOfNcb) +
          //calculating ncb and adding it to total discount
          Math.round(
            ((totalPremiumA * 1 +
              NcbTotalAddon * 1 -
              (Number(quote?.finalTotalDiscount) -
                Number(quote?.deductionOfNcb) -
                (Number(quote.tppdDiscount)
                  ? Number(quote.tppdDiscount)
                  : 0))) *
              Number(quote?.ncbDiscount)) /
              100
          );
      } else if (
        quote?.company_alias === "united_india" &&
        TypeReturn(type) === "car"
      ) {
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
          (quote?.addOnsData?.other && Object.keys(quote?.addOnsData?.other)) ||
          [];
        let othersList = quote?.addOnsData?.other;
        let totalOther = 0;
        others.forEach((el) => {
          if (typeof othersList[el] === "number") {
            totalOther = totalOther + Number(othersList[el]);
          }
        });
        let NcbTotalAddon =
          Number(totalAdditional) + Number(totalInbuilt) + Number(totalOther);
        revisedNcb = Math.round(
          ((totalPremiumA * 1 +
            NcbTotalAddon * 1 -
            (Number(quote?.finalTotalDiscount) -
              Number(quote?.deductionOfNcb) -
              (Number(quote.tppdDiscount) ? Number(quote.tppdDiscount) : 0))) *
            Number(quote?.ncbDiscount)) /
            100
        );
        totalPremiumc =
          Number(quote?.finalTotalDiscount) -
          //deducting the ncb sent by backend
          Number(quote?.deductionOfNcb) +
          //calculating ncb and adding it to total discount
          Math.round(
            ((totalPremiumA * 1 +
              NcbTotalAddon * 1 -
              (Number(
                quote?.finalTotalDiscount ? quote?.finalTotalDiscount : 0
              ) -
                Number(quote?.deductionOfNcb ? quote?.deductionOfNcb : 0) -
                Number(quote?.tppdDiscount * 1 ? quote?.tppdDiscount : 0))) *
              Number(quote?.ncbDiscount ? quote?.ncbDiscount : 0)) /
              100
          );
      } else {
        revisedNcb = Number(quote?.deductionOfNcb);
        totalPremiumc = Number(quote?.finalTotalDiscount);
        otherDiscounts = quote?.icVehicleDiscount || 0;
      }

      //////cpa part
      let totalPremiumB =
        quote?.finalTpPremium * 1 ? quote?.finalTpPremium * 1 : 0;

      let selectedCpa = addOnsAndOthers?.selectedCpa;

      let cpa = 0;

      if (selectedCpa?.includes("Compulsory Personal Accident")) {
        if (!_.isEmpty(addOnsAndOthers?.isTenure)) {
          cpa = quote?.multiYearCpa ? quote?.multiYearCpa : 0;
        } else {
          cpa = quote?.compulsoryPaOwnDriver;
        }
      } else {
        cpa = 0;
      }

      totalPremiumB =
        (Number(quote?.finalTpPremium) || 0) +
        Number(cpa) + //adding un-named passenger cover in multi year cpa sbi.
        (quote?.companyAlias === "sbi" &&
        selectedCpa?.includes("Compulsory Personal Accident") &&
        !_.isEmpty(addOnsAndOthers?.isTenure) &&
        quote?.coverUnnamedPassengerValue * 1
          ? quote?.coverUnnamedPassengerValue *
            (TypeReturn(type) === "bike" ? 4 : 2)
          : 0) +
        //adding additional paid driver cover in multi year cpa sbi.
        (quote?.companyAlias === "sbi" &&
        selectedCpa?.includes("Compulsory Personal Accident") &&
        !_.isEmpty(addOnsAndOthers?.isTenure) &&
        quote?.motorAdditionalPaidDriver * 1
          ? quote?.motorAdditionalPaidDriver *
            (TypeReturn(type) === "bike" ? 4 : 2)
          : 0);

      let applicableAddons = [];
      if (temp_data?.tab !== "tab2") {
        var addonsSelectedList = [];
        if (!_.isEmpty(selectedAddons) || !_.isEmpty(inbuilt)) {
          selectedAddons.forEach((el) => {
            if (additional?.includes(el) && Number(additionalList[el]) !== 0) {
              var newList = {
                name: getAddonName(el),
                premium: Number(additionalList[el]),
                ...(el === "zeroDepreciation" 
                  && quote?.companyAlias === "godigit" && {claimCovered : addOnsAndOthers?.dbStructure?.addonData?.addons.filter(
                  (x) => x?.name === "Zero Depreciation"
                )?.[0]?.claimCovered}),
              };
              addonsSelectedList.push(newList);
            }
          });

          inbuilt.forEach((el) => {
            var newList = {
              name: getAddonName(el),
              premium: Number(inbuiltList[el]),
              ...(el === "zeroDepreciation" 
                && quote?.companyAlias === "godigit" && {claimCovered : addOnsAndOthers?.dbStructure?.addonData?.addons.filter(
                (x) => x?.name === "Zero Depreciation"
              )?.[0]?.claimCovered}),
            };
            addonsSelectedList.push(newList);
          });

          applicableAddons = addonsSelectedList;
        } else {
          applicableAddons = [];
        }
      }

      //uv loading
      let uwLoading = 0;
      if (
        quote?.companyAlias === "shriram" &&
        TypeReturn(type) === "bike" &&
        (quote?.policyType === "Comprehensive" ||
          quote?.policyType === "Own Damage") &&
        totalPremiumA +
          totalAddon -
          totalPremiumc +
          (quote?.tppdDiscount * 1 ? quote?.tppdDiscount * 1 : 0) <
          50
      ) {
        uwLoading =
          50 -
          (totalPremiumA +
            totalAddon -
            totalPremiumc +
            (quote?.tppdDiscount * 1 ? quote?.tppdDiscount * 1 : 0));
      } else {
        uwLoading = 0;
      }

      let totalLoading = 0;

      if (true) {
        if (
          Number(quote?.totalLoadingAmount) > 0 ||
          Number(quote?.underwritingLoadingAmount)
        ) {
          totalLoading =
            Number(quote?.totalLoadingAmount) ||
            Number(quote?.underwritingLoadingAmount);
        } else {
          totalLoading = 0;
        }
      } else {
        totalLoading = 0;
      }

      let totalPremium =
        Number(totalAddon) +
        Number(totalPremiumA) +
        Number(totalPremiumB) -
        Number(totalPremiumc) +
        Number(uwLoading) +
        Number(totalLoading);
      let totalPremiumGst = parseInt((totalPremium * 18) / 100);

      if (temp_data?.journeyCategory === "GCV") {
        if (quote.company_alias === "oriental") {
          //In Oriental , TPPD discount is not added to third party liability for GST calc
          totalPremiumGst =
            parseInt(((totalPremium - quote?.tppdPremiumAmount) * 18) / 100) +
            (quote?.tppdPremiumAmount * 12) / 100;
        } else if (quote.company_alias === "sbi") {
          //In sbi , Basic tp - 12%, rest 18%
          totalPremiumGst =
            parseInt(((totalPremium - quote?.tppdPremiumAmount) * 18) / 100) +
            (quote?.tppdPremiumAmount * 12) / 100;
        } else if (quote.company_alias === "godigit") {
          // GST calc for other IC's in GCV
          totalPremiumGst = parseInt(
            //basic tp
            ((quote?.tppdPremiumAmount -
              //tppd discount
              (Number(quote?.tppdDiscount) ? Number(quote?.tppdDiscount) : 0) +
              //cng/lpg
              (quote?.cngLpgTp * 1 ? quote?.cngLpgTp * 1 : 0)) *
              12) /
              100 +
              //rest of the liability values
              ((totalPremiumB -
                quote?.tppdPremiumAmount +
                //total od + addons - ncb
                totalPremiumA +
                totalAddon -
                (totalPremiumc -
                  (Number(quote?.tppdDiscount)
                    ? Number(quote?.tppdDiscount)
                    : 0)) -
                (quote?.cngLpgTp * 1 ? quote?.cngLpgTp * 1 : 0)) *
                18) /
                100
          );
        } else if (quote.company_alias === "universal_sompo") {
          // GST calc for other IC's in GCV
          totalPremiumGst = parseInt(
            ((totalPremium -
              quote?.tppdPremiumAmount +
              (Number(quote?.tppdDiscount) ? Number(quote?.tppdDiscount) : 0)) *
              18) /
              100 +
              (quote?.tppdPremiumAmount * 0.12 -
                (Number(quote?.tppdDiscount)
                  ? Number(quote?.tppdDiscount)
                  : 0) *
                  0.18)
          );
        } else {
          // GST calc for other IC's in GCV
          totalPremiumGst =
            parseInt(
              ((totalPremium -
                quote?.tppdPremiumAmount +
                (Number(quote?.tppdDiscount)
                  ? Number(quote?.tppdDiscount)
                  : 0)) *
                18) /
                100
            ) +
            ((quote?.tppdPremiumAmount -
              (Number(quote?.tppdDiscount) ? Number(quote?.tppdDiscount) : 0)) *
              12) /
              100;
        }
      }
      let FinalPremium = totalPremium + totalPremiumGst;
      return {
        ...quote,
        totalPremiumA1: totalPremiumA,
        totalAddon1: totalAddon,
        finalPremium1: FinalPremium,
        totalPremium1: totalPremium,
        totalPremiumB1: totalPremiumB,
        totalPremiumc1: totalPremiumc,
        addonDiscountPercentage1: addonDiscountPercentage,
        applicableAddons1: applicableAddons,
        gst1: totalPremiumGst,
        revisedNcb1: revisedNcb,
      };
    });

    let sortedGroupedcomp1 = _.sortBy(sortedAndGrouped, [
      "totalPayableAmountWithAddon",
    ]);

    setQuoteComprehesiveGrouped1(sortedGroupedcomp1);
  }
};

// used in know more popup
export const GetAddonValue = (
  addonName,
  addonDiscountPercentage,
  quote,
  addOnsAndOthers,
  lessthan993
) => {
  let inbuilt = Object.keys(quote?.addOnsData?.inBuilt);
  let additional = Object.keys(quote?.addOnsData?.additional);
  let selectedAddons = addOnsAndOthers?.selectedAddons;

  if (inbuilt?.includes(addonName)) {
    return (
      <span
        style={{
          ...(lessthan993 && { fontSize: "10px" }),
        }}
      >
        {Number(quote?.addOnsData?.inBuilt[addonName]) !== 0 ? (
          `₹ ${currencyFormater(
            parseInt(
              _discount(
                quote?.addOnsData?.inBuilt[addonName],
                addonDiscountPercentage,
                quote?.companyAlias,
                addonName
              )
            )
          )}`
        ) : (
          <>
            {addonName === "roadSideAssistance" &&
            quote?.company_alias === "reliance" ? (
              <>-</>
            ) : (
              <>
                {lessthan993 ? (
                  <>
                    {" "}
                    <i className="fa fa-check" style={{ color: "green" }}></i>
                  </>
                ) : (
                  <>
                    <Badge
                      variant="primary"
                      style={{ position: "relative", bottom: "2px" }}
                    >
                      Included
                    </Badge>
                  </>
                )}
              </>
            )}
          </>
        )}
      </span>
    );
  } else if (
    additional?.includes(addonName) &&
    selectedAddons?.includes(addonName) &&
    Number(quote?.addOnsData?.additional[addonName]) !== 0 &&
    typeof quote?.addOnsData?.additional[addonName] === "number"
  ) {
    return `₹ ${currencyFormater(
      _discount(
        quote?.addOnsData?.additional[addonName],
        addonDiscountPercentage,
        quote?.companyAlias,
        addonName
      )
    )}`;
  } else if (
    additional?.includes(addonName) &&
    Number(quote?.addOnsData?.additional[addonName]) === 0
  ) {
    return "N/A";
  } else if (
    !additional?.includes(addonName) &&
    selectedAddons?.includes(addonName)
  ) {
    return "N/A";
  } else if (Number(quote?.addOnsData?.additional[addonName]) === 0) {
    return "N/A";
  } else if (
    additional?.includes(addonName) &&
    !selectedAddons?.includes(addonName)
  ) {
    return "N/S";
  } else {
    return "N/A";
  }
};
