import { saod } from "./helper";
import moment from "moment";
import _ from "lodash";

//On fastlane response.
export const setFastlaneState = (fastLaneData) => {
  return {
    isRenewalRedirection: "N",
    newCar: fastLaneData?.additional_details?.businessType === "newbusiness",
    breakIn: fastLaneData?.additional_details?.businessType === "breakin",
    odOnly: fastLaneData?.additional_details?.policyType === "own_damage",
    manufacturerId: fastLaneData?.additional_details?.manufacturerId,
    manufId: fastLaneData?.additional_details?.manufacturerId,
    ...(import.meta.env.VITE_BROKER === "BAJAJ" && {
      frontendTags: JSON.stringify({ hideRenewal: true }),
    }),
  };
};

export const getVahaanData = (vehicleData, fastLaneData, TypeReturn, type) => {
  const invoiceDate = `01-${fastLaneData?.additional_details?.manufacturerYear}`;
  const isWithin9Months = invoiceDate && (() => {
    const invoiceFastlane = moment(invoiceDate, "DD-MM-YYYY");
    return moment().diff(invoiceFastlane, 'months', true) < 9;
  })();

  return {
    vehicleRegisterDate:
      vehicleData?.regn_dt?.split("/").join("-") || "01-10-2016",
    ...(vehicleData && {
      policyType: saod(
        fastLaneData?.results[0]?.vehicle?.regn_dt.split("/").join("-"),
        TypeReturn,
        type,
        invoiceDate
      )
        ? "own_damage"
        : "comprehensive",
      version: fastLaneData?.results[0]?.vehicle?.vehicle_cd,
      versionName: fastLaneData?.results[0]?.vehicle?.fla_variant,
      vehicleRegisterDate: fastLaneData?.results[0]?.vehicle?.regn_dt
        .split("/")
        .join("-"),
      policyExpiryDate: fastLaneData?.results[0]?.insurance?.insurance_upto
        ? fastLaneData?.results[0]?.insurance?.insurance_upto
            .split("/")
            .join("-")
        : moment().format("DD-MM-YYYY"),
      fuelType:
        fastLaneData?.results[0]?.vehicle?.fla_fuel_type_desc || "PETROL",
      modelName: fastLaneData?.results[0]?.vehicle?.fla_model_desc,
      manfactureName: fastLaneData?.results[0]?.vehicle?.fla_maker_desc,
      manufacturerId: fastLaneData?.additional_details?.manufacturerId,
      manufacturerYear: fastLaneData?.additional_details?.manufacturerYear,
      businessType: isWithin9Months ? "newbusiness" : "rollover",
      engineNo:
        !_.isEmpty(fastLaneData?.results) &&
        fastLaneData?.results[0]?.vehicle?.eng_no,
      chassisNo:
        !_.isEmpty(fastLaneData?.results) &&
        fastLaneData?.results[0]?.vehicle?.chasi_no,
      vehicleColor:
        !_.isEmpty(fastLaneData?.results) &&
        fastLaneData?.results[0]?.vehicle?.color,
    }),
  };
};

export const getUserData = (temp_data) => {
  return {
    fullName: temp_data?.firstName + " " + temp_data?.lastName,
    firstName: temp_data?.firstName,
    lastName: temp_data?.lastName,
    emailId: temp_data?.emailId,
    mobileNo: temp_data?.mobileNo,
    corpId: temp_data?.corpId,
    userId: temp_data?.userId,
  };
};

export const getPolicyData = (temp_data) => {
  return {
    hasExpired: "no",
    isNcb: "Yes",
    isClaim: "N",
    vehicleUsage: 2,
    vehicleLpgCngKitValue: "",
    previousInsurer: temp_data?.prevIcFullName,
    previousInsurerCode: temp_data?.prevIc,
    previousPolicyType: "Comprehensive",
    ownershipChanged: "N",
  };
};

export const getVehicleDetails = (regIp, regNo1, regNo2, regNo3, type) => {
  return {
    vehicleRegistrationNo:
      regIp && regIp[0] * 1
        ? regIp.toUpperCase()
        : regNo2
        ? `${regNo1}-${regNo2}-${regNo3}`
        : `${regNo1}--${regNo3}`,
    rto: regNo1,
    vehicleRegisterAt: regNo1,
    vehicleOwnerType: "I",
    ...(type !== "cv" && {
      productSubTypeId: type === "car" ? 1 : 2,
    }),
  };
};

