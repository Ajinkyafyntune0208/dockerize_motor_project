import { addDays, addYears, subDays } from "date-fns";
import moment from "moment";

export const dateValidation = (broker) => {
  switch (broker) {
    case "RB":
      return 45;
    case "ABIBL":
      return 90;
    default:
      return 60;
  }
};

//get TP Date
export const getTpDate = (fnDate, type) =>
  moment(
    addYears(
      subDays(
        new Date(
          new Date(
            `${fnDate?.split("-")[2]}`,
            `${fnDate?.split("-")[1] * 1 - 1}`,
            `${fnDate?.split("-")[0]}`
          )
        ),
        1
      ),
      type === "car" ? 3 : 5
    )
  ).format("DD-MM-YYYY");

// calculate policy minimum date
export const calculatePolicyMinDate = (regDate) => {
  return regDate
    ? new Date(
        new Date(
          `${regDate?.split("-")[2]}`,
          `${regDate?.split("-")[1] * 1 - 1}`,
          `${regDate?.split("-")[0]}`
        )
      )
    : moment("01-01-1900").format("DD-MM-YYYY");
};

export const calculatePolicyMinDate1 = (
  temp_data,
  renewalMargin,
  odOnly,
  tempData,
  type
) => {
  return temp_data?.regDate &&
    !renewalMargin &&
    odOnly &&
    (tempData?.policyType === "Third-party" ||
      temp_data?.poicyType === "Third-party") &&
    temp_data?.previousPolicyTypeIdentifier !== "Y"
    ? subDays(
        new Date(
          new Date(
            `${getTpDate(temp_data?.regDate, type)?.split("-")[2]}`,
            `${getTpDate(temp_data?.regDate, type)?.split("-")[1] * 1 - 1}`,
            `${getTpDate(temp_data?.regDate, type)?.split("-")[0]}`
          )
        ),
        0
      )
    : temp_data?.regDate
    ? moment(
        new Date(
          new Date(
            `${temp_data?.regDate?.split("-")[2]}`,
            `${temp_data?.regDate?.split("-")[1] * 1 - 1}`,
            `${temp_data?.regDate?.split("-")[0]}`
          )
        )
      )
    : moment("01-01-1900").format("DD-MM-YYYY");
};

export const calculatePolicyMaxDate = (
  temp_data,
  renewalMargin,
  odOnly,
  tempData,
  type
) => {
  return temp_data?.regDate &&
    !renewalMargin &&
    odOnly &&
    tempData?.policyType === "Third-party" &&
    temp_data?.previousPolicyTypeIdentifier !== "Y"
    ? addDays(
        new Date(
          new Date(
            `${getTpDate(temp_data?.regDate, type)?.split("-")[2]}`,
            `${getTpDate(temp_data?.regDate, type)?.split("-")[1] * 1 - 1}`,
            `${getTpDate(temp_data?.regDate, type)?.split("-")[0]}`
          )
        ),
        45
      )
    : addDays(
        new Date(Date.now() - 86400000),
        dateValidation(import.meta.env.VITE_BROKER)
      );
};
