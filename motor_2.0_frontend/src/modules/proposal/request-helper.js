import moment from "moment";

export const rollover_breakin_constructor = (temp, enquiry_id, type) => {
  let construct = {
    isProposal: true,
    businessType: "breakin",
    enquiryId: enquiry_id,
    stage: 11,
    userProductJourneyId: temp?.enquiry_id || enquiry_id,
    policyType: temp?.odOnly && type !== "cv" ? "own_damage" : "comprehensive",
    vehicleRegistrationNo:
      temp?.journeyType === 3
        ? "NEW"
        : temp?.regNo
        ? temp?.regNo
        : Number(temp?.regDate?.slice(temp?.regDate?.length - 4)) ===
            new Date().getFullYear() && temp?.newCar
        ? "NEW"
        : "NULL",
    productSubTypeId: temp?.productSubTypeId,
    fullName: temp?.firstName + " " + temp?.lastName,
    firstName: temp?.firstName,
    lastName: temp?.lastName,
    emailId: temp?.emailId,
    mobileNo: temp?.mobileNo,
    rto: temp?.rtoNumber,
    manfactureId: temp?.manfId,
    manufactureYear: temp?.manfDate,
    model: temp?.modelId,
    version: temp?.versionId,
    versionName: temp?.versionName,
    vehicleRegisterAt: temp?.rtoNumber,
    vehicleRegisterDate: temp?.regDate,
    vehicleOwnerType: temp?.ownerTypeId === 2 ? "C" : "I",
    policyExpiryDate:
      temp?.expiry === "Not Sure" || temp?.expiry === "New"
        ? "New"
        : temp?.expiry,
    hasExpired: temp?.policyExpired ? "yes" : "no",
    isNcb: temp?.ncb ? "Yes" : "No",
    isClaim: temp?.noClaimMade ? "N" : "Y",
    previousNcb: (temp?.ncb && temp?.ncb?.slice(0, -1)) || 0,
    applicableNcb: (temp?.newNcb && temp?.newNcb?.slice(0, -1)) || 0,
    fuelType: temp?.fuel,
    vehicleUsage: temp?.carrierType || 2,
    vehicleLpgCngKitValue: temp?.kit_val ? temp?.kit_val : "",
    previousInsurer:
      temp?.prevIcFullName !== "NEW"
        ? temp?.prevIcFullName === "New"
          ? "NEW"
          : temp?.prevIcFullName
        : "NEW",
    previousInsurerCode:
      temp?.prevIc !== "NEW"
        ? temp?.prevIc === "New"
          ? "NEW"
          : temp?.prevIc
        : "NEW",
    previousPolicyType: temp?.corporateVehiclesQuoteRequest?.previousPolicyType,
    modelName: temp?.modelName,
    manfactureName: temp?.manfName,
    ownershipChanged: temp?.carOwnership ? "Y" : "N",
    leadJourneyEnd: temp?.leadJourneyEnd ? temp?.leadJourneyEnd : false,
    isNcbVerified: temp?.isNcbVerified === "Y" ? "Y" : "N",
    isClaimVerified: temp?.isClaimVerified,
    resetJourneyStage: "Y",
  };

  return construct;
};

