import { TypeReturn } from "modules/type";
import {
  calculateCpaValue,
  calculateCpaValueForCv,
  createAddonRow,
} from "../../helper";
import {
  addonForBike,
  addonForCar,
  addonForCv,
  addonFullNameForBike,
  addonFullNameForCar,
  addonFullNameForCv,
  addonFullNameForGcv,
} from "./addon-helper";

export const getAddonArray = (addonProps) => {
  // prettier-ignore
  const { type, temp_data, newGroupedQuotesCompare, addOnsAndOthers } = addonProps;
  let AddonArray = [];
  if (TypeReturn(type) === "car") {
    AddonArray.push(
      addonFullNameForCar.filter((x) =>
        temp_data?.odOnly ||
        temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C"
          ? x !== "Compulsory Personal Accident"
          : x
      )
    );
  } else if (TypeReturn(type) === "bike") {
    AddonArray.push(
      addonFullNameForBike.filter((x) =>
        temp_data?.odOnly ||
        temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C"
          ? x !== "Compulsory Personal Accident"
          : x
      )
    );
  } else if (TypeReturn(type) === "cv") {
    if (temp_data.journeyCategory === "GCV") {
      AddonArray.push(
        addonFullNameForGcv.filter((x) =>
          temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C"
            ? x !== "Compulsory Personal Accident"
            : x
        )
      );
    } else {
      AddonArray.push(
        addonFullNameForCv.filter((x) =>
          temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C"
            ? x !== "Compulsory Personal Accident"
            : x
        )
      );
    }
  }
  if (TypeReturn(type) === "car") {
    if (
      temp_data?.odOnly ||
      temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C"
    ) {
      // prettier-ignore
      const addonRow = createAddonRow(addonForCar, newGroupedQuotesCompare, 0, addOnsAndOthers);
      AddonArray.push(addonRow);
    } else {
      // prettier-ignore
      const cpaValue = calculateCpaValue(addOnsAndOthers, newGroupedQuotesCompare, 0);
      // prettier-ignore
      const addonRow = createAddonRow(addonForCar, newGroupedQuotesCompare, 0, addOnsAndOthers);
      addonRow.unshift(cpaValue);
      AddonArray.push(addonRow);
    }
  } else if (TypeReturn(type) === "bike") {
    if (
      temp_data?.odOnly ||
      temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C"
    ) {
      // prettier-ignore
      const addonRow = createAddonRow(addonForBike, newGroupedQuotesCompare, 0, addOnsAndOthers);
      AddonArray.push(addonRow);
    } else {
      if (temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C") {
        // prettier-ignore
        const addonRow = createAddonRow(addonForBike, newGroupedQuotesCompare, 0, addOnsAndOthers);
        AddonArray.push(addonRow);
      } else {
        // prettier-ignore
        const cpaValue = calculateCpaValue(addOnsAndOthers, newGroupedQuotesCompare, 0);
        // prettier-ignore
        const addonRow = createAddonRow(addonForBike, newGroupedQuotesCompare, 0, addOnsAndOthers);
        addonRow.unshift(cpaValue);
        AddonArray.push(addonRow);
      }
    }
  } else if (TypeReturn(type) === "cv") {
    if (temp_data.journeyCategory === "GCV") {
      // prettier-ignore
      const cpaValue = calculateCpaValueForCv(addOnsAndOthers, newGroupedQuotesCompare, 0, temp_data);
      // prettier-ignore
      const addonRow = createAddonRow(addonForCv, newGroupedQuotesCompare, 0, addOnsAndOthers);
      addonRow.unshift(cpaValue);
      AddonArray.push(addonRow);
    } else {
      if (temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C") {
        // prettier-ignore
        const addonRow = createAddonRow(addonForCv, newGroupedQuotesCompare, 0, addOnsAndOthers);
        AddonArray.push(addonRow);
      } else {
        // prettier-ignore
        const cpaValue = calculateCpaValueForCv(addOnsAndOthers, newGroupedQuotesCompare, 0, temp_data);
        // prettier-ignore
        const addonRow = createAddonRow(addonForCv, newGroupedQuotesCompare, 0, addOnsAndOthers);
        addonRow.unshift(cpaValue);
        AddonArray.push(addonRow);
      }
    }
  }
  if (TypeReturn(type) === "car") {
    if (
      temp_data?.odOnly ||
      temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C"
    ) {
      // prettier-ignore
      const addonRow = createAddonRow(addonForCar, newGroupedQuotesCompare, 1, addOnsAndOthers);
      AddonArray.push(addonRow);
    } else {
      // prettier-ignore
      const cpaValue = calculateCpaValue(addOnsAndOthers, newGroupedQuotesCompare, 1);
      // prettier-ignore
      const addonRow = createAddonRow(addonForCar, newGroupedQuotesCompare, 1, addOnsAndOthers);
      addonRow.unshift(cpaValue);
      AddonArray.push(addonRow);
    }
  } else if (TypeReturn(type) === "bike") {
    if (
      temp_data?.odOnly ||
      temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C"
    ) {
      // prettier-ignore
      const addonRow = createAddonRow(addonForBike, newGroupedQuotesCompare, 1, addOnsAndOthers);
      AddonArray.push(addonRow);
    } else {
      // prettier-ignore
      const cpaValue = calculateCpaValue(addOnsAndOthers, newGroupedQuotesCompare, 1);
      // prettier-ignore
      const addonRow = createAddonRow(addonForBike, newGroupedQuotesCompare, 1, addOnsAndOthers);
      addonRow.unshift(cpaValue);
      AddonArray.push(addonRow);
    }
  } else if (TypeReturn(type) === "cv") {
    if (temp_data.journeyCategory === "GCV") {
      // prettier-ignore
      const cpaValue = calculateCpaValueForCv(addOnsAndOthers, newGroupedQuotesCompare, 1, temp_data);
      // prettier-ignore
      const addonRow = createAddonRow(addonForCv, newGroupedQuotesCompare, 1, addOnsAndOthers);
      addonRow.unshift(cpaValue);
      AddonArray.push(addonRow);
    } else {
      if (temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C") {
        // prettier-ignore
        const addonRow = createAddonRow(addonForCv, newGroupedQuotesCompare, 1, addOnsAndOthers);
        AddonArray.push(addonRow);
      } else {
        // prettier-ignore
        const cpaValue = calculateCpaValueForCv(addOnsAndOthers, newGroupedQuotesCompare, 1, temp_data);
        // prettier-ignore
        const addonRow = createAddonRow(addonForCv, newGroupedQuotesCompare, 1, addOnsAndOthers);
        addonRow.unshift(cpaValue);
        AddonArray.push(addonRow);
      }
    }
  }
  if (Number(newGroupedQuotesCompare[2]?.idv) > 0) {
    if (TypeReturn(type) === "car") {
      if (
        temp_data?.odOnly ||
        temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C"
      ) {
        // prettier-ignore
        const addonRow = createAddonRow(addonForCar, newGroupedQuotesCompare, 2, addOnsAndOthers);
        AddonArray.push(addonRow);
      } else {
        // prettier-ignore
        const cpaValue = calculateCpaValue(addOnsAndOthers, newGroupedQuotesCompare, 2);
        // prettier-ignore
        const addonRow = createAddonRow(addonForCar, newGroupedQuotesCompare, 2, addOnsAndOthers);
        addonRow.unshift(cpaValue);
        AddonArray.push(addonRow);
      }
    } else if (TypeReturn(type) === "bike") {
      if (
        temp_data?.odOnly ||
        temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C"
      ) {
        // prettier-ignore
        const addonRow = createAddonRow(addonForBike, newGroupedQuotesCompare, 2, addOnsAndOthers);
        AddonArray.push(addonRow);
      } else {
        // prettier-ignore
        const cpaValue = calculateCpaValue(addOnsAndOthers, newGroupedQuotesCompare, 2);
        // prettier-ignore
        const addonRow = createAddonRow(addonForBike, newGroupedQuotesCompare, 2, addOnsAndOthers);
        addonRow.unshift(cpaValue);
        AddonArray.push(addonRow);
      }
    } else if (TypeReturn(type) === "cv") {
      if (temp_data.journeyCategory === "GCV") {
        // prettier-ignore
        const cpaValue = calculateCpaValueForCv(addOnsAndOthers, newGroupedQuotesCompare, 2, temp_data);
        // prettier-ignore
        const addonRow = createAddonRow(addonForCv, newGroupedQuotesCompare, 2, addOnsAndOthers);
        addonRow.unshift(cpaValue);
        AddonArray.push(addonRow);
      } else {
        if (
          temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType === "C"
        ) {
          // prettier-ignore
          const addonRow = createAddonRow(addonForCv, newGroupedQuotesCompare, 2, addOnsAndOthers);
          AddonArray.push(addonRow);
        } else {
          // prettier-ignore
          const cpaValue = calculateCpaValueForCv(addOnsAndOthers, newGroupedQuotesCompare, 2, temp_data);
          // prettier-ignore
          const addonRow = createAddonRow(addonForCv, newGroupedQuotesCompare, 2, addOnsAndOthers);
          addonRow.unshift(cpaValue);
          AddonArray.push(addonRow);
        }
      }
    }
  }
  return AddonArray;
};
