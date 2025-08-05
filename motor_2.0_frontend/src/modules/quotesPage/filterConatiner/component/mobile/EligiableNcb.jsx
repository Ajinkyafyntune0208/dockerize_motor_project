import { differenceInDays } from "date-fns";
import moment from "moment";
import React from "react";
import Style from "../../style";
import { toDate } from "utils";

const EligibleNcb = ({
  isMobileIOS,
  userData,
  location,
  type,
  tempData,
  newCar,
  setNcbPopup,
}) => {
  const isNcbEditable =
  // userData.temp_data?.tab !== "tab2" &&
  // tempData.policyType !== "Third-party" &&
  !newCar &&
  userData?.temp_data?.expiry &&
  //Renewal config
  (userData?.temp_data?.renewalAttributes?.ncb ||
    userData?.temp_data?.renewalAttributes?.claim ||
    userData?.temp_data?.renewalAttributes?.ownership ||
    userData?.temp_data?.corporateVehiclesQuoteRequest?.isRenewal !== "Y"); 

  return (
    <Style.FilterMobileBottomItem
      isMobileIOS={isMobileIOS}
      onClick={
        true
          ? () => {
              isNcbEditable &&
                document.getElementById("ncbPopupId") &&
                document.getElementById("ncbPopupId").click !== undefined &&
                document.getElementById("ncbPopupId").click();
            }
          : () => {}
      }
    >
      <div className="caption ">ELIGIBLE NCB</div>
      <div className="selection ">
        <span className="selectionText" name="eligible_ncb">
          {" "}
          {userData.temp_data?.newNcb
            ? userData.temp_data?.tab === "tab2"
              ? "NA"
              : userData.temp_data?.newCar
              ? "0%"
              : userData?.temp_data?.newNcb
            : "0%"}{" "}
        </span>
        {location.pathname === `/${type}/quotes` &&
          // userData.temp_data?.tab !== "tab2" &&
          // tempData.policyType !== "Third-party" &&
          !newCar &&
          userData?.temp_data?.expiry &&
          isNcbEditable &&
          differenceInDays(
            toDate(moment().format("DD-MM-YYYY")),
            toDate(userData?.temp_data?.expiry)
          ) <= 90 && (
            <i
              className="fa fa-angle-down arrowDown"
              aria-hidden="true"
              fontSize="15px"
              id="ncbPopupId"
              onClick={() => setNcbPopup(true)}
            ></i>
          )}
      </div>
    </Style.FilterMobileBottomItem>
  );
};

export default EligibleNcb;