export const expiry_construct = (temp, enquiry_id) => {
  let data = {
    isProposal: true,
    clearStartDate: true,
    businessType: temp?.corporateVehiclesQuoteRequest?.businessType,
    enquiryId: enquiry_id,
    stage: 11,
    vehicleRegistrationNo:
      temp?.journeyType === 3
        ? "NEW"
        : temp?.regNo
        ? temp?.regNo
        : temp?.newCar
        ? "NEW"
        : "NULL",
    userProductJourneyId: temp?.enquiry_id || enquiry_id,
    productSubTypeId: temp?.productSubTypeId,
    fullName: temp?.firstName + " " + temp?.lastName,
    firstName: temp?.firstName,
    lastName: temp?.lastName,
    emailId: temp?.emailId,
    mobileNo: temp?.mobileNo,
    policyType: temp?.corporateVehiclesQuoteRequest?.policyType,
    rto: temp?.rtoNumber,
    manfactureId: temp?.manfId,
    manufactureYear: temp?.manfDate,
    model: temp?.modelId,
    version: temp?.versionId,
    versionName: temp?.versionName,
    vehicleRegisterAt: temp?.rtoNumber,
    vehicleRegisterDate:
      temp?.corporateVehiclesQuoteRequest?.businessType === "newbusiness"
        ? new Date(
            temp?.corporateVehiclesQuoteRequest?.vehicleRegisterDate.split(
              "-"
            )[2],
            temp?.corporateVehiclesQuoteRequest?.vehicleRegisterDate.split(
              "-"
            )[1] *
              1 -
              1,
            temp?.corporateVehiclesQuoteRequest?.vehicleRegisterDate.split(
              "-"
            )[0]
          )
            .setHours(0, 0, 0, 0)
            .valueOf() < new Date().setHours(0, 0, 0, 0).valueOf()
          ? moment().format("DD-MM-YYYY")
          : temp?.regDate
        : temp?.regDate,
    vehicleOwnerType: temp?.ownerTypeId === 2 ? "C" : "I",
    policyExpiryDate:
      temp?.expiry === "Not Sure" || temp?.expiry === "New"
        ? "New"
        : temp?.expiry,
    hasExpired: temp?.policyExpired ? "yes" : "no",
    isNcb: temp?.ncb ? "Yes" : "No",
    isClaim: temp?.noClaimMade ? "N" : "Y",
    previousNcb: (temp?.ncb && temp?.ncb?.slice(0, -1)) || 0,
    applicableNcb: (temp?.newNcb && temp?.newNcb?.slice(0, -1)) || 0,
    fuelType: temp?.fuel,
    vehicleUsage: temp?.carrierType || 2,
    vehicleLpgCngKitValue: temp?.kit_val ? temp?.kit_val : "",
    previousInsurer:
      temp?.prevIcFullName !== "NEW"
        ? temp?.prevIcFullName === "New"
          ? "NEW"
          : temp?.prevIcFullName
        : "NEW",
    previousInsurerCode:
      temp?.prevIc !== "NEW"
        ? temp?.prevIc === "New"
          ? "NEW"
          : temp?.prevIc
        : "NEW",
    previousPolicyType: temp?.corporateVehiclesQuoteRequest?.previousPolicyType,
    modelName: temp?.modelName,
    manfactureName: temp?.manfName,
    ownershipChanged: temp?.carOwnership ? "Y" : "N",
    leadJourneyEnd: temp?.leadJourneyEnd ? temp?.leadJourneyEnd : false,
    isNcbVerified: temp?.isNcbVerified === "Y" ? "Y" : "N",
    isClaimVerified: temp?.isClaimVerified,
  };

  return data;
};

export const nb_construct = (temp, enquiry_id) => {
  let data = {
    isProposal: true,
    businessType: temp?.corporateVehiclesQuoteRequest?.businessType,
    enquiryId: enquiry_id,
    stage: 11,
    vehicleRegistrationNo:
      temp?.journeyType === 3
        ? "NEW"
        : temp?.regNo
        ? temp?.regNo
        : temp?.newCar
        ? "NEW"
        : "NULL",
    userProductJourneyId: temp?.enquiry_id || enquiry_id,
    productSubTypeId: temp?.productSubTypeId,
    fullName: temp?.firstName + " " + temp?.lastName,
    firstName: temp?.firstName,
    lastName: temp?.lastName,
    emailId: temp?.emailId,
    mobileNo: temp?.mobileNo,
    policyType: temp?.corporateVehiclesQuoteRequest?.policyType,
    rto: temp?.rtoNumber,
    manfactureId: temp?.manfId,
    manufactureYear: temp?.manfDate,
    model: temp?.modelId,
    version: temp?.versionId,
    versionName: temp?.versionName,
    vehicleRegisterAt: temp?.rtoNumber,
    vehicleRegisterDate: moment().format("DD-MM-YYYY"),
    vehicleOwnerType: temp?.ownerTypeId === 2 ? "C" : "I",
    policyExpiryDate:
      temp?.expiry === "Not Sure" || temp?.expiry === "New"
        ? "New"
        : temp?.expiry,
    hasExpired: temp?.policyExpired ? "yes" : "no",
    isNcb: temp?.ncb ? "Yes" : "No",
    isClaim: temp?.noClaimMade ? "N" : "Y",
    previousNcb: (temp?.ncb && temp?.ncb?.slice(0, -1)) || 0,
    applicableNcb: (temp?.newNcb && temp?.newNcb?.slice(0, -1)) || 0,
    fuelType: temp?.fuel,
    vehicleUsage: temp?.carrierType || 2,
    vehicleLpgCngKitValue: temp?.kit_val ? temp?.kit_val : "",
    previousInsurer:
      temp?.prevIcFullName !== "NEW"
        ? temp?.prevIcFullName === "New"
          ? "NEW"
          : temp?.prevIcFullName
        : "NEW",
    previousInsurerCode:
      temp?.prevIc !== "NEW"
        ? temp?.prevIc === "New"
          ? "NEW"
          : temp?.prevIc
        : "NEW",
    previousPolicyType: temp?.corporateVehiclesQuoteRequest?.previousPolicyType,
    modelName: temp?.modelName,
    manfactureName: temp?.manfName,
    ownershipChanged: temp?.carOwnership ? "Y" : "N",
    leadJourneyEnd: temp?.leadJourneyEnd ? temp?.leadJourneyEnd : false,
    isNcbVerified: temp?.isNcbVerified === "Y" ? "Y" : "N",
    isClaimVerified: temp?.isClaimVerified,
  };

  return data;
};
