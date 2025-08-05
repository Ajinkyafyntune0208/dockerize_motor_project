import { useEffect } from "react";

export const useCkycFetchedDetailsUpdate = ({
  temp_data,
  prefillAndResubmit,
  setVerifiedData,
  setValue,
  owner,
  setResubmit,
}) => {
  const companyAlias = temp_data?.selectedQuote?.companyAlias;
  useEffect(() => {
    if (temp_data?.userProposal?.isCkycVerified === "Y" && prefillAndResubmit) {
      const icverifiedData =
        typeof temp_data?.userProposal?.ckycMetaData === "string"
          ? JSON.parse(temp_data?.userProposal?.ckycMetaData)
          : temp_data?.userProposal?.ckycMetaData;
      if (icverifiedData?.customer_details) {
        setVerifiedData(Object.keys(icverifiedData?.customer_details));
        Object.keys(icverifiedData?.customer_details)?.forEach((each) => {
          icverifiedData?.customer_details[each] &&
            setValue(each, icverifiedData?.customer_details[each]);
          //check for pincode
          if (
            icverifiedData?.customer_details?.pincode &&
            owner?.pincode &&
            icverifiedData?.customer_details?.pincode * 1 !== owner?.pincode * 1
          ) {
            setValue("state", "");
            setValue("stateId", "");
            setValue("city", "");
            setValue("cityId", "");
            setValue("pincode", icverifiedData?.customer_details?.pincode);
          }
        });
        companyAlias === "edelweiss" && setResubmit(true);
      } else {
        companyAlias === "edelweiss" && setResubmit(true);
      }
    }
  }, [temp_data]);
};
