import React from "react";
import { Col } from "react-bootstrap";
import Style from "../../style";
import moment from "moment";
import { subMonths } from "date-fns";
import { ImPencil } from "react-icons/im";
import _ from "lodash";

const PrevPolicyExpiry = ({
  lessthan600,
  isMobileIOS,
  userData,
  newCar,
  tempData,
  setEditDate,
  setPrevPopup,
  isEditable
}) => {
  return (
    <Col lg={6} md={6} sm={6} xs="6" className={lessthan600 ? "px-0 mx-0" : ""}>
      <Style.FilterMobileTopItem
        isMobileIOS={isMobileIOS}
        onClick={
          import.meta.env.VITE_BROKER !== "BAJAJ" ||
          (userData?.temp_data?.corporateVehiclesQuoteRequest?.frontendTags &&
            import.meta.env.VITE_BROKER === "BAJAJ")
            ? () => {
                document.getElementById("prevAndEditPopId") &&
                  document.getElementById("prevAndEditPopId") !== undefined &&
                  document.getElementById("prevAndEditPopId").click();
              }
            : () => {}
        }
      >
        <span className="rtoNameMobile">PREV POLICY EXP:</span>
        <span className="rtoNameMobile noWrapExpiry" name="prev_policy_exp">
          {userData?.temp_data?.currentPolicyType === "newbusiness" || newCar
            ? "N/A"
            : userData?.temp_data?.breakIn
            ? userData?.temp_data?.expiry === "NEW" ||
              moment(subMonths(new Date(Date.now()), 9)).format(
                "DD-MM-YYYY"
              ) === userData?.temp_data?.expiry
              ? "N/A"
              : userData?.temp_data?.expiry &&
                !_.isEmpty(userData?.temp_data?.expiry.split("-")) &&
                Number(userData?.temp_data?.expiry.split("-")?.length) >= 2
              ? `${userData?.temp_data?.expiry.split("-")[0]}/${
                  userData?.temp_data?.expiry.split("-")[1]
                }/${userData?.temp_data?.expiry?.split("-")[2]?.slice(-2)}`
              : ""
            : userData?.temp_data?.expiry &&
              !_.isEmpty(userData?.temp_data?.expiry.split("-")) &&
              Number(userData?.temp_data?.expiry.split("-")?.length) >= 2
            ? `${userData?.temp_data?.expiry.split("-")[0]}/${
                userData?.temp_data?.expiry.split("-")[1]
              }/${userData?.temp_data?.expiry?.split("-")[2]?.slice(-2)}` ||
              "NEW"
            : ""}{" "}
          <ImPencil
            style={{
              visibility: `${
                isEditable
                  ? "visible"
                  : "hidden"
              }`,
            }}
            className="editImageMobile"
            onClick={() => {
              setPrevPopup(true);
              setEditDate(true);
            }}
          />
        </span>
      </Style.FilterMobileTopItem>
    </Col>
  );
};

export default PrevPolicyExpiry;
