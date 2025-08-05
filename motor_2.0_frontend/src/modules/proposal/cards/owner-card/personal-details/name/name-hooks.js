import { useEffect } from 'react';
import _ from 'lodash';

export const useNameSplitting = (FullName, setValue, temp_data) => {
  //splitting fullname
  useEffect(() => {
    if (FullName) {
      let FullnameCheck = FullName.split(" ");
      if (!_.isEmpty(FullnameCheck) && FullnameCheck?.length === 1) {
        let fname = FullnameCheck[0];
        setValue("firstName", fname);
      }
      if (!_.isEmpty(FullnameCheck) && FullnameCheck?.length > 1) {
        let fname = FullnameCheck.slice(0, -1).join(" ");
        let lname = FullnameCheck.slice(-1)[0];
        setValue("firstName", fname);
        setValue("lastName", lname);
      } else {
        setValue("lastName", "");
      }
    } else {
      if (
        !_.isEmpty(temp_data?.userProposal) &&
        temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType !== "C"
      ) {
        setValue("firstName", "");
        setValue("lastName", "");
      }
    }
  }, [FullName]);
}

export const useResetOnOwnertypeChange = (temp_data, CardData, prevOwnerType, setValue) => {
  useEffect(() => {
    if (
      !_.isEmpty(CardData?.owner) &&
      !_.isEmpty(temp_data) &&
      prevOwnerType &&
      temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType !==
      prevOwnerType
    ) {
      setTimeout(() => {
        //resetting fName and lName when prevOwnerType is diff from quote Page
        setValue("firstName", "");
        setValue("lastName", "");
        //Change the prevOwnerType
        setValue(
          "prevOwnerType",
          temp_data?.corporateVehiclesQuoteRequest?.vehicleOwnerType
        );
      }, 0);
    }
  }, [temp_data, CardData, prevOwnerType]);
}