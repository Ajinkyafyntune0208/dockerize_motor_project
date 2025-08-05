/* eslint-disable react-hooks/exhaustive-deps */
import { useState, useEffect } from "react";
import { differenceInDays, differenceInMonths } from "date-fns";
import moment from "moment";
import { toDate, vahaanServicesName } from "utils";

export const usePrevIcVisibility = (prevIc) => {
  const [prevIcData, setPrevIcData] = useState(false);

  useEffect(() => {
    if (prevIc && prevIc !== "others" && prevIc !== "Not selected") {
      setPrevIcData(true);
    } else {
      setPrevIcData(false);
    }
  }, [prevIc]);

  return prevIcData;
};

// calculate renewal margin and od only logic
export const useRenewalAndOdOnly = (temp_data, type) => {
  const [renewalMargin, setRenewalMargin] = useState(false);
  const [odOnly, setOdOnly] = useState(false);

  useEffect(() => {
    let b = "01-09-2018";
    let c = temp_data?.vehicleInvoiceDate || temp_data?.regDate;
    let d = moment().format("DD-MM-YYYY");
    let e = temp_data?.manfDate;
    let diffDaysOd = c && b && differenceInDays(toDate(c), toDate(b));
    let diffManfDays = c && b && differenceInDays(toDate(d), toDate(e));
    let diffMonthsOdCar = c && d && differenceInMonths(toDate(d), toDate(c));
    let diffDayOd = c && d && differenceInDays(toDate(d), toDate(c));
    //calc days for edge cases in the last month of renewal
    let diffDaysOdCar = d && c && differenceInDays(toDate(d), toDate(c));

    // calculating renewal margin
    if (
      ((diffDaysOd >= 0 &&
        diffDayOd > 270 &&
        diffMonthsOdCar >= 58 &&
        (diffMonthsOdCar < 60 ||
          (diffMonthsOdCar === 60 && diffDaysOdCar <= 1095)) &&
        type === "bike") ||
        (diffMonthsOdCar >= 34 &&
          diffDayOd > 270 &&
          (diffMonthsOdCar < 36 ||
            (diffMonthsOdCar === 36 && diffDaysOdCar <= 1095)) &&
          type === "car"))
    ) {
      setRenewalMargin(true);
    } else {
      setRenewalMargin(false);
    }

    // calculating od after renewal margin to prevent double rendering
    if (
      (((diffDaysOd >= 0 &&
        diffDayOd > 270 &&
        diffMonthsOdCar < 58 &&
        type === "bike") ||
        (diffDayOd > 270 && diffMonthsOdCar < 34 && type === "car")) || (diffManfDays > 270 && diffDayOd > 1 && diffMonthsOdCar > 9 && type !== "cv")) &&
      temp_data?.policyType !== "Not sure" &&
      temp_data?.previousPolicyTypeIdentifier !== "Y"
    ) {
      setOdOnly(true);
    } else {
      setOdOnly(false);
    }
  }, [temp_data?.vehicleInvoiceDate, temp_data?.regDate]);

  return { renewalMargin, odOnly, setOdOnly };
};

// policy type selection login
export const usePolicyTypeSelected = (policyType) => {
  const [policyTypeSelected, setPolicyTypeSelected] = useState(false);

  useEffect(() => {
    if (policyType) {
      setPolicyTypeSelected(true);
    } else {
      setPolicyTypeSelected(false);
    }
  }, [policyType]);

  return policyTypeSelected;
};

//setting step based on od
export const useStepBasedOnOd = (
  odOnly,
  renewalMargin,
  policyTypeSelected,
  temp_data
) => {
  const [step, setStep] = useState(2);

  useEffect(() => {
    if (
      (odOnly || renewalMargin) &&
      (!policyTypeSelected ||
        (vahaanServicesName?.includes(
          temp_data?.corporateVehiclesQuoteRequest?.journeyType ||
          temp_data?.isRenewalUpload
        ) &&
          !temp_data?.expiry))
    ) {
      setStep(1);
    } else {
      setStep(2);
    }
  }, [odOnly, renewalMargin, policyTypeSelected, temp_data]);

  return { step, setStep };
};

export const useMobileDrawer = (lessthan767, show) => {
  const [drawer, setDrawer] = useState(false);

  useEffect(() => {
    if (lessthan767 && show) {
      const timeoutId = setTimeout(() => {
        setDrawer(true);
      }, 50);

      return () => clearTimeout(timeoutId);
    }
  }, [lessthan767, show]);

  return { drawer, setDrawer };
};
