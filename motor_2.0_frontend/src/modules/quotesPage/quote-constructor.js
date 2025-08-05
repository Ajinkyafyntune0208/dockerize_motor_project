export const quoteFetch_construct = (
  temp_data,
  tempData,
  type,
  enquiry_id,
  shared,
  theme_conf
) => {
  const isCommissionEnabled = theme_conf?.broker_config?.isCommissionEnabled;
  let data = {
    productSubTypeId: temp_data?.productSubTypeId
      ? temp_data?.productSubTypeId
      : type === "bike"
      ? 2
      : 1,
    businessType: temp_data?.newCar
      ? "newbusiness"
      : temp_data?.breakIn
      ? "breakin"
      : "rollover",

    policyType: temp_data?.odOnly ? "own_damage" : "comprehensive",
    selectedPreviousPolicyType: temp_data?.newCar
      ? "N/A"
      : (tempData?.policyType && tempData?.policyType !== 0) ||
        temp_data?.policyType
      ? tempData?.policyType || temp_data?.policyType
      : "Comprehensive",
    premiumType:
      temp_data?.breakIn && !temp_data?.newCar
        ? temp_data?.odOnly
          ? "own_damage_breakin"
          : "breakin"
        : temp_data?.odOnly
        ? "own_damage"
        : "rollover",
    previousInsurer: temp_data?.prevIc ? temp_data?.prevIc : "",
    enquiryId: enquiry_id,
    ...(temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y" && {
      isRenewal: "Y",
    }),
    ...((temp_data?.corporateVehiclesQuoteRequest?.frontendTags ||
      temp_data?.frontendTags) &&
      import.meta.env.VITE_BROKER === "BAJAJ" && {
        hideRenewal: true,
      }),
    ...(temp_data?.ownerTypeId && {
      vehicleOwnerType: temp_data?.ownerTypeId * 1 === 1 ? "I" : "C",
    }),
    ...(shared &&
      isCommissionEnabled && {
        isQuoteShared: true,
      }),
  };
  return data;
};
