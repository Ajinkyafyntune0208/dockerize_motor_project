import React from "react";
import { Col } from "react-bootstrap";
import Style from "../style";
import { FiEdit } from "react-icons/fi";

const PrevPolicyType = ({
  userData,
  newCar,
  allQuoteloading,
  location,
  type,
  reviewData,
  tempData,
  bundledPolicy,
  prevPolicy,
  setPolicyPopup,
  setJourneyCategoryPopup,
  isEditable
}) => {
  const isPolicyTypeEditable = isEditable || tempData?.policyType === "Not sure";
  return (
    <Col lg={3} md={12}>
      <Style.FilterMenuOpenWrap>
        <Style.FilterMenuOpenSub
          name="previous_policy_type"
          style={{ cursor: "pointer" }}
          id="previous-policy-type"
          onClick={
            (userData?.temp_data?.corporateVehiclesQuoteRequest?.isRenewal !==
              "Y" &&
              !newCar) ||
            (userData?.temp_data?.corporateVehiclesQuoteRequest?.frontendTags &&
              import.meta.env.VITE_BROKER === "BAJAJ")
              ? () => {
                  document.getElementById("policyPopupId") &&
                    document.getElementById("policyPopupId").click !==
                      undefined &&
                    document.getElementById("policyPopupId").click();
                }
              : () => {}
          }
        >
          PREVIOUS POLICY TYPE:{" "}
          <Style.FilterMenuOpenSubBold
            style={{
              textTransform: "capitalize",
              pointerEvents: allQuoteloading ? "none" : "",
            }}
          >
            {
              <>
                {" "}
                {tempData?.policyType === "Not sure"
                  ? "Not sure".toUpperCase()
                  : tempData?.policyType === "Third-party"
                  ? "Third-party".toUpperCase()
                  : tempData?.policyType === "Own-damage" ||
                    (!bundledPolicy && userData?.temp_data?.odOnly)
                  ? "Own-damage".toUpperCase()
                  : bundledPolicy
                  ? "Bundled Policy".toUpperCase()
                  : prevPolicy}
                {}{" "}
              </>
            }{" "}
            { isPolicyTypeEditable  && (
                <FiEdit
                  onClick={() => {
                    setPolicyPopup(true);
                  }}
                  id="policyPopupId"
                  className="blueIcon"
                />
              )}
          </Style.FilterMenuOpenSubBold>
        </Style.FilterMenuOpenSub>
        <Style.FilterMenuOpenEdit>
          <Style.FilterMenuOpenTitle
            id="ownership-popup"
            onClick={
              userData?.temp_data?.corporateVehiclesQuoteRequest?.isRenewal !==
                "Y" ||
              (userData?.temp_data?.corporateVehiclesQuoteRequest
                ?.frontendTags &&
                import.meta.env.VITE_BROKER === "BAJAJ")
                ? () => {
                    setJourneyCategoryPopup(true);
                  }
                : () => {}
            }
          >
            OWNERSHIP:
            <Style.FilterMenuOpenSubBold>
              {" "}
              {location.pathname === `/${type}/quotes` && (
                <span name="ownership">
                  {userData?.temp_data?.ownerTypeId === 2
                    ? "COMPANY "
                    : "INDIVIDUAL "}

                  {(userData?.temp_data?.corporateVehiclesQuoteRequest
                    ?.isRenewal !== "Y" ||
                    (userData?.temp_data?.corporateVehiclesQuoteRequest
                      ?.frontendTags &&
                      import.meta.env.VITE_BROKER === "BAJAJ")) && (
                    <FiEdit
                      className="blueIcon"
                      onClick={() => {
                        setJourneyCategoryPopup(true);
                      }}
                    />
                  )}
                </span>
              )}{" "}
            </Style.FilterMenuOpenSubBold>
          </Style.FilterMenuOpenTitle>
        </Style.FilterMenuOpenEdit>
      </Style.FilterMenuOpenWrap>
    </Col>
  );
};

export default PrevPolicyType;
