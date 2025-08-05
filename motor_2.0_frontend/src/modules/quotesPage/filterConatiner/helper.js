import { differenceInDays, differenceInMonths } from "date-fns";
import { TypeReturn } from "modules/type";
import moment from "moment";
import { toDate } from "utils";
import _ from "lodash";

export const pevPolicyfunc = (newCar, tempData, userData) => {
  return newCar
    ? "N/A"
    : tempData?.policyType
    ? userData?.temp_data?.prevShortTerm * 1
      ? "SHORT TERM"
      : tempData?.policyType.toUpperCase()
    : tempData?.policyType && tempData?.policyType !== 0
    ? tempData?.policyType?.toUpperCase()
    : "NOT SURE";
};

export const calcOdFunc = (userData, type, tempData) => {
  const { temp_data } = userData || {};
  const invoiceDate = temp_data?.vehicleInvoiceDate;
  const manufactureDate = temp_data?.manfDate;
  return (
    (((invoiceDate &&
      differenceInDays(
        toDate(invoiceDate),
        toDate(moment().format("01-09-2018"))
      )) >= 0 &&
      (invoiceDate &&
        moment().format("DD-MM-YYYY") &&
        differenceInDays(
          toDate(moment().format("DD-MM-YYYY")),
          toDate(invoiceDate)
        )) > 270 &&
      (invoiceDate &&
        moment().format("DD-MM-YYYY") &&
        differenceInMonths(
          toDate(moment().format("DD-MM-YYYY")),
          toDate(invoiceDate)
        )) < 58 &&
      TypeReturn(type) === "bike") ||
      ((invoiceDate &&
        moment().format("DD-MM-YYYY") &&
        differenceInDays(
          toDate(moment().format("DD-MM-YYYY")),
          toDate(invoiceDate)
        )) > 270 &&
        (invoiceDate &&
          moment().format("DD-MM-YYYY") &&
          differenceInMonths(
            toDate(moment().format("DD-MM-YYYY")),
            toDate(invoiceDate)
          )) < 34 &&
        TypeReturn(type) === "car") ||
      (invoiceDate &&
        manufactureDate &&
        TypeReturn(type) !== "cv" &&
        differenceInDays(
          toDate(invoiceDate),
          toDate(moment().format("01-09-2018"))
        ) > 1 &&
        differenceInMonths(
          toDate(moment().format("DD-MM-YYYY")),
          toDate(invoiceDate)
        ) < 9 &&
        differenceInDays(
          toDate(moment().format("DD-MM-YYYY")),
          toDate(manufactureDate)
        ) > 270)) &&
    !(
      tempData?.policyType === "Not sure" ||
      userData?.temp_data?.policyType === "Not sure" ||
      userData?.temp_data?.previousPolicyTypeIdentifier === "Y" ||
      tempData?.previousPolicyTypeIdentifier === "Y"
    )
  );
};

export const bundledPolicyFunc = (calculatedOd, userData, type) => {
  const invoiceDate = userData.temp_data?.vehicleInvoiceDate;
  return (
    (calculatedOd ||
      userData?.temp_data?.odOnly ||
      ((invoiceDate &&
        moment().format("DD-MM-YYYY") &&
        differenceInMonths(
          toDate(moment().format("DD-MM-YYYY")),
          toDate(invoiceDate)
        )) >= 34 &&
        (invoiceDate &&
          differenceInDays(
            toDate(invoiceDate),
            toDate(moment().format("01-09-2018"))
          )) >= 0 &&
        (invoiceDate &&
          moment().format("DD-MM-YYYY") &&
          differenceInDays(
            toDate(moment().format("DD-MM-YYYY")),
            toDate(invoiceDate)
          )) > 270 &&
        ((invoiceDate &&
          moment().format("DD-MM-YYYY") &&
          differenceInMonths(
            toDate(moment().format("DD-MM-YYYY")),
            toDate(invoiceDate)
          )) < 36 ||
          ((invoiceDate &&
            moment().format("DD-MM-YYYY") &&
            differenceInMonths(
              toDate(moment().format("DD-MM-YYYY")),
              toDate(invoiceDate)
            )) === 36 &&
            (invoiceDate &&
              moment().format("DD-MM-YYYY") &&
              differenceInDays(
                toDate(moment().format("DD-MM-YYYY")),
                toDate(invoiceDate)
              )) <= 1095)) &&
        type === "car") ||
      ((invoiceDate &&
        moment().format("DD-MM-YYYY") &&
        differenceInMonths(
          toDate(moment().format("DD-MM-YYYY")),
          toDate(invoiceDate)
        )) >= 58 &&
        differenceInDays(
          toDate(invoiceDate),
          toDate(moment().format("01-09-2018"))
        ) >= 0 &&
        (moment().format("DD-MM-YYYY") &&
          differenceInDays(
            toDate(moment().format("DD-MM-YYYY")),
            toDate(invoiceDate)
          )) > 270 &&
        ((moment().format("DD-MM-YYYY") &&
          differenceInMonths(
            toDate(moment().format("DD-MM-YYYY")),
            toDate(invoiceDate)
          )) < 60 ||
          ((moment().format("DD-MM-YYYY") &&
            differenceInMonths(
              toDate(moment().format("DD-MM-YYYY")),
              toDate(invoiceDate)
            )) === 60 &&
            (moment().format("DD-MM-YYYY") &&
              differenceInDays(
                toDate(moment().format("DD-MM-YYYY")),
                toDate(invoiceDate)
              )) <= 1095)) &&
        type === "bike")) &&
    userData?.temp_data?.previousPolicyTypeIdentifier !== "Y" &&
    (new Date().getFullYear() -
      Number(
        userData?.temp_data?.vehicleInvoiceDate?.slice(
          userData?.temp_data?.vehicleInvoiceDate?.length - 4
        )
      ) >=
      1 ||
      (differenceInMonths(
        toDate(moment().format("DD-MM-YYYY")),
        toDate(invoiceDate)
      ) > 9 &&
        differenceInMonths(
          toDate(moment().format("DD-MM-YYYY")),
          toDate(invoiceDate)
        ) <= 12))
  );
};

