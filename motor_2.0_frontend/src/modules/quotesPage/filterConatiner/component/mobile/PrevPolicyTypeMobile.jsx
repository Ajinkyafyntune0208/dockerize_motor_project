import React from "react";
import Style from "../../style";
import _ from "lodash";

const PrevPolicyTypeMobile = ({
  isMobileIOS,
  userData,
  lessthan400,
  lessthan600,
  newCar,
  setPolicyPopup,
  tempData,
  bundledPolicy,
  prevPolicy,
}) => {
  return (
    <Style.FilterMobileBottomItem
      isMobileIOS={isMobileIOS}
      onClick={
        userData?.temp_data?.corporateVehiclesQuoteRequest?.isRenewal !== "Y" ||
        (userData?.temp_data?.corporateVehiclesQuoteRequest?.frontendTags &&
          import.meta.env.VITE_BROKER === "BAJAJ")
          ? () => {
              document.getElementById("policyPopupId") &&
                document.getElementById("policyPopupId").click !== undefined &&
                document.getElementById("policyPopupId").click();
            }
          : () => {}
      }
    >
      <div className="caption ">{"PREV POLICY TYPE"}</div>
      <div className="selection ">
        <span
          name="prev_policy_type"
          className="selectionText"
          style={
            tempData?.policyType === "Own-damage" ||
            (!bundledPolicy && userData?.temp_data?.odOnly)
              ? {}
              : bundledPolicy
              ? { fontSize: "10px" }
              : prevPolicy.toLowerCase() === "comprehensive" && lessthan400
              ? { fontSize: "9px" }
              : {}
          }
        >
          {" "}
          {tempData?.policyType === "Not sure"
            ? "Not sure".toUpperCase()
            : tempData?.policyType === "Third-party"
            ? "Third-party".toUpperCase()
            : tempData?.policyType === "Own-damage" ||
              (!bundledPolicy && userData?.temp_data?.odOnly)
            ? lessthan400
              ? "OD"
              : "Own-damage".toUpperCase()
            : bundledPolicy ||
              ((tempData?.isMultiYearPolicy === "Y" ||
                userData?.temp_data?.isMultiYearPolicy === "Y") &&
                userData?.temp_data?.regDate &&
                !_.isEmpty(userData?.temp_data?.regDate.split("-")) &&
                userData?.temp_data?.regDate.split("-")?.length > 1 &&
                userData?.temp_data?.regDate.split("-")[2] * 1 === 2019 &&
                prevPolicy.toLowerCase() === "comprehensive")
            ? !lessthan600
              ? "Bundled Policy".toUpperCase()
              : "BUNDLED"
            : prevPolicy}{" "}
        </span>
        {(userData?.temp_data?.corporateVehiclesQuoteRequest?.isRenewal !==
          "Y" ||
          (userData?.temp_data?.corporateVehiclesQuoteRequest?.frontendTags &&
            import.meta.env.VITE_BROKER === "BAJAJ")) &&
          !newCar && (
            <i
              className="fa fa-angle-down arrowDown"
              aria-hidden="true"
              fontSize="15px"
              id="policyPopupId"
              onClick={() => {
                setPolicyPopup(true);
              }}
            ></i>
          )}
      </div>
    </Style.FilterMobileBottomItem>
  );
};

export default PrevPolicyTypeMobile;