export const journeyTrackers = (temp_data, enquiry_id, journey_type, token) => {
  return {
    enquiryId: temp_data?.enquiry_id || enquiry_id,
    userProductJourneyId: temp_data?.enquiry_id || enquiry_id,
    ...(journey_type && {
      journeyType: journey_type,
    }),
    ...(token && { token: token }),
    leadJourneyEnd: true,
    stage: 11,
    preventKafkaPush: true,
  };
};

export const updatePolicyExpiry = (data) => {
  return _.pick(data, _.without(_.keys(data), "policyExpiryDate"));
};

export const getVahaanPayload = (
  temp_data,
  enquiry_id,
  registration_no,
  journey_type,
  type
) => {
  return {
    ...(localStorage?.SSO_user && {
      tokenResp: localStorage?.SSO_user,
    }),
    enquiryId: temp_data?.enquiry_id || enquiry_id,
    registration_no: registration_no,
    unformatted_reg_no: registration_no.replace(/-/gi, ""),
    ...(type !== "cv" && {
      productSubType: type === "car" ? 1 : 2,
    }),
    ...(journey_type && {
      journeyType: journey_type,
    }),
    section: type,
  };
};

export const setFastlaneRequest = (categoryParams) => {
  const { journeyType, regIp, category, type } = categoryParams;
  let Reg = new Date();
  return {
    isRenewalRedirection: "N",
    journeyWithoutRegno: "Y",
    journeyType,
    newCar: journeyType * 1 === 3,
    regNo: null,
    regNo1: null,
    regNo2: null,
    regNo3: null,
    regDate:
      regIp &&
      regIp[0] * 1 &&
      `${Reg.getDay()}-${Reg.getMonth() + 1}-${Reg.getFullYear()}`,
    vehicleInvoiceDate:
      regIp &&
      regIp[0] * 1 &&
      `${Reg.getDay()}-${Reg.getMonth() + 1}-${Reg.getFullYear()}`,
    fastlaneJourney: false,
    ...(type !== "cv" &&
      type &&
      !_.isEmpty(category) && {
        productSubTypeId: category?.product_sub_type_id,
        productSubTypeCode: category?.product_sub_type_code,
        productSubTypeName: category?.product_sub_type_code,
      }),
  };
};

export const setSaveQuoteRequest = (otherLinkParams) => {
  //prettier-ignore
  const { token, enquiry_id, _typeReturn: type, journeyType,
          regIp, journey_type, category 
        } = otherLinkParams
  let Reg = new Date();
  return {
    stage: "2",
    // ...(isPartner === "Y" && { frontendTags: "Y" }),
    ...(import.meta.env.VITE_BROKER === "BAJAJ" && {
      frontendTags: JSON.stringify({ hideRenewal: true }),
    }),
    ...(localStorage?.SSO_user && {
      tokenResp: localStorage?.SSO_user,
    }),
    journeyWithoutRegno: "Y",
    vehicleRegistrationNo: Number(journeyType) === 3 ? "NEW" : "NULL",
    ...(token && { token: token }),
    userProductJourneyId: enquiry_id,
    enquiryId: enquiry_id,
    vehicleInvoiceDate:
      (regIp &&
        regIp[0] * 1 &&
        `${Reg.getDay()}-${Reg.getMonth() + 1}-${Reg.getFullYear()}`) ||
      "NULL",
    vehicleRegisterDate:
      (regIp &&
        regIp[0] * 1 &&
        `${Reg.getDay()}-${Reg.getMonth() + 1}-${Reg.getFullYear()}`) ||
      "NULL",
    policyExpiryDate: "NULL",
    previousInsurerCode: "NULL",
    previousInsurer: "NULL",
    previousPolicyType: "NULL",
    businessType: journeyType * 1 === 3 ? "newbusiness" : "NULL",
    policyType: Number(journeyType) === 3 ? "comprehensive" : "NULL",
    previousNcb: "NULL",
    applicableNcb: "NULL",
    fastlaneJourney: false,
    isRenewalRedirection: "N",
    ...(journey_type && {
      journeyType: journey_type,
    }),
    ...(type !== "cv" &&
      type &&
      !_.isEmpty(category) && {
        productSubTypeId: category?.product_sub_type_id,
        productSubTypeCode: category?.product_sub_type_code,
        productSubTypeName: category?.product_sub_type_code,
      }),
  };
};

