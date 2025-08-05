import { useEffect } from "react";
import _ from 'lodash';

export const usePrefillLeadDetails = (temp_data, CardData, owner, fields, setValue) => {
     //Personal Details
  useEffect(() => {
    if (_.isEmpty(owner) && _.isEmpty(CardData?.owner) && !_.isEmpty(fields)) {
      if (Number(temp_data?.ownerTypeId) === 1) {
        if (temp_data?.firstName) {
          setValue("firstName", temp_data?.firstName);
        }

        temp_data?.firstName &&
          setValue(
            "fullName",
            `${temp_data?.firstName}${temp_data?.lastName ? ` ${temp_data?.lastName}` : ""
            }`
          );
      }
      setValue(
        "lastName",
        Number(temp_data?.ownerTypeId) === 1
          ? temp_data?.lastName
          : temp_data?.firstName && temp_data?.lastName
            ? `${temp_data?.firstName} ${temp_data?.lastName}`
            : ""
      );
      setValue("email", temp_data?.emailId);
      setValue("mobileNumber", temp_data?.mobileNo);
    }
  }, [temp_data, fields]);
}