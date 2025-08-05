import { useEffect } from "react";
import _ from "lodash";

export const useOngridPrefill = ({ temp_data, CardData, owner, setValue }) => {
  useEffect(() => {
    if (_.isEmpty(CardData?.owner) && _.isEmpty(owner)) {
      if (!_.isEmpty(temp_data)) {
        let { journeyType, vehicleOwnerType } =
          temp_data?.corporateVehiclesQuoteRequest || {};
        //prettier-ignore
        let {
          firstName,  lastName, fullName, addressLine1, addressLine2,
          addressLine3, pincode, dob, genderName, gender,
          gstNumber, panNumber, occupation, occupationName,
        } = temp_data?.userProposal || {};

        setTimeout(() => {
          if (journeyType === "adrila" && vehicleOwnerType === "C") {
            (firstName || lastName) &&
              setValue(
                "firstName",
                `${firstName ? firstName : ""}${lastName ? lastName : ""}`
              );
          } else {
            firstName && setValue("firstName", firstName);
            lastName && setValue("lastName", lastName);
          }
          ((firstName && lastName) || fullName) &&
            setValue(
              "fullName",
              `${fullName ? fullName : `${firstName} ${lastName}`}`
            );
          (addressLine1 || addressLine2 || addressLine3) &&
            setValue(
              "address",
              `${addressLine1 ? addressLine1 : ""}${
                addressLine2 ? ` ${addressLine2}` : ""
              }${addressLine3 ? ` ${addressLine3}` : ""}`
            );
          pincode && setValue("pincode", pincode);
          dob && setValue("dob", dob);
          genderName && setValue("genderName", genderName);
          gender && setValue("gender", gender);
          gstNumber && setValue("gstNumber", gstNumber);
          panNumber && setValue("panNumber", panNumber);
          occupation && setValue("occupation", occupation);
          occupationName && setValue("occupationName", occupationName);
        }, 500);
      }
    }
  }, [temp_data?.userProposal]);
};