export const onSubmitRegistration = (regParams, UrlParams) => {
  let { regIp, regNo1, regNo2, regNo3 } = regParams;
  let { journeyType, category, _type: type } = UrlParams;
  let Reg = new Date();
  return {
    journeyType,
    regNo1,
    regNo2,
    regNo3,
    regNo: regNo2
      ? `${
          Number(regNo1.split("-")[1]) < 10
            ? `${regNo1.split("-")[0]}-0${Number(regNo1.split("-")[1])}`
            : regNo1
        }-${regNo2}-${regNo3}`
      : `${
          Number(regNo1.split("-")[1]) < 10
            ? `${regNo1.split("-")[0]}-0${Number(regNo1.split("-")[1])}`
            : regNo1
        }--${regNo3}`,
    vehicleInvoiceDate:
      (regIp &&
        regIp[0] * 1 &&
        `${Reg.getDay()}-${Reg.getMonth() + 1}-${Reg.getFullYear()}`) ||
      null,
    vehicleRegisterDate:
      (regIp &&
        regIp[0] * 1 &&
        `${Reg.getDay()}-${Reg.getMonth() + 1}-${Reg.getFullYear()}`) ||
      null,
    journeyWithoutRegno: "N",
    fastlaneJourney: false,
    ...(type !== "cv" &&
      type &&
      !_.isEmpty(category) && {
        productSubTypeId: category?.product_sub_type_id,
        productSubTypeCode: category?.product_sub_type_code,
        productSubTypeName: category?.product_sub_type_code,
      }),
    isRenewalRedirection: "N",
  };
};

export const onSubmitSave = (regParams, UrlParams) => {
  let { regIp, regNo1, regNo2, regNo3 } = regParams;
  let { enquiry_id, category, _type: type, journey_type } = UrlParams;
  let Reg = new Date();
  return {
    stage: "2",
    journeyWithoutRegno: "N",
    ...(regIp && regIp[0] * 1
      ? { vehicleRegistrationNo: regIp.toUpperCase() }
      : {
          vehicleRegistrationNo: regNo2
            ? `${regNo1}-${regNo2}-${regNo3}`
            : `${regNo1}--${regNo3}`,
        }),
    ...(regIp &&
      !(regIp[0] * 1) && {
        rtoNumber: regNo1,
      }),
    ...(regIp &&
      !(regIp[0] * 1) && {
        rto: regNo1,
      }),
    userProductJourneyId: enquiry_id,
    ...(!(regIp[0] * 1) && {
      vehicleRegisterAt: regNo1,
    }),
    // ...(isPartner === "Y" && { frontendTags: "Y" }),
    ...(import.meta.env.VITE_BROKER === "BAJAJ" && {
      frontendTags: JSON.stringify({ hideRenewal: true }),
    }),
    ...(journey_type && {
      journeyType: journey_type,
    }),
    enquiryId: enquiry_id,
    vehicleInvoiceDate:
      (regIp &&
        regIp[0] * 1 &&
        `${Reg.getDay()}-${Reg.getMonth() + 1}-${Reg.getFullYear()}`) ||
      "NULL",
    vehicleRegisterDate:
      (regIp &&
        regIp[0] * 1 &&
        `${Reg.getDay()}-${Reg.getMonth() + 1}-${Reg.getFullYear()}`) ||
      "NULL",
    policyExpiryDate: "NULL",
    previousInsurerCode: "NULL",
    previousInsurer: "NULL",
    previousPolicyType: "NULL",
    businessType: "NULL",
    policyType: "NULL",
    previousNcb: "NULL",
    applicableNcb: "NULL",
    fastlaneJourney: false,
    isRenewalRedirection: "N",
    ...(type !== "cv" &&
      type &&
      !_.isEmpty(category) && {
        productSubTypeId: category?.product_sub_type_id,
        productSubTypeCode: category?.product_sub_type_code,
        productSubTypeName: category?.product_sub_type_code,
      }),
  };
};

export const fastLaneDataObject = (fastLaneData) => {
  const data = fastLaneData?.additional_details || {};
  return {
    policyType: data?.previousPolicyType,
    productSubTypeId: data?.productSubTypeId,
    newCar: data?.businessType === "newbusiness",
    breakIn: data?.businessType === "breakin",
    prevIc: data?.previousInsurerCode,
    prevIcFullName: data?.previousInsurer,
    odOnly: data?.policyType === "own_damage",
    manfName: data?.manfactureName,
    manfactureId: data?.manfactureId,
    manfId: data?.manfactureId,
    modelId: data?.model,
    modelName: data?.modelName,
    versionId: data?.version,
    versionName: data?.versionName,
    manufacturerYear: data?.manufacturerYear,
    regNo: data?.vehicleRegistrationNo,
    regDate: data?.vehicleRegisterDate,
    rtoNumber: data?.rto,
    vehicleInvoiceDate: data?.vehicleInvoiceDate,
  };
};
