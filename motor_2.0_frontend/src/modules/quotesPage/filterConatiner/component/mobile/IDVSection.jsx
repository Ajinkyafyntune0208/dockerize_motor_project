import React from "react";
import Style from "../../style";
import { currencyFormater } from "utils";

const IDVSection = ({
  isMobileIOS,
  userData,
  setIdvPopup,
  tempData,
  getLowestIdv,
}) => {
  return (
    <Style.FilterMobileBottomItem
      isMobileIOS={isMobileIOS}
      onClick={() =>
        userData?.temp_data?.tab !== "tab2" &&
        (userData?.temp_data?.corporateVehiclesQuoteRequest?.isRenewal !==
          "Y" ||
          userData?.temp_data?.renewalAttributes?.idv) &&
        setIdvPopup(true)
      }
    >
      {userData?.temp_data?.isOdDiscountApplicable ||
      userData?.temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y" ||
      userData?.temp_data?.tab === "tab2" ? (
        <div className="selection">
          <span className="selectionText">
            {userData?.temp_data?.isOdDiscountApplicable ||
            userData?.temp_data?.corporateVehiclesQuoteRequest?.isRenewal ===
              "Y" ||
            userData?.temp_data?.tab === "tab2" ? (
              userData?.temp_data?.tab !== "tab2" ? (
                tempData?.idvChoosed ? (
                  `₹ ${currencyFormater(tempData?.idvChoosed)}`
                ) : (
                  `₹ ${currencyFormater(getLowestIdv())}`
                )
              ) : (
                "Not Applicable"
              )
            ) : (
              <>
                SELECT IDV
                <i
                  className="fa fa-angle-down arrowDown"
                  aria-hidden="true"
                  style={{ fontSize: "18px !important" }}
                ></i>
              </>
            )}
          </span>
          {userData?.temp_data?.tab !== "tab2" && (
            <i
              className="fa fa-angle-down arrowDown"
              aria-hidden="true"
              fontSize="15px"
              onClick={() =>
                userData?.temp_data?.tab !== "tab2" &&
                (userData?.temp_data?.corporateVehiclesQuoteRequest
                  ?.isRenewal !== "Y" ||
                  userData?.temp_data?.renewalAttributes?.idv) &&
                setIdvPopup(true)
              }
            ></i>
          )}
        </div>
      ) : (
        <div className="caption ">
          SELECT IDV{" "}
          <i
            className="fa fa-angle-down arrowDown"
            aria-hidden="true"
            fontSize="15px"
          ></i>
        </div>
      )}
    </Style.FilterMobileBottomItem>
  );
};

export default IDVSection;