// quoteData function
export const quoteDataFunc = (
  userData,
  enquiry_id,
  loginData,
  newCar,
  tempData,
  odOnly,
  policyTypeCode
) => {
  return {
    lsq_stage: "Quote Seen",
    enquiryId: userData.temp_data?.enquiry_id || enquiry_id,
    vehicleRegistrationNo:
      userData.temp_data?.journeyType === 3
        ? "NEW"
        : userData.temp_data?.regNo
        ? userData.temp_data?.regNo
        : Number(
            userData.temp_data?.regDate?.slice(
              userData.temp_data?.regDate?.length - 4
            )
          ) === new Date().getFullYear() && userData.temp_data?.newCar
        ? "NEW"
        : "NULL",
    userProductJourneyId: userData.temp_data?.enquiry_id || enquiry_id,
    corpId: loginData?.corpId || userData.temp_data?.corpId,
    userId: loginData?.userId || userData.temp_data?.userId,
    productSubTypeId: userData?.temp_data?.productSubTypeId,
    fullName:
      userData.temp_data?.firstName + " " + userData.temp_data?.lastName,
    firstName: userData.temp_data?.firstName,
    lastName: userData.temp_data?.lastName,
    emailId: userData.temp_data?.emailId,
    mobileNo: userData.temp_data?.mobileNo,
    //remove and rebuild this logic
    businessType: userData.temp_data?.newCar
      ? "newbusiness"
      : userData.temp_data?.breakIn
      ? "breakin"
      : "rollover",
    policyType:
      (userData?.temp_data?.odOnly || odOnly) &&
      tempData?.policyType !== "Not sure"
        ? "own_damage"
        : "comprehensive",
    rto: userData.temp_data?.rtoNumber,
    manfactureId:
      userData.temp_data?.manfId || userData.temp_data?.manufacturerId,
    manufactureYear: userData.temp_data?.manfDate,
    model: userData.temp_data?.modelId,
    version: userData.temp_data?.versionId,
    versionName: userData.temp_data?.versionName,
    vehicleRegisterAt: userData.temp_data?.rtoNumber,
    vehicleRegisterDate: userData.temp_data?.regDate,
    vehicleOwnerType: userData.temp_data?.ownerTypeId === 2 ? "C" : "I",
    policyExpiryDate:
      ["Not Sure", "New"].includes(userData.temp_data?.expiry) ||
      userData.temp_data?.newCar
        ? "New"
        : userData.temp_data?.expiry,
    hasExpired: userData.temp_data?.policyExpired ? "yes" : "no",
    isNcb: userData.temp_data?.ncb ? "Yes" : "No",

    isClaim: userData.temp_data?.noClaimMade ? "N" : "Y",
    previousNcb:
      (userData.temp_data?.ncb && userData.temp_data?.ncb?.slice(0, -1)) || 0,
    applicableNcb:
      (userData.temp_data?.newNcb &&
        userData.temp_data?.newNcb?.slice(0, -1)) ||
      0,

    fuelType: userData.temp_data?.fuel,
    vehicleUsage: userData.temp_data?.carrierType || 2,

    vehicleLpgCngKitValue: userData.temp_data?.kit_val
      ? userData.temp_data?.kit_val
      : "",

    previousInsurer:
      userData.temp_data?.prevIcFullName !== "NEW"
        ? userData.temp_data?.prevIcFullName === "New"
          ? "NEW"
          : userData.temp_data?.prevIcFullName
        : "NEW",
    previousInsurerCode:
      userData.temp_data?.prevIc !== "NEW"
        ? userData.temp_data?.prevIc === "New"
          ? "NEW"
          : userData.temp_data?.prevIc
        : "NEW",

    previousPolicyType:
      !newCar && !userData.temp_data?.newCar
        ? tempData?.policyType === "New"
          ? "Not sure"
          : tempData?.policyType || userData.temp_data?.policyType
        : "NEW",

    modelName: userData.temp_data?.modelName,
    manfactureName: userData.temp_data?.manfName,
    ownershipChanged: userData.temp_data?.carOwnership ? "Y" : "N",
    leadJourneyEnd: userData.temp_data?.leadJourneyEnd
      ? userData.temp_data?.leadJourneyEnd
      : false,
    isNcbVerified: userData.temp_data?.isNcbVerified === "Y" ? "Y" : "N",
    isClaimVerified: userData.temp_data?.isClaimVerified,
    stage: 11,
    isNcbConfirmed: userData.temp_data?.isNcbConfirmed,
    selectedGvw: userData?.temp_data?.selectedGvw,
    defaultGvw: userData?.temp_data?.defaultGvw,
    previousPolicyTypeIdentifier:
      userData?.temp_data?.previousPolicyTypeIdentifier,
    isMultiYearPolicy:
      !_.isEmpty(userData.temp_data?.regDate?.split("-")) &&
      userData.temp_data?.regDate?.split("-")[2] * 1 === 2019 &&
      userData?.temp_data?.isMultiYearPolicy
        ? "Y"
        : "N",
    previousPolicyTypeIdentifierCode: policyTypeCode(),
    seatingCapacity: userData?.temp_data?.seatingCapacity,
    vehicleInvoiceDate: userData?.temp_data?.vehicleInvoiceDate,
  };
};
